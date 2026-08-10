<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopEnrollment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WorkshopUnenrollTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'Asistente']);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function workshop(User $user, CarbonImmutable $start): Workshop
    {
        return Workshop::create([
            'name' => 'Taller cancelación',
            'description' => 'test',
            'capacity' => 10,
            'location' => 'Aula 1',
            'day' => $start->toDateString(),
            'start_time' => $start->format('H:i'),
            'end_time' => $start->addHours(2)->format('H:i'),
            'qr_time_restricted' => true,
            'created_by' => $user->id,
        ]);
    }

    private function enroll(User $user, Workshop $workshop): void
    {
        WorkshopEnrollment::create([
            'user_id' => $user->id,
            'workshop_id' => $workshop->id,
            'enrolled_at' => now(),
            'status' => 'enrolled',
        ]);
    }

    public function test_cannot_cancel_inside_grace_window(): void
    {
        $user = $this->user();
        $start = now('America/Mexico_City')->addHour();
        $workshop = $this->workshop($user, $start);
        $this->enroll($user, $workshop);

        $this->actingAs($user)
            ->delete(route('workshops.unenroll', $workshop))
            ->assertSessionHasErrors('error');

        $this->assertDatabaseHas('workshop_enrollments', [
            'user_id' => $user->id,
            'workshop_id' => $workshop->id,
            'status' => 'enrolled',
        ]);
    }

    public function test_cannot_cancel_after_start(): void
    {
        $user = $this->user();
        $start = now('America/Mexico_City')->subHour();
        $workshop = $this->workshop($user, $start);
        $this->enroll($user, $workshop);

        $this->actingAs($user)
            ->delete(route('workshops.unenroll', $workshop))
            ->assertSessionHasErrors('error');
    }

    public function test_can_cancel_outside_grace_window(): void
    {
        Mail::fake();

        $user = $this->user();
        $start = now('America/Mexico_City')->addHours(3);
        $workshop = $this->workshop($user, $start);
        $this->enroll($user, $workshop);

        $this->actingAs($user)
            ->delete(route('workshops.unenroll', $workshop))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('workshop_enrollments', [
            'user_id' => $user->id,
            'workshop_id' => $workshop->id,
            'status' => 'cancelled',
        ]);
    }
}
