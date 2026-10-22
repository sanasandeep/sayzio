<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Support\NavigationAppearance;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The rail sidebar is two columns, and they are toned independently.
 *
 * Until this split there was one `sidebar` area, so a dark rail beside a
 * light panel - the reason to have the layout - could not be expressed. The
 * assertions that matter here are therefore about SEPARATION: that each area
 * gets its own attribute, its own scope, and its own rules, rather than one
 * set of rules appearing somewhere in the sheet.
 */
class TheTwoNavColumnsAreTonedSeparatelyTest extends TestCase
{
    use RefreshDatabase;

    public function test_there_are_three_areas_and_every_preset_has_a_palette(): void
    {
        $this->assertSame(['rail', 'panel', 'topbar'], array_keys(NavigationAppearance::AREAS));

        foreach (array_keys(NavigationAppearance::PRESETS) as $preset) {
            if ($preset === 'theme') {
                // 'theme' means "leave the surface alone", so it has no palette.
                $this->assertArrayNotHasKey($preset, NavigationAppearance::PALETTES);
                continue;
            }
            $this->assertArrayHasKey(
                $preset,
                NavigationAppearance::PALETTES,
                "{$preset} is offered but has no palette, so it would fall back to light."
            );
            $this->assertCount(3, NavigationAppearance::PALETTES[$preset]);
        }

        // Validation is built from the catalogue, so it cannot reject a
        // preset the pickers offer.
        foreach (array_keys(NavigationAppearance::PRESETS) as $preset) {
            $this->assertStringContainsString($preset, NavigationAppearance::rule());
        }
    }

    public function test_an_old_sidebar_choice_becomes_both_columns(): void
    {
        AppSetting::put(NavigationAppearance::SETTING, ['sidebar' => 'dark', 'topbar' => 'light']);

        $defaults = NavigationAppearance::defaults();

        $this->assertSame('dark', $defaults['rail'], 'A saved sidebar choice must seed the rail.');
        $this->assertSame('dark', $defaults['panel'], 'A saved sidebar choice must seed the panel.');
        $this->assertSame('light', $defaults['topbar'], 'The top bar never read the sidebar key.');

        // A value stored for one column explicitly beats the legacy seed.
        AppSetting::put(NavigationAppearance::SETTING, ['sidebar' => 'dark', 'rail' => 'light']);
        $defaults = NavigationAppearance::defaults();
        $this->assertSame('light', $defaults['rail']);
        $this->assertSame('dark', $defaults['panel']);
    }

    public function test_junk_and_the_old_colour_alias_resolve_to_something_real(): void
    {
        foreach ([['sidebar' => 'nonsense'], ['sidebar' => 42], ['sidebar' => null], []] as $junk) {
            AppSetting::put(NavigationAppearance::SETTING, $junk);
            foreach (NavigationAppearance::defaults() as $area => $value) {
                if ($area === 'logo') {
                    $this->assertIsBool($value);
                    continue;
                }
                $this->assertArrayHasKey($value, NavigationAppearance::PRESETS, "{$area} resolved to {$value}.");
            }
        }

        // 'color' was the only gradient before there were three.
        $this->assertSame('gradient-medium', NavigationAppearance::normalise('color'));
    }

    public function test_each_area_gets_its_own_scope_in_the_stylesheet(): void
    {
        $css = $this->renderAppearancePartial();

        $scopes = [
            'rail'   => '.navrail-rail',
            'panel'  => ':is(.sidebar-shell,.navigation-mobile,.navrail-panel)',
            'topbar' => '.header-v2',
        ];

        // The three gradients share one `^="gradient-"` rule, and two of them
        // then override --nav-control, so gradient-medium has no exact
        // selector by design. Either form counts as covered.
        foreach ($scopes as $area => $scope) {
            foreach (array_keys(NavigationAppearance::PALETTES) as $preset) {
                $exact = 'html[data-' . $area . '-appearance="' . $preset . '"] ' . $scope;
                $prefix = 'html[data-' . $area . '-appearance^="gradient-"] ' . $scope;
                $covered = str_contains($css, $exact)
                    || (str_starts_with($preset, 'gradient-') && str_contains($css, $prefix));

                $this->assertTrue($covered, "No {$preset} rule for the {$area}.");
            }
        }

        // The rail must redefine the tokens for its own subtree. Without this
        // it inherits the panel's and the two columns can never disagree.
        $this->assertMatchesRegularExpression(
            '/html\[data-rail-appearance\]:not\(\[data-rail-appearance="theme"\]\) \.navrail-rail \{[^}]*--nav-surface/s',
            $css,
            'The rail must set its own surface tokens.'
        );

        // Blade escaping would void every rule carrying an attribute selector.
        $this->assertStringNotContainsString('&quot;', $css);
        $this->assertStringNotContainsString('&gt;', $css);
    }

    /**
     * The control. Every assertion above would pass against an empty string,
     * and the one that matters would pass if only the panel had rules.
     */
    public function test_the_partial_renders_rules_for_all_three_areas(): void
    {
        $css = $this->renderAppearancePartial();

        $this->assertNotSame('', trim($css));
        foreach (['rail', 'panel', 'topbar'] as $area) {
            $this->assertGreaterThan(
                4,
                substr_count($css, 'data-' . $area . '-appearance'),
                "Far too few rules for the {$area}."
            );
        }
        // The rail is the new one, so it is the one most likely to be missing.
        $this->assertGreaterThan(0, substr_count($css, '.navrail-rail'));
    }

    public function test_the_rail_is_the_default_and_an_explicit_choice_is_kept(): void
    {
        $fresh = new User(['settings' => []]);
        $this->assertTrue($fresh->usesRailNav(), 'An account with no choice gets the rail.');

        $optedOut = new User(['settings' => ['nav_pack' => 'classic']]);
        $this->assertFalse($optedOut->usesRailNav(), 'An explicit classic choice must survive the flip.');

        $optedIn = new User(['settings' => ['nav_pack' => 'rail']]);
        $this->assertTrue($optedIn->usesRailNav());
    }

    private function renderAppearancePartial(): string
    {
        return (string) view('common.partials.navigation-appearance')->render();
    }
}
