<?php

namespace App\Services;

use App\Modules\Admin\Models\Addon;
use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Models\Price;
use App\Modules\User\Models\User;

/**
 * Resolves the right price + currency for a plan/addon based on the
 * user's billing country.
 *
 * Authoritative source: the polymorphic `prices` table (one row per
 * priceable + currency + cycle, amount stored in MINOR units —
 * cents/paise). The legacy `monthly_price` / `annual_price` (USD) and
 * `*_secondary` (INR) decimal columns on the model are still written
 * in major units for legacy compatibility but are NEVER read here.
 *
 * Currency selection rules:
 *   - Logged-in user with `country` set → look up
 *     `country_currency` config; default USD.
 *   - Logged-in user with no country → respect the
 *     `billing_currency` session flag if present; default USD.
 *   - Anonymous → respect the `billing_currency` session flag; default USD.
 *
 * No FX conversion is ever done — admins set INR and USD independently.
 *
 * Read-path semantics: if the requested (currency, cycle) row is
 * missing, this returns zero IN THAT SAME CURRENCY rather than
 * silently falling back to USD. That makes missing prices visible
 * (₹0.00 / $0.00) instead of masking them and is consistent with the
 * "explicit per-currency pricing" requirement.
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
        return self::buildPrice($priceable, self::currencyForUser($user), $cycle);
    }

    /**
     * Country-code overload of `priceFor()` — useful for admin previews,
     * cron jobs, and any caller that has a country but not a User
     * instance. Resolves country → currency, then renders.
     */
    public static function priceForCountry($priceable, ?string $countryCode, string $cycle = 'monthly'): array
    {
        return self::buildPrice($priceable, self::currencyForCountry($countryCode), $cycle);
    }

    private static function buildPrice($priceable, string $currency, string $cycle): array
    {
        $cycle = $cycle === 'annual' ? 'annual' : 'monthly';
        // Explicit, no silent USD fallback: missing INR row → ₹0.00.
        $minor = self::lookupMinor($priceable, $currency, $cycle) ?? 0;
        return [
            'amount_minor' => $minor,
            'currency'     => $currency,
            'formatted'    => self::money($minor, $currency),
        ];
    }

    private static function lookupMinor($priceable, string $currency, string $cycle): ?int
    {
        if (!$priceable) return null;

        // Authoritative source: the polymorphic prices table. If the
        // `prices` relation is already eager-loaded (e.g. UpgradeController
        // does `with('prices')` to avoid N+1), filter that collection
        // in-memory; otherwise issue a single targeted query.
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
        return $row ? (int) $row->amount_minor_units : null;
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
     * Upsert from the admin form, which now submits MINOR units directly
     * (cents/paise) per the task's pricing contract. Always creates the
     * row — admin validation requires all four (USD/INR × monthly/annual)
     * to be present, so blank-means-delete is intentionally not supported.
     */
    public static function upsertFromMinor($priceable, string $currency, string $cycle, int $minor): void
    {
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
