<?php

namespace App\Modules\Common\Support;

use App\Modules\Admin\Models\AppSetting;

/**
 * Strongly-typed accessor + normalizer for the workspace-wide cookie
 * consent configuration. The blob is stored under a single AppSetting key
 * (`cookie_consent_config`) so views, middleware and the admin form all
 * read/write through one place.
 *
 * "Essential" is always implicit — it can never be turned off or removed
 * by the admin and is never reported as a category visitors must accept.
 */
class CookieConsentConfig
{
    public const SETTING_KEY = 'cookie_consent_config';

    /** EU/EEA + UK ISO-3166 alpha-2 codes used by the `eu` geo scope. */
    public const EU_COUNTRIES = [
        'AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','HU','IE',
        'IT','LV','LT','LU','MT','NL','PL','PT','RO','SK','SI','ES','SE',
        'IS','LI','NO','GB',
    ];

    public static function defaults(): array
    {
        return [
            'enabled'             => true,
            'scope_marketing'     => true,
            'scope_biolink'       => true,
            'policy_version'      => 1,
            'remember_days'       => 180,
            'reprompt_on_change'  => true,
            'geo_scope'           => 'all',     // 'all' | 'eu' | 'custom'
            'geo_countries'       => [],        // ISO codes when geo_scope === 'custom'
            'scroll_acceptance'   => false,
            'block_until_consent' => true,
            'layout'              => 'modal',   // 'modal' | 'banner' | 'corner'
            'position'            => 'bottom-center', // applies to banner/corner
            'theme'               => 'auto',    // 'auto' | 'light' | 'dark'
            'accent'              => '#7c3aed',
            'show_reopen_button'  => true,
            'copy' => [
                'title'             => 'We use cookies',
                'body'              => 'We use cookies to keep this site running, understand how it is used, and (with your permission) to power analytics and marketing. Choose what you are happy with.',
                'accept_all'        => 'Accept all',
                'reject_all'        => 'Reject all',
                'customize'         => 'Customize',
                'save'              => 'Save preferences',
                'policy_link_label' => 'Cookie policy',
                'policy_link_url'   => '/cookies',
            ],
            'categories' => [
                [
                    'id'          => 'analytics',
                    'name'        => 'Analytics',
                    'description' => 'Helps us understand which pages people visit and how the site performs, so we can improve it.',
                    'cookies'     => '_ga, _gid, _ga_*',
                    'default_on'  => false,
                ],
                [
                    'id'          => 'marketing',
                    'name'        => 'Marketing',
                    'description' => 'Used by advertising partners (e.g. Meta, TikTok, X) to measure campaigns and show relevant ads.',
                    'cookies'     => '_fbp, _tt_*, _li_*',
                    'default_on'  => false,
                ],
                [
                    'id'          => 'functional',
                    'name'        => 'Functional / Personalization',
                    'description' => 'Remembers preferences (language, region, embedded players) for a richer experience.',
                    'cookies'     => '',
                    'default_on'  => false,
                ],
            ],
        ];
    }

    /**
     * Read the current config, deep-merged with defaults so partial saves
     * never break a downstream consumer.
     */
    public static function get(): array
    {
        $stored = (array) (AppSetting::get(self::SETTING_KEY, []) ?? []);
        return self::merge(self::defaults(), $stored);
    }

    public static function put(array $data): void
    {
        AppSetting::put(self::SETTING_KEY, self::normalize($data));
    }

    /**
     * Validate + clean an incoming admin payload before it is persisted.
     * Anything missing falls back to the current value (or defaults).
     */
    public static function normalize(array $in): array
    {
        $cur = self::get();

        $allowedLayouts   = ['modal', 'banner', 'corner'];
        $allowedPositions = ['bottom-center', 'bottom-left', 'bottom-right', 'top-center'];
        $allowedThemes    = ['auto', 'light', 'dark'];
        $allowedScopes    = ['all', 'eu', 'custom'];

        // Resolve each field once into a local — avoids the previous
        // `in_array($in['x'] ?? $cur['x']) ? $in['x'] : default` pattern,
        // which could touch a missing $in[] index when only a partial
        // payload (e.g. just `categories`) is normalized.
        $geoScope = $in['geo_scope'] ?? $cur['geo_scope'];
        $layout   = $in['layout']    ?? $cur['layout'];
        $position = $in['position']  ?? $cur['position'];
        $theme    = $in['theme']     ?? $cur['theme'];

        $out = [
            'enabled'             => self::bool($in['enabled'] ?? $cur['enabled']),
            'scope_marketing'     => self::bool($in['scope_marketing'] ?? $cur['scope_marketing']),
            'scope_biolink'       => self::bool($in['scope_biolink'] ?? $cur['scope_biolink']),
            'policy_version'      => max(1, (int) ($in['policy_version'] ?? $cur['policy_version'])),
            'remember_days'       => max(1, min(730, (int) ($in['remember_days'] ?? $cur['remember_days']))),
            'reprompt_on_change'  => self::bool($in['reprompt_on_change'] ?? $cur['reprompt_on_change']),
            'geo_scope'           => in_array($geoScope, $allowedScopes, true) ? $geoScope : 'all',
            'geo_countries'       => self::countryList($in['geo_countries'] ?? $cur['geo_countries']),
            'scroll_acceptance'   => self::bool($in['scroll_acceptance'] ?? $cur['scroll_acceptance']),
            'block_until_consent' => self::bool($in['block_until_consent'] ?? $cur['block_until_consent']),
            'layout'              => in_array($layout, $allowedLayouts, true) ? $layout : 'modal',
            'position'            => in_array($position, $allowedPositions, true) ? $position : 'bottom-center',
            'theme'               => in_array($theme, $allowedThemes, true) ? $theme : 'auto',
            'accent'              => self::color($in['accent'] ?? $cur['accent']),
            'show_reopen_button'  => self::bool($in['show_reopen_button'] ?? $cur['show_reopen_button']),
            'copy'                => self::normalizeCopy((array) ($in['copy'] ?? $cur['copy'])),
            'categories'          => self::normalizeCategories((array) ($in['categories'] ?? $cur['categories'])),
        ];

        return $out;
    }

    private static function bool($v): bool
    {
        if (is_string($v)) return in_array(strtolower($v), ['1','true','on','yes'], true);
        return (bool) $v;
    }

    private static function color($v): string
    {
        $v = trim((string) $v);
        return preg_match('/^#[0-9a-fA-F]{3,8}$/', $v) ? $v : '#7c3aed';
    }

    private static function countryList($v): array
    {
        if (is_string($v)) {
            $v = preg_split('/[\s,]+/', $v) ?: [];
        }
        $codes = [];
        foreach ((array) $v as $c) {
            $c = strtoupper(trim((string) $c));
            if (preg_match('/^[A-Z]{2}$/', $c)) $codes[$c] = true;
        }
        return array_keys($codes);
    }

    private static function normalizeCopy(array $copy): array
    {
        $defaults = self::defaults()['copy'];
        $out = [];
        foreach ($defaults as $k => $default) {
            $val = trim((string) ($copy[$k] ?? ''));
            $out[$k] = $val !== '' ? $val : $default;
        }
        return $out;
    }

    private static function normalizeCategories(array $cats): array
    {
        $allowed = ['analytics', 'marketing', 'functional'];
        $byId = [];
        foreach ($cats as $row) {
            if (!is_array($row)) continue;
            $id = strtolower(trim((string) ($row['id'] ?? '')));
            if (!in_array($id, $allowed, true)) continue;
            $byId[$id] = [
                'id'          => $id,
                'name'        => trim((string) ($row['name'] ?? ucfirst($id))),
                'description' => trim((string) ($row['description'] ?? '')),
                'cookies'     => trim((string) ($row['cookies'] ?? '')),
                'default_on'  => self::bool($row['default_on'] ?? false),
            ];
        }
        // Preserve canonical order regardless of submission order.
        $out = [];
        foreach ($allowed as $id) {
            if (isset($byId[$id])) $out[] = $byId[$id];
        }
        return $out;
    }

    private static function merge(array $defaults, array $stored): array
    {
        $out = $defaults;
        foreach ($stored as $k => $v) {
            if (is_array($v) && isset($defaults[$k]) && is_array($defaults[$k]) && self::isAssoc($defaults[$k])) {
                $out[$k] = self::merge($defaults[$k], $v);
            } else {
                $out[$k] = $v;
            }
        }
        if (isset($stored['categories']) && is_array($stored['categories'])) {
            $out['categories'] = self::normalizeCategories($stored['categories']);
        }
        if (isset($stored['copy']) && is_array($stored['copy'])) {
            $out['copy'] = self::normalizeCopy(array_merge($defaults['copy'], $stored['copy']));
        }
        return $out;
    }

    private static function isAssoc(array $a): bool
    {
        if ($a === []) return false;
        return array_keys($a) !== range(0, count($a) - 1);
    }

    /**
     * Should the consent UI render for this request? Combines the
     * `enabled` flag and per-surface scope flags. Geo scoping is enforced
     * client-side too, but we short-circuit obvious cases here so we
     * don't ship config + markup we know will never display.
     */
    public static function shouldRender(string $surface): bool
    {
        $cfg = self::get();
        if (!$cfg['enabled']) return false;
        if ($surface === 'site' && !$cfg['scope_marketing']) return false;
        if ($surface === 'biolink' && !$cfg['scope_biolink']) return false;
        return true;
    }
}
