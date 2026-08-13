<?php

namespace App\Models\Concerns;

use App\Services\ProgramService;

/**
 * Mantiene automáticamente el item del programa en sincronía con el
 * horario de la actividad (taller, ponencia o conferencia).
 *
 * Al guardar/crear la actividad se hace un upsert del ProgramItem;
 * al eliminar (incluye soft delete) se elimina su item; al restaurar
 * se vuelve a crear. La dirección contraria (programa → actividad)
 * se maneja de forma explícita en ProgramController, por lo que no
 * hay recursión.
 *
 * El modelo debe declarar:
 *
 *     protected static string $programActivityType = 'workshop';
 */
trait EnlazaPrograma
{
    public static function bootEnlazaPrograma(): void
    {
        static::saved(function ($model) {
            if (
                $model->wasRecentlyCreated
                || $model->isDirty(['day', 'start_time', 'end_time', 'location'])
            ) {
                ProgramService::syncFromActivity(
                    static::$programActivityType,
                    $model->getKey(),
                    [
                        'day' => $model->day,
                        'start_time' => $model->start_time,
                        'end_time' => $model->end_time,
                        'location' => $model->location,
                    ],
                );
            }
        });

        static::deleted(function ($model) {
            ProgramService::removeFromActivity(static::$programActivityType, $model->getKey());
        });

        static::restored(function ($model) {
            ProgramService::syncFromActivity(
                static::$programActivityType,
                $model->getKey(),
                [
                    'day' => $model->day,
                    'start_time' => $model->start_time,
                    'end_time' => $model->end_time,
                    'location' => $model->location,
                ],
            );
        });
    }
}
