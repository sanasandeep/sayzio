<?php

namespace Tests\Feature;

use App\Modules\Common\Support\HomePageCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Both pricing tiles list their limits, not just the free one.
 *
 * The teaser reads each tile's limits from the plan's `features`, the same
 * way the /pricing cards do. The free tile comes from the payload's
 * `plans`, which carries features; the cheapest paid tile comes from
 * `cheapestPaid`, which was assembled separately and did not. So the
 * change shipped half-working: Free listed three real limits and the paid
 * tile rendered its price above an empty list, asymmetric against the tile
 * beside it. Nothing failed -- an empty list renders as nothing at all.
 *
 * Checked at both levels, because the two ways this breaks are different:
 * the payload can stop carrying features, or the view can stop reading
 * them.
 */
class HomePricingTilesShowLimitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_cheapest_paid_plan_carries_its_features(): void
    {
        $payload = HomePageCache::buildPayload(null, null, false, 'USD');

        $this->assertArrayHasKey('cheapestPaid', $payload);

        if ($payload['cheapestPaid'] === null) {
            $this->markTestSkipped('no paid plan in this catalogue');
        }

        $this->assertArrayHasKey(
            'features',
            $payload['cheapestPaid'],
            'the paid teaser tile reads its limits from features; without the key it renders an empty list'
        );
    }

    public function test_both_tiles_render_a_limit_list(): void
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($this->get(route('home.sections'))->assertOk()->getContent());
        $xpath = new \DOMXPath($dom);

        $tiles = $xpath->query('//*[contains(@class,"ch-prices")]/article');
        $this->assertGreaterThanOrEqual(2, $tiles->length, 'the teaser shows a free and a paid tile');

        $empty = [];
        foreach ($tiles as $tile) {
            $limits = $xpath->query('.//*[contains(@class,"ch-plan-limits")]/li', $tile);
            if ($limits->length === 0) {
                $empty[] = trim($xpath->query('.//h3', $tile)->item(0)->textContent ?? '?');
            }
        }

        $this->assertSame(
            [],
            $empty,
            'these pricing tiles show a price with no limits beside it: '.implode(', ', $empty)
        );
    }
}
