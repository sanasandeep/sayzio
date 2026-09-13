<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Two decisions about the dashboard's skin, written down so they survive the
 * next person who reaches for a gradient.
 *
 * 1. The light ground is a near-white. It used to be #f4f6fa, a blue-grey
 *    picked so that pure-white cards would lift off it. It did that, but it
 *    also meant a screen whose content is rows rather than cards simply read
 *    as grey.
 *
 * 2. No ambient colour washes behind the page. Two large radial blobs -- blue
 *    top-left, cyan right -- drifted behind every screen built on the bento
 *    stage, with a third animated wash inside the hero. Colour that means
 *    nothing, in motion, behind text people are reading.
 *
 * These are source assertions rather than rendered ones on purpose: a wash is
 * a CSS rule, and the cheapest place to catch it coming back is where it
 * would be written.
 */
class TheDashboardSkinStaysQuietTest extends TestCase
{
    private function css(string $view): string
    {
        return (string) file_get_contents(resource_path('views/'.$view));
    }

    public function test_the_light_ground_is_white(): void
    {
        $css = $this->css('common/partials/theme-styles.blade.php');

        // The dashboard's own light block, not the marketing one below it.
        $block = substr($css, strpos($css, 'html.light-mode {'), 2600);

        $this->assertMatchesRegularExpression(
            '/--bg-body:\s*#ffffff;/',
            $block,
            'the dashboard light ground is no longer white; if that is '
            .'deliberate, change it here too and say why'
        );

        foreach (['#f4f6fa' => 'the blue-grey ground', '#fbfaf8' => 'the near-white half-measure'] as $hex => $what) {
            $this->assertStringNotContainsString(
                '--bg-body: '.$hex.';',
                $block,
                $what.' is back'
            );
        }

        // A white card on a white page has nothing but its border. If the
        // hairline ever goes back to a wash of the ground, the cards vanish.
        $this->assertMatchesRegularExpression(
            '/--border-glass:\s*#e3e0da;/',
            $block,
            'the card hairline changed: on a white page it is the entire card '
            .'edge, so it cannot be softened without the cards disappearing'
        );
    }

    public function test_no_ambient_washes_drift_behind_the_page(): void
    {
        $css = $this->css('user/partials/bento-styles.blade.php');

        foreach (['.bento-stage::before', '.bento-stage::after', '.bento-hero::before'] as $ghost) {
            $this->assertStringNotContainsString(
                $ghost,
                $css,
                "$ghost is back: that is a large blurred colour wash drifting "
                .'behind the content on every page built on this stage. The '
                .'stage keeps its stacking context without one.'
            );
        }
    }

    /**
     * And the fourth one, which this guard originally missed because it was
     * not in the partial with the other three. It sat in the layout, fixed to
     * the viewport rather than to the page, so it tinted every screen at once
     * -- and at 3-6% alpha it was quiet enough to survive a look but loud
     * enough to see.
     */
    public function test_the_layout_does_not_tint_the_viewport(): void
    {
        $layout = $this->css('user/layouts/app.blade.php');

        $this->assertStringNotContainsString(
            'dashboard-wash',
            $layout,
            'the viewport-wide colour wash is back in the user layout'
        );

        // Anything else painting a full-viewport gradient would do the same
        // job under a different name.
        $this->assertDoesNotMatchRegularExpression(
            '/position:\s*fixed;\s*inset:\s*0;[^}]*radial-gradient/s',
            $layout,
            'something in the layout is painting a fixed, full-viewport '
            .'gradient over every page again'
        );
    }

    /**
     * The stage itself has to stay -- the tiles and the row dropdowns depend
     * on its stacking context, so "delete the blobs" must not become "delete
     * the stage".
     */
    public function test_the_stage_keeps_its_stacking_context(): void
    {
        $css = $this->css('user/partials/bento-styles.blade.php');

        $this->assertStringContainsString('.bento-stage { position: relative; isolation: isolate;', $css);
        $this->assertStringContainsString('.bento-stage > * { position: relative; z-index: 1; }', $css);
    }
}
