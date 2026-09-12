<?php

namespace Tests\Unit;

use App\Mail\NotificationMailable;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationMailableTest extends TestCase
{
    public function test_resolves_variables_in_subject_and_body(): void
    {
        $mailable = new NotificationMailable(
            'Hola {{ nombre_completo }}',
            '<p>Hola {{ nombre_completo }}, tu rol es {{ rol }}</p>',
            [
                'nombre_completo' => 'Ana Torres',
                'rol' => 'Ponente',
            ],
        );

        $this->assertSame('Hola Ana Torres', $mailable->envelope()->subject);

        $mailer = Mail::mailer('array');
        $transport = $mailer->getSymfonyTransport();
        $mailer->to('ana@test.com')->send($mailable);

        $html = $transport->messages()[0]->getOriginalMessage()->getHtmlBody();

        $this->assertStringContainsString('Hola Ana Torres', $html);
        $this->assertStringContainsString('tu rol es Ponente', $html);
        $this->assertStringNotContainsString('{{', $html);
        $this->assertStringContainsString('<title>Hola Ana Torres</title>', $html);
    }

    public function test_leaves_unknown_variables_untouched(): void
    {
        $mailable = new NotificationMailable(
            'Asunto {{ desconocida }}',
            '<p>Cuerpo {{ otro }}</p>',
            ['nombre_completo' => 'Luis'],
        );

        $this->assertSame('Asunto {{ desconocida }}', $mailable->envelope()->subject);
    }
}
