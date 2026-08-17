<?php

namespace App\Services;

use App\Mail\EmailTemplateMailable;
use App\Models\EmailTrigger;
use App\Models\User;
use App\Support\EventCatalog;
use App\Support\VariableResolver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailDispatcher
{
    private static ?string $lastError = null;

    public static function lastError(): ?string
    {
        return self::$lastError;
    }

    /**
     * Resuelve plantilla y destinatario a partir del trigger, renderiza
     * las variables y envía el correo. Devuelve true si se envió.
     */
    public static function dispatch(EmailTrigger $trigger, array $payload = []): bool
    {
        self::$lastError = null;

        $template = $trigger->template;

        if (! $template) {
            self::$lastError = 'El disparador no tiene una plantilla asignada.';

            return false;
        }

        $to = self::resolveRecipient($trigger, $payload);

        if (blank($to)) {
            self::$lastError = "No se pudo resolver el destinatario (variable: {$trigger->to}).";

            return false;
        }

        try {
            Mail::to($to)->send(new EmailTemplateMailable($template, $payload));

            return true;
        } catch (\Throwable $e) {
            self::$lastError = $e->getMessage();
            Log::error('EmailDispatcher: error enviando correo', [
                'event_key' => $trigger->event_key,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Prepara los datos de una plantilla para la vista previa en vivo.
     */
    public static function preview(string $eventKey, string $subject, string $bodyHtml): array
    {
        $sample = self::samplePayload($eventKey);

        return [
            'subject' => VariableResolver::resolve($subject, $sample),
            'body_html' => VariableResolver::resolve($bodyHtml, $sample),
        ];
    }

    public static function samplePayload(string $eventKey): array
    {
        $variables = EventCatalog::variables($eventKey);

        $samples = [];

        foreach (array_keys($variables) as $key) {
            $samples[$key] = match ($key) {
                'qrImage' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
                'url_activacion', 'url_escaneo' => 'https://ejemplo.com/enlace-de-ejemplo',
                'nombre_completo' => 'María González',
                'nombre', 'first_name' => 'María',
                'last_name' => 'González',
                'taller', 'name' => 'Taller de ejemplo EICAL',
                'dia', 'day' => 'Lunes 20 de julio',
                'hora_inicio', 'start_time' => '09:00',
                'hora_fin', 'end_time' => '11:00',
                'lugar', 'location' => 'Aula Magna',
                'submission_id' => '12345',
                'title' => 'Innovación en el aula: buenas prácticas',
                'description' => 'Descripción de la actividad de ejemplo.',
                'abstract' => 'Resumen de la ponencia de ejemplo.',
                'capacity' => '40',
                'email' => 'maria.gonzalez@ejemplo.com',
                default => 'Ejemplo de '.$key,
            };
        }

        return $samples;
    }

    private static function resolveRecipient(EmailTrigger $trigger, array $payload): mixed
    {
        if ($trigger->role_id) {
            $emails = User::query()
                ->whereHas('roles', fn ($q) => $q->whereKey($trigger->role_id))
                ->pluck('email')
                ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
                ->values()
                ->all();

            return $emails ?: null;
        }

        $to = $payload[$trigger->to] ?? $payload['destinatario'] ?? null;

        if (blank($to)) {
            return null;
        }

        if (is_array($to)) {
            $to = array_values(array_filter($to, fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL)));

            return $to;
        }

        return filter_var($to, FILTER_VALIDATE_EMAIL) ? $to : null;
    }
}
