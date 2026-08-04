<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        abort_if(! $request->user()->isAdmin(), 403);

        $roles = Role::query()
            ->withCount('users')
            ->with('permissions')
            ->orderBy('id')
            ->get();

        $flattened = [];
        foreach (config('permissions') as $module => $permissions) {
            foreach ($permissions as $key => $label) {
                $flattened[] = ['module' => $module, 'key' => $key, 'label' => $label];
            }
        }

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $roles,
            'permissions' => $flattened,
            'modules' => array_keys(config('permissions')),
        ]);
    }

    public function store(Request $request)
    {
        abort_if(! $request->user()->isAdmin(), 403);

        $validated = $this->validateRequest($request);

        $role = Role::create([
            'name' => $validated['name'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $this->syncPermissions($role, $validated['permissions'] ?? []);

        return back()->with('success', 'Rol creado correctamente.');
    }

    public function update(Request $request, Role $role)
    {
        abort_if(! $request->user()->isAdmin(), 403);

        abort_if($role->name === 'Administrator', 403, 'El rol Administrator no se puede modificar.');

        $validated = $this->validateRequest($request, $role);

        $role->update([
            'name' => $validated['name'],
            'is_active' => $validated['is_active'] ?? $role->is_active,
        ]);

        $this->syncPermissions($role, $validated['permissions'] ?? []);

        return back()->with('success', 'Rol actualizado correctamente.');
    }

    public function users(Role $role)
    {
        abort_if(! request()->user()->isAdmin(), 403);

        return response()->json(
            $role->users()->get(['users.id', 'first_name', 'last_name', 'email'])
        );
    }

    public function destroy(Role $role)
    {
        abort_if(! request()->user()->isAdmin(), 403);

        abort_if($role->name === 'Administrator', 403, 'El rol Administrator no se puede eliminar.');

        $role->users()->detach();
        $role->permissions()->detach();
        $role->delete();

        return back()->with('success', 'Rol eliminado correctamente.');
    }

    private function validateRequest(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name,'.$role?->id],
            'is_active' => ['nullable', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,key'],
        ]);
    }

    private function syncPermissions(Role $role, array $keys): void
    {
        $role->permissions()->sync(
            Permission::whereIn('key', $keys)->pluck('id')
        );
    }
}
