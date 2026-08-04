<?php

namespace App\Services;

use App\Models\Permission;

class PermissionSync
{
    public function sync(): void
    {
        foreach (config('permissions') as $module => $permissions) {
            foreach ($permissions as $key => $label) {
                Permission::updateOrCreate(
                    ['key' => $key],
                    ['module' => $module, 'label' => $label],
                );
            }
        }
    }
}
