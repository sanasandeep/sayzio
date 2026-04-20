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
 * End-to-end coverage for the Razorpay adapter. We stub Razorpay's HTTP
 * API with Http::fake() and simulate webhook deliveries with the real
 * HMAC signing scheme. No live network calls.
 */
class RazorpayAdapterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\GatewaySettingsSeeder::class);
        // IMPORTANT: load the model and save() so the `encrypted:array`
        // cast runs. Using ->update() on the query builder bypasses
        // casts and would write raw arrays that fail decryption later.
        $row = GatewaySetting::where('gateway_slug', 'razorpay')->first();
        $row->is_enabled = true;
        $row->credentials_encrypted = [
            'key_id'         => 'rzp_test_KEY',
            'key_secret'     => 'testsecret',
            'webhook_secret' => 'whsec_test',
        ];
        $row->save();
    }

    protected function buyer(): User
    {
        $u = User::create([
            'name' => 'Buyer ' . Str::random(4),
            'email' => 'b' . Str::random(6) . '@e.com',
            'password' => bcrypt('secret'),
            'country' => 'IN',
        ]);
        BillingAddress::create([
            'user_id' => $u->id, 'country' => 'IN', 'region' => 'MH',
            'postal_code' => '400001', 'line1' => '1 Rd', 'city' => 'Mumbai',
        ]);
        return $u;
    }

    protected function plan(float $monthly = 499.0): Plan
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-' . Str::random(4), 'description' => 'Pro',
            'monthly_price' => $monthly, 'annual_price' => $monthly * 10, 'trial_days' => 0,
            'status' => 'active', 'sort_order' => 1, 'features' => [],
        ]);
        // Explicit INR price row so PricingResolver::priceForCurrency finds it.
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

    public function test_create_checkout_for_new_plan_creates_razorpay_plan_and_subscription(): void
    {
        Http::fake([
            'api.razorpay.com/v1/plans'         => Http::response(['id' => 'plan_XYZ'], 200),
            'api.razorpay.com/v1/subscriptions' => Http::response(['id' => 'sub_ABC'], 200),
        ]);

        $user    = $this->buyer();
        $plan    = $this->plan();
        $invoice = ActivateSubscription::issuePendingInvoice(
            $user,
            [[
                'label' => 'Pro', 'amount_minor' => 49900, 'quantity' => 1,
                'meta' => ['kind' => 'plan', 'plan_id' => $plan->id, 'cycle' => 'monthly'],
            ]],
            'INR',
        );

        $adapter = app(GatewayManager::class)->for('razorpay');
        $result  = $adapter->createCheckout($invoice);

        $this->assertSame('view', $result['kind']);
        $this->assertSame('user.checkout.razorpay', $result['view']);
        $this->assertSame('sub_ABC', $result['data']['subscription_id']);
        $this->assertNull($result['data']['order_id']);

        // Attempt row logged for audit.
        $this->assertDatabaseHas('payment_attempts', [
            'invoice_id'  => $invoice->id,
            'gateway'     => 'razorpay',
            'gateway_ref' => 'subscription:sub_ABC',
            'status'      => 'initiated',
        ]);
    }

    public function test_create_checkout_for_upgrade_creates_razorpay_order(): void
    {
        Http::fake([
            'api.razorpay.com/v1/orders' => Http::response(['id' => 'order_UPG'], 200),
        ]);

        $user    = $this->buyer();
        $plan    = $this->plan();
        $invoice = ActivateSubscription::issuePendingInvoice(
            $user,
            [[
                'label' => 'Prorated upgrade', 'amount_minor' => 25000, 'quantity' => 1,
                'meta' => ['kind' => 'plan_upgrade', 'plan_id' => $plan->id, 'cycle' => 'monthly',
                           'upgrade_from_subscription_id' => 123],
            ]],
            'INR',
        );

        $adapter = app(GatewayManager::class)->for('razorpay');
        $result  = $adapter->createCheckout($invoice);

        $this->assertSame('order_UPG', $result['data']['order_id']);
        $this->assertNull($result['data']['subscription_id']);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $payload = json_encode(['event' => 'payment.captured', 'id' => 'evt_1']);
        $resp = $this->call('POST', '/webhooks/razorpay', [], [], [], [
            'HTTP_X-Razorpay-Signature' => 'nope',
            'CONTENT_TYPE'              => 'application/json',
        ], $payload);
        $resp->assertStatus(400);
    }

    public function test_payment_captured_webhook_activates_subscription(): void
    {
        $user    = $this->buyer();
        $plan    = $this->plan();
        $invoice = ActivateSubscription::issuePendingInvoice(
            $user,
            [[
                'label' => 'Pro', 'amount_minor' => 49900, 'quantity' => 1,
                'meta' => ['kind' => 'plan', 'plan_id' => $plan->id, 'cycle' => 'monthly'],
            ]],
            'INR',
        );
        $invoice->forceFill(['gateway' => 'razorpay'])->save();

        $payload = [
            'id'    => 'evt_success_1',
            'event' => 'payment.captured',
            'payload' => [
                'payment' => ['entity' => [
                    'id'       => 'pay_ABC',
                    'amount'   => 49900,
                    'currency' => 'INR',
                    'notes'    => ['invoice_id' => (string) $invoice->id],
                ]],
            ],
        ];
        $body = json_encode($payload);
        $sig  = hash_hmac('sha256', $body, 'whsec_test');

        $resp = $this->call('POST', '/webhooks/razorpay', [], [], [], [
            'HTTP_X-Razorpay-Signature' => $sig,
            'CONTENT_TYPE'              => 'application/json',
        ], $body);
        $resp->assertStatus(200);

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertNotNull($invoice->subscription_id);

        // Idempotency: second delivery of the same event.id must not
        // create a second PaymentAttempt or double-activate.
        $resp2 = $this->call('POST', '/webhooks/razorpay', [], [], [], [
            'HTTP_X-Razorpay-Signature' => $sig,
            'CONTENT_TYPE'              => 'application/json',
        ], $body);
        $resp2->assertStatus(200);
        $this->assertSame(
            1,
            PaymentAttempt::where('gateway', 'razorpay')
                ->where('gateway_ref', 'evt_success_1')->count()
        );
    }

    public function test_refund_processed_webhook_closes_the_refund(): void
    {
        $user    = $this->buyer();
        $plan    = $this->plan();
        $invoice = ActivateSubscription::issuePendingInvoice(
            $user,
            [[
                'label' => 'Pro', 'amount_minor' => 49900, 'quantity' => 1,
                'meta' => ['kind' => 'plan', 'plan_id' => $plan->id, 'cycle' => 'monthly'],
            ]],
            'INR',
        );
        $refund = Refund::create([
            'invoice_id'   => $invoice->id,
            'user_id'      => $user->id,
            'amount_minor' => 49900,
            'currency'     => 'INR',
            'status'       => 'pending',
            'gateway'      => 'razorpay',
            'gateway_ref'  => 'rfnd_TEST',
            'reason'       => 'user',
            'user_initiated' => true,
        ]);

        $payload = [
            'id'    => 'evt_refund_1',
            'event' => 'refund.processed',
            'payload' => [
                'refund' => ['entity' => [
                    'id' => 'rfnd_TEST', 'amount' => 49900, 'currency' => 'INR',
                ]],
            ],
        ];
        $body = json_encode($payload);
        $sig  = hash_hmac('sha256', $body, 'whsec_test');

        $resp = $this->call('POST', '/webhooks/razorpay', [], [], [], [
            'HTTP_X-Razorpay-Signature' => $sig,
            'CONTENT_TYPE'              => 'application/json',
        ], $body);
        $resp->assertStatus(202);

        $refund->refresh();
        $this->assertSame('succeeded', $refund->status);
        $this->assertNotNull($refund->processed_at);
    }

    public function test_refund_calls_razorpay_api_and_returns_ref(): void
    {
        Http::fake([
            'api.razorpay.com/v1/payments/pay_ABC/refunds' => Http::response([
                'id' => 'rfnd_LIVE', 'status' => 'processed', 'amount' => 49900,
            ], 200),
        ]);

        $user    = $this->buyer();
        $plan    = $this->plan();
        $invoice = ActivateSubscription::issuePendingInvoice(
            $user,
            [[
                'label' => 'Pro', 'amount_minor' => 49900, 'quantity' => 1,
                'meta' => ['kind' => 'plan', 'plan_id' => $plan->id, 'cycle' => 'monthly'],
            ]],
            'INR',
        );
        PaymentAttempt::create([
            'invoice_id'  => $invoice->id,
            'gateway'     => 'razorpay',
            'gateway_ref' => 'evt_success_X',
            'status'      => 'succeeded',
            'raw_response'=> ['razorpay_payment_id' => 'pay_ABC'],
        ]);

        $adapter = app(GatewayManager::class)->for('razorpay');
        $out = $adapter->refund($invoice, 49900, 'user request');
        $this->assertSame('rfnd_LIVE', $out['gateway_ref']);
        $this->assertSame('succeeded', $out['status']);
    }

    public function test_subscription_charged_issues_renewal_invoice(): void
    {
        $user    = $this->buyer();
        $plan    = $this->plan();
        // Simulate a previously activated Razorpay subscription.
        $sub = Subscription::create([
            'user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'currency' => 'INR',
            'current_period_start' => now()->subMonth(), 'current_period_end' => now()->addMinutes(10),
            'gateway' => 'razorpay', 'gateway_subscription_id' => 'sub_ABC',
        ]);

        $payload = [
            'id'    => 'evt_renewal_1',
            'event' => 'subscription.charged',
            'payload' => [
                'subscription' => ['entity' => ['id' => 'sub_ABC']],
                'payment'      => ['entity' => [
                    'id'       => 'pay_RENEW',
                    'amount'   => 49900,
                    'currency' => 'INR',
                ]],
            ],
        ];
        $body = json_encode($payload);
        $sig  = hash_hmac('sha256', $body, 'whsec_test');

        $resp = $this->call('POST', '/webhooks/razorpay', [], [], [], [
            'HTTP_X-Razorpay-Signature' => $sig,
            'CONTENT_TYPE'              => 'application/json',
        ], $body);
        $resp->assertStatus(200);

        $sub->refresh();
        $this->assertTrue($sub->current_period_end->isFuture());
        $this->assertSame('active', $sub->status);
        // A renewal invoice was issued and marked paid.
        $this->assertSame(1, Invoice::where('subscription_id', $sub->id)->where('status', 'paid')->count());
    }
}
