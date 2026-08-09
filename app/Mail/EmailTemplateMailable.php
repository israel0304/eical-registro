<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Support\VariableResolver;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailTemplateMailable extends Mailable
{
    use SerializesModels;

    public string $renderedSubject;

    public string $renderedBody;

    public function __construct(
        public EmailTemplate $template,
        public array $payload = [],
    ) {
        $this->renderedSubject = VariableResolver::resolve($template->subject, $payload);
        $this->renderedBody = VariableResolver::resolve($template->body_html, $payload);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->renderedSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.layout',
            with: [
                'bodyHtml' => $this->renderedBody,
            ],
        );
    }
}
