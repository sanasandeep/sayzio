<?php

namespace Tests\Feature;

use App\Actions\Billing\ActivateSubscription;
use App\Modules\Admin\Models\GatewaySetting;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\BillingAddress;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\PaymentAttempt;
use App\Modules\User\Models\User;
use App\Services\Billing\GatewayManager;
use App\Services\Billing\NotImplementedException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PayU (PayUMoney India) adapter end-to-end tests.
 *
 * Request hash:  sha512(key|txnid|amount|productinfo|firstname|email|udf1..udf10|salt)
 * Response hash: sha512(salt|status|udf10..udf1|email|firstname|productinfo|amount|txnid|key)
 * udf* are all empty in this integration.
 */
class PayuAdapterTest extends TestCase
{
    use RefreshDatabase;

    protected string $key  = 'TESTKEY';
    protected string $salt = 'TESTSALT';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\GatewaySettingsSeeder::class);
        $row = GatewaySetting::where('gateway_slug', 'payumoney')->first();
        $row->is_enabled = true;
        $row->mode = 'test';
        $row->credentials_encrypted = [
            'merchant_key' => $this->key,
            'salt'         => $this->salt,
        ];
        $row->save();
    }

    protected function buyer(): User
    {
        $u = User::create([
            'name' => 'Pat Buyer',
            'email' => 'p' . Str::random(6) . '@e.com',
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
        return $plan;
    }

    /** Forge a valid PayU response hash for the surl/furl + webhook callback. */
    protected function responseHash(string $status, string $email, string $firstname, string $productinfo, string $amount, string $txnid): string
    {
        $parts = array_merge(
            [$this->salt, $status],
            array_fill(0, 10, ''),
            [$email, $firstname, $productinfo, $amount, $txnid, $this->key],
        );
        return hash('sha512', implode('|', $parts));
    }

    public function test_checkout_builds_form_with_valid_request_hash(): void
    {
        $user    = $this->buyer();
        $invoice = ActivateSubscription::issuePendingInvoice(
            $user,
            [['label' => 'Credit top-up', 'amount_minor' => 50000, 'quantity' => 1, 'meta' => ['kind' => 'topup']]],
            'INR',
        );

        $out = app(GatewayManager::class)->for('payumoney')->createCheckout($invoice);

        $this->assertSame('view', $out['kind']);
        $this->assertSame('user.checkout.payumoney', $out['view']);
        $this->assertSame('https://test.payu.in/_payment', $out['data']['action']);

        $f = $out['data']['fields'];
        $this->assertSame($this->key, $f['key']);
        $this->assertSame('500.00', $f['amount']);
        $this->assertStringStartsWith('inv' . $invoice->id . 'x', $f['txnid']);

        // Recompute the request hash exactly and assert it matches.
        $expected = hash('sha512', implode('|', array_merge(
            [$this->key, $f['txnid'], $f['amount'], $f['productinfo'], $f['firstname'], $f['email']],
            array_fill(0, 10, ''),
            [$this->salt],
        )));
        $this->assertSame($expected, $f['hash']);

        $this->assertDatabaseHas('payment_attempts', [
            'invoice_id'  => $invoice->id,
            'gateway'     => 'payumoney',
            'gateway_ref' => 'txn:' . $f['txnid'],
            'status'      => 'initiated',
        ]);
    }

    public function test_return_callback_rejects_invalid_signature(): void
    {
        $resp = $this->post('/webhooks/payumoney', [
            'status' => 'success', 'txnid' => 'inv1xabc', 'amount' => '999.00',
            'productinfo' => 'Invoice X', 'firstname' => 'Pat', 'email' => 'p@e.com',
            'key' => $this->key, 'mihpayid' => 'PAYU123', 'hash' => 'deadbeef',
        ]);
        $resp->assertStatus(400);
    }

    public function test_success_return_activates_invoice_and_is_idempotent(): void
    {
        $user    = $this->buyer();
        $plan    = $this->plan();
        $invoice = ActivateSubscription::issuePendingInvoice(
            $user,
            [['label' => 'Pro', 'amount_minor' => 99900, 'quantity' => 1,
              'meta' => ['kind' => 'plan', 'plan_id' => $plan->id, 'cycle' => 'monthly']]],
            'INR',
        );

        // Drive the real checkout to obtain the deterministic txnid + fields.
        $out   = app(GatewayManager::class)->for('payumoney')->createCheckout($invoice);
        $f     = $out['data']['fields'];
        $txnid = $f['txnid'];

        $payload = [
            'status'      => 'success',
            'txnid'       => $txnid,
            'amount'      => $f['amount'],
            'productinfo' => $f['productinfo'],
            'firstname'   => $f['firstname'],
            'email'       => $f['email'],
            'key'         => $this->key,
            'mihpayid'    => 'PAYU-OK-1',
        ];
        $payload['hash'] = $this->responseHash('success', $f['email'], $f['firstname'], $f['productinfo'], $f['amount'], $txnid);

        $resp = $this->post(route('webhooks.payumoney.return'), $payload);
        $resp->assertRedirect('/user/billing?paid=' . urlencode($invoice->number));

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertNotNull($invoice->subscription_id);

        // Replay the same callback → no duplicate succeeded attempt.
        $this->post(route('webhooks.payumoney.return'), $payload);
        $this->assertSame(1, PaymentAttempt::where('gateway', 'payumoney')
            ->where('gateway_ref', 'PAYU-OK-1')->count());
    }

    public function test_direct_s2s_webhook_activates_invoice(): void
    {
        // Resilient fulfilment path: PayU's server-to-server webhook hits
        // /webhooks/payumoney directly (no browser return). It must fulfil
        // the invoice the same way and return a 200 JSON ack.
        $user    = $this->buyer();
        $plan    = $this->plan();
        $invoice = ActivateSubscription::issuePendingInvoice(
            $user,
            [['label' => 'Pro', 'amount_minor' => 99900, 'quantity' => 1,
              'meta' => ['kind' => 'plan', 'plan_id' => $plan->id, 'cycle' => 'monthly']]],
            'INR',
        );

        $out   = app(GatewayManager::class)->for('payumoney')->createCheckout($invoice);
        $f     = $out['data']['fields'];
        $txnid = $f['txnid'];

        $payload = [
            'status'      => 'success',
            'txnid'       => $txnid,
            'amount'      => $f['amount'],
            'productinfo' => $f['productinfo'],
            'firstname'   => $f['firstname'],
            'email'       => $f['email'],
            'key'         => $this->key,
            'mihpayid'    => 'PAYU-S2S-1',
        ];
        $payload['hash'] = $this->responseHash('success', $f['email'], $f['firstname'], $f['productinfo'], $f['amount'], $txnid);

        $resp = $this->post('/webhooks/payumoney', $payload);
        $resp->assertStatus(200);

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertNotNull($invoice->subscription_id);
        $this->assertSame(1, PaymentAttempt::where('gateway', 'payumoney')
            ->where('gateway_ref', 'PAYU-S2S-1')->count());
    }

    public function test_forged_success_return_does_not_show_paid(): void
    {
        $user    = $this->buyer();
        $invoice = ActivateSubscription::issuePendingInvoice(
            $user,
            [['label' => 'Top-up', 'amount_minor' => 50000, 'quantity' => 1, 'meta' => ['kind' => 'topup']]],
            'INR',
        );
        $out = app(GatewayManager::class)->for('payumoney')->createCheckout($invoice);
        $f   = $out['data']['fields'];

        // A success-looking POST with a bogus hash must NOT mark paid and
        // must NOT redirect the buyer to a paid state.
        $resp = $this->post(route('webhooks.payumoney.return'), [
            'status' => 'success', 'txnid' => $f['txnid'], 'amount' => $f['amount'],
            'productinfo' => $f['productinfo'], 'firstname' => $f['firstname'], 'email' => $f['email'],
            'key' => $this->key, 'mihpayid' => 'PAYU-FORGE-1', 'hash' => 'not-a-real-hash',
        ]);
        $resp->assertRedirect('/user/billing?failed=' . urlencode($invoice->number));

        $invoice->refresh();
        $this->assertNotSame('paid', $invoice->status);
    }

    public function test_failure_return_redirects_failed(): void
    {
        $user    = $this->buyer();
        $invoice = ActivateSubscription::issuePendingInvoice(
            $user,
            [['label' => 'Top-up', 'amount_minor' => 50000, 'quantity' => 1, 'meta' => ['kind' => 'topup']]],
            'INR',
        );
        $out   = app(GatewayManager::class)->for('payumoney')->createCheckout($invoice);
        $f     = $out['data']['fields'];

        $payload = [
            'status' => 'failure', 'txnid' => $f['txnid'], 'amount' => $f['amount'],
            'productinfo' => $f['productinfo'], 'firstname' => $f['firstname'], 'email' => $f['email'],
            'key' => $this->key, 'mihpayid' => 'PAYU-FAIL-1',
        ];
        $payload['hash'] = $this->responseHash('failure', $f['email'], $f['firstname'], $f['productinfo'], $f['amount'], $f['txnid']);

        $resp = $this->post(route('webhooks.payumoney.return'), $payload);
        $resp->assertRedirect('/user/billing?failed=' . urlencode($invoice->number));

        $invoice->refresh();
        $this->assertNotSame('paid', $invoice->status);
    }

    public function test_refund_calls_web_service_and_reports_pending(): void
    {
        Http::fake([
            'test.payu.in/merchant/*' => Http::response([
                'status' => 1, 'msg' => 'Refund Request Queued', 'request_id' => 'RFREQ-1',
            ], 200),
        ]);

        $user    = $this->buyer();
        $plan    = $this->plan();
        $invoice = ActivateSubscription::issuePendingInvoice(
            $user,
            [['label' => 'Pro', 'amount_minor' => 99900, 'quantity' => 1,
              'meta' => ['kind' => 'plan', 'plan_id' => $plan->id, 'cycle' => 'monthly']]],
            'INR',
        );
        // A succeeded attempt carrying the PayU payment id (mihpayid).
        PaymentAttempt::create([
            'invoice_id'  => $invoice->id,
            'gateway'     => 'payumoney',
            'gateway_ref' => 'PAYU-PAID-9',
            'status'      => 'succeeded',
            'raw_response'=> ['mihpayid' => 'PAYU-PAID-9'],
        ]);

        $out = app(GatewayManager::class)->for('payumoney')->refund($invoice, 99900, 'user request');
        $this->assertSame('RFREQ-1', $out['gateway_ref']);
        $this->assertSame('pending', $out['status']);

        Http::assertSent(function ($req) {
            if (!str_contains($req->url(), 'test.payu.in/merchant')) return false;
            $body = $req->body();
            return str_contains($body, 'cancel_refund_transaction')
                && str_contains($body, 'PAYU-PAID-9');
        });
    }

    public function test_recurring_is_unsupported(): void
    {
        $this->expectException(NotImplementedException::class);
        $sub = new \App\Modules\User\Models\Subscription();
        app(GatewayManager::class)->for('payumoney')->chargeRecurring($sub);
    }

    public function test_live_mode_routes_to_secure_host(): void
    {
        $row = GatewaySetting::where('gateway_slug', 'payumoney')->first();
        $row->mode = 'live';
        $row->save();

        $user    = $this->buyer();
        $invoice = ActivateSubscription::issuePendingInvoice(
            $user,
            [['label' => 'Top-up', 'amount_minor' => 50000, 'quantity' => 1, 'meta' => ['kind' => 'topup']]],
            'INR',
        );
        $out = app(GatewayManager::class)->for('payumoney')->createCheckout($invoice);
        $this->assertSame('https://secure.payu.in/_payment', $out['data']['action']);
    }
}
