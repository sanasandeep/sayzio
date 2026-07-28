<?php

namespace Tests\Unit;

use App\Modules\User\Support\BgPresetCatalog;
use PHPUnit\Framework\TestCase;

class BgPresetCatalogDuplicateTest extends TestCase
{
    /**
     * Normalize a preset CSS string so cosmetic differences (whitespace,
     * casing) don't hide real duplicates.
     */
    private function normalizeCss(string $css): string
    {
        $css = strtolower($css);
        $css = preg_replace('/\s+/', ' ', $css) ?? $css;
        $css = str_replace(', ', ',', $css);
        $css = str_replace(': ', ':', $css);
        $css = rtrim(trim($css), ';');

        return $css;
    }

    /**
     * Stricter normalization on top of normalizeCss(): canonicalizes color
     * notation (hex shorthand/case, hex vs rgb()/rgba(), alpha-1 rgba) and
     * numeric formatting of gradient stops (trailing zeros, "0.5" vs ".5"),
     * so two presets that render identical swatches but were authored with
     * different notation still compare equal.
     */
    private function canonicalizeCss(string $css): string
    {
        $css = $this->normalizeCss($css);

        // Hex colors -> canonical rgb()/rgba().
        $css = preg_replace_callback('/#([0-9a-f]{3,8})\b/', function (array $m): string {
            $hex = $m[1];
            $len = strlen($hex);
            if ($len === 3 || $len === 4) {
                $hex = implode('', array_map(fn ($c) => $c . $c, str_split($hex)));
                $len *= 2;
            }
            if ($len !== 6 && $len !== 8) {
                return $m[0];
            }
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            if ($len === 8) {
                $a = $this->formatNumber(hexdec(substr($hex, 6, 2)) / 255);
                if ($a !== '1') {
                    return "rgba({$r},{$g},{$b},{$a})";
                }
            }
            return "rgb({$r},{$g},{$b})";
        }, $css) ?? $css;

        // rgb()/rgba(): strip inner spaces, normalize numbers, drop alpha == 1.
        $css = preg_replace_callback('/rgba?\(([^)]*)\)/', function (array $m): string {
            $parts = array_map(
                fn ($p) => $this->formatNumber((float) trim($p)),
                explode(',', $m[1])
            );
            if (count($parts) === 4 && $parts[3] === '1') {
                array_pop($parts);
            }
            $fn = count($parts) === 4 ? 'rgba' : 'rgb';
            return $fn . '(' . implode(',', $parts) . ')';
        }, $css) ?? $css;

        // Numeric stop values: normalize trailing zeros ("0.00%" -> "0%").
        $css = preg_replace_callback(
            '/(?<![\w.])(\d+\.\d+|\.\d+)(%|px|deg)?/',
            fn (array $m) => $this->formatNumber((float) $m[1]) . ($m[2] ?? ''),
            $css
        ) ?? $css;

        // Gradient directions: map "to <side>" keywords to their angle
        // equivalents, and treat the default direction (180deg / to bottom)
        // as equal to an omitted direction.
        $directionAngles = [
            'to top' => 0,
            'to right' => 90,
            'to bottom' => 180,
            'to left' => 270,
            'to top right' => 45,
            'to right top' => 45,
            'to bottom right' => 135,
            'to right bottom' => 135,
            'to bottom left' => 225,
            'to left bottom' => 225,
            'to top left' => 315,
            'to left top' => 315,
        ];
        $css = preg_replace_callback(
            '/linear-gradient\((?:(to [a-z ]+|-?\d+(?:\.\d+)?deg),)?/',
            function (array $m) use ($directionAngles): string {
                $dir = $m[1] ?? '';
                if ($dir === '') {
                    return 'linear-gradient(';
                }
                if (str_starts_with($dir, 'to ')) {
                    $angle = $directionAngles[preg_replace('/\s+/', ' ', trim($dir))] ?? null;
                    if ($angle === null) {
                        return $m[0];
                    }
                } else {
                    $angle = fmod((float) substr($dir, 0, -3), 360.0);
                    if ($angle < 0) {
                        $angle += 360.0;
                    }
                }
                if ((float) $angle === 180.0) {
                    return 'linear-gradient(';
                }
                return 'linear-gradient(' . $this->formatNumber((float) $angle) . 'deg,';
            },
            $css
        ) ?? $css;

        return $css;
    }

    private function formatNumber(float $n): string
    {
        $s = rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.');
        return $s === '' || $s === '-0' ? '0' : $s;
    }

    public function test_no_two_presets_share_identical_normalized_css(): void
    {
        $seen = [];
        $duplicates = [];

        foreach (BgPresetCatalog::all() as $key => $preset) {
            $normalized = $this->normalizeCss($preset['css']);

            if (isset($seen[$normalized])) {
                $duplicates[] = sprintf('"%s" duplicates "%s"', $key, $seen[$normalized]);
            } else {
                $seen[$normalized] = $key;
            }
        }

        $this->assertSame(
            [],
            $duplicates,
            "Background presets with identical CSS found (users would see the same swatch twice):\n"
                . implode("\n", $duplicates)
        );
    }

    public function test_no_two_presets_share_visually_equivalent_css(): void
    {
        $seen = [];
        $duplicates = [];

        foreach (BgPresetCatalog::all() as $key => $preset) {
            $canonical = $this->canonicalizeCss($preset['css']);

            if (isset($seen[$canonical])) {
                $duplicates[] = sprintf('"%s" duplicates "%s"', $key, $seen[$canonical]);
            } else {
                $seen[$canonical] = $key;
            }
        }

        $this->assertSame(
            [],
            $duplicates,
            "Background presets with visually equivalent CSS found (same swatch, different notation):\n"
                . implode("\n", $duplicates)
        );
    }

    public function test_every_preset_has_nonempty_css_and_known_group(): void
    {
        foreach (BgPresetCatalog::all() as $key => $preset) {
            $this->assertNotSame('', trim($preset['css']), "Preset {$key} has empty CSS");
            $this->assertArrayHasKey($preset['group'], BgPresetCatalog::GROUPS, "Preset {$key} has unknown group {$preset['group']}");
        }
    }
}
