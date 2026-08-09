<?php

namespace App\Http\Controllers;

use App\Models\EmailTemplate;
use App\Models\EmailTrigger;
use App\Models\Role;
use App\Services\EmailDispatcher;
use App\Support\EventCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class EmailTemplateController extends Controller
{
    public function store(Request $request)
    {
        abort_unless($request->user()->can('correos.templates.manage'), 403);

        $validated = $request->validate([
            'event_key' => [
                'required',
                'string',
                $this->eventKeyRule(),
                Rule::unique('email_templates', 'event_key'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:191'],
            'body_html' => ['required', 'string'],
        ]);

        $template = EmailTemplate::create($validated);

        return back()->with('success', "Plantilla de correo «{$template->name}» creada.");
    }

    public function edit(Request $request, EmailTemplate $template)
    {
        abort_unless($request->user()->can('correos.templates.manage'), 403);

        return Inertia::render('Correos/Edit', [
            'template' => $template,
            'templates' => EmailTemplate::query()
                ->orderBy('name')
                ->get(['id', 'event_key', 'name']),
            'triggers' => EmailTrigger::query()
                ->with('template', 'role')
                ->orderBy('event_key')
                ->get(),
            'catalog' => collect(config('events.events'))
                ->filter(fn ($event) => $event['has_trigger'] ?? false)
                ->map(fn ($event, $key) => [
                    'event_key' => $key,
                    'label' => $event['label'],
                    'group' => $event['group'],
                    'to_options' => $event['to_options'] ?? ['destinatario' => 'Destinatario del evento'],
                    'variables' => $event['variables'] ?? [],
                ])
                ->values(),
            'roles' => Role::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'variables' => EventCatalog::variables($template->event_key),
        ]);
    }

    public function update(Request $request, EmailTemplate $template)
    {
        abort_unless($request->user()->can('correos.templates.manage'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:191'],
            'body_html' => ['required', 'string'],
        ]);

        $template->update($validated);

        return back()->with('success', 'Plantilla de correo actualizada.');
    }

    public function destroy(Request $request, EmailTemplate $template)
    {
        abort_unless($request->user()->can('correos.templates.manage'), 403);

        $template->delete();

        return back()->with('success', 'Plantilla de correo eliminada.');
    }

    public function preview(Request $request)
    {
        abort_unless($request->user()->can('correos.templates.manage'), 403);

        $validated = $request->validate([
            'event_key' => ['required', 'string'],
            'subject' => ['nullable', 'string', 'max:191'],
            'body_html' => ['nullable', 'string'],
        ]);

        $preview = EmailDispatcher::preview(
            $validated['event_key'],
            $validated['subject'] ?? '',
            $validated['body_html'] ?? '',
        );

        return response()->json($preview);
    }

    private function eventKeyRule(): \Closure
    {
        return function ($attribute, $value, $fail) {
            $event = EventCatalog::find($value);

            if (! $event) {
                $fail('El evento no existe en el catálogo.');

                return;
            }

            if (! ($event['has_trigger'] ?? false)) {
                $fail('Este evento no admite plantillas de correo.');
            }
        };
    }
}
