<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\Setting;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;

class EventSettings
{
    public static function nombre(): string
    {
        return self::get('evento_nombre', 'EICAL 2026');
    }

    public static function lugar(): string
    {
        return (string) self::get('evento_lugar', '');
    }

    public static function checkinEnabled(): bool
    {
        return (bool) self::get('evento_checkin_enabled', false);
    }

    public static function checkinTimeRestricted(): bool
    {
        return (bool) self::get('evento_checkin_time_restricted', true);
    }

    public static function timezone(): string
    {
        return (string) config('events.timezone', 'America/Mexico_City');
    }

    public static function checkinGraceHours(): int
    {
        return max(0, (int) self::get('evento_checkin_grace_hours', 2));
    }

    public static function minDays(): int
    {
        return max(1, (int) self::get('evento_min_dias', 2));
    }

    public static function startDate(): ?string
    {
        return self::dateOrNull(self::get('evento_fecha_inicio'));
    }

    public static function endDate(): ?string
    {
        return self::dateOrNull(self::get('evento_fecha_fin'));
    }

    public static function totalDays(): int
    {
        $start = self::startDate();
        $end = self::endDate();

        if ($start === null || $end === null) {
            return 0;
        }

        return max(1, CarbonImmutable::parse($start)->diffInDays(CarbonImmutable::parse($end)) + 1);
    }

    public static function dayNumber(?string $date): ?int
    {
        $start = self::startDate();

        if ($date === null || $start === null) {
            return null;
        }

        $diff = CarbonImmutable::parse($start)->diffInDays(CarbonImmutable::parse($date));

        return $diff >= 0 ? $diff + 1 : null;
    }

    public static function dayLabel(?string $date): string
    {
        $number = self::dayNumber($date);

        if ($number !== null && self::totalDays() > 0) {
            return "Día {$number} de ".self::totalDays();
        }

        return $date ?? '';
    }

    /**
     * Etiqueta de fechas del evento para {fecha_evento}, p. ej. "12 al 15 de agosto de 2026".
     */
    public static function rangoFechas(): string
    {
        $format = function (?string $date) {
            if ($date === null) {
                return null;
            }

            return CarbonImmutable::parse($date)->locale('es')->translatedFormat('j \\d\\e F \\d\\e Y');
        };

        $start = $format(self::startDate());
        $end = $format(self::endDate());

        if ($start === null) {
            return '';
        }

        return $end !== null && $end !== $start ? "{$start} al {$end}" : $start;
    }

    public static function attendedDays(int $userId): int
    {
        return Attendance::query()
            ->where('user_id', $userId)
            ->whereNull('workshop_id')
            ->whereNull('presentation_id')
            ->distinct()
            ->count('event_day');
    }

    public static function qualifies(int $userId): bool
    {
        return self::attendedDays($userId) >= self::minDays();
    }

    public static function eventDays(): array
    {
        $start = self::startDate();
        $end = self::endDate();

        if ($start === null || $end === null) {
            return [];
        }

        $days = [];
        foreach (CarbonPeriod::create($start, $end) as $date) {
            $days[] = $date->format('Y-m-d');
        }

        return $days;
    }

    /** Ruta (disco public) del archivo de plantilla de presentaciones, o null. */
    public static function presentationTemplatePath(): ?string
    {
        $path = self::get('plantilla_presentacion_path');

        return is_string($path) && $path !== '' ? $path : null;
    }

    /** Nombre original del archivo de plantilla de presentaciones, o null. */
    public static function presentationTemplateName(): ?string
    {
        $name = self::get('plantilla_presentacion_nombre');

        return is_string($name) && $name !== '' ? $name : null;
    }

    private static function get(string $key, mixed $default = null): mixed
    {
        $value = Setting::query()->where('key', $key)->value('value');

        return $value === null ? $default : $value;
    }

    private static function dateOrNull(mixed $value): ?string
    {
        if (is_string($value) && $value !== '') {
            try {
                return CarbonImmutable::parse($value)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
