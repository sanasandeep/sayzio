<?php

namespace App\Modules\Common\Support;

use App\Modules\Admin\Models\AppSetting;

/**
 * Resolves the product screenshots shown on the marketing home page.
 *
 * Every visual slot on the page has a drawn fallback built into the view, so
 * the page renders correctly with nothing configured and a fresh deploy can
 * never ship a real customer account on the marketing site by accident.
 *
 * When marketing wants a real screenshot instead, they upload it in the admin
 * Asset Vault and paste its URL into the matching field on the Marketing
 * Settings page. The setting holds a URL, the same shape as
 * marketing_default_share_image, and takes effect within the AppSetting cache
 * window without a deploy. Clearing the field returns the slot to its drawing.
 *
 * Only http(s) URLs and site-root paths are accepted, so a stored value can
 * never turn into a javascript: or data: URI in an img src.
 */
class HomeShots
{
    /** Slot name to app setting key. The slot names are what views ask for. */
    public const KEYS = [
        'dashboard' => 'marketing_home_shot_dashboard',
        'links'     => 'marketing_home_shot_links',
        'menu'      => 'marketing_home_shot_menu',
    ];

    /** Human labels for the admin form, in the order they should appear. */
    public const LABELS = [
        'dashboard' => 'Dashboard screenshot',
        'links'     => 'My Links screenshot',
        'menu'      => 'Published page screenshot',
    ];

    /**
     * The URL configured for a slot, or null when the slot is unset, unknown
     * or holds something that is not a safe image URL. A null tells the view
     * to draw its own fallback.
     */
    public static function url(string $slot): ?string
    {
        $key = self::KEYS[$slot] ?? null;
        if ($key === null) {
            return null;
        }

        $stored = AppSetting::get($key, '');
        if (is_array($stored)) {
            $stored = reset($stored);
        }

        return self::sanitise((string) $stored);
    }

    /** True when the slot has a usable image and the view should show it. */
    public static function has(string $slot): bool
    {
        return self::url($slot) !== null;
    }

    /** Every slot's current URL, keyed by slot name, for the admin form. */
    public static function all(): array
    {
        $out = [];
        foreach (array_keys(self::KEYS) as $slot) {
            $out[$slot] = self::url($slot) ?? '';
        }

        return $out;
    }

    /**
     * Accepts an absolute http(s) URL or a site-root path such as
     * /storage/admin-assets/dashboard.png. Everything else, including empty
     * strings and any other scheme, resolves to null.
     */
    public static function sanitise(string $raw): ?string
    {
        $value = trim($raw);
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, '/') && ! str_starts_with($value, '//')) {
            return $value;
        }

        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $value : null;
    }
}
