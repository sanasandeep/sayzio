<?php

namespace App\Modules\Common\Support;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Models\SitePage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Builds, caches and invalidates the public marketing /sitemap.xml, and
 * (best-effort) notifies search engines when its contents meaningfully change.
 *
 * The URL list is sourced entirely from {@see MarketingSeo::sitemapPaths()} so
 * the sitemap stays in lockstep with the per-page SEO meta. The rendered XML is
 * cached for 10 minutes (mirroring the blog sitemap) and flushed whenever a
 * SitePage row is saved/deleted or the marketing_seo AppSetting is updated.
 */
class MarketingSitemap
{
    /** Cache key for the fully-rendered sitemap XML body. */
    public const CACHE_KEY = 'marketing.sitemap.xml';

    /** How long the rendered sitemap stays cached (seconds). */
    private const CACHE_TTL = 600;

    /** AppSetting key holding the IndexNow ownership key. */
    public const INDEXNOW_KEY_SETTING = 'indexnow_key';

    /** Throttle stamp so we never ping search engines more than once a window. */
    private const PING_THROTTLE_KEY = 'marketing.sitemap.last_ping';

    /** Minimum gap between search-engine pings (seconds). */
    private const PING_THROTTLE_SECONDS = 600;

    /**
     * Return the rendered sitemap XML, served from cache when warm.
     */
    public static function render(): string
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => self::build());
    }

    /**
     * Build the sitemap XML body from the marketing SEO registry. Each
     * site_pages-backed entry gets a per-row <lastmod>; code-driven pages fall
     * back to the marketing_seo override row's timestamp.
     */
    public static function build(): string
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
                'lastmod' => self::formatLastmod($lastmod),
            ];
        }

        return view('public.sitemap', ['urls' => $urls])->render();
    }

    /**
     * Drop the cached sitemap so the next request rebuilds it, then (best-effort)
     * notify search engines that the marketing pages changed. Both steps are
     * guarded so they can never break the write path that triggered them.
     */
    public static function flush(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (\Throwable $e) {
            // Cache flushing must never break the write path.
        }

        self::notifySearchEngines();
    }

    /**
     * Notify search engines (via IndexNow, honoured by Bing/Yandex and others)
     * that the marketing URLs changed. Gated to production, throttled to once
     * per window, and dispatched after the response so the admin save stays
     * snappy. Entirely best-effort: any failure is swallowed/logged.
     */
    public static function notifySearchEngines(): void
    {
        try {
            if (!app()->environment('production')) {
                return;
            }

            // Atomic throttle: Cache::add only succeeds when the stamp is absent.
            if (!Cache::add(self::PING_THROTTLE_KEY, time(), self::PING_THROTTLE_SECONDS)) {
                return;
            }

            $urls = [];
            foreach (MarketingSeo::sitemapPaths() as $entry) {
                $urls[] = url($entry['path']);
            }
            if (empty($urls)) {
                return;
            }

            dispatch(static function () use ($urls) {
                self::submitIndexNow($urls);
            })->afterResponse();
        } catch (\Throwable $e) {
            // Notifying search engines is best-effort; never break the write path.
        }
    }

    /**
     * The IndexNow ownership key, generated and persisted on first use so the
     * matching key file can be served from the site root. Returns null only if
     * the key could not be resolved or created.
     */
    public static function indexNowKey(): ?string
    {
        $key = AppSetting::get(self::INDEXNOW_KEY_SETTING);
        $key = is_string($key) ? trim($key) : '';

        if ($key === '') {
            try {
                $key = bin2hex(random_bytes(16)); // 32 lowercase hex chars
                AppSetting::put(self::INDEXNOW_KEY_SETTING, $key);
            } catch (\Throwable $e) {
                return null;
            }
        }

        return $key !== '' ? $key : null;
    }

    /**
     * POST the changed URL list to the IndexNow API. Best-effort: logs a warning
     * on failure but never throws.
     */
    private static function submitIndexNow(array $urls): void
    {
        try {
            $key = self::indexNowKey();
            if ($key === null) {
                return;
            }

            $host = parse_url(url('/'), PHP_URL_HOST);
            if (empty($host)) {
                return;
            }

            $response = Http::timeout(5)
                ->connectTimeout(3)
                ->acceptJson()
                ->post('https://api.indexnow.org/indexnow', [
                    'host' => $host,
                    'key' => $key,
                    'keyLocation' => url('/' . $key . '.txt'),
                    'urlList' => array_values(array_slice($urls, 0, 10000)),
                ]);

            if ($response->failed()) {
                Log::warning('IndexNow sitemap ping failed', [
                    'status' => $response->status(),
                    'host' => $host,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('IndexNow sitemap ping errored', ['message' => $e->getMessage()]);
        }
    }

    /**
     * Normalise a timestamp (Carbon, DateTime, or raw DB string) into a W3C
     * datetime suitable for a sitemap <lastmod>. Returns null when absent or
     * unparseable so the tag is simply omitted rather than breaking the feed.
     */
    private static function formatLastmod($value): ?string
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
}
