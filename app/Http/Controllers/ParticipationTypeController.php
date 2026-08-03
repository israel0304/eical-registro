<?php

namespace App\Http\Controllers;

use App\Models\ParticipationType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ParticipationTypeController extends Controller
{
    public function store(Request $request)
    {
        abort_if(! $request->user()->isAdmin(), 403);

        $validated = $request->validate([
            'key' => ['required', 'string', 'max:100', 'unique:participation_types,key'],
            'label' => ['required', 'string', 'max:255'],
            'event_kind' => ['required', Rule::in(['workshop', 'presentation'])],
            'role' => ['required', Rule::in(['enrolled_attendance', 'instructor', 'presented_author'])],
            'is_active' => ['nullable', 'boolean'],
        ]);

        ParticipationType::create($validated + ['is_active' => (bool) ($validated['is_active'] ?? true)]);

        return back()->with('success', 'Tipo de participación creado.');
    }

    public function update(Request $request, ParticipationType $type)
    {
        abort_if(! $request->user()->isAdmin(), 403);

        $validated = $request->validate([
            'key' => ['required', 'string', 'max:100', Rule::unique('participation_types', 'key')->ignore($type->id)],
            'label' => ['required', 'string', 'max:255'],
            'event_kind' => ['required', Rule::in(['workshop', 'presentation'])],
            'role' => ['required', Rule::in(['enrolled_attendance', 'instructor', 'presented_author'])],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $type->update($validated + ['is_active' => (bool) ($validated['is_active'] ?? true)]);

        return back()->with('success', 'Tipo de participación actualizado.');
    }

    public function destroy(Request $request, ParticipationType $type)
    {
        abort_if(! $request->user()->isAdmin(), 403);

        $type->delete();

        return back()->with('success', 'Tipo de participación eliminado.');
    }
}
