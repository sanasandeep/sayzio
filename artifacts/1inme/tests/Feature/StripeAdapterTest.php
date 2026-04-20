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
 * Stripe adapter end-to-end tests. Stubs api.stripe.com with Http::fake
 * and forges Stripe-Signature headers (t=TS,v1=HMAC_SHA256(TS.body,secret))
 * with the real verification scheme. No live network calls.
 */
class StripeAdapterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\GatewaySettingsSeeder::class);
        $row = GatewaySetting::where('gateway_slug', 'stripe')->first();
        $row->is_enabled = true;
        $row->credentials_encrypted = [
            'secret_key'     => 'sk_test_KEY',
            'publishable_key'=> 'pk_test_KEY',
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
            'country' => 'US',
        ]);
        BillingAddress::create([
            'user_id' => $u->id, 'country' => 'US', 'region' => 'CA',
            'postal_code' => '94000', 'line1' => '1 Rd', 'city' => 'SF',
        ]);
        return $u;
    }

    protected function plan(float $monthly = 9.99): Plan
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-' . Str::random(4), 'description' => 'Pro',
            'monthly_price' => $monthly, 'annual_price' => $monthly * 10, 'trial_days' => 0,
            'status' => 'active', 'sort_order' => 1, 'features' => [],
        ]);
        \App\Modules\Admin\Models\Price::create([
            'priceable_type' => Plan::class, 'priceable_id' => $plan->id,
            'currency' => 'USD', 'billing_cycle' => 'monthly',
            'amount_minor_units' => (int) round($monthly * 100),
        ]);
        \App\Modules\Admin\Models\Price::create([
            'priceable_type' => Plan::class, 'priceable_id' => $plan->id,
            'currency' => 'USD', 'billing_cycle' => 'annual',
            'amount_minor_units' => (int) round($monthly * 10 * 100),
        ]);
        return $plan;
    }

    /** Forge a valid Stripe-Signature header for `$body`. */
    protected function sign(string $body, string $secret = 'whsec_test', ?int $ts = null): string
    {
        $ts = $ts ?? time();
        $sig = hash_hmac('sha256', $ts . '.' . $body, $secret);
        return "t={$ts},v1={$sig}";
    }

    public function test_create_checkout_for_new_plan_creates_stripe_price_and_session(): void
    {
        Http::fake([
            'api.stripe.com/v1/prices'              => Http::response(['id' => 'price_XYZ'], 200),
            'api.stripe.com/v1/checkout/sessions'   => Http::response([
                'id' => 'cs_test_ABC', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_ABC',
            ], 200),
        ]);

        $user    = $this->buyer();
        $plan    = $this->plan();
        $invoice = ActivateSubscription::issuePendingInvoice(
            $user,
            [[
                'label' => 'Pro', 'amount_minor' => 999, 'quantity' => 1,
                'meta' => ['kind' => 'plan', 'plan_id' => $plan->id, 'cycle' => 'monthly'],
            ]],
            'USD',
        );

        $adapter = app(GatewayManager::class)->for('stripe');
        $result  = $adapter->createCheckout($invoice);

        $this->assertSame('redirect', $result['kind']);
        $this->assertStringContainsString('checkout.stripe.com', $result['url']);

        $this->assertDatabaseHas('payment_attempts', [
            'invoice_id'  => $invoice->id,
            'gateway'     => 'stripe',
            'gateway_ref' => 'session:cs_test_ABC',
            'status'      => 'initiated',
        ]);
    }

    public function test_subscription_checkout_sends_tax_as_separate_line_item(): void
    {
        $priceIds = ['price_BASE', 'price_TAX'];
        Http::fake([
            'api.stripe.com/v1/prices' => Http::sequence()
                ->push(['id' => 'price_BASE'], 200)
                ->push(['id' => 'price_TAX'],  200),
            'api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_test_TAX', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_TAX',
            ], 200),
        ]);

        $user    = $this->buyer();
        $plan    = $this->plan();
        $invoice = ActivateSubscription::issuePendingInvoice(
            $user,
            [[
                'label' => 'Pro', 'amount_minor' => 999, 'quantity' => 1,
                'meta' => ['kind' => 'plan', 'plan_id' => $plan->id, 'cycle' => 'monthly'],
            ]],
            'USD',
        );
        // Simulate a jurisdiction with tax (e.g. 10% GST) without
        // depending on the full tax engine flow.
        $invoice->forceFill([
            'subtotal_minor'     => 999,
            'tax_total_minor'    => 100,
            'grand_total_minor'  => 1099,
        ])->save();

        app(GatewayManager::class)->for('stripe')->createCheckout($invoice);

        // Two /v1/prices calls, tax separate from base — neither is
        // the tax-inclusive grand total.
        $priceBodies = [];
        Http::assertSent(function ($req) use (&$priceBodies) {
            if (str_contains($req->url(), '/v1/prices')) {
                $priceBodies[] = $req->body();
                return true;
            }
            return false;
        });
        $this->assertCount(2, $priceBodies, 'expected one base + one tax Price call');
        $hasBase = false; $hasTax = false;
        foreach ($priceBodies as $body) {
            if (str_contains($body, 'unit_amount=999')) $hasBase = true;
            if (str_contains($body, 'unit_amount=100')) $hasTax  = true;
            $this->assertStringNotContainsString('unit_amount=1099', $body,
                'grand total must NEVER be the recurring price (tax folded in)');
        }
        $this->assertTrue($hasBase, 'missing base Price with subtotal_minor');
        $this->assertTrue($hasTax,  'missing tax Price with tax_total_minor');

        // Checkout Session request references both prices as separate line_items.
        Http::assertSent(function ($req) {
            if (!str_contains($req->url(), '/v1/checkout/sessions')) return false;
            $body = $req->body();
            return str_contains($body, 'line_items%5B0%5D%5Bprice%5D=price_BASE')
                && str_contains($body, 'line_items%5B1%5D%5Bprice%5D=price_TAX');
        });
    }

    public function test_create_checkout_for_upgrade_uses_payment_session(): void
    {
        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_test_UPG', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_UPG',
            ], 200),
        ]);

        $user    = $this->buyer();
        $plan    = $this->plan();
        $invoice = ActivateSubscription::issuePendingInvoice(
            $user,
            [[
                'label' => 'Prorated upgrade', 'amount_minor' => 500, 'quantity' => 1,
                'meta' => ['kind' => 'plan_upgrade', 'plan_id' => $plan->id, 'cycle' => 'monthly',
                           'upgrade_from_subscription_id' => 123],
            ]],
            'USD',
        );

        $adapter = app(GatewayManager::class)->for('stripe');
        $result  = $adapter->createCheckout($invoice);

        $this->assertSame('redirect', $result['kind']);
        // Payment mode doesn't hit /v1/prices.
        Http::assertNotSent(function ($req) {
            return str_contains($req->url(), '/v1/prices');
        });
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $payload = json_encode(['id' => 'evt_1', 'type' => 'checkout.session.completed', 'data' => ['object' => []]]);
        $resp = $this->call('POST', '/webhooks/stripe', [], [], [], [
            'HTTP_Stripe-Signature' => 't=1,v1=deadbeef',
            'CONTENT_TYPE'          => 'application/json',
        ], $payload);
        $resp->assertStatus(400);
    }

    public function test_webhook_rejects_stale_timestamp(): void
    {
        $body = json_encode(['id' => 'evt_stale', 'type' => 'checkout.session.completed', 'data' => ['object' => []]]);
        $sig  = $this->sign($body, 'whsec_test', time() - 3600); // 1h old
        $resp = $this->call('POST', '/webhooks/stripe', [], [], [], [
            'HTTP_Stripe-Signature' => $sig,
            'CONTENT_TYPE'          => 'application/json',
        ], $body);
        $resp->assertStatus(400);
    }

    public function test_checkout_session_completed_activates_subscription(): void
    {
        $user    = $this->buyer();
        $plan    = $this->plan();
        $invoice = ActivateSubscription::issuePendingInvoice(
            $user,
            [[
                'label' => 'Pro', 'amount_minor' => 999, 'quantity' => 1,
                'meta' => ['kind' => 'plan', 'plan_id' => $plan->id, 'cycle' => 'monthly'],
            ]],
            'USD',
        );
        $invoice->forceFill(['gateway' => 'stripe'])->save();

        $payload = [
            'id'   => 'evt_css_1',
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id'             => 'cs_test_ABC',
                'mode'           => 'subscription',
                'payment_status' => 'paid',
                'amount_total'   => 999,
                'currency'       => 'usd',
                'subscription'   => 'sub_STRIPE_1',
                'customer'       => 'cus_ABC',
                'metadata'       => ['invoice_id' => (string) $invoice->id],
            ]],
        ];
        $body = json_encode($payload);
        $sig  = $this->sign($body);

        $resp = $this->call('POST', '/webhooks/stripe', [], [], [], [
            'HTTP_Stripe-Signature' => $sig,
            'CONTENT_TYPE'          => 'application/json',
        ], $body);
        $resp->assertStatus(200);

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertNotNull($invoice->subscription_id);

        // Idempotency: replay must not double-count.
        $resp2 = $this->call('POST', '/webhooks/stripe', [], [], [], [
            'HTTP_Stripe-Signature' => $sig,
            'CONTENT_TYPE'          => 'application/json',
        ], $body);
        $resp2->assertStatus(200);
        $this->assertSame(
            1,
            PaymentAttempt::where('gateway', 'stripe')
                ->where('gateway_ref', 'evt_css_1')->count()
        );
    }

    public function test_first_cycle_activation_stamps_gateway_subscription_id(): void
    {
        $user    = $this->buyer();
        $plan    = $this->plan();
        $invoice = ActivateSubscription::issuePendingInvoice(
            $user,
            [[
                'label' => 'Pro', 'amount_minor' => 999, 'quantity' => 1,
                'meta' => ['kind' => 'plan', 'plan_id' => $plan->id, 'cycle' => 'monthly'],
            ]],
            'USD',
        );
        $invoice->forceFill(['gateway' => 'stripe'])->save();

        $payload = [
            'id'   => 'evt_first_1',
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_test_FIRST', 'mode' => 'subscription',
                'payment_status' => 'paid', 'amount_total' => 999, 'currency' => 'usd',
                'subscription' => 'sub_FIRST', 'customer' => 'cus_FIRST',
                'metadata' => ['invoice_id' => (string) $invoice->id],
            ]],
        ];
        $body = json_encode($payload);
        $sig  = $this->sign($body);

        $this->call('POST', '/webhooks/stripe', [], [], [], [
            'HTTP_Stripe-Signature' => $sig,
            'CONTENT_TYPE'          => 'application/json',
        ], $body)->assertStatus(200);

        $invoice->refresh();
        $sub = Subscription::findOrFail($invoice->subscription_id);
        $this->assertSame('sub_FIRST', $sub->gateway_subscription_id,
            'listener must stamp Stripe subscription id from webhook PaymentAttempt');
    }

    public function test_invoice_paid_renewal_issues_renewal_invoice(): void
    {
        $user    = $this->buyer();
        $plan    = $this->plan();
        $sub = Subscription::create([
            'user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'currency' => 'USD',
            'current_period_start' => now()->subMonth(), 'current_period_end' => now()->addMinutes(10),
            'gateway' => 'stripe', 'gateway_subscription_id' => 'sub_ABC',
        ]);

        $payload = [
            'id'   => 'evt_renew_1',
            'type' => 'invoice.paid',
            'data' => ['object' => [
                'billing_reason'  => 'subscription_cycle',
                'subscription'    => 'sub_ABC',
                'amount_paid'     => 999,
                'currency'        => 'usd',
                'payment_intent'  => 'pi_RENEW',
            ]],
        ];
        $body = json_encode($payload);
        $sig  = $this->sign($body);

        $resp = $this->call('POST', '/webhooks/stripe', [], [], [], [
            'HTTP_Stripe-Signature' => $sig,
            'CONTENT_TYPE'          => 'application/json',
        ], $body);
        $resp->assertStatus(200);

        $sub->refresh();
        $this->assertTrue($sub->current_period_end->isFuture());
        $this->assertSame('active', $sub->status);
        $this->assertSame(1, Invoice::where('subscription_id', $sub->id)->where('status', 'paid')->count());
    }

    public function test_charge_refunded_webhook_closes_the_refund(): void
    {
        $user    = $this->buyer();
        $plan    = $this->plan();
        $invoice = ActivateSubscription::issuePendingInvoice(
            $user,
            [[
                'label' => 'Pro', 'amount_minor' => 999, 'quantity' => 1,
                'meta' => ['kind' => 'plan', 'plan_id' => $plan->id, 'cycle' => 'monthly'],
            ]],
            'USD',
        );
        $refund = Refund::create([
            'invoice_id'   => $invoice->id,
            'user_id'      => $user->id,
            'amount_minor' => 999,
            'currency'     => 'USD',
            'status'       => 'pending',
            'gateway'      => 'stripe',
            'gateway_ref'  => 're_TEST',
            'reason'       => 'user',
            'user_initiated' => true,
        ]);

        $payload = [
            'id'   => 'evt_refund_1',
            'type' => 'charge.refunded',
            'data' => ['object' => [
                'id'              => 'ch_ABC',
                'payment_intent'  => 'pi_ABC',
                'amount_refunded' => 999,
                'currency'        => 'usd',
                'refunds' => ['data' => [['id' => 're_TEST', 'amount' => 999]]],
            ]],
        ];
        $body = json_encode($payload);
        $sig  = $this->sign($body);

        $resp = $this->call('POST', '/webhooks/stripe', [], [], [], [
            'HTTP_Stripe-Signature' => $sig,
            'CONTENT_TYPE'          => 'application/json',
        ], $body);
        $resp->assertStatus(202);

        $refund->refresh();
        $this->assertSame('succeeded', $refund->status);
        $this->assertNotNull($refund->processed_at);
    }

    public function test_refund_calls_stripe_api_and_returns_ref(): void
    {
        Http::fake([
            'api.stripe.com/v1/refunds' => Http::response([
                'id' => 're_LIVE', 'status' => 'succeeded', 'amount' => 999,
            ], 200),
        ]);

        $user    = $this->buyer();
        $plan    = $this->plan();
        $invoice = ActivateSubscription::issuePendingInvoice(
            $user,
            [[
                'label' => 'Pro', 'amount_minor' => 999, 'quantity' => 1,
                'meta' => ['kind' => 'plan', 'plan_id' => $plan->id, 'cycle' => 'monthly'],
            ]],
            'USD',
        );
        PaymentAttempt::create([
            'invoice_id'  => $invoice->id,
            'gateway'     => 'stripe',
            'gateway_ref' => 'evt_success_X',
            'status'      => 'succeeded',
            'raw_response'=> ['stripe_payment_intent_id' => 'pi_ABC'],
        ]);

        $adapter = app(GatewayManager::class)->for('stripe');
        $out = $adapter->refund($invoice, 999, 'user request');
        $this->assertSame('re_LIVE', $out['gateway_ref']);
        $this->assertSame('succeeded', $out['status']);
    }
}
