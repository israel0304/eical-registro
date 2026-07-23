<?php

namespace App\Mail;

use App\Models\User;
use App\Models\Workshop;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WorkshopEnrollmentConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Workshop $workshop,
        public User $user,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Inscripción confirmada - '.$this->workshop->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.workshop-enrollment-confirmation',
            with: [
                'workshopName' => $this->workshop->name,
                'userName' => $this->user->first_name.' '.$this->user->last_name,
                'day' => $this->workshop->day,
                'startTime' => $this->workshop->start_time,
                'endTime' => $this->workshop->end_time,
                'location' => $this->workshop->location,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
