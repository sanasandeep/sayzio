<?php

namespace App\Modules\Common\Services;

use App\Modules\User\Models\Link;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Per-biolink visitor throttling. Two sliding-window counters per link:
 *
 *   - per-IP                          (defaults: 30 hits / 60s)
 *   - per-fingerprint (IP + UA hash)  (defaults: 60 hits / 60s)
 *
 * Either limit being exceeded marks the request as throttled. Throttled
 * clicks are still recorded (with `is_throttled = true`) but excluded
 * from creator-facing analytics by the global LinkClick scope, exactly
 * like genuine bot traffic.
 *
 * A "JS challenge" cookie (`1inme_human=1`) is set client-side on every
 * biolink render. Visitors that arrive *without* the cookie AND that
 * also lack a real Accept-Language header (a strong bot tell) are
 * subjected to a much tighter cap so headless scripts that ignore JS
 * burn their budget on the very first burst. Real first-time human
 * visits are always allowed because Accept-Language is set by every
 * mainstream browser.
 *
 * Per-link overrides come from `link.settings.rate_limit`:
 *   {
 *     "enabled":     bool,   // default true
 *     "ip_per_min":  int,    // defaults to DEFAULT_IP_PER_MIN
 *     "fp_per_min":  int,    // defaults to DEFAULT_FP_PER_MIN
 *   }
 */
class VisitorRateLimiter
{
    public const DEFAULT_IP_PER_MIN = 30;
    public const DEFAULT_FP_PER_MIN = 60;

    /** Tighter cap for visitors that fail the JS challenge AND look bot-ish. */
    public const CHALLENGE_FAIL_PER_MIN = 5;

    public const HUMAN_COOKIE = '1inme_human';

    /**
     * Check whether the request should be throttled. Always increments
     * the underlying counters so a flood is throttled even if its hits
     * arrive faster than the cache TTL.
     *
     * The decision is memoized for the lifetime of the current request
     * via `$request->attributes` so the controller's gate (which aborts
     * with 429) and the tracking service (which tags the click row)
     * both see the same answer without double-incrementing the counters.
     */
    public function shouldThrottle(Link $link, Request $request, ?string $userAgent): bool
    {
        $memoKey = "_vrl_decision_{$link->id}";
        if ($request->attributes->has($memoKey)) {
            return (bool) $request->attributes->get($memoKey);
        }

        $config = $this->configFor($link);
        if (!$config['enabled']) {
            $request->attributes->set($memoKey, false);
            return false;
        }

        $ip = (string) ($request->ip() ?? '');
        if ($ip === '') {
            $request->attributes->set($memoKey, false);
            return false;
        }
        $fp = $this->fingerprint($ip, $userAgent);
        $window = 60;

        $ipCount = $this->bump("vrl:ip:{$link->id}:{$ip}", $window);
        $fpCount = $this->bump("vrl:fp:{$link->id}:{$fp}", $window);

        $ipLimit = $config['ip_per_min'];
        $fpLimit = $config['fp_per_min'];

        // Borderline-bot signal: missing Accept-Language and no JS cookie.
        // Real browsers always send Accept-Language; real visitors who
        // executed a single page render will have set the human cookie.
        $hasCookie = (bool) $request->cookie(self::HUMAN_COOKIE);
        $hasAcceptLang = $request->header('Accept-Language') !== null;
        if (!$hasCookie && !$hasAcceptLang) {
            $ipLimit = min($ipLimit, self::CHALLENGE_FAIL_PER_MIN);
            $fpLimit = min($fpLimit, self::CHALLENGE_FAIL_PER_MIN);
        }

        $decision = $ipCount > $ipLimit || $fpCount > $fpLimit;
        $request->attributes->set($memoKey, $decision);
        return $decision;
    }

    /**
     * Resolved per-link config with defaults applied. Public so the
     * settings UI / API can echo back what's currently in force.
     *
     * @return array{enabled:bool, ip_per_min:int, fp_per_min:int}
     */
    public function configFor(Link $link): array
    {
        $raw = (array) data_get($link->settings, 'rate_limit', []);
        return [
            'enabled'    => array_key_exists('enabled', $raw) ? (bool) $raw['enabled'] : true,
            'ip_per_min' => $this->clamp((int) ($raw['ip_per_min'] ?? self::DEFAULT_IP_PER_MIN), 1, 10000),
            'fp_per_min' => $this->clamp((int) ($raw['fp_per_min'] ?? self::DEFAULT_FP_PER_MIN), 1, 10000),
        ];
    }

    protected function fingerprint(string $ip, ?string $userAgent): string
    {
        return substr(hash('sha256', $ip . '|' . ((string) $userAgent)), 0, 24);
    }

    protected function bump(string $key, int $ttlSeconds): int
    {
        try {
            // Cache::add returns true if it created the key (first hit
            // in this window) so we can pin the TTL exactly once.
            if (Cache::add($key, 0, $ttlSeconds)) {
                // pinned
            }
            return (int) Cache::increment($key);
        } catch (\Throwable $e) {
            // Cache failures must never block real traffic.
            return 0;
        }
    }

    protected function clamp(int $v, int $min, int $max): int
    {
        return max($min, min($max, $v));
    }
}
