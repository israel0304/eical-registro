<?php

namespace App\Http\Controllers;

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
}
