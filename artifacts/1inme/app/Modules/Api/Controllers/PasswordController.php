<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Common\Exceptions\EmailDeliveryException;
use App\Modules\Common\Services\Emailer;
use App\Modules\Common\Services\OtpService;
use App\Modules\User\Models\User;
use App\Modules\User\Services\UserPasswordService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * REST parity for self-serve password management (Task #5619).
 *
 * Authenticated (auth:sanctum):
 *   POST /me/password/change     — current-password confirm
 *   POST /me/password/set-code   — OTP for accounts without a chosen password
 *   POST /me/password/set        — OTP-verified first password
 *
 * Public:
 *   POST /auth/password/forgot   — request a reset link (neutral response)
 *   POST /auth/password/reset    — redeem token + set new password
 *
 * All flows funnel through UserPasswordService::apply(), which signs out
 * every OTHER session/token and sends the security notification. The
 * authenticated endpoints keep the caller's current Sanctum token alive.
 */
class PasswordController extends Controller
{
    use ApiResponses;

    private const MSG_SENT = 'If an account exists with that email, a reset link has been sent.';

    public function __construct(
        private OtpService $otp,
        private UserPasswordService $passwords,
    ) {}

    /** Same namespacing as the web PasswordResetController — shared tokens. */
    private static function tokenKey(string $email): string
    {
        return 'user:' . strtolower(trim($email));
    }

    // ── Authenticated ─────────────────────────────────────────────

    public function change(Request $request)
    {
        $user = $request->user();

        if (!$this->passwords->hasChosenPassword($user)) {
            return $this->fail('Your account has no password yet. Use the set-password flow instead.', 422, 'password_not_set');
        }

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ]);

        if (!Hash::check($data['current_password'], $user->password)) {
            return $this->fail('That does not match your current password.', 422, 'invalid_current_password');
        }

        $this->passwords->apply($user, $data['password'], null, $user->currentAccessToken()?->id, 'changed');

        return $this->ok(['changed' => true]);
    }

    public function sendSetCode(Request $request)
    {
        $user = $request->user();

        if ($this->passwords->hasChosenPassword($user)) {
            return $this->fail('Your account already has a password — use the change-password flow instead.', 422, 'password_already_set');
        }

        [$identifier, $type] = $this->codeChannel($user);
        if (!$identifier) {
            return $this->fail('We have no email or WhatsApp number on file to send a verification code to.', 422, 'no_code_channel');
        }

        $code = $this->otp->generate($identifier, $type, 'set_password', 'api', $request->ip());
        $type === 'email' ? $this->otp->sendEmail($identifier, $code) : $this->otp->sendWhatsApp($identifier, $code);

        return $this->ok(['sent' => true, 'channel' => $type]);
    }

    public function set(Request $request)
    {
        $user = $request->user();

        if ($this->passwords->hasChosenPassword($user)) {
            return $this->fail('Your account already has a password — use the change-password flow instead.', 422, 'password_already_set');
        }

        $data = $request->validate([
            'code'     => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ]);

        [$identifier, $type] = $this->codeChannel($user);
        if (!$identifier || !$this->otp->verify($identifier, $data['code'], $type, 'set_password', 'api')) {
            return $this->fail('Invalid or expired code. Please request a new one.', 422, 'invalid_code');
        }

        $this->passwords->apply($user, $data['password'], null, $user->currentAccessToken()?->id, 'changed');

        return $this->ok(['set' => true]);
    }

    // ── Public ────────────────────────────────────────────────────

    public function forgot(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        $email = strtolower(trim($request->email));
        $user  = User::whereRaw('lower(email) = ?', [$email])->first();

        if ($user) {
            $token = Str::random(64);
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => self::tokenKey($user->email)],
                ['token' => Hash::make($token), 'created_at' => now()]
            );

            try {
                Emailer::send('user.password_reset', $user->email, [
                    'name'      => $user->name ?: 'there',
                    'reset_url' => route('user.password.reset', ['token' => $token, 'email' => $user->email]),
                ], ['throw_on_failure' => true]);
            } catch (EmailDeliveryException $e) {
                // Stay existence-neutral even on delivery failure.
            }
        }

        return $this->ok(['message' => self::MSG_SENT]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token'    => ['required', 'string'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', self::tokenKey($request->email))
            ->first();

        if (!$record || !Hash::check($request->token, $record->token)) {
            return $this->fail('This password reset link is invalid or has expired.', 422, 'invalid_reset_token');
        }

        if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', self::tokenKey($request->email))->delete();
            return $this->fail('This password reset link has expired. Please request a new one.', 422, 'expired_reset_token');
        }

        $user = User::whereRaw('lower(email) = ?', [strtolower(trim($request->email))])->first();
        if (!$user) {
            return $this->fail('This password reset link is invalid or has expired.', 422, 'invalid_reset_token');
        }

        $this->passwords->apply($user, $request->password, null, null, 'reset');

        DB::table('password_reset_tokens')->where('email', self::tokenKey($request->email))->delete();

        return $this->ok(['reset' => true]);
    }

    /** @return array{0:?string,1:string} */
    private function codeChannel(User $user): array
    {
        if (!blank($user->email)) {
            return [$user->email, 'email'];
        }
        if (!blank($user->mobile)) {
            return [$user->mobile, 'mobile'];
        }
        return [null, 'email'];
    }
}
