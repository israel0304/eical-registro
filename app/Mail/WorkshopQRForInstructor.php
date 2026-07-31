<?php

namespace App\Mail;

use App\Models\User;
use App\Models\Workshop;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WorkshopQRForInstructor extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Workshop $workshop,
        public User $instructor,
        public string $qrPngBase64,
        public string $scanUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Código QR de asistencia - '.$this->workshop->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.workshop-qr-for-instructor',
            with: [
                'instructorName' => $this->instructor->name,
                'workshopName' => $this->workshop->name,
                'day' => $this->workshop->day,
                'startTime' => $this->workshop->start_time,
                'endTime' => $this->workshop->end_time,
                'location' => $this->workshop->location,
                'scanUrl' => $this->scanUrl,
                'qrImageBase64' => $this->qrPngBase64,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
