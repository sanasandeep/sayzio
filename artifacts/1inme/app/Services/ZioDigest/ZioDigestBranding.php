<?php

namespace App\Services\ZioDigest;

use App\Modules\Admin\Models\AppSetting;
use App\Support\PublicStorageUrl;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Platform-wide Zio Digest logo, admin-updatable.
 *
 * Follows the admin-managed settings pattern (app_settings storage like
 * MailSettings / PlatformServiceSettings): an admin-uploaded logo stored on
 * the public disk wins; otherwise the bundled default asset
 * (public/img/zio-digest-logo.png) is used, so there is never a broken image.
 */
class ZioDigestBranding
{
    /** app_settings key holding the public-disk path of the custom logo. */
    public const KEY_LOGO_PATH = 'zio_digest.logo_path';

    /** Bundled default asset (relative to public/). */
    public const DEFAULT_ASSET = 'img/zio-digest-logo.png';

    /** Public-disk directory custom uploads are stored under. */
    public const UPLOAD_DIR = 'zio-digests/branding';

    /** Stored public-disk path of the custom logo, if one is uploaded. */
    public static function customLogoPath(): ?string
    {
        $path = AppSetting::get(self::KEY_LOGO_PATH);

        return is_string($path) && trim($path) !== '' ? trim($path) : null;
    }

    public static function hasCustomLogo(): bool
    {
        return self::customLogoPath() !== null;
    }

    /**
     * Logo URL for web surfaces (public page + admin). Custom uploads are
     * emitted through PublicStorageUrl::resolve() so S3/CDN setups serve the
     * direct URL; the default falls back to the bundled asset.
     */
    public static function logoUrl(): string
    {
        $path = self::customLogoPath();
        if ($path !== null) {
            return (string) PublicStorageUrl::resolve('/storage/' . ltrim($path, '/'));
        }

        return asset(self::DEFAULT_ASSET);
    }

    /**
     * Absolute logo URL for email bodies and OG/social meta tags.
     */
    public static function logoAbsoluteUrl(): string
    {
        $url = self::logoUrl();
        if (preg_match('#^https?://#i', $url)) {
            return \App\Modules\Common\Support\PlatformHosts::outboundUrl($url);
        }

        return \App\Modules\Common\Support\PlatformHosts::outboundUrl(
            rtrim((string) config('app.url'), '/') . '/' . ltrim($url, '/')
        );
    }

    /**
     * Store a newly uploaded replacement logo on the public disk and make it
     * the platform-wide Zio Digest logo. Returns the stored path.
     */
    public static function storeUploadedLogo(UploadedFile $file): string
    {
        $ext  = strtolower($file->getClientOriginalExtension() ?: 'png');
        $name = 'logo-' . Str::random(16) . '.' . $ext;
        $path = $file->storeAs(self::UPLOAD_DIR, $name, ['disk' => 'public']);

        $previous = self::customLogoPath();
        AppSetting::put(self::KEY_LOGO_PATH, $path);
        self::deleteStoredFile($previous);

        return $path;
    }

    /** Revert to the bundled default logo, removing the custom upload. */
    public static function revertToDefault(): void
    {
        $previous = self::customLogoPath();
        AppSetting::put(self::KEY_LOGO_PATH, null);
        self::deleteStoredFile($previous);
    }

    private static function deleteStoredFile(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }
        try {
            Storage::disk('public')->delete($path);
        } catch (\Throwable $e) {
            Log::warning('ZioDigestBranding: could not delete previous logo', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
