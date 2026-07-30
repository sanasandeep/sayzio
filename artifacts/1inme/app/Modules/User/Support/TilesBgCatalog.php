<?php

namespace App\Modules\User\Support;

/**
 * "Tiles" background type (Task #6204): a full-page metro-style grid of
 * gradient tiles. Only palette + layout KEYS are stored in link settings;
 * the tile gradients and grid spans are always resolved server-side from
 * this catalog, never from client input. The optional animation is a
 * gentle opacity pulse that is disabled under prefers-reduced-motion.
 */
class TilesBgCatalog
{
    public const LAYOUTS = [
        'uniform' => 'Uniform grid',
        'metro'   => 'Metro mix',
        'brick'   => 'Brick rows',
    ];

    public const TILE_COUNT = 24;

    /**
     * Each palette: a cycling list of tile gradient CSS values (safe,
     * curated `linear-gradient(...)` strings only).
     *
     * @var array<string, array{label: string, tiles: list<string>, colors: list<string>}>
     */
    private const PALETTES = [
        'tiles_midnight' => ['label' => 'Midnight', 'colors' => ['#1e293b', '#3b82f6', '#0f172a'], 'tiles' => [
            'linear-gradient(135deg, #1e293b, #0f172a)', 'linear-gradient(135deg, #1d4ed8, #1e3a8a)',
            'linear-gradient(135deg, #334155, #1e293b)', 'linear-gradient(135deg, #0ea5e9, #0369a1)',
            'linear-gradient(135deg, #312e81, #1e1b4b)', 'linear-gradient(135deg, #475569, #1f2937)',
        ]],
        'tiles_sunset' => ['label' => 'Sunset', 'colors' => ['#f97316', '#db2777', '#7c2d12'], 'tiles' => [
            'linear-gradient(135deg, #fb923c, #ea580c)', 'linear-gradient(135deg, #f43f5e, #be123c)',
            'linear-gradient(135deg, #fbbf24, #d97706)', 'linear-gradient(135deg, #db2777, #831843)',
            'linear-gradient(135deg, #c2410c, #7c2d12)', 'linear-gradient(135deg, #fda4af, #e11d48)',
        ]],
        'tiles_forest' => ['label' => 'Forest', 'colors' => ['#16a34a', '#065f46', '#365314'], 'tiles' => [
            'linear-gradient(135deg, #22c55e, #15803d)', 'linear-gradient(135deg, #0d9488, #115e59)',
            'linear-gradient(135deg, #84cc16, #4d7c0f)', 'linear-gradient(135deg, #065f46, #022c22)',
            'linear-gradient(135deg, #4ade80, #16a34a)', 'linear-gradient(135deg, #365314, #1a2e05)',
        ]],
        'tiles_berry' => ['label' => 'Berry', 'colors' => ['#a21caf', '#7c3aed', '#4a044e'], 'tiles' => [
            'linear-gradient(135deg, #c026d3, #86198f)', 'linear-gradient(135deg, #8b5cf6, #6d28d9)',
            'linear-gradient(135deg, #ec4899, #be185d)', 'linear-gradient(135deg, #6b21a8, #3b0764)',
            'linear-gradient(135deg, #d946ef, #a21caf)', 'linear-gradient(135deg, #4c1d95, #2e1065)',
        ]],
        'tiles_ocean' => ['label' => 'Ocean', 'colors' => ['#0891b2', '#0e7490', '#164e63'], 'tiles' => [
            'linear-gradient(135deg, #22d3ee, #0891b2)', 'linear-gradient(135deg, #0ea5e9, #0369a1)',
            'linear-gradient(135deg, #2dd4bf, #0f766e)', 'linear-gradient(135deg, #155e75, #164e63)',
            'linear-gradient(135deg, #38bdf8, #0284c7)', 'linear-gradient(135deg, #075985, #0c4a6e)',
        ]],
        'tiles_mono' => ['label' => 'Mono', 'colors' => ['#404040', '#737373', '#171717'], 'tiles' => [
            'linear-gradient(135deg, #525252, #262626)', 'linear-gradient(135deg, #737373, #404040)',
            'linear-gradient(135deg, #a3a3a3, #525252)', 'linear-gradient(135deg, #262626, #0a0a0a)',
            'linear-gradient(135deg, #404040, #171717)', 'linear-gradient(135deg, #8a8a8a, #3f3f46)',
        ]],
        'tiles_pastel' => ['label' => 'Pastel', 'colors' => ['#fbcfe8', '#bfdbfe', '#fde68a'], 'tiles' => [
            'linear-gradient(135deg, #fbcfe8, #f9a8d4)', 'linear-gradient(135deg, #bfdbfe, #93c5fd)',
            'linear-gradient(135deg, #fde68a, #fcd34d)', 'linear-gradient(135deg, #bbf7d0, #86efac)',
            'linear-gradient(135deg, #ddd6fe, #c4b5fd)', 'linear-gradient(135deg, #fed7aa, #fdba74)',
        ]],
    ];

    /**
     * Per-layout [colSpan, rowSpan] cycles applied to the tile sequence.
     * The grid itself is 4 columns wide (see the public renderer CSS).
     *
     * @var array<string, list<array{int, int}>>
     */
    private const LAYOUT_SPANS = [
        'uniform' => [[1, 1]],
        'metro'   => [[2, 2], [1, 1], [1, 1], [2, 1], [1, 2], [1, 1], [2, 1], [1, 1]],
        'brick'   => [[2, 1], [2, 1], [1, 1], [2, 1], [1, 1], [2, 1]],
    ];

    /** @return array<string, array{label: string, tiles: list<string>, colors: list<string>}> */
    public static function palettes(): array
    {
        return self::PALETTES;
    }

    public static function isValidPalette(string $key): bool
    {
        return isset(self::PALETTES[$key]);
    }

    public static function isValidLayout(string $key): bool
    {
        return isset(self::LAYOUTS[$key]);
    }

    /**
     * Fully-resolved tile list for the renderer: TILE_COUNT entries of
     * ['css' => gradient, 'col' => span, 'row' => span].
     *
     * @return list<array{css: string, col: int, row: int}>
     */
    public static function tiles(string $palette, string $layout): array
    {
        $p = self::PALETTES[$palette] ?? null;
        if (!$p) {
            return [];
        }
        $spans = self::LAYOUT_SPANS[$layout] ?? self::LAYOUT_SPANS['uniform'];
        $out = [];
        for ($i = 0; $i < self::TILE_COUNT; $i++) {
            [$col, $row] = $spans[$i % count($spans)];
            $out[] = [
                'css' => $p['tiles'][$i % count($p['tiles'])],
                'col' => $col,
                'row' => $row,
            ];
        }
        return $out;
    }

    /** @return list<string> representative colors for the mobile fallback */
    public static function colors(string $palette): array
    {
        return self::PALETTES[$palette]['colors'] ?? [];
    }
}
