<?php

namespace App\Modules\Common\Support;

use App\Modules\Admin\Models\AppSetting;

/**
 * Single source of truth for the login-method policy.
 *
 * By default 1INME accepts email as the only login / account-recovery
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

    /** Seeded defaults when an admin has never saved the settings. */
    public const DEFAULT_ALLOWED_CODES = ['+91', '+1'];

    /** Is WhatsApp (mobile) login switched on by an admin? */
    public static function mobileLoginEnabled(): bool
    {
        return (bool) AppSetting::get(self::SETTING_MOBILE_ENABLED, false);
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
