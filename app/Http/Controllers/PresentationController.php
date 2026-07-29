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
            'tab' => $request->input('tab', 'list'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'abstract' => 'nullable|string',
            'discipline' => 'nullable|string|max:255',
            'keywords' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'day' => 'nullable|string|max:50',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'submission_id' => 'nullable|string|max:50',
            'author_ids' => 'required|array|min:1',
            'author_ids.*' => 'exists:users,id',
        ]);

        $presentation = Presentation::create([
            'title' => $validated['title'],
            'abstract' => $validated['abstract'] ?? null,
            'discipline' => $validated['discipline'] ?? null,
            'keywords' => $validated['keywords'] ?? null,
            'location' => $validated['location'] ?? null,
            'day' => $validated['day'] ?? null,
            'start_time' => $validated['start_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
            'submission_id' => $validated['submission_id'] ?? null,
        ]);

        $presentation->authors()->sync($validated['author_ids']);

        return to_route('presentations.index')->with('success', 'Ponencia creada correctamente.');
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
                'title' => 'sometimes|string|max:255',
                'abstract' => 'sometimes|string',
                'discipline' => 'sometimes|string|max:255',
                'keywords' => 'sometimes|string',
                'location' => 'sometimes|string|max:255',
                'day' => 'sometimes|string|max:50',
                'start_time' => 'sometimes|date_format:H:i',
                'end_time' => 'sometimes|date_format:H:i',
                'submission_id' => 'sometimes|string|max:50',
                'author_ids' => 'sometimes|array|min:1',
                'author_ids.*' => 'exists:users,id',
            ]);

            if ($request->has('author_ids')) {
                $presentation->authors()->sync($validated['author_ids']);
            }

            if ($request->has('authors_presented')) {
                $request->validate([
                    'authors_presented' => 'array',
                    'authors_presented.*.user_id' => 'required|exists:users,id',
                    'authors_presented.*.presented' => 'required|boolean',
                ]);

                foreach ($request->input('authors_presented') as $item) {
                    $presentation->authors()->updateExistingPivot($item['user_id'], [
                        'presented' => $item['presented'],
                        'presented_at' => $item['presented'] ? now() : null,
                    ]);
                }
            }
        }

        $presentation->update($validated);

        return to_route('presentations.index')->with('success', 'Ponencia actualizada correctamente.');
    }
}
