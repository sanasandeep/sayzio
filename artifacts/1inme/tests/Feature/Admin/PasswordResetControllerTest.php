<?php

namespace Tests\Feature\Admin;

use App\Modules\Admin\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Regression coverage for the admin forgot-password flow's two safety
 * properties, both of which are otherwise untested and easy to silently break
 * in a refactor:
 *
 *   1. Delivery failures are NOT silently swallowed. The central Emailer
 *      pipeline normally logs a `failed` email_logs row and returns; the
 *      controller opts into throw_on_failure so an EmailDeliveryException
 *      surfaces and the admin sees the amber `delivery_error` banner instead of
 *      the green success `status` banner. If this regressed, an admin whose SMTP
 *      is down would be told "a reset link has been sent" when nothing left the
 *      building — the exact silent-drop this guards against.
 *
 *   2. The account-existence guard: an unrecognised email gets the same neutral
 *      "if an account exists…" success message so the endpoint can't be used to
 *      enumerate admin accounts.
 *
 *   3. The resend throttle: after RESEND_MAX (3) attempts inside the decay
 *      window the endpoint returns the throttle marker instead of sending again,
 *      and each successful resend rotates the stored reset token.
 *
 * The delivery-failure case drives a REAL mail transport failure (the resolved
 * mailer's html() throws, as a down SMTP would) rather than mocking the
 * controller, so it exercises the actual throw_on_failure path end-to-end.
 */
class PasswordResetControllerTest extends TestCase
{
    use RefreshDatabase;

    /** Neutral success message (mirrors PasswordResetController::MSG_SENT). */
    private const NEUTRAL_HINT = 'If an account exists with that email';

    private function makeAdmin(string $email = 'reset-admin@example.com'): Admin
    {
        return Admin::create([
            'name'     => 'Reset Admin',
            'email'    => $email,
            'password' => Hash::make('secret-password'),
            'status'   => 'active',
        ]);
    }

    /** Store token hash currently persisted for an email (null if none). */
    private function storedTokenHash(string $email): ?string
    {
        return DB::table('password_reset_tokens')
            ->where('email', $email)
            ->value('token');
    }

    /**
     * When Emailer::send() throws EmailDeliveryException, sendResetLink() must
     * surface the amber `delivery_error` message and NOT flash the green
     * success `status` — the silent-drop this task guards against.
     */
    public function test_delivery_failure_surfaces_error_not_success(): void
    {
        $admin = $this->makeAdmin();

        // Drive a genuine transport failure: the resolved mailer's html()
        // throws (as a down SMTP would). Emailer swallows this into a `failed`
        // log, but throw_on_failure re-raises it so the controller can react.
        $mailer = \Mockery::mock();
        $mailer->shouldReceive('html')->andThrow(new \RuntimeException('smtp down'));
        Mail::shouldReceive('mailer')->andReturn($mailer);

        $resp = $this->post(route('admin.password.email'), ['email' => $admin->email]);

        $resp->assertRedirect();
        $resp->assertSessionHas('delivery_error');
        $resp->assertSessionMissing('status');
        $this->assertStringContainsString(
            'unable to deliver',
            (string) session('delivery_error'),
        );

        // The failure was still recorded for the admin log — dropped for the
        // user-facing send, never dropped from the audit trail.
        $this->assertDatabaseHas('email_logs', [
            'email_key' => 'admin.password_reset',
            'recipient' => $admin->email,
            'status'    => 'failed',
        ]);
    }

    /**
     * An unrecognised email must get the same neutral success message (no
     * account-existence leak) and never a delivery_error.
     */
    public function test_unknown_email_returns_neutral_message(): void
    {
        $resp = $this->post(route('admin.password.email'), [
            'email' => 'nobody-here@example.com',
        ]);

        $resp->assertRedirect();
        $resp->assertSessionHas('status');
        $resp->assertSessionMissing('delivery_error');
        $this->assertStringContainsString(
            self::NEUTRAL_HINT,
            (string) session('status'),
        );

        // No token was minted for a non-existent admin.
        $this->assertNull($this->storedTokenHash('nobody-here@example.com'));
    }

    /**
     * resendResetLink() must succeed RESEND_MAX (3) times, rotating the stored
     * token each time, then return the throttle marker on the 4th attempt
     * inside the decay window.
     */
    public function test_resend_throttles_after_limit_and_rotates_token(): void
    {
        $admin = $this->makeAdmin();

        $seenHashes = [];

        // Three allowed resends: each succeeds (array transport) and mints a
        // fresh token, so the stored hash changes every time.
        for ($i = 0; $i < 3; $i++) {
            $resp = $this->post(route('admin.password.resend'), ['email' => $admin->email]);

            $resp->assertRedirect();
            $resp->assertSessionHas('status');
            $resp->assertSessionMissing('resend_throttled');

            $hash = $this->storedTokenHash($admin->email);
            $this->assertNotNull($hash);
            $this->assertNotContains($hash, $seenHashes, 'Each successful resend must rotate the token.');
            $seenHashes[] = $hash;
        }

        // Fourth attempt inside the window is throttled: no new send.
        $throttled = $this->post(route('admin.password.resend'), ['email' => $admin->email]);
        $throttled->assertRedirect();
        $throttled->assertSessionHas('resend_throttled');

        // The stored token is unchanged from the last successful resend.
        $this->assertSame(end($seenHashes), $this->storedTokenHash($admin->email));
    }

    /**
     * The consume side: a valid token + email must actually update the admin's
     * password, delete the used token row, and redirect to admin.login with the
     * success flash. This is the end-to-end reset that admins depend on.
     */
    public function test_valid_token_updates_password_and_deletes_token(): void
    {
        $admin = $this->makeAdmin();

        $token = 'valid-reset-token';
        DB::table('password_reset_tokens')->insert([
            'email'      => $admin->email,
            'token'      => Hash::make($token),
            'created_at' => now(),
        ]);

        $resp = $this->post(route('admin.password.update'), [
            'token'                 => $token,
            'email'                 => $admin->email,
            'password'              => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ]);

        $resp->assertRedirect(route('admin.login'));
        $resp->assertSessionHas('success');

        // The password was actually rotated to the new value.
        $this->assertTrue(Hash::check('brand-new-password', $admin->fresh()->password));

        // The used token row is gone (single-use).
        $this->assertNull($this->storedTokenHash($admin->email));
    }

    /**
     * An invalid/mismatched token must be rejected with the neutral
     * "invalid or has expired" error and must NOT change the password.
     */
    public function test_invalid_token_is_rejected(): void
    {
        $admin        = $this->makeAdmin();
        $originalHash = $admin->password;

        DB::table('password_reset_tokens')->insert([
            'email'      => $admin->email,
            'token'      => Hash::make('the-real-token'),
            'created_at' => now(),
        ]);

        $resp = $this->from(route('admin.password.reset', ['token' => 'wrong-token']))
            ->post(route('admin.password.update'), [
                'token'                 => 'wrong-token',
                'email'                 => $admin->email,
                'password'              => 'brand-new-password',
                'password_confirmation' => 'brand-new-password',
            ]);

        $resp->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'invalid or has expired',
            (string) session('errors')->first('email'),
        );

        // Password untouched.
        $this->assertSame($originalHash, $admin->fresh()->password);
    }

    /**
     * A token older than 60 minutes must be rejected AND the stale row deleted,
     * regardless of Carbon's signed-diff behaviour. Guards the expiry window so
     * a leaked-but-old reset link can never be redeemed.
     */
    public function test_expired_token_is_rejected_and_deleted(): void
    {
        $admin        = $this->makeAdmin();
        $originalHash = $admin->password;

        $token = 'expired-reset-token';
        DB::table('password_reset_tokens')->insert([
            'email'      => $admin->email,
            'token'      => Hash::make($token),
            'created_at' => now()->subMinutes(61),
        ]);

        $resp = $this->from(route('admin.password.reset', ['token' => $token]))
            ->post(route('admin.password.update'), [
                'token'                 => $token,
                'email'                 => $admin->email,
                'password'              => 'brand-new-password',
                'password_confirmation' => 'brand-new-password',
            ]);

        $resp->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'expired',
            (string) session('errors')->first('email'),
        );

        // Password untouched and the stale token row was purged.
        $this->assertSame($originalHash, $admin->fresh()->password);
        $this->assertNull($this->storedTokenHash($admin->email));
    }
}
