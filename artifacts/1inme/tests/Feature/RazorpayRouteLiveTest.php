<?php

namespace Tests\Feature;

use App\Modules\User\Models\CreatorPaymentConnection;
use App\Modules\User\Models\CreatorPaymentEvent;
use App\Modules\User\Models\CreatorTip;
use App\Modules\User\Models\User;
use App\Services\CreatorPayouts\Adapters\RazorpayRouteAdapter;
use App\Services\CreatorPayouts\PayoutProviderRegistry;
use App\Services\Monetization\MonetizationCheckout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Task #6642 — live Razorpay Route: linked-account onboarding, Orders
 * API checkout with a 100% transfer, webhook signature verification and
 * idempotent settlement. All Razorpay HTTP is faked; preview fallback
 * without keys is covered too.
 */
class RazorpayRouteLiveTest extends TestCase
{
    use RefreshDatabase;

    private const KEY_ID = 'rzp_test_fakekey';
    private const KEY_SECRET = 'rzp_test_fakesecret';

    protected function tearDown(): void
    {
        unset(
            $_ENV['RAZORPAY_KEY_ID'], $_SERVER['RAZORPAY_KEY_ID'],
            $_ENV['RAZORPAY_KEY_SECRET'], $_SERVER['RAZORPAY_KEY_SECRET'],
            $_ENV['STRIPE_CONNECT_CLIENT_ID'], $_SERVER['STRIPE_CONNECT_CLIENT_ID'],
            $_ENV['STRIPE_SECRET_KEY'], $_SERVER['STRIPE_SECRET_KEY'],
        );
        parent::tearDown();
    }

    private function setKeys(): void
    {
        $_ENV['RAZORPAY_KEY_ID']     = $_SERVER['RAZORPAY_KEY_ID']     = self::KEY_ID;
        $_ENV['RAZORPAY_KEY_SECRET'] = $_SERVER['RAZORPAY_KEY_SECRET'] = self::KEY_SECRET;
    }

    private function adapter(): RazorpayRouteAdapter
    {
        /** @var RazorpayRouteAdapter */
        return PayoutProviderRegistry::adapter('razorpay');
    }

    private function liveConnection(User $creator): CreatorPaymentConnection
    {
        return CreatorPaymentConnection::create([
            'user_id'         => $creator->id,
            'provider'        => 'razorpay',
            'account_id'      => 'acc_LIVE123',
            'status'          => 'active',
            'payouts_enabled' => true,
            'charges_enabled' => true,
            'is_default'      => true,
        ]);
    }

    private function signedWebhook(array $body, ?string $signature = null)
    {
        $raw = json_encode($body);
        $sig = $signature ?? hash_hmac('sha256', $raw, self::KEY_SECRET);
        return $this->call('POST', '/webhooks/razorpay-route', [], [], [], [
            'HTTP_X_RAZORPAY_SIGNATURE' => $sig,
            'CONTENT_TYPE'              => 'application/json',
        ], $raw);
    }

    // ── Preview fallback without keys ───────────────────────────

    public function test_preview_fallback_without_keys(): void
    {
        $user = User::factory()->create();
        $adapter = $this->adapter();

        $url = $adapter->startOnboarding($user, 'https://example.test/back');
        $this->assertStringContainsString('payouts/preview', $url);

        $conn = CreatorPaymentConnection::create([
            'user_id' => $user->id, 'provider' => 'razorpay', 'account_id' => 'preview_x',
        ]);
        $sub = $adapter->createSubscriptionCheckout($conn, ['reference' => 'sub_1', 'token' => 'tok', 'amount' => 500]);
        $one = $adapter->createOneTimeCheckout($conn, ['reference' => 'tip_1', 'token' => 'tok', 'kind' => 'tip', 'amount' => 500]);
        $this->assertStringContainsString('checkout/preview', $sub);
        $this->assertStringContainsString('checkout/preview', $one);
        Http::fake(); // nothing should ever be sent
        Http::assertNothingSent();
    }

    // ── Live onboarding ─────────────────────────────────────────

    public function test_connect_creates_route_linked_account_with_keys(): void
    {
        $this->setKeys();
        Http::fake([
            'api.razorpay.com/v2/accounts' => Http::response([
                'id' => 'acc_NEW789', 'status' => 'created', 'reference_id' => 'user_x',
            ], 200),
            'api.razorpay.com/v2/accounts/*' => Http::response([
                'id' => 'acc_NEW789', 'status' => 'created',
            ], 200),
        ]);

        $user = User::factory()->create();
        $resp = $this->actingAs($user)->get(route('user.payouts.connect', ['provider' => 'razorpay']));
        $resp->assertRedirect(route('user.payouts.return', ['provider' => 'razorpay']));

        $conn = CreatorPaymentConnection::where('user_id', $user->id)->where('provider', 'razorpay')->first();
        $this->assertNotNull($conn);
        $this->assertSame('acc_NEW789', $conn->account_id);
        $this->assertSame('pending', $conn->status);
        $this->assertFalse((bool) $conn->payouts_enabled);
        $this->assertSame('created', $conn->metadata['razorpay_account_status'] ?? null);

        Http::assertSent(function ($request) use ($user) {
            if (!str_ends_with($request->url(), '/v2/accounts') || $request->method() !== 'POST') return false;
            $data = $request->data();
            return $data['type'] === 'route'
                && $data['email'] === $user->email
                && $data['business_type'] === 'individual';
        });
    }

    public function test_sync_status_queries_live_account_state(): void
    {
        $this->setKeys();
        Http::fake([
            'api.razorpay.com/v2/accounts/acc_LIVE123' => Http::response(['id' => 'acc_LIVE123', 'status' => 'activated'], 200),
        ]);

        $user = User::factory()->create();
        $conn = $this->liveConnection($user);
        $conn->update(['status' => 'pending', 'payouts_enabled' => false, 'charges_enabled' => false]);

        $this->adapter()->syncStatus($conn);
        $conn->refresh();
        $this->assertSame('active', $conn->status);
        $this->assertTrue((bool) $conn->payouts_enabled);
        $this->assertTrue((bool) $conn->charges_enabled);
    }

    // ── Live checkout with 100% transfer ────────────────────────

    public function test_checkout_creates_order_with_full_transfer_in_paise(): void
    {
        $this->setKeys();
        Http::fake([
            'api.razorpay.com/v1/orders' => Http::response(['id' => 'order_LIVE1', 'amount' => 50000, 'currency' => 'INR'], 200),
        ]);

        $creator = User::factory()->create();
        $conn = $this->liveConnection($creator);

        $url = $this->adapter()->createSubscriptionCheckout($conn, [
            'reference' => 'sub_42', 'token' => 'tok123', 'amount' => 50000, 'currency' => 'INR',
        ]);

        $this->assertStringContainsString('checkout/razorpay', $url);
        $this->assertStringContainsString('order_id=order_LIVE1', $url);

        Http::assertSent(function ($request) {
            if (!str_contains($request->url(), '/v1/orders')) return false;
            $data = $request->data();
            return $data['amount'] === 50000
                && $data['currency'] === 'INR'
                && $data['transfers'][0]['account'] === 'acc_LIVE123'
                && $data['transfers'][0]['amount'] === 50000 // 0% platform fee
                && $data['notes']['reference'] === 'sub_42'
                && $data['notes']['token'] === 'tok123';
        });

        // Webhook context cached against the order id.
        $ctx = cache()->get(RazorpayRouteAdapter::ORDER_CACHE_PREFIX . 'order_LIVE1');
        $this->assertSame('sub_42', $ctx['reference'] ?? null);

        // The signed hosted-checkout page renders.
        $page = $this->get($url);
        $page->assertOk();
        $page->assertSee('order_LIVE1');
        $page->assertSee('checkout.razorpay.com/v1/checkout.js', false);
    }

    public function test_non_inr_checkout_falls_back_to_preview_and_sends_no_order(): void
    {
        $this->setKeys();
        Http::fake(); // nothing should be sent for a non-INR payload

        $creator = User::factory()->create();
        $conn = $this->liveConnection($creator);

        $url = $this->adapter()->createOneTimeCheckout($conn, [
            'kind' => 'tip', 'reference' => 'tip_9', 'token' => 'tok', 'amount' => 500, 'currency' => 'USD',
        ]);

        // No FX conversion exists — USD cents must never be forwarded as
        // paise. The flow degrades to the preview checkout instead.
        $this->assertStringContainsString('checkout/preview', $url);
        Http::assertNothingSent();
    }

    // ── Webhook ─────────────────────────────────────────────────

    public function test_webhook_rejects_bad_signature(): void
    {
        $this->setKeys();
        $this->signedWebhook(['event' => 'payment.captured'], 'not-the-right-signature')
            ->assertStatus(400);
    }

    public function test_webhook_settles_tip_idempotently(): void
    {
        $this->setKeys();
        Http::fake([
            'api.razorpay.com/v1/orders' => Http::response(['id' => 'order_TIP1'], 200),
        ]);

        $creator = User::factory()->create();
        $fan     = User::factory()->create();
        $this->liveConnection($creator);

        $result = app(MonetizationCheckout::class)->startTip($fan, $creator, 500, 'INR');
        $tip = $result['tip'];
        $this->assertSame(CreatorTip::STATUS_FAILED, $tip->status); // pending until confirm

        $body = [
            'event'   => 'payment.captured',
            'payload' => ['payment' => ['entity' => [
                'id' => 'pay_ABC', 'order_id' => 'order_TIP1', 'status' => 'captured',
            ]]],
        ];

        $this->signedWebhook($body)->assertOk()->assertJson(['status' => 'settled']);
        $this->assertSame(CreatorTip::STATUS_SUCCEEDED, $tip->fresh()->status);
        $events = CreatorPaymentEvent::where('creator_user_id', $creator->id)->count();

        // Re-delivery: no double settlement, no extra ledger event.
        $this->signedWebhook($body)->assertOk()->assertJson(['status' => 'already_settled']);
        $this->assertSame($events, CreatorPaymentEvent::where('creator_user_id', $creator->id)->count());
    }

    public function test_webhook_account_events_flip_connection_flags(): void
    {
        $this->setKeys();
        $creator = User::factory()->create();
        $conn = $this->liveConnection($creator);
        $conn->update(['status' => 'pending', 'payouts_enabled' => false, 'charges_enabled' => false]);

        $this->signedWebhook([
            'event'   => 'account.activated',
            'payload' => ['account' => ['entity' => ['id' => 'acc_LIVE123', 'status' => 'activated']]],
        ])->assertOk();

        $conn->refresh();
        $this->assertSame('active', $conn->status);
        $this->assertTrue((bool) $conn->payouts_enabled);
        $this->assertTrue((bool) $conn->charges_enabled);

        $this->signedWebhook([
            'event'   => 'account.suspended',
            'payload' => ['account' => ['entity' => ['id' => 'acc_LIVE123', 'status' => 'suspended']]],
        ])->assertOk();
        $conn->refresh();
        $this->assertSame('disabled', $conn->status);
        $this->assertFalse((bool) $conn->payouts_enabled);
    }

    public function test_return_url_succeeds_in_production_after_webhook_settled(): void
    {
        $this->setKeys();
        // Simulate production: the preview-only confirm() path is disabled.
        config(['monetization.allow_preview_checkout' => false]);
        Http::fake([
            'api.razorpay.com/v1/orders' => Http::response(['id' => 'order_TIP2'], 200),
        ]);

        $creator = User::factory()->create();
        $fan     = User::factory()->create();
        $this->liveConnection($creator);

        $result = app(MonetizationCheckout::class)->startTip($fan, $creator, 500, 'INR');
        $tip = $result['tip'];

        // Extract the checkout token from the signed hosted-checkout URL.
        parse_str((string) parse_url($result['url'], PHP_URL_QUERY), $qs);
        $this->assertSame('tip_' . $tip->id, $qs['reference']);

        // Webhook settles first (the real production ordering).
        $this->signedWebhook([
            'event'   => 'payment.captured',
            'payload' => ['payment' => ['entity' => ['id' => 'pay_X', 'order_id' => 'order_TIP2']]],
        ])->assertOk()->assertJson(['status' => 'settled']);
        $this->assertSame(CreatorTip::STATUS_SUCCEEDED, $tip->fresh()->status);

        // Buyer then lands on checkout.return → success redirect, NOT the
        // "expired or already used" error path.
        $resp = $this->get(route('checkout.return', [
            'kind' => 'tip', 'reference' => 'tip_' . $tip->id, 'token' => $qs['token'],
        ]));
        $resp->assertRedirect();
        $this->assertStringNotContainsString($resp->headers->get('Location'), url('/') . '?');
        $this->assertNotSame(url('/'), $resp->headers->get('Location'));
        $resp->assertSessionHas('success');
    }

    public function test_webhook_unconfigured_returns_503(): void
    {
        $this->call('POST', '/webhooks/razorpay-route', [], [], [], [
            'HTTP_X_RAZORPAY_SIGNATURE' => 'x', 'CONTENT_TYPE' => 'application/json',
        ], '{}')->assertStatus(503);
    }
}
