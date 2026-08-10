<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopEnrollment;
use App\Services\CertificateRenderer;
use App\Services\EventAudit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AttendanceController extends Controller
{
    public function __construct(private readonly CertificateRenderer $renderer) {}

    public function scan(Request $request, Workshop $workshop)
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login')->withIntendedUrl($request->url());
        }

        $enrollment = WorkshopEnrollment::where('user_id', $user->id)
            ->where('workshop_id', $workshop->id)
            ->where('status', 'enrolled')
            ->first();

        if (! $enrollment) {
            return Inertia::render('Workshops/Scan', [
                'success' => false,
                'message' => 'No estás inscrito en este taller.',
                'workshop' => ['name' => $workshop->name],
            ]);
        }

        $existingAttendance = Attendance::where('user_id', $user->id)
            ->where('workshop_id', $workshop->id)
            ->first();

        if ($existingAttendance) {
            return Inertia::render('Workshops/Scan', [
                'success' => false,
                'message' => 'Ya tienes asistencia registrada en este taller.',
                'workshop' => ['name' => $workshop->name],
            ]);
        }

        if ($workshop->qr_time_restricted) {
            $now = now();
            $eventDate = $workshop->day;
            $startTime = $workshop->start_time;
            $endTime = $workshop->end_time;

            $start = Carbon::parse("{$eventDate} {$startTime}");
            $end = Carbon::parse("{$eventDate} {$endTime}");

            if ($now->lt($start) || $now->gt($end)) {
                return Inertia::render('Workshops/Scan', [
                    'success' => false,
                    'message' => 'Fuera del horario permitido para este taller.',
                    'workshop' => ['name' => $workshop->name],
                ]);
            }
        }

        Attendance::create([
            'user_id' => $user->id,
            'workshop_id' => $workshop->id,
            'event_day' => $workshop->day,
            'registered_by' => $user->id,
        ]);

        $this->notifyAttendanceConfirmed($workshop, $user);

        return Inertia::render('Workshops/Scan', [
            'success' => true,
            'message' => 'Asistencia registrada correctamente.',
            'workshop' => ['name' => $workshop->name],
        ]);
    }

    public function toggleAttendance(Request $request, Workshop $workshop, int $userId)
    {
        $user = $request->user();
        $isAssignedModerator = $workshop->moderators()->where('users.id', $user->id)->exists();

        abort_unless($user->canScoped('workshops.attendance', 'workshops.view', $isAssignedModerator), 403);

        $isEnrolled = WorkshopEnrollment::where('user_id', $userId)
            ->where('workshop_id', $workshop->id)
            ->where('status', 'enrolled')
            ->exists();

        if (! $isEnrolled) {
            return back()->withErrors(['error' => 'El usuario no está inscrito en este taller.']);
        }

        $existing = Attendance::where('user_id', $userId)
            ->where('workshop_id', $workshop->id)
            ->first();

        if ($existing) {
            $existing->delete();

            return back()->with('success', 'Asistencia removida correctamente.');
        }

        Attendance::create([
            'user_id' => $userId,
            'workshop_id' => $workshop->id,
            'event_day' => $workshop->day,
            'registered_by' => $request->user()->id,
        ]);

        $this->notifyAttendanceConfirmed($workshop, User::find($userId));

        return back()->with('success', 'Asistencia marcada correctamente.');
    }

    private function notifyAttendanceConfirmed(Workshop $workshop, ?User $user): void
    {
        if (! $user) {
            return;
        }

        EventAudit::emit('attendance.confirmed', $workshop, request()->user(), [
            'destinatario' => $user->email,
            'nombre_completo' => $user->name,
            'taller' => $workshop->name,
            'dia' => $workshop->day,
            'hora_inicio' => $workshop->start_time,
            'hora_fin' => $workshop->end_time,
            'lugar' => $workshop->location,
        ]);
    }

    public function sendQRToInstructor(Request $request, Workshop $workshop)
    {
        abort_unless($request->user()->can('workshops.qr.send'), 403);

        $validated = $request->validate([
            'user_id' => 'required|exists:workshop_instructor_user,user_id',
        ]);

        $instructor = User::findOrFail($validated['user_id']);

        $belongsToWorkshop = $workshop->instructors()->where('user_id', $instructor->id)->exists();
        if (! $belongsToWorkshop) {
            abort(404);
        }

        $scanUrl = route('workshops.scan', $workshop);
        $qrImage = $this->renderer->qrDataUri($scanUrl, png: true);

        EventAudit::emit('workshop.qr_sent', $workshop, $request->user(), $this->qrPayload($workshop, $instructor, $scanUrl, $qrImage));

        return back()->with('success', "Código QR enviado a {$instructor->email}.");
    }

    public function sendQRToAll(Request $request, Workshop $workshop)
    {
        abort_unless($request->user()->can('workshops.qr.send'), 403);

        $instructors = $workshop->instructors()->whereNotNull('email')->where('email', '!=', '')->get();

        if ($instructors->isEmpty()) {
            return back()->withErrors(['error' => 'No hay instructores con correo electrónico para este taller.']);
        }

        $scanUrl = route('workshops.scan', $workshop);
        $qrImage = $this->renderer->qrDataUri($scanUrl, png: true);

        foreach ($instructors as $instructor) {
            EventAudit::emit('workshop.qr_sent', $workshop, $request->user(), $this->qrPayload($workshop, $instructor, $scanUrl, $qrImage));
        }

        return back()->with('success', "Código QR enviado a {$instructors->count()} instructor(es).");
    }

    private function qrPayload(Workshop $workshop, User $instructor, string $scanUrl, string $qrImage): array
    {
        return [
            'destinatario' => $instructor->email,
            'nombre_completo' => $instructor->name,
            'taller' => $workshop->name,
            'dia' => $workshop->day,
            'hora_inicio' => $workshop->start_time,
            'hora_fin' => $workshop->end_time,
            'lugar' => $workshop->location,
            'url_escaneo' => $scanUrl,
            'qrImage' => $qrImage,
        ];
    }
}
