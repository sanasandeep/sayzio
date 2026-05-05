<?php

namespace App\Modules\Common\Services;

/**
 * SSRF guard for server-side outbound HTTP fetches (Task #1211).
 *
 * Used by WatermarkController and CreatorOgImageController, both of
 * which dereference URLs that ultimately come from creator-supplied
 * post media. Without these checks an attacker could craft a post
 * with media[].url pointing at e.g. 169.254.169.254 (cloud metadata),
 * 127.0.0.1, or a private RFC1918 address and have our server fetch
 * it — turning the watermark proxy into an internal-network probe.
 *
 * Policy:
 *  - scheme MUST be http or https
 *  - host MUST resolve to at least one address, and EVERY resolved
 *    address must be public (no loopback / private / link-local /
 *    reserved / multicast)
 *  - we never follow redirects in the calling Http client (see the
 *    `allow_redirects => false` option) so a 30x to a private IP
 *    can't bypass this check
 */
class HttpFetchGuard
{
    public static function isSafeRemoteUrl(string $url): bool
    {
        $parts = @parse_url($url);
        if (!is_array($parts)) return false;

        $scheme = strtolower($parts['scheme'] ?? '');
        if ($scheme !== 'http' && $scheme !== 'https') return false;

        $host = $parts['host'] ?? '';
        if ($host === '') return false;

        // Resolve host to all A/AAAA records and reject if any is unsafe.
        // gethostbynamel returns an array of IPv4s or false on failure.
        $ips = @gethostbynamel($host);
        if (!is_array($ips) || empty($ips)) {
            // If the host is already an IP literal, validate it directly.
            if (filter_var($host, FILTER_VALIDATE_IP)) {
                return self::isPublicIp($host);
            }
            return false;
        }

        foreach ($ips as $ip) {
            if (!self::isPublicIp($ip)) return false;
        }
        return true;
    }

    public static function isPublicIp(string $ip): bool
    {
        // FILTER_FLAG_NO_PRIV_RANGE rejects RFC1918 (10/8, 172.16/12,
        // 192.168/16) and FILTER_FLAG_NO_RES_RANGE rejects loopback,
        // link-local (169.254/16 incl. cloud metadata), broadcast,
        // documentation and reserved blocks.
        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }
}
