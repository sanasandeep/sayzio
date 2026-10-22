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

        // The wrapper must carry its own geometry. `.page-hero > *` resets it
        // to position:relative at equal specificity, which collapses it to
        // zero height - the styles then render but paint into nothing, which
        // is exactly how this shipped the first time.
        foreach (['position: absolute', 'inset: 0', 'overflow: hidden'] as $needed) {
            $this->assertStringContainsString(
                $needed,
                $css,
                "The deco wrapper must declare \"{$needed}\" or it collapses to zero height."
            );
        }

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

    public function test_each_style_builds_a_double_card(): void
    {
        $css = $this->renderAppearancePartial();
        $selectors = count(PageHeroStyle::options()) * 2;

        // The look is a band (the outer .page-hero, carrying the decoration)
        // with a plate inside it (.hero-row, the reading surface). Both rules
        // have to exist for EVERY selector, not just somewhere in the sheet -
        // a single stray rule would satisfy a plain assertStringContainsString
        // while nineteen styles rendered as a flat card.
        $this->assertSame(
            $selectors,
            substr_count($css, '> .hero-row {'),
            'Every style in both themes needs the inner plate rule.'
        );
        $this->assertSame(
            $selectors,
            preg_match_all('/\.page-hero \{\s*\n\s*padding:/', $css),
            'Every style in both themes needs the outer band padding.'
        );

        // The plate is an opaque surface with its own edge and lift.
        $this->assertSame($selectors, preg_match_all('/\.hero-row \{[^}]*border-radius: 14px/s', $css));
        $this->assertSame($selectors, preg_match_all('/\.hero-row \{[^}]*box-shadow:/s', $css));
        $this->assertSame(
            $selectors,
            preg_match_all('/\.hero-row \{[^}]*background: #[0-9a-f]{6}/s', $css),
            'The plate must be opaque - the decoration sits behind it.'
        );

        // The band needs an opaque surface of its own. A tint would composite
        // against whatever the theme painted on .page-hero - #ffffff in light,
        // the same colour as the plate - and the double card would vanish.
        $this->assertSame(
            $selectors,
            preg_match_all('/\.page-hero \{[^}]*background-color: #[0-9a-f]{6}/s', $css),
            'Every style needs an opaque band colour, or the plate melts into it.'
        );

        // Band and plate must actually differ, per selector.
        preg_match_all('/\.page-hero \{[^}]*background-color: (#[0-9a-f]{6})/s', $css, $bands);
        preg_match_all('/\.hero-row \{[^}]*background: (#[0-9a-f]{6})/s', $css, $plates);
        $this->assertCount($selectors, $bands[1]);
        $this->assertCount($selectors, $plates[1]);
        foreach ($bands[1] as $i => $band) {
            $this->assertNotSame($band, $plates[1][$i], "Band and plate are the same colour ({$band}).");
        }

        // `html.aurora .page-hero` sets `border: 1px solid transparent
        // !important`, so a border-color rule here can never apply. It computed
        // rgba(0,0,0,0) on every page before this was removed.
        // (The emblem and button legitimately set border-color; this is only
        // about the band rule itself.)
        $this->assertDoesNotMatchRegularExpression(
            '/\.page-hero \{[^}]*border-color:/s',
            $css,
            'A border-color on .page-hero loses to an !important aurora rule - use the band colour.'
        );

        // The decoration is drawn by this stylesheet, so whatever the partial
        // puts inside .page-hero-deco is hidden rather than drawn twice.
        $this->assertSame($selectors, substr_count($css, '> .page-hero-deco > * { display: none; }'));

        // The lead hue has to reach the two coloured controls, or the "colour"
        // half of the style never shows on the plate.
        $this->assertSame($selectors, preg_match_all('/\.hero-emblem\.has-letter \{[^}]*background: #/s', $css));
        $this->assertSame($selectors, preg_match_all('/\.btn-primary \{[^}]*background: #/s', $css));

        // Only the two wrappers the partial actually renders are addressed as
        // direct children; anything else would be an assumption about markup.
        $this->assertDoesNotMatchRegularExpression(
            '/\.page-hero > \.(?!hero-row|page-hero-deco)/',
            $css,
            'This stylesheet may only address .hero-row and .page-hero-deco directly.'
        );
    }

    public function test_the_lead_hue_gets_the_more_legible_label(): void
    {
        // Not a 4.5 threshold with an ink fallback: on #6e61ff that picks ink
        // at 4.13 over white at 4.36, the worse of the two. Best-of-two only.
        $this->assertSame('#ffffff', PageHeroStyle::onLead('#6e61ff'));
        $this->assertSame('#071437', PageHeroStyle::onLead('#f6c000'));

        foreach (PageHeroStyle::options() as $key => $opt) {
            foreach (['light', 'dark'] as $theme) {
                $lead = $opt[$theme][0];
                $chosen = PageHeroStyle::onLead($lead);
                $other = $chosen === '#ffffff' ? '#071437' : '#ffffff';

                $this->assertGreaterThanOrEqual(
                    PageHeroStyle::contrast($other, $lead),
                    PageHeroStyle::contrast($chosen, $lead),
                    "{$key}/{$theme}: {$chosen} on {$lead} is the worse of the two labels."
                );
            }
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
