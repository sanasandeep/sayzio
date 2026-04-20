<?php

namespace Tests\Feature;

use App\Actions\Billing\ActivateSubscription;
use App\Modules\Admin\Models\GatewaySetting;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\BillingAddress;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\PaymentAttempt;
use App\Modules\User\Models\Refund;
use App\Modules\User\Models\Subscription;
use App\Modules\User\Models\User;
use App\Services\Billing\GatewayManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Cashfree adapter end-to-end tests. Stubs sandbox.cashfree.com/pg
 * with Http::fake. Webhook signature forgery uses the real v2
 * scheme: `x-webhook-signature` = Base64(HMAC-SHA256(
 *   x-webhook-timestamp . rawBody, clientSecret
 * )).
 */
class CashfreeAdapterTest extends TestCase
{
    use RefreshDatabase;

    protected string $clientSecret = 'cfsec_test';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\GatewaySettingsSeeder::class);
        $row = GatewaySetting::where('gateway_slug', 'cashfree')->first();
        $row->is_enabled = true;
        $row->credentials_encrypted = [
            'mode'          => 'sandbox',
            'client_id'     => 'TEST_CLIENT',
            'client_secret' => $this->clientSecret,
        ];
        $row->save();
    }

    protected function buyer(): User
    {
        $u = User::create([
            'name' => 'Buyer ' . Str::random(4),
            'email' => 'c' . Str::random(6) . '@e.com',
            'password' => bcrypt('secret'),
            'country' => 'IN',
        ]);
        BillingAddress::create([
            'user_id' => $u->id, 'country' => 'IN', 'region' => 'KA',
            'postal_code' => '560001', 'line1' => '1 Rd', 'city' => 'Bengaluru',
        ]);
        return $u;
    }

    protected function plan(float $monthly = 999.0): Plan
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-' . Str::random(4), 'description' => 'Pro',
            'monthly_price' => $monthly, 'annual_price' => $monthly * 10, 'trial_days' => 0,
            'status' => 'active', 'sort_order' => 1, 'features' => [],
        ]);
        \App\Modules\Admin\Models\Price::create([
            'priceable_type' => Plan::class, 'priceable_id' => $plan->id,
            'currency' => 'INR', 'billing_cycle' => 'monthly',
            'amount_minor_units' => (int) round($monthly * 100),
        ]);
        \App\Modules\Admin\Models\Price::create([
            'priceable_type' => Plan::class, 'priceable_id' => $plan->id,
            'currency' => 'INR', 'billing_cycle' => 'annual',
            'amount_minor_units' => (int) round($monthly * 10 * 100),
        ]);
        return $plan;
    }

    /** Forge a valid v2 webhook signature: base64(HMAC-SHA256(ts.body,secret)). */
    protected function sign(string $body, ?int $tsMs = null): array
    {
        $tsMs = $tsMs ?? (int) floor(microtime(true) * 1000);
        $sig  = base64_encode(hash_hmac('sha256', $tsMs . $body, $this->clientSecret, true));
        return ['ts' => (string) $tsMs, 'sig' => $sig];
    }

    public function test_one_time_order_checkout_creates_session_and_records_attempt(): void
    {
        Http::fake([
            'sandbox.cashfree.com/pg/orders' => Http::response([
                'order_id' => 'ORD-TEST-1',
                'cf_order_id' => 999001,
                'payment_session_id' => 'session_abc',
                'order_status' => 'ACTIVE',
            ], 200),
        ]);

        $user    = $this->buyer();
        $invoice = ActivateSubscription::issuePendingInvoice(
            $user,
            [[
                'label' => 'Credit top-up', 'amount_minor' => 50000, 'quantity' => 1,
                'meta' => ['kind' => 'topup'],
            ]],
            'INR',
        );

        $result = app(GatewayManager::class)->for('cashfree')->createCheckout($invoice);

        $this->assertSame('view', $result['kind']);
        $this->assertSame('session_abc', $result['data']['payment_session_id']);
        $this->assertSame('ORD-TEST-1', $result['data']['order_id']);

        $this->assertDatabaseHas('payment_attempts', [
            'invoice_id'  => $invoice->id,
            'gateway'     => 'cashfree',
            'gateway_ref' => 'order:ORD-TEST-1',
            'status'      => 'initiated',
        ]);

        // order_tags.invoice_id roundtrips our invoice id.
        Http::assertSent(function ($req) use ($invoice) {
            if (!str_contains($req->url(), '/pg/orders')) return false;
            $body = $req->body();
            return str_contains($body, '"invoice_id":"' . $invoice->id . '"');
        });
    }

    public function test_subscription_checkout_creates_plan_with_separate_tax(): void
    {
        Http::fake([
            'sandbox.cashfree.com/pg/plans'         => Http::response(['plan_id' => 'plan-ok'], 200),
            'sandbox.cashfree.com/pg/subscriptions' => Http::response([
                'subscription_id'        => 'sub-ok',
                'subscription_session_id'=> 'sess_sub',
                'subscription_status'    => 'INITIALIZED',
            ], 200),
        ]);

        $user    = $this->buyer();
        $plan    = $this->plan();
        $invoice = ActivateSubscription::issuePendingInvoice(
            $user,
            [[
                'label' => 'Pro', 'amount_minor' => 99900, 'quantity' => 1,
                'meta' => ['kind' => 'plan', 'plan_id' => $plan->id, 'cycle' => 'monthly'],
            ]],
            'INR',
        );
        // Simulate 18% GST.
        $invoice->forceFill([
            'subtotal_minor'    => 99900,
            'tax_total_minor'   => 17982,
            'grand_total_minor' => 117882,
        ])->save();

        app(GatewayManager::class)->for('cashfree')->createCheckout($invoice);

        $planBody = null;
        Http::assertSent(function ($req) use (&$planBody) {
            if (str_contains($req->url(), '/pg/plans')) {
                $planBody = json_decode($req->body(), true);
                return true;
            }
            return false;
        });
        $this->assertIsArray($planBody);
        $this->assertSame(999.00, (float) $planBody['plan_recurring_amount'],
            'recurring amount MUST be subtotal (999.00), never the tax-inclusive total');
        $this->assertNotSame(1178.82, (float) $planBody['plan_recurring_amount']);
        $this->assertSame(179.82, (float) $planBody['plan_tax'],
            'plan_tax MUST carry the separate tax amount (179.82)');
        $this->assertSame('monthly', $planBody['plan_interval_type']);
    }

    public function test_subscription_interval_matches_cycle(): void
    {
        Http::fake([
            'sandbox.cashfree.com/pg/plans'         => Http::response(['plan_id' => 'plan-ok'], 200),
            'sandbox.cashfree.com/pg/subscriptions' => Http::response(['subscription_id' => 'sub-ok'], 200),
        ]);

        $user    = $this->buyer();
        $plan    = $this->plan();
        $invoice = ActivateSubscription::issuePendingInvoice(
            $user,
            [[
                'label' => 'Pro annual', 'amount_minor' => 999000, 'quantity' => 1,
                'meta' => ['kind' => 'plan', 'plan_id' => $plan->id, 'cycle' => 'annual'],
            ]],
            'INR',
        );
        app(GatewayManager::class)->for('cashfree')->createCheckout($invoice);

        Http::assertSent(function ($req) {
            if (!str_contains($req->url(), '/pg/plans')) return false;
            $body = json_decode($req->body(), true);
            return ($body['plan_interval_type'] ?? null) === 'yearly';
        });
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $payload = json_encode(['type' => 'PAYMENT_SUCCESS_WEBHOOK', 'data' => []]);
        $ts = (int) floor(microtime(true) * 1000);
        $resp = $this->call('POST', '/webhooks/cashfree', [], [], [], [
            'CONTENT_TYPE'                => 'application/json',
            'HTTP_X-WEBHOOK-TIMESTAMP'    => (string) $ts,
            'HTTP_X-WEBHOOK-SIGNATURE'    => base64_encode('notright'),
        ], $payload);
        $resp->assertStatus(400);
    }

    public function test_webhook_rejects_stale_timestamp(): void
    {
        $payload = json_encode(['type' => 'PAYMENT_SUCCESS_WEBHOOK', 'data' => []]);
        $stale = (int) floor(microtime(true) * 1000) - 3600 * 1000; // 1h old
        $sig = base64_encode(hash_hmac('sha256', $stale . $payload, $this->clientSecret, true));
        $resp = $this->call('POST', '/webhooks/cashfree', [], [], [], [
            'CONTENT_TYPE'             => 'application/json',
            'HTTP_X-WEBHOOK-TIMESTAMP' => (string) $stale,
            'HTTP_X-WEBHOOK-SIGNATURE' => $sig,
        ], $payload);
        $resp->assertStatus(400);
    }

    public function test_payment_success_webhook_activates_invoice(): void
    {
        $user    = $this->buyer();
        $plan    = $this->plan();
        $invoice = ActivateSubscription::issuePendingInvoice(
            $user,
            [[
                'label' => 'Pro', 'amount_minor' => 99900, 'quantity' => 1,
                'meta' => ['kind' => 'plan', 'plan_id' => $plan->id, 'cycle' => 'monthly'],
            ]],
            'INR',
        );
        $invoice->forceFill(['gateway' => 'cashfree'])->save();

        $payload = [
            'type' => 'PAYMENT_SUCCESS_WEBHOOK',
            'data' => [
                'event_id' => 'evt_pg_1',
                'order' => [
                    'order_id'       => 'ORD-X',
                    'order_amount'   => 999.00,
                    'order_currency' => 'INR',
                    'order_tags'     => ['invoice_id' => (string) $invoice->id],
                ],
                'payment' => [
                    'cf_payment_id'    => 12345,
                    'payment_status'   => 'SUCCESS',
                    'payment_amount'   => 999.00,
                    'payment_currency' => 'INR',
                ],
            ],
        ];
        $body = json_encode($payload);
        $s = $this->sign($body);

        $resp = $this->call('POST', '/webhooks/cashfree', [], [], [], [
            'CONTENT_TYPE'             => 'application/json',
            'HTTP_X-WEBHOOK-TIMESTAMP' => $s['ts'],
            'HTTP_X-WEBHOOK-SIGNATURE' => $s['sig'],
        ], $body);
        $resp->assertStatus(200);

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertNotNull($invoice->subscription_id);

        // Idempotent replay.
        $resp2 = $this->call('POST', '/webhooks/cashfree', [], [], [], [
            'CONTENT_TYPE'             => 'application/json',
            'HTTP_X-WEBHOOK-TIMESTAMP' => $s['ts'],
            'HTTP_X-WEBHOOK-SIGNATURE' => $s['sig'],
        ], $body);
        $resp2->assertStatus(200);
        $this->assertSame(1, PaymentAttempt::where('gateway', 'cashfree')
            ->where('gateway_ref', 'evt_pg_1')->count());
    }

    public function test_subscription_payment_success_materialises_renewal(): void
    {
        $user    = $this->buyer();
        $plan    = $this->plan();
        $sub = Subscription::create([
            'user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'currency' => 'INR',
            'current_period_start' => now()->subMonth(), 'current_period_end' => now()->addMinutes(10),
            'gateway' => 'cashfree', 'gateway_subscription_id' => 'sub-ok',
        ]);

        $payload = [
            'type' => 'SUBSCRIPTION_PAYMENT_SUCCESS',
            'data' => [
                'event_id'     => 'evt_sub_renew_1',
                'subscription' => ['subscription_id' => 'sub-ok', 'plan_currency' => 'INR'],
                'payment'      => ['payment_amount' => 999.00, 'payment_currency' => 'INR'],
            ],
        ];
        $body = json_encode($payload);
        $s = $this->sign($body);

        $resp = $this->call('POST', '/webhooks/cashfree', [], [], [], [
            'CONTENT_TYPE'             => 'application/json',
            'HTTP_X-WEBHOOK-TIMESTAMP' => $s['ts'],
            'HTTP_X-WEBHOOK-SIGNATURE' => $s['sig'],
        ], $body);
        $resp->assertStatus(200);

        $sub->refresh();
        $this->assertTrue($sub->current_period_end->isFuture());
        $this->assertSame(1, Invoice::where('subscription_id', $sub->id)->where('status', 'paid')->count());
    }

    public function test_subscription_payment_success_activates_first_cycle_via_tags(): void
    {
        // First cycle: no internal subscription yet, only a pending
        // invoice. Cashfree echoes our subscription_tags back on the
        // webhook payload so we can correlate and activate that
        // pending invoice instead of routing to requires_review.
        $user    = $this->buyer();
        $plan    = $this->plan();
        $invoice = ActivateSubscription::issuePendingInvoice(
            $user,
            [[
                'label' => 'Pro', 'amount_minor' => 99900, 'quantity' => 1,
                'meta' => ['kind' => 'plan', 'plan_id' => $plan->id, 'cycle' => 'monthly'],
            ]],
            'INR',
        );
        $invoice->forceFill(['gateway' => 'cashfree'])->save();

        $payload = [
            'type' => 'SUBSCRIPTION_PAYMENT_SUCCESS',
            'data' => [
                'event_id'     => 'evt_first_cycle',
                'subscription' => [
                    'subscription_id'   => 'sub-firstcycle',
                    'plan_currency'     => 'INR',
                    'subscription_tags' => ['invoice_id' => (string) $invoice->id],
                ],
                'payment' => ['payment_amount' => 999.00, 'payment_currency' => 'INR'],
            ],
        ];
        $body = json_encode($payload);
        $s = $this->sign($body);

        $resp = $this->call('POST', '/webhooks/cashfree', [], [], [], [
            'CONTENT_TYPE'             => 'application/json',
            'HTTP_X-WEBHOOK-TIMESTAMP' => $s['ts'],
            'HTTP_X-WEBHOOK-SIGNATURE' => $s['sig'],
        ], $body);
        $resp->assertStatus(200);

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
    }

    public function test_refund_calls_cashfree_api_and_returns_ref(): void
    {
        Http::fake([
            'sandbox.cashfree.com/pg/orders/ORD-R/refunds' => Http::response([
                'refund_id' => 'ref-cf-1',
                'refund_status' => 'SUCCESS',
                'refund_amount' => 999.00,
            ], 200),
        ]);

        $user    = $this->buyer();
        $plan    = $this->plan();
        $invoice = ActivateSubscription::issuePendingInvoice(
            $user,
            [[
                'label' => 'Pro', 'amount_minor' => 99900, 'quantity' => 1,
                'meta' => ['kind' => 'plan', 'plan_id' => $plan->id, 'cycle' => 'monthly'],
            ]],
            'INR',
        );
        PaymentAttempt::create([
            'invoice_id'  => $invoice->id,
            'gateway'     => 'cashfree',
            'gateway_ref' => 'order:ORD-R',
            'status'      => 'initiated',
            'raw_response'=> ['kind' => 'order', 'ref_id' => 'ORD-R'],
        ]);

        $out = app(GatewayManager::class)->for('cashfree')->refund($invoice, 99900, 'user request');
        $this->assertSame('ref-cf-1', $out['gateway_ref']);
        $this->assertSame('succeeded', $out['status']);
    }
}
