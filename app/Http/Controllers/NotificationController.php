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

    public function create()
    {
        return Inertia::render('Notificaciones/Create', [
            'roles' => Role::query()->orderBy('name')->get(['id', 'name']),
            'conferenceKinds' => $this->audience->conferenceKinds(),
            'templates' => EmailTemplate::query()->orderBy('name')->get(['id', 'name', 'subject', 'body_html']),
        ]);
    }

    public function preview(Request $request)
    {
        $data = $this->validateAudience($request);

        $users = $this->audience->resolve(
            $data['audience_type'],
            $data['role_id'] ?? null,
            $data['kind'] ?? null,
            $data['user_ids'] ?? [],
        );

        $tipoConferencia = $data['audience_type'] === 'speakers_by_kind'
            ? $this->audience->kindLabel($data['kind'])
            : null;

        $sample = $users->take(5)->map(fn ($user) => [
            'email' => $user->email,
            'name' => $user->name,
            'payload' => $this->audience->buildPayload($user, $tipoConferencia),
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
        );

        if ($users->isEmpty()) {
            return back()->with('error', 'La audiencia seleccionada no tiene destinatarios.');
        }

        $audienceValue = match ($data['audience_type']) {
            'role' => ['role_id' => (int) $data['role_id']],
            'speakers_by_kind' => ['kind' => $data['kind']],
            'individual' => ['user_ids' => $users->pluck('id')->all()],
            default => null,
        };

        $tipoConferencia = $data['audience_type'] === 'speakers_by_kind'
            ? $this->audience->kindLabel($data['kind'])
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
                'payload' => $this->audience->buildPayload($user, $tipoConferencia),
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
        ]);
    }
}
