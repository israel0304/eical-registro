<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopEnrollment;
use App\Services\EventAudit;
use App\Support\EventSettings;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkshopEnrollmentController extends Controller
{
    public function store(Request $request, Workshop $workshop)
    {
        $user = $request->user();

        $existingEnrollment = WorkshopEnrollment::where('user_id', $user->id)
            ->where('workshop_id', $workshop->id)
            ->first();

        if ($existingEnrollment) {
            if ($existingEnrollment->status === 'enrolled') {
                return back()->withErrors(['error' => 'Ya estás inscrito en este taller.']);
            }

            if ($existingEnrollment->status === 'cancelled') {
                if (! $workshop->hasAvailableSpots()) {
                    return back()->withErrors(['error' => 'No hay cupos disponibles.']);
                }

                $conflict = $this->conflictingWorkshop($user, $workshop);

                if ($conflict) {
                    return back()->withErrors(['error' => $this->conflictMessage($conflict)]);
                }

                $existingEnrollment->update([
                    'status' => 'enrolled',
                    'enrolled_at' => now(),
                ]);

                $this->notifyEnrollment($existingEnrollment, $workshop, $user);

                return back()->with('success', 'Inscripción reactivada correctamente.');
            }
        }

        if (! $workshop->hasAvailableSpots()) {
            return back()->withErrors(['error' => 'No hay cupos disponibles en este taller.']);
        }

        $conflict = $this->conflictingWorkshop($user, $workshop);

        if ($conflict) {
            return back()->withErrors(['error' => $this->conflictMessage($conflict)]);
        }

        $enrollment = WorkshopEnrollment::create([
            'user_id' => $user->id,
            'workshop_id' => $workshop->id,
            'enrolled_at' => now(),
            'status' => 'enrolled',
        ]);

        $this->notifyEnrollment($enrollment, $workshop, $user);

        return back()->with('success', 'Inscrito correctamente en el taller.');
    }

    private function notifyEnrollment(WorkshopEnrollment $enrollment, Workshop $workshop, User $user): void
    {
        EventAudit::emit('workshop.enrollment', $enrollment, request()->user(), [
            'destinatario' => $user->email,
            'nombre_completo' => $user->name,
            'taller' => $workshop->name,
            'dia' => $workshop->day,
            'hora_inicio' => $workshop->start_time,
            'hora_fin' => $workshop->end_time,
            'lugar' => $workshop->location,
        ]);
    }

    private function notifyEnrollmentCancelled(WorkshopEnrollment $enrollment, Workshop $workshop, User $user): void
    {
        EventAudit::emit('workshop.enrollment_cancelled', $enrollment, request()->user(), [
            'destinatario' => $user->email,
            'nombre_completo' => $user->name,
            'taller' => $workshop->name,
            'dia' => $workshop->day,
            'hora_inicio' => $workshop->start_time,
            'hora_fin' => $workshop->end_time,
            'lugar' => $workshop->location,
        ]);
    }

    public function adminStore(Request $request, Workshop $workshop)
    {
        $this->authorizeManualEnrollment($request->user(), $workshop);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $user = User::findOrFail($validated['user_id']);

        $existingEnrollment = WorkshopEnrollment::where('user_id', $user->id)
            ->where('workshop_id', $workshop->id)
            ->first();

        if ($existingEnrollment && $existingEnrollment->status === 'enrolled') {
            return back()->withErrors(['error' => 'El usuario ya está inscrito en este taller.']);
        }

        $conflict = $this->conflictingWorkshop($user, $workshop);

        if ($conflict) {
            return back()->withErrors([
                'conflict' => true,
                'conflicting_workshop' => [
                    'id' => $conflict->id,
                    'name' => $conflict->name,
                    'day' => $conflict->day,
                    'start_time' => $conflict->start_time,
                    'end_time' => $conflict->end_time,
                ],
                'error' => $this->conflictMessage($conflict),
            ]);
        }

        if (! $workshop->hasAvailableSpots()) {
            return back()->withErrors([
                'cap_full' => true,
                'error' => "El taller está lleno ({$workshop->enrolledCount()} / {$workshop->capacity} cupos).",
            ]);
        }

        if ($existingEnrollment && $existingEnrollment->status === 'cancelled') {
            $existingEnrollment->update([
                'status' => 'enrolled',
                'enrolled_at' => now(),
            ]);

            $this->notifyEnrollment($existingEnrollment, $workshop, $user);

            return back()->with('success', 'Inscripción registrada correctamente.');
        }

        $enrollment = WorkshopEnrollment::create([
            'user_id' => $user->id,
            'workshop_id' => $workshop->id,
            'enrolled_at' => now(),
            'status' => 'enrolled',
        ]);

        $this->notifyEnrollment($enrollment, $workshop, $user);

        return back()->with('success', 'Inscripción registrada correctamente.');
    }

    public function searchUsers(Request $request, Workshop $workshop)
    {
        $this->authorizeManualEnrollment($request->user(), $workshop);

        $search = trim((string) $request->input('search', ''));

        if ($search === '') {
            return response()->json([]);
        }

        return User::where(function ($q) use ($search) {
            $q->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('dni', 'like', "%{$search}%");
        })
            ->limit(20)
            ->get(['id', 'first_name', 'last_name', 'email', 'dni']);
    }

    public function destroy(Request $request, Workshop $workshop)
    {
        $enrollment = WorkshopEnrollment::where('user_id', $request->user()->id)
            ->where('workshop_id', $workshop->id)
            ->where('status', 'enrolled')
            ->first();

        if (! $enrollment) {
            return back()->withErrors(['error' => 'No estás inscrito en este taller.']);
        }

        $hasAttendance = Attendance::where('user_id', $request->user()->id)
            ->where('workshop_id', $workshop->id)
            ->exists();

        if ($hasAttendance) {
            return back()->withErrors(['error' => 'No puedes cancelar: ya tienes asistencia confirmada en este taller.']);
        }

        $windowStart = Carbon::parse("{$workshop->day} {$workshop->start_time}", EventSettings::timezone())
            ->subHours(EventSettings::checkinGraceHours());

        if (now()->gte($windowStart)) {
            return back()->withErrors(['error' => 'No puedes cancelar: el taller está dentro del margen de registro ('.EventSettings::checkinGraceHours().' h antes del inicio) o en curso.']);
        }

        $enrollment->update(['status' => 'cancelled']);

        $this->notifyEnrollmentCancelled($enrollment, $workshop, $request->user());

        return back()->with('success', 'Inscripción cancelada correctamente.');
    }

    public function adminDestroy(Request $request, Workshop $workshop, WorkshopEnrollment $enrollment)
    {
        $this->authorizeManualEnrollment($request->user(), $workshop);

        if ($enrollment->workshop_id !== $workshop->id) {
            abort(404);
        }

        $enrollment->update(['status' => 'cancelled']);

        if ($enrollment->user) {
            $this->notifyEnrollmentCancelled($enrollment, $workshop, $enrollment->user);
        }

        return back()->with('success', 'Inscripción cancelada correctamente.');
    }

    public function myWorkshops(Request $request)
    {
        $user = $request->user();

        $workshops = Workshop::with('instructors')->whereHas('enrollments', function ($q) use ($user) {
            $q->where('user_id', $user->id)->where('status', 'enrolled');
        })->get();

        $attendedWorkshopIds = Attendance::where('user_id', $user->id)
            ->whereIn('workshop_id', $workshops->pluck('id'))
            ->pluck('workshop_id')
            ->all();

        $workshops = $workshops->map(function ($workshop) use ($attendedWorkshopIds) {
            $workshop->has_attendance = in_array($workshop->id, $attendedWorkshopIds, true);

            return $workshop;
        });

        $isInstructor = $user->hasRole('Instructor');

        $instructorWorkshops = $isInstructor
            ? Workshop::with('instructors')
                ->whereHas('instructors', function ($q) use ($user) {
                    $q->whereKey($user->id);
                })
                ->orderBy('day')
                ->orderBy('start_time')
                ->get()
            : collect();

        return Inertia::render('Workshops/MyWorkshops', [
            'workshops' => $workshops,
            'instructorWorkshops' => $instructorWorkshops,
        ]);
    }

    private function authorizeManualEnrollment(User $user, Workshop $workshop): void
    {
        $isInstructor = $workshop->instructors()->whereKey($user->id)->exists();
        $isModerator = $workshop->moderators()->whereKey($user->id)->exists();

        abort_unless($user->can('workshops.enrollments') || $isInstructor || $isModerator, 403);
    }

    private function conflictingWorkshop(User $user, Workshop $workshop): ?Workshop
    {
        return $user->enrolledWorkshops()
            ->wherePivot('status', 'enrolled')
            ->where('workshops.id', '!=', $workshop->id)
            ->where('workshops.day', $workshop->day)
            ->where('workshops.start_time', '<', $workshop->end_time)
            ->where('workshops.end_time', '>', $workshop->start_time)
            ->first();
    }

    private function conflictMessage(Workshop $conflict): string
    {
        $start = $conflict->start_time ? substr($conflict->start_time, 0, 5) : '';
        $end = $conflict->end_time ? substr($conflict->end_time, 0, 5) : '';

        return 'Ya estás inscrito en el taller "'.$conflict->name.'" de '.$start.' a '.$end.'. No puedes inscribirte a dos talleres al mismo horario.';
    }
}
