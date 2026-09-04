<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Conference;
use App\Models\Presentation;
use App\Models\User;
use App\Models\Workshop;
use App\Services\ProgramTemplateRenderer;
use App\Support\EventSettings;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Inertia\Inertia;

class AssignmentController extends Controller
{
    public function index(Request $request)
    {
        $assignments = $this->assignmentsFor($request->user())->map(fn (array $item) => [
            'id' => $item['id'],
            'title' => $item['title'],
            'type' => $item['type'],
            'location' => $item['location'],
            'day' => $item['day']?->format('Y-m-d'),
            'start_time' => $item['start_time'],
            'end_time' => $item['end_time'],
            'url' => $item['url'],
        ]);

        return Inertia::render('Asignaciones/Index', [
            'assignments' => $assignments,
        ]);
    }

    public function print(Request $request): Response
    {
        abort_unless($request->user()->can('asignaciones.view'), 403);

        return response($this->printView(route('asignaciones.print-pdf'), false), 200, [
            'Content-Type' => 'text/html',
        ]);
    }

    public function printPdf(Request $request): Response
    {
        abort_unless($request->user()->can('asignaciones.view'), 403);

        $pdf = (new ProgramTemplateRenderer)->renderBladePdf($this->printView(null, true));

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename=mis_asignaciones_'.date('Y-m-d').'.pdf',
        ]);
    }

    public function show(Request $request, string $type, int $id): JsonResponse
    {
        $user = $request->user();

        return match ($type) {
            'workshop' => $this->showWorkshop($user, $id),
            'presentation' => $this->showPresentation($user, $id),
            'conference' => $this->showConference($user, $id),
            default => response()->json(['error' => 'Tipo no válido'], 404),
        };
    }

    /**
     * Asignaciones del usuario como moderador, enriquecidas con participantes
     * y semblanzas, ordenadas cronológicamente por día y hora.
     */
    private function assignmentsFor(User $user): Collection
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
            'mesa_dialogo' => 'Mesa de dialogo',
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

    private function printView(?string $pdfUrl, bool $forPdf): string
    {
        $user = request()->user();

        return (string) view('asignaciones.print', [
            'assignments' => $this->assignmentsFor($user),
            'eventName' => EventSettings::nombre(),
            'moderator' => $user,
            'pdfUrl' => $pdfUrl,
            'forPdf' => $forPdf,
        ]);
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

    private function showWorkshop($user, int $id): JsonResponse
    {
        $workshop = Workshop::withCount(['enrollments as enrolled_count' => function ($q) {
            $q->where('status', 'enrolled');
        }])->with(['instructors', 'moderators', 'creator'])->findOrFail($id);

        $isAssignedModerator = $workshop->moderators()->where('users.id', $user->id)->exists();
        abort_unless($user->canViewActivity('workshops.view', $isAssignedModerator), 403);

        $workshop->load(['enrollments.user']);
        $attendedUserIds = Attendance::where('workshop_id', $workshop->id)
            ->pluck('user_id')
            ->all();

        $workshop->setRelation(
            'enrollments',
            $workshop->enrollments->map(function ($enrollment) use ($attendedUserIds) {
                $enrollment->has_attendance = in_array($enrollment->user_id, $attendedUserIds, true);

                return $enrollment;
            })
        );

        return response()->json($workshop);
    }

    private function showPresentation($user, int $id): JsonResponse
    {
        $presentation = Presentation::with(['authors', 'moderators'])->findOrFail($id);
        $isAssignedModerator = $presentation->moderators()->where('users.id', $user->id)->exists();
        $isAuthor = $presentation->authors()->where('users.id', $user->id)->exists();
        abort_unless($user->canViewActivity('presentations.view', $isAssignedModerator || $isAuthor), 403);

        return response()->json([
            'presentation' => $presentation,
            'isAuthor' => $isAuthor,
        ]);
    }

    private function showConference($user, int $id): JsonResponse
    {
        $conference = Conference::with(['creator', 'members'])->findOrFail($id);
        $isAssignedModerator = $conference->moderators()->where('users.id', $user->id)->exists();
        abort_unless($user->canViewActivity('conferences.view', $isAssignedModerator), 403);

        return response()->json($conference);
    }
}
