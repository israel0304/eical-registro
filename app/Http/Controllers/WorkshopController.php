<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Workshop;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkshopController extends Controller
{
    public function index(Request $request)
    {
        $query = Workshop::withCount(['enrollments as enrolled_count' => function ($q) {
            $q->where('status', 'enrolled');
        }])->with('instructors');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        $workshops = $query->orderBy('day')->orderBy('start_time')->paginate(15)->withQueryString();

        return Inertia::render('Workshops/Index', [
            'workshops' => $workshops,
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(Request $request)
    {
        abort_if(! $request->user()->isAdmin(), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'capacity' => 'required|integer|min:1',
            'location' => 'required|string|max:255',
            'day' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'qr_time_restricted' => 'boolean',
            'instructors' => 'required|array|min:1|max:5',
            'instructors.*.name' => 'required|string|max:255',
            'instructors.*.institution' => 'nullable|string|max:255',
            'instructors.*.email' => 'required|email|max:255',
        ]);

        $validated['created_by'] = $request->user()->id;
        $instructorsData = $validated['instructors'] ?? [];
        unset($validated['instructors']);

        $workshop = Workshop::create($validated);

        foreach ($instructorsData as $instructor) {
            $workshop->instructors()->create($instructor);
        }

        return back()->with('success', 'Taller creado correctamente.');
    }

    public function show(Workshop $workshop)
    {
        $workshop->loadCount(['enrollments as enrolled_count' => function ($q) {
            $q->where('status', 'enrolled');
        }]);
        $workshop->load(['enrollments.user', 'instructors', 'creator']);

        // Add has_attendance flag to each enrollment
        $workshop->setRelation(
            'enrollments',
            $workshop->enrollments->map(function ($enrollment) {
                $enrollment->has_attendance = Attendance::where('user_id', $enrollment->user_id)
                    ->where('workshop_id', $enrollment->workshop_id)
                    ->exists();

                return $enrollment;
            })
        );

        return Inertia::render('Workshops/Show', [
            'workshop' => $workshop,
        ]);
    }

    public function update(Request $request, Workshop $workshop)
    {
        abort_if(! $request->user()->isAdmin(), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'capacity' => 'required|integer|min:1',
            'location' => 'required|string|max:255',
            'day' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'qr_time_restricted' => 'boolean',
            'instructors' => 'required|array|min:1|max:5',
            'instructors.*.name' => 'required|string|max:255',
            'instructors.*.institution' => 'nullable|string|max:255',
            'instructors.*.email' => 'required|email|max:255',
        ]);

        $instructorsData = $validated['instructors'] ?? [];
        unset($validated['instructors']);

        $workshop->update($validated);

        // Sync instructors: delete existing, recreate
        $workshop->instructors()->delete();
        foreach ($instructorsData as $instructor) {
            $workshop->instructors()->create($instructor);
        }

        return back()->with('success', 'Taller actualizado correctamente.');
    }

    public function destroy(Workshop $workshop)
    {
        abort_if(! request()->user()->isAdmin(), 403);

        $workshop->delete();

        return back()->with('success', 'Taller eliminado correctamente.');
    }
}
