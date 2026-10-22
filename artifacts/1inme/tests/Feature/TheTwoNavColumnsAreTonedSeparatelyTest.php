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

    public function test_there_are_four_areas_and_every_preset_has_a_palette(): void
    {
        $this->assertSame(['rail', 'panel', 'topbar', 'page'], array_keys(NavigationAppearance::AREAS));

        // The page is the ground, not chrome: it takes a background and
        // none of the token redefinitions. Asserted here because the
        // pickers and the validator both build themselves from AREAS,
        // so a fourth area arriving without that distinction would be
        // offered the same block as the other three.
        $this->assertSame(['page'], NavigationAppearance::SURFACE_ONLY_AREAS);

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
            // The palette is a token map, not a three-tuple: a solid
            // preset states all seven tokens and is emitted as literals,
            // a gradient states the three the shared rule needs and lets
            // it derive the rest. Both shapes are legal; a preset that
            // is neither would fall back to light without saying so.
            $palette = NavigationAppearance::PALETTES[$preset];
            foreach (['surface', 'ink', 'muted'] as $token) {
                $this->assertArrayHasKey($token, $palette, "{$preset} has no {$token}.");
            }
            $this->assertTrue(
                NavigationAppearance::tokens($preset) !== [] || str_starts_with($preset, 'gradient-'),
                "{$preset} states neither a full token set nor a gradient's three."
            );
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
            'rail'   => '.navrail',
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
            '/html\[data-rail-appearance\]:not\(\[data-rail-appearance="theme"\]\) \.navrail \{[^}]*--nav-surface/s',
            $css,
            'The rail must set its own surface tokens.'
        );

        // Blade escaping would void every rule carrying an attribute selector.
        $this->assertStringNotContainsString('&quot;', $css);
        $this->assertStringNotContainsString('&gt;', $css);
    }

    /**
     * The check this suite was missing, and the reason the rail's colours did
     * nothing from the day they shipped.
     *
     * Every rail rule was scoped to `.navrail-rail`, a class that appears
     * ZERO times in the markup - the element is `.navrail`. The stylesheet
     * was correct, well-formed and complete, and matched nothing. The old
     * assertions all passed, because they only ever asked what the sheet
     * SAYS; none asked whether anything on the page could hear it.
     *
     * So this one reads both files: every class the appearance partial
     * scopes a navigation surface to has to exist in the markup that
     * surface is made of.
     */
    public function test_every_scope_matches_a_class_the_markup_actually_renders(): void
    {
        $css = $this->renderAppearancePartial();
        $markup = file_get_contents(resource_path('views/user/partials/nav-rail.blade.php'))
            . file_get_contents(resource_path('views/user/layouts/app.blade.php'));

        preg_match_all('/\.(navrail[\w-]*|sidebar-shell|navigation-mobile|header-v2)\b/', $css, $found);
        $scoped = array_values(array_unique($found[1]));
        $this->assertNotEmpty($scoped, 'No scopes found - the sheet changed shape.');

        $orphans = [];
        foreach ($scoped as $class) {
            // Must appear inside a class attribute. A comment naming it is
            // not markup, which is exactly how the old name survived review.
            if (!preg_match('/class="[^"]*\b' . preg_quote($class, '/') . '\b/', $markup)) {
                $orphans[] = $class;
            }
        }

        $this->assertSame(
            [],
            $orphans,
            'Styled but never rendered, so these rules match nothing: ' . implode(', ', $orphans)
        );
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
        $this->assertGreaterThan(0, substr_count($css, '.navrail'));
    }

    /**
     * The two navs spell "selected" differently, and the preset rules
     * only knew one of the spellings.
     *
     * A classic .sidebar-link takes `active`; a rail tile takes
     * `is-active`. The chrome block paints every .navrail-btn with the
     * muted ink and then overrides the selected one -- but the override
     * named `.active`, so on the rail it matched nothing and the
     * selected tile kept the muted ink. It was invisible while the rail
     * scope was wrong, because none of these rules reached the rail at
     * all; correcting the scope is what made it show.
     */
    public function test_the_chrome_rules_use_the_active_class_the_rail_renders(): void
    {
        $css = $this->renderAppearancePartial();
        $markup = file_get_contents(resource_path('views/user/partials/nav-rail.blade.php'));

        $this->assertStringContainsString(
            'is-active',
            $markup,
            'The rail stopped using is-active, so the rule below now names the wrong class.'
        );
        $this->assertStringContainsString(
            '.navrail-btn.is-active',
            $css,
            'No preset rule reaches the rail tile that is actually selected.'
        );
        $this->assertStringNotContainsString(
            '.navrail-btn).active',
            $css,
            'A preset rule is still waiting for a class the rail never sets.'
        );
    }

    /**
     * "in dark mode, why its showing in blue color.. i need it in black
     * shade" (Sana). It was painting rgb(17,24,39) -- blue brightest by
     * 22 points -- and every token in the set came from the same slate
     * family, so the cast compounded across ground, rule and active
     * fill. The assertion is on the CHANNELS rather than on the hex,
     * because the point is the absence of a hue, not one particular
     * value.
     */
    public function test_the_dark_preset_is_a_dark_and_not_a_navy(): void
    {
        $css = $this->renderAppearancePartial();

        preg_match_all('/#([0-9a-f]{6})/i', $css, $all);
        $this->assertNotEmpty($all[1], 'No colours found; the sheet did not render.');

        // Every opaque colour the dark preset declares.
        $line = null;
        foreach (explode("\n", $css) as $row) {
            if (str_contains($row, "appearance=\"dark\"") && str_contains($row, '--nav-surface')) {
                $line = $row;
                break;
            }
        }
        $this->assertNotNull($line, 'The dark preset block is missing.');

        preg_match_all('/#([0-9a-f]{6})/i', $line, $hexes);
        $casts = [];
        foreach (array_unique($hexes[1]) as $hex) {
            if (self::navyness($hex) > 0.7) {
                $casts[] = '#' . $hex . ' (navyness ' . round(self::navyness($hex), 2) . ')';
            }
        }
        $this->assertSame([], $casts, 'Navy in the dark preset: ' . implode(', ', $casts));

        // The control. The measure has to reject the colour this guard
        // was written for, or it is not measuring anything.
        $this->assertGreaterThan(
            0.7,
            self::navyness('111827'),
            'The original navy now passes, so this test no longer discriminates.'
        );
    }

    /**
     * How navy a colour is: its blue excess relative to its own
     * brightness.
     *
     * THIS MEASURE REPLACED A WRONG ONE, AND THE STORY MATTERS
     *
     * The first version asked whether blue was the brightest channel by
     * more than 8 points. That was right while the dark preset was meant
     * to be neutral. Then the preset was deliberately given the page's
     * violet lean, so it would stop reading as a grey slab beside a
     * violet-black page -- and this guard went red on three of the seven
     * tokens for doing exactly what it was asked to do.
     *
     * A test that fails because the product changed its mind is not
     * catching a bug; it is holding a requirement nobody holds any more.
     * The requirement that survives is narrower: the preset may be
     * tinted, it may not be a navy. A navy's blue excess is comparable
     * to its own brightness (#111827 scores 0.93); a tinted neutral's is
     * a fraction of it (nothing in the current preset passes 0.56).
     */
    private static function navyness(string $hex): float
    {
        [$r, $g, $b] = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
        $luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

        return max(0, $b - $r) / max($luminance, 1);
    }

    /**
     * The partial renders the dark preset at all.
     *
     * This used to be the check that the swatch and the preset agreed.
     * They cannot disagree any more -- the rule is emitted from the
     * palette -- so what survives here is the weaker structural claim.
     * The real agreement check, against the Design page's swatches, is
     * in ThePresetColoursAreWrittenDownOnceTest, which is where the
     * drift it was written for actually happened.
     */
    public function test_the_dark_preset_reaches_the_rendered_partial(): void
    {
        $surface = NavigationAppearance::PALETTES['dark']['surface'];
        $this->assertStringContainsString(
            $surface,
            $this->renderAppearancePartial(),
            'The dark preset does not reach the rendered partial at all.'
        );
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
