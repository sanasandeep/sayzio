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
 *  2. Build-meta stamp (`public/build/build-meta.json`, written by the npm
 *     `build` script) — the build time stored as file CONTENT, so it survives
 *     deployment images that strip git metadata AND normalize file mtimes.
 *  3. Vite build manifest mtime (`public/build/manifest.json`) — legacy
 *     fallback for builds produced before the build-meta stamp existed.
 *  4. null — returned silently if no source is accessible so the footer
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
     * Test hook: when set, {@see fromGit()} calls this instead of shelling
     * out to `git log`. Return the raw command output (string) or null to
     * simulate git being unavailable (forcing the manifest fallback).
     */
    public static ?\Closure $gitOutputResolver = null;

    /**
     * Test hook: when set, {@see fromManifest()} reads this path instead of
     * public/build/manifest.json. Point it at a nonexistent file to simulate
     * an environment with no Vite build.
     */
    public static ?string $manifestPathOverride = null;

    /**
     * Test hook: when set, {@see fromBuildMeta()} reads this path instead of
     * public/build/build-meta.json. Point it at a nonexistent file to simulate
     * an environment without the build stamp.
     */
    public static ?string $buildMetaPathOverride = null;

    /**
     * Sentinel cached in place of null so an "unavailable" result is also
     * cached for CACHE_TTL (Cache::remember re-runs the resolver on null).
     */
    public const NONE = 'none';

    /**
     * Return the resolved Carbon timestamp, or null when unavailable.
     */
    public static function get(): ?Carbon
    {
        try {
            $value = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => self::resolve() ?? self::NONE);

            return $value instanceof Carbon ? $value : null;
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

        // 2. Build-meta stamp (content-based, survives mtime normalization)
        $ts = self::fromBuildMeta();
        if ($ts !== null) {
            return $ts;
        }

        // 3. Build manifest mtime
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
            $output = self::$gitOutputResolver !== null
                ? (self::$gitOutputResolver)()
                : shell_exec('git log -1 --format=%ct 2>/dev/null');

            if ($output === null || $output === false) {
                return null;
            }

            $epoch = (int) trim($output);

            return $epoch > 0 ? Carbon::createFromTimestampUTC($epoch) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function fromBuildMeta(): ?Carbon
    {
        try {
            $path = self::$buildMetaPathOverride ?? public_path('build/build-meta.json');

            if (! is_file($path)) {
                return null;
            }

            $data = json_decode((string) file_get_contents($path), true);
            $epoch = (int) ($data['built_at'] ?? 0);

            return $epoch > 0 ? Carbon::createFromTimestampUTC($epoch) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function fromManifest(): ?Carbon
    {
        try {
            $path = self::$manifestPathOverride ?? public_path('build/manifest.json');

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
