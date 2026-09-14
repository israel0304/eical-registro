<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkshopConflictTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'Asistente']);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'Administrator']);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function workshop(User $user, string $name, string $start, string $end, string $day = '2026-10-05'): Workshop
    {
        return Workshop::create([
            'name' => $name,
            'description' => 'test',
            'capacity' => 10,
            'location' => 'Aula 1',
            'day' => $day,
            'start_time' => $start,
            'end_time' => $end,
            'qr_time_restricted' => true,
            'created_by' => $user->id,
        ]);
    }

    private function enroll(User $user, Workshop $workshop, string $status = 'enrolled'): void
    {
        WorkshopEnrollment::create([
            'user_id' => $user->id,
            'workshop_id' => $workshop->id,
            'enrolled_at' => now(),
            'status' => $status,
        ]);
    }

    public function test_enroll_blocked_by_overlapping_workshop(): void
    {
        $user = $this->user();
        $first = $this->workshop($user, 'Taller A', '09:00', '11:00');
        $this->enroll($user, $first);
        $second = $this->workshop($user, 'Taller B', '10:00', '12:00');

        $response = $this->actingAs($user)
            ->post(route('workshops.enroll', $second));

        $response->assertSessionHasErrors('error');
        $this->assertStringContainsString('Taller A', session('errors')->get('error')[0]);
        $this->assertDatabaseMissing('workshop_enrollments', [
            'user_id' => $user->id,
            'workshop_id' => $second->id,
        ]);
    }

    public function test_enroll_allowed_when_times_touch(): void
    {
        $user = $this->user();
        $first = $this->workshop($user, 'Taller A', '09:00', '11:00');
        $this->enroll($user, $first);
        $second = $this->workshop($user, 'Taller B', '11:00', '13:00');

        $this->actingAs($user)
            ->post(route('workshops.enroll', $second))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('workshop_enrollments', [
            'user_id' => $user->id,
            'workshop_id' => $second->id,
            'status' => 'enrolled',
        ]);
    }

    public function test_enroll_allowed_on_different_day(): void
    {
        $user = $this->user();
        $first = $this->workshop($user, 'Taller A', '09:00', '11:00');
        $this->enroll($user, $first);
        $second = $this->workshop($user, 'Taller B', '09:00', '11:00', '2026-10-06');

        $this->actingAs($user)
            ->post(route('workshops.enroll', $second))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('workshop_enrollments', [
            'user_id' => $user->id,
            'workshop_id' => $second->id,
            'status' => 'enrolled',
        ]);
    }

    public function test_reactivation_blocked_by_conflict(): void
    {
        $user = $this->user();
        $cancelled = $this->workshop($user, 'Taller A', '09:00', '11:00');
        $this->enroll($user, $cancelled, 'cancelled');
        $active = $this->workshop($user, 'Taller B', '10:00', '12:00');
        $this->enroll($user, $active);

        $response = $this->actingAs($user)
            ->post(route('workshops.enroll', $cancelled));

        $response->assertSessionHasErrors('error');
        $this->assertStringContainsString('Taller B', session('errors')->get('error')[0]);
        $this->assertDatabaseHas('workshop_enrollments', [
            'user_id' => $user->id,
            'workshop_id' => $cancelled->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_show_exposes_my_enrolled_prop(): void
    {
        $admin = $this->admin();
        $other = $this->workshop($admin, 'Taller A', '09:00', '11:00');
        $this->enroll($admin, $other);
        $current = $this->workshop($admin, 'Taller Actual', '14:00', '16:00');

        $this->actingAs($admin)
            ->get('/workshops/'.$current->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Workshops/Show')
                ->has('myEnrolled', 1)
                ->where('myEnrolled.0.id', $other->id)
                ->where('myEnrolled.0.name', 'Taller A')
                ->where('myEnrolled.0.day', '2026-10-05'));
    }
}
