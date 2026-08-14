<?php

namespace App\Http\Controllers;

use App\Models\CertificateTemplate;
use App\Models\EmailTemplate;
use App\Models\EmailTrigger;
use App\Models\EventLog;
use App\Models\ParticipationType;
use App\Models\Role;
use App\Support\EventCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CertificateTemplateController extends Controller
{
    public function index(Request $request)
    {
        return $this->indexFor($request, 'certificate');
    }

    public function plantillas(Request $request)
    {
        abort_unless(
            $request->user()->can('gafete.templates.manage')
            || $request->user()->can('constancias.templates.manage')
            || $request->user()->can('correos.templates.manage'),
            403
        );

        $lists = [];
        foreach (['badge', 'certificate'] as $kind) {
            $lists[$kind] = CertificateTemplate::query()
                ->kind($kind)
                ->with('participationType')
                ->withCount('elements')
                ->orderBy('participation_type_id')
                ->orderBy('is_default', 'desc')
                ->get();
        }

        $participationTypes = ParticipationType::query()
            ->withCount('templates')
            ->orderBy('event_kind')
            ->orderBy('role')
            ->get();

        $eventLogs = collect();
        $roles = Role::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
        if ($request->user()->hasPermission('correos.templates.manage')) {
            $eventLogs = EventLog::query()
                ->latest()
                ->limit(30)
                ->get()
                ->map(function (EventLog $log) {
                    return [
                        'id' => $log->id,
                        'event_key' => $log->event_key,
                        'event_label' => EventCatalog::label($log->event_key),
                        'status' => $log->status,
                        'message' => $log->message,
                        'subject_type' => class_basename($log->subject_type ?? ''),
                        'subject_id' => $log->subject_id,
                        'actor_name' => $log->actor?->name,
                        'payload' => $log->payload,
                        'created_at' => $log->created_at?->diffForHumans(),
                    ];
                });
        }

        return Inertia::render('Plantillas/Index', [
            'badgeTemplates' => $lists['badge'],
            'certificateTemplates' => $lists['certificate'],
            'participationTypes' => $participationTypes,
            'emailTemplates' => EmailTemplate::query()->with('triggers')->orderBy('name')->get(),
            'emailTriggers' => EmailTrigger::query()->with('template')->orderBy('event_key')->get(),
            'eventCatalog' => collect(config('events.events'))
                ->filter(fn ($event) => $event['has_trigger'] ?? false)
                ->map(fn ($event, $key) => [
                    'event_key' => $key,
                    'label' => $event['label'],
                    'group' => $event['group'],
                    'to_options' => $event['to_options'] ?? ['destinatario' => 'Destinatario del evento'],
                    'variables' => $event['variables'] ?? [],
                ])
                ->values(),
            'eventLogs' => $eventLogs,
            'roles' => $roles,
            'permissions' => [
                'gafete' => $request->user()->hasPermission('gafete.templates.manage'),
                'constancias' => $request->user()->hasPermission('constancias.templates.manage'),
                'invitaciones' => $request->user()->hasPermission('constancias.templates.manage'),
                'correos' => $request->user()->hasPermission('correos.templates.manage'),
            ],
        ]);
    }

    public function badgeIndex(Request $request)
    {
        return $this->indexFor($request, 'badge');
    }

    private function indexFor(Request $request, string $kind)
    {
        abort_unless($request->user()->can($this->templatePermission($kind)), 403);

        $templates = CertificateTemplate::query()
            ->kind($kind)
            ->with('participationType')
            ->withCount('elements')
            ->orderBy('participation_type_id')
            ->orderBy('is_default', 'desc')
            ->get();

        $participationTypes = $kind === 'badge'
            ? collect()
            : ParticipationType::query()
                ->withCount('templates')
                ->orderBy('event_kind')
                ->orderBy('role')
                ->get();

        return Inertia::render('Constancias/Templates/Index', [
            'templates' => $templates,
            'participationTypes' => $participationTypes,
            'kind' => $kind,
        ]);
    }

    public function store(Request $request)
    {
        return $this->storeFor($request, 'certificate');
    }

    public function badgeStore(Request $request)
    {
        return $this->storeFor($request, 'badge');
    }

    private function storeFor(Request $request, string $kind)
    {
        abort_unless($request->user()->can($this->templatePermission($kind)), 403);

        $validated = $this->validateTemplate($request, $kind);

        if ($validated['is_default'] ?? false) {
            $this->clearDefault($kind, $validated['participation_type_id'] ?? null);
        }

        $template = CertificateTemplate::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'kind' => $kind,
            'participation_type_id' => $validated['participation_type_id'] ?? null,
            'is_default' => (bool) ($validated['is_default'] ?? false),
            'width' => $validated['width'] ?? 1800,
            'height' => $validated['height'] ?? 1200,
            'background_path' => $this->storeBackground($request, null, $kind),
        ]);

        return back()->with('success', 'Plantilla creada.');
    }

    public function edit(Request $request, CertificateTemplate $template)
    {
        return $this->editFor($request, $template);
    }

    public function badgeEdit(Request $request, CertificateTemplate $template)
    {
        return $this->editFor($request, $template);
    }

    private function editFor(Request $request, CertificateTemplate $template)
    {
        abort_unless($request->user()->can($this->templatePermission($template->kind ?? 'certificate')), 403);

        $kind = $template->kind ?? 'certificate';
        $template->load('elements', 'participationType');

        $participationTypes = $kind === 'badge'
            ? collect()
            : ParticipationType::query()
                ->where('is_active', true)
                ->orderBy('event_kind')
                ->orderBy('role')
                ->get();

        return Inertia::render('Constancias/Templates/Edit', [
            'template' => $template,
            'variables' => $this->availableVariables($kind),
            'participationTypes' => $participationTypes,
            'kind' => $kind,
        ]);
    }

    public function update(Request $request, CertificateTemplate $template)
    {
        return $this->updateFor($request, $template);
    }

    public function badgeUpdate(Request $request, CertificateTemplate $template)
    {
        return $this->updateFor($request, $template);
    }

    private function updateFor(Request $request, CertificateTemplate $template)
    {
        abort_unless($request->user()->can($this->templatePermission($template->kind ?? 'certificate')), 403);

        $kind = $template->kind ?? 'certificate';
        $validated = $this->validateTemplate($request, $kind);

        if ($validated['is_default'] ?? false) {
            $this->clearDefault($kind, $validated['participation_type_id'] ?? null, $template->id);
        }

        $template->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'kind' => $kind,
            'participation_type_id' => $validated['participation_type_id'] ?? null,
            'is_default' => (bool) ($validated['is_default'] ?? false),
            'width' => $validated['width'] ?? $template->width,
            'height' => $validated['height'] ?? $template->height,
            'background_path' => $this->storeBackground($request, $template, $kind),
        ]);

        $template->elements()->delete();

        $elements = $request->input('elements', []);
        $created = [];
        foreach ($elements as $i => $element) {
            $created[] = [
                'template_id' => $template->id,
                'type' => $element['type'] ?? 'text',
                'content' => $element['content'] ?? null,
                'variable' => $element['variable'] ?? null,
                'x' => (float) ($element['x'] ?? 0),
                'y' => (float) ($element['y'] ?? 0),
                'width' => ! empty($element['width']) ? (int) $element['width'] : null,
                'height' => ! empty($element['height']) ? (int) $element['height'] : null,
                'font_size' => ! empty($element['font_size']) ? (int) $element['font_size'] : null,
                'auto_fit' => ! empty($element['auto_fit']),
                'word_wrap' => ! empty($element['word_wrap']),
                'font_weight' => $element['font_weight'] ?? null,
                'font_family' => $element['font_family'] ?? null,
                'color' => $element['color'] ?? null,
                'text_align' => $element['text_align'] ?? 'center',
                'z_index' => (int) ($element['z_index'] ?? $i),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $template->elements()->insert($created);

        return back()->with('success', 'Plantilla guardada.');
    }

    public function destroy(Request $request, CertificateTemplate $template)
    {
        return $this->destroyFor($request, $template);
    }

    public function badgeDestroy(Request $request, CertificateTemplate $template)
    {
        return $this->destroyFor($request, $template);
    }

    private function destroyFor(Request $request, CertificateTemplate $template)
    {
        abort_unless($request->user()->can($this->templatePermission($template->kind ?? 'certificate')), 403);

        $template->delete();

        return back()->with('success', 'Plantilla eliminada.');
    }

    private function validateTemplate(Request $request, string $kind): array
    {
        $elementTypes = $kind === 'badge' ? ['text', 'qr', 'image'] : ['text', 'qr'];

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'participation_type_id' => $kind === 'badge'
                ? ['nullable', 'exists:participation_types,id']
                : ['required', 'exists:participation_types,id'],
            'is_default' => ['nullable', 'boolean'],
            'width' => ['nullable', 'integer', 'min:200', 'max:5000'],
            'height' => ['nullable', 'integer', 'min:200', 'max:5000'],
            'background' => ['nullable', 'image', 'mimes:png', 'max:5120'],
            'elements' => ['nullable', 'array'],
            'elements.*.type' => ['nullable', Rule::in($elementTypes)],
            'elements.*.content' => ['nullable', 'string'],
            'elements.*.variable' => ['nullable', 'string', 'max:100'],
            'elements.*.x' => ['nullable', 'numeric'],
            'elements.*.y' => ['nullable', 'numeric'],
            'elements.*.width' => ['nullable', 'numeric'],
            'elements.*.height' => ['nullable', 'numeric'],
            'elements.*.font_size' => ['nullable', 'integer', 'min:4', 'max:400'],
            'elements.*.auto_fit' => ['nullable', 'boolean'],
            'elements.*.word_wrap' => ['nullable', 'boolean'],
            'elements.*.font_weight' => ['nullable', 'string', 'max:100'],
            'elements.*.font_family' => ['nullable', 'string', 'max:200'],
            'elements.*.color' => ['nullable', 'string', 'max:50'],
            'elements.*.text_align' => ['nullable', Rule::in(['left', 'center', 'right'])],
            'elements.*.z_index' => ['nullable', 'integer'],
        ]);
    }

    private function storeBackground(Request $request, ?CertificateTemplate $template = null, string $kind = 'certificate'): ?string
    {
        if (! $request->hasFile('background')) {
            return $template?->background_path;
        }

        $file = $request->file('background');
        $name = Str::uuid().'.png';
        $disk = $kind === 'badge' ? 'badge_backgrounds' : 'certificate_backgrounds';

        $path = $file->storeAs($disk, $name, 'public');

        if ($template && $template->background_path) {
            Storage::disk('public')->delete($template->background_path);
        }

        return $path;
    }

    private function templatePermission(string $kind): string
    {
        return $kind === 'badge' ? 'gafete.templates.manage' : 'constancias.templates.manage';
    }

    private function clearDefault(string $kind, ?int $participationTypeId, ?int $except = null): void
    {
        $query = CertificateTemplate::query()->kind($kind)->where('is_default', true);

        if ($kind !== 'badge') {
            $query->where('participation_type_id', $participationTypeId);
        }

        if ($except !== null) {
            $query->where('id', '!=', $except);
        }

        $query->update(['is_default' => false]);
    }

    private function availableVariables(string $kind): array
    {
        if ($kind === 'badge') {
            return [
                ['key' => '{nombre}', 'label' => 'Nombre completo'],
                ['key' => '{dni}', 'label' => 'DNI'],
                ['key' => '{afiliacion}', 'label' => 'Afiliación / Institución'],
                ['key' => '{evento}', 'label' => 'Nombre del evento'],
                ['key' => '{rol}', 'label' => 'Rol de participación'],
                ['key' => '{iniciales}', 'label' => 'Iniciales (si no hay foto)'],
                ['key' => '{foto}', 'label' => 'Foto de perfil (elemento Foto)'],
                ['key' => '{qr}', 'label' => 'Código QR de check-in'],
            ];
        }

        return [
            ['key' => '{nombre}', 'label' => 'Nombre del participante'],
            ['key' => '{tipo_participacion}', 'label' => 'Tipo de participación'],
            ['key' => '{evento}', 'label' => 'Actividad (taller/ponencia)'],
            ['key' => '{fecha_evento}', 'label' => 'Fecha del evento'],
            ['key' => '{folio}', 'label' => 'Folio único'],
            ['key' => '{qr}', 'label' => 'Código QR de verificación'],
        ];
    }
}
