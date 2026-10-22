<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Support\PageHeroStyle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin-selected page-hero look.
 *
 * The CSS paints onto .page-hero-deco rather than .page-hero, because the hero
 * is deliberately NOT overflow:hidden - clipping it cut off the Export dropdown
 * (app.blade.php, 2026-10-05). A style that reintroduced clipping on the card,
 * or that took over ::before from the grid lattice, would be a regression, so
 * both are asserted here.
 */
class ThePageHeroLookIsChosenOnceTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_style_names_a_layout_and_four_hues_per_theme(): void
    {
        $options = PageHeroStyle::options();
        $this->assertCount(10, $options, 'Five colourways across two layouts.');

        foreach ($options as $key => $opt) {
            $this->assertMatchesRegularExpression('/^(rings|fields)-[a-z]+$/', $key);
            $this->assertContains($opt['layout'], ['rings', 'fields']);
            foreach (['light', 'dark'] as $theme) {
                $this->assertCount(4, $opt[$theme], "{$key} must name four {$theme} hues.");
                foreach ($opt[$theme] as $hue) {
                    $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $hue);
                }
            }
            // Light and dark must not be the same values: the ramp defines
            // different tokens per theme, and reusing one on the other ground
            // is the bug this asserts against.
            $this->assertNotSame($opt['light'], $opt['dark'], "{$key} reuses one theme's hues on the other.");
        }
    }

    public function test_an_unset_or_bogus_choice_falls_back(): void
    {
        AppSetting::put(PageHeroStyle::SETTING, []);
        $this->assertSame(PageHeroStyle::FALLBACK, PageHeroStyle::current());

        foreach ([['style' => 'nope'], ['style' => ''], 'rings-violet', null] as $junk) {
            AppSetting::put(PageHeroStyle::SETTING, $junk);
            $current = PageHeroStyle::current();
            $this->assertArrayHasKey($current, PageHeroStyle::options());
        }

        PageHeroStyle::store('fields-warm');
        $this->assertSame('fields-warm', PageHeroStyle::current());

        // store() ignores anything outside the catalogue rather than persisting it.
        PageHeroStyle::store('fields-nonsense');
        $this->assertSame('fields-warm', PageHeroStyle::current());
    }

    public function test_the_stylesheet_paints_on_the_deco_wrapper_not_the_card(): void
    {
        $css = $this->renderAppearancePartial();

        $this->assertStringContainsString('.page-hero-deco', $css);

        // The card must never be clipped by this stylesheet.
        $this->assertDoesNotMatchRegularExpression(
            '/\.page-hero\s*\{[^}]*overflow\s*:\s*hidden/',
            $css,
            'Clipping .page-hero cuts the Export dropdown - decorate .page-hero-deco instead.'
        );

        // ::before belongs to the grid lattice.
        $this->assertStringNotContainsString(
            '.page-hero::before',
            $css,
            '::before carries the 46px grid lattice; a style must not take it over.'
        );
    }

    public function test_the_stylesheet_covers_every_style_in_both_themes(): void
    {
        $css = $this->renderAppearancePartial();

        // Blade's {{ }} escapes the double quotes in an attribute selector to
        // &quot;, which silently invalidates every rule in the sheet. The
        // partial uses {!! !!} for selectors; this is the guard on that.
        $this->assertStringNotContainsString(
            '&quot;',
            $css,
            'Selectors are HTML-escaped - the stylesheet would match nothing.'
        );
        $this->assertStringNotContainsString('&gt;', $css, 'Child combinator was escaped.');

        foreach (array_keys(PageHeroStyle::options()) as $key) {
            $this->assertStringContainsString(
                'html.light-mode[data-hero-style="' . $key . '"]',
                $css,
                "No light rule for {$key}."
            );
            $this->assertStringContainsString(
                'html:not(.light-mode)[data-hero-style="' . $key . '"]',
                $css,
                "No dark rule for {$key}."
            );
        }
    }

    /**
     * The control. Each assertion above would pass against an empty string, so
     * prove the partial actually rendered something stylesheet-shaped first.
     */
    public function test_the_partial_actually_renders(): void
    {
        $css = $this->renderAppearancePartial();

        $this->assertNotSame('', trim($css), 'The appearance partial rendered nothing.');
        $this->assertStringContainsString('<style>', $css);
        $this->assertGreaterThan(
            20,
            substr_count($css, 'data-hero-style'),
            'Expected a rule per style per theme; got far too few.'
        );
        $this->assertMatchesRegularExpression('/rgba\(\d+,\d+,\d+,[0-9.]+\)/', $css, 'No tints emitted.');
    }

    private function renderAppearancePartial(): string
    {
        return (string) view('common.partials.page-hero-appearance')->render();
    }
}
