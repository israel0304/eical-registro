<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopEnrollment;
use App\Support\WorkshopGroups;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkshopGroupsTest extends TestCase
{
    use RefreshDatabase;

    private function workshop(array $overrides = []): Workshop
    {
        return Workshop::create(array_merge([
            'name' => 'Taller único',
            'description' => null,
            'capacity' => 20,
            'location' => 'Aula 1',
            'day' => '2026-09-23',
            'start_time' => '12:00',
            'end_time' => '14:00',
            'created_by' => User::factory()->create()->id,
        ], $overrides));
    }

    public function test_returns_null_for_single_workshop(): void
    {
        $workshop = $this->workshop();

        $this->assertNull(WorkshopGroups::for($workshop));
    }

    public function test_returns_same_group_from_parent_or_child(): void
    {
        $parent = $this->workshop(['name' => 'Taller de prueba', 'day' => '2026-09-24']);
        $child = $this->workshop(['name' => 'Sesión 1: Taller de prueba', 'day' => '2026-09-23', 'parent_workshop_id' => $parent->id]);

        $fromChild = WorkshopGroups::for($child);
        $fromParent = WorkshopGroups::for($parent);

        $this->assertNotNull($fromChild);
        $this->assertSame($parent->id, $fromChild?->groupId());
        $this->assertSame($parent->id, $fromParent?->groupId());
        $this->assertTrue($fromParent?->isDivided());
    }

    public function test_sessions_are_ordered_by_day_then_time(): void
    {
        $parent = $this->workshop(['name' => 'Taller X', 'day' => '2026-09-24', 'start_time' => '11:00', 'end_time' => '13:00']);
        $this->workshop(['name' => 'Sesión 2: Taller X', 'day' => '2026-09-24', 'start_time' => '15:00', 'end_time' => '17:00', 'parent_workshop_id' => $parent->id]);
        $this->workshop(['name' => 'Sesión 1: Taller X', 'day' => '2026-09-23', 'start_time' => '12:00', 'end_time' => '14:00', 'parent_workshop_id' => $parent->id]);

        $sessions = WorkshopGroups::for($parent)?->sessions();

        $this->assertSame(
            ['Sesión 1: Taller X', 'Taller X', 'Sesión 2: Taller X'],
            $sessions?->pluck('name')->all(),
        );
    }

    public function test_base_title_strips_session_prefix(): void
    {
        $parent = $this->workshop(['name' => 'Sesión 1: Desarrollo de Software']);
        $this->workshop(['name' => 'Sesion 2: Desarrollo de Software', 'parent_workshop_id' => $parent->id]);

        $this->assertSame('Desarrollo de Software', WorkshopGroups::for($parent)?->baseTitle());
    }

    public function test_base_title_keeps_original_name_without_prefix(): void
    {
        $parent = $this->workshop(['name' => 'Taller de prueba']);
        $this->workshop(['name' => 'Sesión 2: Taller de prueba', 'parent_workshop_id' => $parent->id]);

        $this->assertSame('Taller de prueba', WorkshopGroups::for($parent)?->baseTitle());
    }

    public function test_total_hours_sums_all_sessions(): void
    {
        $parent = $this->workshop(['name' => 'Taller X', 'start_time' => '12:00', 'end_time' => '14:00']);
        $this->workshop(['name' => 'Sesión 2: Taller X', 'start_time' => '11:00', 'end_time' => '13:30', 'parent_workshop_id' => $parent->id]);

        $this->assertSame('4,5', WorkshopGroups::for($parent)?->totalHours());
    }

    public function test_date_range_same_month_joins_days(): void
    {
        $parent = $this->workshop(['name' => 'Taller X', 'day' => '2026-09-24']);
        $this->workshop(['name' => 'Sesión 1: Taller X', 'day' => '2026-09-23', 'parent_workshop_id' => $parent->id]);

        $this->assertSame('23 y 24 de septiembre de 2026', WorkshopGroups::for($parent)?->dateRange());
    }

    public function test_qualified_requires_attendance_in_every_session(): void
    {
        $user = User::factory()->create();
        $parent = $this->workshop(['name' => 'Taller X']);
        $child = $this->workshop(['name' => 'Sesión 1: Taller X', 'day' => '2026-09-22', 'parent_workshop_id' => $parent->id]);

        WorkshopEnrollment::create(['workshop_id' => $parent->id, 'user_id' => $user->id, 'enrolled_at' => now(), 'status' => 'enrolled']);
        WorkshopEnrollment::create(['workshop_id' => $child->id, 'user_id' => $user->id, 'enrolled_at' => now(), 'status' => 'enrolled']);
        Attendance::create(['workshop_id' => $parent->id, 'user_id' => $user->id, 'event_day' => '2026-09-23', 'registered_by' => $user->id]);

        $group = WorkshopGroups::for($parent);

        $this->assertFalse($group?->qualifiedFor($user));

        Attendance::create(['workshop_id' => $child->id, 'user_id' => $user->id, 'event_day' => '2026-09-22', 'registered_by' => $user->id]);

        $this->assertTrue($group?->qualifiedFor($user));
    }

    public function test_format_hours(): void
    {
        $this->assertSame('4', WorkshopGroups::formatHours(4.0));
        $this->assertSame('3,5', WorkshopGroups::formatHours(3.5));
    }
}
