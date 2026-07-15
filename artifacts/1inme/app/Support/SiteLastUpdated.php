<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves the time the site's code was last updated, for display in the
 * admin footer. The resolution chain is:
 *
 *  1. Latest git commit timestamp (`git log -1 --format=%ct`) — authoritative
 *     in development and on servers that have git metadata.
 *  2. Vite build manifest mtime (`public/build/manifest.json`) — available in
 *     production containers where git history is stripped out.
 *  3. null — returned silently if neither source is accessible so the footer
 *     can hide the element rather than crash.
 *
 * The resolved value is cached for {@see CACHE_TTL} seconds so no shell/FS
 * work happens on every admin page load.
 */
class SiteLastUpdated
{
    private const CACHE_KEY = 'site:last_updated_at';
    private const CACHE_TTL = 300; // 5 minutes

    /**
     * Return the resolved Carbon timestamp, or null when unavailable.
     */
    public static function get(): ?Carbon
    {
        try {
            return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => self::resolve());
        } catch (\Throwable) {
            return self::resolve();
        }
    }

    /**
     * Resolve without cache. Safe to call directly in tests.
     */
    public static function resolve(): ?Carbon
    {
        // 1. git commit time
        $ts = self::fromGit();
        if ($ts !== null) {
            return $ts;
        }

        // 2. Build manifest mtime
        return self::fromManifest();
    }

    /** Clear the cached value (e.g. after a fresh deploy or in tests). */
    public static function flush(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (\Throwable) {
            // Best-effort.
        }
    }

    // -------------------------------------------------------------------------
    // Private resolvers
    // -------------------------------------------------------------------------

    private static function fromGit(): ?Carbon
    {
        try {
            $output = shell_exec('git log -1 --format=%ct 2>/dev/null');

            if ($output === null || $output === false) {
                return null;
            }

            $epoch = (int) trim($output);

            return $epoch > 0 ? Carbon::createFromTimestampUTC($epoch) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function fromManifest(): ?Carbon
    {
        try {
            $path = public_path('build/manifest.json');

            if (! is_file($path)) {
                return null;
            }

            $mtime = filemtime($path);

            return $mtime !== false && $mtime > 0
                ? Carbon::createFromTimestampUTC($mtime)
                : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
