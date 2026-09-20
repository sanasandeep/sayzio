<?php

namespace App\Modules\User\Support;

/**
 * Torn-paper tear variants for the biolink "Torn Paper" background type
 * (Task #6204). Each style describes one or more solid "paper" sheets
 * clipped by jagged tear polygons; the backdrop (photo, gradient built
 * from validated hex colors, or the fallback color) shows through beyond
 * the tears. Only the style KEY is stored in link settings — the clip
 * paths are always resolved server-side from this catalog, never from
 * client input.
 *
 * Legacy pages saved before styles existed have no `torn_style` key and
 * render the classic diagonal tear unchanged (DEFAULT).
 */
class TornStyleCatalog
{
    public const DEFAULT = 'diagonal';

    /**
     * Each sheet: ['clip' => polygon, 'shade' => float 0..1] where shade
     * multiplies the paper color toward black (1 = untouched paper color)
     * so stacked sheets read as separate layers.
     *
     * @var array<string, array{label: string, sheets: list<array{clip: string, shade: float}>}>
     */
    private const STYLES = [
        'diagonal' => [
            'label' => 'Diagonal tear',
            'sheets' => [
                ['clip' => 'polygon(0% 0%, 72% 0%, 70.4% 4%, 72.8% 8%, 70% 13%, 71.6% 18%, 68.8% 23%, 71% 28%, 68.2% 33%, 70.2% 38%, 67.6% 43%, 69.4% 48%, 66.8% 53%, 68.6% 58%, 66% 63%, 67.8% 68%, 65.2% 73%, 66.8% 78%, 64.4% 83%, 65.8% 88%, 63.6% 93%, 64.8% 97%, 62% 100%, 0% 100%)', 'shade' => 1.0],
            ],
        ],
        'bottom' => [
            'label' => 'Bottom tear',
            'sheets' => [
                ['clip' => 'polygon(0% 0%, 100% 0%, 100% 72%, 96% 74.5%, 91% 72.8%, 86% 75.6%, 81% 73.2%, 76% 76%, 71% 73.6%, 66% 76.4%, 61% 74%, 56% 76.8%, 51% 74.2%, 46% 77%, 41% 74.6%, 36% 77.2%, 31% 75%, 26% 77.6%, 21% 75.4%, 16% 78%, 11% 75.8%, 6% 78.4%, 0% 76%)', 'shade' => 1.0],
            ],
        ],
        'double' => [
            'label' => 'Double strip',
            'sheets' => [
                ['clip' => 'polygon(0% 0%, 100% 0%, 100% 38%, 95% 40%, 90% 37.5%, 85% 40.5%, 80% 38%, 75% 41%, 70% 38.5%, 65% 41.5%, 60% 39%, 55% 42%, 50% 39.5%, 45% 42.5%, 40% 40%, 35% 43%, 30% 40.5%, 25% 43.5%, 20% 41%, 15% 44%, 10% 41.5%, 5% 44.5%, 0% 42%)', 'shade' => 1.0],
                ['clip' => 'polygon(0% 58%, 5% 60.5%, 10% 57.5%, 15% 61%, 20% 58%, 25% 61.5%, 30% 58.5%, 35% 62%, 40% 59%, 45% 62.5%, 50% 59.5%, 55% 63%, 60% 60%, 65% 63.5%, 70% 60.5%, 75% 64%, 80% 61%, 85% 64.5%, 90% 61.5%, 95% 65%, 100% 62%, 100% 100%, 0% 100%)', 'shade' => 0.94],
            ],
        ],
        'deckled' => [
            'label' => 'Deckled frame',
            'sheets' => [
                ['clip' => 'polygon(3% 2%, 12% 3.4%, 22% 2.2%, 32% 3.8%, 42% 2.4%, 52% 3.6%, 62% 2.6%, 72% 3.8%, 82% 2.4%, 92% 3.6%, 97% 2.6%, 97.8% 12%, 96.4% 22%, 97.6% 32%, 96.2% 42%, 97.8% 52%, 96.4% 62%, 97.6% 72%, 96.2% 82%, 97.4% 92%, 96.6% 97.4%, 88% 96.6%, 78% 97.8%, 68% 96.4%, 58% 97.6%, 48% 96.2%, 38% 97.8%, 28% 96.4%, 18% 97.6%, 8% 96.2%, 2.6% 97.2%, 3.4% 88%, 2.2% 78%, 3.6% 68%, 2.4% 58%, 3.8% 48%, 2.2% 38%, 3.4% 28%, 2.4% 18%, 3.6% 8%)', 'shade' => 1.0],
            ],
        ],
        'corner' => [
            'label' => 'Corner rip',
            'sheets' => [
                ['clip' => 'polygon(0% 0%, 100% 0%, 100% 58%, 95% 61%, 97% 66%, 91% 70%, 93.5% 75%, 87% 78%, 89% 83%, 82% 86%, 84% 91%, 76% 93%, 78% 97%, 70% 98%, 71% 100%, 0% 100%)', 'shade' => 1.0],
            ],
        ],
        'stack' => [
            'label' => 'Layered stack',
            'sheets' => [
                ['clip' => 'polygon(0% 0%, 86% 0%, 84.4% 4%, 86.8% 8%, 84% 13%, 85.6% 18%, 82.8% 23%, 85% 28%, 82.2% 33%, 84.2% 38%, 81.6% 43%, 83.4% 48%, 80.8% 53%, 82.6% 58%, 80% 63%, 81.8% 68%, 79.2% 73%, 80.8% 78%, 78.4% 83%, 79.8% 88%, 77.6% 93%, 78.8% 97%, 76% 100%, 0% 100%)', 'shade' => 0.78],
                ['clip' => 'polygon(0% 0%, 72% 0%, 70.4% 4%, 72.8% 8%, 70% 13%, 71.6% 18%, 68.8% 23%, 71% 28%, 68.2% 33%, 70.2% 38%, 67.6% 43%, 69.4% 48%, 66.8% 53%, 68.6% 58%, 66% 63%, 67.8% 68%, 65.2% 73%, 66.8% 78%, 64.4% 83%, 65.8% 88%, 63.6% 93%, 64.8% 97%, 62% 100%, 0% 100%)', 'shade' => 1.0],
            ],
        ],
    ];

    /**
     * Curated paper + backdrop combo chips shown in the Torn panel. The
     * first three mirror the retired torn-group presets from
     * BgPresetCatalog so those looks stay one click away.
     *
     * @var array<string, array{label: string, style: string, paper: string, backdrop: array{string, string}}>
     */
    public const PRESETS = [
        'dusty_blue' => ['label' => 'Dusty Blue', 'style' => 'diagonal', 'paper' => '#cfe0e6', 'backdrop' => ['#8aa6b4', '#46626f']],
        'cream'      => ['label' => 'Cream',      'style' => 'diagonal', 'paper' => '#f3ead8', 'backdrop' => ['#b3987a', '#6e563c']],
        'dark'       => ['label' => 'Dark',       'style' => 'diagonal', 'paper' => '#23262b', 'backdrop' => ['#5b6472', '#2e3440']],
        'blush'      => ['label' => 'Blush',      'style' => 'bottom',   'paper' => '#fbe4e8', 'backdrop' => ['#e08e9d', '#a34e63']],
        'mint'       => ['label' => 'Mint',       'style' => 'deckled',  'paper' => '#e2f3e8', 'backdrop' => ['#69a888', '#2f5d48']],
        'sunset'     => ['label' => 'Sunset Stack', 'style' => 'stack',    'paper' => '#fdeddc', 'backdrop' => ['#f2955f', '#8e3b52']],

        // Task #6231: a torn LOOK is a tear shape plus a paper/backdrop
        // colourway -- six bare tear shapes were never six looks. Eighteen
        // colourways, each paired with three shapes that suit it.
        'sandstone_tear' => ['label' => 'Sandstone Tear', 'style' => 'diagonal', 'paper' => '#f0e6d2', 'backdrop' => ['#c4a882', '#7a6248']],
        'sandstone_hem' => ['label' => 'Sandstone Hem', 'style' => 'bottom', 'paper' => '#f0e6d2', 'backdrop' => ['#c4a882', '#7a6248']],
        'sandstone_strip' => ['label' => 'Sandstone Strip', 'style' => 'double', 'paper' => '#f0e6d2', 'backdrop' => ['#c4a882', '#7a6248']],
        'slate_sheet_hem' => ['label' => 'Slate Sheet Hem', 'style' => 'bottom', 'paper' => '#e2e8f0', 'backdrop' => ['#64748b', '#1e293b']],
        'slate_sheet_strip' => ['label' => 'Slate Sheet Strip', 'style' => 'double', 'paper' => '#e2e8f0', 'backdrop' => ['#64748b', '#1e293b']],
        'slate_sheet_frame' => ['label' => 'Slate Sheet Frame', 'style' => 'deckled', 'paper' => '#e2e8f0', 'backdrop' => ['#64748b', '#1e293b']],
        'rose_quartz_strip' => ['label' => 'Rose Quartz Strip', 'style' => 'double', 'paper' => '#fae1e6', 'backdrop' => ['#d99aa8', '#8a4a5c']],
        'rose_quartz_frame' => ['label' => 'Rose Quartz Frame', 'style' => 'deckled', 'paper' => '#fae1e6', 'backdrop' => ['#d99aa8', '#8a4a5c']],
        'rose_quartz_rip' => ['label' => 'Rose Quartz Rip', 'style' => 'corner', 'paper' => '#fae1e6', 'backdrop' => ['#d99aa8', '#8a4a5c']],
        'sage_frame' => ['label' => 'Sage Frame', 'style' => 'deckled', 'paper' => '#e6efe2', 'backdrop' => ['#8fae86', '#405640']],
        'sage_rip' => ['label' => 'Sage Rip', 'style' => 'corner', 'paper' => '#e6efe2', 'backdrop' => ['#8fae86', '#405640']],
        'sage_stack' => ['label' => 'Sage Stack', 'style' => 'stack', 'paper' => '#e6efe2', 'backdrop' => ['#8fae86', '#405640']],
        'charcoal_rip' => ['label' => 'Charcoal Rip', 'style' => 'corner', 'paper' => '#2b2f36', 'backdrop' => ['#6b7280', '#111827']],
        'charcoal_stack' => ['label' => 'Charcoal Stack', 'style' => 'stack', 'paper' => '#2b2f36', 'backdrop' => ['#6b7280', '#111827']],
        'charcoal_tear' => ['label' => 'Charcoal Tear', 'style' => 'diagonal', 'paper' => '#2b2f36', 'backdrop' => ['#6b7280', '#111827']],
        'butter_stack' => ['label' => 'Butter Stack', 'style' => 'stack', 'paper' => '#fdf3d0', 'backdrop' => ['#e0bf6a', '#8a6d22']],
        'butter_tear' => ['label' => 'Butter Tear', 'style' => 'diagonal', 'paper' => '#fdf3d0', 'backdrop' => ['#e0bf6a', '#8a6d22']],
        'butter_hem' => ['label' => 'Butter Hem', 'style' => 'bottom', 'paper' => '#fdf3d0', 'backdrop' => ['#e0bf6a', '#8a6d22']],
        'sky_sheet_tear' => ['label' => 'Sky Sheet Tear', 'style' => 'diagonal', 'paper' => '#e0f0fb', 'backdrop' => ['#86b9dd', '#2f5f88']],
        'sky_sheet_hem' => ['label' => 'Sky Sheet Hem', 'style' => 'bottom', 'paper' => '#e0f0fb', 'backdrop' => ['#86b9dd', '#2f5f88']],
        'sky_sheet_strip' => ['label' => 'Sky Sheet Strip', 'style' => 'double', 'paper' => '#e0f0fb', 'backdrop' => ['#86b9dd', '#2f5f88']],
        'terracotta_hem' => ['label' => 'Terracotta Hem', 'style' => 'bottom', 'paper' => '#fae3d4', 'backdrop' => ['#d18a63', '#7c3f24']],
        'terracotta_strip' => ['label' => 'Terracotta Strip', 'style' => 'double', 'paper' => '#fae3d4', 'backdrop' => ['#d18a63', '#7c3f24']],
        'terracotta_frame' => ['label' => 'Terracotta Frame', 'style' => 'deckled', 'paper' => '#fae3d4', 'backdrop' => ['#d18a63', '#7c3f24']],
        'lilac_strip' => ['label' => 'Lilac Strip', 'style' => 'double', 'paper' => '#efe6fa', 'backdrop' => ['#a98cd4', '#4f3a78']],
        'lilac_frame' => ['label' => 'Lilac Frame', 'style' => 'deckled', 'paper' => '#efe6fa', 'backdrop' => ['#a98cd4', '#4f3a78']],
        'lilac_rip' => ['label' => 'Lilac Rip', 'style' => 'corner', 'paper' => '#efe6fa', 'backdrop' => ['#a98cd4', '#4f3a78']],
        'seafoam_frame' => ['label' => 'Seafoam Frame', 'style' => 'deckled', 'paper' => '#ddf4ee', 'backdrop' => ['#74c3ad', '#26605a']],
        'seafoam_rip' => ['label' => 'Seafoam Rip', 'style' => 'corner', 'paper' => '#ddf4ee', 'backdrop' => ['#74c3ad', '#26605a']],
        'seafoam_stack' => ['label' => 'Seafoam Stack', 'style' => 'stack', 'paper' => '#ddf4ee', 'backdrop' => ['#74c3ad', '#26605a']],
        'ink_rip' => ['label' => 'Ink Rip', 'style' => 'corner', 'paper' => '#1b1d22', 'backdrop' => ['#4b5563', '#0b0d10']],
        'ink_stack' => ['label' => 'Ink Stack', 'style' => 'stack', 'paper' => '#1b1d22', 'backdrop' => ['#4b5563', '#0b0d10']],
        'ink_tear' => ['label' => 'Ink Tear', 'style' => 'diagonal', 'paper' => '#1b1d22', 'backdrop' => ['#4b5563', '#0b0d10']],
        'peach_stack' => ['label' => 'Peach Stack', 'style' => 'stack', 'paper' => '#fde8d8', 'backdrop' => ['#f0a97e', '#a6502f']],
        'peach_tear' => ['label' => 'Peach Tear', 'style' => 'diagonal', 'paper' => '#fde8d8', 'backdrop' => ['#f0a97e', '#a6502f']],
        'peach_hem' => ['label' => 'Peach Hem', 'style' => 'bottom', 'paper' => '#fde8d8', 'backdrop' => ['#f0a97e', '#a6502f']],
        'mint_sheet_tear' => ['label' => 'Mint Sheet Tear', 'style' => 'diagonal', 'paper' => '#e3f7e9', 'backdrop' => ['#7fc79a', '#2f6b48']],
        'mint_sheet_hem' => ['label' => 'Mint Sheet Hem', 'style' => 'bottom', 'paper' => '#e3f7e9', 'backdrop' => ['#7fc79a', '#2f6b48']],
        'mint_sheet_strip' => ['label' => 'Mint Sheet Strip', 'style' => 'double', 'paper' => '#e3f7e9', 'backdrop' => ['#7fc79a', '#2f6b48']],
        'dove_hem' => ['label' => 'Dove Hem', 'style' => 'bottom', 'paper' => '#f2f2f0', 'backdrop' => ['#b8b5ae', '#6b6860']],
        'dove_strip' => ['label' => 'Dove Strip', 'style' => 'double', 'paper' => '#f2f2f0', 'backdrop' => ['#b8b5ae', '#6b6860']],
        'dove_frame' => ['label' => 'Dove Frame', 'style' => 'deckled', 'paper' => '#f2f2f0', 'backdrop' => ['#b8b5ae', '#6b6860']],
        'plum_strip' => ['label' => 'Plum Strip', 'style' => 'double', 'paper' => '#f0e2f0', 'backdrop' => ['#b078b0', '#5a2a5a']],
        'plum_frame' => ['label' => 'Plum Frame', 'style' => 'deckled', 'paper' => '#f0e2f0', 'backdrop' => ['#b078b0', '#5a2a5a']],
        'plum_rip' => ['label' => 'Plum Rip', 'style' => 'corner', 'paper' => '#f0e2f0', 'backdrop' => ['#b078b0', '#5a2a5a']],
        'marigold_frame' => ['label' => 'Marigold Frame', 'style' => 'deckled', 'paper' => '#fdeec6', 'backdrop' => ['#e5a93c', '#8a5a10']],
        'marigold_rip' => ['label' => 'Marigold Rip', 'style' => 'corner', 'paper' => '#fdeec6', 'backdrop' => ['#e5a93c', '#8a5a10']],
        'marigold_stack' => ['label' => 'Marigold Stack', 'style' => 'stack', 'paper' => '#fdeec6', 'backdrop' => ['#e5a93c', '#8a5a10']],
        'steel_blue_rip' => ['label' => 'Steel Blue Rip', 'style' => 'corner', 'paper' => '#dde7ee', 'backdrop' => ['#7d9db5', '#35506a']],
        'steel_blue_stack' => ['label' => 'Steel Blue Stack', 'style' => 'stack', 'paper' => '#dde7ee', 'backdrop' => ['#7d9db5', '#35506a']],
        'steel_blue_tear' => ['label' => 'Steel Blue Tear', 'style' => 'diagonal', 'paper' => '#dde7ee', 'backdrop' => ['#7d9db5', '#35506a']],
        'clay_stack' => ['label' => 'Clay Stack', 'style' => 'stack', 'paper' => '#f5e9e2', 'backdrop' => ['#c19a84', '#6f4a38']],
        'clay_tear' => ['label' => 'Clay Tear', 'style' => 'diagonal', 'paper' => '#f5e9e2', 'backdrop' => ['#c19a84', '#6f4a38']],
        'clay_hem' => ['label' => 'Clay Hem', 'style' => 'bottom', 'paper' => '#f5e9e2', 'backdrop' => ['#c19a84', '#6f4a38']],    ];

    /** @return array<string, string> style key => label */
    public static function styles(): array
    {
        return array_map(fn ($s) => $s['label'], self::STYLES);
    }

    public static function isValidStyle(string $key): bool
    {
        return isset(self::STYLES[$key]);
    }

    /**
     * Paper sheets for a style (falls back to the classic diagonal tear so
     * legacy pages without a stored torn_style render unchanged).
     *
     * @return list<array{clip: string, shade: float}>
     */
    public static function sheets(?string $key): array
    {
        return (self::STYLES[$key ?? ''] ?? self::STYLES[self::DEFAULT])['sheets'];
    }

    /** Darken a 6-digit hex color toward black by the sheet shade factor. */
    public static function shadeHex(string $hex, float $factor): string
    {
        $h = ltrim($hex, '#');
        if (strlen($h) === 3) {
            $h = $h[0].$h[0].$h[1].$h[1].$h[2].$h[2];
        }
        if (strlen($h) < 6 || !ctype_xdigit(substr($h, 0, 6))) {
            return $hex;
        }
        $factor = max(0.0, min(1.0, $factor));
        $out = '#';
        foreach ([0, 2, 4] as $i) {
            $out .= str_pad(dechex((int) round(hexdec(substr($h, $i, 2)) * $factor)), 2, '0', STR_PAD_LEFT);
        }
        return $out;
    }
}
