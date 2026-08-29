<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GafeteUsersPrintTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPermissions(array $keys, array $roleNames): User
    {
        $user = User::factory()->create(['is_active' => true]);

        $roleIds = collect($roleNames)->map(function (string $name) use ($keys) {
            $role = Role::firstOrCreate(['name' => $name], ['is_active' => true]);
            $role->permissions()->sync(
                collect($keys)->map(fn (string $key) => Permission::updateOrCreate(
                    ['key' => $key],
                    ['module' => 'Usuarios', 'label' => ucfirst(str_replace('.', ' ', $key))],
                )->id)->all(),
            );

            return $role->id;
        })->all();

        $user->roles()->sync($roleIds);

        return $user;
    }

    public function test_admin_can_print_single_user_badge(): void
    {
        $admin = $this->userWithPermissions(['users.view'], ['Administrator']);
        $target = User::factory()->create(['first_name' => 'Casimiro', 'last_name' => 'Pérez']);

        $this->actingAs($admin)
            ->get('/users/'.$target->id.'/gafete/imprimir')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertSee('Imprimir gafete')
            ->assertSee('Casimiro Pérez');
    }

    public function test_user_without_users_view_cannot_print_badges(): void
    {
        $user = $this->userWithPermissions([], ['Asistente']);

        $this->actingAs($user)
            ->get('/users/'.$user->id.'/gafete/imprimir')
            ->assertForbidden();

        $this->actingAs($user)->get('/users/gafetes/imprimir')->assertForbidden();

        $this->actingAs($user)->get('/users/gafetes/imprimir/pdf')->assertForbidden();
    }

    public function test_bulk_print_html_includes_only_active_users(): void
    {
        $admin = $this->userWithPermissions(['users.view'], ['Administrator']);
        User::factory()->create(['first_name' => 'Ana', 'last_name' => 'Activa']);
        User::factory()->create(['first_name' => 'Beto', 'last_name' => 'Activo']);
        $inactive = User::factory()->create(['first_name' => 'Zena', 'last_name' => 'Inactiva', 'is_active' => false]);

        $response = $this->actingAs($admin)->get('/users/gafetes/imprimir');

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertSee('Imprimir todos los gafetes')
            ->assertSee('Ana Activa')
            ->assertSee('Beto Activo')
            ->assertDontSee('Zena Inactiva');

        $this->assertStringNotContainsString('Zena Inactiva', $response->getContent());
    }

    public function test_bulk_print_pdf_returns_pdf(): void
    {
        $admin = $this->userWithPermissions(['users.view'], ['Administrator']);
        User::factory()->create(['first_name' => 'Ana', 'last_name' => 'Activa']);

        $this->actingAs($admin)
            ->get('/users/gafetes/imprimir/pdf')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename=credenciales_asistentes_'.date('Y-m-d').'.pdf');
    }
}
