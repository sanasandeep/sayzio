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

    /**
     * And the block that actually reaches people.
     *
     * `aurora` is added to the html element for every user with the Aurora UI,
     * so their dashboard matches `html.aurora.light-mode` -- two class names
     * against the other block's one, which wins on specificity. The first pass
     * at the white ground changed only `html.light-mode`, shipped green, and
     * changed nothing at all for anyone on Aurora. The guard above passed the
     * whole way, because it was reading the block being edited rather than the
     * block being rendered.
     *
     * So: whichever light block sets the ground, it sets it to white.
     */
    public function test_the_aurora_light_ground_is_white_too(): void
    {
        $css = $this->css('common/partials/theme-styles.blade.php');

        $start = strpos($css, 'html.aurora.light-mode {');
        $this->assertNotFalse($start, 'the Aurora light block is gone; if it was renamed, point this test at the new name');
        $block = substr($css, $start, 2600);

        $this->assertMatchesRegularExpression(
            '/--bg-body:\s*#ffffff;/',
            $block,
            'the Aurora light ground is not white. This is the block nearly '
            .'every signed-in user renders, so a ground set only on '
            .'html.light-mode never reaches them.'
        );
    }

    /**
     * The same point without naming a block, so a third light scope added
     * later cannot quietly reintroduce a grey ground.
     */
    public function test_no_light_scope_stands_on_a_retired_ground(): void
    {
        $css = $this->css('common/partials/theme-styles.blade.php');

        foreach (['#f4f6fa' => 'the blue-grey', '#fbfaf8' => 'the near-white half-measure', '#f6f5f2' => 'the warm paper'] as $hex => $what) {
            $this->assertStringNotContainsString(
                '--bg-body: '.$hex.';',
                $css,
                $what.' ground is back in one of the light scopes'
            );
        }
    }

    /**
     * Cards do not move under the cursor, and the gradient edge is something
     * hover says rather than the page's resting decoration.
     *
     * The lift was translateY(-2px) plus a larger shadow, which together read
     * as the card coming off the page -- on a white ground, with a gradient
     * already on every card at once, that was three effects doing the job of
     * none. At rest the edge is now a flat hairline; hover swaps that one
     * layer for the gradient and changes nothing else, so there is no border
     * width to reflow and nothing to shift under the pointer.
     */
    public function test_cards_do_not_lift_under_the_cursor(): void
    {
        $css = $this->css('common/partials/theme-styles.blade.php');

        $start = strpos($css, 'html.aurora .card-premium:hover');
        $this->assertNotFalse($start, 'the card hover rule is gone; if it moved, point this test at it');
        $hover = substr($css, $start, 400);

        $this->assertStringNotContainsString(
            'translateY',
            $hover,
            'the card hover lift is back'
        );
        $this->assertStringNotContainsString(
            'lg-shadow-hover',
            $hover,
            'the hover shadow is back: a bigger shadow on hover is the lift by '
            .'another name'
        );
    }

    /**
     * The first attempt at removing the lift changed three of the four card
     * families and left .bento-tile lifting in bento-styles, while two
     * unscoped rules kept setting --lg-shadow-hover with !important -- so on
     * the real dashboard the shadow still bloomed under the cursor and the
     * change read as nothing having happened.
     *
     * The families are one object with four class names. They get one rule.
     */
    public function test_every_card_family_shares_the_one_hover_rule(): void
    {
        $css = $this->css('common/partials/theme-styles.blade.php');

        $start = strpos($css, 'html.aurora .glass:hover');
        $this->assertNotFalse($start, 'the shared card hover rule is gone');
        $hover = substr($css, $start, 500);

        foreach (['.card-premium:hover', '.stat-card:hover', '.bento-tile:hover'] as $family) {
            $this->assertStringContainsString(
                'html.aurora '.$family,
                $hover,
                "$family dropped out of the shared hover rule: it will fall "
                .'back to whatever unscoped rule still lifts it'
            );
        }

        // The rule has to undo the older ones, not merely omit them.
        $this->assertStringContainsString('transform: none !important', $hover);
        $this->assertStringContainsString('box-shadow: none !important', $hover);
    }

    /** Nothing may still reference the token that was deleted with the lift. */
    public function test_the_hot_edge_token_has_no_orphaned_users(): void
    {
        foreach ([
            'common/partials/theme-styles.blade.php',
            'user/partials/bento-styles.blade.php',
            'user/layouts/app.blade.php',
        ] as $view) {
            $this->assertStringNotContainsString(
                'aurora-edge-hot',
                $this->css($view),
                "$view still reads --aurora-edge-hot, which is no longer "
                .'defined: the whole background-image declaration is invalid '
                .'at computed-value time, so the card loses its surface'
            );
        }
    }

    public function test_the_gradient_edge_is_a_hover_state(): void
    {
        $css = $this->css('common/partials/theme-styles.blade.php');

        // The resting rule for the three card classes.
        $start = strpos($css, 'html.aurora .glass,');
        $this->assertNotFalse($start);
        $rest = substr($css, $start, 600);

        $this->assertStringNotContainsString(
            'var(--aurora-edge)',
            $rest,
            'cards are painting the gradient edge at rest again. On a page '
            .'where every panel is a card, that is not a highlight, it is the '
            .'background pattern -- the gradient belongs on hover.'
        );
        $this->assertStringContainsString(
            'linear-gradient(var(--border-glass), var(--border-glass))',
            $rest,
            'the resting card edge is no longer the flat hairline'
        );
    }

    /**
     * The rail and the bar are the page's edges, not panels floating above it.
     */
    public function test_the_chrome_carries_no_shadow(): void
    {
        $css = $this->css('common/partials/theme-styles.blade.php');

        foreach ([
            'html.aurora aside.sidebar-shell,' => 'the rail shadow',
            'html.aurora header.header-v2,' => 'the header shadow',
        ] as $selector => $what) {
            $start = strpos($css, $selector);
            $this->assertNotFalse($start, "$selector is gone from the Aurora block");
            $block = substr($css, $start, 260);

            $this->assertStringContainsString('box-shadow: none !important', $block, $what.' is no longer being cleared');

            // .dash-glass forces a transparent border-color with !important,
            // twice, so the hairline only survives if this one is !important
            // too -- which is the bug that shipped here once already.
            $this->assertStringContainsString(
                'var(--border-glass) !important',
                $block,
                $what.": the hairline lost its !important, so .dash-glass's "
                .'transparent border-color wins and the edge disappears'
            );
        }
    }

    /** Flat on the page. The hairline is the edge; a shadow drew it twice. */
    public function test_cards_carry_no_shadow_at_all(): void
    {
        $css = $this->css('common/partials/theme-styles.blade.php');

        $block = substr($css, strpos($css, 'html.aurora .glass,'), 1400);

        $this->assertStringNotContainsString(
            'box-shadow: var(--lg-shadow)',
            $block,
            'the card shadow is back at rest or on hover'
        );
        $this->assertSame(
            2,
            substr_count($block, 'box-shadow: none !important'),
            'both the resting and the hover card rule must clear the shadow'
        );
    }

    /**
     * The rail's class is .sidebar-shell. Three separate rules were written
     * against `aside.sidebar` and silently matched nothing -- the chrome kept
     * its glass shadow and the rail kept its heavy dividers, through two
     * deploys, while every source-level check passed.
     *
     * A selector that matches no element is the failure mode these guards
     * exist for, so this one names the class that is actually in the markup.
     */
    public function test_the_chrome_rules_name_the_class_the_markup_uses(): void
    {
        $css = $this->css('common/partials/theme-styles.blade.php');
        $layout = $this->css('user/layouts/app.blade.php');

        $this->assertStringContainsString(
            'sidebar-shell',
            $layout,
            'the rail no longer carries .sidebar-shell; the CSS below is '
            .'keyed to it and would stop matching'
        );

        $this->assertStringContainsString('html.aurora aside.sidebar-shell', $css);
        $this->assertStringNotContainsString(
            'html.aurora aside.sidebar ',
            $css,
            'a rule is keyed to aside.sidebar again, which matches nothing'
        );
    }

    /**
     * The one that would have caught three deploys' worth of silence.
     *
     * Editing a rule's explanation by replacing text that began AFTER the
     * comment's closing marker leaves prose sitting in raw CSS, followed by a
     * stray `*​/`. The browser then treats everything up to that marker as one
     * malformed construct and drops the rule immediately after it -- no error,
     * no warning, the file still "contains" the rule, and every source-level
     * assertion in this class still passes. It happened three times in this
     * file: the card rest rule, the card hover rule, and the rail's border.
     *
     * Balanced markers plus no prose outside a comment is the cheap check.
     */
    public function test_every_comment_in_the_stylesheet_is_closed_once(): void
    {
        foreach ([
            'common/partials/theme-styles.blade.php',
            'user/partials/bento-styles.blade.php',
            'common/partials/card-ribbon.blade.php',
        ] as $view) {
            $css = $this->css($view);

            $this->assertSame(
                substr_count($css, '/*'),
                substr_count($css, '*/'),
                "$view has unbalanced CSS comment markers: a rule is being "
                .'swallowed by a comment that opens or closes in the wrong place'
            );

            // With comments removed, nothing that reads as a sentence should
            // be left standing where a declaration belongs. Blade's own
            // comments go first: they are stripped before the browser ever
            // sees the file, so prose inside one is not an orphan.
            $stripped = preg_replace('#\{\{--.*?--\}\}#s', '', $css);
            $stripped = preg_replace('#/\*.*?\*/#s', '', (string) $stripped);
            $orphans  = [];
            foreach (explode("\n", (string) $stripped) as $line) {
                if (preg_match('/^\s*[A-Z][a-z]+ [a-z]+/', $line)
                    && ! str_contains($line, '{')
                    && ! str_contains($line, ':')
                    && ! str_contains($line, '--}}')) {
                    $orphans[] = trim($line);
                }
            }

            $this->assertSame(
                [],
                $orphans,
                "$view has prose outside a comment, which means the rule after "
                .'it is being dropped by the CSS parser'
            );
        }
    }

    /**
     * Dark mode is the same skin, not a different one.
     *
     * Light lost its ambient colour in three separate changes -- the stage
     * blobs, the hero wash, the viewport tint -- and dark kept `body::after`,
     * three blurred blooms fixed to the viewport at 135vh behind every page.
     * That single rule is most of why the two themes stopped looking like one
     * product: one flat, one glassy.
     *
     * The grain went with it. A noise tile exists to stop a large blurred
     * gradient banding, and there is no longer a gradient to band.
     */
    public function test_dark_mode_has_no_ambient_wash_either(): void
    {
        $css = $this->css('common/partials/theme-styles.blade.php');

        $start = strpos($css, 'html.aurora body::after');
        $this->assertNotFalse($start, 'the aurora body layer rule is gone entirely; check what replaced it');
        $this->assertStringContainsString(
            'display: none',
            substr($css, $start, 120),
            'the full-viewport aurora wash is painting again in dark mode'
        );

        $block = substr($css, strpos($css, 'html.aurora {'), 3000);
        foreach (['--glow-1', '--glow-2', '--glow-3'] as $glow) {
            $this->assertMatchesRegularExpression(
                '/'.$glow.':\s*transparent;/',
                $block,
                "$glow has colour again: anything still reading it (.bg-mesh, "
                .'page partials) will start painting ambient light on the dark '
                .'canvas that light mode does not have'
            );
        }

        $this->assertMatchesRegularExpression('/--noise-opacity:\s*0;/', $block);
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
