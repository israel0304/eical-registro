<?php

namespace Tests\Feature\Auth;

use App\Models\EventLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered()
    {
        $response = $this->get(route('password.request'));

        $response->assertOk();
    }

    public function test_reset_password_link_can_be_requested()
    {
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        $this->assertDatabaseHas('event_logs', [
            'event_key' => 'user.password_reset',
            'subject_type' => (new User)->getMorphClass(),
            'subject_id' => $user->id,
            'status' => 'recorded',
        ]);
    }

    public function test_reset_password_screen_can_be_rendered()
    {
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        $response = $this->get(route('password.reset', $this->resetToken($user)));

        $response->assertOk();
    }

    public function test_password_can_be_reset_with_valid_token()
    {
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        $response = $this->post(route('password.update'), [
            'token' => $this->resetToken($user),
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));
    }

    public function test_password_cannot_be_reset_with_invalid_token(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertSessionHasErrors('email');
    }

    private function resetToken(User $user): string
    {
        $log = EventLog::query()
            ->where('event_key', 'user.password_reset')
            ->where('subject_id', $user->id)
            ->firstOrFail();

        $url = $log->payload['url_restablecer'] ?? '';

        return basename((string) parse_url($url, PHP_URL_PATH));
    }
}
