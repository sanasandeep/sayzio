<?php

namespace Tests\Feature;

use App\Modules\User\Models\CreatorPaymentConnection;
use App\Modules\User\Models\User;
use App\Services\CreatorPayouts\PayoutProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Task #6634 — PhonePe, CCAvenue, and Paytm as creator payout
 * providers. Covers registry listing, the preview-mode connect flow
 * (no live credentials in test envs), set-default, and that the
 * monetization checkout adapter resolution works for the new slugs.
 */
class IndiaPayoutProvidersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The payouts area auto-resolves to "coming soon" until at least one
        // provider's platform credentials exist. Stub Stripe's keys so the
        // EnsureFeatureAvailable guard lets us through; the three new
        // providers themselves stay UNCONFIGURED (preview mode).
        $_ENV['STRIPE_CONNECT_CLIENT_ID'] = $_SERVER['STRIPE_CONNECT_CLIENT_ID'] = 'ca_test_guard';
        $_ENV['STRIPE_SECRET_KEY']        = $_SERVER['STRIPE_SECRET_KEY']        = 'sk_test_guard';
    }

    protected function tearDown(): void
    {
        unset(
            $_ENV['STRIPE_CONNECT_CLIENT_ID'], $_SERVER['STRIPE_CONNECT_CLIENT_ID'],
            $_ENV['STRIPE_SECRET_KEY'], $_SERVER['STRIPE_SECRET_KEY'],
        );
        parent::tearDown();
    }

    public static function indianProviders(): array
    {
        return [
            'phonepe'  => ['phonepe', 'PhonePe'],
            'ccavenue' => ['ccavenue', 'CCAvenue'],
            'paytm'    => ['paytm', 'Paytm'],
        ];
    }

    // ── Registry ────────────────────────────────────────────────

    public function test_registry_lists_new_providers_with_required_fields(): void
    {
        foreach (['phonepe', 'ccavenue', 'paytm'] as $slug) {
            $p = PayoutProviderRegistry::get($slug);
            $this->assertNotNull($p, "$slug missing from registry");
            foreach (['slug', 'name', 'icon', 'tint', 'short', 'countries', 'payout_speed', 'fees', 'env_keys', 'docs_url'] as $key) {
                $this->assertArrayHasKey($key, $p, "$slug missing $key");
                $this->assertNotEmpty($p[$key], "$slug has empty $key");
            }
            $this->assertSame($slug, $p['slug']);
            $this->assertFalse((bool) $p['adult_friendly'], "$slug must not be adult-friendly");
            // Not adult-only → visible to SFW creators too.
            $this->assertArrayHasKey($slug, PayoutProviderRegistry::all(false));
        }
    }

    #[DataProvider('indianProviders')]
    public function test_adapter_resolves_for_new_slugs(string $slug, string $name = ''): void
    {
        $adapter = PayoutProviderRegistry::adapter($slug);
        $this->assertSame($slug, $adapter->slug());
    }

    public function test_existing_providers_unaffected(): void
    {
        foreach (['stripe', 'paypal', 'razorpay', 'ccbill', 'segpay'] as $slug) {
            $this->assertNotNull(PayoutProviderRegistry::get($slug));
            $this->assertSame($slug, PayoutProviderRegistry::adapter($slug)->slug());
        }
        $this->assertSame(['ccbill', 'segpay'], PayoutProviderRegistry::adultFriendlySlugs());
    }

    // ── Payouts page ────────────────────────────────────────────

    public function test_payouts_page_shows_new_providers(): void
    {
        $user = User::factory()->create();
        $resp = $this->actingAs($user)->get(route('user.payouts.show'));
        $resp->assertOk();
        $resp->assertSee('PhonePe');
        $resp->assertSee('CCAvenue');
        $resp->assertSee('Paytm');
    }

    // ── Preview-mode connect flow ───────────────────────────────

    #[DataProvider('indianProviders')]
    public function test_connect_redirects_to_preview_when_unconfigured(string $slug, string $name = ''): void
    {
        $user = User::factory()->create();
        $resp = $this->actingAs($user)->get(route('user.payouts.connect', ['provider' => $slug]));

        $resp->assertRedirect();
        $this->assertStringContainsString('payouts/preview', $resp->headers->get('Location'));
        $this->assertDatabaseHas('creator_payment_connections', [
            'user_id'  => $user->id,
            'provider' => $slug,
        ]);
    }

    #[DataProvider('indianProviders')]
    public function test_preview_complete_activates_and_can_be_default(string $slug, string $name): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('user.payouts.preview-complete', ['provider' => $slug]))
            ->assertRedirect(route('user.payouts.show'));

        $conn = CreatorPaymentConnection::where('user_id', $user->id)->where('provider', $slug)->first();
        $this->assertNotNull($conn);
        $this->assertSame('active', $conn->status);
        $this->assertTrue((bool) $conn->payouts_enabled);
        $this->assertTrue((bool) $conn->charges_enabled);
        // First connection auto-becomes the default.
        $this->assertTrue((bool) $conn->is_default);

        // Explicit set-default keeps working.
        $this->actingAs($user)
            ->post(route('user.payouts.set-default', ['connection' => $conn->id]))
            ->assertRedirect();
        $this->assertTrue((bool) $conn->fresh()->is_default);
    }

    // ── Checkout resolution ─────────────────────────────────────

    #[DataProvider('indianProviders')]
    public function test_checkout_urls_resolve_in_preview_mode(string $slug, string $name = ''): void
    {
        $user = User::factory()->create();
        $conn = CreatorPaymentConnection::create([
            'user_id'         => $user->id,
            'provider'        => $slug,
            'account_id'      => 'preview_test',
            'status'          => 'active',
            'payouts_enabled' => true,
            'charges_enabled' => true,
            'is_default'      => true,
        ]);

        $adapter = PayoutProviderRegistry::adapter($slug);
        $sub = $adapter->createSubscriptionCheckout($conn, ['reference' => 1, 'token' => 'tok']);
        $one = $adapter->createOneTimeCheckout($conn, ['reference' => 1, 'token' => 'tok', 'kind' => 'tip']);

        $this->assertStringContainsString('checkout/preview', $sub);
        $this->assertStringContainsString("provider={$slug}", $sub);
        $this->assertStringContainsString('checkout/preview', $one);
        $this->assertStringContainsString("provider={$slug}", $one);
    }
}
