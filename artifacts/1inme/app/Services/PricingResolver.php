<?php

namespace App\Services;

use App\Modules\Admin\Models\Addon;
use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Models\Price;
use App\Modules\Common\Services\GeoIpService;
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
 *     `billing_currency` session flag if present; otherwise fall back
 *     to the geo-IP default (INR for India, USD elsewhere).
 *   - Anonymous → respect the `billing_currency` session flag; otherwise
 *     fall back to the geo-IP default (INR for India, USD elsewhere).
 *
 * The geo-derived default is cached on the session under
 * `billing_currency_geo` so we don't re-hit the geo-IP service on
 * every request. The manual switcher (which writes
 * `billing_currency`) and an explicit user country still win over it.
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
    public const SESSION_KEY_GEO = 'billing_currency_geo';

    /**
     * How long a cached geo-derived currency stays valid before we
     * re-check, even if the request IP hasn't visibly changed. Bounds
     * the staleness window for cases where the cached IP isn't
     * available (e.g. legacy string-shaped cache values).
     */
    public const GEO_CACHE_TTL_SECONDS = 86400; // 24h

    /** Country → currency lookup. Defaults to USD when unmapped. */
    public static function currencyForCountry(?string $countryCode): string
    {
        if (!$countryCode) return 'USD';
        $cc = strtoupper($countryCode);
        $map = config('country_currency', []);
        return $map[$cc] ?? 'USD';
    }

    /**
     * True when the active currency was chosen by geo-IP fallback rather
     * than by the user's profile country or an explicit session switch.
     * Used by the pricing page to show a small "based on your location"
     * hint so roaming visitors know they can flip back.
     */
    public static function wasPickedByGeo(?User $user): bool
    {
        if ($user && !empty($user->country)) return false;
        try {
            $session = session(self::SESSION_KEY);
        } catch (\Throwable $e) {
            return false;
        }
        if (is_string($session) && in_array($session, ['USD', 'INR'], true)) return false;
        return true;
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
        return self::geoDefaultCurrency();
    }

    /**
     * Geo-IP-derived default currency for the current request.
     *
     * Resolution order:
     *   1. If we already cached a geo currency on this session AND the
     *      cached entry is still fresh (request IP matches the IP we
     *      cached against, and the entry is younger than
     *      GEO_CACHE_TTL_SECONDS), reuse it. This bounds staleness so
     *      visitors who flip networks (mobile data ↔ Wi-Fi, VPN on/off)
     *      get re-evaluated instead of being stuck on the original
     *      currency for the life of the session.
     *   2. Otherwise ask GeoIpService for the country of the current
     *      request's client IP. The framework's trusted-proxy setup
     *      means $request->ip() returns the real client IP (X-Forwarded-For)
     *      behind the load balancer.
     *   3. Map country → currency (IN → INR, everything else → USD).
     *   4. Cache the result (with the IP and timestamp it was derived
     *      from) on the session and return it.
     *
     * Private/loopback IPs and any geo-IP failure (timeout, exception,
     * unmapped country) all gracefully fall back to USD without
     * throwing — this keeps local dev and offline test environments
     * working with no extra setup.
     *
     * Returns USD when no HTTP request is bound (CLI / queue worker
     * context), since there's nothing to geolocate.
     */
    private static function geoDefaultCurrency(): string
    {
        $currentIp = null;
        try {
            if (app()->bound('request')) {
                $rip = request()->ip();
                if (is_string($rip) && $rip !== '') {
                    $currentIp = $rip;
                }
            }
        } catch (\Throwable $e) {
            $currentIp = null;
        }

        try {
            $cached = session(self::SESSION_KEY_GEO);
            if (self::cachedGeoStillValid($cached, $currentIp)) {
                return is_array($cached) ? $cached['currency'] : $cached;
            }
        } catch (\Throwable $e) {
            // No session bound (CLI) — skip cache and fall through to USD.
            return 'USD';
        }

        $currency = 'USD';
        try {
            if ($currentIp !== null) {
                $cc = app(GeoIpService::class)->detectCountry($currentIp);
                $currency = self::currencyForCountry($cc);
            }
        } catch (\Throwable $e) {
            $currency = 'USD';
        }

        try {
            session([self::SESSION_KEY_GEO => [
                'currency' => $currency,
                'ip'       => $currentIp,
                'at'       => time(),
            ]]);
        } catch (\Throwable $e) {
            // best-effort cache only
        }
        return $currency;
    }

    /**
     * True iff a session-cached geo entry can still be reused for the
     * current request. Accepts both the new array shape (with `ip` and
     * `at`) and the legacy string shape (currency only) — legacy
     * entries are considered fresh only if they're inside the TTL,
     * which we can't know without a timestamp, so they're treated as
     * stale and re-evaluated on first read after deploy.
     */
    private static function cachedGeoStillValid($cached, ?string $currentIp): bool
    {
        if (!is_array($cached)) {
            return false;
        }
        $cur = $cached['currency'] ?? null;
        if (!is_string($cur) || !in_array($cur, ['USD', 'INR'], true)) {
            return false;
        }
        $cachedIp = $cached['ip'] ?? null;
        if ($currentIp !== null && is_string($cachedIp) && $cachedIp !== $currentIp) {
            return false;
        }
        $at = $cached['at'] ?? null;
        if (!is_int($at) || (time() - $at) > self::GEO_CACHE_TTL_SECONDS) {
            return false;
        }
        return true;
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

    /**
     * Currency-locked lookup. Used by lifecycle paths (proration,
     * recurring charges) that must NOT re-derive the currency from the
     * user's current country/session — once a subscription is created
     * its currency is locked for the life of the subscription and only
     * changes at a new-subscription boundary.
     */
    public static function priceForCurrency($priceable, string $currency, string $cycle = 'monthly'): array
    {
        $currency = in_array($currency, ['USD', 'INR'], true) ? $currency : 'USD';
        return self::buildPrice($priceable, $currency, $cycle);
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
