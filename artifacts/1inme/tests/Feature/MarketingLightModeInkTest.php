<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AssertsAgainstLargeSubjects;
use Tests\TestCase;

/**
 * Dark-theme colours need a light-mode counterpart.
 *
 * Batch 15 measured every marketing page for unreadable text and reported
 * zero. It was checking one direction: dark ink on a dark ground. The mirror
 * case -- a colour written for the dark theme still applying when the page
 * turns white -- it never looked for, so "unreadable ink: 0" was true only of
 * the half it measured.
 *
 * What that hid: on /pricing, the head-to-head card rendered the product's own
 * name at 1.17:1. `.cmp-vs-name { color: #fff }` over
 * `.cmp-vs-ours { background: rgba(61,107,255,.12) }` -- white on a 12% tint,
 * which on a white page is white on white. Alongside it the tagline at 2.20,
 * "feature lead" at 2.56, the score total at 2.85. Reported by Sana from a
 * screenshot, not by any test.
 *
 * A browser can composite grounds and this cannot, so this guard works on the
 * stylesheet instead: a rule that pins a near-white colour, on a selector the
 * light theme also renders, must have a light-mode counterpart. That is
 * checkable in PHP, and it is the condition that was actually violated.
 */
class MarketingLightModeInkTest extends TestCase
{
    use RefreshDatabase;
    use AssertsAgainstLargeSubjects;

    private function stylesheet(): string
    {
        return (string) file_get_contents(public_path('css/marketing-anim.css'));
    }

    /**
     * Selectors pinned to a near-white colour outside any light-mode block.
     *
     * @return array<string,string> selector => the colour it pins
     */
    private function whiteInkSelectors(): array
    {
        $css = $this->stylesheet();

        // Comments first, or they ride along on the front of the next
        // selector and the report reads
        // "/* CTA pill */ .cmp-cta pins #fff".
        $css = preg_replace('#/\*.*?\*/#s', '', $css) ?? '';

        // Drop every light-mode block; what remains is the base theme.
        $base = preg_replace('/html\.light-mode[^{]*\{[^}]*\}/s', '', $css) ?? '';

        preg_match_all('/([^{}]+)\{([^}]*)\}/s', $base, $rules, PREG_SET_ORDER);

        $found = [];

        foreach ($rules as [, $selector, $body]) {
            // `color:` only -- a white background or border is not ink.
            if (! preg_match('/(?<![-\w])color\s*:\s*(#fff\b|#ffffff\b|white\b|rgba?\(\s*255\s*,\s*255\s*,\s*255)/i', $body, $m)) {
                continue;
            }

            // White ink is CORRECT when the same rule paints its own ground.
            //
            // `.cmp-vs-badge` is a solid blue circle, `.cmp-cta` a blue pill,
            // `.cmp-brand-ours` a blue chip -- all white-on-blue in either
            // theme, and all three were reported by the first version of this
            // check. A rule that sets both a white colour and a background is
            // making a complete statement; the defect is a rule that sets only
            // the colour and inherits whatever ground the theme gives it.
            if (preg_match('/(?<![-\w])background(-color|-image)?\s*:/i', $body)) {
                continue;
            }

            $selector = trim(preg_replace('/\s+/', ' ', $selector) ?? '');

            if ($selector === '' || str_starts_with($selector, '@')) {
                continue;
            }

            $found[$selector] = $m[1];
        }

        return $found;
    }

    /** Does the sheet carry a light-mode rule that re-colours this selector? */
    private function hasLightModeInk(string $selector): bool
    {
        $css = $this->stylesheet();

        // The last class in the selector is what a light-mode rule would
        // target; `.cmp-vs-ours .cmp-vs-name` is overridden by a rule naming
        // `.cmp-vs-name`, whatever it scopes it to.
        if (! preg_match_all('/\.([a-z0-9_-]+)/i', $selector, $classes)) {
            return false;
        }

        $leaf = end($classes[1]);

        return (bool) preg_match(
            '/html\.light-mode[^{]*\.' . preg_quote($leaf, '/') . '(?![\w-])[^{]*\{[^}]*(?<![-\w])color\s*:/si',
            $css
        );
    }

    /**
     * Every white-ink selector in the compare block has a light-mode colour.
     *
     * Scoped to `cmp-` because that is the block this was found in and the one
     * that renders on a white page. Widening it to the whole stylesheet is
     * worth doing, but as its own piece of work with its own reckoning of
     * what is genuinely dark-only -- not folded in here, where it would
     * arrive as a wall of pre-existing failures and get switched off.
     */
    public function test_compare_block_white_ink_has_a_light_mode_colour(): void
    {
        $missing = [];

        foreach ($this->whiteInkSelectors() as $selector => $colour) {
            if (! str_contains($selector, 'cmp-')) {
                continue;
            }

            if ($this->hasLightModeInk($selector)) {
                continue;
            }

            $missing[] = "{$selector}  pins {$colour}";
        }

        $this->assertSame([], $missing, sprintf(
            "These selectors pin white text with no light-mode colour to replace it.\n\n"
            . "On a white page that is white on white. It is how the product's own name\n"
            . "came to render at 1.17:1 in the head-to-head card.\n\n"
            . "Add an `html.light-mode` rule giving each one a dark ink.\n\n%s",
            implode("\n", $missing)
        ));
    }

    /**
     * And the scan has to be finding rules at all.
     *
     * Every assertion above passes against an empty set, and an empty set is
     * what a regex that stopped matching produces.
     */
    public function test_the_scan_finds_white_ink_rules_to_judge(): void
    {
        $all = $this->whiteInkSelectors();

        // Named, not counted. A threshold is a guess that goes stale the
        // moment the exclusions change -- this one was written as "> 10"
        // before own-background rules were correctly excluded, and the honest
        // total turned out to be four.
        //
        // `.cmp-vs-name` is the selector this whole test exists for: white
        // ink, no ground of its own. If the scan stops seeing it, the scan is
        // broken, whatever the count says.
        $this->assertArrayHasKey(
            '.cmp-vs-name',
            $all,
            'the scan can no longer see .cmp-vs-name -- the exact rule that rendered the '
            . "product's own name at 1.17:1 -- so it would not catch that defect again"
        );

        $this->assertNotEmpty($all, 'the white-ink scan found nothing at all; it is not reading the stylesheet');
    }

    /** The bolt icon is gone from the head-to-head card. */
    public function test_the_compare_card_name_carries_no_icon(): void
    {
        $source = (string) file_get_contents(
            resource_path('views/public/partials/_compare.blade.php')
        );

        preg_match('/<div class="cmp-vs-name">(.*?)<\/div>/s', $source, $m);

        $this->assertNotEmpty($m, 'the head-to-head card no longer has a cmp-vs-name block');

        $this->assertStringNotContainsString(
            '<i ',
            $m[1],
            'the bolt icon is back in front of the product name in the head-to-head card'
        );
    }
}
