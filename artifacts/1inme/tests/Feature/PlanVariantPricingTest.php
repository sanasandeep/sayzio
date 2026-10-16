<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Services\PricingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What a generated variant costs.
 *
 * The first version of the generator multiplied the `monthly_price` decimal
 * column, which nothing public reads. PricingResolver reads the polymorphic
 * `prices` table and deliberately has no fallback ("missing INR row -> Rs
 * 0.00"), so all seventy-two variants would have been free on every surface
 * while looking perfectly priced in admin. A dry run could not show it: every
 * number it printed came from the column the page ignores.
 *
 * The second thing it got wrong was rounding to a whole currency unit. On a
 * Rs 167 plan that is invisible; on a $4 plan every multiplier from 0.88 to
 * 1.15 lands back on $4, so the cheapest category -- the one with the actual
 * paying customers -- came out as nine names at one price. Renaming presented
 * as curation is worse than no curation.
 */
class PlanVariantPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_generated_variant_has_a_real_price_in_every_currency_the_anchor_has(): void
    {
        $this->artisan('plans:generate-variants', ['--category' => ['bio-entry']])->assertSuccessful();

        $variants = $this->generated('bio-entry');
        $this->assertGreaterThan(5, $variants->count(), 'the generator created almost nothing, so the rest of this proves nothing');

        foreach ($variants as $variant) {
            foreach (['USD', 'INR'] as $currency) {
                foreach (['monthly', 'annual'] as $cycle) {
                    $this->assertGreaterThan(
                        0,
                        PricingResolver::priceForCurrency($variant, $currency, $cycle)['amount_minor'],
                        "{$variant->name} has no {$currency} {$cycle} price, so every public surface renders it free",
                    );
                }
            }
        }
    }

    /**
     * The cheapest category is the one that matters here: it is where the
     * paying customers are, and it is the one whole-unit rounding flattens.
     */
    public function test_the_cheapest_category_still_gets_more_than_one_price(): void
    {
        $this->artisan('plans:generate-variants', ['--category' => ['bio-entry']])->assertSuccessful();

        $prices = $this->generated('bio-entry')
            ->map(fn (Plan $p) => PricingResolver::priceForCurrency($p, 'USD', 'monthly')['amount_minor'])
            ->unique();

        $this->assertGreaterThan(
            1,
            $prices->count(),
            'every variant in the cheapest category came out at the same price: that is renaming, not curation',
        );
    }

    /**
     * The decimal columns are not what the page reads, but the admin edit
     * form prefills from them. If they disagree, a curator opens a variant,
     * sees one price, and the public page shows another -- with no error
     * anywhere to say which is real.
     */
    public function test_the_admin_prefill_columns_agree_with_the_public_price(): void
    {
        $this->artisan('plans:generate-variants', ['--category' => ['bio-advanced']])->assertSuccessful();

        foreach ($this->generated('bio-advanced') as $variant) {
            $this->assertSame(
                PricingResolver::priceForCurrency($variant, 'INR', 'monthly')['amount_minor'],
                (int) round(((float) $variant->monthly_price_secondary) * 100),
                "{$variant->name} shows one INR price in admin and another on the page",
            );
        }
    }

    /**
     * A rupee does not divide into halves on a pricing page. Rs 1,305.50 is
     * arithmetic that escaped, not a price, and India is the market this
     * catalogue is mostly read in.
     */
    public function test_rupee_prices_land_on_whole_rupees(): void
    {
        $this->artisan('plans:generate-variants')->assertSuccessful();

        // Generated variants only. The internal comp plan has no category
        // and no business being held to a pricing-page rule.
        $generated = Plan::whereNotNull('plan_category')
            ->where('plan_category', '!=', '')
            ->where('is_category_default', false)
            ->get();
        $this->assertGreaterThan(50, $generated->count(), 'the generator barely ran, so this proves nothing');

        foreach ($generated as $variant) {
            foreach (['monthly', 'annual'] as $cycle) {
                $minor = PricingResolver::priceForCurrency($variant, 'INR', $cycle)['amount_minor'];
                $this->assertSame(
                    0,
                    $minor % 100,
                    "{$variant->name} is priced at Rs ".number_format($minor / 100, 2)." for {$cycle}",
                );
            }
        }
    }

    /** @return \Illuminate\Support\Collection<int, Plan> */
    private function generated(string $category)
    {
        return Plan::where('plan_category', $category)
            ->where('is_category_default', false)
            ->get();
    }
}
