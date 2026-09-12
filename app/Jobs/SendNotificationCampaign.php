<?php

namespace App\Jobs;

use App\Mail\NotificationMailable;
use App\Models\NotificationRecipient;
use App\Models\NotificationSend;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendNotificationCampaign implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 3600;

    public function __construct(
        public NotificationSend $send,
    ) {}

    public function handle(): void
    {
        $this->send->update(['status' => NotificationSend::STATUS_PROCESSING]);

        $recipients = $this->send->recipients()->where('status', NotificationRecipient::STATUS_PENDING)->get();

        foreach ($recipients as $recipient) {
            try {
                Mail::bcc($recipient->email)->send(new NotificationMailable(
                    $this->send->subject,
                    $this->send->body_html,
                    $recipient->payload ?? [],
                ));

                $recipient->update([
                    'status' => NotificationRecipient::STATUS_SENT,
                    'sent_at' => now(),
                    'error' => null,
                ]);
            } catch (Throwable $exception) {
                $recipient->update([
                    'status' => NotificationRecipient::STATUS_FAILED,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $this->finalize();
    }

    public function failed(?Throwable $throwable): void
    {
        $this->send->update([
            'status' => NotificationSend::STATUS_FAILED,
            'error' => $throwable?->getMessage(),
            'completed_at' => now(),
        ]);
    }

    private function finalize(): void
    {
        $totalSent = $this->send->recipients()->where('status', NotificationRecipient::STATUS_SENT)->count();
        $totalFailed = $this->send->recipients()->where('status', NotificationRecipient::STATUS_FAILED)->count();

        $status = null;

        if ($totalSent > 0 && $totalFailed > 0) {
            $status = NotificationSend::STATUS_PARTIAL;
        } elseif ($totalSent > 0) {
            $status = NotificationSend::STATUS_SENT;
        } else {
            $status = NotificationSend::STATUS_FAILED;
        }

        $this->send->update([
            'sent_count' => $totalSent,
            'failed_count' => $totalFailed,
            'status' => $status,
            'completed_at' => now(),
        ]);
    }
}
