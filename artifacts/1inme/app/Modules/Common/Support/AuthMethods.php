<?php

namespace App\Modules\Common\Support;

use App\Modules\Admin\Models\AppSetting;

/**
 * Single source of truth for the login-method policy.
 *
 * By default Sayzio accepts email as the only login / account-recovery
 * identifier. An admin can switch on mobile login — which is WhatsApp-only
 * (codes delivered through the Meta WhatsApp Cloud API) — and restrict it
 * to an allow-list of international dialling codes.
 *
 * The toggle + country-code list live in AppSetting (JSONB key/value,
 * 5-min cached). The WhatsApp *delivery credentials* live in
 * config/whatsapp.php (env-backed), not here.
 */
class AuthMethods
{
    public const SETTING_MOBILE_ENABLED = 'auth_mobile_login_enabled';
    public const SETTING_ALLOWED_CODES  = 'auth_allowed_country_codes';

    public const SETTING_EMAIL_PASSWORD_ENABLED = 'auth_email_password_enabled';
    public const SETTING_EMAIL_OTP_ENABLED      = 'auth_email_otp_enabled';

    public const SETTING_EMAIL_VERIFICATION_REQUIRED = 'auth_email_verification_required';

    /**
     * Temporary "pause new registrations" switch. When on, no NEW account
     * can be created on any surface (web register form/submit, OTP
     * login-as-signup for an unknown identifier, social sign-in for an
     * unlinked identity, mobile/API register endpoints) — instead the
     * visitor sees the branded "we're upgrading" page/message. Existing
     * users keep signing in and using everything exactly as before.
     */
    public const SETTING_REGISTRATION_PAUSED = 'auth_registration_paused';

    /**
     * Demo mode: when on, the actual one-time code is surfaced on screen
     * after it is sent (alongside the normal email/WhatsApp delivery). This
     * exists so reviewers and demo accounts can complete OTP sign-in without
     * access to a real inbox/phone. It NEVER changes how codes are generated,
     * delivered, or expired — it only reveals an already-issued code.
     */
    public const SETTING_DEMO_REVEAL_OTP = 'auth_demo_reveal_otp_enabled';

    /** Seeded defaults when an admin has never saved the settings. */
    public const DEFAULT_ALLOWED_CODES = ['+91', '+1'];

    /**
     * Defaults preserve today's behaviour: email OTP is on (the historical
     * hardcoded primary method) and email + password login is off (accounts
     * are created with a random, unused password).
     */
    public const DEFAULT_EMAIL_OTP_ENABLED      = true;
    public const DEFAULT_EMAIL_PASSWORD_ENABLED = true;

    /**
     * Whether a newly-registered user must verify their email (via the
     * emailed 6-digit code) before reaching their account. Defaults to ON so
     * existing installations keep forcing verification with no behaviour
     * change. Only meaningful when password login is available — in OTP-only
     * mode the emailed code is the sole way in, so verification can never be
     * skipped regardless of this setting.
     */
    public const DEFAULT_EMAIL_VERIFICATION_REQUIRED = true;

    /**
     * New registrations are accepted by default — the pause switch is an
     * explicit, temporary action an admin takes.
     */
    public const DEFAULT_REGISTRATION_PAUSED = false;

    /**
     * Demo-reveal defaults ON so out-of-the-box demo/review environments can
     * complete OTP sign-in without a real inbox. Admins switch it off for
     * production-grade privacy.
     */
    public const DEFAULT_DEMO_REVEAL_OTP = true;

    /** Is WhatsApp (mobile) login switched on by an admin? */
    public static function mobileLoginEnabled(): bool
    {
        return (bool) AppSetting::get(self::SETTING_MOBILE_ENABLED, false);
    }

    /** Can users sign in with their email address + a chosen password? */
    public static function emailPasswordEnabled(): bool
    {
        return (bool) AppSetting::get(
            self::SETTING_EMAIL_PASSWORD_ENABLED,
            self::DEFAULT_EMAIL_PASSWORD_ENABLED
        );
    }

    /** Can users sign in with a one-time code emailed to them? */
    public static function emailOtpEnabled(): bool
    {
        return (bool) AppSetting::get(
            self::SETTING_EMAIL_OTP_ENABLED,
            self::DEFAULT_EMAIL_OTP_ENABLED
        );
    }

    /**
     * Must a new registrant verify their email (via the emailed code) before
     * reaching their account? Defaults to ON. This only takes effect when a
     * usable password exists for the account — in OTP-only mode the emailed
     * code is the only way to authenticate, so it can never be skipped.
     */
    public static function emailVerificationRequired(): bool
    {
        return (bool) AppSetting::get(
            self::SETTING_EMAIL_VERIFICATION_REQUIRED,
            self::DEFAULT_EMAIL_VERIFICATION_REQUIRED
        );
    }

    /**
     * Are new account registrations currently paused by an admin? When true,
     * every account-creation path is blocked and the visitor is shown the
     * branded "we're upgrading" page/message. Defaults to OFF.
     */
    public static function registrationPaused(): bool
    {
        // Always read the live value from the database — registration pause is a
        // security gate that must reflect admin changes immediately without any
        // cache warm-up window. Auth flows are not hot paths.
        $row = \DB::table('app_settings')
            ->where('key', self::SETTING_REGISTRATION_PAUSED)
            ->first();
        if ($row === null) {
            return self::DEFAULT_REGISTRATION_PAUSED;
        }
        // The `value` column uses Eloquent's 'array' cast (JSONB storage).
        // DB::table() returns the raw JSON string, so decode it before casting.
        // Note: (bool) "false" === true in PHP — json_decode is mandatory here.
        return (bool) json_decode($row->value, true);
    }

    /**
     * The user-facing message shown (web + API) when a new sign-up is
     * attempted while registrations are paused. Kept here so every surface
     * speaks with one voice.
     */
    public static function registrationPausedMessage(): string
    {
        return "We're upgrading and aren't accepting new sign-ups right now. If you already have an account, you can still sign in.";
    }

    /**
     * Stable machine code for the paused-registration condition, used in the
     * unified API error envelope so mobile clients can branch on it.
     */
    public const ERROR_REGISTRATION_PAUSED = 'registration_paused';

    /**
     * Is verifying a user's email address meaningful under the current
     * login policy? Email verification only matters when email is actually
     * used to sign in — either with a one-time code or a password. In a
     * (hypothetical) mobile-only configuration where both email login
     * methods are switched off the email address never authenticates the
     * account, so an "verify your email" nudge would be pointless. Used to
     * gate the post-sign-up verification reminder banner so it never shows
     * for accounts that can never (meaningfully) verify.
     */
    public static function emailVerificationMeaningful(): bool
    {
        return self::emailOtpEnabled() || self::emailPasswordEnabled();
    }

    /**
     * Is "Demo mode (reveal OTP on screen)" switched on? When on, a freshly
     * issued code is shown to the user after it's sent. Defaults to ON.
     */
    public static function demoRevealOtpEnabled(): bool
    {
        return (bool) AppSetting::get(
            self::SETTING_DEMO_REVEAL_OTP,
            self::DEFAULT_DEMO_REVEAL_OTP
        );
    }

    /**
     * Build the on-screen "for demo purposes" reveal line for a just-issued
     * code, or null when demo mode is off / no real code was generated.
     *
     * Callers MUST pass the actual code returned by OtpService::generate and
     * only when a real code was issued (i.e. a matching account existed) — so
     * passing null here for account-existence-hiding branches safely reveals
     * nothing.
     */
    public static function demoRevealMessage(?string $code): ?string
    {
        if (!self::demoRevealOtpEnabled() || $code === null || $code === '') {
            return null;
        }
        return 'For demo purposes only — your verification code is ' . $code;
    }

    /**
     * The login identifier types currently accepted. Email is always
     * present; mobile only when the admin has enabled it.
     *
     * @return array<int,string>
     */
    public static function allowedTypes(): array
    {
        return self::mobileLoginEnabled() ? ['email', 'mobile'] : ['email'];
    }

    public static function typeAllowed(string $type): bool
    {
        return in_array($type, self::allowedTypes(), true);
    }

    /**
     * Allowed international dialling codes (e.g. ['+91', '+1']).
     *
     * @return array<int,string>
     */
    public static function allowedCountryCodes(): array
    {
        $stored = AppSetting::get(self::SETTING_ALLOWED_CODES, self::DEFAULT_ALLOWED_CODES);
        $codes = self::normalizeCodes(is_array($stored) ? $stored : []);
        return $codes;
    }

    /**
     * Does this phone number fall under one of the allowed dialling codes?
     * Compares on digits only so "+91 98…", "+9198…" and "9198…" all match
     * the "+91" code.
     */
    public static function isAllowedMobile(string $number): bool
    {
        $digits = preg_replace('/\D+/', '', $number) ?? '';
        if ($digits === '') {
            return false;
        }
        foreach (self::allowedCountryCodes() as $code) {
            $codeDigits = preg_replace('/\D+/', '', $code) ?? '';
            if ($codeDigits !== '' && str_starts_with($digits, $codeDigits)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Clean an arbitrary list of dialling codes into the canonical
     * "+<digits>" form, de-duplicated and order-preserving. Anything that
     * has no digits is dropped.
     *
     * @param  array<int,mixed>  $codes
     * @return array<int,string>
     */
    public static function normalizeCodes(array $codes): array
    {
        $out = [];
        foreach ($codes as $code) {
            $digits = preg_replace('/\D+/', '', (string) $code) ?? '';
            if ($digits === '') {
                continue;
            }
            $canonical = '+' . $digits;
            if (!in_array($canonical, $out, true)) {
                $out[] = $canonical;
            }
        }
        return $out;
    }

    /** Human-readable "+91, +1" string for prompts and error messages. */
    public static function allowedCountryCodesLabel(): string
    {
        return implode(', ', self::allowedCountryCodes());
    }
}
