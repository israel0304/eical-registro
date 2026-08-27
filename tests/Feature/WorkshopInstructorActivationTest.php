<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkshopInstructorActivationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'Administrator']);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function userWithActivatePermission(): User
    {
        $role = Role::firstOrCreate(['name' => 'Coordinador']);
        $role->permissions()->sync([
            Permission::firstOrCreate(['key' => 'workshops.activate'], [
                'module' => 'Talleres',
                'label' => 'Activar constancias de instructores',
            ])->id,
        ]);

        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function workshop(User $creator): Workshop
    {
        return Workshop::create([
            'name' => 'Taller de prueba',
            'description' => 'test',
            'capacity' => 10,
            'location' => 'Aula 1',
            'day' => '2026-10-05',
            'start_time' => '09:00',
            'end_time' => '13:00',
            'created_by' => $creator->id,
        ]);
    }

    public function test_toggle_activates_instructor_constancia(): void
    {
        $user = $this->userWithActivatePermission();
        $instructor = User::factory()->create();
        $workshop = $this->workshop($this->admin());
        $workshop->instructors()->attach($instructor->id);

        $this->actingAs($user)
            ->post("/workshops/{$workshop->id}/instructors/{$instructor->id}/activation")
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('workshop_instructor_user', [
            'workshop_id' => $workshop->id,
            'user_id' => $instructor->id,
            'activated' => true,
        ]);
    }

    public function test_toggle_deactivates_instructor_constancia(): void
    {
        $user = $this->userWithActivatePermission();
        $instructor = User::factory()->create();
        $workshop = $this->workshop($this->admin());
        $workshop->instructors()->attach($instructor->id, [
            'activated' => true,
            'activated_at' => now(),
        ]);

        $this->actingAs($user)
            ->post("/workshops/{$workshop->id}/instructors/{$instructor->id}/activation")
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('workshop_instructor_user', [
            'workshop_id' => $workshop->id,
            'user_id' => $instructor->id,
            'activated' => false,
        ]);
    }

    public function test_non_instructor_cannot_be_activated(): void
    {
        $user = $this->userWithActivatePermission();
        $outsider = User::factory()->create();
        $workshop = $this->workshop($this->admin());

        $this->actingAs($user)
            ->post("/workshops/{$workshop->id}/instructors/{$outsider->id}/activation")
            ->assertRedirect()
            ->assertSessionHasErrors('error');
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $role = Role::firstOrCreate(['name' => 'Asistente']);
        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);

        $instructor = User::factory()->create();
        $workshop = $this->workshop($this->admin());
        $workshop->instructors()->attach($instructor->id);

        $this->actingAs($user)
            ->post("/workshops/{$workshop->id}/instructors/{$instructor->id}/activation")
            ->assertForbidden();
    }

    public function test_new_instructors_are_created_deactivated(): void
    {
        $instructor = User::factory()->create();
        $workshop = $this->workshop($this->admin());
        $workshop->instructors()->attach($instructor->id);

        $this->assertDatabaseHas('workshop_instructor_user', [
            'workshop_id' => $workshop->id,
            'user_id' => $instructor->id,
            'activated' => false,
        ]);
    }
}
