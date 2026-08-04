<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $role = Role::create(['name' => 'Asistente']);
        $role->permissions()->attach(
            Permission::create([
                'key' => 'dashboard.view',
                'module' => 'Dashboard',
                'label' => 'Ver dashboard',
            ])
        );

        $user = User::factory()->create();
        $user->roles()->attach($role);
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }
}
