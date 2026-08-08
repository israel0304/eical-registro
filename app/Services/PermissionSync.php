<?php

namespace App\Services;

use App\Models\Permission;

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
    }
}
