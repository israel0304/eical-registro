<?php

namespace App\Http\Controllers;

use App\Jobs\SendNotificationCampaign;
use App\Models\Conference;
use App\Models\EmailTemplate;
use App\Models\NotificationRecipient;
use App\Models\NotificationSend;
use App\Models\Role;
use App\Models\User;
use App\Models\Workshop;
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
        $workshopId = (int) $request->query('workshop_id', 0);
        $workshopName = $workshopId > 0 ? $this->audience->workshopName($workshopId) : null;

        return Inertia::render('Notificaciones/Create', [
            'roles' => Role::query()->orderBy('name')->get(['id', 'name']),
            'conferenceKinds' => $this->audience->conferenceKinds(),
            'templates' => EmailTemplate::query()->orderBy('name')->get(['id', 'name', 'subject', 'body_html']),
            'workshopId' => $workshopId > 0 && $workshopName !== null ? $workshopId : null,
            'workshopName' => $workshopName,
        ]);
    }

    public function preview(Request $request)
    {
        $data = $this->validateAudience($request);

        $this->authorizeAudience($request, $data);

        $users = $this->audience->resolve(
            $data['audience_type'],
            $data['role_id'] ?? null,
            $data['kind'] ?? null,
            $data['user_ids'] ?? [],
            $data['workshop_id'] ?? null,
        );

        $tipoConferencia = $data['audience_type'] === 'speakers_by_kind'
            ? $this->audience->kindLabel($data['kind'])
            : null;

        $nombreTaller = $data['audience_type'] === 'workshop_enrollment'
            ? $this->audience->workshopName((int) $data['workshop_id'])
            : null;

        $sample = $users->take(5)->map(fn ($user) => [
            'email' => $user->email,
            'name' => $user->name,
            'payload' => $this->audience->buildPayload($user, $tipoConferencia, $nombreTaller),
        ])->values();

        return response()->json([
            'count' => $users->count(),
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
        $data = $this->validateAudience($request);

        $this->authorizeAudience($request, $data);

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:191'],
            'body_html' => ['required', 'string'],
            'template_id' => ['nullable', 'integer', 'exists:email_templates,id'],
        ]);

        $users = $this->audience->resolve(
            $data['audience_type'],
            $data['role_id'] ?? null,
            $data['kind'] ?? null,
            $data['user_ids'] ?? [],
            $data['workshop_id'] ?? null,
        );

        if ($users->isEmpty()) {
            return back()->with('error', 'La audiencia seleccionada no tiene destinatarios.');
        }

        $audienceValue = match ($data['audience_type']) {
            'role' => ['role_id' => (int) $data['role_id']],
            'speakers_by_kind' => ['kind' => $data['kind']],
            'individual' => ['user_ids' => $users->pluck('id')->all()],
            'workshop_enrollment' => ['workshop_id' => (int) $data['workshop_id']],
            default => null,
        };

        $tipoConferencia = $data['audience_type'] === 'speakers_by_kind'
            ? $this->audience->kindLabel($data['kind'])
            : null;

        $nombreTaller = $data['audience_type'] === 'workshop_enrollment'
            ? $this->audience->workshopName((int) $data['workshop_id'])
            : null;

        $send = NotificationSend::create([
            'subject' => $validated['subject'],
            'body_html' => $validated['body_html'],
            'audience_type' => $data['audience_type'],
            'audience_value' => $audienceValue,
            'template_id' => $validated['template_id'] ?? null,
            'sent_by' => $request->user()->id,
            'recipient_count' => $users->count(),
            'status' => NotificationSend::STATUS_PENDING,
        ]);

        foreach ($users as $user) {
            $send->recipients()->create([
                'email' => $user->email,
                'name' => $user->name,
                'payload' => $this->audience->buildPayload($user, $tipoConferencia, $nombreTaller),
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

    private function validateAudience(Request $request): array
    {
        return $request->validate([
            'audience_type' => ['required', Rule::in(NotificationSend::AUDIENCE_TYPES)],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'kind' => ['nullable', Rule::in(Conference::KINDS)],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'workshop_id' => ['nullable', 'integer', 'exists:workshops,id'],
        ]);
    }

    /**
     * Los gestores completos (correos.notifications.manage) envían a cualquier
     * audiencia. El resto (instructores) solo a los inscritos de un taller que
     * impartan.
     */
    private function authorizeAudience(Request $request, array $data): void
    {
        $user = $request->user();

        if ($this->canManageAll($user)) {
            return;
        }

        if ($data['audience_type'] !== 'workshop_enrollment') {
            abort(403, 'Solo puedes enviar notificaciones a los inscritos de tus talleres.');
        }

        abort_unless(
            $this->isInstructorOf($user, (int) ($data['workshop_id'] ?? 0)),
            403,
            'Solo puedes enviar notificaciones a los inscritos de tus talleres.',
        );
    }

    private function canManageAll(User $user): bool
    {
        return $user->can('correos.notifications.manage');
    }

    private function isInstructorOf(User $user, int $workshopId): bool
    {
        return Workshop::query()
            ->whereKey($workshopId)
            ->whereHas('instructors', fn ($q) => $q->whereKey($user->id))
            ->exists();
    }
}
