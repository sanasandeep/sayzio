<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\BillingAddress;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\User;
use App\Services\Billing\Adapters\OfflineAdapter;
use App\Services\Billing\GatewayManager;
use App\Services\PricingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The first-term intro discount must apply ONLY to a brand-new
 * subscription (the {@see \App\Modules\User\Controllers\CheckoutController}
 * "new plan" path). Renewals (built inside the gateway adapters) charge
 * the full normal price so the customer reverts to the normal rate.
 */
class IntroDiscountCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\GatewaySettingsSeeder::class);
    }

    protected function makeBuyer(string $country = 'IN'): User
    {
        $u = User::create([
            'name'     => 'Buyer '.Str::random(4),
            'email'    => 'b'.Str::random(6).'@e.com',
            'password' => bcrypt('secret'),
            'country'  => $country,
        ]);
        BillingAddress::create([
            'user_id' => $u->id, 'country' => $country, 'region' => 'MH',
            'postal_code' => '400001', 'line1' => '1 Rd', 'city' => 'Mumbai',
        ]);
        return $u;
    }

    /**
     * A plan priced in both currencies with a 25% first-term intro
     * discount on all cycles. INR monthly normal price is 40000 paise.
     */
    protected function makeIntroPlan(): Plan
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.Str::random(4), 'description' => 'Pro',
            'monthly_price' => 400.00, 'annual_price' => 4000.00, 'trial_days' => 0,
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

    /** Pull the single plan line item out of an invoice's stored line_items. */
    protected function planLine(Invoice $invoice): array
    {
        foreach ((array) $invoice->line_items as $li) {
            if (in_array(($li['meta']['kind'] ?? null), ['plan', 'plan_renewal'], true)) {
                return $li;
            }
        }
        $this->fail('No plan line item found on invoice '.$invoice->number);
    }

    public function test_new_plan_checkout_charges_discounted_first_term(): void
    {
        $user = $this->makeBuyer();
        $plan = $this->makeIntroPlan();

        $this->actingAs($user)->post('/user/checkout/handoff', [
            'gateway' => 'offline',
            'plan_id' => $plan->id,
            'cycle'   => 'monthly',
        ])->assertOk();

        $invoice = Invoice::where('user_id', $user->id)->firstOrFail();
        $line = $this->planLine($invoice);

        // INR monthly normal = 40000 paise, 25% off => 30000 charged.
        $this->assertSame('plan', $line['meta']['kind']);
        $this->assertSame(30000, (int) $line['amount_minor'], 'first term should be discounted 25%');
        $this->assertSame(40000, (int) $line['meta']['intro_discount']['normal_minor']);
        $this->assertSame(10000, (int) $line['meta']['intro_discount']['amount_off_minor']);
        $this->assertSame(25, (int) $line['meta']['intro_discount']['percent_off']);
        $this->assertSame('percent', $line['meta']['intro_discount']['type']);
    }

    public function test_renewal_charges_full_price_not_discounted(): void
    {
        $user = $this->makeBuyer();
        $plan = $this->makeIntroPlan();

        // Stand up an active subscription whose currency is locked to INR.
        $sub = \App\Modules\User\Models\Subscription::create([
            'user_id'              => $user->id,
            'plan_id'              => $plan->id,
            'status'               => 'active',
            'billing_cycle'        => 'monthly',
            'currency'             => 'INR',
            'gateway'              => 'offline',
            'current_period_start' => now()->subMonth(),
            'current_period_end'   => now()->addDay(),
        ]);

        app(OfflineAdapter::class)->chargeRecurring($sub->fresh());

        $invoice = Invoice::where('subscription_id', $sub->id)->firstOrFail();
        $line = $this->planLine($invoice);

        // Renewal must charge the full 40000 paise, no intro applied.
        $this->assertSame('plan_renewal', $line['meta']['kind']);
        $this->assertSame(40000, (int) $line['amount_minor'], 'renewal must charge full price');
        $this->assertArrayNotHasKey('intro_discount', $line['meta']);
    }

    public function test_intro_excluded_cycle_charges_full_price(): void
    {
        $user = $this->makeBuyer();
        // Intro restricted to annual only — a monthly new checkout pays full.
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.Str::random(4), 'description' => 'Pro',
            'monthly_price' => 400.00, 'annual_price' => 4000.00, 'trial_days' => 0,
            'status' => 'active', 'sort_order' => 1, 'features' => [],
            'intro_discount' => [
                'enabled' => true, 'type' => 'percent', 'percent' => 25,
                'cycles' => ['annual'],
            ],
        ]);
        PricingResolver::upsertManyFromMinor($plan, [
            ['USD', 'monthly', 2000],
            ['USD', 'annual', 20000],
            ['INR', 'monthly', 40000],
            ['INR', 'annual', 400000],
        ]);

        $this->actingAs($user)->post('/user/checkout/handoff', [
            'gateway' => 'offline',
            'plan_id' => $plan->id,
            'cycle'   => 'monthly',
        ])->assertOk();

        $invoice = Invoice::where('user_id', $user->id)->firstOrFail();
        $line = $this->planLine($invoice);

        $this->assertSame(40000, (int) $line['amount_minor'], 'excluded cycle pays full price');
        $this->assertArrayNotHasKey('intro_discount', $line['meta']);
    }
}
