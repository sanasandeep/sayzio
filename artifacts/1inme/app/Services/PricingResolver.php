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
 *   - Logged-in user with no country → respect their persisted
 *     `preferred_currency` (set by the manual switcher and stored on
 *     the user record so it follows them across devices); otherwise
 *     fall through to the rules below.
 *   - Anonymous (or signed-in user with no country and no persisted
 *     preference) → respect the `billing_currency` session flag if
 *     present; otherwise the long-lived signed `billing_currency_pref`
 *     cookie (so the choice survives session expiry); otherwise fall
 *     back to the geo-IP default (INR for India, USD elsewhere).
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
     * Long-lived signed cookie that mirrors the manual override for
     * anonymous (and not-yet-signed-in) visitors so their choice
     * survives session expiry / cookie wipes shorter than this TTL.
     * Signed-in users with no profile country also get the choice
     * mirrored onto `users.preferred_currency` so it follows them
     * across devices — see `rememberManualChoice()`.
     */
    public const COOKIE_KEY = 'billing_currency_pref';
    public const COOKIE_DAYS = 365;

    /**
     * Where the currency the visitor is currently seeing came from.
     * Used by pricing views to render the right badge ("Auto-detected
     * from your location" vs "You selected this" vs "Based on your
     * billing country") so the auto-pick is transparent.
     */
    public const SOURCE_USER_COUNTRY = 'user_country';
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_GEO = 'geo';

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
     * Where did the active currency come from? Mirrors the precedence
     * in `currencyForUser()`:
     *   - SOURCE_USER_COUNTRY: signed-in user has a profile country.
     *   - SOURCE_MANUAL: visitor explicitly clicked the switcher
     *     (session `billing_currency` is set).
     *   - SOURCE_GEO: derived (or fell back to USD) from the request's
     *     geo-IP. Anything not covered above lands here so views can
     *     surface the "Auto-detected from your location" hint.
     */
    public static function currencySourceForUser(?User $user): string
    {
        if ($user && !empty($user->country)) {
            return self::SOURCE_USER_COUNTRY;
        }
        if ($user && self::isValidCurrency($user->preferred_currency ?? null)) {
            return self::SOURCE_MANUAL;
        }
        try {
            $session = session(self::SESSION_KEY);
        } catch (\Throwable $e) {
            $session = null;
        }
        if (self::isValidCurrency($session)) {
            return self::SOURCE_MANUAL;
        }
        if (self::isValidCurrency(self::cookieOverride())) {
            return self::SOURCE_MANUAL;
        }
        return self::SOURCE_GEO;
    }

    private static function isValidCurrency($value): bool
    {
        return is_string($value) && in_array($value, ['USD', 'INR'], true);
    }

    /**
     * Read the long-lived signed override cookie off the current
     * request, if any. Returns null when there's no bound request
     * (CLI / queue), no cookie set, or the value isn't a supported
     * currency code.
     */
    private static function cookieOverride(): ?string
    {
        try {
            if (!app()->bound('request')) {
                return null;
            }
            $val = request()->cookie(self::COOKIE_KEY);
        } catch (\Throwable $e) {
            return null;
        }
        return self::isValidCurrency($val) ? $val : null;
    }

    /**
     * Back-compat shim for callers that only need to know whether the
     * currency was auto-picked by geo-IP fallback. New code should use
     * `currencySourceForUser()` directly.
     */
    public static function wasPickedByGeo(?User $user): bool
    {
        return self::currencySourceForUser($user) === self::SOURCE_GEO;
    }

    /**
     * Country code (ISO 3166-1 alpha-2) the geo-IP resolver derived
     * the auto-picked currency from, or null when geo lookup failed
     * / no cache entry exists / a non-geo source decided the currency.
     *
     * Reads from the same session-scoped cache `geoDefaultCurrency()`
     * populates so this is a free piggyback — no extra GeoIP call.
     * Always run AFTER currency resolution on the same request so the
     * cache is guaranteed to be primed.
     */
    public static function geoDetectedCountryCode(): ?string
    {
        try {
            $cached = session(self::SESSION_KEY_GEO);
        } catch (\Throwable $e) {
            return null;
        }
        if (!is_array($cached)) {
            return null;
        }
        $cc = $cached['country'] ?? null;
        return is_string($cc) && $cc !== '' ? strtoupper($cc) : null;
    }

    /**
     * City name the geo-IP resolver associated with the auto-picked
     * currency, or null when geo lookup didn't return a city / no
     * cache entry exists / a non-geo source decided the currency.
     *
     * Reads from the same session-scoped cache `geoDefaultCurrency()`
     * populates so this is a free piggyback — no extra GeoIP call.
     * Always run AFTER currency resolution on the same request so the
     * cache is guaranteed to be primed.
     */
    public static function geoDetectedCity(): ?string
    {
        try {
            $cached = session(self::SESSION_KEY_GEO);
        } catch (\Throwable $e) {
            return null;
        }
        if (!is_array($cached)) {
            return null;
        }
        $city = $cached['city'] ?? null;
        return is_string($city) && $city !== '' ? $city : null;
    }

    /**
     * Human-readable country name for the geo-detected country, e.g.
     * "United States" for "US". Uses the intl extension's locale data
     * (always available — verified at boot). Returns null when there's
     * no cached country code or the code can't be resolved to a name,
     * so callers can hide the country hint gracefully.
     */
    public static function geoDetectedCountryName(): ?string
    {
        $cc = self::geoDetectedCountryCode();
        if (!$cc) {
            return null;
        }
        if (class_exists(\Locale::class)) {
            // `getDisplayRegion` needs a locale-shaped tag; "-XX" is the
            // canonical way to ask for just the region's display name.
            $name = \Locale::getDisplayRegion('-' . $cc, 'en');
            if (is_string($name) && $name !== '' && strtoupper($name) !== strtoupper($cc)) {
                return $name;
            }
        }
        return null;
    }

    /** Resolve the currency that applies to the given user (or anonymous). */
    public static function currencyForUser(?User $user): string
    {
        if ($user && !empty($user->country)) {
            return self::currencyForCountry($user->country);
        }
        if ($user && self::isValidCurrency($user->preferred_currency ?? null)) {
            return $user->preferred_currency;
        }
        try {
            $session = session(self::SESSION_KEY);
        } catch (\Throwable $e) {
            $session = null;
        }
        if (self::isValidCurrency($session)) {
            return $session;
        }
        $cookie = self::cookieOverride();
        if ($cookie !== null) {
            return $cookie;
        }
        return self::geoDefaultCurrency();
    }

    /**
     * Persist a manual currency choice across sessions. Always writes:
     *   - the per-request session flag (so the rest of the request and
     *     subsequent requests in the same session render correctly),
     *   - a long-lived signed cookie (so anonymous visitors keep their
     *     choice after their session expires).
     *
     * Additionally, when the visitor is signed in and has NOT set a
     * profile country, the choice is mirrored onto
     * `users.preferred_currency` so it follows them across devices.
     * Users with an explicit country don't get the column written —
     * country is the billing-of-record signal and must keep winning.
     *
     * Returns the long-lived cookie object so the caller can attach it
     * to the outgoing response (Laravel's Cookie::queue() also works).
     */
    public static function rememberManualChoice(string $currency, ?User $user = null): \Symfony\Component\HttpFoundation\Cookie
    {
        $currency = self::isValidCurrency($currency) ? $currency : 'USD';

        try {
            session([self::SESSION_KEY => $currency]);
        } catch (\Throwable $e) {
            // No session bound — best-effort only.
        }

        if ($user && empty($user->country)) {
            try {
                $user->forceFill(['preferred_currency' => $currency])->save();
            } catch (\Throwable $e) {
                // Don't block the user-facing switch on a DB hiccup.
            }
        }

        // `secure` is left null so Laravel falls back to
        // `config('session.secure')` (which is env-driven) instead of
        // hard-coding insecure transport.
        return cookie(
            self::COOKIE_KEY,
            $currency,
            self::COOKIE_DAYS * 24 * 60, // minutes
            '/',
            null,
            null,  // secure: defer to session config (env-driven)
            true   // httpOnly
        );
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
        $cc = null;
        $city = null;
        try {
            if ($currentIp !== null) {
                // Use detectGeo() so we capture both country and city
                // from the same underlying lookup — the GeoIpService
                // already caches by IP, so this is no extra cost.
                $geo = app(GeoIpService::class)->detectGeo($currentIp);
                $cc = $geo['country_code'] ?? null;
                $city = $geo['city'] ?? null;
                $currency = self::currencyForCountry($cc);
            }
        } catch (\Throwable $e) {
            $currency = 'USD';
            $cc = null;
            $city = null;
        }

        try {
            session([self::SESSION_KEY_GEO => [
                'currency' => $currency,
                'country'  => is_string($cc) && $cc !== '' ? strtoupper($cc) : null,
                'city'     => is_string($city) && $city !== '' ? $city : null,
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
