<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Models\BlogPost;
use App\Modules\Common\Models\SitePage;
use App\Modules\Common\Support\MarketingSeo;
use App\Modules\Common\Support\MarketingSitemap;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Public XML sitemap + robots.txt for the marketing pages.
 *
 * The URL list is sourced entirely from {@see \App\Modules\Common\Support\MarketingSeo::sitemapPaths()}
 * (code-driven SEO registry + site_pages-backed slugs) so it stays in lockstep
 * with the per-page SEO meta. Rendering/caching/invalidation lives in
 * {@see MarketingSitemap}. Note: this is the *marketing* sitemap; the blog
 * keeps its own sitemap at /blogs/sitemap.xml.
 *
 * Both the marketing sitemap and the sitemap index are cached for 10 minutes
 * (matching the blog sitemap) so search-engine crawlers don't recompute the DB
 * queries on every hit. The caches are invalidated by
 * {@see self::flushPublicCaches()} whenever marketing pages change
 * (site_pages / marketing_seo) and — for the index, which carries a blog
 * lastmod — whenever blog posts publish/update (see BlogPost::flushPublicCaches).
 */
class SitemapController extends Controller
{
    /** Cache key for the sitemap index (/sitemap_index.xml). */
    public const INDEX_CACHE_KEY = 'sitemap.index.xml';

    /** TTL (seconds) for the sitemap index cache, matching the blog sitemap. */
    public const CACHE_TTL = 600;

    /**
     * Invalidate every cached public sitemap artifact when marketing pages
     * change: the sitemap index (owned here) and the marketing sitemap
     * (owned by {@see MarketingSitemap}, which additionally best-effort pings
     * search engines). Guarded so a cache failure never breaks the write path.
     */
    public static function flushPublicCaches(): void
    {
        try {
            Cache::forget(self::INDEX_CACHE_KEY);
        } catch (\Throwable $e) {
            // Cache flushing must never break the write path.
        }

        MarketingSitemap::flush();
    }

    /**
     * Sitemap index that references both the marketing sitemap and the blog
     * sitemap so search engines can discover every public URL from a single
     * entry point (/sitemap_index.xml). The individual sitemaps keep working
     * on their own.
     */
    public function index()
    {
        $body = Cache::remember(self::INDEX_CACHE_KEY, self::CACHE_TTL, function () {
            $sitemaps = [
                [
                    'loc' => url('/sitemap.xml'),
                    'lastmod' => $this->formatLastmod($this->marketingLastmod()),
                ],
                [
                    'loc' => url('/blogs/sitemap.xml'),
                    'lastmod' => $this->formatLastmod($this->blogLastmod()),
                ],
            ];

            return view('public.sitemap_index', ['sitemaps' => $sitemaps])->render();
        });

        return response($body, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * Most recent change across marketing pages: the newest site_pages row
     * timestamp or the marketing_seo override row, whichever is later.
     */
    private function marketingLastmod()
    {
        $latest = null;

        $newestRow = SitePage::query()->max('updated_at');
        $codeFallback = optional(
            AppSetting::where('key', MarketingSeo::SETTING_KEY)->first()
        )->updated_at;

        foreach ([$newestRow, $codeFallback] as $candidate) {
            if (empty($candidate)) {
                continue;
            }
            $ts = $candidate instanceof \DateTimeInterface
                ? Carbon::instance($candidate)
                : Carbon::parse((string) $candidate);
            if ($latest === null || $ts->greaterThan($latest)) {
                $latest = $ts;
            }
        }

        return $latest;
    }

    /**
     * Most recent published-post update, mirroring the blog sitemap content.
     */
    private function blogLastmod()
    {
        return BlogPost::published()->max('updated_at');
    }

    public function sitemap()
    {
        // Cached for 10 minutes; invalidated by MarketingSitemap::flush() when a
        // SitePage row or the marketing_seo AppSetting changes so it stays in sync.
        $body = MarketingSitemap::render();

        return response($body, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * Serve the IndexNow ownership key file (/{key}.txt) so search engines can
     * verify we own the host before honouring our change notifications. Returns
     * 404 for any path that does not match the stored key.
     */
    public function indexNowKey(string $key)
    {
        $stored = MarketingSitemap::indexNowKey();

        if ($stored === null || !hash_equals($stored, $key)) {
            abort(404);
        }

        return response($stored, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * Normalise a timestamp (Carbon, DateTime, or raw DB string) into a W3C
     * datetime suitable for a sitemap <lastmod>. Returns null when absent or
     * unparseable so the tag is simply omitted rather than breaking the feed.
     */
    private function formatLastmod($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->toAtomString();
        }

        try {
            return Carbon::parse((string) $value)->toAtomString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function robots()
    {
        $lines = [
            'User-agent: *',
            // Keep crawlers out of the dashboard / app & API surfaces.
            'Disallow: /user/',
            'Disallow: /admin/',
            'Disallow: /api/',
            'Disallow: /sanctum/',
            'Disallow: /storage/',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /feed',
            'Disallow: /viewer/',
            'Disallow: /companion/',
            'Disallow: /embed/',
            '',
            'Sitemap: ' . url('/sitemap_index.xml'),
            '',
        ];

        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
