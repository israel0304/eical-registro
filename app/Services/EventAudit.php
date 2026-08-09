<?php

namespace App\Services;

use App\Jobs\SendTriggeredEmails;
use App\Models\EmailTrigger;
use App\Models\EventLog;
use Illuminate\Database\Eloquent\Model;

class EventAudit
{
    /**
     * Registra un evento en event_log y, si existen disparadores activos
     * para el evento, encola el envío del correo asociado. Se crea una
     * entrada de auditoría por cada disparador activo.
     */
    public static function emit(
        string $eventKey,
        ?Model $subject = null,
        ?Model $actor = null,
        array $payload = [],
    ): EventLog {
        $triggers = EmailTrigger::query()
            ->where('event_key', $eventKey)
            ->where('is_active', true)
            ->whereNotNull('email_template_id')
            ->get();

        if ($triggers->isEmpty()) {
            return static::log($eventKey, $subject, $actor, $payload, null);
        }

        $first = null;

        foreach ($triggers as $trigger) {
            $log = static::log($eventKey, $subject, $actor, $payload, $trigger);

            SendTriggeredEmails::dispatch($trigger, $payload, $log);

            $first ??= $log;
        }

        return $first;
    }

    public static function markSent(EventLog $log): void
    {
        $log->update(['status' => 'sent']);
    }

    public static function markFailed(EventLog $log, string $message): void
    {
        $log->update(['status' => 'failed', 'message' => $message]);
    }

    private static function log(
        string $eventKey,
        ?Model $subject,
        ?Model $actor,
        array $payload,
        ?EmailTrigger $trigger,
    ): EventLog {
        return EventLog::create([
            'event_key' => $eventKey,
            'trigger_id' => $trigger?->id,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject ? $subject->getKey() : null,
            'actor_type' => $actor ? $actor->getMorphClass() : null,
            'actor_id' => $actor ? $actor->getKey() : null,
            'payload' => $payload,
            'status' => 'recorded',
        ]);
    }
}
