<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\Conference;
use App\Models\Presentation;
use App\Models\User;
use App\Models\Workshop;
use App\Services\CertificateRenderer;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ConstanciaController extends Controller
{
    public function __construct(private readonly CertificateRenderer $renderer) {}

    public function myCertificates(Request $request)
    {
        $user = $request->user();

        $workshopIds = Workshop::whereHas('enrollments', function ($q) use ($user) {
            $q->where('user_id', $user->id)->where('status', 'enrolled');
        })->pluck('id');

        $completedWorkshops = Workshop::whereIn('id', $workshopIds)
            ->whereHas('attendances', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->with('instructors')
            ->withCount(['attendances as attendance_count' => function ($q) use ($user) {
                $q->where('user_id', $user->id);
            }])
            ->get();

        $instructorWorkshops = Workshop::whereHas('instructors', function ($q) use ($user) {
            $q->where('users.id', $user->id);
        })
            ->with(['instructors' => function ($q) use ($user) {
                $q->where('users.id', $user->id);
            }])
            ->get();

        $presentationCertificates = Presentation::whereHas('authors', function ($q) use ($user) {
            $q->where('users.id', $user->id)
                ->where('presentation_authors.presented', true);
        })
            ->with(['authors' => function ($q) {
                $q->where('presentation_authors.presented', true)
                    ->withPivot('presented', 'presented_at');
            }])
            ->get();

        $conferenceCertificates = Conference::whereHas('members', function ($q) use ($user) {
            $q->where('users.id', $user->id);
        })
            ->with(['members' => function ($q) use ($user) {
                $q->where('users.id', $user->id)->withPivot('role', 'activated', 'activated_at');
            }])
            ->orderBy('day')
            ->get();

        $certificates = Certificate::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy(fn ($certificate) => $certificate->event_type.'-'.$certificate->event_id);

        foreach ($completedWorkshops as $workshop) {
            $workshop->folio = $certificates->get('workshop-'.$workshop->id)?->folio;
        }

        foreach ($instructorWorkshops as $workshop) {
            $workshop->folio = $certificates->get('workshop-'.$workshop->id)?->folio;
        }

        foreach ($presentationCertificates as $presentation) {
            $presentation->folio = $certificates->get('presentation-'.$presentation->id)?->folio;
        }

        foreach ($conferenceCertificates as $conference) {
            $conference->folio = $certificates->get('conference-'.$conference->id)?->folio;
            $conference->activated = (bool) $conference->members->first()?->pivot->activated;
            $conference->member_role = $conference->members->first()?->pivot->role;
        }

        return Inertia::render('Constancias/Index', [
            'completedWorkshops' => $completedWorkshops,
            'instructorWorkshops' => $instructorWorkshops,
            'presentationCertificates' => $presentationCertificates,
            'conferenceCertificates' => $conferenceCertificates,
            'user' => $user,
        ]);
    }

    public function download(Request $request, $id)
    {
        $user = $request->user();
        $workshop = Workshop::findOrFail($id);

        $isEnrolled = $workshop->enrollments()
            ->where('user_id', $user->id)
            ->where('status', 'enrolled')
            ->exists();

        if (! $isEnrolled) {
            return back()->withErrors(['error' => 'No estás inscrito en este taller.']);
        }

        $hasAttendance = $workshop->attendances()->where('user_id', $user->id)->exists();

        if (! $hasAttendance) {
            return back()->withErrors(['error' => 'Tu asistencia aún no ha sido verificada.']);
        }

        $certificate = $this->renderer->issue($user, 'workshop', $workshop);

        if ($certificate === null) {
            return back()->withErrors(['error' => 'No fue posible generar la constancia.']);
        }

        $certificate->update(['downloaded_at' => now()]);

        return $this->respondWithHtml($certificate);
    }

    public function adminDownload($workshopId, $userId)
    {
        abort_if(! request()->user()->isAdmin(), 403);

        $workshop = Workshop::findOrFail($workshopId);
        $user = User::findOrFail($userId);

        $hasAttendance = $workshop->attendances()->where('user_id', $user->id)->exists();

        if (! $hasAttendance) {
            return back()->withErrors(['error' => 'El usuario no tiene asistencia verificada en este taller.']);
        }

        $certificate = $this->renderer->issue($user, 'workshop', $workshop);

        if ($certificate === null) {
            return back()->withErrors(['error' => 'No fue posible generar la constancia.']);
        }

        $certificate->update(['downloaded_at' => now()]);

        return $this->respondWithHtml($certificate);
    }

    public function downloadPonencia(Request $request, Presentation $presentation)
    {
        $user = $request->user();

        $presented = $presentation->authors()
            ->where('users.id', $user->id)
            ->wherePivot('presented', true)
            ->exists();

        if (! $presented) {
            return back()->withErrors(['error' => 'No tienes una ponencia presentada para descargar esta constancia.']);
        }

        $certificate = $this->renderer->issue($user, 'presentation', $presentation);

        if ($certificate === null) {
            return back()->withErrors(['error' => 'No fue posible generar la constancia.']);
        }

        $certificate->update(['downloaded_at' => now()]);

        return $this->respondWithHtml($certificate);
    }

    public function adminDownloadPonencia(Presentation $presentation, User $user)
    {
        abort_if(! request()->user()->isAdmin(), 403);

        $certificate = $this->renderer->issue($user, 'presentation', $presentation);

        if ($certificate === null) {
            return back()->withErrors(['error' => 'No fue posible generar la constancia.']);
        }

        $certificate->update(['downloaded_at' => now()]);

        return $this->respondWithHtml($certificate);
    }

    public function downloadConferencia(Request $request, Conference $conference)
    {
        $user = $request->user();

        $activated = $conference->members()
            ->where('users.id', $user->id)
            ->wherePivot('activated', true)
            ->exists();

        if (! $activated) {
            return back()->withErrors(['error' => 'Tu constancia aún no ha sido activada.']);
        }

        $certificate = $this->renderer->issue($user, 'conference', $conference);

        if ($certificate === null) {
            return back()->withErrors(['error' => 'No fue posible generar la constancia.']);
        }

        $certificate->update(['downloaded_at' => now()]);

        return $this->respondWithHtml($certificate);
    }

    public function adminDownloadConferencia(Conference $conference, User $user)
    {
        abort_if(! request()->user()->isAdmin(), 403);

        $certificate = $this->renderer->issue($user, 'conference', $conference);

        if ($certificate === null) {
            return back()->withErrors(['error' => 'No fue posible generar la constancia.']);
        }

        $certificate->update(['downloaded_at' => now()]);

        return $this->respondWithHtml($certificate);
    }

    public function downloadPdf(Request $request, Certificate $certificate)
    {
        $user = $request->user();

        abort_unless($certificate->user_id === $user->id || $user->isAdmin(), 403);

        return response($this->renderer->renderPdf($certificate), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename=constancia_'.$certificate->folio.'.pdf',
        ]);
    }

    private function respondWithHtml(Certificate $certificate)
    {
        $html = $this->renderer->render($certificate);

        return response($html, 200, [
            'Content-Type' => 'text/html',
            'Content-Disposition' => 'inline; filename=constancia_'.$certificate->folio.'.html',
        ]);
    }
}
