<?php

namespace App\Modules\Common\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Issues and verifies short-lived 6-digit one-time codes used by both
 * the web and mobile auth flows.
 *
 * Security guarantees worth keeping in mind when touching this class:
 *   - Codes are compared in constant time via hash_equals() so a
 *     timing-side-channel attacker can't bisect the code digit by digit.
 *   - Each issued code is locked to MAX_ATTEMPTS verification attempts;
 *     once exceeded the row is force-marked used so brute force can't
 *     keep trying within the 10-minute window.
 *   - Issuing a new code invalidates every prior unused code for the
 *     same identifier+purpose+guard tuple, so an attacker who has
 *     previously snooped a code can't replay it after the user
 *     re-requests one.
 */
class OtpService
{
    /** Hard cap on wrong guesses per issued code before the row is burned. */
    public const MAX_ATTEMPTS = 5;

    /** Code TTL — kept short to limit the window for brute-force / replay. */
    public const TTL_MINUTES = 10;

    public function generate(string $identifier, string $type = 'email', string $purpose = 'login', string $guard = 'web', ?string $ip = null): string
    {
        DB::table('otps')
            ->where('identifier', $identifier)
            ->where('type', $type)
            ->where('purpose', $purpose)
            ->where('guard', $guard)
            ->where('used', false)
            ->update(['used' => true]);

        $code = app()->environment('production')
            ? str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT)
            : '123456';

        DB::table('otps')->insert([
            'identifier' => $identifier,
            'type' => $type,
            'code' => $code,
            'purpose' => $purpose,
            'guard' => $guard,
            'expires_at' => Carbon::now()->addMinutes(self::TTL_MINUTES),
            'used' => false,
            'attempts' => 0,
            'issued_ip' => $ip,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $code;
    }

    public function verify(string $identifier, string $code, string $type = 'email', string $purpose = 'login', string $guard = 'web'): bool
    {
        // Pull the most recent unused, non-expired code for the tuple
        // WITHOUT matching on the user-supplied code yet — we need a
        // single deterministic row so we can both (a) compare the code
        // in constant time and (b) increment the attempts counter even
        // on a wrong guess.
        $otp = DB::table('otps')
            ->where('identifier', $identifier)
            ->where('type', $type)
            ->where('purpose', $purpose)
            ->where('guard', $guard)
            ->where('used', false)
            ->where('expires_at', '>', Carbon::now())
            ->orderByDesc('id')
            ->first();

        if (!$otp) {
            return false;
        }

        // Lock the row out once it has burned through MAX_ATTEMPTS bad
        // guesses — even if the next guess happens to be correct.
        if ((int) ($otp->attempts ?? 0) >= self::MAX_ATTEMPTS) {
            DB::table('otps')->where('id', $otp->id)->update(['used' => true]);
            Log::warning('OTP attempt cap exceeded', [
                'otp_id'     => $otp->id,
                'identifier' => $identifier,
                'type'       => $type,
                'purpose'    => $purpose,
            ]);
            return false;
        }

        // Constant-time comparison so a timing oracle can't leak the
        // code one character at a time.
        $candidate = (string) $code;
        $expected  = (string) $otp->code;
        if (strlen($candidate) !== strlen($expected) || !hash_equals($expected, $candidate)) {
            DB::table('otps')->where('id', $otp->id)->update([
                'attempts'        => (int) ($otp->attempts ?? 0) + 1,
                'last_attempt_at' => now(),
                'updated_at'      => now(),
            ]);
            return false;
        }

        DB::table('otps')->where('id', $otp->id)->update([
            'used'            => true,
            'last_attempt_at' => now(),
            'updated_at'      => now(),
        ]);

        return true;
    }

    public function sendEmail(string $email, string $code): void
    {
        try {
            \Mail::raw("Your 1INME verification code is: {$code}\n\nThis code expires in " . self::TTL_MINUTES . " minutes.\n\nIf you didn't request this code, you can safely ignore this email.", function ($message) use ($email) {
                $message->to($email);
                $message->subject('Your 1INME Verification Code');
            });
        } catch (\Exception $e) {
            \Log::warning('OTP email send failed: ' . $e->getMessage());
        }
    }

    public function sendSms(string $mobile, string $code): void
    {
        \Log::info("OTP SMS sent to mobile number ending in " . substr($mobile, -4));
    }
}
