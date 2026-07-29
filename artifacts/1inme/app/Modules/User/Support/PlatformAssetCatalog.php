<?php

namespace App\Modules\User\Support;

use App\Support\PublicStorageUrl;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Catalog of platform-provided (owner-managed) image assets stored on the
 * S3 bucket under `assets/<folder>/`. The owner drops files into these
 * folders via the AWS console; the app lists them live (with a short
 * cache) so new files appear automatically — no hard-coded filenames.
 *
 * Rules:
 * - Available on EVERY plan — no gating anywhere in this class.
 * - Assets are platform-owned and public: URLs resolve through
 *   PublicStorageUrl (CDN-aware), never raw `/storage` bridges in prod
 *   and never per-user signed URLs.
 * - Tolerant by design: an empty/missing folder or an S3 hiccup yields an
 *   empty list, never a 500.
 * - `hand-drawn` files come as PNG + SVG pairs sharing a basename; the PNG
 *   is the preview/raster entry and the SVG (when present) rides along as
 *   `svg_url`.
 */
class PlatformAssetCatalog
{
    /** Cache TTL for a folder listing (seconds). */
    public const CACHE_TTL = 720; // 12 minutes

    /** Public folder slug → S3 key prefix. */
    public const FOLDERS = [
        'biolink-backgrounds' => 'assets/biolink-backgrounds',
        'grid-images'         => 'assets/grid-images',
        'hand-drawn'          => 'assets/hand-drawn',
        'people-avatars'      => 'assets/people-avatars',
        'stock-avatars'       => 'assets/stock-avatars',
    ];

    /** Folder slugs allowed as an avatar source. */
    public const AVATAR_FOLDERS = ['people-avatars', 'stock-avatars', 'hand-drawn'];

    /** Image extensions we surface (raster preview-capable + svg). */
    private const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];

    public static function isFolder(?string $folder): bool
    {
        return $folder !== null && array_key_exists($folder, self::FOLDERS);
    }

    /**
     * Cached listing of a folder. Each entry:
     * `{key, name, label, url, svg_url?}` where `url` is the public
     * CDN-resolved URL and `key` is the raw S3 object key (used as the
     * stable identifier on save paths).
     *
     * @return array<int, array<string, string|null>>
     */
    public static function list(string $folder): array
    {
        if (!self::isFolder($folder)) {
            return [];
        }

        return Cache::remember(
            "platform_assets:{$folder}",
            self::CACHE_TTL,
            fn () => self::listFresh($folder)
        );
    }

    /**
     * Drop the cached listing for a folder so the next list() call hits
     * S3 fresh. Called by the admin gallery manager after any mutation
     * (upload/rename/delete) so pickers refresh immediately instead of
     * waiting out the TTL.
     */
    public static function bustCache(string $folder): void
    {
        if (self::isFolder($folder)) {
            Cache::forget("platform_assets:{$folder}");
        }
    }

    /** Human-friendly display name for a folder slug. */
    public static function folderLabel(string $folder): string
    {
        return Str::title(str_replace('-', ' ', $folder));
    }

    /**
     * Whether a bare filename (no path) is acceptable for storage in a
     * curated folder: plain image name, allowed extension, no traversal.
     */
    public static function isValidFilename(?string $name): bool
    {
        if ($name === null || $name === '' || str_contains($name, '/') || str_contains($name, '..')) {
            return false;
        }
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        return in_array($ext, self::EXTENSIONS, true)
            && preg_match('/^[A-Za-z0-9 _().,&\'!\[\]-]+\.[A-Za-z0-9]+$/u', $name) === 1;
    }

    /**
     * Whether an S3 object key is a plausible member of the given folder
     * (used to validate save-path input without an S3 round-trip: the
     * folder prefix must match and the filename must be a plain image
     * name — no traversal, no nested paths).
     */
    public static function isValidKey(string $folder, ?string $key): bool
    {
        if (!self::isFolder($folder) || $key === null || $key === '') {
            return false;
        }
        $prefix = self::FOLDERS[$folder] . '/';
        if (!str_starts_with($key, $prefix)) {
            return false;
        }
        $name = substr($key, strlen($prefix));
        if ($name === '' || str_contains($name, '/') || str_contains($name, '..')) {
            return false;
        }
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        return in_array($ext, self::EXTENSIONS, true)
            && preg_match('/^[A-Za-z0-9 _().,&\'!\[\]-]+\.[A-Za-z0-9]+$/u', $name) === 1;
    }

    /**
     * Validate a key against any of the given folder slugs; returns the
     * matching folder slug or null.
     *
     * @param array<int, string> $folders
     */
    public static function folderForKey(?string $key, array $folders): ?string
    {
        foreach ($folders as $folder) {
            if (self::isValidKey($folder, $key)) {
                return $folder;
            }
        }

        return null;
    }

    /** Public (CDN-resolved) URL for a raw S3 object key. */
    public static function urlForKey(string $key): string
    {
        return PublicStorageUrl::resolve('/storage/' . ltrim($key, '/')) ?? ('/storage/' . ltrim($key, '/'));
    }

    /**
     * Uncached listing. Never throws: S3 failures log and return [].
     *
     * @return array<int, array<string, string|null>>
     */
    private static function listFresh(string $folder): array
    {
        $prefix = self::FOLDERS[$folder];

        try {
            $files = Storage::disk('s3')->files($prefix);
        } catch (\Throwable $e) {
            Log::warning('PlatformAssetCatalog: S3 listing failed', [
                'folder' => $folder,
                'error'  => $e->getMessage(),
            ]);

            return [];
        }

        if (!is_array($files)) {
            return [];
        }

        // Group by basename so hand-drawn PNG+SVG pairs collapse into one
        // entry (PNG preferred as the preview/raster URL, SVG attached).
        $byBase = [];
        foreach ($files as $path) {
            $name = basename((string) $path);
            $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, self::EXTENSIONS, true)) {
                continue;
            }
            $base = pathinfo($name, PATHINFO_FILENAME);
            $byBase[$base][$ext] = (string) $path;
        }

        ksort($byBase, SORT_NATURAL | SORT_FLAG_CASE);

        $assets = [];
        foreach ($byBase as $base => $variants) {
            // Prefer a raster file as the primary entry; an SVG-only entry
            // still surfaces (some folders may carry pure SVGs).
            $primary = null;
            foreach (['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'] as $ext) {
                if (isset($variants[$ext])) {
                    $primary = $variants[$ext];
                    break;
                }
            }
            if ($primary === null) {
                continue;
            }

            $entry = [
                'key'   => $primary,
                'name'  => basename($primary),
                'label' => self::labelFromBasename((string) $base),
                'url'   => self::urlForKey($primary),
            ];
            if (isset($variants['svg']) && $variants['svg'] !== $primary) {
                $entry['svg_key'] = $variants['svg'];
                $entry['svg_url'] = self::urlForKey($variants['svg']);
            }
            $assets[] = $entry;
        }

        return $assets;
    }

    /** "sunset-beach_02" → "Sunset Beach 02". */
    private static function labelFromBasename(string $base): string
    {
        $label = trim(preg_replace('/[\s_-]+/', ' ', $base) ?? $base);

        return $label === '' ? $base : Str::title($label);
    }
}
