<?php

namespace Tests\Feature\Auth;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function closeRegistrations(): void
    {
        Setting::create(['key' => 'evento_registro_abierto', 'value' => '0']);
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_register_screen_is_open_by_default()
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/Register')
                ->where('closed', false));
    }

    public function test_register_screen_shows_notice_when_registration_is_closed()
    {
        $this->closeRegistrations();

        $this->get(route('register'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/Register')
                ->where('closed', true));
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), [
            'first_name' => 'Test',
            'last_name' => 'User',
            'dni' => '12345678901',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'affiliation' => 'Test University',
            'country' => 'Mexico',
            'state' => 'CDMX',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('dashboard', absolute: false));

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'email_verified_at' => null,
        ]);

        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user->password_set_at);

        $this->assertDatabaseHas('event_logs', [
            'event_key' => 'user.registered',
            'subject_type' => (new User)->getMorphClass(),
            'subject_id' => $user->id,
        ]);
    }

    public function test_new_users_cannot_register_when_registration_is_closed()
    {
        $this->closeRegistrations();

        $this->post(route('register.store'), [
            'first_name' => 'Test',
            'last_name' => 'User',
            'dni' => '12345678901',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'affiliation' => 'Test University',
            'country' => 'Mexico',
            'state' => 'CDMX',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    public function test_login_and_home_hide_register_link_when_closed()
    {
        $this->closeRegistrations();

        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/Login')
                ->where('canRegister', false));

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/Login')
                ->where('canRegister', false));
    }

    public function test_login_and_home_offer_register_link_when_open()
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/Login')
                ->where('canRegister', true));

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/Login')
                ->where('canRegister', true));
    }
}
