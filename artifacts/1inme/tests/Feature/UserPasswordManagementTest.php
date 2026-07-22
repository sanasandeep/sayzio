<?php

namespace Tests\Feature;

use App\Modules\Common\Services\OtpService;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Self-serve password management (Task #5619).
 *
 * Web:
 *   POST /user/settings/security/password        — change (current-password) or set-first (OTP)
 *   POST /user/settings/security/password/code   — OTP for the set-first variant
 *   GET/POST /user/forgot-password, /user/reset-password/{token}
 *
 * API (/api/v1):
 *   POST /me/password/change | /me/password/set-code | /me/password/set
 *   POST /auth/password/forgot | /auth/password/reset (public)
 *
 * All flows funnel through UserPasswordService::apply(): password +
 * password_set_at stamped, remember_token rotated, every OTHER Sanctum
 * token revoked (the caller's own token survives), reset revokes all.
 */
class UserPasswordManagementTest extends TestCase
{
    use RefreshDatabase;

    private const OLD_PASSWORD = 'old-password-123';
    private const NEW_PASSWORD = 'new-password-456';

    // ---------------------------------------------------------------
    // Fixtures
    // ---------------------------------------------------------------

    /** Account with a user-chosen password. */
    private function chosenUser(): User
    {
        return User::factory()->create([
            'password'        => Hash::make(self::OLD_PASSWORD),
            'password_set_at' => now()->subDay(),
        ])->fresh();
    }

    /** OTP/social account — filler hash, never chose a password. */
    private function fillerUser(): User
    {
        return User::factory()->create([
            'password'        => Hash::make(Str::random(40)),
            'password_set_at' => null,
        ])->fresh();
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    private function otpCode(User $user, string $guard): string
    {
        return app(OtpService::class)->generate($user->email, 'email', 'set_password', $guard, '127.0.0.1');
    }

    private function userTokenKey(string $email): string
    {
        return 'user:' . strtolower(trim($email));
    }

    /** Seed a valid reset token row; returns the plaintext token. */
    private function seedResetToken(User $user, ?Carbon $createdAt = null): string
    {
        $token = Str::random(64);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $this->userTokenKey($user->email)],
            ['token' => Hash::make($token), 'created_at' => $createdAt ?? now()]
        );

        return $token;
    }

    // ---------------------------------------------------------------
    // Web — change password (current-password confirm)
    // ---------------------------------------------------------------

    public function test_web_change_requires_correct_current_password(): void
    {
        $user = $this->chosenUser();

        $this->be($user, 'web');
        $resp = $this->post(route('user.account.password.update'), [
            'current_password'      => 'wrong-password',
            'password'              => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ]);

        $resp->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $user->fresh()->password));
    }

    public function test_web_change_happy_path_keeps_session_and_revokes_tokens(): void
    {
        $user = $this->chosenUser();
        $user->createToken('phone'); // a mobile token that must be revoked

        $this->be($user, 'web');
        $resp = $this->post(route('user.account.password.update'), [
            'current_password'      => self::OLD_PASSWORD,
            'password'              => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ]);

        $resp->assertRedirect(route('user.account.two-factor.show'));
        $resp->assertSessionHas('success');

        $fresh = $user->fresh();
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $fresh->password));
        $this->assertNotNull($fresh->password_set_at);
        $this->assertSame(0, $fresh->tokens()->count(), 'All Sanctum tokens must be revoked on web change.');
        $this->assertAuthenticatedAs($fresh, 'web');
    }

    public function test_web_set_first_password_verifies_otp_code(): void
    {
        $user = $this->fillerUser();

        $this->be($user, 'web');

        // Wrong code is rejected.
        $this->post(route('user.account.password.update'), [
            'code'                  => '000000',
            'password'              => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertSessionHasErrors('code');
        $this->assertNull($user->fresh()->password_set_at);

        // Correct 'set_password' purpose code works.
        $code = $this->otpCode($user, 'web');
        $resp = $this->post(route('user.account.password.update'), [
            'code'                  => $code,
            'password'              => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ]);

        $resp->assertRedirect(route('user.account.two-factor.show'));
        $fresh = $user->fresh();
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $fresh->password));
        $this->assertNotNull($fresh->password_set_at);
    }

    public function test_web_set_code_endpoint_rejected_when_password_already_chosen(): void
    {
        $user = $this->chosenUser();

        $this->be($user, 'web');
        $this->from(route('user.account.two-factor.show'))
            ->post(route('user.account.password.code'))
            ->assertSessionHasErrors('password');
    }

    // ---------------------------------------------------------------
    // Web — forgot / reset
    // ---------------------------------------------------------------

    public function test_web_forgot_is_existence_neutral(): void
    {
        $this->from(route('user.password.request'))
            ->post(route('user.password.email'), ['email' => 'nobody@example.com'])
            ->assertRedirect(route('user.password.request'))
            ->assertSessionHas('status');

        $this->assertNull(
            DB::table('password_reset_tokens')->where('email', $this->userTokenKey('nobody@example.com'))->first()
        );
    }

    public function test_web_forgot_mints_user_prefixed_token_row(): void
    {
        $user = $this->chosenUser();

        $this->from(route('user.password.request'))
            ->post(route('user.password.email'), ['email' => $user->email])
            ->assertSessionHas('status');

        $row = DB::table('password_reset_tokens')
            ->where('email', $this->userTokenKey($user->email))->first();
        $this->assertNotNull($row, 'Expected a user:-prefixed password_reset_tokens row.');
        // Bare-email (admin-namespace) row must NOT exist.
        $this->assertNull(
            DB::table('password_reset_tokens')->where('email', strtolower($user->email))->first()
        );
    }

    public function test_web_reset_happy_path_signs_out_everything(): void
    {
        $user  = $this->fillerUser();
        $user->createToken('phone');
        $token = $this->seedResetToken($user);

        $resp = $this->post(route('user.password.update'), [
            'token'                 => $token,
            'email'                 => $user->email,
            'password'              => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ]);

        $resp->assertRedirect(route('user.login'));

        $fresh = $user->fresh();
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $fresh->password));
        $this->assertNotNull($fresh->password_set_at);
        $this->assertSame(0, $fresh->tokens()->count());
        // Token row consumed.
        $this->assertNull(
            DB::table('password_reset_tokens')->where('email', $this->userTokenKey($user->email))->first()
        );
    }

    public function test_web_reset_rejects_bad_and_expired_tokens(): void
    {
        $user = $this->chosenUser();
        $this->seedResetToken($user);

        // Wrong token.
        $this->from('/user/reset-password/x')->post(route('user.password.update'), [
            'token'                 => 'not-the-token',
            'email'                 => $user->email,
            'password'              => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertSessionHasErrors('email');

        // Expired token (61 minutes old).
        $token = $this->seedResetToken($user, now()->subMinutes(61));
        $this->from('/user/reset-password/x')->post(route('user.password.update'), [
            'token'                 => $token,
            'email'                 => $user->email,
            'password'              => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $user->fresh()->password));
    }

    public function test_web_forgot_and_reset_pages_render(): void
    {
        $this->get(route('user.password.request'))->assertOk();
        $this->get(route('user.password.reset', ['token' => 'abc', 'email' => 'a@b.co']))->assertOk();
    }

    // ---------------------------------------------------------------
    // API — change / set
    // ---------------------------------------------------------------

    public function test_api_change_keeps_current_token_and_revokes_others(): void
    {
        $user  = $this->chosenUser();
        $mine  = $this->token($user);
        $user->createToken('other-device');

        $resp = $this->withToken($mine)->postJson('/api/v1/me/password/change', [
            'current_password'      => self::OLD_PASSWORD,
            'password'              => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ]);

        $resp->assertOk()->assertJsonPath('data.changed', true);

        $fresh = $user->fresh();
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $fresh->password));
        $this->assertSame(1, $fresh->tokens()->count(), 'Only the calling token must survive.');

        // The surviving token still works.
        $this->flushHeaders();
        $this->withToken($mine)->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_api_change_rejects_wrong_current_password_and_filler_accounts(): void
    {
        $user = $this->chosenUser();
        $this->withToken($this->token($user))->postJson('/api/v1/me/password/change', [
            'current_password'      => 'wrong',
            'password'              => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertStatus(422)->assertJsonPath('error.code', 'invalid_current_password');

        $this->flushHeaders();
        $filler = $this->fillerUser();
        $this->withToken($this->token($filler))->postJson('/api/v1/me/password/change', [
            'current_password'      => 'anything',
            'password'              => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertStatus(422)->assertJsonPath('error.code', 'password_not_set');
    }

    public function test_api_set_first_password_via_otp(): void
    {
        $user = $this->fillerUser();
        $mine = $this->token($user);

        $this->withToken($mine)->postJson('/api/v1/me/password/set-code', [])
            ->assertOk()
            ->assertJsonPath('data.sent', true)
            ->assertJsonPath('data.channel', 'email');

        // Wrong code rejected.
        $this->withToken($mine)->postJson('/api/v1/me/password/set', [
            'code'                  => '000000',
            'password'              => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertStatus(422)->assertJsonPath('error.code', 'invalid_code');

        $code = $this->otpCode($user, 'api');
        $this->withToken($mine)->postJson('/api/v1/me/password/set', [
            'code'                  => $code,
            'password'              => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertOk()->assertJsonPath('data.set', true);

        $fresh = $user->fresh();
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $fresh->password));
        $this->assertNotNull($fresh->password_set_at);
        $this->assertSame(1, $fresh->tokens()->count());
    }

    public function test_api_set_code_rejected_when_password_already_chosen(): void
    {
        $user = $this->chosenUser();
        $this->withToken($this->token($user))
            ->postJson('/api/v1/me/password/set-code', [])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'password_already_set');
    }

    // ---------------------------------------------------------------
    // API — public forgot / reset
    // ---------------------------------------------------------------

    public function test_api_forgot_is_existence_neutral(): void
    {
        $user = $this->chosenUser();

        $known   = $this->postJson('/api/v1/auth/password/forgot', ['email' => $user->email]);
        $unknown = $this->postJson('/api/v1/auth/password/forgot', ['email' => 'ghost@example.com']);

        $known->assertOk();
        $unknown->assertOk();
        $this->assertSame($known->json('data.message'), $unknown->json('data.message'));

        $this->assertNotNull(
            DB::table('password_reset_tokens')->where('email', $this->userTokenKey($user->email))->first()
        );
    }

    public function test_api_reset_happy_path_and_expiry(): void
    {
        $user  = $this->chosenUser();
        $token = $this->seedResetToken($user);

        $this->postJson('/api/v1/auth/password/reset', [
            'token'                 => $token,
            'email'                 => $user->email,
            'password'              => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertOk()->assertJsonPath('data.reset', true);

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->fresh()->password));

        // Expired token.
        $expired = $this->seedResetToken($user, now()->subMinutes(61));
        $this->postJson('/api/v1/auth/password/reset', [
            'token'                 => $expired,
            'email'                 => $user->email,
            'password'              => 'another-password-9',
            'password_confirmation' => 'another-password-9',
        ])->assertStatus(422)->assertJsonPath('error.code', 'expired_reset_token');
    }
}
