<?php

namespace Tests\Feature;

use App\Actions\Billing\ActivateSubscription;
use App\Modules\Admin\Models\Addon;
use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\BillingAddress;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\Subscription;
use App\Modules\User\Models\SubscriptionAddon;
use App\Modules\User\Models\SubscriptionCreditReview;
use App\Modules\User\Models\User;
use App\Services\Billing\SubscriptionLifecycle;
use App\Services\PricingResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * End-to-end coverage for the no-proration plan-change billing model.
 *
 * The model (see memory: plan-upgrade-downgrade-billing) spans many
 * surfaces and previously had no automated assertions tying them
 * together. This test exercises the four launch-critical flows plus the
 * two documented edge cases:
 *
 *   1. A mid-cycle UPGRADE charges the full new-plan price, resets the
 *      cycle to a fresh full period from now, cancels + links the old
 *      subscription, and flags ONE pending {@see SubscriptionCreditReview}
 *      for the forfeited leftover value (never auto-credited).
 *   2. APPROVING a credit review extends the new subscription's expiry
 *      (and the user's plan_expires_at mirror) by exactly the granted days.
 *   3. A scheduled DOWNGRADE, once the cycle ends, is applied by the
 *      renewal cron: the sub moves to the lower plan, add-ons the lower
 *      plan can't carry are dropped, it is re-billed at the LOWER price,
 *      and it is excluded from the normal renewal path (never charged at
 *      the old higher price).
 *   4. CANCELLING a scheduled downgrade before it applies leaves the
 *      subscription entirely untouched.
 *
 * Edge cases:
 *   - A stale/invalid downgrade target (archived / no longer cheaper) is
 *     cleared safely and the sub renews on its existing plan.
 *   - A zero-leftover credit review cannot be approved (only dismissed).
 */
class UpgradeDowngradeBillingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\GatewaySettingsSeeder::class);
    }

    // ------------------------------------------------------------------
    // Fixtures
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

    /**
     * A paid, public, non-default plan priced in INR + USD. Lower-priced
     * "from" plans and higher-priced "to" plans are built by passing the
     * INR monthly minor amount.
     */
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

    /**
     * Build a paid-then-activatable upgrade invoice carrying the
     * plan_upgrade meta ActivateSubscription keys off.
     */
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

    protected function makeAdmin(): Admin
    {
        $role = Role::firstOrCreate(
            ['slug' => 'staff-settings-manage'],
            ['name' => 'Staff (settings.manage)', 'guard' => 'admin']
        );
        $perm = Permission::firstOrCreate(
            ['slug' => 'settings.manage'],
            ['name' => 'settings.manage', 'group' => 'settings']
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);

        return Admin::create([
            'name'     => 'Admin ' . Str::random(4),
            'email'    => 'a' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
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
    // 1. Upgrade: full price, fresh cycle, pending credit review
    // ------------------------------------------------------------------

    public function test_upgrade_charges_full_price_resets_cycle_and_flags_credit_review(): void
    {
        $user = $this->makeBuyer();
        $from = $this->makePlan('Starter', 20000);
        $to   = $this->makePlan('Pro', 40000);

        // Old sub is 20 days from period end and carries one add-on.
        $oldEnd = now()->addDays(20);
        $old    = $this->makeActiveSub($user, $from, now()->subDays(10), $oldEnd);
        $addon  = $this->makeAddon('Extra seats');
        SubscriptionAddon::create([
            'subscription_id' => $old->id, 'addon_id' => $addon->id, 'qty' => 2,
        ]);

        $invoice = $this->issueUpgradeInvoice($user, $to, $old);
        $new     = app(ActivateSubscription::class)->run($invoice, 'offline');

        // New subscription on the target plan, fresh full cycle from now.
        $this->assertSame($to->id, $new->plan_id);
        $this->assertSame('active', $new->status);
        $this->assertEqualsWithDelta(
            now()->addMonth()->timestamp,
            Carbon::parse($new->current_period_end)->timestamp,
            120,
            'upgrade must reset to a fresh full cycle (~now + 1 month), not extend the old period',
        );

        // Charge equals the FULL target-plan price (no proration delta).
        $line = null;
        foreach ((array) $invoice->fresh()->line_items as $li) {
            if (($li['meta']['kind'] ?? null) === 'plan_upgrade') $line = $li;
        }
        $this->assertNotNull($line);
        $this->assertSame(40000, (int) $line['amount_minor']);

        // Old sub cancelled and linked to the replacement.
        $old->refresh();
        $this->assertSame('cancelled', $old->status);
        $this->assertSame($new->id, $old->replaced_by_id);

        // Add-on carried forward onto the new subscription.
        $this->assertSame(1, SubscriptionAddon::where('subscription_id', $new->id)
            ->where('addon_id', $addon->id)->count());

        // User mirror flipped to the new plan immediately.
        $user->refresh();
        $this->assertSame($to->id, $user->plan_id);
        $this->assertEqualsWithDelta(
            Carbon::parse($new->current_period_end)->timestamp,
            Carbon::parse($user->plan_expires_at)->timestamp,
            5,
        );

        // Exactly one pending credit review, leftover ~20 days, add-on snapshot kept.
        $reviews = SubscriptionCreditReview::where('subscription_id', $new->id)->get();
        $this->assertCount(1, $reviews);
        $review = $reviews->first();
        $this->assertSame('pending', $review->status);
        $this->assertSame($old->id, $review->old_subscription_id);
        $this->assertSame($from->id, $review->old_plan_id);
        $this->assertSame($to->id, $review->new_plan_id);
        $this->assertEqualsWithDelta(20, $review->leftover_days, 1);
        $this->assertSame($review->leftover_days, $review->leftover_addon_days,
            'carried add-on time shares the leftover plan days');
        $this->assertCount(1, $review->addons_snapshot);
    }

    // ------------------------------------------------------------------
    // 2. Approving a review extends expiry by granted days
    // ------------------------------------------------------------------

    public function test_approving_credit_review_extends_expiry_by_granted_days(): void
    {
        $user = $this->makeBuyer();
        $from = $this->makePlan('Starter', 20000);
        $to   = $this->makePlan('Pro', 40000);

        $old     = $this->makeActiveSub($user, $from, now()->subDays(10), now()->addDays(20));
        $invoice = $this->issueUpgradeInvoice($user, $to, $old);
        $new     = app(ActivateSubscription::class)->run($invoice, 'offline');

        $review = SubscriptionCreditReview::where('subscription_id', $new->id)->firstOrFail();
        $expiryBefore = Carbon::parse($new->fresh()->current_period_end);
        $userExpiryBefore = Carbon::parse($user->fresh()->plan_expires_at);

        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'admin')
            ->post("/admin/credit-reviews/{$review->id}/approve", ['granted_days' => 12])
            ->assertRedirect();

        $review->refresh();
        $this->assertSame('approved', $review->status);
        $this->assertSame(12, $review->granted_days);
        $this->assertNotNull($review->actioned_at);

        $this->assertEqualsWithDelta(
            $expiryBefore->copy()->addDays(12)->timestamp,
            Carbon::parse($new->fresh()->current_period_end)->timestamp,
            5,
        );
        $this->assertEqualsWithDelta(
            $userExpiryBefore->copy()->addDays(12)->timestamp,
            Carbon::parse($user->fresh()->plan_expires_at)->timestamp,
            5,
        );
    }

    // ------------------------------------------------------------------
    // 3. Scheduled downgrade applies at cycle end
    // ------------------------------------------------------------------

    public function test_scheduled_downgrade_applies_drops_addons_and_rebills_at_lower_price(): void
    {
        $user   = $this->makeBuyer();
        $higher = $this->makePlan('Pro', 40000);
        $lower  = $this->makePlan('Starter', 20000);

        // The lower plan can carry $keep but NOT $drop.
        $keep = $this->makeAddon('Analytics');
        $drop = $this->makeAddon('Priority support');
        $lower->addons()->attach($keep->id);

        // Period already ended; a downgrade is scheduled to the lower plan.
        $sub = $this->makeActiveSub(
            $user, $higher, now()->subMonth(), now()->subMinute(),
            ['scheduled_downgrade_plan_id' => $lower->id],
        );
        SubscriptionAddon::create(['subscription_id' => $sub->id, 'addon_id' => $keep->id, 'qty' => 1]);
        SubscriptionAddon::create(['subscription_id' => $sub->id, 'addon_id' => $drop->id, 'qty' => 1]);

        $this->artisan('subscriptions:renew-due')->assertExitCode(0);

        $sub->refresh();
        $this->assertSame($lower->id, $sub->plan_id, 'sub moved to the lower plan');
        $this->assertNull($sub->scheduled_downgrade_plan_id, 'schedule cleared after apply');
        // The sub is still a live, accessible subscription (offline renewals
        // land in past_due/grace awaiting manual payment — features stay on
        // until grace_until — but it must never be torn down here).
        $this->assertNotContains($sub->status, ['cancelled', 'expired']);

        // Ineligible add-on dropped, eligible one kept.
        $this->assertSame(0, SubscriptionAddon::where('subscription_id', $sub->id)
            ->where('addon_id', $drop->id)->count());
        $this->assertSame(1, SubscriptionAddon::where('subscription_id', $sub->id)
            ->where('addon_id', $keep->id)->count());

        // User mirror moved to the lower plan.
        $this->assertSame($lower->id, $user->fresh()->plan_id);

        // Re-billed EXACTLY ONCE at the LOWER plan price — proving it was
        // both downgrade-billed and excluded from the normal renewal pass
        // (no second invoice, and never billed at the old higher price).
        $invoices = Invoice::where('subscription_id', $sub->id)
            ->where('status', 'awaiting_admin_approval')->get();
        $this->assertCount(1, $invoices, 'exactly one renewal invoice; not double-billed');
        $line = $this->renewalLine($invoices->first());
        $this->assertSame(20000, (int) $line['amount_minor'], 'renewal billed at lower plan price');
        $this->assertSame($sub->id, (int) $line['meta']['renew_subscription_id']);
    }

    // ------------------------------------------------------------------
    // 4. Cancelling a scheduled downgrade leaves the sub untouched
    // ------------------------------------------------------------------

    public function test_cancelling_scheduled_downgrade_leaves_subscription_untouched(): void
    {
        $user   = $this->makeBuyer();
        $higher = $this->makePlan('Pro', 40000);
        $lower  = $this->makePlan('Starter', 20000);

        $end = now()->addDays(10);
        $sub = $this->makeActiveSub($user, $higher, now()->subDays(20), $end);

        $lifecycle = app(SubscriptionLifecycle::class);
        $lifecycle->scheduleDowngrade($sub, $lower);
        $this->assertSame($lower->id, $sub->fresh()->scheduled_downgrade_plan_id);

        $lifecycle->cancelScheduledDowngrade($sub);

        $sub->refresh();
        $this->assertNull($sub->scheduled_downgrade_plan_id);
        $this->assertSame($higher->id, $sub->plan_id, 'plan unchanged');
        $this->assertSame('active', $sub->status);
        $this->assertFalse((bool) $sub->cancel_at_period_end);
        $this->assertNull($sub->cancel_at);
        $this->assertEqualsWithDelta($end->timestamp, Carbon::parse($sub->current_period_end)->timestamp, 5);
    }

    // ------------------------------------------------------------------
    // Edge case: stale/invalid downgrade target cleared safely
    // ------------------------------------------------------------------

    public function test_invalid_downgrade_target_is_cleared_and_sub_renews_on_existing_plan(): void
    {
        $user   = $this->makeBuyer();
        $higher = $this->makePlan('Pro', 40000);
        $stale  = $this->makePlan('Starter', 20000);
        // The target is archived after scheduling → no longer a valid downgrade.
        $stale->forceFill(['is_archived' => true, 'status' => 'archived'])->save();

        $sub = $this->makeActiveSub(
            $user, $higher, now()->subMonth(), now()->subMinute(),
            ['scheduled_downgrade_plan_id' => $stale->id],
        );

        $this->artisan('subscriptions:renew-due')->assertExitCode(0);

        $sub->refresh();
        $this->assertNull($sub->scheduled_downgrade_plan_id, 'invalid schedule cleared');
        $this->assertSame($higher->id, $sub->plan_id, 'stays on the existing (higher) plan');
        $this->assertNotContains($sub->status, ['cancelled', 'expired'], 'sub not torn down');

        // It still renews — on the existing plan, at the existing price.
        $invoice = Invoice::where('subscription_id', $sub->id)
            ->where('status', 'awaiting_admin_approval')->latest('id')->firstOrFail();
        $line = $this->renewalLine($invoice);
        $this->assertSame(40000, (int) $line['amount_minor']);
    }

    // ------------------------------------------------------------------
    // Edge case: zero-leftover review can only be dismissed
    // ------------------------------------------------------------------

    public function test_zero_leftover_review_cannot_be_approved_only_dismissed(): void
    {
        $user   = $this->makeBuyer();
        $from   = $this->makePlan('Starter', 20000);
        $to     = $this->makePlan('Pro', 40000);
        $new    = $this->makeActiveSub($user, $to, now(), now()->addMonth());

        $review = SubscriptionCreditReview::create([
            'user_id'             => $user->id,
            'subscription_id'     => $new->id,
            'old_subscription_id' => null,
            'old_plan_id'         => $from->id,
            'new_plan_id'         => $to->id,
            'leftover_days'       => 0,
            'leftover_addon_days' => 0,
            'addons_snapshot'     => [],
            'currency'            => 'INR',
            'status'              => 'pending',
        ]);

        $admin = $this->makeAdmin();

        // Approving with no granted_days falls back to leftover_days (0) → rejected.
        $this->actingAs($admin, 'admin')
            ->post("/admin/credit-reviews/{$review->id}/approve", [])
            ->assertRedirect();
        $this->assertSame('pending', $review->fresh()->status, 'zero-leftover approve must be a no-op');

        // Dismissing works.
        $this->actingAs($admin, 'admin')
            ->post("/admin/credit-reviews/{$review->id}/dismiss", [])
            ->assertRedirect();
        $this->assertSame('dismissed', $review->fresh()->status);
        $this->assertSame(0, (int) $review->fresh()->granted_days);
    }
}
