<?php

namespace App\Services;

use App\Models\ProgramItem;
use App\Support\EventSettings;
use Illuminate\Support\Collection;

class ProgramService
{
    /**
     * Items del programa agrupados por día, en el orden esperado:
     * primero los bloques con hora, luego las actividades enlazadas ordenadas por día/hora.
     */
    public static function itemsByDay(): Collection
    {
        $items = ProgramItem::query()
            ->with(['activity'])
            ->orderBy('day')
            ->orderBy('start_time')
            ->orderBy('id')
            ->get();

        return static::groupByDay($items);
    }

    public static function printItemsByDay(): Collection
    {
        return static::itemsByDay();
    }

    /**
     * @param  Collection<int, ProgramItem>  $items
     */
    public static function groupByDay(Collection $items): Collection
    {
        $days = collect(EventSettings::eventDays());

        $groups = $items
            ->groupBy(fn (ProgramItem $item) => $item->day?->format('Y-m-d') ?? 'sin-dia')
            ->sortBy(function (Collection $group, string $key) use ($days) {
                $index = $days->search($key);

                return $index === false ? PHP_INT_MAX : $index;
            });

        return $days
            ->mapWithKeys(fn (string $day) => [
                $day => [
                    'label' => EventSettings::dayLabel($day),
                    'items' => $groups->get($day, collect()),
                ],
            ])
            ->reject(fn (array $group) => $group['items']->isEmpty());
    }

    /**
     * Título de un item del programa (bloque o actividad enlazada).
     */
    public static function titleFor(ProgramItem $item): string
    {
        if ($item->title !== null && trim($item->title) !== '') {
            return $item->title;
        }

        return $item->activity?->name ?? $item->activity?->title ?? 'Actividad';
    }

    /**
     * Inscribe una actividad en el programa (upsert).
     *
     * Usado por el trait EnlazaPrograma y por el comando programa:sync.
     * created_by es opcional (puede no haber usuario autenticado).
     */
    public static function syncFromActivity(?string $activityType, ?int $activityId, array $attributes = []): ProgramItem
    {
        if ($activityType === null || $activityId === null) {
            throw new \InvalidArgumentException('activity_type y activity_id son obligatorios.');
        }

        return ProgramItem::updateOrCreate(
            ['activity_type' => $activityType, 'activity_id' => $activityId],
            $attributes,
        );
    }

    public static function removeFromActivity(string $activityType, int $activityId): void
    {
        ProgramItem::where('activity_type', $activityType)
            ->where('activity_id', $activityId)
            ->delete();
    }

    /**
     * Crea los items del programa faltantes para todas las actividades
     * registradas (reconciliación defensiva antes de renderizar).
     */
    public static function ensureActivityItemsSynced(): void
    {
        foreach (config('program.activity_types', []) as $type => $modelClass) {
            $ids = $modelClass::query()->pluck('id');

            $existing = ProgramItem::query()
                ->where('activity_type', $type)
                ->whereIn('activity_id', $ids)
                ->pluck('activity_id')
                ->all();

            foreach ($ids->diff($existing) as $activityId) {
                $activity = $modelClass::find($activityId);

                if ($activity === null) {
                    continue;
                }

                static::syncFromActivity($type, $activityId, [
                    'day' => $activity->day,
                    'start_time' => $activity->start_time,
                    'end_time' => $activity->end_time,
                    'location' => $activity->location,
                ]);
            }
        }
    }

    public static function modelLabel(string $type): string
    {
        return match ($type) {
            'workshop' => 'Taller',
            'presentation' => 'Ponencia',
            'conference' => 'Conferencia',
            default => 'Actividad',
        };
    }
}
