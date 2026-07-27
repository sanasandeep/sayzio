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

    public function test_every_preset_has_nonempty_css_and_known_group(): void
    {
        foreach (BgPresetCatalog::all() as $key => $preset) {
            $this->assertNotSame('', trim($preset['css']), "Preset {$key} has empty CSS");
            $this->assertArrayHasKey($preset['group'], BgPresetCatalog::GROUPS, "Preset {$key} has unknown group {$preset['group']}");
        }
    }
}
