<?php

namespace App\Http\Controllers;

use App\Models\Conference;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ConferenceController extends Controller
{
    public function index(Request $request)
    {
        $query = Conference::with('members');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('title', 'like', "%{$search}%");
        }

        $conferences = $query->orderBy('day')->orderBy('start_time')->paginate(15)->withQueryString();

        return Inertia::render('Conferences/Index', [
            'conferences' => $conferences,
            'filters' => $request->only(['search']),
            'kinds' => Conference::KINDS,
        ]);
    }

    public function create(Request $request)
    {
        abort_unless($request->user()->can('conferences.create'), 403);

        return Inertia::render('Conferences/Create', [
            'kinds' => Conference::KINDS,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can('conferences.create'), 403);

        $validated = $this->validateRequest($request);
        $members = $validated['members'] ?? [];
        $moderatorIds = $validated['moderator_ids'] ?? [];
        unset($validated['members'], $validated['moderator_ids']);

        $conference = Conference::create($validated + ['created_by' => $request->user()->id]);

        $this->syncMembers($conference, $members, $moderatorIds);
        $this->syncMemberRoles($members, $moderatorIds);

        return redirect()->route('conferences.show', $conference)->with('success', 'Conferencia creada correctamente.');
    }

    public function show(Conference $conference)
    {
        $user = request()->user();
        $isAssignedModerator = $conference->moderators()->where('users.id', $user->id)->exists();

        abort_unless($user->canViewActivity('conferences.view', $isAssignedModerator), 403);

        $conference->load(['creator', 'members']);

        return Inertia::render('Conferences/Show', [
            'conference' => $conference,
        ]);
    }

    public function edit(Request $request, Conference $conference)
    {
        abort_unless($request->user()->can('conferences.edit'), 403);

        $conference->load('members');

        return Inertia::render('Conferences/Edit', [
            'conference' => $conference,
            'kinds' => Conference::KINDS,
        ]);
    }

    public function update(Request $request, Conference $conference)
    {
        abort_unless($request->user()->can('conferences.edit'), 403);

        $validated = $this->validateRequest($request);
        $members = $validated['members'] ?? [];
        $moderatorIds = $validated['moderator_ids'] ?? [];
        unset($validated['members'], $validated['moderator_ids']);

        $conference->update($validated);
        $this->syncMembers($conference, $members, $moderatorIds);
        $this->syncMemberRoles($members, $moderatorIds);

        return redirect()->route('conferences.show', $conference)->with('success', 'Conferencia actualizada correctamente.');
    }

    public function destroy(Conference $conference)
    {
        abort_unless(request()->user()->can('conferences.delete'), 403);

        $conference->delete();

        return back()->with('success', 'Conferencia eliminada correctamente.');
    }

    public function toggleActivation(Request $request, Conference $conference, User $user)
    {
        $currentUser = $request->user();
        $isAssignedModerator = $conference->moderators()->where('users.id', $currentUser->id)->exists();

        abort_unless($currentUser->canScoped('conferences.activate', 'conferences.view', $isAssignedModerator), 403);

        $member = $conference->members()->where('users.id', $user->id)->first();

        if ($member === null) {
            return back()->withErrors(['error' => 'El usuario no es miembro de esta conferencia.']);
        }

        $activated = ! (bool) $member->pivot->activated;

        $conference->members()->updateExistingPivot($user->id, [
            'activated' => $activated,
            'activated_at' => $activated ? now() : null,
        ]);

        return back()->with('success', $activated ? 'Constancia activada.' : 'Constancia desactivada.');
    }

    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'kind' => ['required', Rule::in(Conference::KINDS)],
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'day' => 'nullable|date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'members' => 'nullable|array',
            'members.*.id' => 'required|integer|exists:users,id',
            'moderator_ids' => 'nullable|array',
            'moderator_ids.*' => 'required|integer|exists:users,id',
        ]);
    }

    private function syncMembers(Conference $conference, array $members, array $moderatorIds): void
    {
        $pivot = [];

        foreach ($members as $member) {
            $pivot[$member['id']] = ['role' => 'speaker'];
        }

        foreach ($moderatorIds as $moderatorId) {
            $pivot[$moderatorId] = ['role' => 'moderator'];
        }

        $conference->members()->sync($pivot);
    }

    private function syncMemberRoles(array $members, array $moderatorIds): void
    {
        $speakerRoleId = Role::where('name', 'Speaker')->value('id');
        $moderatorRoleId = Role::where('name', 'Moderator')->value('id');

        foreach ($members as $member) {
            User::find($member['id'])?->roles()->syncWithoutDetaching([$speakerRoleId]);
        }

        foreach ($moderatorIds as $moderatorId) {
            User::find($moderatorId)?->roles()->syncWithoutDetaching([$moderatorRoleId]);
        }
    }
}
