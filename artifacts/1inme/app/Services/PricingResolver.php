<?php

namespace App\Services;

use App\Modules\Admin\Models\Addon;
use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Models\Price;
use App\Modules\User\Models\User;

/**
 * Resolves the right price + currency for a plan/addon based on the
 * user's billing country. Falls back through:
 *   1. The polymorphic `prices` row matching (currency, cycle).
 *   2. The legacy `monthly_price` / `annual_price` (USD) and
 *      `*_secondary` (INR) decimal columns on the model itself.
 *   3. Zero, in USD, as a last-ditch safety net so views never blow up.
 *
 * Currency selection rules:
 *   - Logged-in user with `country` set → look up
 *     `country_currency` config; default USD.
 *   - Logged-in user with no country → respect the
 *     `billing_currency` session flag if present; default USD.
 *   - Anonymous → respect the `billing_currency` session flag; default USD.
 *
 * No FX conversion is ever done — admins set INR and USD independently.
 */
class PricingResolver
{
    public const SESSION_KEY = 'billing_currency';

    /** Country → currency lookup. Defaults to USD when unmapped. */
    public static function currencyForCountry(?string $countryCode): string
    {
        if (!$countryCode) return 'USD';
        $cc = strtoupper($countryCode);
        $map = config('country_currency', []);
        return $map[$cc] ?? 'USD';
    }

    /** Resolve the currency that applies to the given user (or anonymous). */
    public static function currencyForUser(?User $user): string
    {
        if ($user && !empty($user->country)) {
            return self::currencyForCountry($user->country);
        }
        $session = session(self::SESSION_KEY);
        if (is_string($session) && in_array($session, ['USD', 'INR'], true)) {
            return $session;
        }
        return 'USD';
    }

    /**
     * Returns ['amount_minor' => int, 'currency' => string, 'formatted' => string].
     */
    public static function priceFor($priceable, ?User $user, string $cycle = 'monthly'): array
    {
        $cycle = $cycle === 'annual' ? 'annual' : 'monthly';
        $currency = self::currencyForUser($user);

        $minor = self::lookupMinor($priceable, $currency, $cycle);

        // If the requested currency has no row AND no legacy column, fall
        // back to USD so we never render an empty price.
        if ($minor === null && $currency !== 'USD') {
            $currency = 'USD';
            $minor = self::lookupMinor($priceable, 'USD', $cycle);
        }
        $minor = $minor ?? 0;

        return [
            'amount_minor' => $minor,
            'currency'     => $currency,
            'formatted'    => self::money($minor, $currency),
        ];
    }

    private static function lookupMinor($priceable, string $currency, string $cycle): ?int
    {
        if (!$priceable) return null;

        // 1) Authoritative source: polymorphic prices table. If the
        // `prices` relation is already eager-loaded (e.g. the upgrade page
        // does `with('prices')` to avoid N+1), filter that collection
        // in-memory; otherwise fall back to a single targeted query.
        if (method_exists($priceable, 'relationLoaded') && $priceable->relationLoaded('prices')) {
            $row = $priceable->prices->first(function ($p) use ($currency, $cycle) {
                return $p->currency === $currency
                    && $p->billing_cycle === $cycle
                    && (bool) $p->is_active;
            });
        } else {
            $row = Price::where('priceable_type', get_class($priceable))
                ->where('priceable_id', $priceable->getKey())
                ->where('currency', $currency)
                ->where('billing_cycle', $cycle)
                ->where('is_active', true)
                ->first();
        }
        if ($row) {
            return (int) $row->amount_minor_units;
        }

        // 2) Compatibility shim: the legacy decimal columns on Plan/Addon.
        $col = $cycle === 'annual' ? 'annual_price' : 'monthly_price';
        if ($currency === 'INR') {
            $secondary = $col . '_secondary';
            if (isset($priceable->{$secondary}) && $priceable->{$secondary} !== null) {
                return (int) round(((float) $priceable->{$secondary}) * 100);
            }
            // No INR shim available.
            return null;
        }
        if (isset($priceable->{$col}) && $priceable->{$col} !== null) {
            return (int) round(((float) $priceable->{$col}) * 100);
        }
        return null;
    }

    /**
     * Format a minor-unit amount as a display string. Public so views and
     * other code can format ad-hoc amounts without going through priceFor.
     */
    public static function money(int $minor, string $currency): string
    {
        $major = $minor / 100;
        $symbol = match ($currency) {
            'INR'   => '₹',
            'USD'   => '$',
            default => $currency . ' ',
        };
        return $symbol . number_format($major, 2);
    }

    /**
     * Convenience for upsert from admin forms (where input is in major units).
     */
    public static function upsertFromMajor($priceable, string $currency, string $cycle, $majorOrNull): void
    {
        if ($majorOrNull === null || $majorOrNull === '') {
            // Allow admins to clear an INR price by leaving it blank.
            Price::where('priceable_type', get_class($priceable))
                ->where('priceable_id', $priceable->getKey())
                ->where('currency', $currency)
                ->where('billing_cycle', $cycle)
                ->delete();
            return;
        }
        $minor = (int) round(((float) $majorOrNull) * 100);
        Price::updateOrCreate(
            [
                'priceable_type' => get_class($priceable),
                'priceable_id'   => $priceable->getKey(),
                'currency'       => $currency,
                'billing_cycle'  => $cycle,
            ],
            [
                'amount_minor_units' => max(0, $minor),
                'is_active'          => true,
            ]
        );
    }
}
