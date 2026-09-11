<?php

namespace Tests\Feature;

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
    /** The classic homepage's below-the-fold content. */
    private function sections(): string
    {
        $response = $this->get('/home/sections');
        $response->assertOk();

        return $response->getContent();
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
     */
    public function test_no_selector_points_at_a_removed_section(): void
    {
        $this->assertStringNotContainsString(
            "'#share .share-card'",
            $this->sections(),
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
