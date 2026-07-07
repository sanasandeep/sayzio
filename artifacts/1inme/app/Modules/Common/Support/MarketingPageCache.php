<?php

namespace App\Modules\Common\Support;

use App\Modules\Common\Controllers\BlogController;
use App\Modules\Common\Controllers\CreatorsController;
use App\Modules\Common\Controllers\SitePageController;
use App\Modules\Common\Models\SitePage;
use Illuminate\Support\Facades\Cache;

/**
 * Proactive warmer for the non-home marketing surfaces: /features (and
 * every other SitePage-backed marketing page), /creators, /demos and
 * /blogs. The /pricing catalogue is warmed separately by
 * HomePageCache::warm() via PricingPageCache::warm(), which runs from the
 * same `home:warm-caches` command. Each of these pages caches its DB reads as
 * plain attribute arrays with a 5-10 minute TTL, but was visitor-primed
 * only — the first visitor after a deploy/restart or Autoscale wake-up
 * paid a multi-second cold rebuild over the distant RDS.
 *
 * Invoked alongside HomePageCache::warm() by the `home:warm-caches`
 * command (scheduled every four minutes AND run once at boot by the
 * production run command / EC2 deploy script), so all marketing pages are
 * instant from the very first visit.
 *
 * The builders live with their request-path consumers (controllers /
 * SitePage model) so the warmer and the lazy rebuild can never drift;
 * this class only orchestrates: call builder → Cache::put with the longer
 * HomePageCache::WARM_TTL (a single missed run can't open an expiry gap;
 * freshness comes from each run overwriting the keys, so admin edits land
 * within one warm cadence — explicit flushes still clear keys instantly).
 *
 * Best-effort semantics: every section is fault-isolated. A failure logs
 * and is reported in the summary, but never blocks the other sections or
 * serving (visitors fall back to the lazy request-path rebuild).
 */
class MarketingPageCache
{
    /**
     * Rebuild every marketing-page cache from the database and overwrite
     * the stored copies. Returns a summary for logging.
     *
     * @return array{site_pages:int,creators:array<int,string>,demos:bool,blogs:bool,errors:array<int,string>}
     */
    public static function warm(): array
    {
        $ttl = HomePageCache::WARM_TTL;

        $summary = [
            'site_pages' => 0,
            'creators'   => [],
            'demos'      => false,
            'blogs'      => false,
            'errors'     => [],
        ];

        // Every SitePage-backed marketing page (/features, /about, /faqs,
        // policies, AI product pages, ...) reads through the per-slug
        // attribute cache. One query warms them all. Only positive hits are
        // cached, matching cachedBySlug()'s firstOrCreate/firstOrFail
        // semantics for missing rows.
        try {
            foreach (SitePage::query()->get() as $page) {
                Cache::put(
                    SitePage::SLUG_CACHE_PREFIX . $page->slug,
                    $page->getAttributes(),
                    $ttl
                );
                $summary['site_pages']++;
            }
        } catch (\Throwable $e) {
            $summary['errors'][] = self::reportWarmFailure('site_pages', $e);
        }

        // /creators — default anonymous directory page, the default
        // (non-adult) trending carousel variant, and the tag cloud.
        $creators = new CreatorsController();
        try {
            Cache::put(
                CreatorsController::DEFAULT_CACHE_KEY,
                $creators->buildDefaultIndexPayload(),
                $ttl
            );
            $summary['creators'][] = 'default_index';
        } catch (\Throwable $e) {
            $summary['errors'][] = self::reportWarmFailure('creators_default_index', $e);
        }
        try {
            Cache::put(
                CreatorsController::trendingCarouselCacheKey(false, false),
                $creators->buildTrendingCarouselRows(false, false),
                $ttl
            );
            $summary['creators'][] = 'trending_carousel';
        } catch (\Throwable $e) {
            $summary['errors'][] = self::reportWarmFailure('creators_trending', $e);
        }
        try {
            Cache::put(
                CreatorsController::POPULAR_TAGS_CACHE_KEY,
                $creators->buildPopularTagCounts(24),
                $ttl
            );
            $summary['creators'][] = 'popular_tags';
        } catch (\Throwable $e) {
            $summary['errors'][] = self::reportWarmFailure('creators_popular_tags', $e);
        }

        // /demos gallery link data.
        try {
            Cache::put(
                SitePageController::DEMOS_CACHE_KEY,
                SitePageController::buildDemosData(),
                $ttl
            );
            $summary['demos'] = true;
        } catch (\Throwable $e) {
            $summary['errors'][] = self::reportWarmFailure('demos', $e);
        }

        // /blogs default index page.
        try {
            Cache::put(
                BlogController::INDEX_CACHE_KEY,
                BlogController::buildDefaultIndexPayload(),
                $ttl
            );
            $summary['blogs'] = true;
        } catch (\Throwable $e) {
            $summary['errors'][] = self::reportWarmFailure('blogs_index', $e);
        }

        return $summary;
    }

    /**
     * Log a warm-section failure and return a short label for the summary.
     */
    private static function reportWarmFailure(string $section, \Throwable $e): string
    {
        \Illuminate\Support\Facades\Log::warning('marketing warm-caches section failed', [
            'section' => $section,
            'error' => $e->getMessage(),
        ]);

        return $section;
    }
}
