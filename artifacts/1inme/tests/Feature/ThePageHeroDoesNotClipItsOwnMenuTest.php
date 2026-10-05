<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Sana, 2026-10-05: "dropdown menu cuts".
 *
 * It did. The Export control on a link's page opens a menu of three
 * formats that hangs below the hero card, and the hero was
 * `overflow: hidden` — so the third format was sliced in half at the card's
 * edge and could not be clicked.
 *
 * ---- Why the clip was there ------------------------------------------
 *
 * The hero carries a decorative ribbon that is absolutely positioned and
 * deliberately runs past the card's corner. Clipping the card is the
 * obvious way to keep that ribbon inside it, and it worked — until a menu
 * was added that is SUPPOSED to escape.
 *
 * The fix is to clip the layer that needs clipping rather than the card
 * that contains it: the ribbon now lives in its own inset wrapper, and the
 * hero does not clip at all.
 *
 * ---- Why this is a test and not just a fix ---------------------------
 *
 * Nothing else in this suite would ever catch it. The menu renders, the
 * links are right, every assertion about the markup passes — the bug is
 * entirely in whether one ancestor clips, and the next person to want the
 * ribbon tidy will reach for `overflow: hidden` on the hero again.
 */
class ThePageHeroDoesNotClipItsOwnMenuTest extends TestCase
{
    private function layoutCss(): string
    {
        return file_get_contents(resource_path('views/user/layouts/app.blade.php'));
    }

    /** The rule that caused it, asserted gone. */
    public function test_the_hero_does_not_clip_its_own_overflow(): void
    {
        $css = $this->layoutCss();

        // The .page-hero block, up to its closing brace.
        $this->assertTrue(
            (bool) preg_match('/\.page-hero\s*\{(.*?)\}/s', $css, $m),
            'the .page-hero rule has moved — this guard no longer looks at anything'
        );

        $this->assertStringNotContainsString(
            'overflow: hidden',
            $m[1],
            'the hero clips again — the Export menu hangs below it and will be cut off'
        );
    }

    /** And the layer that does need clipping still does. */
    public function test_the_decoration_is_clipped_instead(): void
    {
        $css = $this->layoutCss();

        $this->assertTrue(
            (bool) preg_match('/\.page-hero-deco\s*\{(.*?)\}/s', $css, $m),
            'nothing clips the ribbon now, so it runs past the card corner'
        );

        $rule = preg_replace('/\s+/', ' ', $m[1]);

        $this->assertStringContainsString('overflow: hidden', $rule);
        $this->assertStringContainsString('inset: 0', $rule);
        // Inherited, so a change to the hero's corner radius cannot leave the
        // ribbon clipped to a square inside a rounded card.
        $this->assertStringContainsString('border-radius: inherit', $rule);
    }

    /** The markup actually uses that wrapper. */
    public function test_the_hero_wraps_its_ribbon(): void
    {
        $hero = file_get_contents(resource_path('views/user/partials/page-hero.blade.php'));

        $this->assertMatchesRegularExpression(
            '/<div class="page-hero-deco">\s*@include\(\'common\.partials\.card-ribbon\'\)\s*<\/div>/',
            $hero,
            'the ribbon is not inside the clipping wrapper, so it will escape the card'
        );
    }

    /**
     * The ribbon's own rules still select it through the wrapper.
     *
     * This is the half that would break silently: the positioning rules were
     * written as `.page-hero > .cribbon`, and adding a wrapper makes the
     * ribbon a grandchild. The rules would stop matching, the ribbon would
     * drop into the flow, and the hero's layout would quietly change.
     */
    public function test_the_ribbon_rules_still_reach_it_through_the_wrapper(): void
    {
        $css = $this->layoutCss();

        $this->assertStringNotContainsString(
            '.page-hero > .cribbon',
            $css,
            'a direct-child selector cannot reach the ribbon now that it is wrapped'
        );
        $this->assertStringContainsString('.page-hero .cribbon', $css);
    }
}
