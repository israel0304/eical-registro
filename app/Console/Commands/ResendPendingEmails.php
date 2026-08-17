<?php

namespace App\Console\Commands;

use App\Jobs\SendTriggeredEmails;
use App\Models\EventLog;
use Illuminate\Console\Command;

class ResendPendingEmails extends Command
{
    protected $signature = 'emails:resend-pending';

    protected $description = 'Re-encola correos cuyo event_log sigue en status recorded (nunca procesados por el queue worker)';

    public function handle(): int
    {
        $pending = EventLog::query()
            ->where('status', 'recorded')
            ->whereNotNull('trigger_id')
            ->get();

        if ($pending->isEmpty()) {
            $this->info('No hay correos pendientes.');

            return self::SUCCESS;
        }

        $this->info("{$pending->count()} correo(s) pendiente(s) encontrado(s):");
        $this->newLine();

        foreach ($pending as $log) {
            $trigger = $log->trigger;

            if (! $trigger || ! $trigger->is_active) {
                $this->warn("  #{$log->id} — trigger desactivado o inexistente, saltando.");

                continue;
            }

            SendTriggeredEmails::dispatch($trigger, $log->payload, $log);

            $to = $log->payload['destinatario'] ?? 'desconocido';
            $this->line("  #{$log->id} → {$to} encolado.");
        }

        $this->newLine();
        $this->info('Listo. Los correos se enviarán cuando el queue worker los procese.');

        return self::SUCCESS;
    }
}
