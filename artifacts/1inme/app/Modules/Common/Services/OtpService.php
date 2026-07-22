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
            \App\Modules\Common\Services\Emailer::send('auth.otp_code', $email, [
                'code'        => $code,
                'ttl_minutes' => self::TTL_MINUTES,
            ]);
        } catch (\Exception $e) {
            \Log::warning('OTP email send failed: ' . $e->getMessage());
        }
    }

    /**
     * Deliver a code over WhatsApp via the Meta WhatsApp Cloud API.
     *
     * Runs in "preview" mode — logging the code instead of calling Meta —
     * whenever the credentials in config/whatsapp.php are absent, so the
     * flow stays fully demonstrable in development. In production with
     * credentials present it posts the configured template message.
     */
    public function sendWhatsApp(string $mobile, string $code): void
    {
        // Admin-managed values (API Keys hub) take precedence; the settings
        // accessors fall back to config/whatsapp.php (env) when unset, so
        // preview mode is preserved when neither source is configured.
        $phoneNumberId = (string) (\App\Services\Integrations\IntegrationKeySettings::whatsappPhoneNumberId() ?? '');
        $accessToken   = (string) (\App\Services\Integrations\IntegrationKeySettings::whatsappAccessToken() ?? '');

        // Meta requires the recipient in international format, digits only.
        $to = preg_replace('/\D+/', '', $mobile) ?? '';

        if ($phoneNumberId === '' || $accessToken === '' || $to === '') {
            Log::info('WhatsApp OTP (preview mode — credentials absent): code ' . $code . ' for number ending in ' . substr($mobile, -4));
            return;
        }

        $version  = \App\Services\Integrations\IntegrationKeySettings::whatsappGraphVersion();
        $template = \App\Services\Integrations\IntegrationKeySettings::whatsappTemplateName();
        $language = \App\Services\Integrations\IntegrationKeySettings::whatsappTemplateLanguage();
        $endpoint = "https://graph.facebook.com/{$version}/{$phoneNumberId}/messages";

        try {
            $response = \Illuminate\Support\Facades\Http::withToken($accessToken)
                ->acceptJson()
                ->post($endpoint, [
                    'messaging_product' => 'whatsapp',
                    'to'                => $to,
                    'type'              => 'template',
                    'template'          => [
                        'name'     => $template,
                        'language' => ['code' => $language],
                        'components' => [
                            [
                                'type'       => 'body',
                                'parameters' => [
                                    ['type' => 'text', 'text' => $code],
                                ],
                            ],
                            [
                                'type'        => 'button',
                                'sub_type'    => 'url',
                                'index'       => '0',
                                'parameters'  => [
                                    ['type' => 'text', 'text' => $code],
                                ],
                            ],
                        ],
                    ],
                ]);

            if ($response->failed()) {
                Log::warning('WhatsApp OTP send failed: HTTP ' . $response->status() . ' ' . $response->body());
            }
        } catch (\Throwable $e) {
            Log::warning('WhatsApp OTP send threw: ' . $e->getMessage());
        }
    }
}
