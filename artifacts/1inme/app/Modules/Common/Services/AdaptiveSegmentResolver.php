<?php

namespace App\Modules\Common\Services;

use App\Modules\User\Models\BiolinkBlock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Buckets a public biolink visitor into a coarse segment key for the
 * Adaptive Biolink bandit (Task #3531). Reuses the same detection
 * helpers the rest of the app already relies on (device/OS visibility
 * rules on BiolinkBlock, GeoIP for continent) so segments line up with
 * what creators already see elsewhere in the product, rather than
 * inventing a second classification scheme.
 *
 * Segments are intentionally coarse (a handful of buckets per axis) —
 * fine-grained segmentation would explode the number of bandit arms
 * and starve each one of traffic before it can learn anything.
 */
class AdaptiveSegmentResolver
{
    public const RETURNING_COOKIE = '_adap_seen';
    public const RETURNING_COOKIE_TTL_DAYS = 400;

    protected const SOCIAL_HOST_HINTS = [
        'instagram.com', 'facebook.com', 'fb.me', 'tiktok.com', 'twitter.com',
        'x.com', 't.co', 'pinterest.com', 'snapchat.com', 'linkedin.com',
        'whatsapp.com', 'reddit.com',
    ];

    protected const SEARCH_HOST_HINTS = [
        'google.', 'bing.com', 'yahoo.', 'duckduckgo.com', 'baidu.com', 'yandex.',
    ];

    /**
     * @return string A stable, low-cardinality segment key such as
     *   "mobile|ios|northamerica|social|evening|returning".
     */
    public function resolve(Request $request): string
    {
        $parts = [
            $this->device($request),
            $this->os($request),
            $this->continent($request),
            $this->referrerClass($request),
            $this->timeBucket(),
            $this->visitorRecency($request),
        ];

        return implode('|', $parts);
    }

    protected function device(Request $request): string
    {
        return BiolinkBlock::detectDevice($request) ?: 'desktop';
    }

    protected function os(Request $request): string
    {
        $os = strtolower(BiolinkBlock::detectOS($request) ?: '');
        return $os !== '' ? str_replace(' ', '', $os) : 'other';
    }

    protected function continent(Request $request): string
    {
        try {
            // Reuses the same country resolution biolink block visibility
            // rules already rely on (sim-preview override → CDN/geo header
            // → GeoIP fallback), so segments line up with what creators
            // already configure elsewhere.
            $country = BiolinkBlock::detectCountry($request);
            $continent = $country !== '' ? BiolinkBlock::countryToContinent($country) : '';
        } catch (\Throwable $e) {
            // Best-effort — never let a lookup failure break segment
            // resolution (and therefore the public page render).
            $continent = '';
        }
        $continent = strtolower(str_replace(' ', '', $continent));
        return $continent !== '' ? $continent : 'unknown';
    }

    protected function referrerClass(Request $request): string
    {
        $referer = $request->header('referer');
        if (!$referer) return 'direct';

        $host = strtolower((string) (parse_url($referer, PHP_URL_HOST) ?? ''));
        if ($host === '') return 'direct';

        foreach (self::SOCIAL_HOST_HINTS as $hint) {
            if (str_contains($host, $hint)) return 'social';
        }
        foreach (self::SEARCH_HOST_HINTS as $hint) {
            if (str_contains($host, $hint)) return 'search';
        }
        return 'other';
    }

    protected function timeBucket(): string
    {
        $hour = (int) now()->format('G');
        return match (true) {
            $hour < 6  => 'night',
            $hour < 12 => 'morning',
            $hour < 18 => 'afternoon',
            default    => 'evening',
        };
    }

    /**
     * "returning" if we've seen this visitor before (long-lived cookie,
     * same idea as the A/B sticky-assignment cookie but content-free —
     * it only marks "has this browser been here before?"). Queues the
     * cookie on first sight so the NEXT visit is classified as
     * returning. Falls back to "new" for clients without cookies
     * (bots, curl probes) rather than guessing.
     */
    protected function visitorRecency(Request $request): string
    {
        if ($request->cookie(self::RETURNING_COOKIE)) {
            return 'returning';
        }

        Cookie::queue(
            self::RETURNING_COOKIE,
            '1',
            self::RETURNING_COOKIE_TTL_DAYS * 24 * 60,
            null, null, true, false, false, 'lax'
        );

        return 'new';
    }
}
