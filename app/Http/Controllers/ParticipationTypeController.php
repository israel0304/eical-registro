<?php

namespace App\Http\Controllers;

use App\Models\ParticipationType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ParticipationTypeController extends Controller
{
    public function index(Request $request)
    {
        $participationTypes = ParticipationType::query()
            ->withCount('templates', 'certificates')
            ->orderBy('event_kind')
            ->orderBy('role')
            ->get();

        return Inertia::render('Constancias/Tipos/Index', [
            'participationTypes' => $participationTypes,
            'catalog' => [
                'event_kinds' => config('participation.event_kinds'),
                'roles' => config('participation.roles'),
                'kinds' => config('participation.kinds'),
                'role_rules' => config('participation.role_rules'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        abort_if(! $request->user()->isAdmin(), 403);

        $validated = $this->validateType($request);

        ParticipationType::create($validated + [
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'manual_generable' => (bool) ($validated['manual_generable'] ?? false),
        ]);

        return back()->with('success', 'Tipo de participación creado.');
    }

    public function update(Request $request, ParticipationType $type)
    {
        abort_if(! $request->user()->isAdmin(), 403);

        $validated = $this->validateType($request, $type);

        $type->update($validated + [
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'manual_generable' => (bool) ($validated['manual_generable'] ?? false),
        ]);

        return back()->with('success', 'Tipo de participación actualizado.');
    }

    public function destroy(Request $request, ParticipationType $type)
    {
        abort_if(! $request->user()->isAdmin(), 403);

        $templates = $type->templates()->count();
        $certificates = $type->certificates()->count();

        if ($templates > 0 || $certificates > 0) {
            return back()->with('error', "No se puede eliminar este tipo: tiene {$templates} plantilla(s) y {$certificates} constancia(s) asociadas.");
        }

        $type->delete();

        return back()->with('success', 'Tipo de participación eliminado.');
    }

    private function validateType(Request $request, ?ParticipationType $type = null): array
    {
        $eventKind = $request->input('event_kind');
        $allowedRoles = config('participation.role_rules.'.$eventKind, []);

        $validator = Validator::make($request->all(), [
            'key' => ['required', 'string', 'max:100', Rule::unique('participation_types', 'key')->ignore($type?->id)],
            'label' => ['required', 'string', 'max:255'],
            'event_kind' => ['required', Rule::in(array_keys(config('participation.event_kinds')))],
            'kind' => ['nullable', Rule::in(array_keys(config('participation.kinds.'.$eventKind, [])))],
            'role' => $allowedRoles === []
                ? ['nullable', Rule::in([])]
                : ['required', Rule::in($allowedRoles)],
            'is_active' => ['nullable', 'boolean'],
            'manual_generable' => ['nullable', 'boolean'],
        ]);

        $validator->after(function ($validator) use ($request, $type, $eventKind) {
            $this->validateCombination($validator, $request, $type, $eventKind);
        });

        return $validator->validated();
    }

    private function validateCombination($validator, Request $request, ?ParticipationType $type, ?string $eventKind): void
    {
        if ($eventKind === null) {
            return;
        }

        $kind = $request->input('kind') ?: null;
        $role = $request->input('role') ?: null;

        $exists = ParticipationType::query()
            ->where('event_kind', $eventKind)
            ->where('kind', $kind)
            ->where('role', $role)
            ->when($type, fn ($query) => $query->where('id', '!=', $type->id))
            ->exists();

        if ($exists) {
            $validator->errors()->add(
                'role',
                'Ya existe un tipo con esta combinación de evento, rol y sub-tipo.'
            );
        }
    }
}
