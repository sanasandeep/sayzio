<?php

namespace App\Modules\User\Support;

/**
 * Task #3525 — single source of truth for the widgets the `/user/dashboard`
 * customize picker (and the AI designer) may pick from. Every key here maps
 * 1:1 to an existing block already rendered by
 * `user/dashboard/index.blade.php` — no new metrics were introduced.
 *
 * Widgets are grouped under the tab they live in so the view can hide a tab
 * entirely once none of its widgets are selected in the active layout.
 */
class DashboardWidgetCatalog
{
    public const TAB_OVERVIEW = 'overview';
    public const TAB_TRAFFIC = 'traffic';
    public const TAB_GROWTH = 'growth';

    /**
     * @var array<string, array{label:string, description:string, icon:string, tab:string}>
     */
    public const WIDGETS = [
        'stat_total_clicks' => [
            'label'       => 'Total Clicks',
            'description' => 'Lifetime click count, tall feature tile.',
            'icon'        => 'fa-mouse-pointer',
            'tab'         => self::TAB_OVERVIEW,
        ],
        'stat_today' => [
            'label'       => 'Clicks Today',
            'description' => 'How many clicks landed today.',
            'icon'        => 'fa-chart-line',
            'tab'         => self::TAB_OVERVIEW,
        ],
        'stat_plan' => [
            'label'       => 'Plan Snapshot',
            'description' => 'Your current plan name and price.',
            'icon'        => 'fa-crown',
            'tab'         => self::TAB_OVERVIEW,
        ],
        'stat_links' => [
            'label'       => 'Links Count',
            'description' => 'Total number of links you have created.',
            'icon'        => 'fa-link',
            'tab'         => self::TAB_OVERVIEW,
        ],
        'stat_projects' => [
            'label'       => 'Projects Count',
            'description' => 'Total number of projects.',
            'icon'        => 'fa-folder',
            'tab'         => self::TAB_OVERVIEW,
        ],
        'recent_links' => [
            'label'       => 'Recent Links',
            'description' => 'Your most recently created links, with quick access to each.',
            'icon'        => 'fa-clock',
            'tab'         => self::TAB_OVERVIEW,
        ],
        'quick_actions' => [
            'label'       => 'Quick Actions',
            'description' => 'One-tap shortcuts to create a link, project, tracker or QR code.',
            'icon'        => 'fa-bolt',
            'tab'         => self::TAB_OVERVIEW,
        ],
        'plan_detail' => [
            'label'       => 'Your Plan (detail)',
            'description' => 'Plan usage bar against your links limit.',
            'icon'        => 'fa-gem',
            'tab'         => self::TAB_OVERVIEW,
        ],
        'traffic_channels' => [
            'label'       => 'Channel Breakdown',
            'description' => 'What share of your traffic comes from browsers, in-app webviews, and bots.',
            'icon'        => 'fa-chart-pie',
            'tab'         => self::TAB_TRAFFIC,
        ],
        'backlinks' => [
            'label'       => 'Backlink Radar',
            'description' => 'New pages around the web linking back to your properties this week.',
            'icon'        => 'fa-bullseye',
            'tab'         => self::TAB_GROWTH,
        ],
        'coin_balance' => [
            'label'       => 'Coin Balance',
            'description' => 'Your AI coin wallet balance (only shown while the AI engine is on).',
            'icon'        => 'fa-brain',
            'tab'         => self::TAB_GROWTH,
        ],
    ];

    /** @return list<string> */
    public static function allKeys(): array
    {
        return array_keys(self::WIDGETS);
    }

    public static function isValid(string $key): bool
    {
        return array_key_exists($key, self::WIDGETS);
    }

    public static function tabFor(string $key): ?string
    {
        return self::WIDGETS[$key]['tab'] ?? null;
    }

    /**
     * Filter + de-dupe an arbitrary list down to valid catalog keys only,
     * preserving the given order. Used to sanitize both preset definitions
     * and AI designer output before they're ever persisted or rendered.
     *
     * @param  list<mixed>  $keys
     * @return list<string>
     */
    public static function sanitize(array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            if (is_string($key) && self::isValid($key) && !in_array($key, $out, true)) {
                $out[] = $key;
            }
        }
        return $out;
    }

    /**
     * @param  list<string>  $widgets
     * @return array{overview:bool, traffic:bool, growth:bool}
     */
    public static function tabVisibility(array $widgets): array
    {
        $tabs = ['overview' => false, 'traffic' => false, 'growth' => false];
        foreach ($widgets as $key) {
            $tab = self::tabFor($key);
            if ($tab !== null) {
                $tabs[$tab] = true;
            }
        }
        return $tabs;
    }

    /**
     * Frontend-friendly catalog payload (ordered, with tab grouping) used by
     * both the web "Customize dashboard" modal and the mobile API.
     *
     * @return list<array{key:string,label:string,description:string,icon:string,tab:string}>
     */
    public static function forFrontend(): array
    {
        $out = [];
        foreach (self::WIDGETS as $key => $meta) {
            $out[] = array_merge(['key' => $key], $meta);
        }
        return $out;
    }
}
