<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Models\BlogPost;
use App\Modules\Common\Models\SitePage;
use App\Modules\Common\Support\MarketingSeo;
use App\Modules\Common\Support\MarketingSitemap;
use Illuminate\Support\Carbon;

/**
 * Public XML sitemap + robots.txt for the marketing pages.
 *
 * The URL list is sourced entirely from {@see \App\Modules\Common\Support\MarketingSeo::sitemapPaths()}
 * (code-driven SEO registry + site_pages-backed slugs) so it stays in lockstep
 * with the per-page SEO meta. Rendering/caching/invalidation lives in
 * {@see MarketingSitemap}. Note: this is the *marketing* sitemap; the blog
 * keeps its own sitemap at /blogs/sitemap.xml.
 */
class SitemapController extends Controller
{
    /**
     * Sitemap index that references both the marketing sitemap and the blog
     * sitemap so search engines can discover every public URL from a single
     * entry point (/sitemap_index.xml). The individual sitemaps keep working
     * on their own.
     */
    public function index()
    {
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

        $body = view('public.sitemap_index', ['sitemaps' => $sitemaps])->render();

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
