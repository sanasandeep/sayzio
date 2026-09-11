<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The hero says what the visitor gets, and its proof line agrees with it.
 *
 * The old headline was "Your link, now it talks back" -- a description of what
 * the product does. Somebody deciding in five seconds is scanning for what
 * they get, and "talks back" is not that. The strongest claim on the page,
 * "picks up your calls", was buried as the third item in a comma list in the
 * subhead, and it is the one thing no other link-in-bio tool does.
 *
 * Sana chose this direction from three drafts. The headline is deliberately
 * narrower than the (mixed) user base; the audience section below the hero is
 * what catches everyone it does not speak to.
 *
 * The pairing these tests hold is the one that broke before: a headline about
 * customers above a proof line that says "creators" tells a business owner the
 * proof is about somebody else. Change either and the other has to follow.
 */
class HomepageHeroSellsTheOutcomeTest extends TestCase
{
    private function hero(): string
    {
        $response = $this->get('/');
        $response->assertOk();

        $html = $response->getContent();

        // Scope to the hero. Asserting against the whole document would let a
        // match anywhere on a 700 KB page pass for a match in the headline.
        $start = strpos($html, 'id="hero-h"');
        $this->assertNotFalse($start, 'the hero headline element is gone from the homepage');

        return substr($html, max(0, $start - 4000), 8000);
    }

    public function test_the_headline_names_an_outcome(): void
    {
        $this->assertStringContainsString(
            'Never miss another',
            $this->hero(),
            'the hero headline has changed. If that is deliberate, update this test and '
            . 'check the proof line under it still agrees with the new headline.'
        );
    }

    /**
     * The claim that earns the headline is still in the copy.
     *
     * If "picks up calls" ever leaves the hero, the headline is writing a
     * cheque the page no longer cashes.
     */
    public function test_the_call_answering_claim_backs_the_headline(): void
    {
        $this->assertMatchesRegularExpression(
            '/picks up (your )?calls/i',
            $this->hero(),
            'the headline promises the customer you would otherwise miss, but the subhead '
            . 'no longer says the AI picks up calls -- which is the thing that makes the '
            . 'promise true'
        );
    }

    /**
     * The proof line includes the audience the headline addresses.
     *
     * "375,000+ creators" under a headline about customers reads as proof
     * about somebody else.
     */
    public function test_the_proof_line_includes_businesses(): void
    {
        $this->assertMatchesRegularExpression(
            '/375,000\+.{0,120}businesses/is',
            $this->hero(),
            'the hero proof line no longer mentions businesses, so a business owner reading '
            . 'a headline about customers is told the 375,000 are creators'
        );
    }

    /** And it is not back to Indian digit grouping. */
    public function test_the_hero_number_is_grouped_for_the_audience(): void
    {
        $hero = $this->hero();

        $this->assertStringContainsString('375,000+', $hero);
        $this->assertStringNotContainsString('3,75,000', $hero);
    }
}
