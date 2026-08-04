<?php

namespace App\Services\Integrations;

use App\Modules\Admin\Models\AppSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cloudflare Turnstile (invisible captcha) for the WEB sign-up and
 * OTP-send/resend flows. Mirrors the PlatformServiceSettings pattern:
 *
 *   - the site key, secret key and an enforcement toggle live in the
 *     `app_settings` key/value store (secret Crypt-encrypted at rest),
 *   - each getter falls back to config/services.php + env so a
 *     server-provisioned key keeps working without an admin save,
 *   - unconfigured or toggled off ⇒ enabled() is false and every flow
 *     behaves exactly as before (no script loaded, no token required).
 *
 * Deliberately NOT applied to the mobile app / API endpoints — native
 * Turnstile needs its own widget integration; the API keeps its existing
 * throttles.
 */
class TurnstileSettings
{
    public const KEY_SITE_KEY   = 'turnstile.site_key';
    public const KEY_SECRET_ENC = 'turnstile.secret_key_enc';
    public const KEY_ENABLED    = 'turnstile.enabled';

    /** The form field Cloudflare's widget injects into protected forms. */
    public const TOKEN_FIELD = 'cf-turnstile-response';

    public const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    // ── Accessors ─────────────────────────────────────────────────

    public static function siteKey(): ?string
    {
        $admin = AppSetting::get(self::KEY_SITE_KEY);
        if (is_string($admin) && trim($admin) !== '') return trim($admin);
        $cfg = (string) (config('services.turnstile.site_key') ?: env('TURNSTILE_SITE_KEY', ''));
        return $cfg !== '' ? $cfg : null;
    }

    public static function setSiteKey(?string $v): void
    {
        $v = $v !== null ? trim($v) : null;
        AppSetting::put(self::KEY_SITE_KEY, ($v === null || $v === '') ? null : $v);
    }

    public static function secretKey(): ?string
    {
        $enc = AppSetting::get(self::KEY_SECRET_ENC);
        if ($enc && is_string($enc)) {
            try {
                $v = Crypt::decryptString($enc);
                if ($v !== '') return $v;
            } catch (\Throwable $e) {
                // fall through to env
            }
        }
        $cfg = (string) (config('services.turnstile.secret_key') ?: env('TURNSTILE_SECRET_KEY', ''));
        return $cfg !== '' ? $cfg : null;
    }

    public static function setSecretKey(?string $v): void
    {
        if ($v === null || trim($v) === '') {
            AppSetting::put(self::KEY_SECRET_ENC, null);
            return;
        }
        AppSetting::put(self::KEY_SECRET_ENC, Crypt::encryptString(trim($v)));
    }

    public static function hasAdminSecret(): bool
    {
        $enc = AppSetting::get(self::KEY_SECRET_ENC);
        if (!$enc || !is_string($enc)) return false;
        try {
            return Crypt::decryptString($enc) !== '';
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function maskedSecretKey(): ?string
    {
        $secret = self::secretKey();
        if (!$secret) return null;
        return '••••••••' . substr($secret, -4);
    }

    /** The raw admin toggle (may be on while keys are still missing). */
    public static function toggleOn(): bool
    {
        return (bool) AppSetting::get(self::KEY_ENABLED, false);
    }

    public static function setEnabled(bool $on): void
    {
        AppSetting::put(self::KEY_ENABLED, $on);
    }

    /**
     * Effective enforcement switch: the admin toggle must be ON *and* both
     * keys must be present. Unconfigured ⇒ off ⇒ every flow behaves exactly
     * as today.
     */
    public static function enabled(): bool
    {
        return self::toggleOn()
            && self::siteKey() !== null
            && self::secretKey() !== null;
    }

    public static function status(): array
    {
        if (self::enabled()) {
            return ['key' => 'configured', 'label' => 'Enforcing', 'tone' => 'green'];
        }
        if (self::siteKey() !== null && self::secretKey() !== null) {
            return ['key' => 'env', 'label' => 'Configured (enforcement off)', 'tone' => 'amber'];
        }
        return ['key' => 'preview', 'label' => 'Not configured (off)', 'tone' => 'slate'];
    }

    // ── Verification ──────────────────────────────────────────────

    /**
     * Validate a client token against Cloudflare's siteverify endpoint.
     *
     * A missing/empty token or a definitive "success: false" answer fails
     * closed (bots must not pass by omitting the widget). A *transport*
     * failure (Cloudflare unreachable / timeout) fails OPEN with a warning
     * log — a Cloudflare outage must not take down sign-ups platform-wide;
     * the existing rate limiters and honeypot still apply.
     */
    public static function verify(?string $token, ?string $ip = null): bool
    {
        $token = trim((string) $token);
        if ($token === '') {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post(self::VERIFY_URL, array_filter([
                    'secret'   => self::secretKey(),
                    'response' => $token,
                    'remoteip' => $ip,
                ]));

            if (!$response->ok()) {
                Log::warning('Turnstile siteverify returned HTTP ' . $response->status() . ' — failing open.');
                return true;
            }

            $ok = (bool) $response->json('success');
            if (!$ok) {
                Log::info('Turnstile verification failed', [
                    'error_codes' => $response->json('error-codes'),
                    'ip'          => $ip,
                ]);
            }
            return $ok;
        } catch (\Throwable $e) {
            Log::warning('Turnstile siteverify unreachable — failing open: ' . $e->getMessage());
            return true;
        }
    }
}
