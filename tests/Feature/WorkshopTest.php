<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkshopTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'Administrator']);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    protected function regularUser(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'Asistente']);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    protected function workshopFor(User $user, array $overrides = []): Workshop
    {
        return Workshop::create(array_merge([
            'name' => 'Taller de prueba',
            'description' => 'Descripción',
            'capacity' => 10,
            'location' => 'Aula 1',
            'day' => '2026-10-05',
            'start_time' => '09:00',
            'end_time' => '13:00',
            'created_by' => $user->id,
        ], $overrides));
    }

    public function test_admin_can_view_workshops(): void
    {
        $user = $this->admin();

        $this->actingAs($user)
            ->get('/workshops')
            ->assertOk();
    }

    public function test_user_without_permission_cannot_view_workshops(): void
    {
        $user = $this->regularUser();

        $this->actingAs($user)
            ->get('/workshops')
            ->assertForbidden();
    }

    public function test_index_shows_active_by_default(): void
    {
        $admin = $this->admin();
        $active = $this->workshopFor($admin);
        $deleted = $this->workshopFor($admin, ['name' => 'Taller eliminado']);
        $deleted->delete();

        $this->actingAs($admin)
            ->get('/workshops')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Workshops/Index')
                ->where('filters.status', 'active')
                ->has('workshops.data', 1)
                ->where('workshops.data.0.id', $active->id)
            );
    }

    public function test_index_filter_deleted_shows_trashed(): void
    {
        $admin = $this->admin();
        $active = $this->workshopFor($admin);
        $deleted = $this->workshopFor($admin, ['name' => 'Taller eliminado']);
        $deleted->delete();

        $this->actingAs($admin)
            ->get('/workshops?status=deleted')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Workshops/Index')
                ->where('filters.status', 'deleted')
                ->has('workshops.data', 1)
                ->where('workshops.data.0.id', $deleted->id)
            );
    }

    public function test_index_filter_all_shows_both(): void
    {
        $admin = $this->admin();
        $active = $this->workshopFor($admin);
        $deleted = $this->workshopFor($admin, ['name' => 'Taller eliminado']);
        $deleted->delete();

        $this->actingAs($admin)
            ->get('/workshops?status=all')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Workshops/Index')
                ->where('filters.status', 'all')
                ->has('workshops.data', 2)
            );
    }

    public function test_soft_delete_sets_deleted_at(): void
    {
        $admin = $this->admin();
        $workshop = $this->workshopFor($admin);

        $this->actingAs($admin)
            ->delete("/workshops/{$workshop->id}")
            ->assertRedirect();

        $this->assertSoftDeleted('workshops', ['id' => $workshop->id]);
    }

    public function test_force_delete_blocked_when_enrollments_exist(): void
    {
        $admin = $this->admin();
        $workshop = $this->workshopFor($admin);
        $user = $this->regularUser();

        WorkshopEnrollment::create([
            'user_id' => $user->id,
            'workshop_id' => $workshop->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);

        $workshop->delete();

        $this->actingAs($admin)
            ->post("/workshops/{$workshop->id}/force-delete")
            ->assertRedirect()
            ->assertSessionHasErrors('forceDelete');

        $this->assertSoftDeleted('workshops', ['id' => $workshop->id]);
        $this->assertDatabaseHas('workshop_enrollments', [
            'workshop_id' => $workshop->id,
            'status' => 'enrolled',
        ]);
    }

    public function test_force_delete_succeeds_when_no_enrollments(): void
    {
        $admin = $this->admin();
        $workshop = $this->workshopFor($admin);
        $workshop->delete();

        $this->actingAs($admin)
            ->post("/workshops/{$workshop->id}/force-delete")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('workshops', ['id' => $workshop->id]);
        $this->assertDatabaseMissing('workshop_instructor_user', ['workshop_id' => $workshop->id]);
        $this->assertDatabaseMissing('workshop_moderator_user', ['workshop_id' => $workshop->id]);
    }

    public function test_force_delete_succeeds_when_only_cancelled_enrollments(): void
    {
        $admin = $this->admin();
        $workshop = $this->workshopFor($admin);
        $user = $this->regularUser();

        WorkshopEnrollment::create([
            'user_id' => $user->id,
            'workshop_id' => $workshop->id,
            'status' => 'cancelled',
            'enrolled_at' => now(),
        ]);

        $workshop->delete();

        $this->actingAs($admin)
            ->post("/workshops/{$workshop->id}/force-delete")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('workshops', ['id' => $workshop->id]);
    }

    public function test_restore_clears_deleted_at(): void
    {
        $admin = $this->admin();
        $workshop = $this->workshopFor($admin);
        $workshop->delete();

        $this->actingAs($admin)
            ->post("/workshops/{$workshop->id}/restore")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('workshops', [
            'id' => $workshop->id,
            'deleted_at' => null,
        ]);
    }

    public function test_admin_can_create_workshop(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/workshops', [
                'name' => 'Nuevo Taller',
                'description' => 'Desc',
                'capacity' => 20,
                'location' => 'Aula 2',
                'day' => '2026-10-06',
                'start_time' => '10:00',
                'end_time' => '14:00',
                'qr_time_restricted' => true,
                'instructors' => [[
                    'first_name' => 'Juan',
                    'last_name' => 'Pérez',
                    'affiliation' => 'Univ',
                    'email' => 'juan@example.com',
                ]],
                'moderator_ids' => [],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('workshops', ['name' => 'Nuevo Taller']);
    }

    public function test_admin_can_update_workshop(): void
    {
        $admin = $this->admin();
        $workshop = $this->workshopFor($admin);

        $this->actingAs($admin)
            ->put("/workshops/{$workshop->id}", [
                'name' => 'Taller Actualizado',
                'description' => 'Desc',
                'capacity' => 20,
                'location' => 'Aula 2',
                'day' => '2026-10-06',
                'start_time' => '10:00',
                'end_time' => '14:00',
                'qr_time_restricted' => true,
                'instructors' => [[
                    'first_name' => 'Juan',
                    'last_name' => 'Pérez',
                    'affiliation' => 'Univ',
                    'email' => 'juan@example.com',
                ]],
                'moderator_ids' => [],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('workshops', ['id' => $workshop->id, 'name' => 'Taller Actualizado']);
    }
}
