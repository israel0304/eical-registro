<?php

namespace App\Jobs;

use App\Models\EmailTrigger;
use App\Models\EventLog;
use App\Services\EmailDispatcher;
use App\Services\EventAudit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendTriggeredEmails implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public EmailTrigger $trigger,
        public array $payload = [],
        public ?EventLog $eventLog = null,
    ) {}

    public function handle(): void
    {
        if (EmailDispatcher::dispatch($this->trigger, $this->payload)) {
            if ($this->eventLog) {
                EventAudit::markSent($this->eventLog);
            }

            return;
        }

        if ($this->eventLog) {
            EventAudit::markFailed($this->eventLog, EmailDispatcher::lastError() ?? 'No se pudo enviar el correo.');
        }
    }
}
