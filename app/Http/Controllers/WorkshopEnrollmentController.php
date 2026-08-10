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
        abort_unless($request->user()->can('workshops.enrollments'), 403);

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
        })->get()->map(function ($workshop) use ($user) {
            $workshop->has_attendance = Attendance::where('user_id', $user->id)
                ->where('workshop_id', $workshop->id)
                ->exists();

            return $workshop;
        });

        return Inertia::render('Workshops/MyWorkshops', [
            'workshops' => $workshops,
        ]);
    }
}
