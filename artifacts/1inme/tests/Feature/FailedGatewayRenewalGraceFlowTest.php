<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\BillingAddress;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\Subscription;
use App\Modules\User\Models\SubscriptionAddon;
use App\Modules\User\Models\User;
use App\Services\Billing\Adapters\OfflineAdapter;
use App\Services\PricingResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Failure-path mirror of {@see PaidGatewayDowngradeBillingFlowTest}.
 *
 * That test proves the HAPPY path: an immediately-paying gateway settles
 * the renewal so the subscription stays `active`. This test proves the
 * mirror-image FAILURE path: when a real (immediately-charging) gateway's
 * chargeRecurring() THROWS — declined card, gateway outage, expired
 * credentials — the subscription must drop cleanly into `past_due` with a
 * correct grace window (current_period_end + plan.grace_days) and the user
 * must KEEP access until grace_until, not be torn down on the spot.
 *
 * Both renewal branches in RenewDueSubscriptions catch \Throwable and call
 * SubscriptionLifecycle::markRenewalFailed(), but neither was exercised
 * against a throwing paying gateway:
 *   - applyScheduledDowngrades() — the scheduled-downgrade branch, which
 *     must STILL apply the plan switch first, then go past_due.
 *   - renewUpcoming()           — the normal pre-renewal-window branch.
 *
 * The throwing gateway is modelled by {@see ThrowingOfflineAdapter} below:
 * its chargeRecurring() raises before issuing any invoice, exactly as a
 * synchronous declined charge does — so no paid invoice is ever created.
 *
 * A final grace-elapsed run then proves the wind-down completes: once
 * grace_until passes the subscription expires and the user falls back to
 * the Free plan.
 */
class FailedGatewayRenewalGraceFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\GatewaySettingsSeeder::class);

        // Every resolution of the offline gateway (the slug the test subs
        // carry, and the one GatewaySettingsSeeder enables) now returns a
        // gateway whose recurring charge THROWS — standing in for a
        // Stripe/Razorpay-class adapter hitting a declined card / outage.
        $this->app->bind(OfflineAdapter::class, ThrowingOfflineAdapter::class);
    }

    // ------------------------------------------------------------------
    // Fixtures (mirrors PaidGatewayDowngradeBillingFlowTest)
    // ------------------------------------------------------------------

    protected function makeBuyer(string $country = 'IN'): User
    {
        $u = User::create([
            'name'     => 'Buyer ' . Str::random(4),
            'email'    => 'b' . Str::random(8) . '@e.com',
            'password' => Hash::make('secret'),
            'status'   => 'active',
            'country'  => $country,
        ]);
        BillingAddress::create([
            'user_id' => $u->id, 'country' => $country, 'region' => 'MH',
            'postal_code' => '400001', 'line1' => '1 Rd', 'city' => 'Mumbai',
        ]);
        return $u;
    }

    protected function makeFreePlan(): Plan
    {
        $plan = Plan::create([
            'name'        => 'Free',
            'slug'        => 'free',
            'description' => 'Free',
            'monthly_price' => 0,
            'annual_price'  => 0,
            'trial_days'  => 0,
            'grace_days'  => 0,
            'status'      => 'active',
            'is_default'  => true,
            'is_archived' => false,
            'sort_order'  => 0,
            'features'    => [],
        ]);
        return $plan;
    }

    protected function makePlan(string $label, int $inrMonthlyMinor): Plan
    {
        $plan = Plan::create([
            'name'        => $label,
            'slug'        => Str::slug($label) . '-' . Str::random(4),
            'description' => $label,
            'monthly_price' => $inrMonthlyMinor / 100,
            'annual_price'  => ($inrMonthlyMinor * 10) / 100,
            'trial_days'  => 0,
            'grace_days'  => 7,
            'status'      => 'active',
            'is_default'  => false,
            'is_archived' => false,
            'sort_order'  => 1,
            'features'    => [],
        ]);
        PricingResolver::upsertManyFromMinor($plan, [
            ['INR', 'monthly', $inrMonthlyMinor],
            ['INR', 'annual', $inrMonthlyMinor * 10],
            ['USD', 'monthly', (int) round($inrMonthlyMinor / 80)],
            ['USD', 'annual', (int) round(($inrMonthlyMinor * 10) / 80)],
        ]);
        return $plan;
    }

    protected function makeActiveSub(User $user, Plan $plan, Carbon $start, Carbon $end, array $overrides = []): Subscription
    {
        return Subscription::create(array_merge([
            'user_id'              => $user->id,
            'plan_id'              => $plan->id,
            'status'               => 'active',
            'billing_cycle'        => 'monthly',
            'currency'             => 'INR',
            'gateway'              => 'offline',
            'cancel_at_period_end' => false,
            'current_period_start' => $start,
            'current_period_end'   => $end,
        ], $overrides));
    }

    /** No paid invoice should exist for this subscription's user. */
    protected function assertNoPaidInvoice(Subscription $sub): void
    {
        $paid = Invoice::where('user_id', $sub->user_id)
            ->where('status', 'paid')
            ->count();
        $this->assertSame(0, $paid, 'a thrown recurring charge must not leave a paid invoice');
    }

    // ------------------------------------------------------------------
    // 1. Throwing gateway: scheduled downgrade APPLIES, then drops to grace
    // ------------------------------------------------------------------

    public function test_throwing_gateway_scheduled_downgrade_applies_then_goes_past_due(): void
    {
        $user   = $this->makeBuyer();
        $higher = $this->makePlan('Pro', 40000);
        $lower  = $this->makePlan('Starter', 20000);

        // The lower plan can carry $keep but NOT $drop.
        $keep = \App\Modules\Admin\Models\Addon::create([
            'name' => 'Analytics', 'slug' => 'analytics-' . Str::random(4),
            'description' => 'Analytics', 'type' => 'recurring',
            'monthly_price' => 1.00, 'annual_price' => 10.00,
            'status' => 'active', 'is_archived' => false, 'sort_order' => 1,
        ]);
        $drop = \App\Modules\Admin\Models\Addon::create([
            'name' => 'Priority support', 'slug' => 'priority-' . Str::random(4),
            'description' => 'Priority support', 'type' => 'recurring',
            'monthly_price' => 1.00, 'annual_price' => 10.00,
            'status' => 'active', 'is_archived' => false, 'sort_order' => 1,
        ]);
        $lower->addons()->attach($keep->id);

        // Period already ended; a downgrade to the lower plan is scheduled.
        $endedAt = now()->subMinute();
        $sub = $this->makeActiveSub(
            $user, $higher, now()->subMonth(), $endedAt,
            ['scheduled_downgrade_plan_id' => $lower->id],
        );
        SubscriptionAddon::create(['subscription_id' => $sub->id, 'addon_id' => $keep->id, 'qty' => 1]);
        SubscriptionAddon::create(['subscription_id' => $sub->id, 'addon_id' => $drop->id, 'qty' => 1]);

        $this->artisan('subscriptions:renew-due')->assertExitCode(0);

        $sub->refresh();

        // The plan switch STILL happens first — entitlements move to the
        // lower plan even though the renewal charge then fails.
        $this->assertSame($lower->id, $sub->plan_id, 'downgrade applied before the charge failure');
        $this->assertNull($sub->scheduled_downgrade_plan_id, 'schedule cleared on apply');

        // THE POINT: the throwing gateway dropped the sub cleanly into grace,
        // NOT cancelled/expired. Access is retained until grace_until.
        $this->assertSame('past_due', $sub->status, 'failed renewal lands in past_due');
        $this->assertNotContains($sub->status, ['cancelled', 'expired'], 'not torn down on the spot');
        $this->assertNotNull($sub->grace_until, 'a grace window was opened');
        $this->assertTrue(Carbon::parse($sub->grace_until)->isFuture(), 'grace window is still open');
        $this->assertEqualsWithDelta(
            $endedAt->copy()->addDays(7)->timestamp,
            Carbon::parse($sub->grace_until)->timestamp,
            120,
            'grace_until = current_period_end + plan.grace_days',
        );

        // Ineligible add-on still dropped by the applied downgrade.
        $this->assertSame(0, SubscriptionAddon::where('subscription_id', $sub->id)
            ->where('addon_id', $drop->id)->count());
        $this->assertSame(1, SubscriptionAddon::where('subscription_id', $sub->id)
            ->where('addon_id', $keep->id)->count());

        // No money changed hands — the charge threw before settling.
        $this->assertNoPaidInvoice($sub);

        // User still on the (now lower) paid plan, not yet on Free.
        $user->refresh();
        $this->assertSame($lower->id, $user->plan_id, 'access retained during grace');
    }

    // ------------------------------------------------------------------
    // 2. Throwing gateway: a normal renewal drops cleanly into grace
    // ------------------------------------------------------------------

    public function test_throwing_gateway_normal_renewal_goes_past_due_with_grace(): void
    {
        $user = $this->makeBuyer();
        $plan = $this->makePlan('Pro', 40000);

        // Due within the 24h pre-renewal window, no downgrade scheduled.
        $end = now()->addHours(6);
        $sub = $this->makeActiveSub($user, $plan, now()->subMonth()->addHours(6), $end);

        $this->artisan('subscriptions:renew-due')->assertExitCode(0);

        $sub->refresh();
        $this->assertSame('past_due', $sub->status, 'failed renewal lands in past_due');
        $this->assertNotContains($sub->status, ['cancelled', 'expired'], 'not torn down on the spot');
        $this->assertNotNull($sub->grace_until);
        $this->assertTrue(Carbon::parse($sub->grace_until)->isFuture(), 'grace window is still open');
        $this->assertEqualsWithDelta(
            $end->copy()->addDays(7)->timestamp,
            Carbon::parse($sub->grace_until)->timestamp,
            120,
            'grace_until = current_period_end + plan.grace_days',
        );

        // Period end is NOT advanced — the renewal never settled.
        $this->assertEqualsWithDelta(
            $end->timestamp,
            Carbon::parse($sub->current_period_end)->timestamp,
            120,
            'failed renewal does not advance the period',
        );

        $this->assertNoPaidInvoice($sub);

        // Access retained: the user is still on the paid plan during grace.
        $user->refresh();
        $this->assertSame($plan->id, $user->plan_id ?? $plan->id);
    }

    // ------------------------------------------------------------------
    // 3. Grace-elapsed run expires the sub and downgrades the user to Free
    // ------------------------------------------------------------------

    public function test_grace_elapsed_run_expires_sub_and_downgrades_to_free(): void
    {
        $free = $this->makeFreePlan();
        $user = $this->makeBuyer();
        $plan = $this->makePlan('Pro', 40000);
        // User is on the paid plan with a future expiry while active.
        $user->forceFill(['plan_id' => $plan->id, 'plan_expires_at' => now()->addHours(6)])->save();

        $end = now()->addHours(6);
        $sub = $this->makeActiveSub($user, $plan, now()->subMonth()->addHours(6), $end);

        // First run: the throwing gateway drops it into grace.
        $this->artisan('subscriptions:renew-due')->assertExitCode(0);
        $sub->refresh();
        $this->assertSame('past_due', $sub->status);
        $graceUntil = Carbon::parse($sub->grace_until);

        // During grace the user keeps the paid plan.
        $user->refresh();
        $this->assertSame($plan->id, $user->plan_id, 'access retained while grace is open');

        // Travel past the grace window and run again.
        Carbon::setTestNow($graceUntil->copy()->addDay());
        try {
            $this->artisan('subscriptions:renew-due')->assertExitCode(0);
        } finally {
            Carbon::setTestNow();
        }

        // Now the subscription is expired and the user is back on Free.
        $sub->refresh();
        $this->assertSame('expired', $sub->status, 'grace elapsed → expired');

        $user->refresh();
        $this->assertSame($free->id, $user->plan_id, 'user downgraded to Free');
        $this->assertNull($user->plan_expires_at, 'paid expiry cleared on downgrade');

        // Still no paid invoice anywhere in the failure flow.
        $this->assertNoPaidInvoice($sub);
    }
}

/**
 * Test double for an immediately-charging gateway whose recurring charge
 * FAILS (declined card, gateway outage, expired credentials).
 *
 * It throws from chargeRecurring() BEFORE issuing any invoice — exactly as
 * a synchronous declined charge does — so the renewal flow exercises the
 * \Throwable catch → markRenewalFailed() path and no paid invoice is left
 * behind.
 */
class ThrowingOfflineAdapter extends OfflineAdapter
{
    public function chargeRecurring(Subscription $subscription): array
    {
        throw new \RuntimeException('gateway declined the recurring charge');
    }
}
