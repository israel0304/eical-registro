<?php

namespace App\Http\Controllers;

use App\Mail\WorkshopQRForInstructor;
use App\Models\Attendance;
use App\Models\Instructor;
use App\Models\Workshop;
use App\Models\WorkshopEnrollment;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class AttendanceController extends Controller
{
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

        return Inertia::render('Workshops/Scan', [
            'success' => true,
            'message' => 'Asistencia registrada correctamente.',
            'workshop' => ['name' => $workshop->name],
        ]);
    }

    public function toggleAttendance(Request $request, Workshop $workshop, int $userId)
    {
        abort_if(! $request->user()->isAdmin(), 403);

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

        return back()->with('success', 'Asistencia marcada correctamente.');
    }

    public function sendQRToInstructor(Request $request, Workshop $workshop)
    {
        abort_if(! $request->user()->isAdmin(), 403);

        $validated = $request->validate([
            'instructor_id' => 'required|exists:instructors,id',
        ]);

        $instructor = Instructor::findOrFail($validated['instructor_id']);

        if (! $instructor->email) {
            return back()->withErrors(['error' => 'Este instructor no tiene correo electrónico.']);
        }

        if ($instructor->workshop_id !== $workshop->id) {
            abort(404);
        }

        $scanUrl = route('workshops.scan', $workshop);

        $renderer = new ImageRenderer(
            new RendererStyle(300),
            new SvgImageBackEnd,
        );
        $writer = new Writer($renderer);
        $qrSvg = $writer->writeString($scanUrl);
        $qrPngBase64 = $this->svgToPngBase64($qrSvg);

        Mail::to($instructor->email)->send(
            new WorkshopQRForInstructor($workshop, $instructor, $qrPngBase64, $scanUrl)
        );

        return back()->with('success', "Código QR enviado a {$instructor->email}.");
    }

    public function sendQRToAll(Request $request, Workshop $workshop)
    {
        abort_if(! $request->user()->isAdmin(), 403);

        $instructors = $workshop->instructors()->whereNotNull('email')->where('email', '!=', '')->get();

        if ($instructors->isEmpty()) {
            return back()->withErrors(['error' => 'No hay instructores con correo electrónico para este taller.']);
        }

        $scanUrl = route('workshops.scan', $workshop);

        $renderer = new ImageRenderer(
            new RendererStyle(300),
            new SvgImageBackEnd,
        );
        $writer = new Writer($renderer);
        $qrSvg = $writer->writeString($scanUrl);
        $qrPngBase64 = $this->svgToPngBase64($qrSvg);

        foreach ($instructors as $instructor) {
            Mail::to($instructor->email)->send(
                new WorkshopQRForInstructor($workshop, $instructor, $qrPngBase64, $scanUrl)
            );
        }

        return back()->with('success', "Código QR enviado a {$instructors->count()} instructor(es).");
    }

    private function svgToPngBase64(string $svg): string
    {
        $img = new \Imagick;
        $img->readImageBlob($svg);
        $img->setImageFormat('png');
        $img->setImageBackgroundColor(new \ImagickPixel('white'));
        $img->flattenImages();

        return base64_encode($img->getImageBlob());
    }
}
