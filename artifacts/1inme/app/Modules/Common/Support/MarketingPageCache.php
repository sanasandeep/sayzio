<?php

namespace App\Modules\Common\Support;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\SiteStat;
use App\Modules\Common\Controllers\BlogController;
use App\Modules\Common\Controllers\CreatorsController;
use App\Modules\Common\Controllers\SitePageController;
use App\Modules\Common\Models\SiteAssistantPageHint;
use App\Modules\Common\Models\SitePage;
use App\Modules\Common\Services\BlogCtaComposer;
use App\Modules\Common\Services\EventsHeroBandComposer;
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
     * AppSetting keys read on every marketing-page render (shared layout:
     * branding, footer socials, SEO/pixels, cookie consent, assistant
     * config, maintenance flags, announcements, blog settings, WhatsApp
     * CTAs, wallet flag). Most installs leave many of these UNSET — no row
     * exists — so warming only stored rows still lets the first post-deploy
     * request pay one ~750ms cross-region query per unset key (this alone
     * made a warmed /pricing take ~20s). Listing them here lets warmAll()
     * cache the MISSING sentinel proactively. A stale list degrades
     * gracefully: an unlisted key just falls back to one lazy request-path
     * query, then sticks for the TTL.
     */
    public const LAYOUT_SETTING_KEYS = [
        'blog_settings',
        'brand_icon_url',
        'brand_logo_dark_url',
        'brand_logo_light_url',
        'cookie_consent_config',
        'maintenance_admin_only_enabled',
        'maintenance_marketing_enabled',
        'marketing_app_store_url',
        'marketing_default_share_image',
        'marketing_features_testimonials',
        'marketing_ga4_id',
        'marketing_meta_pixel_id',
        'marketing_play_store_url',
        'marketing_seo',
        'marketing_whatsapp_channel_url',
        'marketing_whatsapp_message',
        'marketing_whatsapp_number',
        'public_announcements',
        'site_assistant.config',
        'social_link_facebook',
        'social_link_github',
        'social_link_instagram',
        'social_link_linkedin',
        'social_link_threads',
        'social_link_tiktok',
        'social_link_twitter',
        'social_link_youtube',
        'wallet.enabled',
    ];

    /**
     * Rebuild every marketing-page cache from the database and overwrite
     * the stored copies. Returns a summary for logging.
     *
     * @return array{site_pages:int,creators:array<int,string>,demos:bool,blogs:bool,app_settings:int,layout:array<int,string>,errors:array<int,string>}
     */
    public static function warm(): array
    {
        $ttl = HomePageCache::WARM_TTL;

        $summary = [
            'site_pages'   => 0,
            'creators'     => [],
            'demos'        => false,
            'blogs'        => false,
            'app_settings' => 0,
            'layout'       => [],
            'errors'       => [],
        ];

        // Shared-layout reads that hit EVERY marketing page render: the
        // per-key app_settings cache (branding/socials/SEO/consent/...),
        // the events hero promo band, the "Latest from blog" CTA, the
        // site-stats trust band, and the assistant page hints. All were
        // visitor-primed only — even with the page payload caches warm,
        // the first post-deploy /pricing paid ~25s of these misses over
        // the cross-region RDS.
        try {
            $summary['app_settings'] = AppSetting::warmAll(self::LAYOUT_SETTING_KEYS, $ttl);
        } catch (\Throwable $e) {
            $summary['errors'][] = self::reportWarmFailure('app_settings', $e);
        }
        try {
            Cache::put(
                EventsHeroBandComposer::CACHE_KEY,
                (new EventsHeroBandComposer())->buildRows(),
                $ttl
            );
            $summary['layout'][] = 'events_band';
        } catch (\Throwable $e) {
            $summary['errors'][] = self::reportWarmFailure('events_band', $e);
        }
        try {
            Cache::put(BlogCtaComposer::CACHE_KEY, BlogCtaComposer::buildRows(), $ttl);
            $summary['layout'][] = 'blog_cta';
        } catch (\Throwable $e) {
            $summary['errors'][] = self::reportWarmFailure('blog_cta', $e);
        }
        try {
            Cache::put(SiteStat::ACTIVE_CACHE_KEY, SiteStat::buildActiveRows(), $ttl);
            $summary['layout'][] = 'site_stats';
        } catch (\Throwable $e) {
            $summary['errors'][] = self::reportWarmFailure('site_stats', $e);
        }
        try {
            foreach (['marketing', 'app'] as $surface) {
                Cache::put(
                    SiteAssistantPageHint::SURFACE_CACHE_PREFIX . $surface,
                    SiteAssistantPageHint::buildRowsForSurface($surface),
                    $ttl
                );
            }
            $summary['layout'][] = 'assistant_hints';
        } catch (\Throwable $e) {
            $summary['errors'][] = self::reportWarmFailure('assistant_hints', $e);
        }

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

        // /creators — default directory page (shared by anonymous AND
        // signed-in visitors; viewer-specific bits are overlaid live),
        // every distinct trending-carousel variant (default / show-adult
        // / only-adult — signed-in age-gated visitors hit the non-default
        // ones), and the tag cloud.
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
        foreach (CreatorsController::trendingCarouselVariants() as $variant => [$showAdult, $onlyAdult]) {
            try {
                Cache::put(
                    CreatorsController::trendingCarouselCacheKey($showAdult, $onlyAdult),
                    $creators->buildTrendingCarouselRows($showAdult, $onlyAdult),
                    $ttl
                );
                $summary['creators'][] = 'trending_carousel_' . $variant;
            } catch (\Throwable $e) {
                $summary['errors'][] = self::reportWarmFailure('creators_trending_' . $variant, $e);
            }
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
