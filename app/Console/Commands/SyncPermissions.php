<?php

namespace App\Console\Commands;

use App\Models\Permission;
use App\Models\Role;
use App\Services\PermissionSync;
use Illuminate\Console\Command;

class SyncPermissions extends Command
{
    protected $signature = 'permissions:sync';

    protected $description = 'Sincroniza los permisos definidos en config/permissions.php y los vincula a los roles';

    public function handle(): int
    {
        app(PermissionSync::class)->sync();

        foreach (PermissionSync::rolePermissionMap() as $roleId => $keys) {
            $role = Role::find($roleId);

            if ($role !== null) {
                $role->permissions()->sync(
                    Permission::whereIn('key', $keys)->pluck('id')
                );
            }
        }

        $adminRole = Role::where('name', config('roles.super_admin'))->first();

        if ($adminRole !== null) {
            $adminRole->permissions()->sync(Permission::pluck('id'));
        }

        $this->info('Permisos sincronizados correctamente.');

        return self::SUCCESS;
    }
}
