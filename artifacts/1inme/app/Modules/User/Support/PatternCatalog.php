<?php

namespace App\Modules\User\Support;

/**
 * Geometric pattern background presets (Task #6204). Only the preset KEY
 * is stored in link settings; the CSS is always resolved server-side from
 * this catalog, never from client input.
 */
class PatternCatalog
{
    /**
     * @var array<string, array{label: string, css: string, colors: list<string>}>
     */
    private const PRESETS = [
        'pattern_dots_dark' => ['label' => 'Dots Dark', 'colors' => ['#111827', '#374151'],
            'css' => 'background-color: #111827;background-image: radial-gradient(rgba(148,163,184,0.35) 1.5px, transparent 1.5px);background-size: 22px 22px'],
        'pattern_dots_light' => ['label' => 'Dots Light', 'colors' => ['#f8fafc', '#cbd5e1'],
            'css' => 'background-color: #f8fafc;background-image: radial-gradient(rgba(71,85,105,0.35) 1.5px, transparent 1.5px);background-size: 22px 22px'],
        'pattern_grid_dark' => ['label' => 'Grid Dark', 'colors' => ['#0f172a', '#334155'],
            'css' => 'background-color: #0f172a;background-image: linear-gradient(rgba(148,163,184,0.16) 1px, transparent 1px), linear-gradient(90deg, rgba(148,163,184,0.16) 1px, transparent 1px);background-size: 32px 32px'],
        'pattern_grid_light' => ['label' => 'Grid Light', 'colors' => ['#ffffff', '#dbeafe'],
            'css' => 'background-color: #ffffff;background-image: linear-gradient(rgba(59,130,246,0.14) 1px, transparent 1px), linear-gradient(90deg, rgba(59,130,246,0.14) 1px, transparent 1px);background-size: 32px 32px'],
        'pattern_stripes_indigo' => ['label' => 'Stripes Indigo', 'colors' => ['#1e1b4b', '#4338ca'],
            'css' => 'background-color: #1e1b4b;background-image: repeating-linear-gradient(45deg, rgba(129,140,248,0.16) 0px, rgba(129,140,248,0.16) 12px, transparent 12px, transparent 28px)'],
        'pattern_stripes_sand' => ['label' => 'Stripes Sand', 'colors' => ['#fef3c7', '#d97706'],
            'css' => 'background-color: #fef3c7;background-image: repeating-linear-gradient(45deg, rgba(217,119,6,0.14) 0px, rgba(217,119,6,0.14) 12px, transparent 12px, transparent 28px)'],
        'pattern_zigzag_teal' => ['label' => 'Zigzag Teal', 'colors' => ['#042f2e', '#14b8a6'],
            'css' => 'background-color: #042f2e;background-image: linear-gradient(135deg, rgba(45,212,191,0.22) 25%, transparent 25%), linear-gradient(225deg, rgba(45,212,191,0.22) 25%, transparent 25%), linear-gradient(45deg, rgba(45,212,191,0.22) 25%, transparent 25%), linear-gradient(315deg, rgba(45,212,191,0.22) 25%, transparent 25%);background-position: 14px 0, 14px 0, 0 0, 0 0;background-size: 28px 28px;background-repeat: repeat'],
        'pattern_waves_blue' => ['label' => 'Waves Blue', 'colors' => ['#0c4a6e', '#38bdf8'],
            'css' => 'background-color: #0c4a6e;background-image: repeating-radial-gradient(circle at 0 0, transparent 0, transparent 22px, rgba(125,211,252,0.16) 22px, rgba(125,211,252,0.16) 24px)'],
        'pattern_checker_mono' => ['label' => 'Checker Mono', 'colors' => ['#18181b', '#3f3f46'],
            'css' => 'background-color: #18181b;background-image: linear-gradient(45deg, rgba(161,161,170,0.14) 25%, transparent 25%, transparent 75%, rgba(161,161,170,0.14) 75%), linear-gradient(45deg, rgba(161,161,170,0.14) 25%, transparent 25%, transparent 75%, rgba(161,161,170,0.14) 75%);background-position: 0 0, 18px 18px;background-size: 36px 36px'],
        'pattern_crosshatch_rose' => ['label' => 'Crosshatch Rose', 'colors' => ['#4c0519', '#fb7185'],
            'css' => 'background-color: #4c0519;background-image: repeating-linear-gradient(45deg, rgba(251,113,133,0.14) 0px, rgba(251,113,133,0.14) 1px, transparent 1px, transparent 12px), repeating-linear-gradient(-45deg, rgba(251,113,133,0.14) 0px, rgba(251,113,133,0.14) 1px, transparent 1px, transparent 12px)'],
        'pattern_honeycomb_amber' => ['label' => 'Honeycomb Amber', 'colors' => ['#451a03', '#f59e0b'],
            'css' => 'background-color: #451a03;background-image: radial-gradient(circle farthest-side at 0% 50%, transparent 23.5%, rgba(245,158,11,0.18) 24%, rgba(245,158,11,0.18) 26%, transparent 26.5%), radial-gradient(circle farthest-side at 100% 50%, transparent 23.5%, rgba(245,158,11,0.18) 24%, rgba(245,158,11,0.18) 26%, transparent 26.5%);background-size: 48px 28px'],
        'pattern_diamonds_violet' => ['label' => 'Diamonds Violet', 'colors' => ['#2e1065', '#a78bfa'],
            'css' => 'background-color: #2e1065;background-image: linear-gradient(135deg, rgba(167,139,250,0.16) 25%, transparent 25%), linear-gradient(225deg, rgba(167,139,250,0.16) 25%, transparent 25%), linear-gradient(315deg, rgba(167,139,250,0.16) 25%, transparent 25%), linear-gradient(45deg, rgba(167,139,250,0.16) 25%, transparent 25%);background-size: 30px 30px'],
    ];

    /** @return array<string, array{label: string, css: string, colors: list<string>}> */
    public static function all(): array
    {
        return self::PRESETS;
    }

    public static function isValidKey(string $key): bool
    {
        return isset(self::PRESETS[$key]);
    }

    public static function css(string $key): ?string
    {
        return self::PRESETS[$key]['css'] ?? null;
    }

    /** @return list<string> */
    public static function colors(string $key): array
    {
        return self::PRESETS[$key]['colors'] ?? [];
    }
}
