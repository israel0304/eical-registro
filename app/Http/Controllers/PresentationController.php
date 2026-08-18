<?php

namespace App\Http\Controllers;

use App\Models\Presentation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PresentationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Presentation::with(['authors', 'moderators']);

        if ($user->hasPermission('presentations.my') && ! $user->hasPermission('presentations.view')) {
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
            'day' => 'nullable|date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'submission_id' => 'nullable|string|max:50',
            'author_ids' => 'required|array|min:1',
            'author_ids.*' => 'exists:users,id',
            'moderator_ids' => 'nullable|array',
            'moderator_ids.*' => 'exists:users,id',
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
        $this->syncAuthorRoles($validated['author_ids']);

        $moderatorIds = $validated['moderator_ids'] ?? [];
        $presentation->moderators()->sync($moderatorIds);
        $this->syncModeratorRoles($moderatorIds);

        return to_route('presentations.index')->with('success', 'Ponencia creada correctamente.');
    }

    public function show(Presentation $presentation)
    {
        $user = request()->user();
        $isAssignedModerator = $presentation->moderators()->where('users.id', $user->id)->exists();
        $isAuthor = $presentation->authors()->where('users.id', $user->id)->exists();

        abort_unless($user->canViewActivity('presentations.view', $isAssignedModerator || $isAuthor), 403);

        $presentation->load(['authors', 'moderators']);

        return Inertia::render('Presentations/Show', [
            'presentation' => $presentation,
        ]);
    }

    public function update(Request $request, Presentation $presentation)
    {
        $user = $request->user();
        $isAssignedModerator = $presentation->moderators()->where('users.id', $user->id)->exists();
        $isAuthor = $presentation->authors()->where('users.id', $user->id)->exists();

        if ($request->has('authors_presented')) {
            abort_unless($user->can('presentations.presented'), 403);

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

            if (! $request->inertia()) {
                return response()->json(['ok' => true]);
            }

            return to_route('presentations.index')->with('success', 'Ponencia actualizada correctamente.');
        }

        if ($user->can('presentations.edit')) {
            $validated = $request->validate([
                'title' => 'sometimes|string|max:255',
                'abstract' => 'sometimes|string',
                'discipline' => 'sometimes|string|max:255',
                'keywords' => 'sometimes|string',
                'location' => 'nullable|string|max:255',
                'day' => 'nullable|date',
                'start_time' => 'nullable|date_format:H:i',
                'end_time' => 'nullable|date_format:H:i',
                'submission_id' => 'nullable|string|max:50',
                'author_ids' => 'sometimes|array|min:1',
                'author_ids.*' => 'exists:users,id',
                'moderator_ids' => 'sometimes|array',
                'moderator_ids.*' => 'exists:users,id',
            ]);

            $moderatorIds = $validated['moderator_ids'] ?? null;
            unset($validated['moderator_ids']);

            if ($request->has('author_ids')) {
                $presentation->authors()->sync($validated['author_ids']);
                $this->syncAuthorRoles($validated['author_ids']);
            }

            if ($moderatorIds !== null) {
                $presentation->moderators()->sync($moderatorIds);
                $this->syncModeratorRoles($moderatorIds);
            }
        } elseif ($isAssignedModerator) {
            $validated = [];
        } elseif ($user->hasPermission('presentations.my') && $isAuthor) {
            $validated = $request->validate([
                'title' => 'sometimes|string|max:255',
                'abstract' => 'sometimes|string',
                'discipline' => 'sometimes|string|max:255',
                'keywords' => 'sometimes|string',
            ]);
        } else {
            abort_if(! $isAssignedModerator, 403);
            $validated = [];
        }

        if (! empty($validated)) {
            $presentation->update($validated);
        }

        return to_route('presentations.index')->with('success', 'Ponencia actualizada correctamente.');
    }

    public function destroy(Request $request, Presentation $presentation)
    {
        abort_unless($request->user()->can('presentations.delete'), 403);

        $presentation->delete();

        return to_route('presentations.index')->with('success', 'Ponencia eliminada correctamente.');
    }

    private function syncAuthorRoles(array $authorIds): void
    {
        $ponenteRoleId = Role::where('name', 'Ponente')->value('id');

        foreach ($authorIds as $authorId) {
            User::find($authorId)?->roles()->syncWithoutDetaching([$ponenteRoleId]);
        }
    }

    private function syncModeratorRoles(array $moderatorIds): void
    {
        $moderatorRoleId = Role::where('name', 'Moderator')->value('id');

        foreach ($moderatorIds as $moderatorId) {
            User::find($moderatorId)?->roles()->syncWithoutDetaching([$moderatorRoleId]);
        }
    }
}
