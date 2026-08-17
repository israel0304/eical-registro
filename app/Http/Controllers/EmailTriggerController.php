<?php

namespace App\Http\Controllers;

use App\Jobs\SendTriggeredEmails;
use App\Models\EmailTemplate;
use App\Models\EmailTrigger;
use App\Models\EventLog;
use App\Support\EventCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmailTriggerController extends Controller
{
    public function store(Request $request)
    {
        abort_unless($request->user()->can('correos.templates.manage'), 403);

        $validated = $this->validateTrigger($request);

        $trigger = EmailTrigger::create($validated);

        return back()->with('success', "Disparador para «{$trigger->event_key}» creado.");
    }

    public function update(Request $request, EmailTrigger $trigger)
    {
        abort_unless($request->user()->can('correos.templates.manage'), 403);

        $validated = $this->validateTrigger($request);

        $trigger->update($validated);

        return back()->with('success', 'Disparador actualizado.');
    }

    public function destroy(Request $request, EmailTrigger $trigger)
    {
        abort_unless($request->user()->can('correos.templates.manage'), 403);

        $trigger->delete();

        return back()->with('success', 'Disparador eliminado.');
    }

    public function resend(Request $request, EventLog $eventLog): JsonResponse
    {
        abort_unless($request->user()->can('correos.templates.manage'), 403);

        $trigger = $eventLog->trigger;

        if (! $trigger || ! $trigger->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'El disparador asociado está inactivo o ya no existe.',
            ], 422);
        }

        SendTriggeredEmails::dispatch($trigger, $eventLog->payload, $eventLog);

        return response()->json([
            'success' => true,
            'message' => 'Correo encolado para envío.',
        ]);
    }

    private function validateTrigger(Request $request): array
    {
        $eventKey = $request->input('event_key');
        $toOptions = array_keys(EventCatalog::toOptions($eventKey));

        return $request->validate([
            'event_key' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $event = EventCatalog::find($value);

                    if (! $event) {
                        $fail('El evento no existe en el catálogo.');

                        return;
                    }

                    if (! ($event['has_trigger'] ?? false)) {
                        $fail('Este evento no admite disparadores de correo.');
                    }
                },
            ],
            'email_template_id' => [
                'required',
                'integer',
                'exists:email_templates,id',
                function ($attribute, $value, $fail) use ($eventKey) {
                    $template = EmailTemplate::find($value);

                    if ($template && $template->event_key !== $eventKey) {
                        $fail('La plantilla seleccionada no corresponde al evento del disparador.');
                    }
                },
            ],
            'to' => [
                'nullable',
                'string',
                Rule::requiredIf(blank($request->input('role_id'))),
                Rule::in($toOptions),
            ],
            'role_id' => [
                'nullable',
                'integer',
                'exists:roles,id',
                Rule::requiredIf(blank($request->input('to'))),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
