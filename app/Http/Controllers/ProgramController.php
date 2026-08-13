<?php

namespace App\Http\Controllers;

use App\Models\Conference;
use App\Models\Presentation;
use App\Models\ProgramItem;
use App\Models\Workshop;
use App\Services\ProgramService;
use App\Support\EventSettings;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProgramController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->can('programa.view'), 403);

        ProgramService::ensureActivityItemsSynced();

        $groups = ProgramService::itemsByDay()->map(function (array $group) {
            $group['items'] = collect($group['items'])
                ->map(fn (ProgramItem $item) => $this->serialize($item))
                ->values();

            return $group;
        })->values();

        return Inertia::render('Programa/Index', [
            'groups' => $groups,
            'eventName' => EventSettings::nombre(),
            'days' => EventSettings::eventDays(),
            'blockTypes' => config('program.block_types', []),
            'canManage' => $request->user()->can('programa.manage'),
            'canPrint' => $request->user()->can('programa.print'),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can('programa.manage'), 403);

        $validated = $this->validateBlock($request);

        ProgramItem::create([
            'title' => $validated['title'],
            'day' => $validated['day'],
            'start_time' => $validated['start_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
            'location' => $validated['location'] ?? null,
            'block_type' => $validated['block_type'],
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Bloque agregado al programa.');
    }

    public function update(Request $request, ProgramItem $programItem)
    {
        abort_unless($request->user()->can('programa.manage'), 403);

        if ($programItem->activity_type !== null) {
            $this->updateLinkedActivity($request, $programItem);

            return back()->with('success', 'Horario actualizado. El cambio se refleja también en la actividad.');
        }

        $validated = $this->validateBlock($request);

        $programItem->update([
            'title' => $validated['title'],
            'day' => $validated['day'],
            'start_time' => $validated['start_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
            'location' => $validated['location'] ?? null,
            'block_type' => $validated['block_type'],
        ]);

        return back()->with('success', 'Bloque actualizado.');
    }

    public function destroy(Request $request, ProgramItem $programItem)
    {
        abort_unless($request->user()->can('programa.manage'), 403);

        abort_if($programItem->activity_type !== null, 422, 'Las actividades enlazadas no pueden eliminarse del programa.');

        $programItem->delete();

        return back()->with('success', 'Bloque eliminado del programa.');
    }

    public function print(Request $request)
    {
        abort_unless($request->user()->can('programa.print'), 403);

        ProgramService::ensureActivityItemsSynced();

        $groups = ProgramService::printItemsByDay()->map(function (array $group) {
            $group['items'] = collect($group['items'])
                ->map(fn (ProgramItem $item) => $this->serialize($item))
                ->values();

            return $group;
        })->values();

        return view('programa.print', [
            'groups' => $groups,
            'eventName' => EventSettings::nombre(),
        ]);
    }

    private function validateBlock(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'day' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:255'],
            'block_type' => ['required', 'string', 'in:'.implode(',', array_keys(config('program.block_types', [])))],
        ]);
    }

    /**
     * La edición de un item enlazado actualiza la actividad original;
     * el evento saved de la actividad re-sincroniza el item del programa.
     */
    private function updateLinkedActivity(Request $request, ProgramItem $programItem): void
    {
        $activity = $programItem->activity;

        abort_if($activity === null, 404, 'La actividad vinculada ya no existe.');

        $modelClass = get_class($activity);
        $requiredFields = $modelClass === Workshop::class
            ? ['day' => ['required', 'date'], 'start_time' => ['required', 'date_format:H:i'], 'end_time' => ['required', 'date_format:H:i', 'after:start_time']]
            : ['day' => ['nullable', 'date'], 'start_time' => ['nullable', 'date_format:H:i'], 'end_time' => ['nullable', 'date_format:H:i', 'after:start_time']];

        $validated = $request->validate(array_merge($requiredFields, [
            'location' => ['nullable', 'string', 'max:255'],
        ]));

        $activity->update($validated);
    }

    private function serialize(ProgramItem $item): array
    {
        $activity = $item->activity;

        return [
            'id' => $item->id,
            'title' => ProgramService::titleFor($item),
            'description' => $item->description,
            'location' => $item->location,
            'day' => $item->day?->format('Y-m-d'),
            'start_time' => $item->start_time?->format('H:i'),
            'end_time' => $item->end_time?->format('H:i'),
            'time_label' => $item->time_label,
            'block_type' => $item->block_type,
            'block_label' => $item->block_type ? (config('program.block_types')[$item->block_type] ?? $item->block_type) : null,
            'activity_type' => $item->activity_type,
            'activity_label' => $item->activity_type ? ProgramService::modelLabel($item->activity_type) : null,
            'activity_name' => $activity?->name ?? $activity?->title ?? null,
            'kind' => $item->activity_type !== null ? 'activity' : 'block',
            'details' => $this->activityDetails($item),
        ];
    }

    /**
     * Datos completos de la actividad enlazada para el modal de detalle.
     */
    private function activityDetails(ProgramItem $item): array
    {
        $activity = $item->activity;

        if ($activity instanceof Workshop) {
            $activity->loadMissing(['instructors:id,first_name,last_name,affiliation', 'moderators:id,first_name,last_name,affiliation']);

            return [
                'description' => $activity->description,
                'capacity' => $activity->capacity,
                'enrolled_count' => $activity->enrolledCount(),
                'available_spots' => $activity->hasAvailableSpots() ? $activity->availableSpots() : 0,
                'instructors' => $activity->instructors->map(fn ($user) => [
                    'name' => $user->name,
                    'affiliation' => $user->affiliation,
                ])->values(),
                'moderators' => $activity->moderators->map(fn ($user) => [
                    'name' => $user->name,
                    'affiliation' => $user->affiliation,
                ])->values(),
            ];
        }

        if ($activity instanceof Presentation) {
            $activity->loadMissing(['authors:id,first_name,last_name,affiliation', 'moderators:id,first_name,last_name,affiliation']);
            $authors = $activity->authors
                ->sortBy(fn ($user) => $user->pivot->author_order)
                ->values();

            return [
                'abstract' => $activity->abstract,
                'discipline' => $activity->discipline,
                'keywords' => $activity->keywords,
                'authors' => $authors->map(fn ($user) => [
                    'name' => $user->name,
                    'affiliation' => $user->affiliation,
                ])->values(),
                'moderators' => $activity->moderators->map(fn ($user) => [
                    'name' => $user->name,
                    'affiliation' => $user->affiliation,
                ])->values(),
            ];
        }

        if ($activity instanceof Conference) {
            $activity->loadMissing(['members:id,first_name,last_name,affiliation']);
            $members = $activity->members;
            $people = fn ($role) => $members
                ->filter(fn ($user) => $user->pivot->role === $role)
                ->map(fn ($user) => [
                    'name' => $user->name,
                    'affiliation' => $user->affiliation,
                ])
                ->values();

            return [
                'description' => $activity->description,
                'kind' => $activity->kind,
                'kind_label' => config('participation.kinds.conference.'.$activity->kind, $activity->kind),
                'speakers' => $people('speaker'),
                'moderators' => $people('moderator'),
            ];
        }

        return [];
    }
}
