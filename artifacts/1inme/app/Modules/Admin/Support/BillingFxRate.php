<?php

namespace App\Modules\Admin\Support;

use App\Modules\Admin\Models\AppSetting;

/**
 * Admin-editable INR-per-USD exchange rate used to keep INR pricing in
 * sync with USD pricing without a code deploy.
 *
 * Stored in `app_settings` under a single key; falls back to the legacy
 * hardcoded ₹90/$1 rate when unset so fresh installs behave exactly as
 * before. Read by CoinPackagesSeeder when seeding INR prices and by the
 * admin coin-package form to show a computed INR hint.
 */
class BillingFxRate
{
    public const KEY = 'billing.fx_rate_inr';

    /** Legacy hardcoded rate (₹ per $1). */
    public const DEFAULT = 90.0;

    /**
     * Current INR-per-USD rate. Always a sane positive float — bad or
     * missing stored values fall back to the default.
     */
    public static function get(): float
    {
        $raw = AppSetting::get(self::KEY);
        $rate = is_numeric($raw) ? (float) $raw : null;

        return ($rate !== null && $rate > 0) ? $rate : self::DEFAULT;
    }

    /**
     * Persist a new rate. Caller is responsible for validation; this
     * guards against non-positive values as a final safety net.
     */
    public static function put(float $rate): void
    {
        if ($rate <= 0) {
            return;
        }
        AppSetting::put(self::KEY, $rate);
    }

    /**
     * Convert a USD minor-unit amount (cents) to INR minor units (paise)
     * at the current rate. Cents → paise is a straight multiply because
     * both currencies use 2 decimal places.
     */
    public static function usdMinorToInrMinor(int $usdMinor, ?float $rate = null): int
    {
        $rate ??= self::get();

        return (int) round($usdMinor * $rate);
    }
}
