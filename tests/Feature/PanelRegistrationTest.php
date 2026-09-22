<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PanelRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([
            Role::firstOrCreate(['name' => config('roles.super_admin')])->id,
        ]);

        return $user;
    }

    private function userWithPermission(string $key): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'Role-'.$key]);
        $role->permissions()->sync(
            Permission::firstOrCreate(['key' => $key], [
                'module' => 'Usuarios',
                'label' => $key,
            ])
        );
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function plainUser(): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([
            Role::firstOrCreate(['name' => config('roles.default')])->id,
        ]);

        return $user;
    }

    private function closeRegistrations(): void
    {
        Setting::create(['key' => 'evento_registro_abierto', 'value' => '0']);
    }

    private function registrationPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'affiliation' => 'Test University',
            'country' => 'Mexico',
            'state' => 'CDMX',
        ], $overrides);
    }

    public function test_guests_cannot_access_panel_registration(): void
    {
        $this->get(route('users.registro'))->assertRedirect(route('login'));
    }

    public function test_user_without_create_permission_is_forbidden(): void
    {
        $this->actingAs($this->plainUser())
            ->get(route('users.registro'))
            ->assertForbidden();
    }

    public function test_user_with_create_permission_can_view_panel_registration(): void
    {
        $this->actingAs($this->userWithPermission('users.register'))
            ->get(route('users.registro'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Users/Registro')
                ->where('closed', false));
    }

    public function test_panel_registration_shows_closed_when_registration_is_closed(): void
    {
        $this->closeRegistrations();

        $this->actingAs($this->userWithPermission('users.register'))
            ->get(route('users.registro'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Users/Registro')
                ->where('closed', true));
    }

    public function test_guest_cannot_submit_panel_registration(): void
    {
        $this->post(route('users.registro.store'))->assertRedirect(route('login'));
    }

    public function test_user_without_create_permission_cannot_submit(): void
    {
        $this->actingAs($this->plainUser())
            ->post(route('users.registro.store'), $this->registrationPayload())
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    public function test_user_with_create_permission_can_register_user(): void
    {
        $operator = $this->userWithPermission('users.register');

        $response = $this->actingAs($operator)
            ->post(route('users.registro.store'), $this->registrationPayload());

        $response->assertSessionHas('success');
        $response->assertSessionHasNoErrors();

        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->password_set_at);
        $this->assertTrue($user->roles->contains('name', config('roles.default')));

        $this->assertDatabaseHas('event_logs', [
            'event_key' => 'user.registered',
            'subject_type' => (new User)->getMorphClass(),
            'subject_id' => $user->id,
        ]);
    }

    public function test_panel_registration_does_not_log_in_as_new_user(): void
    {
        $operator = $this->userWithPermission('users.register');

        $this->actingAs($operator)
            ->post(route('users.registro.store'), $this->registrationPayload())
            ->assertSessionHas('success');

        $this->assertEquals($operator->id, auth()->id());
    }

    public function test_panel_registration_blocked_when_registration_is_closed(): void
    {
        $this->closeRegistrations();

        $this->actingAs($this->userWithPermission('users.register'))
            ->post(route('users.registro.store'), $this->registrationPayload())
            ->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    public function test_panel_registration_validates_duplicate_email(): void
    {
        User::factory()->create(['email' => 'test@example.com']);

        $this->actingAs($this->userWithPermission('users.register'))
            ->post(route('users.registro.store'), $this->registrationPayload())
            ->assertSessionHasErrors('email');
    }
}
