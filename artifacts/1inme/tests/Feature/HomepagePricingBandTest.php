<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AssertsAgainstLargeSubjects;
use Tests\TestCase;

/**
 * The pricing band, rebuilt against a Stripe reference.
 *
 * What was asked for was specific, so this guards the specific things rather
 * than "it looks nice": the colour belongs to the ground and not to the cards,
 * the two plans are a light/dark pair rather than white against saturated
 * blue, the free plan's numbers come from the plan record, and the one action
 * being sold is legible.
 *
 * That last one is not hypothetical. The premium CTA is a white button on a
 * dark card, and it went white-on-white twice in this batch -- once when the
 * light-mode ink rule for dark surfaces landed, and again when the escape for
 * a different card raised that rule's specificity past the one colouring this
 * button. Neither showed up anywhere except in a screenshot.
 */
class HomepagePricingBandTest extends TestCase
{
    use RefreshDatabase;
    use AssertsAgainstLargeSubjects;

    private function band(): string
    {
        $html = $this->get('/home/sections')->assertOk()->getContent();

        $start = strpos($html, 'id="pricing"');
        $this->assertNotFalse($start, 'the pricing band did not render at all');

        $end = strpos($html, 'id="faq"', $start);

        return substr($html, $start, $end === false ? 40000 : $end - $start);
    }

    public function test_the_band_renders_with_both_plans(): void
    {
        $band = $this->band();

        $this->assertSubjectContains('pr-card--dark', $band, 'no premium card');
        $this->assertSubjectContains('pr-cells', $band, 'no value cells');
        $this->assertSubjectContains('Most popular', $band);
    }

    /**
     * "Not saturated blue" was the request, and #3d6bff is the saturated blue
     * the card used to be. It is still the brand accent and still appears in
     * the band's wash and its icons; what it must not be again is the card's
     * fill.
     */
    public function test_the_premium_card_is_navy_rather_than_the_brand_blue(): void
    {
        $css = (string) file_get_contents(resource_path('views/home/partials/pricing-style.blade.php'));

        preg_match('/\.pr-card--dark\s*\{(.*?)\}/s', $css, $m);

        $this->assertNotEmpty($m, '.pr-card--dark has no rule');

        $this->assertPatternAbsent(
            '/background[^;]*#3d6bff/i',
            $m[1],
            'the premium card is filled with the brand blue again'
        );

        // A navy: dark enough to be the dark half of a light/dark pair.
        $this->assertPatternFound(
            '/--pr-card:\s*#0[0-9a-f]{5}/i',
            $m[1],
            'the premium card needs a dark fill or the pair is two light cards'
        );
    }

    /** The wash lives on the band, and the band bleeds the full page width. */
    public function test_the_colour_is_in_the_ground_not_in_the_cards(): void
    {
        $css = (string) file_get_contents(resource_path('views/home/partials/pricing-style.blade.php'));

        $this->assertPatternFound(
            '/\.pr-band::before\s*\{[^}]*radial-gradient/s',
            $css,
            'the band has no wash of its own'
        );
        $this->assertPatternFound(
            '/\.pr-band::after\s*\{[^}]*background-image[^;]*gradient/s',
            $css,
            'the dotted verticals are gone'
        );

        // A linear-gradient tiled into a column gives a SOLID rule -- the 1px
        // slice fills the whole tile height. Only a shape smaller than its
        // tile actually dots.
        preg_match('/\.pr-band::after\s*\{(.*?)\}/s', $css, $m);
        $this->assertSubjectContains(
            'radial-gradient',
            $m[1] ?? '',
            'a linear-gradient tiled this way draws solid verticals, not dotted ones'
        );
    }

    /**
     * The free plan's numbers are the plan record's, so raising a limit in the
     * admin panel changes this card too. They were nearly a hardcoded list.
     */
    public function test_the_free_plan_cells_come_from_the_plan_record(): void
    {
        $view = (string) file_get_contents(resource_path('views/home/partials/pricing.blade.php'));

        foreach (['max_links', 'max_biolinks', 'storage_limit_mb', 'contacts_max'] as $key) {
            $this->assertSubjectContains($key, $view, "the free card stopped reading {$key} from the plan");
        }

        // "1 Link in Bio pages" reads as a bug even when the number is right.
        $this->assertSubjectContains(
            'substr($meta[1], 0, -1)',
            $view,
            'the singular case is not handled'
        );
    }

    /**
     * The premium CTA sits on white inside a card whose text is forced white
     * in light mode. It has to be excluded from that, and the exclusion has to
     * be the one that works -- `card-lit-cta` is excluded too, but forces the
     * brand blue rather than the card's navy.
     */
    public function test_the_premium_cta_escapes_the_forced_white_ink(): void
    {
        $band = $this->band();

        preg_match('/<a[^>]*class="([^"]*pr-cta[^"]*)"[^>]*>\s*Explore premium plans/s', $band, $m);

        $this->assertNotEmpty($m, 'the premium CTA is not where this test expects it');
        $this->assertSubjectContains(
            'surface-lit-keep',
            $m[1],
            'without an escape this button is white text on its own white ground in light mode'
        );

        $css = (string) file_get_contents(resource_path('views/home/partials/pricing-style.blade.php'));

        $this->assertPatternFound(
            '/\.pr-card--dark\s+\.pr-cta\s*\{[^}]*color:\s*#0[0-9a-f]{5}/is',
            $css,
            'the escaped button still needs a dark label stated for its white ground'
        );
    }

    /** The three side errands, together, once. */
    public function test_the_anchor_pill_carries_the_three_side_routes(): void
    {
        $band = $this->band();

        $this->assertSubjectContains('pr-anchors', $band);
        $this->assertSubjectContains('Compare every plan', $band);
        $this->assertSubjectContains('Coin packages', $band);
        $this->assertSubjectContains('custom-plan-request', $band);
    }

    /**
     * Both themes get a ground and both get ink. A band that names its colours
     * in only one of them is a band that is unreadable in the other, which is
     * the failure the rest of this batch was spent on.
     */
    public function test_both_themes_are_stated(): void
    {
        $css = (string) file_get_contents(resource_path('views/home/partials/pricing-style.blade.php'));

        // The selector these tokens hang on gained a second name -- /pricing
        // wanted the card language without the band's wash, so the tokens
        // were split onto `.pr-band, .pr-scope`. The rule this test exists
        // for is unchanged: whatever the selector, both themes state a
        // ground and both state ink.
        $this->assertPatternFound('/\.pr-band[^{]*\{[^}]*--pr-ground:/s', $css);
        $this->assertPatternFound('/html:not\(\.light-mode\)[^{]*\.pr-band[^{]*\{[^}]*--pr-ground:/s', $css);
        $this->assertPatternFound('/html:not\(\.light-mode\)[^{]*\.pr-band[^{]*\{[^}]*--pr-ink:/s', $css);

        // In dark mode both cards are dark, so two filled white buttons would
        // leave the section with no hierarchy; the free plan's goes outline.
        $this->assertPatternFound(
            '/html:not\(\.light-mode\)\s*\.pr-card:not\(\.pr-card--dark\)\s*\.pr-cta\s*\{[^}]*background:\s*transparent/s',
            $css,
            'in dark mode both CTAs are filled white and neither one leads'
        );
    }
}
