<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_ponente_role_map_includes_presentations_my_without_view(): void
    {
        $keys = PermissionSync::rolePermissionMap()[2];

        $this->assertContains('presentations.my', $keys);
        $this->assertNotContains('presentations.view', $keys);
    }

    public function test_speaker_role_map_includes_conferences_view_without_edit_or_delete(): void
    {
        $keys = PermissionSync::rolePermissionMap()[5];

        $this->assertContains('conferences.view', $keys);
        $this->assertNotContains('conferences.edit', $keys);
        $this->assertNotContains('conferences.delete', $keys);
    }

    public function test_admin_permission_keys_return_all_configured_permissions(): void
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => config('roles.super_admin')]);
        $user->roles()->sync([$role->id]);

        $expected = collect(config('permissions'))
            ->flatMap(fn (array $permissions) => array_keys($permissions))
            ->values()
            ->all();

        $actual = $user->permissionKeys();

        sort($expected);
        sort($actual);

        $this->assertSame($expected, $actual);
    }

    public function test_permissions_sync_command_applies_role_map(): void
    {
        Role::query()->delete();

        foreach ([
            1 => 'Administrator',
            2 => 'Ponente',
            3 => 'Asistente',
            4 => 'Instructor',
            5 => 'Speaker',
            6 => 'Moderator',
        ] as $id => $name) {
            Role::query()->forceCreate([
                'id' => $id,
                'name' => $name,
                'is_active' => true,
            ]);
        }

        $this->artisan('permissions:sync')->assertSuccessful();

        $ponenteKeys = Role::find(2)->permissions()->pluck('key')->all();
        $this->assertContains('presentations.my', $ponenteKeys);
        $this->assertNotContains('presentations.view', $ponenteKeys);

        $speakerKeys = Role::find(5)->permissions()->pluck('key')->all();
        $this->assertContains('conferences.view', $speakerKeys);
        $this->assertNotContains('conferences.edit', $speakerKeys);

        $adminCount = Role::find(1)->permissions()->count();
        $this->assertSame(count(Permission::all()), $adminCount);
    }
}
