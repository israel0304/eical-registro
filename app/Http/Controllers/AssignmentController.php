<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Conference;
use App\Models\Presentation;
use App\Models\Workshop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AssignmentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $workshops = $user->moderatedWorkshops()->orderBy('day')->orderBy('start_time')->get()->map(function ($w) {
            return [
                'id' => $w->id,
                'title' => $w->name,
                'type' => 'Taller',
                'location' => $w->location,
                'day' => $w->day,
                'start_time' => $w->start_time,
                'end_time' => $w->end_time,
                'url' => route('workshops.show', $w),
            ];
        });

        $presentations = $user->moderatedPresentations()->orderBy('day')->orderBy('start_time')->get()->map(function ($p) {
            return [
                'id' => $p->id,
                'title' => $p->title,
                'type' => 'Ponencia',
                'location' => $p->location,
                'day' => $p->day,
                'start_time' => $p->start_time,
                'end_time' => $p->end_time,
                'url' => route('presentations.show', $p),
            ];
        });

        $conferences = $user->moderatedConferences()->orderBy('day')->orderBy('start_time')->get()->map(function ($c) {
            return [
                'id' => $c->id,
                'title' => $c->title,
                'type' => 'Conferencia',
                'location' => $c->location,
                'day' => $c->day ? $c->day->format('Y-m-d') : null,
                'start_time' => $c->start_time,
                'end_time' => $c->end_time,
                'url' => route('conferences.show', $c),
            ];
        });

        $assignments = $workshops->concat($presentations)->concat($conferences)->sortBy(function ($item) {
            return ($item['day'] ?? '9999-12-31').' '.($item['start_time'] ?? '00:00');
        })->values();

        return Inertia::render('Asignaciones/Index', [
            'assignments' => $assignments,
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
