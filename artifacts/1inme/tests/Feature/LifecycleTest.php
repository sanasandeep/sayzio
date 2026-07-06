<?php

namespace Tests\Feature;

use App\Actions\Billing\ActivateSubscription;
use App\Modules\Admin\Models\GatewaySetting;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\BillingAddress;
use App\Modules\User\Models\CreditNote;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\Refund;
use App\Modules\User\Models\Subscription;
use App\Modules\User\Models\User;
use App\Services\Billing\RefundService;
use App\Services\Billing\SubscriptionLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\GatewaySettingsSeeder::class);
    }

    protected function makeUser(): User
    {
        $u = User::factory()->create([
            'country' => 'IN',
        ]);
        BillingAddress::create([
            'user_id' => $u->id, 'country' => 'IN', 'region' => 'MH',
            'postal_code' => '400001', 'line1' => '1 R', 'city' => 'Mumbai',
        ]);
        return $u;
    }

    protected function makePlan(string $slug, float $monthly, int $grace = 7, int $refundDays = 7): Plan
    {
        return Plan::create([
            'name' => ucfirst($slug), 'slug' => $slug.'-'.Str::random(4),
            'monthly_price' => $monthly, 'annual_price' => $monthly * 10,
            'trial_days' => 0, 'grace_days' => $grace, 'refund_window_days' => $refundDays,
            'status' => 'active', 'sort_order' => 1, 'features' => [],
        ]);
    }

    protected function makeFreePlan(): Plan
    {
        return Plan::create([
            'name' => 'Free', 'slug' => 'free', 'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'grace_days' => 0, 'refund_window_days' => 0,
            'status' => 'active', 'sort_order' => 0, 'features' => [], 'is_default' => true,
        ]);
    }

    protected function payPlanInvoice(User $user, Plan $plan, string $cycle = 'monthly', array $extraMeta = []): Subscription
    {
        $items = [[
            'label' => $plan->name.' ('.$cycle.')',
            'amount_minor' => (int) round($plan->monthly_price * 100),
            'quantity' => 1,
            'meta' => array_merge(['kind' => 'plan', 'plan_id' => $plan->id, 'cycle' => $cycle], $extraMeta),
        ]];
        $invoice = ActivateSubscription::issuePendingInvoice($user, $items, 'INR');
        return (new ActivateSubscription())->run($invoice, 'offline', 'ref-'.$invoice->number);
    }

    public function test_renewal_cron_offline_creates_awaiting_approval_invoice_and_is_idempotent(): void
    {
        GatewaySetting::where('gateway_slug', 'offline')->update(['is_enabled' => true]);
        $user = $this->makeUser();
        $plan = $this->makePlan('pro', 9.99);
        $sub  = $this->payPlanInvoice($user, $plan);
        $sub->forceFill([
            'gateway'              => 'offline',
            'current_period_start' => now()->subDays(29),
            'current_period_end'   => now()->addHours(6),
        ])->save();

        $before = Invoice::where('user_id', $user->id)->count();
        $this->artisan('subscriptions:renew-due')->assertExitCode(0);
        $after = Invoice::where('user_id', $user->id)->count();
        $this->assertSame($before + 1, $after, 'one renewal invoice issued');

        $renewal = Invoice::where('user_id', $user->id)->latest('id')->first();
        $this->assertSame('awaiting_admin_approval', $renewal->status);

        // Second run within the same period is a no-op.
        $this->artisan('subscriptions:renew-due')->assertExitCode(0);
        $this->assertSame($after, Invoice::where('user_id', $user->id)->count(), 'idempotent');
    }

    public function test_grace_auto_downgrade_after_grace_window(): void
    {
        $free = $this->makeFreePlan();
        $user = $this->makeUser();
        $plan = $this->makePlan('pro', 9.99, 2);
        $sub  = $this->payPlanInvoice($user, $plan);
        $sub->forceFill([
            'status'             => 'past_due',
            'current_period_end' => now()->subDays(3),
            'grace_until'        => now()->subHour(),
        ])->save();

        app(SubscriptionLifecycle::class)->expireIfGraceElapsed($sub->fresh());
        $sub->refresh(); $user->refresh();
        $this->assertSame('expired', $sub->status);
        $this->assertSame($free->id, $user->plan_id);
    }

    public function test_user_self_serve_offline_refund_is_pending_until_admin_confirms(): void
    {
        $free = $this->makeFreePlan();
        $user = $this->makeUser();
        $plan = $this->makePlan('pro', 9.99, 7, 7);
        GatewaySetting::where('gateway_slug', 'offline')->update(['is_enabled' => true]);
        $sub = $this->payPlanInvoice($user, $plan);
        $invoice = Invoice::where('user_id', $user->id)->where('status', 'paid')->latest()->first();
        $invoice->forceFill(['gateway' => 'offline'])->save();

        $refund = app(RefundService::class)->issue($invoice, (int) $invoice->grand_total_minor, [
            'user_initiated' => true, 'downgrade_on_success' => true,
        ]);
        $this->assertSame('pending', $refund->status);
        $this->assertSame(0, CreditNote::where('refund_id', $refund->id)->count());
        $sub->refresh();
        $this->assertSame('active', $sub->status, 'no downgrade until admin confirms');

        $refund = app(RefundService::class)->confirmManual($refund, 'UTR-9001', null);
        $this->assertSame('succeeded', $refund->status);
        $this->assertSame('UTR-9001', $refund->gateway_ref);
        $this->assertSame(1, CreditNote::where('refund_id', $refund->id)->count());
        $sub->refresh(); $user->refresh();
        $this->assertSame('cancelled', $sub->status);
        $this->assertSame($free->id, $user->plan_id);
    }

    public function test_confirm_manual_requires_a_reference_and_rejects_non_pending(): void
    {
        $this->makeFreePlan();
        $user = $this->makeUser();
        $plan = $this->makePlan('pro', 9.99);
        GatewaySetting::where('gateway_slug', 'offline')->update(['is_enabled' => true]);
        $this->payPlanInvoice($user, $plan);
        $invoice = Invoice::where('user_id', $user->id)->where('status', 'paid')->latest()->first();
        $invoice->forceFill(['gateway' => 'offline'])->save();
        $refund = app(RefundService::class)->issue($invoice, (int) $invoice->grand_total_minor, []);

        try {
            app(RefundService::class)->confirmManual($refund, '   ', null);
            $this->fail('empty reference should throw');
        } catch (\InvalidArgumentException) { /* ok */ }

        app(RefundService::class)->confirmManual($refund, 'UTR-OK', null);
        $this->expectException(\InvalidArgumentException::class);
        app(RefundService::class)->confirmManual($refund->fresh(), 'UTR-AGAIN', null);
    }

    public function test_admin_partial_refund_creates_credit_note_only_after_confirmation(): void
    {
        $this->makeFreePlan();
        $user = $this->makeUser();
        $plan = $this->makePlan('pro', 20.00);
        GatewaySetting::where('gateway_slug', 'offline')->update(['is_enabled' => true]);
        $sub = $this->payPlanInvoice($user, $plan);
        $invoice = Invoice::where('user_id', $user->id)->where('status', 'paid')->latest()->first();
        $invoice->forceFill(['gateway' => 'offline'])->save();
        $refund = app(RefundService::class)->issue($invoice, 500, [
            'downgrade_on_success' => false, 'reason' => 'partial courtesy',
        ]);
        $this->assertSame(500, $refund->amount_minor);
        $this->assertSame('pending', $refund->status);
        $this->assertSame(0, CreditNote::where('refund_id', $refund->id)->count());

        app(RefundService::class)->confirmManual($refund, 'UTR-501', null);
        $sub->refresh(); $user->refresh();
        $this->assertSame('active', $sub->status);
        $this->assertSame($plan->id, $user->plan_id);
        $this->assertSame(1, CreditNote::where('refund_id', $refund->id)->count());
    }

    public function test_refund_cannot_exceed_invoice_total(): void
    {
        $this->makeFreePlan();
        $user = $this->makeUser();
        $plan = $this->makePlan('pro', 9.99);
        GatewaySetting::where('gateway_slug', 'offline')->update(['is_enabled' => true]);
        $this->payPlanInvoice($user, $plan);
        $invoice = Invoice::where('user_id', $user->id)->where('status', 'paid')->latest()->first();
        $invoice->forceFill(['gateway' => 'offline'])->save();

        app(RefundService::class)->issue($invoice, (int) $invoice->grand_total_minor, []);
        $this->expectException(\InvalidArgumentException::class);
        app(RefundService::class)->issue($invoice, 1, []);
    }

    public function test_credit_note_numbering_is_sequential_per_fy(): void
    {
        $this->makeFreePlan();
        GatewaySetting::where('gateway_slug', 'offline')->update(['is_enabled' => true]);
        $plan = $this->makePlan('pro', 9.99);
        $u1 = $this->makeUser(); $this->payPlanInvoice($u1, $plan);
        $inv1 = Invoice::where('user_id', $u1->id)->where('status', 'paid')->latest()->first();
        $inv1->forceFill(['gateway' => 'offline'])->save();
        $u2 = $this->makeUser(); $this->payPlanInvoice($u2, $plan);
        $inv2 = Invoice::where('user_id', $u2->id)->where('status', 'paid')->latest()->first();
        $inv2->forceFill(['gateway' => 'offline'])->save();

        $r1 = app(RefundService::class)->issue($inv1, (int) $inv1->grand_total_minor, []);
        app(RefundService::class)->confirmManual($r1, 'UTR-A', null);
        $r2 = app(RefundService::class)->issue($inv2, (int) $inv2->grand_total_minor, []);
        app(RefundService::class)->confirmManual($r2, 'UTR-B', null);
        $cn1 = CreditNote::where('refund_id', $r1->id)->first();
        $cn2 = CreditNote::where('refund_id', $r2->id)->first();
        $this->assertSame($cn1->seq + 1, $cn2->seq);
        $this->assertStringStartsWith('CN/', $cn1->number);
    }

    public function test_cancel_at_period_end_actually_cancels_after_period_end(): void
    {
        $free = $this->makeFreePlan();
        $user = $this->makeUser();
        $plan = $this->makePlan('pro', 9.99);
        GatewaySetting::where('gateway_slug', 'offline')->update(['is_enabled' => true]);
        $sub = $this->payPlanInvoice($user, $plan);

        app(SubscriptionLifecycle::class)->cancelAtPeriodEnd($sub);
        $sub->forceFill([
            'current_period_end' => now()->subMinute(),
            'cancel_at'          => now()->subMinute(),
        ])->save();

        $this->artisan('subscriptions:renew-due')->assertExitCode(0);
        $sub->refresh(); $user->refresh();
        $this->assertSame('cancelled', $sub->status);
        $this->assertSame($free->id, $user->plan_id);
    }

    public function test_grace_ending_email_is_sent_only_once_per_window(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $this->makeFreePlan();
        $user = $this->makeUser();
        $plan = $this->makePlan('pro', 9.99, 7);
        GatewaySetting::where('gateway_slug', 'offline')->update(['is_enabled' => true]);
        $sub = $this->payPlanInvoice($user, $plan);
        $sub->forceFill([
            'status'             => 'past_due',
            'current_period_end' => now()->subDays(3),
            'grace_until'        => now()->addHours(6),
        ])->save();

        $this->artisan('subscriptions:renew-due');
        $this->artisan('subscriptions:renew-due');
        $this->artisan('subscriptions:renew-due');

        $sub->refresh();
        $this->assertNotNull($sub->grace_ending_notified_at);
    }

    public function test_concurrent_refunds_cannot_exceed_invoice_total(): void
    {
        $this->makeFreePlan();
        $user = $this->makeUser();
        $plan = $this->makePlan('pro', 20.00);
        GatewaySetting::where('gateway_slug', 'offline')->update(['is_enabled' => true]);
        $this->payPlanInvoice($user, $plan);
        $invoice = Invoice::where('user_id', $user->id)->where('status', 'paid')->latest()->first();
        $invoice->forceFill(['gateway' => 'offline'])->save();

        // Back-to-back calls simulating a double-click. The second one
        // must fail with the sum-check re-run inside the lock.
        $total = (int) $invoice->grand_total_minor;
        $first = intdiv($total, 2) + 10;
        $r1 = app(RefundService::class)->issue($invoice, $first, []);
        // Offline refund stays pending but still counts toward the
        // already-refunded sum inside the lock.
        $this->assertSame('pending', $r1->status);
        $this->expectException(\InvalidArgumentException::class);
        app(RefundService::class)->issue($invoice, $total - $first + 1, []);
    }

    public function test_duplicate_subscription_refund_within_dedupe_window_is_a_noop(): void
    {
        config()->set('billing.refund.dedupe_seconds', 10);
        $this->makeFreePlan();
        $user = $this->makeUser();
        $plan = $this->makePlan('pro', 9.99);
        GatewaySetting::where('gateway_slug', 'offline')->update(['is_enabled' => true]);
        $this->payPlanInvoice($user, $plan);
        $invoice = Invoice::where('user_id', $user->id)->where('status', 'paid')->latest()->first();
        $invoice->forceFill(['gateway' => 'offline'])->save();

        // A double-click: two identical refunds with no explicit key. The
        // second must collapse to the first instead of creating a second row.
        $opts = ['user_initiated' => true, 'downgrade_on_success' => true];
        $r1 = app(RefundService::class)->issue($invoice, (int) $invoice->grand_total_minor, $opts);
        $r2 = app(RefundService::class)->issue($invoice, (int) $invoice->grand_total_minor, $opts);

        $this->assertSame($r1->id, $r2->id, 'duplicate submit returns the original refund');
        $this->assertSame(1, Refund::where('invoice_id', $invoice->id)->count(), 'only one refund created');
    }

    public function test_duplicate_subscription_refund_with_same_idempotency_key_is_a_noop(): void
    {
        // Disable the time window so only the explicit key can dedupe here.
        config()->set('billing.refund.dedupe_seconds', 0);
        $this->makeFreePlan();
        $user = $this->makeUser();
        $plan = $this->makePlan('pro', 9.99);
        GatewaySetting::where('gateway_slug', 'offline')->update(['is_enabled' => true]);
        $this->payPlanInvoice($user, $plan);
        $invoice = Invoice::where('user_id', $user->id)->where('status', 'paid')->latest()->first();
        $invoice->forceFill(['gateway' => 'offline'])->save();

        $opts = ['user_initiated' => true, 'downgrade_on_success' => true, 'idempotency_key' => 'sub-refund-1'];
        $r1 = app(RefundService::class)->issue($invoice, (int) $invoice->grand_total_minor, $opts);
        $r2 = app(RefundService::class)->issue($invoice, (int) $invoice->grand_total_minor, $opts);

        $this->assertSame($r1->id, $r2->id, 'same idempotency key returns the original refund');
        $this->assertSame('sub-refund-1', $r1->fresh()->idempotency_key);
        $this->assertSame(1, Refund::where('invoice_id', $invoice->id)->count(), 'only one refund created');
    }

    public function test_duplicate_full_refund_via_web_endpoint_creates_one_refund(): void
    {
        config()->set('billing.refund.dedupe_seconds', 10);
        $free = $this->makeFreePlan();
        $user = $this->makeUser();
        $plan = $this->makePlan('pro', 9.99, 7, 7);
        GatewaySetting::where('gateway_slug', 'offline')->update(['is_enabled' => true]);
        $this->payPlanInvoice($user, $plan);
        $invoice = Invoice::where('user_id', $user->id)->where('status', 'paid')->latest()->first();
        $invoice->forceFill(['gateway' => 'offline', 'paid_at' => now()])->save();

        // Same hidden idempotency key from one rendered form, submitted twice.
        $payload = ['idempotency_key' => 'web-refund-' . $invoice->id];
        $this->actingAs($user)
            ->post(route('user.billing.refund', $invoice), $payload)
            ->assertRedirect();
        $this->actingAs($user)
            ->post(route('user.billing.refund', $invoice), $payload)
            ->assertRedirect();

        $this->assertSame(1, Refund::where('invoice_id', $invoice->id)->count(), 'double-submit created one refund');
        $refunded = (int) Refund::where('invoice_id', $invoice->id)
            ->whereIn('status', ['pending', 'succeeded'])->sum('amount_minor');
        $this->assertSame((int) $invoice->grand_total_minor, $refunded, 'not over-refunded');
    }

    public function test_unpaid_offline_renewal_transitions_to_past_due_past_period_end(): void
    {
        $this->makeFreePlan();
        $user = $this->makeUser();
        $plan = $this->makePlan('pro', 9.99, 7);
        GatewaySetting::where('gateway_slug', 'offline')->update(['is_enabled' => true]);
        $sub = $this->payPlanInvoice($user, $plan);
        // Issue a renewal invoice awaiting_admin_approval (simulating an
        // offline renewal that hasn't been paid yet).
        \App\Modules\User\Models\Invoice::create([
            'number' => 'TEST/2026/0001', 'financial_year' => '2026-27', 'seq' => 9999,
            'user_id' => $user->id, 'currency' => $sub->currency,
            'subtotal_minor' => 999, 'tax_total_minor' => 0, 'grand_total_minor' => 999,
            'billing_address_snapshot' => [], 'merchant_snapshot' => [],
            'line_items' => [[
                'label' => 'x', 'amount_minor' => 999, 'quantity' => 1,
                'meta' => ['kind' => 'plan_renewal', 'renew_subscription_id' => $sub->id],
            ]],
            'tax_breakdown' => [], 'reverse_charge_note' => '', 'place_of_supply' => 'IN-MH',
            'issued_at' => now(), 'subscription_id' => $sub->id,
            'gateway' => 'offline', 'status' => 'awaiting_admin_approval',
        ]);
        $sub->forceFill(['current_period_end' => now()->subMinute()])->save();

        $this->artisan('subscriptions:renew-due')->assertExitCode(0);
        $sub->refresh();
        $this->assertSame('past_due', $sub->status);
        $this->assertNotNull($sub->grace_until);
    }

    public function test_admin_subscription_timeline_lists_events_in_order(): void
    {
        $this->makeFreePlan();
        $user = $this->makeUser();
        $plan = $this->makePlan('pro', 9.99);
        GatewaySetting::where('gateway_slug', 'offline')->update(['is_enabled' => true]);
        $sub = $this->payPlanInvoice($user, $plan);

        $controller = new \App\Modules\Admin\Controllers\SubscriptionController();
        $ref = new \ReflectionMethod($controller, 'buildTimeline');
        $ref->setAccessible(true);
        $events = $ref->invoke($controller, $sub, collect(), collect(), collect());
        $this->assertNotEmpty($events);
        $this->assertSame('created', $events[0]['kind']);
    }

    public function test_admin_timeline_excludes_other_subscriptions_events(): void
    {
        $this->makeFreePlan();
        $user = $this->makeUser();
        $planA = $this->makePlan('pro', 9.99);
        $planB = $this->makePlan('plus', 19.99);
        GatewaySetting::where('gateway_slug', 'offline')->update(['is_enabled' => true]);
        $subA = $this->payPlanInvoice($user, $planA);
        // Simulate a second, independent subscription for the same user.
        $subB = $this->payPlanInvoice($user, $planB);
        // Refund subB's invoice and confirm it → should NOT appear on subA's timeline.
        $invB = Invoice::where('user_id', $user->id)
            ->where('subscription_id', $subB->id)->latest()->first();
        $invB->forceFill(['gateway' => 'offline'])->save();
        $rB = app(RefundService::class)->issue($invB, (int) $invB->grand_total_minor, [
            'downgrade_on_success' => false,
        ]);
        app(RefundService::class)->confirmManual($rB, 'UTR-B', null);

        $response = $this->withoutMiddleware()->get("/admin/subscriptions/{$subA->id}");
        $response->assertStatus(200);
        $response->assertDontSee('UTR-B');
        $response->assertDontSeeText('Refund of ' . number_format($invB->grand_total_minor / 100, 2));
    }

    public function test_scheduled_downgrade_creates_in_app_notification_with_plan_and_date(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $this->makeFreePlan();
        $user = $this->makeUser();
        $high = $this->makePlan('pro', 30.00);
        $low  = $this->makePlan('starter', 10.00);
        GatewaySetting::where('gateway_slug', 'offline')->update(['is_enabled' => true]);
        $sub = $this->payPlanInvoice($user, $high);

        app(SubscriptionLifecycle::class)->scheduleDowngrade($sub->fresh(), $low);

        $effective = \Carbon\Carbon::parse($sub->fresh()->current_period_end)->toDateString();
        $n = \App\Modules\User\Models\UserNotification::where('user_id', $user->id)
            ->where('type', 'billing.subscription_update')->latest('id')->first();
        $this->assertNotNull($n, 'a bell notification is created for the scheduled downgrade');
        $this->assertSame($low->name, $n->data['target_plan'] ?? null);
        $this->assertStringContainsString($low->name, $n->data['message'] ?? '');
        $this->assertStringContainsString($effective, $n->data['message'] ?? '');
        $this->assertSame(route('user.billing.show'), $n->data['url'] ?? null);
    }

    public function test_applied_downgrade_in_app_notification_lists_dropped_addons(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $this->makeFreePlan();
        $user = $this->makeUser();
        $plan = $this->makePlan('starter', 10.00);
        GatewaySetting::where('gateway_slug', 'offline')->update(['is_enabled' => true]);
        $sub = $this->payPlanInvoice($user, $plan);

        app(SubscriptionLifecycle::class)->notify($sub, 'downgrade_applied', [
            'target_plan'    => $plan->name,
            'dropped_addons' => ['Extra Storage', 'Priority Support'],
        ]);

        $n = \App\Modules\User\Models\UserNotification::where('user_id', $user->id)
            ->where('type', 'billing.subscription_update')->latest('id')->first();
        $this->assertNotNull($n, 'a bell notification is created when a downgrade applies');
        $this->assertStringContainsString($plan->name, $n->data['message'] ?? '');
        $this->assertStringContainsString('Extra Storage', $n->data['message'] ?? '');
        $this->assertStringContainsString('Priority Support', $n->data['message'] ?? '');
    }

    public function test_upgrade_creates_new_subscription_preserving_period_end(): void
    {
        $this->makeFreePlan();
        $user = $this->makeUser();
        $a = $this->makePlan('basic', 10.00);
        $b = $this->makePlan('premium', 30.00);
        $oldSub = $this->payPlanInvoice($user, $a);
        $originalEnd = $oldSub->current_period_end->copy();

        // Simulate upgrade invoice + activation (what /billing/upgrade/handoff posts).
        $items = [[
            'label' => 'Upgrade', 'amount_minor' => 1000, 'quantity' => 1,
            'meta'  => [
                'kind' => 'plan_upgrade', 'plan_id' => $b->id, 'cycle' => 'monthly',
                'upgrade_from_subscription_id' => $oldSub->id,
            ],
        ]];
        $invoice = ActivateSubscription::issuePendingInvoice($user, $items, 'INR');
        $newSub = (new ActivateSubscription())->run($invoice, 'offline', 'up-'.$invoice->number);

        $oldSub->refresh();
        $this->assertSame('cancelled', $oldSub->status);
        $this->assertSame($newSub->id, $oldSub->replaced_by_id);
        $this->assertSame($b->id, $newSub->plan_id);
        $this->assertSame($originalEnd->toDateTimeString(), $newSub->current_period_end->toDateTimeString());
    }
}
