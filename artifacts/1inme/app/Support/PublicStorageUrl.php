<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Resolves stored public-disk paths (`/storage/<path>`) straight to the
 * direct S3/CloudFront URL when the public disk is S3-backed, so browsers
 * and email clients never pay the extra round-trip through the
 * `/storage/{path}` bridge route (`storage.cdn.fallback`).
 *
 * Behaviour:
 * - `/storage/...` value + S3 public disk  → direct CDN URL.
 * - Anything else (absolute URLs, data URIs, non-S3 disk, null/empty)
 *   passes through unchanged.
 * - On a transient S3 config/SDK failure it logs and returns the original
 *   value, so images degrade gracefully to the bridge redirect instead of
 *   vanishing.
 */
class PublicStorageUrl
{
    /**
     * Memoized "is the public disk S3-backed?" answer for the request.
     */
    private static ?bool $publicDiskIsS3 = null;

    public static function resolve(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $trimmed = trim($url);
        if ($trimmed === '' || !str_starts_with($trimmed, '/storage/')) {
            return $url;
        }

        if (!self::publicDiskIsS3()) {
            return $url;
        }

        $relativePath = ltrim(substr($trimmed, strlen('/storage/')), '/');
        if ($relativePath === '') {
            return $url;
        }

        try {
            return Storage::disk('public')->url($relativePath);
        } catch (\Throwable $e) {
            Log::warning('PublicStorageUrl: could not build S3 URL, using bridge path', [
                'url'   => $trimmed,
                'error' => $e->getMessage(),
            ]);
            return $url;
        }
    }

    private static function publicDiskIsS3(): bool
    {
        if (self::$publicDiskIsS3 === null) {
            self::$publicDiskIsS3 = config('filesystems.disks.public.driver') === 's3';
        }
        return self::$publicDiskIsS3;
    }
}
