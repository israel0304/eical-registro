<?php

namespace App\Services;

use App\Models\Conference;
use App\Models\Presentation;
use App\Models\User;
use App\Models\Workshop;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ModeratorAssignments
{
    /**
     * Asignaciones del usuario como moderador, enriquecidas con participantes
     * y semblanzas, ordenadas cronológicamente por día y hora.
     */
    public function assignmentsFor(User $user): Collection
    {
        $personColumns = 'id,first_name,last_name,affiliation,semblanza';

        $workshops = $user->moderatedWorkshops()
            ->with([
                'instructors:'.$personColumns,
                'moderators:'.$personColumns,
            ])
            ->get()
            ->map(fn (Workshop $w) => [
                'id' => $w->id,
                'title' => $w->name,
                'type' => 'Taller',
                'kind' => null,
                'url' => route('workshops.show', $w),
                'day' => $this->day($w->day),
                'start_time' => $w->start_time,
                'end_time' => $w->end_time,
                'location' => $w->location,
                'description' => trim((string) $w->description),
                'discipline' => null,
                'keywords' => null,
                'participants' => $this->participants($w->instructors, 'Instructor')
                    ->concat($this->participants($w->moderators, 'Moderador')),
            ]);

        $presentations = $user->moderatedPresentations()
            ->with([
                'authors:'.$personColumns,
                'moderators:'.$personColumns,
            ])
            ->get()
            ->map(fn (Presentation $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'type' => 'Ponencia',
                'kind' => null,
                'url' => route('presentations.show', $p),
                'day' => $this->day($p->day),
                'start_time' => $p->start_time,
                'end_time' => $p->end_time,
                'location' => $p->location,
                'description' => trim((string) $p->abstract),
                'discipline' => trim((string) $p->discipline),
                'keywords' => trim((string) $p->keywords),
                'participants' => $this->participants($p->authors, 'Autor')
                    ->concat($this->participants($p->moderators, 'Moderador')),
            ]);

        $conferenceKinds = [
            'magistral' => 'Magistral',
            'especial' => 'Especial',
            'simposio' => 'Simposio',
            'grupo_tematico' => 'Grupo temático',
        ];

        $conferences = $user->moderatedConferences()
            ->with(['members:'.$personColumns])
            ->get()
            ->map(function (Conference $c) use ($conferenceKinds) {
                [$speakers, $moderators] = $c->members->partition(fn (User $u) => $u->pivot->role === 'speaker');

                return [
                    'id' => $c->id,
                    'title' => $c->title,
                    'type' => 'Conferencia',
                    'kind' => $conferenceKinds[$c->kind] ?? null,
                    'url' => route('conferences.show', $c),
                    'day' => $this->day($c->day),
                    'start_time' => $c->start_time,
                    'end_time' => $c->end_time,
                    'location' => $c->location,
                    'description' => trim((string) $c->description),
                    'discipline' => null,
                    'keywords' => null,
                    'participants' => $this->participants($speakers, 'Conferencista')
                        ->concat($this->participants($moderators, 'Moderador')),
                ];
            });

        return $workshops->concat($presentations)->concat($conferences)
            ->sortBy(fn (array $item) => ($item['day']?->format('Y-m-d') ?? '9999-12-31').' '.($item['start_time'] ?? '00:00'))
            ->values();
    }

    private function participants(Collection $users, string $role): Collection
    {
        $items = [];

        foreach ($users as $user) {
            $items[] = [
                'role' => $role,
                'name' => $user->name,
                'affiliation' => trim((string) $user->affiliation),
                'semblanza' => trim((string) $user->semblanza),
            ];
        }

        return collect($items);
    }

    private function day(mixed $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
