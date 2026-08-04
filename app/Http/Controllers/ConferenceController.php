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
        abort_if(! $request->user()->isAdmin(), 403);

        return Inertia::render('Conferences/Create', [
            'kinds' => Conference::KINDS,
        ]);
    }

    public function store(Request $request)
    {
        abort_if(! $request->user()->isAdmin(), 403);

        $validated = $this->validateRequest($request);
        $members = $validated['members'] ?? [];
        unset($validated['members']);

        $conference = Conference::create($validated + ['created_by' => $request->user()->id]);

        $this->syncMembers($conference, $members);
        $this->syncMemberRoles($members);

        return redirect()->route('conferences.show', $conference)->with('success', 'Conferencia creada correctamente.');
    }

    public function show(Conference $conference)
    {
        $conference->load(['creator', 'members']);

        return Inertia::render('Conferences/Show', [
            'conference' => $conference,
        ]);
    }

    public function edit(Request $request, Conference $conference)
    {
        abort_if(! $request->user()->isAdmin(), 403);

        $conference->load('members');

        return Inertia::render('Conferences/Edit', [
            'conference' => $conference,
            'kinds' => Conference::KINDS,
        ]);
    }

    public function update(Request $request, Conference $conference)
    {
        abort_if(! $request->user()->isAdmin(), 403);

        $validated = $this->validateRequest($request);
        $members = $validated['members'] ?? [];
        unset($validated['members']);

        $conference->update($validated);
        $this->syncMembers($conference, $members);
        $this->syncMemberRoles($members);

        return redirect()->route('conferences.show', $conference)->with('success', 'Conferencia actualizada correctamente.');
    }

    public function destroy(Conference $conference)
    {
        abort_if(! request()->user()->isAdmin(), 403);

        $conference->delete();

        return back()->with('success', 'Conferencia eliminada correctamente.');
    }

    public function toggleActivation(Request $request, Conference $conference, User $user)
    {
        abort_if(! $request->user()->isAdmin(), 403);

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
        ]);
    }

    private function syncMembers(Conference $conference, array $members): void
    {
        $pivot = [];

        foreach ($members as $member) {
            $pivot[$member['id']] = ['role' => 'speaker'];
        }

        $conference->members()->sync($pivot);
    }

    private function syncMemberRoles(array $members): void
    {
        $speakerRoleId = Role::where('name', 'Speaker')->value('id') ?? 5;

        foreach ($members as $member) {
            User::find($member['id'])?->roles()->syncWithoutDetaching([$speakerRoleId]);
        }
    }
}
