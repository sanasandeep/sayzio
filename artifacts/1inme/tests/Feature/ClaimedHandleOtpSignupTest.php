<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\Common\Services\OtpService;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Companion to ClaimedHandleSurvivesRegistrationTest. That test pins the web
 * POST user.register.submit flow, which is the ONLY signup surface a "claim
 * your link" handle is ever carried into (homepage hero → open-auth modal →
 * hidden desired_handle field → AuthController::applyClaimedHandle).
 *
 * This test pins the *decision* for the other account-creation surfaces named
 * in the task — the OTP-as-signup paths — so the claimed-handle behavior is
 * consistent and can't silently drift:
 *
 *   DECISION: a claimed handle is intentionally OUT OF SCOPE for the OTP-based
 *   signup paths. The claim-handle entry points never route a handle into
 *   them, so there is nothing to carry through:
 *
 *   1. Web bare OTP path (POST user.otp.send → user.otp.verify) never CREATES
 *      an account at all. An unknown identifier gets a generic "if an account
 *      exists" response with no code issued, and verify returns "User not
 *      found" — so there is no new account for a handle to attach to.
 *   2. Mobile/API OTP signup (POST /api/v1/auth/otp/register) DOES create an
 *      account, but its request contract has no handle field. A handle sent
 *      anyway is ignored, not applied — the new account has no @handle.
 *
 * This mirrors the documented design that the OTP / social account-creation
 * paths don't accept a handle (handles are chosen later in-app). If that ever
 * changes deliberately, these assertions are the place to update.
 *
 * See memory "Marketing hero claim-handle handoff" and "Banned-name @handle
 * surfaces".
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
     * unknown identifier creates nothing (and issues no code), so there is no
     * new account for a claimed handle to land on.
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
     * the web verify step — it returns "User not found". This is what makes a
     * claimed handle moot on this path: verify never creates a user, so a
     * (hypothetical) carried handle would have nothing to attach to.
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
     * The mobile/API OTP signup DOES create an account, but it has no handle
     * field — a handle sent alongside the request is ignored, not applied.
     * The account is created with no @handle (to be chosen later in-app).
     */
    public function test_api_otp_register_ignores_a_supplied_handle(): void
    {
        // The controller stores the email lowercased, so keep the local part
        // lowercase to match it back on the lookup below.
        $email = 'mobilesignup' . Str::lower(Str::random(6)) . '@example.com';

        $this->postJson('/api/v1/auth/otp/register', [
            'name'           => 'Mobile Signup',
            'identifier'     => $email,
            'type'           => 'email',
            // Neither field is part of the contract — both must be ignored.
            'handle'         => 'claimedhandle',
            'desired_handle' => 'claimedhandle',
        ])->assertStatus(201)
          ->assertJsonPath('data.sent', true);

        $user = User::where('email', $email)->first();
        $this->assertNotNull($user, 'the OTP signup must create the account');
        $this->assertNull($user->handle, 'the OTP signup path must not apply a handle');
        $this->assertDatabaseMissing('users', ['handle' => 'claimedhandle']);
    }
}
