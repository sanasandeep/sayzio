<?php

namespace Tests\Feature;

use App\Actions\Billing\ActivateSubscription;
use App\Modules\Admin\Models\Addon;
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
 * Happy-path coverage for the no-proration plan-change billing model with
 * an IMMEDIATELY-PAYING gateway (Stripe/Razorpay/etc class of adapter),
 * as opposed to the offline/manual gateway exercised by
 * {@see UpgradeDowngradeBillingFlowTest}.
 *
 * Why this exists (see memory: plan-upgrade-downgrade-billing "Testing
 * gotcha (offline gateway)"): with the offline adapter, chargeRecurring()
 * only issues an UNPAID awaiting_admin_approval invoice and never extends
 * current_period_end. So after `subscriptions:renew-due`, a downgraded sub
 * legitimately lands in past_due/grace — which is correct for offline, but
 * means the real paying path (gateway pays the renewal invoice, extends the
 * period, keeps the sub `active`) had no automated assertions. A regression
 * there would silently push *paying* customers into grace on every
 * downgrade. This test closes that gap.
 *
 * The paying gateway is modelled by {@see ImmediatelyPayingOfflineAdapter}
 * below: it reuses the offline invoice-issuing path, then immediately runs
 * {@see ActivateSubscription} on the renewal invoice (exactly what a real
 * gateway webhook does on a synchronous successful charge) so the invoice
 * is marked paid and the period is extended.
 */
class PaidGatewayDowngradeBillingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\GatewaySettingsSeeder::class);

        // Make every resolution of the offline gateway (the slug the test
        // subscriptions carry, and the one GatewaySettingsSeeder enables)
        // return a gateway that PAYS the renewal invoice immediately,
        // standing in for a Stripe/Razorpay-class adapter.
        $this->app->bind(OfflineAdapter::class, ImmediatelyPayingOfflineAdapter::class);
    }

    // ------------------------------------------------------------------
    // Fixtures (mirrors UpgradeDowngradeBillingFlowTest)
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

    protected function makeAddon(string $label): Addon
    {
        return Addon::create([
            'name'        => $label,
            'slug'        => Str::slug($label) . '-' . Str::random(4),
            'description' => $label,
            'type'        => 'recurring',
            'monthly_price' => 1.00,
            'annual_price'  => 10.00,
            'status'      => 'active',
            'is_archived' => false,
            'sort_order'  => 1,
        ]);
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

    protected function issueUpgradeInvoice(User $user, Plan $target, Subscription $from): Invoice
    {
        $minor = PricingResolver::priceForCurrency($target, 'INR', 'monthly')['amount_minor'];
        return ActivateSubscription::issuePendingInvoice($user, [[
            'label'        => $target->name . ' (monthly upgrade)',
            'amount_minor' => (int) $minor,
            'quantity'     => 1,
            'meta'         => [
                'kind'                         => 'plan_upgrade',
                'plan_id'                      => $target->id,
                'cycle'                        => 'monthly',
                'upgrade_from_subscription_id' => $from->id,
            ],
        ]], 'INR');
    }

    /** Pull the lone renewal line item out of an invoice, or fail. */
    protected function renewalLine(Invoice $invoice): array
    {
        foreach ((array) $invoice->line_items as $li) {
            if (($li['meta']['kind'] ?? null) === 'plan_renewal') {
                return $li;
            }
        }
        $this->fail('No plan_renewal line item on invoice ' . $invoice->number);
    }

    // ------------------------------------------------------------------
    // 1. Paying gateway: scheduled downgrade renews ACTIVE at lower price
    // ------------------------------------------------------------------

    public function test_paying_gateway_scheduled_downgrade_stays_active_and_advances_period(): void
    {
        $user   = $this->makeBuyer();
        $higher = $this->makePlan('Pro', 40000);
        $lower  = $this->makePlan('Starter', 20000);

        // The lower plan can carry $keep but NOT $drop.
        $keep = $this->makeAddon('Analytics');
        $drop = $this->makeAddon('Priority support');
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

        // Moved onto the lower plan, schedule cleared.
        $this->assertSame($lower->id, $sub->plan_id, 'sub moved to the lower plan');
        $this->assertNull($sub->scheduled_downgrade_plan_id, 'schedule cleared after apply');

        // THE POINT OF THIS TEST: a paying gateway settled the renewal, so
        // the sub stays ACTIVE (never past_due/grace) and its period was
        // advanced ~1 cycle into the future from the old period end.
        $this->assertSame('active', $sub->status, 'paying gateway keeps the downgraded sub active');
        $this->assertNull($sub->grace_until, 'no grace window — the renewal was paid');
        $this->assertTrue(
            Carbon::parse($sub->current_period_end)->isFuture(),
            'period advanced into the future, not left in the past',
        );
        $this->assertEqualsWithDelta(
            $endedAt->copy()->addMonth()->timestamp,
            Carbon::parse($sub->current_period_end)->timestamp,
            120,
            'renewal extends from the old period end by ~1 cycle',
        );

        // Ineligible add-on dropped, eligible one kept.
        $this->assertSame(0, SubscriptionAddon::where('subscription_id', $sub->id)
            ->where('addon_id', $drop->id)->count());
        $this->assertSame(1, SubscriptionAddon::where('subscription_id', $sub->id)
            ->where('addon_id', $keep->id)->count());

        // User mirror moved to the lower plan and expiry mirrors the sub.
        $user->refresh();
        $this->assertSame($lower->id, $user->plan_id);
        $this->assertEqualsWithDelta(
            Carbon::parse($sub->current_period_end)->timestamp,
            Carbon::parse($user->plan_expires_at)->timestamp,
            5,
            'user plan_expires_at mirrors the advanced period end',
        );

        // Billed EXACTLY ONCE at the LOWER plan price, and the invoice is
        // PAID (proving the gateway settled it, not left awaiting approval).
        $invoices = Invoice::where('subscription_id', $sub->id)->get();
        $this->assertCount(1, $invoices, 'exactly one renewal invoice; not double-billed');
        $invoice = $invoices->first();
        $this->assertSame('paid', $invoice->status, 'paying gateway marked the renewal invoice paid');
        $this->assertNotNull($invoice->paid_at);
        $line = $this->renewalLine($invoice);
        $this->assertSame(20000, (int) $line['amount_minor'], 'renewal billed at lower plan price');
        $this->assertSame($sub->id, (int) $line['meta']['renew_subscription_id']);
    }

    // ------------------------------------------------------------------
    // 2. Paying gateway: a normal (non-downgrade) renewal also stays active
    // ------------------------------------------------------------------

    public function test_paying_gateway_normal_renewal_stays_active_and_advances_period(): void
    {
        $user = $this->makeBuyer();
        $plan = $this->makePlan('Pro', 40000);

        // Due within the 24h pre-renewal window, no downgrade scheduled.
        $end = now()->addHours(6);
        $sub = $this->makeActiveSub($user, $plan, now()->subMonth()->addHours(6), $end);

        $this->artisan('subscriptions:renew-due')->assertExitCode(0);

        $sub->refresh();
        $this->assertSame('active', $sub->status, 'paid renewal keeps the sub active');
        $this->assertNull($sub->grace_until);
        $this->assertEqualsWithDelta(
            $end->copy()->addMonth()->timestamp,
            Carbon::parse($sub->current_period_end)->timestamp,
            120,
            'renewal extends from the old period end by ~1 cycle',
        );

        $invoice = Invoice::where('subscription_id', $sub->id)->firstOrFail();
        $this->assertSame('paid', $invoice->status);
        $this->assertSame(40000, (int) $this->renewalLine($invoice)['amount_minor']);
    }

    // ------------------------------------------------------------------
    // 2b. Re-running the cron NEVER double-charges a paid normal renewal
    // ------------------------------------------------------------------

    /**
     * Idempotency regression guard for the PAYING path. With a gateway that
     * marks the renewal invoice paid AND advances current_period_end in the
     * same run, a second tick within the same window must be a complete
     * no-op: one paid invoice, period advanced by exactly one cycle, the
     * customer charged once. (The offline/unpaid case is covered elsewhere;
     * this proves the guards also hold when the invoice settles immediately.)
     */
    public function test_paying_gateway_normal_renewal_is_idempotent_across_reruns(): void
    {
        $user = $this->makeBuyer();
        $plan = $this->makePlan('Pro', 40000);

        $end = now()->addHours(6);
        $sub = $this->makeActiveSub($user, $plan, now()->subMonth()->addHours(6), $end);

        // Two ticks back-to-back (overlapping schedulers / an accidental
        // manual re-run within the same renewal window).
        $this->artisan('subscriptions:renew-due')->assertExitCode(0);
        $this->artisan('subscriptions:renew-due')->assertExitCode(0);

        $sub->refresh();
        $this->assertSame('active', $sub->status, 'sub stays active after two runs');
        $this->assertNull($sub->grace_until, 'never knocked into grace');

        // Period advanced by ONLY ~1 cycle — not two. A second charge would
        // have pushed current_period_end out another month.
        $this->assertEqualsWithDelta(
            $end->copy()->addMonth()->timestamp,
            Carbon::parse($sub->current_period_end)->timestamp,
            120,
            'period advanced exactly one cycle despite two cron runs',
        );

        // Exactly one renewal invoice for the sub, and it is paid.
        $invoices = Invoice::where('subscription_id', $sub->id)->get();
        $this->assertCount(1, $invoices, 'no second renewal invoice on the re-run');
        $invoice = $invoices->first();
        $this->assertSame('paid', $invoice->status);
        $this->assertSame(40000, (int) $this->renewalLine($invoice)['amount_minor']);

        // Belt-and-braces: exactly one PAID plan_renewal charge for this sub
        // across all of the user's invoices — the customer paid once.
        $this->assertSame(1, $this->paidRenewalCount($user, $sub),
            'customer charged exactly once across both runs');
    }

    // ------------------------------------------------------------------
    // 2c. Re-running the cron NEVER double-charges a paid scheduled downgrade
    // ------------------------------------------------------------------

    /**
     * Same idempotency guarantee for the scheduled-downgrade path, which
     * runs through a separate branch (applyScheduledDowngrades) and clears
     * scheduled_downgrade_plan_id on the first run. A second tick must not
     * re-apply the downgrade nor issue a second renewal charge.
     */
    public function test_paying_gateway_scheduled_downgrade_is_idempotent_across_reruns(): void
    {
        $user   = $this->makeBuyer();
        $higher = $this->makePlan('Pro', 40000);
        $lower  = $this->makePlan('Starter', 20000);

        $endedAt = now()->subMinute();
        $sub = $this->makeActiveSub(
            $user, $higher, now()->subMonth(), $endedAt,
            ['scheduled_downgrade_plan_id' => $lower->id],
        );

        $this->artisan('subscriptions:renew-due')->assertExitCode(0);
        $this->artisan('subscriptions:renew-due')->assertExitCode(0);

        $sub->refresh();
        $this->assertSame($lower->id, $sub->plan_id, 'still on the lower plan, not re-applied');
        $this->assertNull($sub->scheduled_downgrade_plan_id, 'schedule stays cleared');
        $this->assertSame('active', $sub->status, 'paying gateway keeps it active across both runs');
        $this->assertNull($sub->grace_until);

        // Period advanced by ONLY ~1 cycle from the old period end.
        $this->assertEqualsWithDelta(
            $endedAt->copy()->addMonth()->timestamp,
            Carbon::parse($sub->current_period_end)->timestamp,
            120,
            'period advanced exactly one cycle despite two cron runs',
        );

        // Exactly one renewal invoice, paid, at the LOWER price.
        $invoices = Invoice::where('subscription_id', $sub->id)->get();
        $this->assertCount(1, $invoices, 'no second renewal invoice on the re-run');
        $invoice = $invoices->first();
        $this->assertSame('paid', $invoice->status);
        $this->assertSame(20000, (int) $this->renewalLine($invoice)['amount_minor'],
            'still billed once at the lower plan price');

        $this->assertSame(1, $this->paidRenewalCount($user, $sub),
            'customer charged exactly once across both runs');
    }

    /** Count this sub's PAID plan_renewal charges across the user's invoices. */
    protected function paidRenewalCount(User $user, Subscription $sub): int
    {
        return Invoice::where('user_id', $user->id)
            ->where('status', 'paid')
            ->get()
            ->filter(fn (Invoice $inv) => collect((array) $inv->line_items)
                ->contains(fn ($li) => ($li['meta']['kind'] ?? null) === 'plan_renewal'
                    && (int) ($li['meta']['renew_subscription_id'] ?? 0) === $sub->id))
            ->count();
    }

    // ------------------------------------------------------------------
    // 3. Paying gateway: upgrade activates immediately and resets the cycle
    // ------------------------------------------------------------------

    public function test_paying_gateway_upgrade_activates_immediately_and_resets_cycle(): void
    {
        $user = $this->makeBuyer();
        $from = $this->makePlan('Starter', 20000);
        $to   = $this->makePlan('Pro', 40000);

        $old = $this->makeActiveSub($user, $from, now()->subDays(10), now()->addDays(20));

        // The gateway settles the upgrade invoice synchronously: the
        // webhook/return handler runs ActivateSubscription with the paying
        // gateway slug. (Upgrades go through checkout, not chargeRecurring.)
        $invoice = $this->issueUpgradeInvoice($user, $to, $old);
        $new     = app(ActivateSubscription::class)->run($invoice, 'offline');

        $this->assertSame($to->id, $new->plan_id);
        $this->assertSame('active', $new->status);
        $this->assertEqualsWithDelta(
            now()->addMonth()->timestamp,
            Carbon::parse($new->current_period_end)->timestamp,
            120,
            'upgrade resets to a fresh full cycle (~now + 1 month)',
        );

        // Old sub cancelled + linked; invoice settled at the full new price.
        $old->refresh();
        $this->assertSame('cancelled', $old->status);
        $this->assertSame($new->id, $old->replaced_by_id);

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertNotNull($invoice->paid_at);

        $user->refresh();
        $this->assertSame($to->id, $user->plan_id);
        $this->assertEqualsWithDelta(
            Carbon::parse($new->current_period_end)->timestamp,
            Carbon::parse($user->plan_expires_at)->timestamp,
            5,
        );
    }
}

/**
 * Test double for an immediately-paying gateway (Stripe/Razorpay class).
 *
 * It reuses {@see OfflineAdapter::chargeRecurring} to build + persist the
 * renewal invoice exactly as production does, then immediately runs
 * {@see ActivateSubscription} on that invoice — which is precisely what a
 * real gateway's synchronous success webhook does: mark the invoice paid
 * and extend the subscription's current_period_end. This is what keeps a
 * renewed/downgraded subscription `active` instead of dropping into the
 * offline grace path.
 */
class ImmediatelyPayingOfflineAdapter extends OfflineAdapter
{
    public function chargeRecurring(Subscription $subscription): array
    {
        // Build the awaiting-approval renewal invoice via the real offline
        // path (correct line items, currency lock, subscription link).
        $result  = parent::chargeRecurring($subscription);
        $invoice = Invoice::findOrFail($result['invoice_id']);

        // Then settle it synchronously, as a paying gateway would.
        app(ActivateSubscription::class)->run($invoice, $this->slug());

        return ['kind' => 'paid', 'invoice_id' => $invoice->id];
    }
}
