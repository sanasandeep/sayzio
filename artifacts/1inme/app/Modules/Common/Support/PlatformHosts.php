<?php

namespace App\Modules\Common\Support;

use App\Modules\User\Models\Domain;

class PlatformHosts
{
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

        $devDomain = self::normalize(env('REPLIT_DEV_DOMAIN'));
        if ($devDomain) $hosts[] = $devDomain;

        $deployedDomains = (string) env('REPLIT_DOMAINS', '');
        if ($deployedDomains !== '') {
            foreach (explode(',', $deployedDomains) as $d) {
                $n = self::normalize($d);
                if ($n) $hosts[] = $n;
            }
        }

        return array_values(array_unique($hosts));
    }

    /**
     * The normalised current request host, only when it is one of the
     * configured platform hosts. Returns null on CLI, when no request
     * is bound, or when the request is on a custom/unknown host.
     *
     * Useful as the default for "copy short link" buttons so a creator
     * editing on the Replit dev domain copies a URL that uses that
     * same host (rather than always APP_URL).
     */
    public static function currentRequestHost(): ?string
    {
        try {
            $host = self::normalize(request()->getHost());
        } catch (\Throwable) {
            return null;
        }
        if ($host === null) return null;
        return in_array($host, self::configured(), true) ? $host : null;
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
        return array_values(array_filter(self::configured(), fn ($h) => $h !== $primary));
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
        if (in_array($normalized, self::configured(), true)) return true;
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
        return Domain::where('domain', $normalized)
            ->where(function ($q) {
                $q->where('is_active', false)->orWhere('is_verified', false);
            })
            ->exists();
    }
}
