<?php

namespace App\Http\Controllers;

use App\Models\Presentation;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PresentationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Presentation::with('authors');

        if ($user->isPonente()) {
            $query->whereHas('authors', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('discipline', 'like', "%{$search}%");
            });
        }

        $presentations = $query->orderBy('day')->orderBy('start_time')->paginate(15)->withQueryString();

        return Inertia::render('Presentations/Index', [
            'presentations' => $presentations,
            'filters' => $request->only(['search']),
        ]);
    }

    public function show(Presentation $presentation)
    {
        $presentation->load('authors');

        return Inertia::render('Presentations/Show', [
            'presentation' => $presentation,
        ]);
    }

    public function update(Request $request, Presentation $presentation)
    {
        $user = $request->user();

        if ($user->isPonente()) {
            $validated = $request->validate([
                'title' => 'sometimes|string|max:255',
                'abstract' => 'sometimes|string',
                'discipline' => 'sometimes|string|max:255',
                'keywords' => 'sometimes|string',
            ]);
        } else {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'abstract' => 'nullable|string',
                'discipline' => 'nullable|string|max:255',
                'keywords' => 'nullable|string',
                'location' => 'nullable|string|max:255',
                'day' => 'nullable|string|max:50',
                'start_time' => 'nullable|date_format:H:i',
                'end_time' => 'nullable|date_format:H:i',
            ]);
        }

        $presentation->update($validated);

        return back()->with('success', 'Ponencia actualizada correctamente.');
    }
}
