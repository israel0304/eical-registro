<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkshopManualEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private const PERMISSION = 'workshops.enrollments';

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'Administrator']);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function userWithPermission(string $key): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'Role-'.$key]);
        $role->permissions()->sync(
            Permission::firstOrCreate(['key' => $key], [
                'module' => 'Workshops',
                'label' => $key,
            ])
        );
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function plainUser(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $role = Role::firstOrCreate(['name' => 'Asistente']);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function workshop(User $creator, array $overrides = []): Workshop
    {
        return Workshop::create(array_merge([
            'name' => 'Taller de prueba',
            'capacity' => 2,
            'location' => 'Aula 1',
            'day' => '2026-10-05',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'created_by' => $creator->id,
        ], $overrides));
    }

    private function enroll(User $user, Workshop $workshop, string $status = 'enrolled'): WorkshopEnrollment
    {
        return WorkshopEnrollment::create([
            'user_id' => $user->id,
            'workshop_id' => $workshop->id,
            'enrolled_at' => now(),
            'status' => $status,
        ]);
    }

    public function test_admin_can_register_user_manually(): void
    {
        $admin = $this->admin();
        $workshop = $this->workshop($admin);
        $target = $this->plainUser();

        $this->actingAs($admin)
            ->post(route('workshops.enrollments.admin-store', $workshop), [
                'user_id' => $target->id,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('workshop_enrollments', [
            'workshop_id' => $workshop->id,
            'user_id' => $target->id,
            'status' => 'enrolled',
        ]);
    }

    public function test_user_with_permission_can_register_manually(): void
    {
        $admin = $this->admin();
        $workshop = $this->workshop($admin);
        $manager = $this->userWithPermission(self::PERMISSION);
        $target = $this->plainUser();

        $this->actingAs($manager)
            ->post(route('workshops.enrollments.admin-store', $workshop), [
                'user_id' => $target->id,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('workshop_enrollments', [
            'workshop_id' => $workshop->id,
            'user_id' => $target->id,
            'status' => 'enrolled',
        ]);
    }

    public function test_assigned_instructor_can_register_user_manually(): void
    {
        $admin = $this->admin();
        $workshop = $this->workshop($admin);
        $instructor = $this->plainUser();
        $workshop->instructors()->attach($instructor->id);
        $target = $this->plainUser();

        $this->actingAs($instructor)
            ->post(route('workshops.enrollments.admin-store', $workshop), [
                'user_id' => $target->id,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('workshop_enrollments', [
            'workshop_id' => $workshop->id,
            'user_id' => $target->id,
            'status' => 'enrolled',
        ]);
    }

    public function test_assigned_moderator_can_register_user_manually(): void
    {
        $admin = $this->admin();
        $workshop = $this->workshop($admin);
        $moderator = $this->plainUser();
        $workshop->moderators()->attach($moderator->id);
        $target = $this->plainUser();

        $this->actingAs($moderator)
            ->post(route('workshops.enrollments.admin-store', $workshop), [
                'user_id' => $target->id,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('workshop_enrollments', [
            'workshop_id' => $workshop->id,
            'user_id' => $target->id,
            'status' => 'enrolled',
        ]);
    }

    public function test_unassigned_user_cannot_register_manually(): void
    {
        $admin = $this->admin();
        $workshop = $this->workshop($admin);
        $outsider = $this->plainUser();
        $target = $this->plainUser();

        $this->actingAs($outsider)
            ->post(route('workshops.enrollments.admin-store', $workshop), [
                'user_id' => $target->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('workshop_enrollments', [
            'workshop_id' => $workshop->id,
            'user_id' => $target->id,
        ]);
    }

    public function test_duplicate_enrollment_is_rejected(): void
    {
        $admin = $this->admin();
        $workshop = $this->workshop($admin);
        $target = $this->plainUser();
        $this->enroll($target, $workshop);

        $this->actingAs($admin)
            ->post(route('workshops.enrollments.admin-store', $workshop), [
                'user_id' => $target->id,
            ])
            ->assertSessionHasErrors('error');

        $this->assertDatabaseCount('workshop_enrollments', 1);
    }

    public function test_cancelled_enrollment_is_reactivated(): void
    {
        $admin = $this->admin();
        $workshop = $this->workshop($admin);
        $target = $this->plainUser();
        $this->enroll($target, $workshop, 'cancelled');

        $this->actingAs($admin)
            ->post(route('workshops.enrollments.admin-store', $workshop), [
                'user_id' => $target->id,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('workshop_enrollments', [
            'workshop_id' => $workshop->id,
            'user_id' => $target->id,
            'status' => 'enrolled',
        ]);
    }

    public function test_manual_registration_blocked_by_time_conflict(): void
    {
        $admin = $this->admin();
        $conflicting = $this->workshop($admin, [
            'name' => 'Taller Conflictivo',
            'start_time' => '09:00',
            'end_time' => '11:00',
        ]);
        $target = $this->plainUser();
        $this->enroll($target, $conflicting);
        $workshop = $this->workshop($admin, ['name' => 'Taller Destino']);

        $response = $this->actingAs($admin)
            ->post(route('workshops.enrollments.admin-store', $workshop), [
                'user_id' => $target->id,
            ]);

        $response->assertSessionHasErrors(['error', 'conflict']);
        $this->assertDatabaseMissing('workshop_enrollments', [
            'workshop_id' => $workshop->id,
            'user_id' => $target->id,
        ]);
    }

    public function test_manual_registration_allowed_when_workshop_is_full(): void
    {
        $admin = $this->admin();
        $workshop = $this->workshop($admin);
        $this->enroll($this->plainUser(), $workshop);
        $this->enroll($this->plainUser(), $workshop);
        $target = $this->plainUser();

        $response = $this->actingAs($admin)
            ->post(route('workshops.enrollments.admin-store', $workshop), [
                'user_id' => $target->id,
            ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('workshop_enrollments', [
            'workshop_id' => $workshop->id,
            'user_id' => $target->id,
            'status' => 'enrolled',
        ]);
    }

    public function test_assigned_instructor_can_register_manually_when_workshop_is_full(): void
    {
        $admin = $this->admin();
        $workshop = $this->workshop($admin);
        $this->enroll($this->plainUser(), $workshop);
        $this->enroll($this->plainUser(), $workshop);
        $instructor = $this->plainUser();
        $workshop->instructors()->attach($instructor->id);
        $target = $this->plainUser();

        $this->actingAs($instructor)
            ->post(route('workshops.enrollments.admin-store', $workshop), [
                'user_id' => $target->id,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('workshop_enrollments', [
            'workshop_id' => $workshop->id,
            'user_id' => $target->id,
            'status' => 'enrolled',
        ]);
    }

    public function test_assigned_moderator_can_register_manually_when_workshop_is_full(): void
    {
        $admin = $this->admin();
        $workshop = $this->workshop($admin);
        $this->enroll($this->plainUser(), $workshop);
        $this->enroll($this->plainUser(), $workshop);
        $moderator = $this->plainUser();
        $workshop->moderators()->attach($moderator->id);
        $target = $this->plainUser();

        $this->actingAs($moderator)
            ->post(route('workshops.enrollments.admin-store', $workshop), [
                'user_id' => $target->id,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('workshop_enrollments', [
            'workshop_id' => $workshop->id,
            'user_id' => $target->id,
            'status' => 'enrolled',
        ]);
    }

    public function test_self_enrollment_blocked_when_workshop_is_full(): void
    {
        $admin = $this->admin();
        $workshop = $this->workshop($admin);
        $this->enroll($this->plainUser(), $workshop);
        $this->enroll($this->plainUser(), $workshop);
        $user = $this->plainUser();

        $this->actingAs($user)
            ->post(route('workshops.enroll', $workshop), [])
            ->assertSessionHasErrors('error');

        $this->assertDatabaseMissing('workshop_enrollments', [
            'workshop_id' => $workshop->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_user_search_is_scoped(): void
    {
        $admin = $this->admin();
        $workshop = $this->workshop($admin);
        $target = $this->plainUser([
            'first_name' => 'Buscame',
            'last_name' => 'Prueba',
        ]);

        $this->actingAs($admin)
            ->getJson('/api/workshops/'.$workshop->id.'/users?search=Buscame')
            ->assertOk()
            ->assertJsonFragment(['id' => $target->id]);

        $outsider = $this->plainUser();

        $this->actingAs($outsider)
            ->get('/api/workshops/'.$workshop->id.'/users?search=Buscame')
            ->assertForbidden();
    }

    public function test_assigned_instructor_can_cancel_enrollment(): void
    {
        $admin = $this->admin();
        $workshop = $this->workshop($admin);
        $instructor = $this->plainUser();
        $workshop->instructors()->attach($instructor->id);
        $target = $this->plainUser();
        $enrollment = $this->enroll($target, $workshop);

        $this->actingAs($instructor)
            ->delete(route('workshops.enrollments.admin-destroy', [$workshop, $enrollment]))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('workshop_enrollments', [
            'id' => $enrollment->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_unassigned_user_cannot_cancel_enrollment(): void
    {
        $admin = $this->admin();
        $workshop = $this->workshop($admin);
        $outsider = $this->plainUser();
        $target = $this->plainUser();
        $enrollment = $this->enroll($target, $workshop);

        $this->actingAs($outsider)
            ->delete(route('workshops.enrollments.admin-destroy', [$workshop, $enrollment]))
            ->assertForbidden();

        $this->assertDatabaseHas('workshop_enrollments', [
            'id' => $enrollment->id,
            'status' => 'enrolled',
        ]);
    }
}
