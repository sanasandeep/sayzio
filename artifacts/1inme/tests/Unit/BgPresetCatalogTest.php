<?php

namespace Tests\Unit;

use App\Modules\User\Support\BgPresetCatalog;
use PHPUnit\Framework\TestCase;

class BgPresetCatalogTest extends TestCase
{
    public function test_catalog_is_not_empty(): void
    {
        $this->assertNotEmpty(BgPresetCatalog::all());
    }

    public function test_every_preset_has_valid_shape_and_known_group(): void
    {
        foreach (BgPresetCatalog::all() as $key => $preset) {
            $this->assertIsString($key, 'Preset keys must be strings');
            $this->assertNotSame('', trim($key), 'Preset key must not be blank');
            $this->assertArrayHasKey('group', $preset, "Preset [{$key}] missing group");
            $this->assertArrayHasKey('label', $preset, "Preset [{$key}] missing label");
            $this->assertArrayHasKey('css', $preset, "Preset [{$key}] missing css");
            $this->assertNotSame('', trim($preset['label']), "Preset [{$key}] has a blank label");
            $this->assertNotSame('', trim($preset['css']), "Preset [{$key}] has blank css");
            $this->assertArrayHasKey(
                $preset['group'],
                BgPresetCatalog::GROUPS,
                "Preset [{$key}] references unknown group [{$preset['group']}]"
            );
        }
    }

    public function test_labels_are_unique_within_each_group(): void
    {
        $seen = [];
        foreach (BgPresetCatalog::all() as $key => $preset) {
            $labelKey = $preset['group'].'|'.mb_strtolower(trim($preset['label']));
            $this->assertArrayNotHasKey(
                $labelKey,
                $seen,
                "Duplicate label '{$preset['label']}' in group [{$preset['group']}]: presets [{$seen[$labelKey]}] and [{$key}]"
            );
            $seen[$labelKey] = $key;
        }
    }

    public function test_no_two_presets_have_identical_normalized_css(): void
    {
        $seen = [];
        foreach (BgPresetCatalog::all() as $key => $preset) {
            $norm = $this->normalizeCss($preset['css']);
            $this->assertArrayNotHasKey(
                $norm,
                $seen,
                "Presets [{$seen[$norm]}] and [{$key}] have identical normalized CSS: {$norm}"
            );
            $seen[$norm] = $key;
        }
    }

    public function test_every_preset_yields_at_least_one_extracted_color(): void
    {
        $api = BgPresetCatalog::forApi();

        $this->assertSame(
            count(BgPresetCatalog::all()),
            count($api['presets']),
            'forApi() must expose every catalog preset'
        );

        foreach ($api['presets'] as $preset) {
            $this->assertNotEmpty(
                $preset['colors'],
                "Preset [{$preset['key']}] extracted no colors — mobile swatch would render blank"
            );
            foreach ($preset['colors'] as $color) {
                $this->assertMatchesRegularExpression(
                    '/^(#[0-9a-f]{3,8}|(?:rgba?|hsla?)\([^()]*\))$/',
                    $color,
                    "Preset [{$preset['key']}] has malformed extracted color [{$color}]"
                );
            }
        }
    }

    public function test_for_api_groups_match_picker_groups(): void
    {
        // Task #6204: forApi() only advertises picker-visible groups —
        // gradients moved to the Gradient tab, torn to the standalone
        // Torn Paper type. Their presets stay resolvable, flagged hidden.
        $api = BgPresetCatalog::forApi();
        $apiGroupKeys = array_column($api['groups'], 'key');
        $this->assertSame(array_keys(BgPresetCatalog::pickerGroups()), $apiGroupKeys);

        foreach (BgPresetCatalog::HIDDEN_PICKER_GROUPS as $hidden) {
            $this->assertNotContains($hidden, $apiGroupKeys);
        }
    }

    public function test_for_api_hidden_flag_matches_hidden_groups(): void
    {
        foreach (BgPresetCatalog::forApi()['presets'] as $preset) {
            $expected = in_array($preset['group'], BgPresetCatalog::HIDDEN_PICKER_GROUPS, true);
            $this->assertSame(
                $expected,
                $preset['hidden'],
                "Preset [{$preset['key']}] hidden flag mismatch for group [{$preset['group']}]"
            );
        }
    }

    public function test_picker_presets_exclude_hidden_groups_but_keys_still_resolve(): void
    {
        $picker = BgPresetCatalog::pickerPresets();
        $this->assertNotEmpty($picker);

        foreach ($picker as $preset) {
            $this->assertNotContains($preset['group'], BgPresetCatalog::HIDDEN_PICKER_GROUPS);
        }

        // Legacy keys (hidden groups) must still resolve for saved pages.
        foreach (BgPresetCatalog::all() as $key => $preset) {
            if (in_array($preset['group'], BgPresetCatalog::HIDDEN_PICKER_GROUPS, true)) {
                $this->assertArrayNotHasKey($key, $picker);
                $this->assertNotNull(BgPresetCatalog::css($key), "Legacy preset [{$key}] must keep resolving");
            }
        }
    }

    private function normalizeCss(string $css): string
    {
        $norm = strtolower($css);
        $norm = preg_replace('/\s+/', '', $norm) ?? $norm;
        $norm = str_replace(["'", '"'], '', $norm);
        return rtrim($norm, ';');
    }
}
