<?php

namespace App\Modules\User\Support;

/**
 * Mesh-gradient background presets (Task #6204): a solid base color with
 * several soft radial "blobs" layered on top, giving the blended
 * multi-point mesh look. Only the preset KEY is stored in link settings;
 * the CSS is always resolved server-side from this catalog, never from
 * client input.
 */
class MeshGradientCatalog
{
    /**
     * Each entry: base color + blobs [color, x%, y%, spread%].
     *
     * @var array<string, array{label: string, base: string, blobs: list<array{string, int, int, int}>}>
     */
    private const PRESETS = [
        'mesh_aurora' => ['label' => 'Aurora', 'base' => '#0b1026', 'blobs' => [
            ['#22d3ee', 15, 20, 55], ['#a78bfa', 80, 15, 50], ['#34d399', 70, 80, 55], ['#f472b6', 20, 85, 45],
        ]],
        'mesh_sunrise' => ['label' => 'Sunrise', 'base' => '#2b1055', 'blobs' => [
            ['#ff8a5c', 20, 80, 60], ['#ffd166', 50, 95, 45], ['#f25f8e', 80, 60, 55], ['#7357c4', 70, 10, 55],
        ]],
        'mesh_lagoon' => ['label' => 'Lagoon', 'base' => '#03252e', 'blobs' => [
            ['#0ea5e9', 25, 25, 55], ['#2dd4bf', 75, 30, 55], ['#a3e635', 85, 85, 45], ['#0369a1', 15, 80, 55],
        ]],
        'mesh_candy' => ['label' => 'Candy', 'base' => '#fdf2f8', 'blobs' => [
            ['#f9a8d4', 20, 20, 55], ['#c4b5fd', 80, 25, 55], ['#99f6e4', 70, 85, 50], ['#fde68a', 15, 80, 45],
        ]],
        'mesh_ember' => ['label' => 'Ember', 'base' => '#1c0a06', 'blobs' => [
            ['#f97316', 25, 75, 55], ['#ef4444', 75, 65, 55], ['#facc15', 55, 95, 40], ['#7c2d12', 80, 15, 55],
        ]],
        'mesh_orchid' => ['label' => 'Orchid', 'base' => '#170b2b', 'blobs' => [
            ['#c026d3', 25, 25, 55], ['#8b5cf6', 75, 20, 55], ['#ec4899', 80, 80, 50], ['#312e81', 15, 80, 55],
        ]],
        'mesh_glacier' => ['label' => 'Glacier', 'base' => '#eef6fb', 'blobs' => [
            ['#93c5fd', 20, 25, 55], ['#a5f3fc', 75, 20, 55], ['#c7d2fe', 75, 85, 50], ['#e0f2fe', 20, 80, 45],
        ]],
        'mesh_forest' => ['label' => 'Forest', 'base' => '#0a1f12', 'blobs' => [
            ['#16a34a', 25, 30, 55], ['#84cc16', 75, 20, 45], ['#0d9488', 75, 80, 55], ['#365314', 15, 85, 50],
        ]],
        'mesh_noir' => ['label' => 'Noir', 'base' => '#0a0a0a', 'blobs' => [
            ['#404040', 25, 20, 55], ['#525b6b', 80, 30, 50], ['#1f2937', 70, 85, 55], ['#312e3f', 15, 80, 45],
        ]],
        'mesh_peach' => ['label' => 'Peach', 'base' => '#fff7ed', 'blobs' => [
            ['#fdba74', 20, 25, 55], ['#fda4af', 80, 20, 50], ['#fcd34d', 75, 85, 45], ['#fecaca', 20, 80, 50],
        ]],
    ];

    /** @return array<string, array{label: string, base: string, blobs: list<array{string, int, int, int}>}> */
    public static function all(): array
    {
        return self::PRESETS;
    }

    public static function isValidKey(string $key): bool
    {
        return isset(self::PRESETS[$key]);
    }

    /** Full CSS declaration block for a preset key, or null. */
    public static function css(string $key): ?string
    {
        $p = self::PRESETS[$key] ?? null;
        if (!$p) {
            return null;
        }
        $layers = [];
        foreach ($p['blobs'] as [$color, $x, $y, $spread]) {
            $layers[] = sprintf('radial-gradient(at %d%% %d%%, %s 0%%, transparent %d%%)', $x, $y, $color, $spread);
        }
        return 'background-color: '.$p['base'].';background-image: '.implode(', ', $layers);
    }

    /**
     * Representative colors for the mobile LinearGradient fallback.
     *
     * @return list<string>
     */
    public static function colors(string $key): array
    {
        $p = self::PRESETS[$key] ?? null;
        if (!$p) {
            return [];
        }
        return array_merge(array_map(fn ($b) => $b[0], array_slice($p['blobs'], 0, 3)), [$p['base']]);
    }
}
