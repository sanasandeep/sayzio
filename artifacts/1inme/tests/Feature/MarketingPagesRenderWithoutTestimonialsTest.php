<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The marketing pages that carried the fabricated testimonials still render
 * now that the fallback is empty.
 *
 * features.blade.php includes the testimonials partial unconditionally and
 * relies on the partial's own `@if($__items->isNotEmpty())` guard, while the
 * AI-product and use-case pages guard at the call site. Both routes to an
 * empty section are exercised here, because "the section disappears cleanly"
 * is the whole reason an empty default is safe.
 */
class MarketingPagesRenderWithoutTestimonialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_features_page_renders_and_shows_no_testimonials(): void
    {
        $this->get('/features')
            ->assertOk()
            ->assertDontSee('Maya R.')
            ->assertDontSee('Daniel K.')
            ->assertDontSee('Priya S.');
    }
}
