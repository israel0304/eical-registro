<?php

namespace App\Http\Controllers;

use App\Jobs\SendNotificationCampaign;
use App\Models\Conference;
use App\Models\EmailTemplate;
use App\Models\NotificationRecipient;
use App\Models\NotificationSend;
use App\Models\Role;
use App\Models\User;
use App\Services\NotificationAudienceService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationAudienceService $audience,
    ) {}

    public function index(Request $request)
    {
        $query = NotificationSend::query()
            ->with('sender');

        if ($request->filled('search')) {
            $query->where('subject', 'like', '%'.$request->input('search').'%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $sends = $query->latest()->paginate(15)->withQueryString();

        $sends->getCollection()->transform(
            fn (NotificationSend $send) => $send->setAttribute('audience_label', $this->audience->labelFor($send))
        );

        return Inertia::render('Notificaciones/Index', [
            'notifications' => $sends,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $canManageAll = $this->canManageAll($user);
        $workshopId = (int) $request->query('workshop_id', 0);
        $workshopName = $workshopId > 0 ? $this->audience->workshopName($workshopId) : null;

        return Inertia::render('Notificaciones/Create', [
            'roles' => Role::query()->orderBy('name')->get(['id', 'name']),
            'conferenceKinds' => $this->audience->conferenceKinds(),
            'templates' => EmailTemplate::query()->orderBy('name')->get(['id', 'name', 'subject', 'body_html']),
            'courses' => $this->audience->workshopCourses($canManageAll ? null : $user),
            'canManageAll' => $canManageAll,
            'workshopId' => $workshopId > 0 && $workshopName !== null ? $workshopId : null,
            'workshopName' => $workshopName,
        ]);
    }

    public function preview(Request $request)
    {
        $segments = $this->validateAudience($request);

        if ($segments === []) {
            return response()->json(['count' => 0, 'sample' => []]);
        }

        $this->authorizeAudience($request, $segments);

        $resolved = $this->audience->resolveSegmentsDetailed($segments);

        $sample = $resolved['users']->take(5)->map(fn ($user) => [
            'email' => $user->email,
            'name' => $user->name,
            'groups' => array_map(
                fn (array $segment) => $this->audience->segmentLabel($segment),
                $resolved['matches'][$user->id] ?? []
            ),
            'payload' => $this->audience->payloadFor($user, $resolved['matches'][$user->id] ?? []),
        ])->values();

        return response()->json([
            'count' => $resolved['users']->count(),
            'sample' => $sample,
        ]);
    }

    public function show(NotificationSend $notificationSend)
    {
        return response()->json(
            $notificationSend->recipients()->latest('id')->paginate(50)
        );
    }

    public function users(Request $request)
    {
        $search = trim((string) $request->input('search'));

        $query = User::query()->where('is_active', true);

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(fn ($q) => $q
                ->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('dni', 'like', $like));
        }

        return response()->json(
            $query->orderBy('last_name')->orderBy('first_name')->limit(10)
                ->get(['id', 'first_name', 'last_name', 'email', 'dni'])
        );
    }

    public function store(Request $request)
    {
        $segments = $this->validateAudience($request);

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:191'],
            'body_html' => ['required', 'string'],
            'template_id' => ['nullable', 'integer', 'exists:email_templates,id'],
        ]);

        if ($segments === []) {
            return back()->with('error', 'Selecciona al menos un grupo de destinatarios.');
        }

        $this->authorizeAudience($request, $segments);

        $resolved = $this->audience->resolveSegmentsDetailed($segments);
        $users = $resolved['users'];

        if ($users->isEmpty()) {
            return back()->with('error', 'La audiencia seleccionada no tiene destinatarios.');
        }

        $send = NotificationSend::create([
            'subject' => $validated['subject'],
            'body_html' => $validated['body_html'],
            'audience_type' => NotificationSend::AUDIENCE_SEGMENTS,
            'audience_value' => ['segments' => $segments],
            'template_id' => $validated['template_id'] ?? null,
            'sent_by' => $request->user()->id,
            'recipient_count' => $users->count(),
            'status' => NotificationSend::STATUS_PENDING,
        ]);

        foreach ($users as $user) {
            $send->recipients()->create([
                'email' => $user->email,
                'name' => $user->name,
                'payload' => $this->audience->payloadFor($user, $resolved['matches'][$user->id] ?? []),
                'status' => NotificationRecipient::STATUS_PENDING,
            ]);
        }

        SendNotificationCampaign::dispatch($send);

        return redirect()->route('correos.notificaciones.index')
            ->with('success', 'Notificación programada para '.$users->count().' destinatario(s).');
    }

    public function retryFailed(Request $request, NotificationSend $notificationSend)
    {
        $failed = $notificationSend->recipients()->where('status', NotificationRecipient::STATUS_FAILED)->count();

        if ($failed === 0) {
            return back()->with('error', 'No hay destinatarios fallidos para reintentar.');
        }

        foreach ($notificationSend->recipients()->where('status', NotificationRecipient::STATUS_FAILED)->get() as $recipient) {
            $recipient->update(['status' => NotificationRecipient::STATUS_PENDING]);
        }

        SendNotificationCampaign::dispatch($notificationSend);

        return back()->with('success', "Reintentando envío a {$failed} destinatario(s) fallidos.");
    }

    /**
     * Acepta la campaña combinada (segments) y, por compatibilidad, la
     * audiencia de un solo tipo histórica (audience_type + role_id/kind/
     * user_ids/workshop_id). Devuelve la lista de segmentos ya normalizada.
     *
     * @return array<int, array<string, mixed>>
     */
    private function validateAudience(Request $request): array
    {
        $legacyTypes = array_values(array_diff(NotificationSend::AUDIENCE_TYPES, [NotificationSend::AUDIENCE_SEGMENTS]));

        $data = $request->validate([
            'audience_type' => ['nullable', Rule::in($legacyTypes)],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'kind' => ['nullable', Rule::in(Conference::KINDS)],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'workshop_id' => ['nullable', 'integer', 'exists:workshops,id'],
            'segments' => ['nullable', 'array', 'max:60'],
            'segments.*' => ['array'],
            'segments.*.type' => ['required', Rule::in(NotificationAudienceService::SEGMENT_TYPES)],
            'segments.*.role_ids' => ['nullable', 'array'],
            'segments.*.role_ids.*' => ['integer', 'exists:roles,id'],
            'segments.*.kinds' => ['nullable', 'array'],
            'segments.*.kinds.*' => [Rule::in(Conference::KINDS)],
            'segments.*.workshop_ids' => ['nullable', 'array'],
            'segments.*.workshop_ids.*' => ['integer', 'exists:workshops,id'],
            'segments.*.all_workshops' => ['nullable', 'boolean'],
            'segments.*.user_ids' => ['nullable', 'array', 'max:500'],
            'segments.*.user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $segments = $this->audience->normalizeSegments($data['segments'] ?? []);

        if ($segments === [] && filled($data['audience_type'] ?? null)) {
            $segments = $this->audience->normalizeSegments([
                $this->audience->legacySegment(
                    (string) $data['audience_type'],
                    $data['role_id'] ?? null,
                    $data['kind'] ?? null,
                    (array) ($data['user_ids'] ?? []),
                    $data['workshop_id'] ?? null,
                ),
            ]);
        }

        return $segments;
    }

    /**
     * Los gestores completos (correos.notifications.manage) combinan cualquier
     * segmento. El resto (instructores) solo puede usar segmentos de taller
     * limitados a los que imparten, y nunca "todos los talleres".
     *
     * @param  array<int, array<string, mixed>>  $segments
     */
    private function authorizeAudience(Request $request, array $segments): void
    {
        $user = $request->user();

        if ($this->canManageAll($user)) {
            return;
        }

        $message = 'Solo puedes enviar notificaciones a los inscritos o instructores de tus talleres.';

        foreach ($segments as $segment) {
            $type = $segment['type'] ?? '';

            if (! in_array($type, [NotificationAudienceService::SEGMENT_WORKSHOP_ENROLLMENT, NotificationAudienceService::SEGMENT_WORKSHOP_INSTRUCTORS], true)) {
                abort(403, $message);
            }

            if (! empty($segment['all_workshops'])) {
                abort(403, $message);
            }

            foreach ((array) ($segment['workshop_ids'] ?? []) as $workshopId) {
                abort_unless($this->isInstructorOf($user, (int) $workshopId), 403, $message);
            }
        }
    }

    private function canManageAll(User $user): bool
    {
        return $user->can('correos.notifications.manage');
    }

    private function isInstructorOf(User $user, int $workshopId): bool
    {
        return $this->audience->instructsCourse($user, $workshopId);
    }
}
