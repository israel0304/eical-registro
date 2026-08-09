<?php

namespace App\Support;

class VariableResolver
{
    /**
     * Reemplaza las variables {{ clave }} del contenido con los valores
     * del payload. Los valores null se sustituyen por cadena vacía.
     */
    public static function resolve(string $content, array $payload = []): string
    {
        if ($content === '' || $content === null || empty($payload)) {
            return $content ?? '';
        }

        $search = [];
        $replace = [];

        foreach ($payload as $key => $value) {
            $search[] = '{{ '.$key.' }}';
            $replace[] = self::stringify($value);
        }

        return str_replace($search, $replace, $content);
    }

    public static function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Sí' : 'No';
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '';
        }

        return (string) $value;
    }
}
