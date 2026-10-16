<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Checks the current compact homepage teaser, rather than the retired plan rail. */
class HomepagePricingBandTest extends TestCase
{
    use RefreshDatabase;

    public function test_pricing_teaser_shows_free_and_paid_options_and_links_to_full_pricing(): void
    {
        $html = $this->get('/home/sections')->assertOk()->getContent();
        $document = new \DOMDocument();
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        $section = $xpath->query('//*[@id="pricing"]')->item(0);
        $this->assertNotNull($section);
        $this->assertStringContainsString('Free', $section->textContent);
        $this->assertStringContainsString('Paid plans from', $section->textContent);
        $this->assertSame(2, $xpath->query('.//article', $section)->length);
        $this->assertSame(route('site.pricing'), $xpath->query('.//a', $section)->item(0)->getAttribute('href'));
    }

    public function test_teaser_reads_the_current_catalogue_price(): void
    {
        $html = view('home.partials.compact-conversion', [
            'plans' => [['is_free' => true, 'name' => 'Starter']],
            'cheapestPaidPlan' => ['monthly' => ['formatted' => '$17.50']],
        ])->render();
        $this->assertStringContainsString('$17.50', $html);
        $this->assertStringContainsString('Starter', $html);
    }

    public function test_teaser_handles_an_empty_paid_catalogue_without_a_fake_price(): void
    {
        $html = view('home.partials.compact-conversion', ['plans' => [], 'cheapestPaidPlan' => null])->render();
        $this->assertStringContainsString('Find your plan', $html);
        $this->assertStringNotContainsString('Paid plans from', $html);
    }
}
