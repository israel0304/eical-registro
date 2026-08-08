<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ROLE_NAME = 'Moderator';

    private const MODULE_VIEWS = [
        'workshops.view',
        'presentations.view',
        'conferences.view',
    ];

    public function up(): void
    {
        $role = DB::table('roles')->where('name', self::ROLE_NAME)->first();

        if ($role === null) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('key', self::MODULE_VIEWS)
            ->pluck('id');

        DB::table('role_permission')
            ->where('role_id', $role->id)
            ->whereIn('permission_id', $permissionIds)
            ->delete();
    }

    public function down(): void
    {
        $role = DB::table('roles')->where('name', self::ROLE_NAME)->first();

        if ($role === null) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('key', self::MODULE_VIEWS)
            ->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('role_permission')->updateOrInsert([
                'role_id' => $role->id,
                'permission_id' => $permissionId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
