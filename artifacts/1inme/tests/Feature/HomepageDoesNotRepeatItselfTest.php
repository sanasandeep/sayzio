<?php

namespace Tests\Feature;

use Tests\Support\RendersTheHomepage;
use Tests\TestCase;

/**
 * The classic homepage does not spend a section on something it has already
 * shown.
 *
 * Sana asked for 15 sections instead of 25. The five removed here were the
 * easy half of that, because none of them needed a new home -- they described
 * things the page or the site already covered:
 *
 *   Resume & portfolio  -- one of the 18 link types in the grid; /features
 *                          covers it (checked before removing).
 *   Phonebook/vCard     -- a link type; /features covers it.
 *   Forms               -- a link type; /features covers it.
 *   Notifications       -- nobody chooses a product over its notification
 *                          preferences.
 *   Share anywhere      -- restated what a link is, one section after a grid
 *                          of 18 things a link can be.
 *
 * Sana's own observation drove four of the five: "in 18 links its already
 * there".
 *
 * The partials are NOT deleted -- they still render in
 * deferred-sections-business.blade.php, a different homepage design. These
 * tests are scoped to the classic design for exactly that reason.
 */
class HomepageDoesNotRepeatItselfTest extends TestCase
{
    use RendersTheHomepage;

    /**
     * Everything below the classic homepage's hero.
     *
     * That used to be one response. It is two now: the proof band, the
     * showcase and the audience section are server-rendered into `/` for
     * search engines, and the rest still arrives through /home/sections.
     * A test about what was CUT from the page has to look at the whole page,
     * or the split quietly turns "this section is gone" into "this section
     * is in the other response".
     */
    private function sections(): string
    {
        return $this->wholeHomepage();
    }

    public function test_the_removed_sections_are_gone(): void
    {
        $html = $this->sections();

        foreach ([
            'id="share"' => 'the Share section is back on the classic homepage',
            'id="resume"' => 'the Resume section is back on the classic homepage',
        ] as $marker => $why) {
            $this->assertStringNotContainsString($marker, $html, $why);
        }
    }

    /**
     * The page still renders, and still has the sections that earn their place.
     *
     * Cutting by hand out of a 2,200-line Blade file is exactly the kind of
     * edit that takes a neighbour with it, so this checks the survivors rather
     * than only the casualties.
     */
    public function test_the_sections_that_stay_still_render(): void
    {
        $html = $this->sections();

        foreach ([
            'id="audience"' => 'the audience section',
            'id="domains"' => 'the custom domain section',
            'id="workspace-team"' => 'the teams section',
            'id="faq"' => 'the FAQ',
            'id="cta-final"' => 'the closing CTA',
        ] as $marker => $what) {
            $this->assertStringContainsString(
                $marker,
                $html,
                "{$what} disappeared, which was not the intention of this cut"
            );
        }
    }

    /**
     * The expandable-card injector does not reference a section that is gone.
     *
     * A dead selector matches nothing and breaks nothing, which is what makes
     * it worth a test: it would have sat there describing a section that no
     * longer exists until somebody wasted an hour on it.
     *
     * THIS TEST USED TO PASS FOR THE WRONG REASON. It searched the
     * /home/sections fragment for the selector string, and the injector is
     * not in that fragment at all -- it is included from home.blade.php.
     * The string was absent because the whole file was absent, so the
     * assertion held no matter what the SELECTORS array said. Server-
     * rendering the first sections put the injector in the haystack and the
     * test failed on the explanatory comment, which is how the hole was
     * found.
     *
     * So it reads the source and looks at the ACTIVE entries only. A
     * selector named in a comment saying why it was removed is the file
     * doing its job; one still in the array is the bug.
     */
    public function test_no_selector_points_at_a_removed_section(): void
    {
        $source = (string) file_get_contents(
            resource_path('views/home/partials/expandable-cards.blade.php')
        );

        $active = [];
        foreach (preg_split('/\R/', $source) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) {
                continue;
            }
            $active[] = $trimmed;
        }
        $active = implode("\n", $active);

        // Guard the guard: if the array is ever renamed or restructured, the
        // loop above could silently have nothing to look at.
        $this->assertStringContainsString(
            "'#audience .audience-card'",
            $active,
            'the SELECTORS array no longer looks the way this test reads it -- '
            . 'the check below would pass against anything'
        );

        $this->assertStringNotContainsString(
            "'#share .share-card'",
            $active,
            'the expandable-card injector still lists a selector for the removed Share section'
        );
    }

    /**
     * The other homepage design is untouched.
     *
     * The partials were left in place deliberately. If this ever fails, the
     * removal went further than deduplicating the classic page.
     */
    public function test_the_business_design_still_has_its_sections(): void
    {
        $blade = file_get_contents(
            resource_path('views/home/deferred-sections-business.blade.php')
        );

        foreach (['resume', 'dialer-contacts', 'forms'] as $partial) {
            $this->assertStringContainsString(
                "home.partials.{$partial}",
                $blade,
                "the business homepage design lost its {$partial} section -- the cut was "
                . 'meant to be scoped to the classic design only'
            );
        }
    }
}
