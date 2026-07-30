<?php

namespace App\Modules\Common\Support;

use App\Modules\Admin\Models\AppSetting;

/**
 * Single source of truth for the download / store CTAs shown on the four
 * standalone product marketing pages (/dialer, /browser, /extension, /app).
 *
 * Every URL is admin-managed (Admin → Marketing settings → "Product page
 * download links") with sensible fallbacks to sources that already exist:
 *   - Android APKs fall back to the admin APK manager's live release
 *     (the public /android landing page), when one is published.
 *   - Desktop installers fall back to the live SayZio Browser release
 *     resolved by {@see ZioBrowserRelease} (the same source as /download).
 *   - Extension store buttons come from {@see ExtensionStoreLinks} (direct
 *     listing URL when configured, store search page pre-publish).
 *
 * Empty/unresolvable URLs are returned as '' so pages can hide dead buttons.
 */
class ProductDownloadLinks
{
    /** AppSetting keys for the admin-managed overrides. */
    public const DIALER_PLAY_URL   = 'product_dialer_play_url';
    public const DIALER_APK_URL    = 'product_dialer_apk_url';
    public const BROWSER_MAC_URL   = 'product_browser_mac_url';
    public const BROWSER_WIN_URL   = 'product_browser_windows_url';

    private static function setting(string $key): string
    {
        $v = trim((string) AppSetting::get($key, ''));

        return preg_match('#^https?://#i', $v) === 1 ? $v : '';
    }

    /**
     * True when the admin APK manager has a live Android release the public
     * /android landing page can serve.
     */
    public static function hasLiveApk(): bool
    {
        try {
            return \App\Modules\Admin\Models\AndroidApkRelease::live() !== null;
        } catch (\Throwable $e) {
            // Missing table on a fresh env must never blank a marketing page.
            return false;
        }
    }

    /**
     * Zio Dialer CTAs.
     *
     * @return array{play:string, apk:string} URLs, '' when unavailable.
     */
    public static function dialer(): array
    {
        $apk = self::setting(self::DIALER_APK_URL);
        if ($apk === '' && self::hasLiveApk()) {
            $apk = route('android.show');
        }

        return [
            'play' => self::setting(self::DIALER_PLAY_URL),
            'apk'  => $apk,
        ];
    }

    /**
     * Zio Browser desktop CTAs. Admin overrides win; otherwise the live
     * GitHub release resolved by ZioBrowserRelease (same as /download).
     *
     * @return array{mac:string, windows:string, version:string}
     */
    public static function browser(): array
    {
        $mac = self::setting(self::BROWSER_MAC_URL);
        $win = self::setting(self::BROWSER_WIN_URL);
        $version = '';

        if ($mac === '' || $win === '') {
            try {
                $release = ZioBrowserRelease::current();
            } catch (\Throwable $e) {
                $release = [];
            }
            $version = (string) ($release['version'] ?? '');
            if ($mac === '') {
                $mac = (string) ($release['mac_arm64_dmg'] ?? ($release['mac_x64_dmg'] ?? ''));
            }
            if ($win === '') {
                $win = (string) ($release['windows_exe'] ?? '');
            }
        }

        return ['mac' => $mac, 'windows' => $win, 'version' => $version];
    }

    /**
     * Browser-extension store buttons (delegates to ExtensionStoreLinks).
     *
     * @return list<array{key:string,label:string,url:string,is_listing:bool}>
     */
    public static function extension(): array
    {
        return ExtensionStoreLinks::stores();
    }

    /**
     * Sayzio mobile app CTAs — the existing marketing store URLs plus the
     * APK manager fallback for Android side-loading.
     *
     * @return array{play:string, ios:string, apk:string}
     */
    public static function app(): array
    {
        return [
            'play' => self::setting('marketing_play_store_url'),
            'ios'  => self::setting('marketing_app_store_url'),
            'apk'  => self::hasLiveApk() ? route('android.show') : '',
        ];
    }
}
