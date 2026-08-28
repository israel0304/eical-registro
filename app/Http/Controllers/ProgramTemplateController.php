<?php

namespace App\Http\Controllers;

use App\Models\CertificateTemplate;
use App\Services\ProgramService;
use App\Support\EventSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ProgramTemplateController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->can('programa.templates.manage'), 403);

        $templates = CertificateTemplate::query()
            ->kind('program')
            ->withCount('elements')
            ->orderBy('is_default', 'desc')
            ->orderBy('id')
            ->get();

        return Inertia::render('Programa/Plantillas/Index', [
            'templates' => $templates,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can('programa.templates.manage'), 403);

        $validated = $this->validateTemplate($request);

        $template = CertificateTemplate::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'kind' => 'program',
            'role_id' => null,
            'is_default' => (bool) ($validated['is_default'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'width' => $validated['width'] ?? 816,
            'height' => $validated['height'] ?? 1056,
            'background_path' => $this->storeBackground($request, null),
        ]);

        $this->setActive($template);

        $this->insertElements($template, $request->input('elements', []));

        return back()->with('success', 'Plantilla de programa creada.');
    }

    public function edit(Request $request, CertificateTemplate $template)
    {
        abort_unless($request->user()->can('programa.templates.manage'), 403);
        abort_if($template->kind !== 'program', 404);

        $template->load('elements');

        return Inertia::render('Programa/Plantillas/Edit', [
            'template' => $template,
            'variables' => $this->availableVariables(),
            'groups' => ProgramService::groupsForRender(),
            'meta' => [
                'eventName' => EventSettings::nombre(),
                'fechas' => EventSettings::rangoFechas(),
                'lugar' => EventSettings::lugar(),
            ],
        ]);
    }

    public function update(Request $request, CertificateTemplate $template)
    {
        abort_unless($request->user()->can('programa.templates.manage'), 403);
        abort_if($template->kind !== 'program', 404);

        $validated = $this->validateTemplate($request);

        $template->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'kind' => 'program',
            'role_id' => null,
            'is_default' => (bool) ($validated['is_default'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? $template->is_active),
            'width' => $validated['width'] ?? $template->width,
            'height' => $validated['height'] ?? $template->height,
            'background_path' => $this->storeBackground($request, $template),
        ]);

        $this->setActive($template);

        $template->elements()->delete();

        $this->storeElements($template, $request->input('elements', []));

        return back()->with('success', 'Plantilla de programa guardada.');
    }

    /**
     * Inserta los elementos. Si no se envían, siembra un encabezado básico
     * y el bloque "lista del programa" para que la plantilla sea usable.
     */
    private function insertElements(CertificateTemplate $template, array $elements): void
    {
        if (! empty($elements)) {
            $this->storeElements($template, $elements);

            return;
        }

        $created = [
            [
                'template_id' => $template->id,
                'type' => 'text',
                'content' => '{evento}',
                'x' => 48,
                'y' => 56,
                'width' => 720,
                'height' => 60,
                'font_size' => 32,
                'auto_fit' => false,
                'word_wrap' => true,
                'font_weight' => 'bold',
                'font_family' => 'Georgia, serif',
                'color' => '#111827',
                'text_align' => 'center',
                'z_index' => 1,
            ],
            [
                'template_id' => $template->id,
                'type' => 'text',
                'content' => '{fecha_evento}',
                'x' => 48,
                'y' => 128,
                'width' => 720,
                'height' => 36,
                'font_size' => 16,
                'auto_fit' => false,
                'word_wrap' => true,
                'font_family' => 'Verdana, sans-serif',
                'color' => '#6b7280',
                'text_align' => 'center',
                'z_index' => 2,
            ],
            [
                'template_id' => $template->id,
                'type' => 'program',
                'content' => json_encode([
                    'show_day' => true,
                    'show_time' => true,
                    'show_location' => true,
                    'show_persons' => true,
                    'accent_color' => '#9d174d',
                    'text_color' => '#111827',
                    'badge_text_color' => '#ffffff',
                    'row_font_size' => 13,
                    'day_font_size' => 14,
                    'time_column' => 96,
                    'bottom_padding' => 32,
                    'row_padding_y' => 8,
                    'type_colors' => [
                        'workshop' => '#b45309',
                        'presentation' => '#0369a1',
                        'conference' => '#9d174d',
                        'block' => '#475569',
                    ],
                ]),
                'x' => 48,
                'y' => 190,
                'width' => 720,
                'height' => null,
                'font_size' => null,
                'auto_fit' => false,
                'word_wrap' => true,
                'text_align' => 'left',
                'z_index' => 3,
            ],
        ];

        foreach ($created as $row) {
            $template->elements()->create($row);
        }
    }

    private function storeElements(CertificateTemplate $template, array $elements): void
    {
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
    }

    public function toggleActive(Request $request, CertificateTemplate $template)
    {
        abort_unless($request->user()->can('programa.templates.manage'), 403);
        abort_if($template->kind !== 'program', 404);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $template->update(['is_active' => $validated['is_active']]);
        $this->setActive($template);

        return back()->with(
            'success',
            $validated['is_active'] ? 'Plantilla activada.' : 'Plantilla desactivada.',
        );
    }

    public function destroy(Request $request, CertificateTemplate $template)
    {
        abort_unless($request->user()->can('programa.templates.manage'), 403);
        abort_if($template->kind !== 'program', 404);

        $template->delete();

        return back()->with('success', 'Plantilla de programa eliminada.');
    }

    public function uploadImage(Request $request)
    {
        abort_unless($request->user()->can('programa.templates.manage'), 403);

        $request->validate([
            'file' => ['required', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:5120'],
        ]);

        $path = $request->file('file')->store('program-images', 'public');

        return response()->json(['url' => Storage::url($path)]);
    }

    private function validateTemplate(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'width' => ['nullable', 'integer', 'min:200', 'max:5000'],
            'height' => ['nullable', 'integer', 'min:200', 'max:5000'],
            'background' => ['nullable', 'image', 'mimes:png', 'max:5120'],
            'elements' => ['nullable', 'array'],
            'elements.*.type' => ['nullable', Rule::in(['text', 'qr', 'image', 'program'])],
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
            'elements.*.text_align' => ['nullable', Rule::in(['left', 'center', 'right', 'justify'])],
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
        $path = $file->storeAs('program_backgrounds', $name, 'public');

        if ($template && $template->background_path) {
            Storage::disk('public')->delete($template->background_path);
        }

        return $path;
    }

    private function setActive(CertificateTemplate $template): void
    {
        if (! $template->is_active) {
            return;
        }

        CertificateTemplate::query()
            ->kind('program')
            ->where('id', '!=', $template->id)
            ->update(['is_active' => false]);
    }

    private function availableVariables(): array
    {
        return [
            ['key' => '{evento}', 'label' => 'Nombre del evento'],
            ['key' => '{nombre_evento}', 'label' => 'Nombre del evento'],
            ['key' => '{fecha_evento}', 'label' => 'Fechas del evento (inicio - fin)'],
            ['key' => '{lugar_evento}', 'label' => 'Lugar / sede del evento'],
            ['key' => '{pagina}', 'label' => 'Número de página'],
            ['key' => '{total_paginas}', 'label' => 'Total de páginas'],
        ];
    }
}
