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
 * PayPal adapter end-to-end tests. Stubs api-m.sandbox.paypal.com with
 * Http::fake. Webhook signature verification is exercised by stubbing
 * PayPal's /v1/notifications/verify-webhook-signature endpoint to
 * return SUCCESS/FAILURE — we do not need to forge PayPal's X.509
 * cert chain because the adapter intentionally delegates that check
 * to PayPal's server-side API.
 */
class PaypalAdapterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\GatewaySettingsSeeder::class);
        $row = GatewaySetting::where('gateway_slug', 'paypal')->first();
        $row->is_enabled = true;
        $row->credentials_encrypted = [
            'mode'          => 'sandbox',
            'client_id'     => 'AXxxClient',
            'client_secret' => 'EXxxSecret',
            'webhook_id'    => 'WH-ABC',
        ];
        $row->save();
    }

    protected function buyer(): User
    {
        $u = User::create([
            'name' => 'Buyer ' . Str::random(4),
            'email' => 'p' . Str::random(6) . '@e.com',
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

    protected function tokenFake(): array
    {
        return [
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'A21AAtoken', 'token_type' => 'Bearer', 'expires_in' => 32000,
            ], 200),
        ];
    }

    public function test_one_time_order_checkout_returns_view_with_order_id(): void
    {
        Http::fake($this->tokenFake() + [
            'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
                'id' => 'ORDER-123',
                'status' => 'CREATED',
                'links' => [['rel' => 'approve', 'href' => 'https://paypal.com/checkoutnow?token=ORDER-123']],
            ], 201),
        ]);

        $user    = $this->buyer();
        $invoice = ActivateSubscription::issuePendingInvoice(
            $user,
            [[
                'label' => 'Credit top-up', 'amount_minor' => 500, 'quantity' => 1,
                'meta' => ['kind' => 'topup'],
            ]],
            'USD',
        );

        $adapter = app(GatewayManager::class)->for('paypal');
        $result  = $adapter->createCheckout($invoice);

        $this->assertSame('view', $result['kind']);
        $this->assertSame('user.checkout.paypal', $result['view']);
        $this->assertSame('ORDER-123', $result['data']['order_id']);
        $this->assertNull($result['data']['subscription_id']);

        $this->assertDatabaseHas('payment_attempts', [
            'invoice_id'  => $invoice->id,
            'gateway'     => 'paypal',
            'gateway_ref' => 'order:ORDER-123',
            'status'      => 'initiated',
        ]);

        // The purchase_units.custom_id must carry our invoice id so the
        // PAYMENT.CAPTURE.COMPLETED webhook round-trips it.
        Http::assertSent(function ($req) use ($invoice) {
            if (!str_contains($req->url(), '/v2/checkout/orders')) return false;
            $body = $req->body();
            return str_contains($body, '"custom_id":"' . $invoice->id . '"');
        });
    }

    public function test_subscription_checkout_ensures_product_plan_and_creates_subscription(): void
    {
        Http::fake($this->tokenFake() + [
            'api-m.sandbox.paypal.com/v1/catalogs/products' => Http::response(['id' => 'PROD-1'], 201),
            'api-m.sandbox.paypal.com/v1/billing/plans'     => Http::response(['id' => 'P-PLAN-1', 'status' => 'ACTIVE'], 201),
            'api-m.sandbox.paypal.com/v1/billing/subscriptions' => Http::response([
                'id' => 'I-SUB-1', 'status' => 'APPROVAL_PENDING',
                'links' => [['rel' => 'approve', 'href' => 'https://paypal.com/...']],
            ], 201),
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

        $result = app(GatewayManager::class)->for('paypal')->createCheckout($invoice);

        $this->assertSame('view', $result['kind']);
        $this->assertSame('I-SUB-1', $result['data']['subscription_id']);
        $this->assertNull($result['data']['order_id']);

        // Plan + Product caching: credentials_encrypted has been written
        // back with pp_product:<id> and pp_plan:<...> keys.
        $row = GatewaySetting::where('gateway_slug', 'paypal')->first();
        $creds = $row->credentials();
        $this->assertSame('PROD-1', $creds['pp_product:' . $plan->id] ?? null);
        $hasPlanCache = false;
        foreach ($creds as $k => $v) {
            if (str_starts_with($k, 'pp_plan:' . $plan->id . ':monthly:USD:')) {
                $hasPlanCache = true; break;
            }
        }
        $this->assertTrue($hasPlanCache, 'plan cache key should be persisted');
    }

    public function test_subscription_plan_sends_tax_as_percentage_not_folded_into_base(): void
    {
        Http::fake($this->tokenFake() + [
            'api-m.sandbox.paypal.com/v1/catalogs/products' => Http::response(['id' => 'PROD-2'], 201),
            'api-m.sandbox.paypal.com/v1/billing/plans'     => Http::response(['id' => 'P-PLAN-2'], 201),
            'api-m.sandbox.paypal.com/v1/billing/subscriptions' => Http::response([
                'id' => 'I-SUB-2', 'links' => [['rel' => 'approve', 'href' => 'https://x']],
            ], 201),
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
        // Simulate 10% tax jurisdiction.
        $invoice->forceFill([
            'subtotal_minor'    => 999,
            'tax_total_minor'   => 100,
            'grand_total_minor' => 1099,
        ])->save();

        app(GatewayManager::class)->for('paypal')->createCheckout($invoice);

        // The plan body must use the base amount (9.99) as fixed_price
        // and ~10.01% as taxes.percentage (100/999 = 10.01). Critically
        // it must NEVER fold tax into the recurring base.
        $planBody = null;
        Http::assertSent(function ($req) use (&$planBody) {
            if (str_contains($req->url(), '/v1/billing/plans')) {
                $planBody = json_decode($req->body(), true);
                return true;
            }
            return false;
        });
        $this->assertIsArray($planBody, 'expected POST /v1/billing/plans');
        $this->assertSame('9.99', $planBody['billing_cycles'][0]['pricing_scheme']['fixed_price']['value'],
            'fixed_price MUST be subtotal (9.99), never the tax-inclusive 10.99');
        $this->assertNotSame('10.99', $planBody['billing_cycles'][0]['pricing_scheme']['fixed_price']['value']);
        $this->assertArrayHasKey('taxes', $planBody);
        $this->assertFalse($planBody['taxes']['inclusive']);
        $this->assertGreaterThan(9.0, (float) $planBody['taxes']['percentage']);
    }

    public function test_webhook_rejects_when_verify_api_returns_failure(): void
    {
        Http::fake($this->tokenFake() + [
            'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' =>
                Http::response(['verification_status' => 'FAILURE'], 200),
        ]);

        $payload = json_encode(['id' => 'evt_reject', 'event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => []]);
        $resp = $this->call('POST', '/webhooks/paypal', [], [], [], [
            'CONTENT_TYPE'               => 'application/json',
            'HTTP_PAYPAL-AUTH-ALGO'      => 'SHA256withRSA',
            'HTTP_PAYPAL-CERT-URL'       => 'https://api.paypal.com/cert',
            'HTTP_PAYPAL-TRANSMISSION-ID'   => 'tx-1',
            'HTTP_PAYPAL-TRANSMISSION-SIG'  => 'sig-bogus',
            'HTTP_PAYPAL-TRANSMISSION-TIME' => gmdate('Y-m-d\TH:i:s\Z'),
        ], $payload);
        $resp->assertStatus(400);
    }

    public function test_webhook_rejects_when_transmission_headers_missing(): void
    {
        $payload = json_encode(['id' => 'evt_noheaders', 'event_type' => 'PAYMENT.CAPTURE.COMPLETED']);
        $resp = $this->call('POST', '/webhooks/paypal', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
        $resp->assertStatus(400);
    }

    public function test_payment_capture_completed_activates_invoice(): void
    {
        Http::fake($this->tokenFake() + [
            'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' =>
                Http::response(['verification_status' => 'SUCCESS'], 200),
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
        $invoice->forceFill(['gateway' => 'paypal'])->save();

        $payload = [
            'id'         => 'evt_capture_1',
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource'   => [
                'id'        => 'CAP-1',
                'status'    => 'COMPLETED',
                'custom_id' => (string) $invoice->id,
                'amount'    => ['currency_code' => 'USD', 'value' => '9.99'],
            ],
        ];
        $body = json_encode($payload);

        $resp = $this->call('POST', '/webhooks/paypal', [], [], [], [
            'CONTENT_TYPE'                  => 'application/json',
            'HTTP_PAYPAL-AUTH-ALGO'         => 'SHA256withRSA',
            'HTTP_PAYPAL-CERT-URL'          => 'https://api.paypal.com/cert',
            'HTTP_PAYPAL-TRANSMISSION-ID'   => 'tx-ok',
            'HTTP_PAYPAL-TRANSMISSION-SIG'  => 'sig-ok',
            'HTTP_PAYPAL-TRANSMISSION-TIME' => gmdate('Y-m-d\TH:i:s\Z'),
        ], $body);
        $resp->assertStatus(200);

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertNotNull($invoice->subscription_id);

        // Idempotent replay.
        $resp2 = $this->call('POST', '/webhooks/paypal', [], [], [], [
            'CONTENT_TYPE'                  => 'application/json',
            'HTTP_PAYPAL-AUTH-ALGO'         => 'SHA256withRSA',
            'HTTP_PAYPAL-CERT-URL'          => 'https://api.paypal.com/cert',
            'HTTP_PAYPAL-TRANSMISSION-ID'   => 'tx-ok',
            'HTTP_PAYPAL-TRANSMISSION-SIG'  => 'sig-ok',
            'HTTP_PAYPAL-TRANSMISSION-TIME' => gmdate('Y-m-d\TH:i:s\Z'),
        ], $body);
        $resp2->assertStatus(200);
        $this->assertSame(1, PaymentAttempt::where('gateway', 'paypal')
            ->where('gateway_ref', 'evt_capture_1')->count());
    }

    public function test_sale_completed_materialises_renewal_invoice(): void
    {
        Http::fake($this->tokenFake() + [
            'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' =>
                Http::response(['verification_status' => 'SUCCESS'], 200),
        ]);

        $user    = $this->buyer();
        $plan    = $this->plan();
        $sub = Subscription::create([
            'user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'currency' => 'USD',
            'current_period_start' => now()->subMonth(), 'current_period_end' => now()->addMinutes(10),
            'gateway' => 'paypal', 'gateway_subscription_id' => 'I-SUB-RENEW',
        ]);
        // Prior paid invoice proves first cycle already landed — this
        // lets the renewal branch run instead of the first-cycle guard.
        $priorInv = ActivateSubscription::issuePendingInvoice(
            $user,
            [[
                'label' => 'Pro', 'amount_minor' => 999, 'quantity' => 1,
                'meta' => ['kind' => 'plan', 'plan_id' => $plan->id, 'cycle' => 'monthly'],
            ]],
            'USD',
        );
        $priorInv->forceFill([
            'gateway' => 'paypal', 'subscription_id' => $sub->id, 'status' => 'paid',
            'paid_at' => now()->subMonth(),
        ])->save();

        $payload = [
            'id'         => 'evt_renew_1',
            'event_type' => 'PAYMENT.SALE.COMPLETED',
            'resource'   => [
                'billing_agreement_id' => 'I-SUB-RENEW',
                'amount' => ['total' => '9.99', 'currency' => 'USD'],
            ],
        ];
        $body = json_encode($payload);

        $resp = $this->call('POST', '/webhooks/paypal', [], [], [], [
            'CONTENT_TYPE'                  => 'application/json',
            'HTTP_PAYPAL-AUTH-ALGO'         => 'SHA256withRSA',
            'HTTP_PAYPAL-CERT-URL'          => 'https://api.paypal.com/cert',
            'HTTP_PAYPAL-TRANSMISSION-ID'   => 'tx-r',
            'HTTP_PAYPAL-TRANSMISSION-SIG'  => 'sig-r',
            'HTTP_PAYPAL-TRANSMISSION-TIME' => gmdate('Y-m-d\TH:i:s\Z'),
        ], $body);
        $resp->assertStatus(200);

        $sub->refresh();
        $this->assertTrue($sub->current_period_end->isFuture());
        $this->assertSame('active', $sub->status);
        // Prior invoice (first cycle) + new renewal invoice = 2 paid.
        $this->assertSame(2, Invoice::where('subscription_id', $sub->id)->where('status', 'paid')->count());
    }

    public function test_first_cycle_sale_after_activated_does_not_materialise_renewal(): void
    {
        // Regression: PayPal fires BILLING.SUBSCRIPTION.ACTIVATED AND
        // PAYMENT.SALE.COMPLETED for the first charge. ACTIVATED owns
        // the activation; the accompanying SALE must NOT create a
        // phantom "renewal" invoice while we are still comfortably
        // inside the freshly-activated period.
        Http::fake($this->tokenFake() + [
            'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' =>
                Http::response(['verification_status' => 'SUCCESS'], 200),
        ]);

        $user    = $this->buyer();
        $plan    = $this->plan();
        $sub = Subscription::create([
            'user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'currency' => 'USD',
            'current_period_start' => now(),
            // Fresh first cycle: renewal is ~1 month away.
            'current_period_end'   => now()->addMonth(),
            'gateway' => 'paypal', 'gateway_subscription_id' => 'I-SUB-FC',
        ]);
        // ACTIVATED already landed and activated the user's first-cycle invoice.
        $first = ActivateSubscription::issuePendingInvoice(
            $user,
            [[
                'label' => 'Pro', 'amount_minor' => 999, 'quantity' => 1,
                'meta'  => ['kind' => 'plan', 'plan_id' => $plan->id, 'cycle' => 'monthly'],
            ]],
            'USD',
        );
        $first->forceFill([
            'gateway' => 'paypal', 'subscription_id' => $sub->id,
            'status' => 'paid', 'paid_at' => now()->subMinutes(1),
        ])->save();

        $payload = [
            'id'         => 'evt_sale_firstcycle',
            'event_type' => 'PAYMENT.SALE.COMPLETED',
            'resource'   => [
                'billing_agreement_id' => 'I-SUB-FC',
                'amount' => ['total' => '9.99', 'currency' => 'USD'],
            ],
        ];
        $body = json_encode($payload);
        $resp = $this->call('POST', '/webhooks/paypal', [], [], [], [
            'CONTENT_TYPE'                  => 'application/json',
            'HTTP_PAYPAL-AUTH-ALGO'         => 'SHA256withRSA',
            'HTTP_PAYPAL-CERT-URL'          => 'https://api.paypal.com/cert',
            'HTTP_PAYPAL-TRANSMISSION-ID'   => 'tx-fc',
            'HTTP_PAYPAL-TRANSMISSION-SIG'  => 'sig-fc',
            'HTTP_PAYPAL-TRANSMISSION-TIME' => gmdate('Y-m-d\TH:i:s\Z'),
        ], $body);
        // Duplicate-of-ACTIVATED SALE → requires_review (202), no new invoice.
        $resp->assertStatus(202);
        $this->assertSame(1, Invoice::where('subscription_id', $sub->id)->count());
        $this->assertSame(1, Invoice::where('subscription_id', $sub->id)
            ->where('status', 'paid')->count());
    }

    public function test_refund_calls_paypal_api_and_returns_ref(): void
    {
        Http::fake($this->tokenFake() + [
            'api-m.sandbox.paypal.com/v2/payments/captures/CAP-REF/refund' => Http::response([
                'id' => 'REF-LIVE', 'status' => 'COMPLETED',
            ], 201),
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
            'gateway'     => 'paypal',
            'gateway_ref' => 'evt_paid',
            'status'      => 'succeeded',
            'raw_response'=> ['paypal_capture_id' => 'CAP-REF'],
        ]);

        $out = app(GatewayManager::class)->for('paypal')->refund($invoice, 999, 'user request');
        $this->assertSame('REF-LIVE', $out['gateway_ref']);
        $this->assertSame('succeeded', $out['status']);
    }

    public function test_dashboard_refund_resolves_capture_id_from_links(): void
    {
        // Dashboard-initiated refund: we never got a matching Refund
        // row keyed by gateway_ref. The capture id lives ONLY in
        // links[rel=up].href — must be parsed out and used to resolve
        // the invoice via PaymentAttempt.raw_response.paypal_capture_id.
        Http::fake($this->tokenFake() + [
            'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' =>
                Http::response(['verification_status' => 'SUCCESS'], 200),
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
        $invoice->forceFill(['gateway' => 'paypal', 'status' => 'paid', 'paid_at' => now()])->save();
        PaymentAttempt::create([
            'invoice_id'  => $invoice->id,
            'gateway'     => 'paypal',
            'gateway_ref' => 'cap-attempt',
            'status'      => 'succeeded',
            'raw_response'=> ['paypal_capture_id' => 'CAP-DASH-123'],
        ]);
        $refund = Refund::create([
            'user_id'      => $user->id,
            'invoice_id'   => $invoice->id,
            'gateway'      => 'paypal',
            'amount_minor' => 999,
            'currency'     => 'USD',
            'status'       => 'pending',
            'reason'       => 'dashboard',
        ]);

        $payload = [
            'id'         => 'evt_refund_dash_1',
            'event_type' => 'PAYMENT.CAPTURE.REFUNDED',
            'resource'   => [
                'id'     => 'REF-DASH-9',
                'amount' => ['value' => '9.99', 'currency_code' => 'USD'],
                // merchant invoice number; NOT a capture id.
                'invoice_id' => (string) $invoice->number,
                'links'  => [
                    ['rel' => 'self', 'href' => 'https://api.paypal.com/v2/payments/refunds/REF-DASH-9'],
                    ['rel' => 'up',   'href' => 'https://api.paypal.com/v2/payments/captures/CAP-DASH-123'],
                ],
            ],
        ];
        $body = json_encode($payload);

        $resp = $this->call('POST', '/webhooks/paypal', [], [], [], [
            'CONTENT_TYPE'                  => 'application/json',
            'HTTP_PAYPAL-AUTH-ALGO'         => 'SHA256withRSA',
            'HTTP_PAYPAL-CERT-URL'          => 'https://api.paypal.com/cert',
            'HTTP_PAYPAL-TRANSMISSION-ID'   => 'tx-rd',
            'HTTP_PAYPAL-TRANSMISSION-SIG'  => 'sig-rd',
            'HTTP_PAYPAL-TRANSMISSION-TIME' => gmdate('Y-m-d\TH:i:s\Z'),
        ], $body);
        // Refunds are never payment.succeeded — the webhook controller
        // returns 202 (requires_review). What matters is the refund
        // row was finalised via the links-parse capture-id resolution.
        $resp->assertStatus(202);

        $refund->refresh();
        $this->assertSame('succeeded', $refund->status);
        $this->assertSame('REF-DASH-9', $refund->gateway_ref);
    }
}
