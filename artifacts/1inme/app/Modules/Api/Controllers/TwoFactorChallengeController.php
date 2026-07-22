<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Api\Resources\UserResource;
use App\Modules\User\Models\User;
use App\Modules\User\Services\TotpService;
use App\Modules\User\Services\TwoFactorPolicy;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

/**
 * Mobile/API second-factor challenge. When a login path (password, OTP,
 * social) resolves a user with a confirmed TOTP authenticator, it does NOT
 * issue a token; instead it returns `totp_required` plus a short-lived
 * APP_KEY-encrypted `challenge_token` (stateless — no session, mirrors the
 * account-merge token pattern). The client then POSTs that token together
 * with a 6-digit authenticator code OR a single-use recovery/backup code
 * here to complete sign-in.
 */
class TwoFactorChallengeController extends Controller
{
    use ApiResponses;

    private const PURPOSE = '2fa_login';
    private const TTL_MINUTES = 10;

    /** Mint a challenge token for a user who still owes the second factor. */
    public static function issueChallengeToken(User $user): string
    {
        return Crypt::encrypt([
            'p'   => self::PURPOSE,
            'uid' => $user->id,
            'exp' => now()->addMinutes(self::TTL_MINUTES)->getTimestamp(),
        ]);
    }

    /**
     * Verify the second factor and issue the real session token.
     * Accepts an authenticator (TOTP) code or a recovery/backup code —
     * both `/auth/2fa/challenge/verify` and `/auth/2fa/backup-codes/verify`
     * land here so older client builds keep working.
     */
    public function verify(Request $request, TotpService $totp, TwoFactorPolicy $policy)
    {
        $data = $request->validate([
            'challenge_token' => ['required', 'string', 'max:2048'],
            'code'            => ['required', 'string', 'max:64'],
            'device'          => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $payload = Crypt::decrypt($data['challenge_token']);
        } catch (\Throwable) {
            return $this->fail('Sign-in expired. Start again from the login screen.', 410, 'challenge_expired');
        }

        if (
            !is_array($payload)
            || ($payload['p'] ?? null) !== self::PURPOSE
            || !is_int($payload['uid'] ?? null)
            || (int) ($payload['exp'] ?? 0) < now()->getTimestamp()
        ) {
            return $this->fail('Sign-in expired. Start again from the login screen.', 410, 'challenge_expired');
        }

        $user = User::find($payload['uid']);
        if (!$user || ($user->status ?? 'active') !== 'active' || !$policy->userHasEnrolledTotp($user)) {
            return $this->fail('Sign-in expired. Start again from the login screen.', 410, 'challenge_expired');
        }

        $code = trim($data['code']);
        $secret = Crypt::decryptString($user->two_factor_secret);
        $matched = $totp->verify($secret, $code) !== null;

        // If TOTP fails, try single-use recovery/backup codes (mirrors the
        // web TwoFactorController::verifyChallenge consumption logic).
        if (!$matched) {
            $stored = json_decode(Crypt::decryptString($user->two_factor_recovery_codes ?? '[]') ?: '[]', true) ?: [];
            $remaining = [];
            $consumed = false;
            foreach ($stored as $hashed) {
                if (!$consumed && Hash::check($code, $hashed)) {
                    $consumed = true;
                    continue;
                }
                $remaining[] = $hashed;
            }
            if ($consumed) {
                $user->forceFill([
                    'two_factor_recovery_codes' => Crypt::encryptString(json_encode($remaining)),
                ])->save();
                $matched = true;
            }
        }

        if (!$matched) {
            return $this->fail('That code is not valid (or has already been used).', 400, 'invalid_2fa_code');
        }

        $newToken = \App\Modules\Api\Support\SessionTokenIssuer::issue(
            $user, $request, $data['device'] ?? null, 'mobile', 'mobile'
        );

        \App\Jobs\RecordLoginEventJob::dispatch(
            $user->id,
            'mobile_totp',
            (string) ($request->ip() ?? ''),
            (string) ($request->userAgent() ?? ''),
            [
                'personal_access_token_id' => $newToken->accessToken->id ?? null,
                'device_label'             => $data['device'] ?? null,
            ],
            true,
            now(),
        );

        return $this->ok([
            'user'  => UserResource::toArray($user, self: true),
            'token' => $newToken->plainTextToken,
        ]);
    }
}
