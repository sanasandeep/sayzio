<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Services\PricingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The intro discount has a DISPLAY surface ({@see PricingResolver::introFor})
 * consumed by the public /pricing page, the in-app /user/upgrade page, and
 * the RevenueCat mobile billing controller. Those surfaces render the
 * struck-through normal price, the discounted first-term price, the
 * percent-off badge and an optional label — all pre-formatted by introFor
 * so views never re-derive currency formatting.
 *
 * This covers the formatting (currency symbol, rounding, per-currency
 * amounts) that the pure-function {@see IntroDiscountTest} math coverage does
 * not — a regression in introFor's `*_formatted` keys would otherwise ship
 * silently. See {@see PricingPagesIntroRenderTest} for the page-render side.
 */
class IntroDiscountDisplayTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A plan priced in both currencies with a 25% first-term intro discount
     * on all cycles. USD monthly = $20.00 (2000c), INR monthly = ₹400.00
     * (40000 paise).
     */
    private function makePercentPlan(): Plan
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.Str::random(4), 'description' => 'Pro',
            'monthly_price' => 20.00, 'annual_price' => 200.00, 'trial_days' => 0,
            'status' => 'active', 'sort_order' => 1, 'features' => [],
            'intro_discount' => [
                'enabled' => true,
                'type'    => 'percent',
                'percent' => 25,
                'label'   => 'New customer offer',
            ],
        ]);
        PricingResolver::upsertManyFromMinor($plan, [
            ['USD', 'monthly', 2000],
            ['USD', 'annual', 20000],
            ['INR', 'monthly', 40000],
            ['INR', 'annual', 400000],
        ]);
        return $plan;
    }

    public function test_intro_for_formats_usd_block(): void
    {
        $plan = $this->makePercentPlan();

        // Pass the resolved normal price explicitly (as the controllers do).
        $intro = PricingResolver::introFor($plan, 'USD', 'monthly', 2000);

        $this->assertNotNull($intro);
        // 25% off $20.00 => $5.00 off, $15.00 first term.
        $this->assertSame(1500, $intro['first_minor']);
        $this->assertSame('$15.00', $intro['first_formatted']);
        $this->assertSame(2000, $intro['normal_minor']);
        $this->assertSame('$20.00', $intro['normal_formatted']);
        $this->assertSame(500, $intro['amount_off_minor']);
        $this->assertSame('$5.00', $intro['amount_off_formatted']);
        $this->assertSame(25, $intro['percent_off']);
        $this->assertSame('percent', $intro['type']);
        $this->assertSame('New customer offer', $intro['label']);
    }

    public function test_intro_for_formats_inr_block_with_rupee_symbol(): void
    {
        $plan = $this->makePercentPlan();

        $intro = PricingResolver::introFor($plan, 'INR', 'monthly', 40000);

        $this->assertNotNull($intro);
        // 25% off ₹400.00 => ₹100.00 off, ₹300.00 first term.
        $this->assertSame(30000, $intro['first_minor']);
        $this->assertSame('₹300.00', $intro['first_formatted']);
        $this->assertSame(40000, $intro['normal_minor']);
        $this->assertSame('₹400.00', $intro['normal_formatted']);
        $this->assertSame(10000, $intro['amount_off_minor']);
        $this->assertSame('₹100.00', $intro['amount_off_formatted']);
        $this->assertSame(25, $intro['percent_off']);
        $this->assertSame('New customer offer', $intro['label']);
    }

    public function test_intro_for_resolves_normal_price_from_prices_table_when_omitted(): void
    {
        $plan = $this->makePercentPlan();

        // Omit $normalMinor entirely — introFor must read it from the prices
        // table for the requested (currency, cycle) and still format correctly.
        $intro = PricingResolver::introFor($plan, 'INR', 'annual');

        $this->assertNotNull($intro);
        // 25% off ₹4000.00 => ₹1000.00 off, ₹3000.00 first term.
        $this->assertSame('₹3,000.00', $intro['first_formatted']);
        $this->assertSame('₹4,000.00', $intro['normal_formatted']);
        $this->assertSame('₹1,000.00', $intro['amount_off_formatted']);
        $this->assertSame(25, $intro['percent_off']);
    }

    public function test_intro_for_formats_per_currency_fixed_amounts(): void
    {
        $plan = Plan::create([
            'name' => 'Fixed', 'slug' => 'fixed-'.Str::random(4), 'description' => 'Fixed',
            'monthly_price' => 20.00, 'annual_price' => 200.00, 'trial_days' => 0,
            'status' => 'active', 'sort_order' => 1, 'features' => [],
            'intro_discount' => [
                'enabled' => true,
                'type'    => 'fixed',
                // $3.00 off in USD, ₹50.00 off in INR — independent per currency.
                'fixed'   => ['USD' => 300, 'INR' => 5000],
            ],
        ]);
        PricingResolver::upsertManyFromMinor($plan, [
            ['USD', 'monthly', 2000],
            ['INR', 'monthly', 40000],
        ]);

        $usd = PricingResolver::introFor($plan, 'USD', 'monthly', 2000);
        $this->assertSame('$3.00', $usd['amount_off_formatted']);
        $this->assertSame('$17.00', $usd['first_formatted']);
        $this->assertSame('$20.00', $usd['normal_formatted']);
        $this->assertSame('fixed', $usd['type']);
        $this->assertNull($usd['label']);

        $inr = PricingResolver::introFor($plan, 'INR', 'monthly', 40000);
        $this->assertSame('₹50.00', $inr['amount_off_formatted']);
        $this->assertSame('₹350.00', $inr['first_formatted']);
        $this->assertSame('₹400.00', $inr['normal_formatted']);
    }

    public function test_intro_for_returns_null_when_no_intro_configured(): void
    {
        $plan = Plan::create([
            'name' => 'Plain', 'slug' => 'plain-'.Str::random(4), 'description' => 'Plain',
            'monthly_price' => 20.00, 'annual_price' => 200.00, 'trial_days' => 0,
            'status' => 'active', 'sort_order' => 1, 'features' => [],
            // No intro_discount config at all.
        ]);
        PricingResolver::upsertManyFromMinor($plan, [
            ['USD', 'monthly', 2000],
            ['INR', 'monthly', 40000],
        ]);

        $this->assertNull(PricingResolver::introFor($plan, 'USD', 'monthly', 2000));
        $this->assertNull(PricingResolver::introFor($plan, 'INR', 'monthly', 40000));
    }

    public function test_intro_for_returns_null_for_excluded_cycle(): void
    {
        $plan = Plan::create([
            'name' => 'AnnualOnly', 'slug' => 'annual-'.Str::random(4), 'description' => 'x',
            'monthly_price' => 20.00, 'annual_price' => 200.00, 'trial_days' => 0,
            'status' => 'active', 'sort_order' => 1, 'features' => [],
            'intro_discount' => [
                'enabled' => true, 'type' => 'percent', 'percent' => 25,
                'cycles'  => ['annual'],
            ],
        ]);
        PricingResolver::upsertManyFromMinor($plan, [
            ['USD', 'monthly', 2000],
            ['USD', 'annual', 20000],
        ]);

        // Monthly is excluded -> no intro block; annual still discounts.
        $this->assertNull(PricingResolver::introFor($plan, 'USD', 'monthly', 2000));
        $this->assertNotNull(PricingResolver::introFor($plan, 'USD', 'annual', 20000));
    }
}
