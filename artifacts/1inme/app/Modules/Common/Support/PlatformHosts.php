<?php

namespace App\Modules\Common\Support;

use App\Modules\User\Models\Domain;

class PlatformHosts
{
    /**
     * Canonical brand platform domains, always recognised as "the platform"
     * regardless of what APP_URL / Replit env happen to advertise. These are
     * the public hosts the product is reachable on. `sayzio.app` is the
     * current primary; `1in.me` is kept as a fully-working selectable domain.
     *
     * @var array<int,string>
     */
    public const PLATFORM_DOMAINS = ['sayzio.app', '1in.me'];

    /** @var array<string,string>|null cached parent-process env */
    private static ?array $parentEnvCache = null;

    /**
     * Read the parent process's env via /proc/<PPID>/environ. PHP's
     * built-in dev server (`php artisan serve`) forks worker processes
     * that do NOT inherit the master's environment, so vars exported
     * by the Replit runtime (REPLIT_DOMAINS, REPLIT_DEV_DOMAIN) are
     * invisible to env() / $_SERVER / $_ENV / getenv() inside requests.
     * The master (parent) process still has them, so we read its environ
     * file directly as a last resort. Cached per-request.
     *
     * @return array<string,string>
     */
    private static function parentEnv(): array
    {
        if (self::$parentEnvCache !== null) return self::$parentEnvCache;
        $env = [];
        try {
            $ppid = function_exists('posix_getppid') ? posix_getppid() : null;
            if ($ppid && is_readable("/proc/{$ppid}/environ")) {
                $raw = @file_get_contents("/proc/{$ppid}/environ");
                if (is_string($raw) && $raw !== '') {
                    foreach (explode("\0", $raw) as $line) {
                        if ($line === '' || !str_contains($line, '=')) continue;
                        [$k, $v] = explode('=', $line, 2);
                        $env[$k] = $v;
                    }
                }
            }
        } catch (\Throwable) {
            // ignore — fallback returns empty array
        }
        return self::$parentEnvCache = $env;
    }

    /**
     * Robust env reader. Tries Laravel's env() first (which reads .env
     * via the Dotenv repository), then $_SERVER / $_ENV / getenv() for
     * normally-inherited process vars, and finally falls back to the
     * parent process's /proc/<PPID>/environ — required because the
     * `php artisan serve` worker processes do not inherit env vars
     * from their master.
     */
    private static function readEnv(string $key, string $default = ''): string
    {
        $val = env($key);
        if ($val !== null && $val !== '') return (string) $val;
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') return (string) $_SERVER[$key];
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') return (string) $_ENV[$key];
        $g = getenv($key);
        if ($g !== false && $g !== '') return (string) $g;
        $parent = self::parentEnv();
        if (isset($parent[$key]) && $parent[$key] !== '') return $parent[$key];
        return $default;
    }

    /**
     * Normalise a host for comparison: lowercase, strip port, trim.
     * Returns null when there's nothing usable.
     */
    public static function normalize(?string $host): ?string
    {
        if ($host === null) return null;
        $host = trim($host);
        if ($host === '') return null;
        if (str_contains($host, ':')) {
            $host = explode(':', $host, 2)[0];
        }
        return strtolower($host);
    }

    /**
     * The set of hosts that should be treated as "the platform" — i.e. NOT
     * a custom domain. Includes the host parsed from APP_URL plus anything
     * Replit advertises via env (REPLIT_DEV_DOMAIN, REPLIT_DOMAINS).
     *
     * @return array<int,string> normalised hostnames
     */
    public static function configured(): array
    {
        $hosts = [];
        $appHost = self::normalize(parse_url((string) config('app.url'), PHP_URL_HOST) ?: null);
        if ($appHost) $hosts[] = $appHost;

        $devDomain = self::normalize(self::readEnv('REPLIT_DEV_DOMAIN'));
        if ($devDomain) $hosts[] = $devDomain;

        $deployedDomains = self::readEnv('REPLIT_DOMAINS', '');
        if ($deployedDomains !== '') {
            foreach (explode(',', $deployedDomains) as $d) {
                $n = self::normalize($d);
                if ($n) $hosts[] = $n;
            }
        }

        return array_values(array_unique($hosts));
    }

    /**
     * The canonical brand platform domains (PLATFORM_DOMAINS), normalised.
     *
     * @return array<int,string>
     */
    public static function brandDomains(): array
    {
        $out = [];
        foreach (self::PLATFORM_DOMAINS as $d) {
            $n = self::normalize($d);
            if ($n) $out[] = $n;
        }
        return array_values(array_unique($out));
    }

    /**
     * The canonical primary brand domain — the public host the product
     * should consolidate on (currently sayzio.app). This is the first
     * entry of PLATFORM_DOMAINS, normalised. Returns null only if no
     * brand domains are configured.
     */
    public static function primaryBrandDomain(): ?string
    {
        $brands = self::brandDomains();
        return $brands[0] ?? null;
    }

    /**
     * True when $host is a recognised brand domain that is NOT the canonical
     * primary brand domain (e.g. the short-link domain 1in.me while sayzio.app
     * is primary). Dev/preview hosts (Replit dev domain, localhost) and the
     * primary brand domain itself return false, so callers can safely use this
     * to gate a "consolidate onto the primary brand" redirect.
     */
    public static function isNonPrimaryBrandDomain(?string $host): bool
    {
        $normalized = self::normalize($host);
        if ($normalized === null) return false;
        $primary = self::primaryBrandDomain();
        if ($primary !== null && $normalized === $primary) return false;
        return in_array($normalized, self::brandDomains(), true);
    }

    /**
     * Every host that should be treated as "the platform": the canonical
     * brand domains (sayzio.app, 1in.me) plus whatever APP_URL / Replit env
     * advertise via configured(). Brand domains come first so the platform's
     * primary host is preferred by any "first match" caller.
     *
     * @return array<int,string>
     */
    public static function platformDomains(): array
    {
        return array_values(array_unique(array_merge(self::brandDomains(), self::configured())));
    }

    /**
     * The normalised current request host, only when it is one of the
     * configured platform hosts. Returns null on CLI, when no request
     * is bound, or when the request is on a custom/unknown host.
     *
     * Useful as the default for "copy short link" buttons so a creator
     * editing on the Replit dev domain copies a URL that uses that
     * same host (rather than always APP_URL).
     *
     * Loopback hosts (localhost / 127.0.0.1 / 0.0.0.0) are intentionally
     * skipped — they appear in dev when the public Replit proxy forwards
     * to the app on localhost, but they are never useful as a copyable
     * short-link prefix for the creator.
     */
    public static function currentRequestHost(): ?string
    {
        try {
            $host = self::normalize(request()->getHost());
        } catch (\Throwable) {
            return null;
        }
        if ($host === null) return null;
        if (self::isLoopback($host)) return null;
        return in_array($host, self::configured(), true) ? $host : null;
    }

    /**
     * The best host to *display* to the creator as their primary
     * platform short-link prefix. Always prefers a real public domain
     * (Replit deployment domains, then the dev preview, then APP_URL)
     * over loopback hosts so the alias card never shows "localhost/".
     */
    public static function primary(): string
    {
        // Prefer a canonical brand domain (sayzio.app, then 1in.me) whenever it
        // is actually one of the hosts serving this deployment — i.e. the
        // deployment's custom domain is the brand domain. This makes the
        // canonical short-link prefix the brand primary in production, while
        // dev/preview (where no brand domain is served) keeps using the Replit
        // dev host so the preview iframe and "copy link" stay on-host.
        $serving = self::configured();
        foreach (self::brandDomains() as $brand) {
            if (in_array($brand, $serving, true)) {
                return $brand;
            }
        }

        $deployed = self::readEnv('REPLIT_DOMAINS', '');
        if ($deployed !== '') {
            foreach (explode(',', $deployed) as $d) {
                $n = self::normalize($d);
                if ($n && !self::isLoopback($n)) return $n;
            }
        }

        $dev = self::normalize(self::readEnv('REPLIT_DEV_DOMAIN'));
        if ($dev && !self::isLoopback($dev)) return $dev;

        $app = self::normalize(parse_url((string) config('app.url'), PHP_URL_HOST) ?: null);
        if ($app && !self::isLoopback($app)) return $app;

        // Last resort: any configured host, even loopback, then raw APP_URL.
        foreach (self::configured() as $h) {
            if (!self::isLoopback($h)) return $h;
        }
        return $app ?? 'localhost';
    }

    /**
     * The canonical URL for the current request: scheme + path + query, but
     * with the host normalised to the primary brand domain whenever the
     * request came in on a *recognised brand domain* (primary or
     * non-primary). Dev/preview hosts and custom user domains are left
     * exactly as-is (`request()->url()`/`fullUrl()` equivalent), since only
     * the two branded marketing hosts (sayzio.app / 1in.me) are meant to
     * consolidate onto a single canonical.
     *
     * Used by shared marketing partials (canonical link, og:url, JSON-LD
     * `url`) so every brand-domain request — whichever host actually served
     * it — always advertises the same preferred URL to crawlers and social
     * platforms, even on routes that don't 301-redirect.
     */
    public static function canonicalUrl(): string
    {
        try {
            $host = self::normalize(request()->getHost());
        } catch (\Throwable) {
            return request()->fullUrl();
        }

        $primary = self::primaryBrandDomain();
        if ($primary === null || $host === null || !in_array($host, self::brandDomains(), true)) {
            return request()->fullUrl();
        }

        $uri = request()->getRequestUri(); // includes leading "/" + query string
        return request()->getScheme() . '://' . $primary . $uri;
    }

    /**
     * Normalise a generated absolute URL for outbound use (emails, in-app /
     * push notifications, digests) — anything built from CLI/queue context
     * where there is no request host to anchor on. Whenever the URL's host
     * is a recognised *non-primary* brand domain (e.g. `1in.me` while
     * `sayzio.app` is primary — typically because production APP_URL still
     * points at the legacy domain), the host is rewritten to the primary
     * brand domain and the scheme forced to https.
     *
     * Dev/preview hosts (Replit dev domain, localhost) and custom user
     * domains are left untouched so local previews and per-user branding
     * keep working. Relative URLs and unparseable strings pass through
     * unchanged. This never affects inbound alias resolution — existing
     * `1in.me` short links keep resolving; only *generated* links move to
     * the primary brand host.
     */
    public static function outboundUrl(string $url): string
    {
        if ($url === '') return $url;

        $primary = self::primaryBrandDomain();
        if ($primary === null) return $url;

        $host = self::normalize(parse_url($url, PHP_URL_HOST) ?: null);
        if ($host === null || !self::isNonPrimaryBrandDomain($host)) {
            return $url;
        }

        $parts = parse_url($url);
        if (!is_array($parts)) return $url;

        $rebuilt = 'https://' . $primary;
        $rebuilt .= $parts['path'] ?? '';
        if (isset($parts['query']) && $parts['query'] !== '') {
            $rebuilt .= '?' . $parts['query'];
        }
        if (isset($parts['fragment']) && $parts['fragment'] !== '') {
            $rebuilt .= '#' . $parts['fragment'];
        }
        return $rebuilt;
    }

    private static function isLoopback(string $host): bool
    {
        return in_array($host, ['localhost', '127.0.0.1', '0.0.0.0', '::1'], true);
    }

    /**
     * Hosts other than $primary that are also serving this platform.
     * Caller passes whichever host they're already displaying so we
     * don't repeat it.
     *
     * @return array<int,string>
     */
    public static function others(?string $primary): array
    {
        $primary = self::normalize($primary);
        return array_values(array_filter(self::platformDomains(), fn ($h) => $h !== $primary));
    }

    /**
     * True when the host should be treated as the platform for this
     * request. A host counts as platform when it is:
     *   - null/empty (CLI / synthetic requests), OR
     *   - explicitly configured via APP_URL / REPLIT_DEV_DOMAIN /
     *     REPLIT_DOMAINS, OR
     *   - the current request host that is NOT a verified+active row in
     *     the `domains` table.
     *
     * In other words, the only host that is NOT a platform host is one
     * that has been registered, verified, and activated as a custom
     * domain. That host is handled separately by the caller (alias
     * lookup is scoped to its domain_id).
     *
     * Hosts that exist in the `domains` table but aren't yet verified
     * and active are still considered platform here; the caller decides
     * whether to surface the "Domain not connected" notice for them.
     */
    public static function isPlatformHost(?string $host): bool
    {
        $normalized = self::normalize($host);
        if ($normalized === null) return true;
        // Canonical brand domains + env-configured hosts are always platform,
        // even when a `domains` row also exists for them (the platform's own
        // global domains are stored there too, but resolve as platform).
        if (in_array($normalized, self::platformDomains(), true)) return true;
        return !Domain::where('domain', $normalized)
            ->where('is_active', true)
            ->where('is_verified', true)
            ->exists();
    }

    /**
     * True when the host has a row in `domains` that is NOT yet verified
     * and active. Used by the redirect controller to keep the "Domain
     * not connected" notice working for users who started attaching a
     * custom domain but haven't finished CNAME verification.
     */
    public static function isPendingCustomDomain(?string $host): bool
    {
        $normalized = self::normalize($host);
        if ($normalized === null) return false;
        // A canonical platform host is never a "pending custom domain", even
        // if its global `domains` row is briefly unverified during rollout —
        // it must keep resolving as platform, not show "Domain not connected".
        if (in_array($normalized, self::platformDomains(), true)) return false;
        return Domain::where('domain', $normalized)
            ->where(function ($q) {
                $q->where('is_active', false)->orWhere('is_verified', false);
            })
            ->exists();
    }
}
