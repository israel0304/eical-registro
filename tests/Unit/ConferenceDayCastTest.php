<?php

namespace Tests\Unit;

use App\Models\Conference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConferenceDayCastTest extends TestCase
{
    use RefreshDatabase;

    public function test_day_attribute_serializes_to_ymd_without_time(): void
    {
        $user = User::factory()->create();

        $conference = Conference::create([
            'title' => 'Conferencia de prueba',
            'kind' => 'magistral',
            'day' => '2026-10-05',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'created_by' => $user->id,
        ]);

        $day = $conference->day;

        $this->assertSame('2026-10-05', $day->format('Y-m-d'));
        $this->assertSame('2026-10-05', $conference->toArray()['day']);
    }

    public function test_day_serialized_json_is_ymd_not_datetime(): void
    {
        $user = User::factory()->create();

        $conference = Conference::create([
            'title' => 'Conferencia JSON',
            'kind' => 'simposio',
            'day' => '2026-10-06',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'created_by' => $user->id,
        ]);

        $json = json_decode($conference->toJson(), true);

        $this->assertSame('2026-10-06', $json['day']);
        $this->assertStringNotContainsString(' 00:00:00', $json['day']);
    }
}
