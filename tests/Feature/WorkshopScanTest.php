<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopEnrollment;
use App\Support\EventSettings;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkshopScanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(
            Carbon::parse('2026-08-10 12:00:00', EventSettings::timezone()),
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function user(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'Asistente']);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function restrictedWorkshop(User $user, string $day, string $start, string $end): Workshop
    {
        return Workshop::create([
            'name' => 'Taller QR',
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

    private function enroll(User $user, Workshop $workshop): void
    {
        WorkshopEnrollment::create([
            'user_id' => $user->id,
            'workshop_id' => $workshop->id,
            'enrolled_at' => now(),
            'status' => 'enrolled',
        ]);
    }

    public function test_scan_allows_attendance_inside_local_time_window(): void
    {
        Mail::fake();

        $user = $this->user();
        $tz = 'America/Mexico_City';
        $start = now($tz)->subHour();
        $end = now($tz)->addHour();
        $workshop = $this->restrictedWorkshop(
            $user,
            $start->toDateString(),
            $start->format('H:i'),
            $end->format('H:i'),
        );
        $this->enroll($user, $workshop);

        $this->actingAs($user)
            ->get(route('workshops.scan', $workshop))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Workshops/Scan')
                ->where('success', true));

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'workshop_id' => $workshop->id,
        ]);
    }

    public function test_scan_rejects_attendance_outside_local_time_window(): void
    {
        Mail::fake();

        $user = $this->user();
        $tz = 'America/Mexico_City';
        $yesterday = now($tz)->subDay();
        $workshop = $this->restrictedWorkshop($user, $yesterday->toDateString(), '09:00', '10:00');
        $this->enroll($user, $workshop);

        $this->actingAs($user)
            ->get(route('workshops.scan', $workshop))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Workshops/Scan')
                ->where('success', false)
                ->where('message', 'Fuera del horario permitido para este taller.'));

        $this->assertDatabaseMissing('attendances', [
            'user_id' => $user->id,
            'workshop_id' => $workshop->id,
        ]);
    }

    public function test_scan_allows_attendance_up_to_grace_hours_before_start(): void
    {
        Mail::fake();

        $user = $this->user();
        $tz = 'America/Mexico_City';
        $start = now($tz)->addHour();
        $end = now($tz)->addHours(2);
        $workshop = $this->restrictedWorkshop(
            $user,
            $start->toDateString(),
            $start->format('H:i'),
            $end->format('H:i'),
        );
        $this->enroll($user, $workshop);

        $this->actingAs($user)
            ->get(route('workshops.scan', $workshop))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Workshops/Scan')
                ->where('success', true));
    }

    public function test_scan_allows_attendance_up_to_grace_hours_after_end(): void
    {
        Mail::fake();

        $user = $this->user();
        $tz = 'America/Mexico_City';
        $start = now($tz)->subHours(3);
        $end = now($tz)->subHour();
        $workshop = $this->restrictedWorkshop(
            $user,
            $start->toDateString(),
            $start->format('H:i'),
            $end->format('H:i'),
        );
        $this->enroll($user, $workshop);

        $this->actingAs($user)
            ->get(route('workshops.scan', $workshop))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Workshops/Scan')
                ->where('success', true));
    }

    public function test_scan_rejects_attendance_beyond_grace_before_start(): void
    {
        Mail::fake();

        $user = $this->user();
        $tz = 'America/Mexico_City';
        $start = now($tz)->addHours(3);
        $end = now($tz)->addHours(4);
        $workshop = $this->restrictedWorkshop(
            $user,
            $start->toDateString(),
            $start->format('H:i'),
            $end->format('H:i'),
        );
        $this->enroll($user, $workshop);

        $this->actingAs($user)
            ->get(route('workshops.scan', $workshop))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Workshops/Scan')
                ->where('success', false)
                ->where('message', 'Fuera del horario permitido para este taller.'));
    }

    public function test_scan_rejects_attendance_beyond_grace_after_end(): void
    {
        Mail::fake();

        $user = $this->user();
        $tz = 'America/Mexico_City';
        $start = now($tz)->subHours(5);
        $end = now($tz)->subHours(3);
        $workshop = $this->restrictedWorkshop(
            $user,
            $start->toDateString(),
            $start->format('H:i'),
            $end->format('H:i'),
        );
        $this->enroll($user, $workshop);

        $this->actingAs($user)
            ->get(route('workshops.scan', $workshop))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Workshops/Scan')
                ->where('success', false)
                ->where('message', 'Fuera del horario permitido para este taller.'));
    }

    public function test_scan_uses_configured_grace_hours(): void
    {
        Mail::fake();

        Setting::updateOrCreate(['key' => 'evento_checkin_grace_hours'], ['value' => '0']);

        $user = $this->user();
        $tz = 'America/Mexico_City';
        $start = now($tz)->addHour();
        $end = now($tz)->addHours(2);
        $workshop = $this->restrictedWorkshop(
            $user,
            $start->toDateString(),
            $start->format('H:i'),
            $end->format('H:i'),
        );
        $this->enroll($user, $workshop);

        $this->actingAs($user)
            ->get(route('workshops.scan', $workshop))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Workshops/Scan')
                ->where('success', false)
                ->where('message', 'Fuera del horario permitido para este taller.'));
    }
}
