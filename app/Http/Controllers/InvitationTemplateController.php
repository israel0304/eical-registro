<?php

namespace App\Http\Controllers;

use App\Models\CertificateTemplate;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class InvitationTemplateController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->can('constancias.templates.manage'), 403);

        $templates = CertificateTemplate::query()
            ->kind('invitation')
            ->with('role')
            ->withCount('elements')
            ->orderBy('role_id')
            ->orderBy('is_default', 'desc')
            ->get();

        $roles = Role::query()
            ->orderBy('id')
            ->get();

        return Inertia::render('Constancias/Invitaciones/Index', [
            'templates' => $templates,
            'roles' => $roles,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can('constancias.templates.manage'), 403);

        $validated = $this->validateTemplate($request);

        if ($validated['is_default'] ?? false) {
            $this->clearDefault($validated['role_id']);
        }

        $template = CertificateTemplate::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'kind' => 'invitation',
            'role_id' => $validated['role_id'],
            'is_default' => (bool) ($validated['is_default'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'width' => $validated['width'] ?? 816,
            'height' => $validated['height'] ?? 1056,
            'background_path' => $this->storeBackground($request, null),
        ]);

        return back()->with('success', 'Plantilla de carta creada.');
    }

    public function edit(Request $request, CertificateTemplate $template)
    {
        abort_unless($request->user()->can('constancias.templates.manage'), 403);
        abort_if($template->kind !== 'invitation', 404);

        $template->load('elements', 'role');

        $roles = Role::query()
            ->orderBy('id')
            ->get();

        return Inertia::render('Constancias/Invitaciones/Edit', [
            'template' => $template,
            'variables' => $this->availableVariables(),
            'roles' => $roles,
        ]);
    }

    public function update(Request $request, CertificateTemplate $template)
    {
        abort_unless($request->user()->can('constancias.templates.manage'), 403);
        abort_if($template->kind !== 'invitation', 404);

        $validated = $this->validateTemplate($request);

        if ($validated['is_default'] ?? false) {
            $this->clearDefault($validated['role_id'], $template->id);
        }

        $template->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'kind' => 'invitation',
            'role_id' => $validated['role_id'],
            'is_default' => (bool) ($validated['is_default'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? $template->is_active),
            'width' => $validated['width'] ?? $template->width,
            'height' => $validated['height'] ?? $template->height,
            'background_path' => $this->storeBackground($request, $template),
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

        return back()->with('success', 'Plantilla de carta guardada.');
    }

    public function toggleActive(Request $request, CertificateTemplate $template)
    {
        abort_unless($request->user()->can('constancias.templates.manage'), 403);
        abort_if($template->kind !== 'invitation', 404);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $template->update(['is_active' => $validated['is_active']]);

        return back()->with(
            'success',
            $validated['is_active'] ? 'Plantilla activada.' : 'Plantilla desactivada.',
        );
    }

    public function destroy(Request $request, CertificateTemplate $template)
    {
        abort_unless($request->user()->can('constancias.templates.manage'), 403);
        abort_if($template->kind !== 'invitation', 404);

        $template->delete();

        return back()->with('success', 'Plantilla de carta eliminada.');
    }

    private function validateTemplate(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'role_id' => ['required', 'exists:roles,id'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'width' => ['nullable', 'integer', 'min:200', 'max:5000'],
            'height' => ['nullable', 'integer', 'min:200', 'max:5000'],
            'background' => ['nullable', 'image', 'mimes:png', 'max:5120'],
            'elements' => ['nullable', 'array'],
            'elements.*.type' => ['nullable', Rule::in(['text', 'qr', 'image'])],
            'elements.*.content' => ['nullable', 'string'],
            'elements.*.variable' => ['nullable', 'string', 'max:100'],
            'elements.*.x' => ['nullable', 'numeric'],
            'elements.*.y' => ['nullable', 'numeric'],
            'elements.*.width' => ['nullable', 'numeric'],
            'elements.*.height' => ['nullable', 'numeric'],
            'elements.*.font_size' => ['nullable', 'integer', 'min:4', 'max:400'],
            'elements.*.auto_fit' => ['nullable', 'boolean'],
            'elements.*.font_weight' => ['nullable', 'string', 'max:100'],
            'elements.*.font_family' => ['nullable', 'string', 'max:200'],
            'elements.*.color' => ['nullable', 'string', 'max:50'],
            'elements.*.text_align' => ['nullable', Rule::in(['left', 'center', 'right'])],
            'elements.*.z_index' => ['nullable', 'integer'],
        ]);
    }

    private function storeBackground(Request $request, ?CertificateTemplate $template = null): ?string
    {
        if (! $request->hasFile('background')) {
            return $template?->background_path;
        }

        $file = $request->file('background');
        $name = Str::uuid().'.png';
        $path = $file->storeAs('invitation_backgrounds', $name, 'public');

        if ($template && $template->background_path) {
            Storage::disk('public')->delete($template->background_path);
        }

        return $path;
    }

    private function clearDefault(int $roleId, ?int $except = null): void
    {
        $query = CertificateTemplate::query()->kind('invitation')->where('role_id', $roleId)->where('is_default', true);

        if ($except !== null) {
            $query->where('id', '!=', $except);
        }

        $query->update(['is_default' => false]);
    }

    private function availableVariables(): array
    {
        return [
            ['key' => '{nombre_completo}', 'label' => 'Nombre completo'],
            ['key' => '{rol}', 'label' => 'Rol de participación'],
            ['key' => '{nombre_evento}', 'label' => 'Nombre del evento'],
            ['key' => '{fecha_evento}', 'label' => 'Fechas del evento (inicio - fin)'],
            ['key' => '{institucion}', 'label' => 'Institución / Afiliación'],
            ['key' => '{pais}', 'label' => 'País'],
            ['key' => '{ponencia}', 'label' => 'Título de la ponencia (si aplica)'],
            ['key' => '{actividad}', 'label' => 'Nombre de la actividad vinculada'],
            ['key' => '{titulo_actividad}', 'label' => 'Título de la actividad (ponencia/taller/conferencia/trabajo)'],
            ['key' => '{dni}', 'label' => 'DNI'],
            ['key' => '{folio}', 'label' => 'Folio'],
            ['key' => '{qr}', 'label' => 'Código QR de verificación'],
        ];
    }
}
