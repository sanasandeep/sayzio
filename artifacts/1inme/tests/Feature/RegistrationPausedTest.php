<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\Plan;
use App\Modules\Common\Support\AuthMethods;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage for the admin "pause new registrations" switch
 * (AuthMethods::registrationPaused via app_settings). When ON every
 * account-creation surface must create nothing and instead show the
 * branded upgrade page (web) or return a structured error (API), while
 * existing users keep signing in normally.
 */
class RegistrationPausedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The upgrade page uses @vite; swap it for a no-op so the view
        // renders without a built manifest in the test environment.
        $this->withoutVite();

        Plan::firstOrCreate(['slug' => 'free'], [
            'name' => 'Free', 'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'grace_days' => 0, 'refund_window_days' => 0,
            'status' => 'active', 'sort_order' => 0, 'features' => [],
            'is_default' => true,
        ]);
    }

    private function pause(bool $on = true): void
    {
        AppSetting::put(AuthMethods::SETTING_REGISTRATION_PAUSED, $on);
        $this->assertSame($on, AuthMethods::registrationPaused());
    }

    private function makeUser(string $email): User
    {
        return User::create([
            'name'     => 'Existing ' . Str::random(4),
            'email'    => $email,
            'password' => Hash::make('secret-pass'),
            'status'   => 'active',
        ]);
    }

    public function test_default_is_off(): void
    {
        $this->assertFalse(AuthMethods::registrationPaused());
    }

    public function test_web_register_page_shows_upgrade_when_paused(): void
    {
        $this->pause();
        $this->get('/user/register')
            ->assertOk()
            ->assertViewIs('user.auth.registration-paused');
    }

    public function test_web_register_post_creates_nothing_when_paused(): void
    {
        $this->pause();
        $before = User::count();
        $this->post('/user/register', [
            'name'                  => 'New Person',
            'email'                 => 'newperson@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertOk()->assertViewIs('user.auth.registration-paused');

        $this->assertSame($before, User::count());
        $this->assertDatabaseMissing('users', ['email' => 'newperson@example.com']);
    }

    public function test_web_otp_unknown_identifier_shows_upgrade_when_paused(): void
    {
        $this->pause();
        $this->post('/user/send-otp', [
            'identifier' => 'nobody@example.com',
            'type'       => 'email',
        ])->assertOk()->assertViewIs('user.auth.registration-paused');

        $this->assertDatabaseMissing('users', ['email' => 'nobody@example.com']);
    }

    public function test_web_otp_existing_user_still_sends_when_paused(): void
    {
        $this->pause();
        $this->makeUser('member@example.com');

        // Existing accounts are unaffected: the code is issued and the user
        // is taken to the verify form, exactly as when not paused.
        $this->post('/user/send-otp', [
            'identifier' => 'member@example.com',
            'type'       => 'email',
        ])->assertRedirect(route('user.otp.verify.form'));
    }

    public function test_api_register_blocked_when_paused(): void
    {
        $this->pause();
        $this->postJson('/api/v1/auth/register', [
            'name'     => 'Api New',
            'email'    => 'apinew@example.com',
            'password' => 'password123',
        ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', AuthMethods::ERROR_REGISTRATION_PAUSED);

        $this->assertDatabaseMissing('users', ['email' => 'apinew@example.com']);
    }

    public function test_api_otp_send_unknown_blocked_when_paused(): void
    {
        $this->pause();
        $this->postJson('/api/v1/auth/otp/send', [
            'identifier' => 'apinobody@example.com',
            'type'       => 'email',
        ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', AuthMethods::ERROR_REGISTRATION_PAUSED);
    }

    public function test_api_otp_register_blocked_when_paused(): void
    {
        $this->pause();
        $this->postJson('/api/v1/auth/otp/register', [
            'name'       => 'Api Otp New',
            'identifier' => 'apiotpnew@example.com',
            'type'       => 'email',
        ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', AuthMethods::ERROR_REGISTRATION_PAUSED);

        $this->assertDatabaseMissing('users', ['email' => 'apiotpnew@example.com']);
    }

    public function test_api_register_works_when_not_paused(): void
    {
        $this->pause(false);
        $this->postJson('/api/v1/auth/register', [
            'name'     => 'Api Allowed',
            'email'    => 'apiallowed@example.com',
            'password' => 'password123',
        ])->assertStatus(201);

        $this->assertDatabaseHas('users', ['email' => 'apiallowed@example.com']);
    }
}
