<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\Common\Support\HomePageCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Plans starting from X" states the cheapest paid plan in the catalogue.
 *
 * It used to state the POPULAR plan's price instead, and the difference was
 * not small: on a catalogue whose entry paid plan was Rs 167/mo, the homepage
 * advertised Rs 1,389/mo.
 *
 * The cause was structural rather than arithmetical. The landing teaser is
 * built from `HomePageCache::buildPayload()`, which deliberately returns just
 * two plan cards -- the free plan and the one flagged `is_popular` -- because
 * the full grid lives at /pricing. The view then took the cheapest PAID entry
 * of those two, which with one paid card is simply that card. Sorting was
 * doing its job; the collection was never the catalogue.
 *
 * So a "starting from" figure has to be computed over every public plan, and
 * `buildPayload` now carries `cheapestPaid` for exactly that. This test pins
 * the property that matters -- it is the MINIMUM, not the popular one, not
 * the first one -- with a catalogue arranged so those three answers differ.
 */
class HomepageStartingPriceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A catalogue where the cheapest, the popular and the first-ordered paid
     * plans are three different rows. Any implementation that returns the
     * wrong one of them fails here.
     */
    /**
     * The app's own seeders put a plan catalogue in the test database, and
     * this suite is asserting a MINIMUM -- so any plan it does not know about
     * changes the answer. Every existing plan is archived first, leaving the
     * fixture as the whole public catalogue.
     */
    private function clearCatalogue(): void
    {
        Plan::query()->update(['is_archived' => true, 'status' => 'inactive']);
    }

    private function seedCatalogue(): void
    {
        $this->clearCatalogue();
        // Ordered first, and dear: catches "just take the first paid plan".
        $this->plan('Studio', 4900, false, 1);
        // Popular, and dearer than the cheapest: the actual bug.
        $this->plan('Professional', 1900, true, 2);
        // The genuine minimum, deliberately last in the ordering.
        $this->plan('Lite', 700, false, 3);
        $this->plan('Starter', 0, false, 0);
    }

    private function plan(string $name, int $monthlyMinor, bool $popular, int $order): Plan
    {
        // Created directly rather than through a factory: Plan has none, and
        // the columns that matter here are few and explicit.
        $plan = Plan::create([
            'name'        => $name,
            'slug'        => 'test-' . \Illuminate\Support\Str::slug($name),
            'description' => $name . ' plan',
            'status'      => 'active',
            'is_archived' => false,
            'is_popular'  => $popular,
            'sort_order'  => $order,
            'features'    => [],
        ]);

        \App\Services\PricingResolver::upsertFromMinor($plan, 'USD', 'monthly', $monthlyMinor);

        return $plan;
    }

    public function test_the_starting_price_is_the_cheapest_paid_plan(): void
    {
        $this->seedCatalogue();

        $payload = HomePageCache::buildPayload(null, null, false, 'USD');

        $this->assertNotNull($payload['cheapestPaid'] ?? null, 'buildPayload did not carry a cheapestPaid entry.');
        $this->assertSame('Lite', $payload['cheapestPaid']['name']);
        $this->assertSame(700, (int) $payload['cheapestPaid']['monthly']['amount_minor']);
    }

    /**
     * The specific regression: the popular plan is NOT the answer unless it
     * also happens to be the cheapest.
     */
    public function test_the_starting_price_is_not_the_popular_plan(): void
    {
        $this->seedCatalogue();

        $payload = HomePageCache::buildPayload(null, null, false, 'USD');

        $this->assertNotSame('Professional', $payload['cheapestPaid']['name']);
        $this->assertLessThan(1900, (int) $payload['cheapestPaid']['monthly']['amount_minor']);
    }

    /** Free plans are not a starting PRICE -- a zero would read as "from $0". */
    public function test_free_plans_are_not_counted_as_the_minimum(): void
    {
        $this->seedCatalogue();

        $payload = HomePageCache::buildPayload(null, null, false, 'USD');

        $this->assertGreaterThan(0, (int) $payload['cheapestPaid']['monthly']['amount_minor']);
    }

    /** A catalogue with nothing paid in it has no starting price to state. */
    public function test_a_catalogue_with_no_paid_plan_reports_no_minimum(): void
    {
        $this->clearCatalogue();
        $this->plan('Starter', 0, false, 0);

        $payload = HomePageCache::buildPayload(null, null, false, 'USD');

        $this->assertNull($payload['cheapestPaid'] ?? null);
    }
}
