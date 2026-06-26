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

    public const LAYOUTS   = ['modal', 'banner', 'corner', 'inline', 'pill', 'takeover'];
    public const POSITIONS = [
        'bottom-center', 'bottom-left', 'bottom-right',
        'top-center', 'top-left', 'top-right',
        'middle-left', 'middle-right',
    ];
    public const SIZES        = ['compact', 'default', 'wide'];
    public const BTN_STYLES   = ['solid', 'outline', 'link'];
    public const ANIMATIONS   = ['none', 'fade', 'slide-up', 'slide-down'];
    public const THEMES       = ['auto', 'light', 'dark'];
    public const GEO_SCOPES   = ['all', 'eu', 'custom'];

    /** Layouts where the position picker is meaningful. */
    public const POSITIONABLE_LAYOUTS = ['banner', 'corner', 'pill'];

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
            'layout'              => 'banner',
            'position'            => 'bottom-center',
            'size'                => 'default', // 'compact' | 'default' | 'wide'
            'max_width'           => 440,       // px clamp for non-takeover layouts
            'radius'              => 16,        // border-radius in px
            'theme'               => 'auto',
            'accent'              => '#3d6bff',

            // Independent button styling. Each entry: bg, text, style.
            'buttons' => [
                'primary'   => ['bg' => '#3d6bff', 'text' => '#ffffff', 'style' => 'solid'],
                'secondary' => ['bg' => '#ffffff', 'text' => '#111827', 'style' => 'outline'],
                'tertiary'  => ['bg' => '#3d6bff', 'text' => '#3d6bff', 'style' => 'link'],
            ],

            // Backdrop (modal / takeover layouts).
            'backdrop' => [
                'show' => true,
                'dim'  => 55,    // 0..100, alpha % for the dimming layer
                'blur' => false, // backdrop blur on/off
            ],

            // Per-surface layout/position overrides. Empty string = inherit.
            'surface_overrides' => [
                'site'    => ['layout' => '', 'position' => ''],
                'biolink' => ['layout' => '', 'position' => ''],
            ],

            'animation'      => 'fade', // none | fade | slide-up | slide-down
            'entrance_delay' => 0,      // seconds, 0..30

            // Optional header brand image inside the prompt.
            'header_logo_enabled' => false,
            'header_logo_url'     => '',

            'show_policy_link' => true,

            // Legacy floating reopen icon — retired in favor of footer link.
            // Defaults to false so existing tenants don't suddenly see it.
            'show_reopen_button'  => false,

            'copy' => [
                'title'             => 'We use cookies',
                'body'              => 'We use cookies to keep this site running, understand how it is used, and (with your permission) to power analytics and marketing. Choose what you are happy with.',
                'accept_all'        => 'Accept all',
                'reject_all'        => 'Reject all',
                'customize'         => 'Customize',
                'save'              => 'Save preferences',
                'policy_link_label' => 'Cookie policy',
                'policy_link_url'   => '/cookies',
                'reopen_link_label' => 'Cookie preferences',
            ],
            // Optional per-locale overrides for the visitor copy block. Keys are
            // BCP-47 tags (e.g. 'fr', 'pt-BR'); each entry is a partial copy
            // map. Missing fields fall back to the default `copy` above, so
            // existing single-string installs keep working untouched.
            'copy_locales' => [],
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
     * Resolve the effective layout/position for a given surface, applying
     * any per-surface override on top of the global layout/position.
     */
    public static function effectiveFor(array $cfg, string $surface): array
    {
        $ovr = $cfg['surface_overrides'][$surface] ?? [];
        $layout = !empty($ovr['layout']) && in_array($ovr['layout'], self::LAYOUTS, true)
            ? $ovr['layout'] : $cfg['layout'];
        $position = !empty($ovr['position']) && in_array($ovr['position'], self::POSITIONS, true)
            ? $ovr['position'] : $cfg['position'];
        return ['layout' => $layout, 'position' => $position];
    }

    /**
     * Validate + clean an incoming admin payload before it is persisted.
     * Anything missing falls back to the current value (or defaults).
     */
    public static function normalize(array $in): array
    {
        $cur = self::get();

        $geoScope = $in['geo_scope'] ?? $cur['geo_scope'];
        $layout   = $in['layout']    ?? $cur['layout'];
        $position = $in['position']  ?? $cur['position'];
        $theme    = $in['theme']     ?? $cur['theme'];
        $size     = $in['size']      ?? $cur['size'];
        $anim     = $in['animation'] ?? $cur['animation'];

        $out = [
            'enabled'             => self::bool($in['enabled'] ?? $cur['enabled']),
            'scope_marketing'     => self::bool($in['scope_marketing'] ?? $cur['scope_marketing']),
            'scope_biolink'       => self::bool($in['scope_biolink'] ?? $cur['scope_biolink']),
            'policy_version'      => max(1, (int) ($in['policy_version'] ?? $cur['policy_version'])),
            'remember_days'       => max(1, min(730, (int) ($in['remember_days'] ?? $cur['remember_days']))),
            'reprompt_on_change'  => self::bool($in['reprompt_on_change'] ?? $cur['reprompt_on_change']),
            'geo_scope'           => in_array($geoScope, self::GEO_SCOPES, true) ? $geoScope : 'all',
            'geo_countries'       => self::countryList($in['geo_countries'] ?? $cur['geo_countries']),
            'scroll_acceptance'   => self::bool($in['scroll_acceptance'] ?? $cur['scroll_acceptance']),
            'block_until_consent' => self::bool($in['block_until_consent'] ?? $cur['block_until_consent']),
            'layout'              => in_array($layout, self::LAYOUTS, true) ? $layout : 'modal',
            'position'            => in_array($position, self::POSITIONS, true) ? $position : 'bottom-center',
            'size'                => in_array($size, self::SIZES, true) ? $size : 'default',
            'max_width'           => max(280, min(960, (int) ($in['max_width'] ?? $cur['max_width']))),
            'radius'              => max(0, min(40, (int) ($in['radius'] ?? $cur['radius']))),
            'theme'               => in_array($theme, self::THEMES, true) ? $theme : 'auto',
            'accent'              => self::color($in['accent'] ?? $cur['accent']),
            'buttons'             => self::normalizeButtons((array) ($in['buttons'] ?? $cur['buttons']), $cur['buttons']),
            'backdrop'            => self::normalizeBackdrop((array) ($in['backdrop'] ?? $cur['backdrop']), $cur['backdrop']),
            'surface_overrides'   => self::normalizeSurfaceOverrides((array) ($in['surface_overrides'] ?? $cur['surface_overrides'])),
            'animation'           => in_array($anim, self::ANIMATIONS, true) ? $anim : 'fade',
            'entrance_delay'      => max(0, min(30, (int) ($in['entrance_delay'] ?? $cur['entrance_delay']))),
            'header_logo_enabled' => self::bool($in['header_logo_enabled'] ?? $cur['header_logo_enabled']),
            'header_logo_url'     => self::url($in['header_logo_url'] ?? $cur['header_logo_url']),
            'show_policy_link'    => self::bool($in['show_policy_link'] ?? $cur['show_policy_link']),
            'show_reopen_button'  => self::bool($in['show_reopen_button'] ?? $cur['show_reopen_button']),
            'copy'                => self::normalizeCopy((array) ($in['copy'] ?? $cur['copy'])),
            'copy_locales'        => self::normalizeCopyLocales((array) ($in['copy_locales'] ?? $cur['copy_locales'] ?? [])),
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
        return preg_match('/^#[0-9a-fA-F]{3,8}$/', $v) ? $v : '#3d6bff';
    }

    private static function url($v): string
    {
        $v = trim((string) $v);
        if ($v === '') return '';
        if (preg_match('#^(/|https?://|data:image/)#i', $v)) return mb_substr($v, 0, 2000);
        return '';
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

    private static function normalizeButtons(array $in, array $cur): array
    {
        $roles = ['primary', 'secondary', 'tertiary'];
        $out = [];
        foreach ($roles as $role) {
            $row = (array) ($in[$role] ?? $cur[$role] ?? []);
            $curRow = (array) ($cur[$role] ?? []);
            $style = $row['style'] ?? ($curRow['style'] ?? 'solid');
            $out[$role] = [
                'bg'    => self::color($row['bg']   ?? ($curRow['bg']   ?? '#3d6bff')),
                'text'  => self::color($row['text'] ?? ($curRow['text'] ?? '#ffffff')),
                'style' => in_array($style, self::BTN_STYLES, true) ? $style : 'solid',
            ];
        }
        return $out;
    }

    private static function normalizeBackdrop(array $in, array $cur): array
    {
        return [
            'show' => self::bool($in['show'] ?? $cur['show'] ?? true),
            'dim'  => max(0, min(100, (int) ($in['dim'] ?? $cur['dim'] ?? 55))),
            'blur' => self::bool($in['blur'] ?? $cur['blur'] ?? false),
        ];
    }

    private static function normalizeSurfaceOverrides(array $in): array
    {
        $out = [];
        foreach (['site', 'biolink'] as $surface) {
            $row = (array) ($in[$surface] ?? []);
            $layout   = (string) ($row['layout']   ?? '');
            $position = (string) ($row['position'] ?? '');
            $out[$surface] = [
                'layout'   => in_array($layout, self::LAYOUTS, true) ? $layout : '',
                'position' => in_array($position, self::POSITIONS, true) ? $position : '',
            ];
        }
        return $out;
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

    /**
     * Validate the per-locale copy overrides. Locale keys are canonicalised
     * to BCP-47 form (lowercase primary + uppercase region, e.g. `pt-BR`).
     * Unknown / malformed keys are dropped silently. Empty per-key values
     * are stripped so they fall back to the default copy at render time.
     */
    public static function normalizeCopyLocales(array $in): array
    {
        $allowedKeys = array_keys(self::defaults()['copy']);
        $caps = [
            'title'             => 200,
            'body'              => 2000,
            'accept_all'        => 60,
            'reject_all'        => 60,
            'customize'         => 60,
            'save'              => 60,
            'policy_link_label' => 80,
            'policy_link_url'   => 500, // not localised in the form, but accepted if posted
            'reopen_link_label' => 80,
        ];
        $out = [];
        foreach ($in as $code => $row) {
            if (!is_array($row)) continue;
            $canon = self::canonicalLocale((string) $code);
            if ($canon === null) continue;
            $entry = [];
            foreach ($allowedKeys as $k) {
                if (!array_key_exists($k, $row)) continue;
                $val = trim((string) $row[$k]);
                if ($val === '') continue;
                $entry[$k] = mb_substr($val, 0, $caps[$k] ?? 200);
            }
            if (!empty($entry)) {
                $out[$canon] = $entry;
            }
            if (count($out) >= 50) break;
        }
        ksort($out);
        return $out;
    }

    /**
     * Canonicalise a locale tag to lowercase primary + uppercase region.
     * Returns null if the input doesn't look like a BCP-47 language tag.
     */
    public static function canonicalLocale(string $code): ?string
    {
        $code = trim($code);
        if ($code === '') return null;
        if (!preg_match('/^([a-zA-Z]{2,3})(?:[-_]([a-zA-Z]{2,4}))?$/', $code, $m)) {
            return null;
        }
        $primary = strtolower($m[1]);
        if (!empty($m[2])) {
            return $primary . '-' . strtoupper($m[2]);
        }
        return $primary;
    }

    /**
     * Pick the best available locale for a visitor given an Accept-Language
     * header value. Tries exact match first (case-insensitive), then falls
     * back to a primary-subtag match (e.g. `pt-BR` → `pt`, or `pt` → `pt-BR`
     * if only the regional variant is configured). Returns null if nothing
     * matches or the header is empty.
     */
    public static function pickLocale(array $available, ?string $acceptLanguage): ?string
    {
        if (empty($available) || !$acceptLanguage) return null;

        $availMap = [];      // lowercased tag => canonical tag
        $availPrimary = [];  // primary subtag => first canonical tag in order
        foreach ($available as $code) {
            $availMap[strtolower($code)] = $code;
            $primary = strtolower(explode('-', $code)[0]);
            if (!isset($availPrimary[$primary])) $availPrimary[$primary] = $code;
        }

        $entries = [];
        foreach (explode(',', $acceptLanguage) as $part) {
            $part = trim($part);
            if ($part === '') continue;
            $q = 1.0;
            $tag = $part;
            if (strpos($part, ';') !== false) {
                [$tag, $params] = explode(';', $part, 2);
                $tag = trim($tag);
                if (preg_match('/q=([0-9.]+)/', $params, $qm)) {
                    $q = (float) $qm[1];
                }
            }
            if ($tag === '*' || $q <= 0) continue;
            if (!preg_match('/^[a-zA-Z]{1,8}(-[a-zA-Z0-9]{1,8})*$/', $tag)) continue;
            $entries[] = [$tag, $q];
        }
        usort($entries, fn($a, $b) => $b[1] <=> $a[1]);

        foreach ($entries as [$tag, $q]) {
            $low = strtolower($tag);
            if (isset($availMap[$low])) return $availMap[$low];
            $primary = explode('-', $low)[0];
            if (isset($availPrimary[$primary])) return $availPrimary[$primary];
        }
        return null;
    }

    /**
     * Resolve the copy block to render for a given visitor by overlaying any
     * matching per-locale entry on top of the admin-defined defaults. When
     * no locale matches (or none are configured), the defaults are returned
     * unchanged — preserving behaviour for single-string installs.
     *
     * If $acceptLanguage is null, the current request's Accept-Language is
     * used (when available), so view templates can call this with just $cfg.
     */
    public static function copyFor(array $cfg, ?string $acceptLanguage = null): array
    {
        $copy = (array) ($cfg['copy'] ?? self::defaults()['copy']);
        $locales = (array) ($cfg['copy_locales'] ?? []);
        if (empty($locales)) return $copy;

        if ($acceptLanguage === null && function_exists('request')) {
            try {
                $acceptLanguage = (string) (request()->server('HTTP_ACCEPT_LANGUAGE') ?? '');
            } catch (\Throwable $e) {
                $acceptLanguage = '';
            }
        }
        if ($acceptLanguage === '' || $acceptLanguage === null) return $copy;

        $picked = self::pickLocale(array_keys($locales), $acceptLanguage);
        if ($picked === null) return $copy;

        $override = (array) ($locales[$picked] ?? []);
        foreach ($copy as $k => $v) {
            if (isset($override[$k]) && $override[$k] !== '') {
                $copy[$k] = $override[$k];
            }
        }
        return $copy;
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
        if (isset($stored['copy_locales']) && is_array($stored['copy_locales'])) {
            $out['copy_locales'] = self::normalizeCopyLocales($stored['copy_locales']);
        } else {
            $out['copy_locales'] = [];
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
