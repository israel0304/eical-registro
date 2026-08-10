<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;

class PermissionSync
{
    public function sync(): void
    {
        $validKeys = [];

        foreach (config('permissions') as $module => $permissions) {
            foreach ($permissions as $key => $label) {
                $validKeys[] = $key;

                Permission::updateOrCreate(
                    ['key' => $key],
                    ['module' => $module, 'label' => $label],
                );
            }
        }

        Permission::query()
            ->whereNotIn('key', $validKeys)
            ->get()
            ->each(function (Permission $permission) {
                $permission->roles()->detach();
                $permission->delete();
            });

        $this->attachAllToSuperAdmin();
    }

    /**
     * Mapa rol => permisos. Fuente única de verdad para el seeder
     * y el comando artisan permissions:sync.
     */
    public static function rolePermissionMap(): array
    {
        return [
            2 => ['dashboard.view', 'workshops.view', 'workshops.my', 'presentations.view', 'presentations.my', 'constancias.view', 'constancias.download', 'constancias.invitaciones.download', 'gafete.view'],
            3 => ['dashboard.view', 'workshops.view', 'workshops.my', 'constancias.view', 'constancias.download', 'gafete.view'],
            4 => ['dashboard.view', 'workshops.view', 'workshops.my', 'constancias.view', 'constancias.download', 'gafete.view'],
            5 => ['dashboard.view', 'constancias.view', 'constancias.download', 'gafete.view', 'conferences.view'],
            6 => ['dashboard.view', 'constancias.view', 'constancias.download', 'gafete.view', 'asignaciones.view'],
        ];
    }

    private function attachAllToSuperAdmin(): void
    {
        $adminRole = Role::where('name', config('roles.super_admin'))->first();

        if ($adminRole !== null) {
            $adminRole->permissions()->sync(Permission::pluck('id'));
        }
    }
}
