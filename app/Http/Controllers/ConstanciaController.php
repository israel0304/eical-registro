<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Certificate;
use App\Models\Conference;
use App\Models\ParticipationType;
use App\Models\Presentation;
use App\Models\User;
use App\Models\Workshop;
use App\Services\CertificateRenderer;
use App\Support\EventSettings;
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

        $invitationLetters = [];
        if ($user->hasPermission('constancias.invitaciones.download')) {
            $cartaTypes = ParticipationType::query()
                ->where('event_kind', 'event')
                ->where('kind', 'carta')
                ->where('is_active', true)
                ->orderBy('label')
                ->get();

            $cartaCertificates = Certificate::query()
                ->where('user_id', $user->id)
                ->where('event_type', 'event')
                ->where('event_id', 0)
                ->whereIn('participation_type_id', $cartaTypes->pluck('id'))
                ->get()
                ->keyBy('participation_type_id');

            foreach ($cartaTypes as $cartaType) {
                $cartaCertificate = $cartaCertificates->get($cartaType->id);

                $invitationLetters[] = [
                    'id' => $cartaType->id,
                    'key' => $cartaType->key,
                    'label' => $cartaType->label,
                    'rol' => $this->renderer->cartaRolLabel($user),
                    'folio' => $cartaCertificate?->folio,
                    'downloaded' => $cartaCertificate?->downloaded_at !== null,
                ];
            }
        }

        $eventAttendanceType = ParticipationType::query()
            ->where('event_kind', 'event')
            ->whereNull('kind')
            ->where('is_active', true)
            ->first();

        $eventCertificate = $eventAttendanceType !== null
            ? Certificate::query()
                ->where('user_id', $user->id)
                ->where('participation_type_id', $eventAttendanceType->id)
                ->where('event_type', 'event')
                ->first()
            : null;

        $eventAttendance = [
            'has' => Attendance::query()
                ->where('user_id', $user->id)
                ->whereNull('workshop_id')
                ->whereNull('presentation_id')
                ->exists(),
            'days_attended' => EventSettings::attendedDays($user->id),
            'required_days' => EventSettings::minDays(),
            'total_days' => EventSettings::totalDays(),
            'qualifies' => EventSettings::qualifies($user->id),
            'evento_nombre' => EventSettings::nombre(),
            'fecha_inicio' => EventSettings::startDate(),
            'fecha_fin' => EventSettings::endDate(),
        ];

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

        $invitationActivities = [
            'presentations' => $presentationCertificates->map(fn (Presentation $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'day' => $p->day,
            ])->values(),
            'workshops' => $instructorWorkshops->map(fn (Workshop $w) => [
                'id' => $w->id,
                'name' => $w->name,
                'day' => $w->day,
            ])->values(),
            'conferences' => $conferenceCertificates->map(fn (Conference $c) => [
                'id' => $c->id,
                'title' => $c->title,
                'day' => $c->day,
            ])->values(),
        ];

        return Inertia::render('Constancias/Index', [
            'completedWorkshops' => $completedWorkshops,
            'instructorWorkshops' => $instructorWorkshops,
            'presentationCertificates' => $presentationCertificates,
            'conferenceCertificates' => $conferenceCertificates,
            'eventCertificate' => $eventCertificate,
            'eventAttendance' => $eventAttendance,
            'invitationLetters' => $invitationLetters,
            'invitationActivities' => $invitationActivities,
            'user' => $user,
        ]);
    }

    public function downloadInvitacion(Request $request)
    {
        $user = $request->user();

        if (! $user->hasPermission('constancias.invitaciones.download')) {
            return back()->withErrors(['error' => 'No tienes autorización para descargar una carta de invitación.']);
        }

        $type = $this->resolveCartaType($request->input('type'));

        if ($type === null) {
            return back()->withErrors(['error' => 'El tipo de carta de invitación no está configurado.']);
        }

        $event = null;
        $rolLabel = $this->renderer->cartaRolLabel($user);

        $eventType = $request->input('event_type');
        $eventId = (int) $request->input('event_id', 0);

        if ($eventType !== null && $eventId > 0) {
            $event = $this->resolveCartaActivity($user, (string) $eventType, $eventId);

            if ($event === null) {
                return back()->withErrors(['error' => 'No tienes una actividad válida para generar esta carta de invitación.']);
            }

            $rolLabel = $this->renderer->activityRolLabel($user, $event);
        }

        $certificate = $this->renderer->issueCarta($user, $type, $rolLabel, $event);

        if ($certificate === null) {
            return back()->withErrors(['error' => 'No fue posible generar la carta de invitación.']);
        }

        $certificate->update(['downloaded_at' => now()]);

        return $this->respondWithHtml($certificate);
    }

    private function resolveCartaType(mixed $type): ?ParticipationType
    {
        $query = ParticipationType::query()
            ->where('event_kind', 'event')
            ->where('kind', 'carta')
            ->where('is_active', true);

        if (is_numeric($type)) {
            $query->where('id', (int) $type);
        } else {
            $query->where('key', $type ?: 'carta_invitacion');
        }

        return $query->first();
    }

    private function resolveCartaActivity(User $user, string $eventType, int $eventId): Workshop|Presentation|Conference|null
    {
        if ($eventType === 'presentation') {
            $presentation = Presentation::find($eventId);
            $presented = $presentation !== null && $presentation->authors()
                ->where('users.id', $user->id)
                ->wherePivot('presented', true)
                ->exists();

            return $presented ? $presentation : null;
        }

        if ($eventType === 'workshop') {
            $workshop = Workshop::find($eventId);
            $instructor = $workshop !== null && $workshop->instructors()
                ->where('users.id', $user->id)
                ->exists();

            return $instructor ? $workshop : null;
        }

        if ($eventType === 'conference') {
            $conference = Conference::find($eventId);
            $member = $conference !== null && $conference->members()
                ->where('users.id', $user->id)
                ->exists();

            return $member ? $conference : null;
        }

        return null;
    }

    public function downloadEvento(Request $request)
    {
        $user = $request->user();

        if (! EventSettings::qualifies($user->id)) {
            return back()->withErrors(['error' => 'Aún no cumples los días mínimos de asistencia al evento (llevas '.EventSettings::attendedDays($user->id).' de '.EventSettings::minDays().').']);
        }

        $certificate = $this->renderer->issueEvent($user);

        if ($certificate === null) {
            return back()->withErrors(['error' => 'No fue posible generar la constancia.']);
        }

        $certificate->update(['downloaded_at' => now()]);

        return $this->respondWithHtml($certificate);
    }

    public function adminDownloadEvento(User $user)
    {
        abort_unless(request()->user()->can('constancias.download'), 403);

        if (! EventSettings::qualifies($user->id)) {
            return back()->withErrors(['error' => 'El usuario no cumple los días mínimos de asistencia al evento (lleva '.EventSettings::attendedDays($user->id).' de '.EventSettings::minDays().').']);
        }

        $certificate = $this->renderer->issueEvent($user);

        if ($certificate === null) {
            return back()->withErrors(['error' => 'No fue posible generar la constancia.']);
        }

        $certificate->update(['downloaded_at' => now()]);

        return $this->respondWithHtml($certificate);
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
        $currentUser = request()->user();
        $workshop = Workshop::findOrFail($workshopId);
        $user = User::findOrFail($userId);

        $isAssignedModerator = $workshop->moderators()->where('users.id', $currentUser->id)->exists();

        abort_unless($currentUser->canScoped('constancias.download', 'constancias.view', $isAssignedModerator), 403);

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
        $currentUser = request()->user();
        $isAssignedModerator = $presentation->moderators()->where('users.id', $currentUser->id)->exists();

        abort_unless($currentUser->canScoped('constancias.download', 'constancias.view', $isAssignedModerator), 403);

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
        $currentUser = request()->user();
        $isAssignedModerator = $conference->moderators()->where('users.id', $currentUser->id)->exists();

        abort_unless($currentUser->canScoped('constancias.download', 'constancias.view', $isAssignedModerator), 403);

        $certificate = $this->renderer->issue($user, 'conference', $conference);

        if ($certificate === null) {
            return back()->withErrors(['error' => 'No fue posible generar la constancia.']);
        }

        $certificate->update(['downloaded_at' => now()]);

        return $this->respondWithHtml($certificate);
    }

    public function adminGenerate(ParticipationType $type, User $user)
    {
        abort_unless(request()->user()->can('constancias.download'), 403);

        if (! $type->manual_generable) {
            return back()->withErrors(['error' => 'Este tipo de constancia no está marcado como generable manualmente.']);
        }

        $certificate = $this->renderer->issueType($user, $type, $type->event_kind, 0);

        if ($certificate === null) {
            return back()->withErrors(['error' => 'No fue posible generar la constancia.']);
        }

        $certificate->update(['downloaded_at' => now()]);

        return $this->respondWithHtml($certificate);
    }

    public function downloadPdf(Request $request, Certificate $certificate)
    {
        $user = $request->user();

        abort_unless($certificate->user_id === $user->id || $user->can('constancias.download'), 403);

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
