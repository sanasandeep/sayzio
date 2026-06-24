<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\BillingAddress;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\Subscription;
use App\Modules\User\Models\User;
use App\Services\Billing\ProrationCalculator;
use App\Services\PricingResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * End-to-end coverage for the mid-cycle UPGRADE money-math.
 *
 * When an existing subscriber switches plans mid-cycle the charge is the
 * prorated FULL new-plan price (see {@see ProrationCalculator}) and the
 * first-term intro discount must NEVER apply — that reduction is reserved
 * for brand-new subscriptions only. {@see IntroDiscountCheckoutTest} covers
 * the new-subscription + renewal paths; this asserts the upgrade endpoint
 * ({@see \App\Modules\User\Controllers\BillingController::upgradeHandoff})
 * never sneaks the intro discount into the charged amount.
 */
class UpgradeProrationFlowTest extends TestCase
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
     * A cheaper "from" plan with INR monthly = 20000 paise. No intro
     * discount — this is just the plan the buyer is currently on.
     */
    protected function makeFromPlan(): Plan
    {
        $plan = Plan::create([
            'name' => 'Starter', 'slug' => 'starter-'.Str::random(4), 'description' => 'Starter',
            'monthly_price' => 200.00, 'annual_price' => 2000.00, 'trial_days' => 0,
            'status' => 'active', 'sort_order' => 1, 'features' => [],
        ]);
        PricingResolver::upsertManyFromMinor($plan, [
            ['USD', 'monthly', 1000],
            ['USD', 'annual', 10000],
            ['INR', 'monthly', 20000],
            ['INR', 'annual', 200000],
        ]);
        return $plan;
    }

    /**
     * A pricier "to" plan with INR monthly = 40000 paise AND an active
     * 25% first-term intro discount on all cycles. If the upgrade path
     * wrongly applied the intro, the charge would be reduced 25%.
     */
    protected function makeIntroTargetPlan(): Plan
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.Str::random(4), 'description' => 'Pro',
            'monthly_price' => 400.00, 'annual_price' => 4000.00, 'trial_days' => 0,
            'status' => 'active', 'sort_order' => 2, 'features' => [],
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

    /** Stand up an active subscription whose currency is locked to INR. */
    protected function makeActiveSubscription(User $user, Plan $plan, Carbon $periodEnd): Subscription
    {
        return Subscription::create([
            'user_id'              => $user->id,
            'plan_id'              => $plan->id,
            'status'               => 'active',
            'billing_cycle'        => 'monthly',
            'currency'             => 'INR',
            'gateway'              => 'offline',
            'current_period_start' => now()->subDays(15),
            'current_period_end'   => $periodEnd,
        ]);
    }

    /** Pull the upgrade line item out of an invoice's stored line_items. */
    protected function upgradeLine(Invoice $invoice): array
    {
        foreach ((array) $invoice->line_items as $li) {
            if (($li['meta']['kind'] ?? null) === 'plan_upgrade') {
                return $li;
            }
        }
        $this->fail('No plan_upgrade line item found on invoice '.$invoice->number);
    }

    public function test_midcycle_upgrade_charges_prorated_full_price_never_intro(): void
    {
        $user = $this->makeBuyer();
        $from = $this->makeFromPlan();
        $to   = $this->makeIntroTargetPlan();

        $periodEnd = now()->addDays(15);
        $sub = $this->makeActiveSubscription($user, $from, $periodEnd);

        // Authoritative expected charge: the prorated FULL new-plan price
        // in the subscription's locked currency, computed exactly the way
        // the controller does (no intro discount in this code path).
        $calc = ProrationCalculator::prorate(
            $from, $to, 'monthly', now(), $periodEnd, $user, 'INR',
        );
        $this->assertTrue($calc['is_upgrade']);
        $expected = (int) $calc['amount_minor'];
        $this->assertGreaterThan(0, $expected);

        $this->actingAs($user)->post('/user/billing/upgrade/handoff', [
            'gateway' => 'offline',
            'plan_id' => $to->id,
        ]);

        $invoice = Invoice::where('user_id', $user->id)->firstOrFail();
        $line = $this->upgradeLine($invoice);

        // Charge equals the prorated full new-plan price...
        $this->assertSame($expected, (int) $line['amount_minor'],
            'upgrade must charge the prorated full new-plan price');

        // ...and the intro discount must NOT have been applied. The
        // discounted figure would be 25% lower — guard against it.
        $discounted = (int) round($expected * 0.75);
        $this->assertNotSame($discounted, (int) $line['amount_minor'],
            'intro discount must never apply on the upgrade path');
        $this->assertArrayNotHasKey('intro_discount', (array) $line['meta'],
            'upgrade line item must carry no intro_discount meta');

        // Sanity-check the proration math is the FULL price, not a delta:
        // INR monthly normal = 40000 paise, 15 days left in a 30-day cycle.
        $this->assertSame(intdiv(40000 * $calc['days_left'], 30), $expected);
    }

    public function test_downgrade_confirm_is_rejected_as_free(): void
    {
        $user = $this->makeBuyer();
        $from = $this->makeIntroTargetPlan(); // currently on the pricier plan
        $to   = $this->makeFromPlan();        // attempting to "upgrade" to a cheaper one

        $this->makeActiveSubscription($user, $from, now()->addDays(15));

        // A downgrade (lower price) is not an upgrade: confirm must 422 and
        // no charge is produced.
        $this->actingAs($user)->post('/user/billing/upgrade/confirm', [
            'plan_id' => $to->id,
        ])->assertStatus(422);

        $this->assertSame(0, Invoice::where('user_id', $user->id)->count());
    }
}
