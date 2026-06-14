<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Models\SitePage;
use App\Modules\Common\Support\MarketingSeo;
use Illuminate\Support\Carbon;

/**
 * Public XML sitemap + robots.txt for the marketing pages.
 *
 * The URL list is sourced entirely from {@see MarketingSeo::sitemapPaths()}
 * (code-driven SEO registry + site_pages-backed slugs) so it stays in lockstep
 * with the per-page SEO meta. Note: this is the *marketing* sitemap; the blog
 * keeps its own sitemap at /blogs/sitemap.xml.
 */
class SitemapController extends Controller
{
    public function sitemap()
    {
        // Per-row lastmod for the site_pages-backed pages. Read updated_at off
        // model instances so the value is a Carbon regardless of cast config.
        $rowUpdated = [];
        foreach (SitePage::query()->get(['slug', 'updated_at']) as $row) {
            $rowUpdated[$row->slug] = $row->updated_at;
        }

        // Code-driven pages have no model; fall back to the marketing_seo
        // override row's timestamp (the last time any of them was edited).
        $codeFallback = optional(
            AppSetting::where('key', MarketingSeo::SETTING_KEY)->first()
        )->updated_at;

        $urls = [];
        foreach (MarketingSeo::sitemapPaths() as $entry) {
            $slug = $entry['slug'];

            if ($slug !== null) {
                $ts = $rowUpdated[$slug] ?? null;
                $lastmod = $ts ?: $codeFallback;
            } else {
                $lastmod = $codeFallback;
            }

            $urls[] = [
                'loc' => url($entry['path']),
                'lastmod' => $this->formatLastmod($lastmod),
            ];
        }

        $body = view('public.sitemap', ['urls' => $urls])->render();

        return response($body, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
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
            'Sitemap: ' . url('/sitemap.xml'),
            '',
        ];

        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
