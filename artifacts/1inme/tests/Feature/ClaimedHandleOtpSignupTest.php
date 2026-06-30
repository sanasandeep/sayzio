<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\Plan;
use App\Modules\Common\Services\OtpService;
use App\Modules\Common\Support\AuthMethods;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Companion to ClaimedHandleSurvivesRegistrationTest (which pins the web
 * email/password user.register.submit flow).
 *
 * This test pins the passwordless OTP / WhatsApp sign-up path from the
 * homepage auth modal. That path is sign-IN by default — a bare code request
 * for an unknown identifier creates nothing and stays enumeration-safe — but
 * the modal's explicit "Sign up with WhatsApp" affordance sends intent=signup,
 * which CREATES the account and carries through the @handle the visitor claimed
 * on the homepage hero (via the hidden desired_handle field).
 *
 *   1. Bare / login-intent web OTP for an unknown identifier creates no
 *      account (and issues no code), and verify returns "User not found".
 *   2. intent=signup web OTP for an unknown identifier DOES create the
 *      account, reserves the claimed handle, and the subsequent verify logs in.
 *   3. intent=signup is still gated by the registration-paused switch.
 *
 * See memory "Marketing hero claim-handle handoff", "Registration pause
 * switch", and "Banned-name @handle surfaces".
 */
class ClaimedHandleOtpSignupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Some auth views use @vite; swap it for a no-op so any rendered
        // page works without a built manifest in the test environment.
        $this->withoutVite();

        // OTP signup assigns the default (free) plan to the new account.
        Plan::firstOrCreate(['slug' => 'free'], [
            'name' => 'Free', 'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'grace_days' => 0, 'refund_window_days' => 0,
            'status' => 'active', 'sort_order' => 0, 'features' => [],
            'is_default' => true,
        ]);
    }

    /**
     * The web "log in with a code" path is login-only: sending a code for an
     * unknown identifier with no signup intent creates nothing (and issues no
     * code), so there is no new account for a claimed handle to land on.
     */
    public function test_web_otp_send_for_unknown_identifier_creates_no_account(): void
    {
        $email = 'newcomer' . Str::lower(Str::random(6)) . '@example.com';

        $this->post('/user/send-otp', [
            'identifier' => $email,
            'type'       => 'email',
        ])->assertRedirect(route('user.otp.verify.form'));

        $this->assertDatabaseMissing('users', ['email' => $email]);
    }

    /**
     * Even a *valid* code for an unknown identifier cannot mint an account via
     * the web verify step when no account was created at send time — it returns
     * "User not found".
     */
    public function test_web_otp_verify_for_unknown_identifier_creates_no_account(): void
    {
        $email = 'ghost' . Str::lower(Str::random(6)) . '@example.com';

        // Mint a genuine code for the unknown identifier and drive verify
        // directly with the session the send step would have set.
        $code = (new OtpService())->generate($email, 'email', 'login', 'web');

        $this->withSession(['otp_identifier' => $email, 'otp_type' => 'email'])
            ->post('/user/verify-otp', ['code' => $code])
            ->assertRedirect(route('user.login'))
            ->assertSessionHasErrors('code');

        $this->assertDatabaseMissing('users', ['email' => $email]);
    }

    /**
     * The explicit sign-up affordance (intent=signup) for an unknown identifier
     * CREATES the account on the web OTP path and reserves the @handle the
     * visitor claimed on the homepage hero. The subsequent verify then logs the
     * brand-new user in.
     */
    public function test_web_otp_signup_intent_creates_account_with_claimed_handle(): void
    {
        $email  = 'creator' . Str::lower(Str::random(6)) . '@example.com';
        $handle = 'taken' . Str::lower(Str::random(6));

        $this->post('/user/send-otp', [
            'identifier'     => $email,
            'type'           => 'email',
            'intent'         => 'signup',
            'desired_handle' => $handle,
        ])->assertRedirect(route('user.otp.verify.form'));

        $user = User::where('email', $email)->first();
        $this->assertNotNull($user, 'signup intent must create the account');
        $this->assertSame($handle, $user->handle, 'the claimed handle must be reserved on the new account');
        $this->assertNotNull($user->plan_id, 'the new account must get the default plan');

        // The minted code logs the new user straight in.
        $code = (new OtpService())->generate($email, 'email', 'login', 'web');
        $this->withSession(['otp_identifier' => $email, 'otp_type' => 'email'])
            ->post('/user/verify-otp', ['code' => $code]);

        $this->assertAuthenticatedAs($user->fresh());
    }

    /**
     * Signup intent is honored on the WhatsApp (mobile) identifier path too —
     * the same affordance, just delivered over WhatsApp. Requires an admin to
     * have switched mobile login on.
     */
    public function test_web_otp_signup_intent_via_whatsapp_creates_account_with_handle(): void
    {
        AppSetting::put(AuthMethods::SETTING_MOBILE_ENABLED, true);

        $mobile = '+1' . random_int(2000000000, 2999999999);
        $handle = 'wacreator' . Str::lower(Str::random(6));

        $this->post('/user/send-otp', [
            'identifier'     => $mobile,
            'type'           => 'mobile',
            'intent'         => 'signup',
            'desired_handle' => $handle,
        ])->assertRedirect(route('user.otp.verify.form'));

        $user = User::where('mobile', $mobile)->first();
        $this->assertNotNull($user, 'WhatsApp signup intent must create the account');
        $this->assertSame($handle, $user->handle, 'the claimed handle must be reserved on the new account');
    }

    /**
     * Signup intent stays gated by the registration-paused switch: no account
     * is created and the visitor sees the branded "upgrading" page.
     */
    public function test_web_otp_signup_intent_is_blocked_when_registration_paused(): void
    {
        AppSetting::put(AuthMethods::SETTING_REGISTRATION_PAUSED, true);

        $email = 'paused' . Str::lower(Str::random(6)) . '@example.com';

        $this->post('/user/send-otp', [
            'identifier'     => $email,
            'type'           => 'email',
            'intent'         => 'signup',
            'desired_handle' => 'somehandle',
        ])->assertOk();

        $this->assertDatabaseMissing('users', ['email' => $email]);
    }
}
