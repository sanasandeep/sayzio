<?php

namespace Tests\Feature;

use App\Actions\Billing\ActivateSubscription;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\Subscription;
use App\Modules\User\Models\User;
use App\Modules\User\Models\WalletTransaction;
use App\Services\Billing\WalletService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the plan-included recurring coin grant path:
 *
 *   - First activation of a monthly plan grants the monthly coin amount.
 *   - First activation of an annual plan grants the yearly coin amount.
 *   - A renewal (plan_renewal intent) grants coins for the new period.
 *   - Re-delivering the same webhook (same invoice, already paid) is a
 *     clean no-op — coins are never double-credited.
 *   - Plans with 0 (or absent) coin amounts produce no wallet transaction.
 *
 * The idempotency key format is `plan_grant:sub:{id}:from:{YYYY-MM-DD}`,
 * where the date is the subscription's current_period_start. This key
 * changes with each new billing period so renewals always grant, while
 * re-runs for the same period are absorbed.
 */
class PlanIncludedCoinGrantTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create(['role' => 'user']);
    }

    private function makePlan(array $features = []): Plan
    {
        return Plan::create([
            'name'                    => 'Test Plan ' . Str::random(4),
            'slug'                    => 'test-plan-' . Str::random(6),
            'description'             => 'test',
            'monthly_price'           => 9.99,
            'annual_price'            => 99.99,
            'monthly_price_secondary' => 799,
            'annual_price_secondary'  => 7999,
            'trial_days'              => 0,
            'grace_days'              => 0,
            'refund_window_days'      => 0,
            'features'                => $features,
            'status'                  => 'active',
            'sort_order'              => 0,
        ]);
    }

    private function makePlanInvoice(User $user, Plan $plan, string $cycle, string $intent = 'plan', ?int $renewSubId = null): Invoice
    {
        $meta = [
            'kind'    => $intent,
            'plan_id' => $plan->id,
            'cycle'   => $cycle,
        ];
        if ($renewSubId) {
            $meta['renew_subscription_id'] = $renewSubId;
        }

        return Invoice::create([
            'number'                   => 'INV/TEST/' . Str::upper(Str::random(8)),
            'financial_year'           => '2026-27',
            'seq'                      => random_int(1, 1_000_000),
            'user_id'                  => $user->id,
            'currency'                 => 'USD',
            'subtotal_minor'           => 999,
            'tax_total_minor'          => 0,
            'grand_total_minor'        => 999,
            'billing_address_snapshot' => [],
            'merchant_snapshot'        => [],
            'tax_breakdown'            => [],
            'status'                   => 'pending',
            'line_items'               => [[
                'label'        => 'Plan subscription',
                'amount_minor' => 999,
                'quantity'     => 1,
                'meta'         => $meta,
            ]],
        ]);
    }

    public function test_monthly_activation_grants_monthly_coin_amount(): void
    {
        $user  = $this->makeUser();
        $plan  = $this->makePlan(['included_coins_monthly' => 200, 'included_coins_yearly' => 1000]);
        $inv   = $this->makePlanInvoice($user, $plan, 'monthly');

        app(ActivateSubscription::class)->run($inv, 'offline');

        $this->assertSame(200, app(WalletService::class)->getBalance($user));

        $tx = WalletTransaction::where('user_id', $user->id)->where('type', 'purchase')->first();
        $this->assertNotNull($tx);
        $this->assertSame(200, (int) $tx->delta_coins);
        $this->assertStringContainsString('Included with your', $tx->reason);
        $this->assertStringContainsString($plan->name, $tx->reason);
        $this->assertStringStartsWith('plan_grant:sub:', $tx->idempotency_key);
    }

    public function test_annual_activation_grants_yearly_coin_amount(): void
    {
        $user  = $this->makeUser();
        $plan  = $this->makePlan(['included_coins_monthly' => 200, 'included_coins_yearly' => 1000]);
        $inv   = $this->makePlanInvoice($user, $plan, 'annual');

        app(ActivateSubscription::class)->run($inv, 'offline');

        $this->assertSame(1000, app(WalletService::class)->getBalance($user));

        $tx = WalletTransaction::where('user_id', $user->id)->where('type', 'purchase')->first();
        $this->assertNotNull($tx);
        $this->assertSame(1000, (int) $tx->delta_coins);
    }

    public function test_renewal_grants_coins_for_new_period(): void
    {
        $user = $this->makeUser();
        $plan = $this->makePlan(['included_coins_monthly' => 150]);

        // First activation — creates the subscription and grants coins.
        $inv1 = $this->makePlanInvoice($user, $plan, 'monthly');
        app(ActivateSubscription::class)->run($inv1, 'offline');

        $sub = Subscription::where('user_id', $user->id)->latest('id')->firstOrFail();

        $this->assertSame(150, app(WalletService::class)->getBalance($user));

        // Simulate a month passing.
        $sub->forceFill([
            'current_period_start' => $sub->current_period_end,
            'current_period_end'   => Carbon::parse($sub->current_period_end)->addMonth(),
        ])->save();

        // Renewal invoice with the plan_renewal intent.
        $inv2 = $this->makePlanInvoice($user, $plan, 'monthly', 'plan_renewal', $sub->id);
        app(ActivateSubscription::class)->run($inv2, 'offline');

        // After renewal the user should have 2× the monthly amount.
        $this->assertSame(300, app(WalletService::class)->getBalance($user));

        $grants = WalletTransaction::where('user_id', $user->id)
            ->where('type', 'purchase')
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $grants, 'Activation and renewal should each grant coins once.');
    }

    public function test_no_double_credit_on_webhook_redelivery(): void
    {
        $user = $this->makeUser();
        $plan = $this->makePlan(['included_coins_monthly' => 500]);
        $inv  = $this->makePlanInvoice($user, $plan, 'monthly');

        $activator = app(ActivateSubscription::class);
        $activator->run($inv, 'offline');

        // Re-deliver the same activation for the already-paid invoice.
        // ActivateSubscription short-circuits on the paid-invoice guard,
        // so no second grant is attempted.
        $activator->run($inv->fresh(), 'offline');

        $this->assertSame(500, app(WalletService::class)->getBalance($user));
        $this->assertCount(
            1,
            WalletTransaction::where('user_id', $user->id)->where('type', 'purchase')->get(),
            'Re-delivery of an already-paid invoice must not double-credit coins.'
        );
    }

    public function test_idempotency_key_prevents_double_grant_even_without_invoice_guard(): void
    {
        // Defense-in-depth: if the invoice guard were bypassed, the wallet's
        // idempotency key on the transaction row must still prevent a second credit.
        $user = $this->makeUser();
        $plan = $this->makePlan(['included_coins_monthly' => 300]);

        $wallet = app(WalletService::class);
        $idem   = 'plan_grant:sub:99:from:2026-01-01';
        $opts   = ['reason' => 'test', 'idempotency_key' => $idem];

        $first  = $wallet->credit($user, 300, $opts);
        $second = $wallet->credit($user, 300, $opts);

        $this->assertSame($first->id, $second->id, 'Same idempotency key must return the original transaction.');
        $this->assertSame(300, $wallet->getBalance($user));
    }

    public function test_no_grant_when_monthly_coin_amount_is_zero(): void
    {
        $user = $this->makeUser();
        $plan = $this->makePlan(['included_coins_monthly' => 0]);
        $inv  = $this->makePlanInvoice($user, $plan, 'monthly');

        app(ActivateSubscription::class)->run($inv, 'offline');

        $this->assertSame(0, app(WalletService::class)->getBalance($user));
        $this->assertCount(
            0,
            WalletTransaction::where('user_id', $user->id)->where('type', 'purchase')->get(),
            'Zero coin amount must not produce a wallet transaction.'
        );
    }

    public function test_no_grant_when_coin_amount_is_absent_from_features(): void
    {
        $user = $this->makeUser();
        $plan = $this->makePlan([]);
        $inv  = $this->makePlanInvoice($user, $plan, 'monthly');

        app(ActivateSubscription::class)->run($inv, 'offline');

        $this->assertSame(0, app(WalletService::class)->getBalance($user));
        $this->assertCount(
            0,
            WalletTransaction::where('user_id', $user->id)->where('type', 'purchase')->get(),
            'Missing coin-grant features must not produce a wallet transaction.'
        );
    }

    public function test_annual_plan_does_not_grant_monthly_coins(): void
    {
        // Only the yearly amount should be granted for an annual subscription,
        // not the (different) monthly amount.
        $user = $this->makeUser();
        $plan = $this->makePlan(['included_coins_monthly' => 100, 'included_coins_yearly' => 800]);
        $inv  = $this->makePlanInvoice($user, $plan, 'annual');

        app(ActivateSubscription::class)->run($inv, 'offline');

        $this->assertSame(800, app(WalletService::class)->getBalance($user));
    }

    public function test_manual_admin_assignment_grants_monthly_coins(): void
    {
        $user = $this->makeUser();
        $plan = $this->makePlan(['included_coins_monthly' => 200, 'included_coins_yearly' => 1000]);

        app(ActivateSubscription::class)->grantPlanCoinsForManualAssignment($user, $plan);

        $this->assertSame(200, app(WalletService::class)->getBalance($user));

        $tx = WalletTransaction::where('user_id', $user->id)->where('type', 'purchase')->first();
        $this->assertNotNull($tx);
        $this->assertStringStartsWith("plan_grant:user:{$user->id}:plan:{$plan->id}:assigned:", $tx->idempotency_key);
    }

    public function test_manual_admin_assignment_uses_yearly_amount_for_annual_cycle_users(): void
    {
        $user = $this->makeUser();
        $user->forceFill(['billing_cycle' => 'annual'])->save();
        $plan = $this->makePlan(['included_coins_monthly' => 200, 'included_coins_yearly' => 1000]);

        app(ActivateSubscription::class)->grantPlanCoinsForManualAssignment($user->fresh(), $plan);

        $this->assertSame(1000, app(WalletService::class)->getBalance($user));
    }

    public function test_manual_admin_assignment_is_idempotent_same_day(): void
    {
        $user = $this->makeUser();
        $plan = $this->makePlan(['included_coins_monthly' => 300]);

        $activator = app(ActivateSubscription::class);
        $activator->grantPlanCoinsForManualAssignment($user, $plan);
        $activator->grantPlanCoinsForManualAssignment($user, $plan);

        $this->assertSame(300, app(WalletService::class)->getBalance($user));
        $this->assertCount(
            1,
            WalletTransaction::where('user_id', $user->id)->where('type', 'purchase')->get(),
            'A same-day duplicate admin assignment must not double-credit coins.'
        );
    }

    public function test_manual_admin_assignment_skips_grant_for_zero_coin_plan(): void
    {
        $user = $this->makeUser();
        $plan = $this->makePlan(['included_coins_monthly' => 0]);

        app(ActivateSubscription::class)->grantPlanCoinsForManualAssignment($user, $plan);

        $this->assertSame(0, app(WalletService::class)->getBalance($user));
        $this->assertCount(0, WalletTransaction::where('user_id', $user->id)->where('type', 'purchase')->get());
    }

    public function test_admin_assign_plan_route_grants_included_coins(): void
    {
        $user = $this->makeUser();
        $plan = $this->makePlan(['included_coins_monthly' => 250]);

        $role  = \App\Modules\Admin\Models\Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin']
        );
        $admin = \App\Modules\Admin\Models\Admin::create([
            'name'     => 'Test Admin',
            'email'    => 'admin-' . Str::random(6) . '@example.com',
            'password' => bcrypt('secret-password'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);

        $this->be($admin, 'admin');

        $response = $this->post(route('admin.users.assign-plan', $user), [
            'plan_id' => $plan->id,
        ]);
        $response->assertSessionHas('success');

        $this->assertSame($plan->id, $user->fresh()->plan_id);
        $this->assertSame(250, app(WalletService::class)->getBalance($user));

        // Double-submit on the same day must be a no-op for the wallet.
        $this->post(route('admin.users.assign-plan', $user), ['plan_id' => $plan->id]);
        $this->assertSame(250, app(WalletService::class)->getBalance($user));
    }

    public function test_plan_writer_persists_coin_grant_amounts_in_features(): void
    {
        // Smoke-test that PlanWriter::collectFeatures picks up the two keys
        // from the form and coerces negative input to zero.
        $writer = app(\App\Modules\Admin\Support\PlanWriter::class);

        $request = new \Illuminate\Http\Request();
        $request->merge([
            'features' => [
                'included_coins_monthly' => '250',
                'included_coins_yearly'  => '2000',
            ],
        ]);

        $out = $writer->collectFeatures($request, $request->input('features', []), []);

        $this->assertSame(250, $out['included_coins_monthly']);
        $this->assertSame(2000, $out['included_coins_yearly']);
    }

    public function test_plan_writer_clamps_negative_coin_grants_to_zero(): void
    {
        $writer  = app(\App\Modules\Admin\Support\PlanWriter::class);
        $request = new \Illuminate\Http\Request();
        $request->merge([
            'features' => [
                'included_coins_monthly' => '-50',
                'included_coins_yearly'  => '-1',
            ],
        ]);

        $out = $writer->collectFeatures($request, $request->input('features', []), []);

        $this->assertSame(0, $out['included_coins_monthly']);
        $this->assertSame(0, $out['included_coins_yearly']);
    }
}
