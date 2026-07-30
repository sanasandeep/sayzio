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
        'sunset'     => ['label' => 'Sunset',     'style' => 'stack',    'paper' => '#fdeddc', 'backdrop' => ['#f2955f', '#8e3b52']],
    ];

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
