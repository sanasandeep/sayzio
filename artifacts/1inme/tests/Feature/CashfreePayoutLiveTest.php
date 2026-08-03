<?php

namespace Tests\Feature;

use App\Modules\Common\Support\FeatureStates\FeatureCatalog;
use App\Modules\User\Models\CreatorPaymentConnection;
use App\Modules\User\Models\CreatorPaymentEvent;
use App\Modules\User\Models\CreatorTip;
use App\Modules\User\Models\User;
use App\Services\CreatorPayouts\Adapters\CashfreeAdapter;
use App\Services\CreatorPayouts\PayoutProviderRegistry;
use App\Services\Monetization\MonetizationCheckout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Task #6643 — Cashfree as a live payout provider (Easy Split): registry
 * surfacing, preview fallback without keys, live vendor onboarding, order
 * checkout with a 100% split, webhook signature verification and
 * idempotent settlement. All Cashfree HTTP is faked.
 */
class CashfreePayoutLiveTest extends TestCase
{
    use RefreshDatabase;

    private const APP_ID = 'cf_test_fakeappid';
    private const SECRET = 'cf_test_fakesecret';

    protected function tearDown(): void
    {
        unset(
            $_ENV['CASHFREE_APP_ID'], $_SERVER['CASHFREE_APP_ID'],
            $_ENV['CASHFREE_SECRET_KEY'], $_SERVER['CASHFREE_SECRET_KEY'],
        );
        parent::tearDown();
    }

    private function setKeys(): void
    {
        $_ENV['CASHFREE_APP_ID']     = $_SERVER['CASHFREE_APP_ID']     = self::APP_ID;
        $_ENV['CASHFREE_SECRET_KEY'] = $_SERVER['CASHFREE_SECRET_KEY'] = self::SECRET;
    }

    private function adapter(): CashfreeAdapter
    {
        /** @var CashfreeAdapter */
        return PayoutProviderRegistry::adapter('cashfree');
    }

    private function liveConnection(User $creator): CreatorPaymentConnection
    {
        return CreatorPaymentConnection::create([
            'user_id'         => $creator->id,
            'provider'        => 'cashfree',
            'account_id'      => 'cfv_uLIVE123',
            'status'          => 'active',
            'payouts_enabled' => true,
            'charges_enabled' => true,
            'is_default'      => true,
        ]);
    }

    private function signedWebhook(array $body, ?string $signature = null, ?string $timestamp = null)
    {
        $raw = json_encode($body);
        $ts  = $timestamp ?? (string) (time() * 1000);
        $sig = $signature ?? base64_encode(hash_hmac('sha256', $ts . $raw, self::SECRET, true));
        return $this->call('POST', '/webhooks/cashfree-payouts', [], [], [], [
            'HTTP_X_WEBHOOK_SIGNATURE' => $sig,
            'HTTP_X_WEBHOOK_TIMESTAMP' => $ts,
            'CONTENT_TYPE'             => 'application/json',
        ], $raw);
    }

    // ── Registry ────────────────────────────────────────────────

    public function test_registry_lists_cashfree_with_required_fields(): void
    {
        $p = PayoutProviderRegistry::get('cashfree');
        $this->assertNotNull($p);
        foreach (['slug', 'name', 'icon', 'tint', 'short', 'countries', 'payout_speed', 'fees', 'env_keys', 'docs_url'] as $key) {
            $this->assertArrayHasKey($key, $p, "cashfree missing $key");
            $this->assertNotEmpty($p[$key], "cashfree has empty $key");
        }
        $this->assertSame('cashfree', $p['slug']);
        $this->assertSame('India only', $p['countries']);
        $this->assertSame(['CASHFREE_APP_ID', 'CASHFREE_SECRET_KEY'], $p['env_keys']);
        $this->assertFalse((bool) $p['adult_friendly']);
        // Not adult-only → visible to SFW creators too.
        $this->assertArrayHasKey('cashfree', PayoutProviderRegistry::all(false));
        $this->assertSame('cashfree', PayoutProviderRegistry::adapter('cashfree')->slug());
        // The adult-friendly lineup is unchanged.
        $this->assertSame(['ccbill', 'segpay'], PayoutProviderRegistry::adultFriendlySlugs());
    }

    public function test_payouts_page_shows_cashfree(): void
    {
        $this->setKeys(); // also satisfies the coming-soon gate
        $user = User::factory()->create();
        $resp = $this->actingAs($user)->get(route('user.payouts.show'));
        $resp->assertOk();
        $resp->assertSee('Cashfree');
    }

    public function test_cashfree_keys_make_payouts_feature_configured(): void
    {
        $this->assertFalse(FeatureCatalog::paymentProviderConfigured());
        $this->setKeys();
        $this->assertTrue(FeatureCatalog::paymentProviderConfigured());
    }

    // ── Preview fallback without keys ───────────────────────────

    public function test_preview_fallback_without_keys(): void
    {
        $user = User::factory()->create();
        $adapter = $this->adapter();

        $url = $adapter->startOnboarding($user, 'https://example.test/back');
        $this->assertStringContainsString('payouts/preview', $url);

        $conn = CreatorPaymentConnection::create([
            'user_id' => $user->id, 'provider' => 'cashfree', 'account_id' => 'preview_x',
        ]);
        $sub = $adapter->createSubscriptionCheckout($conn, ['reference' => 'sub_1', 'token' => 'tok', 'amount' => 500]);
        $one = $adapter->createOneTimeCheckout($conn, ['reference' => 'tip_1', 'token' => 'tok', 'kind' => 'tip', 'amount' => 500]);
        $this->assertStringContainsString('checkout/preview', $sub);
        $this->assertStringContainsString('checkout/preview', $one);
        Http::fake(); // nothing should ever be sent
        Http::assertNothingSent();
    }

    // ── Live onboarding ─────────────────────────────────────────

    public function test_connect_creates_easy_split_vendor_with_keys(): void
    {
        $this->setKeys();
        Http::fake([
            'api.cashfree.com/pg/easy-split/vendors' => Http::response([
                'vendor_id' => 'cfv_uNEW', 'status' => 'IN_BENE_CREATION',
            ], 200),
            'api.cashfree.com/pg/easy-split/vendors/*' => Http::response([
                'vendor_id' => 'cfv_uNEW', 'status' => 'IN_BENE_CREATION',
            ], 200),
        ]);

        $user = User::factory()->create();
        $resp = $this->actingAs($user)->get(route('user.payouts.connect', ['provider' => 'cashfree']));
        $resp->assertRedirect(route('user.payouts.return', ['provider' => 'cashfree']));

        $conn = CreatorPaymentConnection::where('user_id', $user->id)->where('provider', 'cashfree')->first();
        $this->assertNotNull($conn);
        $this->assertSame('cfv_uNEW', $conn->account_id);
        $this->assertSame('pending', $conn->status);
        $this->assertFalse((bool) $conn->payouts_enabled);
        $this->assertSame('IN_BENE_CREATION', $conn->metadata['cashfree_vendor_status'] ?? null);

        Http::assertSent(function ($request) use ($user) {
            if (!str_ends_with($request->url(), '/easy-split/vendors') || $request->method() !== 'POST') return false;
            $data = $request->data();
            return $data['vendor_id'] === 'cfv_u' . $user->id
                && $data['email'] === $user->email
                && $request->hasHeader('x-client-id', self::APP_ID)
                && $request->hasHeader('x-api-version', CashfreeAdapter::API_VERSION);
        });
    }

    public function test_sync_status_queries_live_vendor_state(): void
    {
        $this->setKeys();
        Http::fake([
            'api.cashfree.com/pg/easy-split/vendors/cfv_uLIVE123' => Http::response([
                'vendor_id' => 'cfv_uLIVE123', 'status' => 'ACTIVE',
            ], 200),
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

    // ── Live checkout with 100% split ───────────────────────────

    public function test_checkout_creates_order_with_full_split(): void
    {
        $this->setKeys();
        Http::fake([
            'api.cashfree.com/pg/orders' => Http::response([
                'order_id' => 'mc_sub_42_abc', 'payment_session_id' => 'session_XYZ',
            ], 200),
        ]);

        $creator = User::factory()->create();
        $conn = $this->liveConnection($creator);

        $url = $this->adapter()->createSubscriptionCheckout($conn, [
            'reference' => 'sub_42', 'token' => 'tok123', 'amount' => 50000, 'currency' => 'INR',
        ]);

        $this->assertStringContainsString('checkout/cashfree-payout', $url);
        $this->assertStringContainsString('order_id=mc_sub_42_abc', $url);
        $this->assertStringContainsString('session_id=session_XYZ', $url);

        Http::assertSent(function ($request) {
            if (!str_contains($request->url(), '/pg/orders')) return false;
            $data = $request->data();
            return $data['order_amount'] === 500.0
                && $data['order_currency'] === 'INR'
                && $data['order_splits'][0]['vendor_id'] === 'cfv_uLIVE123'
                && $data['order_splits'][0]['percentage'] === 100 // 0% platform fee
                && $data['order_tags']['reference'] === 'sub_42'
                && $data['order_tags']['token'] === 'tok123'
                && str_contains($data['order_meta']['notify_url'], '/webhooks/cashfree-payouts');
        });

        // Webhook context cached against the order id.
        $ctx = cache()->get(CashfreeAdapter::ORDER_CACHE_PREFIX . 'mc_sub_42_abc');
        $this->assertSame('sub_42', $ctx['reference'] ?? null);

        // The signed hosted-checkout page renders.
        $page = $this->get($url);
        $page->assertOk();
        $page->assertSee('session_XYZ');
        $page->assertSee('sdk.cashfree.com/js/v3/cashfree.js', false);
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

        $this->assertStringContainsString('checkout/preview', $url);
        Http::assertNothingSent();
    }

    // ── Webhook ─────────────────────────────────────────────────

    public function test_webhook_rejects_bad_signature(): void
    {
        $this->setKeys();
        $this->signedWebhook(['type' => 'PAYMENT_SUCCESS_WEBHOOK'], 'not-the-right-signature')
            ->assertStatus(400);
    }

    public function test_webhook_rejects_stale_timestamp(): void
    {
        $this->setKeys();
        $stale = (string) ((time() - 3600) * 1000);
        $this->signedWebhook(['type' => 'PAYMENT_SUCCESS_WEBHOOK'], null, $stale)
            ->assertStatus(400);
    }

    public function test_webhook_unconfigured_returns_503(): void
    {
        $this->call('POST', '/webhooks/cashfree-payouts', [], [], [], [
            'HTTP_X_WEBHOOK_SIGNATURE' => 'x',
            'HTTP_X_WEBHOOK_TIMESTAMP' => (string) (time() * 1000),
            'CONTENT_TYPE'             => 'application/json',
        ], '{}')->assertStatus(503);
    }

    public function test_webhook_settles_tip_idempotently(): void
    {
        $this->setKeys();
        Http::fake([
            'api.cashfree.com/pg/orders' => Http::response([
                'order_id' => 'mc_TIP1', 'payment_session_id' => 'session_T1',
            ], 200),
        ]);

        $creator = User::factory()->create();
        $fan     = User::factory()->create();
        $this->liveConnection($creator);

        $result = app(MonetizationCheckout::class)->startTip($fan, $creator, 500, 'INR');
        $tip = $result['tip'];
        $this->assertSame(CreatorTip::STATUS_FAILED, $tip->status); // pending until settle

        $body = [
            'type' => 'PAYMENT_SUCCESS_WEBHOOK',
            'data' => [
                'order'   => ['order_id' => 'mc_TIP1'],
                'payment' => ['cf_payment_id' => 'cfp_1', 'payment_status' => 'SUCCESS'],
            ],
        ];

        $this->signedWebhook($body)->assertOk()->assertJson(['status' => 'settled']);
        $this->assertSame(CreatorTip::STATUS_SUCCEEDED, $tip->fresh()->status);
        $events = CreatorPaymentEvent::where('creator_user_id', $creator->id)->count();

        // Re-delivery: no double settlement, no extra ledger event.
        $this->signedWebhook($body)->assertOk()->assertJson(['status' => 'already_settled']);
        $this->assertSame($events, CreatorPaymentEvent::where('creator_user_id', $creator->id)->count());
    }

    public function test_webhook_settles_from_order_tags_fallback(): void
    {
        $this->setKeys();
        Http::fake([
            'api.cashfree.com/pg/orders' => Http::response([
                'order_id' => 'mc_TIP2', 'payment_session_id' => 'session_T2',
            ], 200),
        ]);

        $creator = User::factory()->create();
        $fan     = User::factory()->create();
        $this->liveConnection($creator);

        $result = app(MonetizationCheckout::class)->startTip($fan, $creator, 500, 'INR');
        $tip = $result['tip'];
        parse_str((string) parse_url($result['url'], PHP_URL_QUERY), $qs);

        // Simulate a lost cache entry — the webhook must fall back to the
        // order_tags Cashfree echoes on the payload.
        cache()->forget(CashfreeAdapter::ORDER_CACHE_PREFIX . 'mc_TIP2');

        $this->signedWebhook([
            'type' => 'PAYMENT_SUCCESS_WEBHOOK',
            'data' => [
                'order' => [
                    'order_id'   => 'mc_TIP2',
                    'order_tags' => ['kind' => 'tip', 'reference' => 'tip_' . $tip->id, 'token' => $qs['token']],
                ],
                'payment' => ['cf_payment_id' => 'cfp_2'],
            ],
        ])->assertOk()->assertJson(['status' => 'settled']);
        $this->assertSame(CreatorTip::STATUS_SUCCEEDED, $tip->fresh()->status);
    }

    public function test_webhook_vendor_events_flip_connection_flags(): void
    {
        $this->setKeys();
        $creator = User::factory()->create();
        $conn = $this->liveConnection($creator);
        $conn->update(['status' => 'pending', 'payouts_enabled' => false, 'charges_enabled' => false]);

        $this->signedWebhook([
            'type' => 'VENDOR_STATUS_UPDATE',
            'data' => ['vendor' => ['vendor_id' => 'cfv_uLIVE123', 'status' => 'ACTIVE']],
        ])->assertOk();

        $conn->refresh();
        $this->assertSame('active', $conn->status);
        $this->assertTrue((bool) $conn->payouts_enabled);
        $this->assertTrue((bool) $conn->charges_enabled);

        $this->signedWebhook([
            'type' => 'VENDOR_STATUS_UPDATE',
            'data' => ['vendor' => ['vendor_id' => 'cfv_uLIVE123', 'status' => 'BLOCKED']],
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
            'api.cashfree.com/pg/orders' => Http::response([
                'order_id' => 'mc_TIP3', 'payment_session_id' => 'session_T3',
            ], 200),
        ]);

        $creator = User::factory()->create();
        $fan     = User::factory()->create();
        $this->liveConnection($creator);

        $result = app(MonetizationCheckout::class)->startTip($fan, $creator, 500, 'INR');
        $tip = $result['tip'];
        parse_str((string) parse_url($result['url'], PHP_URL_QUERY), $qs);
        $this->assertSame('tip_' . $tip->id, $qs['reference']);

        // Webhook settles first (the real production ordering).
        $this->signedWebhook([
            'type' => 'PAYMENT_SUCCESS_WEBHOOK',
            'data' => ['order' => ['order_id' => 'mc_TIP3'], 'payment' => ['cf_payment_id' => 'cfp_3']],
        ])->assertOk()->assertJson(['status' => 'settled']);
        $this->assertSame(CreatorTip::STATUS_SUCCEEDED, $tip->fresh()->status);

        // Buyer then lands on checkout.return → success redirect, NOT the
        // "expired or already used" error path.
        $resp = $this->get(route('checkout.return', [
            'kind' => 'tip', 'reference' => 'tip_' . $tip->id, 'token' => $qs['token'],
        ]));
        $resp->assertRedirect();
        $this->assertNotSame(url('/'), $resp->headers->get('Location'));
        $resp->assertSessionHas('success');
    }
}
