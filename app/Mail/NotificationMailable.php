<?php

namespace App\Mail;

use App\Support\VariableResolver;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Envelope;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

class NotificationMailable extends Mailable
{
    public string $renderedSubject;

    public string $renderedBody;

    public function __construct(
        string $subject,
        string $bodyHtml,
        public array $payload = [],
    ) {
        $this->renderedSubject = VariableResolver::resolve($subject, $payload);
        $this->renderedBody = VariableResolver::resolve($bodyHtml, $payload);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->renderedSubject,
        );
    }

    public function build()
    {
        foreach ($this->inlineImages() as $key => $image) {
            $this->withSymfonyMessage(function (Email $message) use ($key, $image) {
                $cid = 'embedded-'.$key.'@eical';

                $message->addPart(
                    (new DataPart($image['data'], $image['name'], $image['mime']))
                        ->asInline()
                        ->setContentId($cid)
                );

                $this->renderedBody = str_replace($image['dataUri'], 'cid:'.$cid, $this->renderedBody);
                $message->html(view('emails.layout', ['subject' => $this->renderedSubject, 'bodyHtml' => $this->renderedBody])->render());
            });
        }

        return $this->view('emails.layout', ['subject' => $this->renderedSubject, 'bodyHtml' => $this->renderedBody]);
    }

    /**
     * Devuelve las imágenes del payload en formato data URI para poder
     * incrustarlas como attachment inline (cid:) en lugar de data URI.
     *
     * @return array<string, array{data: string, name: string, mime: string, dataUri: string}>
     */
    private function inlineImages(): array
    {
        $images = [];

        foreach ($this->payload as $key => $value) {
            if (is_string($value) && preg_match('/^data:image\/(png|jpe?g|gif|webp);base64,([A-Za-z0-9+\/]+={0,2})$/i', $value, $matches)) {
                $ext = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];

                $images[$key] = [
                    'data' => base64_decode($matches[2], true) ?: '',
                    'name' => $key.'.'.$ext,
                    'mime' => 'image/'.$matches[1],
                    'dataUri' => $value,
                ];
            }
        }

        return $images;
    }
}
