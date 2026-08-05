<?php

namespace App\Http\Controllers;

use App\Models\ParticipationType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ParticipationTypeController extends Controller
{
    public function index(Request $request)
    {
        $participationTypes = ParticipationType::query()
            ->withCount('templates')
            ->orderBy('event_kind')
            ->orderBy('role')
            ->get();

        return Inertia::render('Constancias/Tipos/Index', [
            'participationTypes' => $participationTypes,
        ]);
    }

    public function store(Request $request)
    {
        abort_if(! $request->user()->isAdmin(), 403);

        $validated = $this->validateType($request);

        ParticipationType::create($validated + ['is_active' => (bool) ($validated['is_active'] ?? true)]);

        return back()->with('success', 'Tipo de participación creado.');
    }

    public function update(Request $request, ParticipationType $type)
    {
        abort_if(! $request->user()->isAdmin(), 403);

        $validated = $this->validateType($request, $type);

        $type->update($validated + ['is_active' => (bool) ($validated['is_active'] ?? true)]);

        return back()->with('success', 'Tipo de participación actualizado.');
    }

    public function destroy(Request $request, ParticipationType $type)
    {
        abort_if(! $request->user()->isAdmin(), 403);

        $type->delete();

        return back()->with('success', 'Tipo de participación eliminado.');
    }

    private function validateType(Request $request, ?ParticipationType $type = null): array
    {
        return $request->validate([
            'key' => ['required', 'string', 'max:100', Rule::unique('participation_types', 'key')->ignore($type?->id)],
            'label' => ['required', 'string', 'max:255'],
            'event_kind' => ['required', Rule::in(['workshop', 'presentation', 'conference', 'event'])],
            'kind' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(['enrolled_attendance', 'instructor', 'presented_author', 'speaker', 'moderator'])],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
