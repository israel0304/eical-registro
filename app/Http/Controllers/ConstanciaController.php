<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Certificate;
use App\Models\Conference;
use App\Models\ModeradorConstancia;
use App\Models\ParticipationType;
use App\Models\Presentation;
use App\Models\Role;
use App\Models\User;
use App\Models\Workshop;
use App\Services\CertificateRenderer;
use App\Services\ModeratorAssignments;
use App\Support\EventSettings;
use App\Support\ManualCertificateOptions;
use App\Support\WorkshopGroups;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

        $cartaPresentations = Presentation::whereHas('authors', function ($q) use ($user) {
            $q->where('users.id', $user->id);
        })
            ->with(['authors' => function ($q) {
                $q->withPivot('presented', 'presented_at');
            }])
            ->get();

        $conferenceCertificates = Conference::whereHas('members', function ($q) use ($user) {
            $q->where('users.id', $user->id)->where('conference_members.role', 'speaker');
        })
            ->with(['members' => function ($q) use ($user) {
                $q->where('users.id', $user->id)->withPivot('role', 'activated', 'activated_at');
            }])
            ->orderBy('day')
            ->get();

        $moderatorConstancia = $this->moderatorConstanciaFor($user);

        $certificates = Certificate::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy(fn ($certificate) => $certificate->event_type.'-'.$certificate->event_id);

        $cartaCertificates = Certificate::query()
            ->where('user_id', $user->id)
            ->where('event_type', 'carta-presentation')
            ->whereNotNull('event_id')
            ->get()
            ->keyBy(fn ($certificate) => 'carta-presentation-'.$certificate->event_id);

        $cartaConferenceCertificates = Certificate::query()
            ->where('user_id', $user->id)
            ->where('event_type', 'carta-conference')
            ->whereNotNull('event_id')
            ->get()
            ->keyBy(fn ($certificate) => 'carta-conference-'.$certificate->event_id);

        $speakerConferenceTypes = collect(Conference::KINDS)
            ->mapWithKeys(fn ($kind) => [$kind => ParticipationType::query()
                ->where('event_kind', 'conference')
                ->where('role', 'speaker')
                ->where('kind', $kind)
                ->where('is_active', true)
                ->first()]);

        $hasActivityCartas = $user->can('constancias.download')
            && ($cartaPresentations->isNotEmpty() || $conferenceCertificates->isNotEmpty());

        $invitationLetters = [];
        foreach ($user->roles()->orderBy('roles.id')->get() as $role) {
            if ($role->name === 'Ponente') {
                continue;
            }

            if ($role->name === 'Speaker' && $hasActivityCartas) {
                continue;
            }

            $template = $this->renderer->invitationTemplateFor($role);

            if ($template === null) {
                continue;
            }

            $rolLabel = $this->renderer->cartaRoleLabel($role);

            $cartaCertificate = Certificate::query()
                ->where('user_id', $user->id)
                ->where('role_id', $role->id)
                ->where('event_type', 'event')
                ->where('event_id', 0)
                ->first();

            $invitationLetters[] = [
                'id' => $role->id,
                'key' => $role->name,
                'label' => 'Carta de Invitación - '.$rolLabel,
                'rol' => $rolLabel,
                'folio' => $cartaCertificate?->folio,
                'downloaded' => $cartaCertificate?->downloaded_at !== null,
            ];
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

        $completedWorkshops = $this->groupWorkshopEntries(
            $completedWorkshops,
            $certificates,
            fn (WorkshopGroups $group) => $group->hasFullAttendance($user),
            $user,
        );

        $instructorWorkshops = $this->groupWorkshopEntries(
            $instructorWorkshops,
            $certificates,
            fn (WorkshopGroups $group) => $group->hasFullInstructor($user),
            $user,
        );

        foreach ($presentationCertificates as $presentation) {
            $presentation->folio = $certificates->get('presentation-'.$presentation->id)?->folio;
        }

        foreach ($cartaPresentations as $presentation) {
            $presentation->cartaFolio = $cartaCertificates->get('carta-presentation-'.$presentation->id)?->folio;
        }

        foreach ($conferenceCertificates as $conference) {
            $conference->folio = $certificates->get('conference-'.$conference->id)?->folio;
            $conference->activated = (bool) $conference->members->first()?->pivot->activated;
            $conference->member_role = $conference->members->first()?->pivot->role;
            $conference->cartaFolio = $cartaConferenceCertificates->get('carta-conference-'.$conference->id)?->folio;
            $conference->tipo_participacion = $speakerConferenceTypes[$conference->kind]?->label ?? '';
            $conference->horario = $this->renderer->scheduleRange($conference->start_time, $conference->end_time);
        }

        return Inertia::render('Constancias/Index', [
            'completedWorkshops' => $completedWorkshops,
            'instructorWorkshops' => $instructorWorkshops,
            'presentationCertificates' => $presentationCertificates,
            'cartaPresentations' => $cartaPresentations,
            'cartaConferences' => $conferenceCertificates,
            'conferenceCertificates' => $conferenceCertificates,
            'moderatorConstancia' => $moderatorConstancia,
            'isComite' => $user->isComite(),
            'eventCertificate' => $eventCertificate,
            'eventAttendance' => $eventAttendance,
            'invitationLetters' => $invitationLetters,
            'user' => $user,
        ]);
    }

    private function moderatorConstanciaFor(User $user): ?array
    {
        $assignments = app(ModeratorAssignments::class)->assignmentsFor($user);

        if ($assignments->isEmpty()) {
            return null;
        }

        $type = ParticipationType::query()
            ->where('key', 'moderador')
            ->where('event_kind', 'conference')
            ->whereNull('kind')
            ->where('role', 'moderator')
            ->where('is_active', true)
            ->first();

        $certificate = $type !== null
            ? Certificate::query()
                ->where('user_id', $user->id)
                ->where('participation_type_id', $type->id)
                ->where('event_type', 'conference')
                ->where('event_id', 0)
                ->first()
            : null;

        $activation = ModeradorConstancia::where('user_id', $user->id)->first();

        return [
            'activated' => (bool) $activation?->activated,
            'folio' => $certificate?->folio,
            'activity_count' => $assignments->count(),
            'activity_titles' => $assignments->pluck('title')->map(fn ($t) => (string) $t)->all(),
        ];
    }

    public function downloadInvitacion(Request $request)
    {
        $user = $request->user();

        $role = Role::find($request->integer('role'));

        if ($role === null || ! $user->roles()->where('roles.id', $role->id)->exists()) {
            return back()->withErrors(['error' => 'El rol seleccionado no es válido.']);
        }

        $template = $this->renderer->invitationTemplateFor($role);

        if ($template === null) {
            return back()->withErrors(['error' => 'La carta de invitación no está disponible para tu rol.']);
        }

        $certificate = $this->renderer->issueCarta($user, $role);

        if ($certificate === null) {
            return back()->withErrors(['error' => 'No fue posible generar la carta de invitación.']);
        }

        $certificate->update(['downloaded_at' => now()]);

        return $this->respondWithHtml($certificate);
    }

    public function downloadInvitacionPonencia(Request $request, Presentation $presentation)
    {
        $user = $request->user();

        $isAuthor = $presentation->authors()->where('users.id', $user->id)->exists();

        if (! $isAuthor) {
            return back()->withErrors(['error' => 'No eres autor de esta ponencia.']);
        }

        $role = Role::where('name', 'Ponente')->first();

        if ($role === null || ! $user->roles()->where('roles.id', $role->id)->exists()) {
            return back()->withErrors(['error' => 'No tienes el rol de Ponente.']);
        }

        $template = $this->renderer->invitationTemplateFor($role);

        if ($template === null) {
            return back()->withErrors(['error' => 'La carta de invitación no está disponible.']);
        }

        $certificate = $this->renderer->issueCartaForPresentation($user, $role, $presentation);

        if ($certificate === null) {
            return back()->withErrors(['error' => 'No fue posible generar la carta de invitación.']);
        }

        $certificate->update(['downloaded_at' => now()]);

        return $this->respondWithHtml($certificate);
    }

    public function downloadInvitacionConferencia(Request $request, Conference $conference)
    {
        $user = $request->user();

        $isSpeaker = $conference->speakers()->where('users.id', $user->id)->exists();

        if (! $isSpeaker) {
            return back()->withErrors(['error' => 'No eres speaker de esta conferencia.']);
        }

        $role = Role::where('name', 'Speaker')->first();

        if ($role === null || ! $user->roles()->where('roles.id', $role->id)->exists()) {
            return back()->withErrors(['error' => 'No tienes el rol de Speaker.']);
        }

        $template = $this->renderer->invitationTemplateForConference($conference);

        if ($template === null) {
            return back()->withErrors(['error' => 'La carta de invitación no está disponible.']);
        }

        $certificate = $this->renderer->issueCartaForConference($user, $role, $conference);

        if ($certificate === null) {
            return back()->withErrors(['error' => 'No fue posible generar la carta de invitación.']);
        }

        $certificate->update(['downloaded_at' => now()]);

        return $this->respondWithHtml($certificate);
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

        $group = WorkshopGroups::for($workshop);

        if ($group !== null && $group->isDivided() && $group->qualifiedFor($user)) {
            $workshop = $group->representative();
        }

        $isInstructor = $workshop->instructors()
            ->where('users.id', $user->id)
            ->wherePivot('activated', true)
            ->exists();

        if (! $isInstructor) {
            $isInstructorPending = $workshop->instructors()->where('users.id', $user->id)->exists();

            if ($isInstructorPending) {
                return back()->withErrors(['error' => 'Tu constancia aún no ha sido activada.']);
            }

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

        $group = WorkshopGroups::for($workshop);

        if ($group !== null && $group->isDivided() && $group->hasFullAttendance($user)) {
            $workshop = $group->representative();
        }

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

    public function updateHours(Request $request, Workshop $workshop, User $user)
    {
        $currentUser = $request->user();

        $isAssignedModerator = $workshop->moderators()->where('users.id', $currentUser->id)->exists();

        abort_unless($currentUser->canScoped('constancias.download', 'constancias.view', $isAssignedModerator), 403);

        $validated = $request->validate([
            'hours' => 'present|nullable|numeric|min:0|max:999.99',
        ]);

        $group = WorkshopGroups::for($workshop);

        if ($group !== null && $group->isDivided() && $group->hasFullAttendance($user)) {
            $workshop = $group->representative();
        }

        $hasAttendance = $workshop->attendances()->where('user_id', $user->id)->exists();

        if (! $hasAttendance) {
            return back()->withErrors(['error' => 'El usuario no tiene asistencia verificada en este taller.']);
        }

        $hours = $validated['hours'] === null ? null : (float) $validated['hours'];

        $certificate = $this->renderer->setWorkshopHoursOverride($user, $workshop, $hours);

        if ($certificate === null) {
            return back()->withErrors(['error' => 'No fue posible actualizar las horas de la constancia.']);
        }

        return back()->with(
            'success',
            $hours === null
                ? 'Se restauraron las horas reales del taller.'
                : 'Horas de la constancia actualizadas.',
        );
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

    public function downloadComite(Request $request)
    {
        $user = $request->user();

        if (! $user->isComite()) {
            return back()->withErrors(['error' => 'No eres miembro del comité.']);
        }

        $type = ParticipationType::where('key', 'comite')->first();

        if ($type === null) {
            return back()->withErrors(['error' => 'El tipo de participación de comité no está disponible.']);
        }

        $certificate = $this->renderer->issueComite($user, $type);

        if ($certificate === null) {
            return back()->withErrors(['error' => 'No fue posible generar la constancia de comité.']);
        }

        $certificate->update(['downloaded_at' => now()]);

        return $this->respondWithHtml($certificate);
    }

    public function downloadModerador(Request $request)
    {
        $user = $request->user();

        if (! $user->moderatedAnyActivity()) {
            return back()->withErrors(['error' => 'No moderas ninguna actividad del EICAL.']);
        }

        $activated = ModeradorConstancia::where('user_id', $user->id)->value('activated');

        if (! $activated) {
            return back()->withErrors(['error' => 'Tu constancia aún no ha sido activada.']);
        }

        $certificate = $this->renderer->issueModerador($user);

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

        if ($conference->moderators()->where('users.id', $user->id)->exists()) {
            $certificate = $this->renderer->issueModerador($user);

            if ($certificate === null) {
                return back()->withErrors(['error' => 'No fue posible generar la constancia de moderador.']);
            }

            $certificate->update(['downloaded_at' => now()]);

            return $this->respondWithHtml($certificate);
        }

        $certificate = $this->renderer->issue($user, 'conference', $conference);

        if ($certificate === null) {
            return back()->withErrors(['error' => 'No fue posible generar la constancia.']);
        }

        $certificate->update(['downloaded_at' => now()]);

        return $this->respondWithHtml($certificate);
    }

    public function manualOptions(ParticipationType $type, User $user)
    {
        abort_unless(request()->user()->can('constancias.download'), 403);

        if (! $type->manual_generable || ! $type->is_active) {
            abort(404);
        }

        return response()->json(ManualCertificateOptions::for($user, $type));
    }

    public function adminGenerate(Request $request, ParticipationType $type, User $user)
    {
        abort_unless($request->user()->can('constancias.download'), 403);

        if (! $type->manual_generable) {
            return back()->withErrors(['error' => 'Este tipo de constancia no está marcado como generable manualmente.']);
        }

        if ($type->event_kind === 'workshop') {
            return $this->adminGenerateWorkshop($request, $user, $type);
        }

        if ($type->event_kind === 'conference') {
            return $this->adminGenerateConference($request, $user, $type);
        }

        return $this->respondWithManualCertificate(
            $this->renderer->issueType($user, $type, $type->event_kind, 0)
        );
    }

    private function adminGenerateWorkshop(Request $request, User $user, ParticipationType $type)
    {
        $options = ManualCertificateOptions::for($user, $type)['options'];

        if ($options === []) {
            return back()->withErrors(['error' => 'El usuario no cumple los requisitos para ninguna constancia de taller.']);
        }

        $option = $this->resolveManualOption($options, $request->integer('workshop_id'), 'taller');

        if ($option instanceof RedirectResponse) {
            return $option;
        }

        $workshop = Workshop::find($option['id']);

        if ($workshop === null) {
            return back()->withErrors(['error' => 'No se encontró el taller seleccionado.']);
        }

        return $this->respondWithManualCertificate(
            $this->renderer->issueWorkshopForType($user, $type, $workshop)
        );
    }

    private function adminGenerateConference(Request $request, User $user, ParticipationType $type)
    {
        if ($type->role === 'moderator') {
            if (! ManualCertificateOptions::moderatorEligible($user)) {
                return back()->withErrors(['error' => 'El usuario no cumple los requisitos para la constancia de moderador.']);
            }

            return $this->respondWithManualCertificate(
                $this->renderer->issueModeradorForType($user, $type)
            );
        }

        $options = ManualCertificateOptions::for($user, $type)['options'];

        if ($options === []) {
            return back()->withErrors(['error' => 'El usuario no cumple los requisitos para ninguna constancia de conferencia.']);
        }

        $option = $this->resolveManualOption($options, $request->integer('conference_id'), 'conferencia');

        if ($option instanceof RedirectResponse) {
            return $option;
        }

        $conference = Conference::find($option['id']);

        if ($conference === null) {
            return back()->withErrors(['error' => 'No se encontró la conferencia seleccionada.']);
        }

        return $this->respondWithManualCertificate(
            $this->renderer->issueConferenceForType($user, $type, $conference)
        );
    }

    /**
     * @param  array<int, array{id: int, label: string, subtitle: string, event_type: string}>  $options
     * @return array{id: int, label: string, subtitle: string, event_type: string}|RedirectResponse
     */
    private function resolveManualOption(array $options, int $selectedId, string $label)
    {
        if ($selectedId > 0) {
            foreach ($options as $option) {
                if ($option['id'] === $selectedId) {
                    return $option;
                }
            }

            return back()->withErrors(['error' => "La {$label} seleccionada no está disponible para este usuario."]);
        }

        if (count($options) === 1) {
            return $options[0];
        }

        return back()->withErrors(['error' => "Selecciona la {$label} para generar la constancia."]);
    }

    private function respondWithManualCertificate(?Certificate $certificate)
    {
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

    /**
     * Group the workshop entries shown in "Mis constancias": sessions that
     * belong to a divided course and are fully qualified are collapsed into a
     * single entry (grouped by their parent). Workshops that do not meet the
     * full qualifier keep their per-session entry.
     */
    private function groupWorkshopEntries(iterable $workshops, Collection $certificates, callable $fullQualifier, User $user): array
    {
        $entries = [];
        $consumed = [];

        foreach ($workshops as $workshop) {
            if (in_array($workshop->id, $consumed, true)) {
                continue;
            }

            $group = WorkshopGroups::for($workshop);

            if ($group !== null && $group->isDivided() && $fullQualifier($group)) {
                $sessions = $group->sessions();

                foreach ($sessions as $session) {
                    $consumed[] = $session->id;
                }

                $representative = $group->representative();
                $certificate = $certificates->get(WorkshopGroups::GROUPED_EVENT_TYPE.'-'.$group->groupId());

                $entries[] = [
                    'id' => $group->groupId(),
                    'name' => $group->baseTitle(),
                    'instructors' => $representative->instructors,
                    'day' => $group->dateRange(),
                    'location' => $representative->location,
                    'sessions' => $sessions->map(fn (Workshop $session) => [
                        'id' => $session->id,
                        'day' => $session->day,
                        'start_time' => $session->start_time,
                        'end_time' => $session->end_time,
                        'location' => $session->location,
                    ])->values()->all(),
                    'session_count' => $sessions->count(),
                    'horas_totales' => $certificate?->hours_override !== null
                        ? WorkshopGroups::formatHours((float) $certificate->hours_override)
                        : $group->totalHours(),
                    'folio' => $certificate?->folio,
                    'activated' => true,
                ];

                continue;
            }

            $consumed[] = $workshop->id;

            $certificate = $certificates->get('workshop-'.$workshop->id);

            $workshop->folio = $certificate?->folio;
            $workshop->horas_totales = $certificate?->hours_override !== null
                ? WorkshopGroups::formatHours((float) $certificate->hours_override)
                : WorkshopGroups::formatHours(WorkshopGroups::duration($workshop));
            $workshop->activated = (bool) $workshop->instructors->first()?->pivot->activated;

            $entries[] = $workshop;
        }

        return $entries;
    }
}
