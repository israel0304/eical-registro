<?php

namespace App\Support;

class EventCatalog
{
    /**
     * Devuelve la entrada del catálogo para un event_key (p. ej.
     * "workshop.enrollment"). Se accede al arreglo por clave literal porque
     * la notación de puntos de config() rompería claves que ya contienen dots.
     */
    public static function find(string $key): ?array
    {
        return config('events.events')[$key] ?? null;
    }

    public static function label(string $key): string
    {
        return self::find($key)['label'] ?? $key;
    }

    public static function variables(string $key): array
    {
        return self::find($key)['variables'] ?? [];
    }

    public static function supportsTrigger(string $key): bool
    {
        return (bool) (self::find($key)['has_trigger'] ?? false);
    }

    public static function toOptions(string $key): array
    {
        return self::find($key)['to_options'] ?? ['destinatario' => 'Destinatario del evento'];
    }
}
