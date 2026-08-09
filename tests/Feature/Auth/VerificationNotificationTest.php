<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerificationNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_verification_notification(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertRedirect(route('home'));

        $this->assertDatabaseHas('event_logs', [
            'event_key' => 'user.registered',
            'subject_type' => (new User)->getMorphClass(),
            'subject_id' => $user->id,
            'status' => 'recorded',
        ]);
    }

    public function test_does_not_send_verification_notification_if_email_is_verified(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertDatabaseMissing('event_logs', [
            'event_key' => 'user.registered',
            'subject_id' => $user->id,
        ]);
    }
}
