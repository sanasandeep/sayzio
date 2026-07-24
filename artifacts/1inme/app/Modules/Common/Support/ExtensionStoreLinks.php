<?php

namespace App\Modules\Common\Support;

use App\Modules\Admin\Models\AppSetting;

/**
 * Single source of truth for the browser-extension install links shown on the
 * web "Browser Extension" card (Settings → Connected Accounts & Apps) and the
 * mobile Browser extension info page (via GET /api/v1/extension/stores).
 *
 * Until the extension is published, each store falls back to a search-results
 * URL for "Zio Extension". Once a listing exists, an admin pastes the direct listing
 * URL in Admin → Marketing settings ("Browser extension store links") and both
 * surfaces switch to it — no code deploy needed.
 */
class ExtensionStoreLinks
{
    /** AppSetting keys, one per store, mirroring marketing_play_store_url. */
    public const SETTING_KEYS = [
        'chrome'  => 'extension_chrome_store_url',
        'edge'    => 'extension_edge_store_url',
        'firefox' => 'extension_firefox_store_url',
    ];

    /** Pre-publish fallbacks: store search pages for "Zio Extension". */
    private const SEARCH_URLS = [
        'chrome'  => 'https://chromewebstore.google.com/search/Zio%20Extension',
        'edge'    => 'https://microsoftedge.microsoft.com/addons/Search/Zio%20Extension',
        'firefox' => 'https://addons.mozilla.org/en-US/firefox/search/?q=Zio%20Extension',
    ];

    private const LABELS = [
        'chrome'  => 'Chrome Web Store',
        'edge'    => 'Edge Add-ons',
        'firefox' => 'Firefox Add-ons',
    ];

    /**
     * Resolved store list. Each entry:
     * ['key' => 'chrome', 'label' => 'Chrome Web Store', 'url' => ..., 'is_listing' => bool]
     * `is_listing` is true when the admin has configured a direct listing URL
     * (vs the pre-publish search fallback).
     *
     * @return list<array{key:string,label:string,url:string,is_listing:bool}>
     */
    public static function stores(): array
    {
        $out = [];
        foreach (self::SETTING_KEYS as $key => $settingKey) {
            $configured = trim((string) AppSetting::get($settingKey, ''));
            $isListing = $configured !== '' && preg_match('#^https?://#i', $configured) === 1;
            $out[] = [
                'key'        => $key,
                'label'      => self::LABELS[$key],
                'url'        => $isListing ? $configured : self::SEARCH_URLS[$key],
                'is_listing' => $isListing,
            ];
        }

        return $out;
    }
}
