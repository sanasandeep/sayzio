<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\BillingAddress;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\Subscription;
use App\Modules\User\Models\User;
use App\Services\PricingResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * HTTP-level coverage for the user-facing plan-change billing buttons.
 *
 * The no-proration billing MODEL (ActivateSubscription / SubscriptionLifecycle
 * / RenewDueSubscriptions) is exercised at the action level by
 * {@see UpgradeDowngradeBillingFlowTest}. This class instead drives the real
 * request entry points the buttons POST to, so a broken request mapping
 * (wrong plan id, missing eligibility guard, bypassed no-proration rule,
 * missing owner/auth gate) is caught end-to-end:
 *
 *  Web ({@see \App\Modules\User\Controllers\BillingController}):
 *   - POST /user/billing/upgrade/handoff  → full-price upgrade invoice
 *   - POST /user/billing/upgrade/confirm  → rejects same/lower target (422)
 *   - POST /user/billing/downgrade/schedule → sets scheduled_downgrade_plan_id
 *   - POST /user/billing/downgrade/cancel → clears it
 *   - POST /user/billing/cancel → cancel-at-period-end (and supersedes a
 *     scheduled downgrade)
 *   - eligibility guards: can't "downgrade" to a higher/equal plan, can't
 *     downgrade to Free via this path
 *   - guards: unauthenticated + no-active-subscription are rejected
 *
 *  API ({@see \App\Modules\Api\Controllers\BillingController::subscription}):
 *   - GET /api/v1/billing/subscription surfaces the scheduled_downgrade block
 *     the mobile app reads (plan id/name + applies_at), null when none.
 *
 * Auth: web uses session `actingAs`; the API uses a real Bearer token
 * (Sanctum::actingAs breaks TouchSessionToken on the API path).
 */
class BillingEndpointUpgradeDowngradeTest extends TestCase
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
        $u->ensureDefaultWorkspace();
        return $u->fresh();
    }

    /** A paid, public, non-default plan priced in INR + USD. */
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

    /** The free / default plan (a downgrade to this is "cancel", not "downgrade"). */
    protected function makeFreePlan(): Plan
    {
        return Plan::create([
            'name'        => 'Free',
            'slug'        => 'free-' . Str::random(4),
            'description' => 'Free',
            'monthly_price' => 0,
            'annual_price'  => 0,
            'trial_days'  => 0,
            'grace_days'  => 7,
            'status'      => 'active',
            'is_default'  => true,
            'is_archived' => false,
            'sort_order'  => 0,
            'features'    => [],
        ]);
    }

    protected function makeActiveSub(User $user, Plan $plan, ?Carbon $start = null, ?Carbon $end = null, array $overrides = []): Subscription
    {
        return Subscription::create(array_merge([
            'user_id'              => $user->id,
            'plan_id'              => $plan->id,
            'status'               => 'active',
            'billing_cycle'        => 'monthly',
            'currency'             => 'INR',
            'gateway'              => 'offline',
            'cancel_at_period_end' => false,
            'current_period_start' => $start ?? now()->subDays(10),
            'current_period_end'   => $end ?? now()->addDays(20),
        ], $overrides));
    }

    /** Pull the lone plan_upgrade line item out of an invoice, or null. */
    protected function upgradeLine(Invoice $invoice): ?array
    {
        foreach ((array) $invoice->line_items as $li) {
            if (($li['meta']['kind'] ?? null) === 'plan_upgrade') return $li;
        }
        return null;
    }

    // ==================================================================
    // WEB — Upgrade
    // ==================================================================

    public function test_upgrade_handoff_creates_full_price_upgrade_invoice(): void
    {
        $user = $this->makeBuyer();
        $from = $this->makePlan('Starter', 20000);
        $to   = $this->makePlan('Pro', 40000);
        $sub  = $this->makeActiveSub($user, $from);

        $this->actingAs($user)
            ->post('/user/billing/upgrade/handoff', [
                'plan_id' => $to->id,
                'gateway' => 'offline',
            ])
            ->assertStatus(200); // offline adapter returns the instructions view

        $invoice = Invoice::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'awaiting_admin_approval'])
            ->latest('id')->firstOrFail();

        $line = $this->upgradeLine($invoice);
        $this->assertNotNull($line, 'upgrade invoice must carry a plan_upgrade line item');
        // No proration: the buyer is charged the FULL target-plan price.
        $this->assertSame(40000, (int) $line['amount_minor']);
        $this->assertSame($to->id, (int) $line['meta']['plan_id']);
        $this->assertSame($sub->id, (int) $line['meta']['upgrade_from_subscription_id']);
    }

    public function test_upgrade_confirm_rejects_same_or_lower_plan(): void
    {
        $user  = $this->makeBuyer();
        $from  = $this->makePlan('Pro', 40000);
        $lower = $this->makePlan('Starter', 20000);
        $this->makeActiveSub($user, $from);

        // Lower-priced target → not an upgrade.
        $this->actingAs($user)
            ->post('/user/billing/upgrade/confirm', ['plan_id' => $lower->id])
            ->assertStatus(422);

        // Same plan → not an upgrade.
        $this->actingAs($user)
            ->post('/user/billing/upgrade/confirm', ['plan_id' => $from->id])
            ->assertStatus(422);
    }

    public function test_upgrade_handoff_rejects_lower_plan_and_creates_no_invoice(): void
    {
        $user  = $this->makeBuyer();
        $from  = $this->makePlan('Pro', 40000);
        $lower = $this->makePlan('Starter', 20000);
        $this->makeActiveSub($user, $from);

        $this->actingAs($user)
            ->from('/user/billing/downgrade')
            ->post('/user/billing/upgrade/handoff', [
                'plan_id' => $lower->id,
                'gateway' => 'offline',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, Invoice::where('user_id', $user->id)->count(),
            'a rejected upgrade must not mint an invoice');
    }

    public function test_upgrade_handoff_requires_active_subscription(): void
    {
        $user = $this->makeBuyer();
        $to   = $this->makePlan('Pro', 40000);

        $this->actingAs($user)
            ->post('/user/billing/upgrade/handoff', [
                'plan_id' => $to->id,
                'gateway' => 'offline',
            ])
            ->assertStatus(404);
    }

    // ==================================================================
    // WEB — Downgrade schedule / cancel
    // ==================================================================

    public function test_schedule_downgrade_sets_scheduled_plan_id(): void
    {
        $user   = $this->makeBuyer();
        $higher = $this->makePlan('Pro', 40000);
        $lower  = $this->makePlan('Starter', 20000);
        $sub    = $this->makeActiveSub($user, $higher);

        $this->actingAs($user)
            ->post('/user/billing/downgrade/schedule', ['plan_id' => $lower->id])
            ->assertRedirect(route('user.billing.show'))
            ->assertSessionHas('status');

        $sub->refresh();
        $this->assertSame($lower->id, (int) $sub->scheduled_downgrade_plan_id);
        // Scheduling a downgrade must not immediately change the live plan.
        $this->assertSame($higher->id, (int) $sub->plan_id);
        $this->assertSame('active', $sub->status);
    }

    public function test_schedule_downgrade_rejects_higher_or_equal_plan(): void
    {
        $user   = $this->makeBuyer();
        $lowCur = $this->makePlan('Starter', 20000);
        $higher = $this->makePlan('Pro', 40000);
        $sub    = $this->makeActiveSub($user, $lowCur);

        // Higher-priced target is an upgrade, not a downgrade.
        $this->actingAs($user)
            ->from('/user/billing/downgrade')
            ->post('/user/billing/downgrade/schedule', ['plan_id' => $higher->id])
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertNull($sub->fresh()->scheduled_downgrade_plan_id);

        // Same plan is not a valid downgrade either.
        $this->actingAs($user)
            ->from('/user/billing/downgrade')
            ->post('/user/billing/downgrade/schedule', ['plan_id' => $lowCur->id])
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertNull($sub->fresh()->scheduled_downgrade_plan_id);
    }

    public function test_schedule_downgrade_rejects_free_default_plan(): void
    {
        $user   = $this->makeBuyer();
        $higher = $this->makePlan('Pro', 40000);
        $free   = $this->makeFreePlan();
        $sub    = $this->makeActiveSub($user, $higher);

        // Moving to Free is "cancel at period end", never a scheduled downgrade.
        $this->actingAs($user)
            ->from('/user/billing/downgrade')
            ->post('/user/billing/downgrade/schedule', ['plan_id' => $free->id])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNull($sub->fresh()->scheduled_downgrade_plan_id);
    }

    public function test_cancel_scheduled_downgrade_clears_plan_id(): void
    {
        $user   = $this->makeBuyer();
        $higher = $this->makePlan('Pro', 40000);
        $lower  = $this->makePlan('Starter', 20000);
        $sub    = $this->makeActiveSub($user, $higher, null, null, [
            'scheduled_downgrade_plan_id' => $lower->id,
        ]);

        $this->actingAs($user)
            ->post('/user/billing/downgrade/cancel')
            ->assertRedirect()
            ->assertSessionHas('status');

        $sub->refresh();
        $this->assertNull($sub->scheduled_downgrade_plan_id);
        // Cancelling the schedule leaves the live plan untouched.
        $this->assertSame($higher->id, (int) $sub->plan_id);
        $this->assertSame('active', $sub->status);
    }

    public function test_cancel_at_period_end_supersedes_scheduled_downgrade(): void
    {
        $user   = $this->makeBuyer();
        $higher = $this->makePlan('Pro', 40000);
        $lower  = $this->makePlan('Starter', 20000);
        $end    = now()->addDays(12);
        $sub    = $this->makeActiveSub($user, $higher, null, $end, [
            'scheduled_downgrade_plan_id' => $lower->id,
        ]);

        $this->actingAs($user)
            ->post('/user/billing/cancel')
            ->assertRedirect()
            ->assertSessionHas('status');

        $sub->refresh();
        $this->assertTrue((bool) $sub->cancel_at_period_end);
        $this->assertNotNull($sub->cancel_at);
        $this->assertEqualsWithDelta($end->timestamp, Carbon::parse($sub->cancel_at)->timestamp, 5);
        // Cancel-to-Free supersedes any scheduled paid downgrade.
        $this->assertNull($sub->scheduled_downgrade_plan_id);
    }

    public function test_schedule_downgrade_requires_active_subscription(): void
    {
        $user  = $this->makeBuyer();
        $lower = $this->makePlan('Starter', 20000);

        $this->actingAs($user)
            ->post('/user/billing/downgrade/schedule', ['plan_id' => $lower->id])
            ->assertStatus(404);
    }

    public function test_billing_routes_require_authentication(): void
    {
        $this->post('/user/billing/downgrade/schedule', ['plan_id' => 1])
            ->assertStatus(302); // redirected to login, not processed
        $this->assertGuest();
    }

    // ==================================================================
    // API — subscription() scheduled_downgrade block (mobile reads this)
    // ==================================================================

    public function test_api_subscription_exposes_scheduled_downgrade_block(): void
    {
        $user   = $this->makeBuyer();
        $higher = $this->makePlan('Pro', 40000);
        $lower  = $this->makePlan('Starter', 20000);
        $end    = now()->addDays(9);
        $sub    = $this->makeActiveSub($user, $higher, null, $end, [
            'scheduled_downgrade_plan_id' => $lower->id,
        ]);

        $res = $this->withToken($user->createToken('mobile-test')->plainTextToken)
            ->getJson('/api/v1/billing/subscription')
            ->assertStatus(200);

        $res->assertJsonPath('data.subscription.id', $sub->id)
            ->assertJsonPath('data.subscription.plan_id', $higher->id)
            ->assertJsonPath('data.subscription.scheduled_downgrade.plan_id', $lower->id)
            ->assertJsonPath('data.subscription.scheduled_downgrade.plan_name', $lower->name);

        $this->assertNotNull(
            $res->json('data.subscription.scheduled_downgrade.applies_at'),
            'mobile needs the date the downgrade takes effect',
        );
    }

    public function test_api_subscription_has_null_downgrade_when_none_scheduled(): void
    {
        $user = $this->makeBuyer();
        $plan = $this->makePlan('Pro', 40000);
        $this->makeActiveSub($user, $plan);

        $this->withToken($user->createToken('mobile-test')->plainTextToken)
            ->getJson('/api/v1/billing/subscription')
            ->assertStatus(200)
            ->assertJsonPath('data.subscription.scheduled_downgrade', null);
    }

    public function test_api_subscription_null_when_user_has_no_subscription(): void
    {
        $user = $this->makeBuyer();

        $this->withToken($user->createToken('mobile-test')->plainTextToken)
            ->getJson('/api/v1/billing/subscription')
            ->assertStatus(200)
            ->assertJsonPath('data.subscription', null);
    }

    // ==================================================================
    // API — scheduleDowngrade / cancelDowngrade action endpoints
    // (mobile parity with the web schedule/cancel buttons)
    // ==================================================================

    public function test_api_schedule_downgrade_sets_scheduled_plan_id(): void
    {
        $user   = $this->makeBuyer();
        $higher = $this->makePlan('Pro', 40000);
        $lower  = $this->makePlan('Starter', 20000);
        $sub    = $this->makeActiveSub($user, $higher);

        $res = $this->withToken($user->createToken('mobile-test')->plainTextToken)
            ->postJson('/api/v1/billing/downgrade/schedule', ['plan_id' => $lower->id])
            ->assertStatus(200);

        $res->assertJsonPath('data.scheduled_downgrade.plan_id', $lower->id)
            ->assertJsonPath('data.scheduled_downgrade.plan_name', $lower->name);
        $this->assertNotNull($res->json('data.scheduled_downgrade.applies_at'));

        $sub->refresh();
        $this->assertSame($lower->id, (int) $sub->scheduled_downgrade_plan_id);
        // Scheduling must not immediately change the live plan.
        $this->assertSame($higher->id, (int) $sub->plan_id);
        $this->assertSame('active', $sub->status);
    }

    public function test_api_schedule_downgrade_rejects_higher_or_equal_plan(): void
    {
        $user   = $this->makeBuyer();
        $lowCur = $this->makePlan('Starter', 20000);
        $higher = $this->makePlan('Pro', 40000);
        $sub    = $this->makeActiveSub($user, $lowCur);
        $token  = $user->createToken('mobile-test')->plainTextToken;

        // Higher-priced target is an upgrade, not a downgrade.
        $this->withToken($token)
            ->postJson('/api/v1/billing/downgrade/schedule', ['plan_id' => $higher->id])
            ->assertStatus(422);
        $this->assertNull($sub->fresh()->scheduled_downgrade_plan_id);

        // Same plan is not a valid downgrade either.
        $this->withToken($token)
            ->postJson('/api/v1/billing/downgrade/schedule', ['plan_id' => $lowCur->id])
            ->assertStatus(422);
        $this->assertNull($sub->fresh()->scheduled_downgrade_plan_id);
    }

    public function test_api_schedule_downgrade_rejects_free_default_plan(): void
    {
        $user   = $this->makeBuyer();
        $higher = $this->makePlan('Pro', 40000);
        $free   = $this->makeFreePlan();
        $sub    = $this->makeActiveSub($user, $higher);

        // Moving to Free is "cancel", never a scheduled downgrade.
        $this->withToken($user->createToken('mobile-test')->plainTextToken)
            ->postJson('/api/v1/billing/downgrade/schedule', ['plan_id' => $free->id])
            ->assertStatus(422);

        $this->assertNull($sub->fresh()->scheduled_downgrade_plan_id);
    }

    public function test_api_schedule_downgrade_requires_active_subscription(): void
    {
        $user  = $this->makeBuyer();
        $lower = $this->makePlan('Starter', 20000);

        $this->withToken($user->createToken('mobile-test')->plainTextToken)
            ->postJson('/api/v1/billing/downgrade/schedule', ['plan_id' => $lower->id])
            ->assertStatus(404);
    }

    public function test_api_cancel_downgrade_clears_scheduled_plan_id(): void
    {
        $user   = $this->makeBuyer();
        $higher = $this->makePlan('Pro', 40000);
        $lower  = $this->makePlan('Starter', 20000);
        $sub    = $this->makeActiveSub($user, $higher, null, null, [
            'scheduled_downgrade_plan_id' => $lower->id,
        ]);

        $this->withToken($user->createToken('mobile-test')->plainTextToken)
            ->postJson('/api/v1/billing/downgrade/cancel')
            ->assertStatus(200)
            ->assertJsonPath('data.scheduled_downgrade', null);

        $sub->refresh();
        $this->assertNull($sub->scheduled_downgrade_plan_id);
        $this->assertSame($higher->id, (int) $sub->plan_id);
        $this->assertSame('active', $sub->status);
    }

    public function test_api_cancel_downgrade_is_noop_when_none_scheduled(): void
    {
        $user = $this->makeBuyer();
        $plan = $this->makePlan('Pro', 40000);
        $this->makeActiveSub($user, $plan);

        $this->withToken($user->createToken('mobile-test')->plainTextToken)
            ->postJson('/api/v1/billing/downgrade/cancel')
            ->assertStatus(200)
            ->assertJsonPath('data.scheduled_downgrade', null);
    }

    public function test_api_cancel_downgrade_requires_active_subscription(): void
    {
        $user = $this->makeBuyer();

        $this->withToken($user->createToken('mobile-test')->plainTextToken)
            ->postJson('/api/v1/billing/downgrade/cancel')
            ->assertStatus(404);
    }

    public function test_api_billing_action_routes_require_authentication(): void
    {
        $this->postJson('/api/v1/billing/downgrade/schedule', ['plan_id' => 1])
            ->assertStatus(401);
        $this->postJson('/api/v1/billing/downgrade/cancel')
            ->assertStatus(401);
    }
}
