<?php

namespace Tests\Unit\Support;

use App\Modules\Common\Support\SitePagesContent;
use Tests\TestCase;

/**
 * The built-in testimonial fallback must stay empty.
 *
 * SitePagesContent::testimonialsDefault() is the fallback used whenever the
 * `marketing_features_testimonials` setting is empty, so whatever it returns
 * shows up on every marketing page that has no real testimonials configured:
 * /features, every AI-product page and every use-case page.
 *
 * It used to return three invented people presented as genuine customers --
 * "Maya R., content creator · 240k followers", "Daniel K., founder",
 * "Priya S., coach" -- and one of them carried a fabricated result, "32% in
 * two weeks". Nothing marked them as examples. Visitors had no way to tell.
 *
 * That is a legal exposure, not a cosmetic one: the FTC's 2024 rule on fake
 * consumer reviews carries civil penalties per violation, and India's
 * consumer-protection framework covers fabricated reviews as well.
 *
 * This test exists because the failure mode is someone filling the array back
 * in to make a page "look finished" during a redesign. The section partials
 * all guard with `@if($__items->isNotEmpty())`, so empty renders no section
 * at all -- an unfinished-looking page is the correct outcome until real
 * quotes exist. Real ones come from Admin -> Testimonials, which already has
 * a public submission form.
 */
class TestimonialsAreNotFabricatedTest extends TestCase
{
    public function test_the_built_in_testimonial_fallback_is_empty(): void
    {
        $this->assertSame(
            [],
            SitePagesContent::testimonialsDefault(),
            'testimonialsDefault() must stay empty. Anything returned here is '
            . 'shown to visitors as a real customer testimonial on every '
            . 'marketing page that has none configured. Add real testimonials '
            . 'through Admin -> Testimonials instead.',
        );
    }

    /**
     * The specific invented people, pinned by name. If someone restores the
     * old array wholesale, the assertion above catches it; this one names
     * what came back so the failure explains itself.
     */
    public function test_the_previously_invented_reviewers_are_gone(): void
    {
        $encoded = json_encode(SitePagesContent::testimonialsDefault());

        foreach (['Maya R.', 'Daniel K.', 'Priya S.', '240k followers', '32%'] as $invented) {
            $this->assertStringNotContainsString(
                $invented,
                (string) $encoded,
                "\"{$invented}\" is from the fabricated testimonial set that used to ship "
                . 'as the default. It was never a real customer.',
            );
        }
    }
}
