<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\Plan;
use App\Modules\Common\Support\AuthMethods;
use App\Modules\User\Models\LinkedIdentifier;
use App\Modules\User\Models\User;
use App\Modules\User\Services\SocialFollowers\SocialOAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for the admin "pause new registrations" switch on the
 * SOCIAL first-login path (web "Sign in with Google"): when an unbound social
 * identity with a verified email that matches NO existing account completes
 * OAuth, SocialOAuthController::callback normally auto-creates a free
 * account. While `auth_registration_paused` is ON that branch must create
 * NOTHING (no user, no linked identifier, no session) — while sign-in for
 * EXISTING accounts keeps working. Companion to RegistrationPausedTest
 * (email/OTP + API surfaces) and the otp-signup-*-paused.spec.ts Browser
 * specs (WhatsApp + email OTP).
 *
 * The provider round-trip is faked by mocking SocialOAuthService::fetchProfile
 * (the controller receives it via method injection, so the container mock is
 * picked up); the state handshake uses the real session key the controller
 * checks with hash_equals.
 */
class SocialFirstLoginRegistrationPausedTest extends TestCase
{
    use RefreshDatabase;

    private const PROVIDER = 'google';
    private const STATE    = 'test-oauth-state-token-0123456789';

    protected function setUp(): void
    {
        parent::setUp();
        // Some redirect targets (login/registration-paused pages) use @vite;
        // swap it for a no-op so views render without a built manifest.
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

    /**
     * Fake the provider round-trip: the controller's method-injected
     * SocialOAuthService resolves from the container, so a mock whose
     * fetchProfile returns [externalId, handle, email] stands in for the
     * real token exchange + profile lookup.
     */
    private function mockProfile(string $externalId, ?string $handle, ?string $email): void
    {
        $this->mock(SocialOAuthService::class, function ($mock) use ($externalId, $handle, $email) {
            $mock->shouldReceive('fetchProfile')->andReturn([$externalId, $handle, $email]);
        });
    }

    /**
     * Hit the login-mode OAuth callback exactly as the provider redirect
     * would, with the state/mode session keys loginConnect() stashes.
     */
    private function hitCallback()
    {
        return $this->withSession([
            'social_oauth_state_' . self::PROVIDER => self::STATE,
            'social_oauth_mode_' . self::PROVIDER  => 'login',
        ])->get('/user/social-oauth/' . self::PROVIDER . '/callback?' . http_build_query([
            'state' => self::STATE,
            'code'  => 'fake-provider-code',
        ]));
    }

    public function test_social_first_login_creates_nothing_when_paused(): void
    {
        $this->pause();
        $this->mockProfile('ext-paused-1', 'Paused Person', 'social-paused@example.com');

        $before = User::count();
        $response = $this->hitCallback();

        // Web visitors are bounced to /register, which shows the branded
        // paused page while the switch is on.
        $response->assertRedirect(route('user.register'));

        // The decisive invariants: NO account, NO social binding, NO session.
        $this->assertSame($before, User::count());
        $this->assertDatabaseMissing('users', ['email' => 'social-paused@example.com']);
        $this->assertSame(0, LinkedIdentifier::where('kind', 'social')
            ->where('provider', self::PROVIDER)
            ->where('external_id', 'ext-paused-1')->count());
        $this->assertGuest('web');
    }

    public function test_social_login_for_existing_email_account_still_works_when_paused(): void
    {
        $this->pause();
        $existing = User::factory()->create(['email' => 'social-member@example.com']);
        $this->mockProfile('ext-member-1', 'Member', 'social-member@example.com');

        $before = User::count();
        $response = $this->hitCallback();

        // Pausing registrations must never lock out existing users: the
        // matching-email account is auto-linked and signed in.
        $response->assertRedirect(route('user.dashboard'));
        $this->assertAuthenticatedAs($existing, 'web');
        $this->assertSame($before, User::count());
        $this->assertSame(1, LinkedIdentifier::where('kind', 'social')
            ->where('provider', self::PROVIDER)
            ->where('external_id', 'ext-member-1')
            ->where('user_id', $existing->id)->count());
    }

    public function test_social_first_login_creates_gated_account_when_open(): void
    {
        $this->pause(false);
        $this->mockProfile('ext-open-1', 'Fresh Person', 'social-open@example.com');

        $response = $this->hitCallback();

        // Open registrations: the account IS minted, the social identity is
        // bound, the visitor is signed in — and the fresh account is GATED
        // into the mandatory name entry, not dropped on a bare dashboard.
        // This control proves the paused-case refusal isn't a broken flow.
        $response->assertRedirect(route('user.complete.profile'));

        $user = User::where('email', 'social-open@example.com')->first();
        $this->assertNotNull($user, 'open social first-login must create the account');
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertSame(1, LinkedIdentifier::where('kind', 'social')
            ->where('provider', self::PROVIDER)
            ->where('external_id', 'ext-open-1')
            ->where('user_id', $user->id)->count());
    }
}
