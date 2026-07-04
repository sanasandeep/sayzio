<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\User;
use Illuminate\Support\Carbon;

/**
 * Task #3525 — the 5 curated dashboard presets, plus persistence of the
 * user's chosen layout (preset or AI-designed custom selection) into the
 * existing `users.settings` JSON column, mirroring the
 * `persona_banner_dismissed_at` / `whatsapp_prompt_dismissed_at` pattern
 * (no new migration).
 *
 * Default (no `dashboard_layout` key yet): every widget, i.e. today's exact
 * dashboard — nobody sees a visual change until they explicitly opt into a
 * preset or an AI-designed layout.
 */
class DashboardPresets
{
    public const SETTINGS_KEY = 'dashboard_layout';

    public const DEFAULT_PRESET = 'overview';

    /**
     * @var array<string, array{label:string, description:string, icon:string, widgets:list<string>}>
     */
    public const PRESETS = [
        'overview' => [
            'label'       => 'Overview',
            'description' => 'The full command-center view — everything at a glance. (default)',
            'icon'        => 'fa-gauge-high',
            'widgets'     => [
                'stat_total_clicks', 'stat_today', 'stat_plan', 'stat_links', 'stat_projects',
                'recent_links', 'quick_actions', 'plan_detail',
                'traffic_channels',
                'backlinks', 'coin_balance',
            ],
        ],
        'growth_traffic' => [
            'label'       => 'Growth & Traffic',
            'description' => 'Where your clicks come from and how they trend.',
            'icon'        => 'fa-arrow-trend-up',
            'widgets'     => [
                'stat_total_clicks', 'stat_today', 'traffic_channels', 'backlinks',
                'coin_balance', 'recent_links', 'quick_actions',
            ],
        ],
        'content_posts' => [
            'label'       => 'Content & Posts',
            'description' => 'Focused on the links and projects you publish.',
            'icon'        => 'fa-folder-open',
            'widgets'     => [
                'stat_links', 'stat_projects', 'stat_today', 'recent_links', 'quick_actions',
            ],
        ],
        'monetization' => [
            'label'       => 'Monetization',
            'description' => 'Your plan, AI wallet, and earnings-adjacent numbers.',
            'icon'        => 'fa-gem',
            'widgets'     => [
                'stat_plan', 'plan_detail', 'coin_balance', 'stat_total_clicks', 'quick_actions',
            ],
        ],
        'audience_followers' => [
            'label'       => 'Audience & Followers',
            'description' => 'Who is finding you and where they come from.',
            'icon'        => 'fa-users',
            'widgets'     => [
                'recent_links', 'stat_links', 'backlinks', 'traffic_channels',
                'stat_today', 'quick_actions',
            ],
        ],
    ];

    /** @return list<string> */
    public static function presetKeys(): array
    {
        return array_keys(self::PRESETS);
    }

    public static function isValidPreset(string $key): bool
    {
        return array_key_exists($key, self::PRESETS);
    }

    /** @return list<string> */
    public static function widgetsForPreset(string $key): array
    {
        return self::PRESETS[$key]['widgets'] ?? self::PRESETS[self::DEFAULT_PRESET]['widgets'];
    }

    /**
     * Frontend-friendly preset payload for the "Customize dashboard" picker
     * and its mobile equivalent.
     *
     * @return list<array{key:string,label:string,description:string,icon:string,widgets:list<string>}>
     */
    public static function forFrontend(): array
    {
        $out = [];
        foreach (self::PRESETS as $key => $meta) {
            $out[] = array_merge(['key' => $key], $meta);
        }
        return $out;
    }

    /**
     * Resolve a user's active layout. Falls back to the default preset
     * (today's full dashboard) whenever no choice has been made yet, or a
     * stored preset key has since been retired.
     *
     * @return array{preset: ?string, is_custom: bool, widgets: list<string>, source: ?string}
     */
    public static function resolveFor(User $user): array
    {
        $stored = $user->settings[self::SETTINGS_KEY] ?? null;

        if (is_array($stored) && !empty($stored['custom']) && is_array($stored['widgets'] ?? null)) {
            $widgets = DashboardWidgetCatalog::sanitize($stored['widgets']);
            if (!empty($widgets)) {
                return [
                    'preset'    => null,
                    'is_custom' => true,
                    'widgets'   => $widgets,
                    'source'    => $stored['source'] ?? 'ai',
                ];
            }
        }

        $presetKey = is_array($stored) ? ($stored['preset'] ?? null) : null;
        if (!is_string($presetKey) || !self::isValidPreset($presetKey)) {
            $presetKey = self::DEFAULT_PRESET;
        }

        return [
            'preset'    => $presetKey,
            'is_custom' => false,
            'widgets'   => self::widgetsForPreset($presetKey),
            'source'    => null,
        ];
    }

    /** Persist a preset choice for the user. */
    public static function applyPreset(User $user, string $presetKey): void
    {
        $settings = $user->settings ?? [];
        $settings[self::SETTINGS_KEY] = [
            'preset'     => $presetKey,
            'updated_at' => now()->toIso8601String(),
        ];
        $user->forceFill(['settings' => $settings])->save();
    }

    /**
     * Persist an AI-designed custom widget selection for the user.
     *
     * @param  list<string>  $widgets
     */
    public static function applyCustom(User $user, array $widgets, string $source = 'ai'): void
    {
        $sanitized = DashboardWidgetCatalog::sanitize($widgets);
        if (empty($sanitized)) {
            $sanitized = self::widgetsForPreset(self::DEFAULT_PRESET);
        }

        $settings = $user->settings ?? [];
        $settings[self::SETTINGS_KEY] = [
            'custom'     => true,
            'widgets'    => $sanitized,
            'source'     => $source,
            'updated_at' => now()->toIso8601String(),
        ];
        $user->forceFill(['settings' => $settings])->save();
    }
}
