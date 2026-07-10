<?php

namespace Tests\Feature;

use App\Modules\User\Models\CreatorPaymentConnection;
use App\Modules\User\Models\CreatorSubscription;
use App\Modules\User\Models\SubscriptionTier;
use App\Modules\User\Models\User;
use App\Services\Monetization\MonetizationCheckout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The preview checkout flow (/checkout/preview + /checkout/return) simulates
 * a successful payment WITHOUT collecting money or verifying a provider charge.
 * It grants paid entitlements on token possession alone, so it MUST be disabled
 * in production. These tests prove that when preview checkout is not allowed,
 * an attacker who obtained a valid checkout token cannot activate a paid
 * subscription (or any other entitlement) by hitting the return/confirm routes.
 */
class MonetizationPreviewCheckoutGuardTest extends TestCase
{
    use RefreshDatabase;

    private function makeCreatorAndFan(): array
    {
        $creator = User::factory()->create();
        $fan     = User::factory()->create();
        CreatorPaymentConnection::create([
            'user_id'         => $creator->id,
            'provider'        => 'stripe',
            'account_id'      => 'acct_' . Str::random(8),
            'status'          => 'active',
            'payouts_enabled' => true,
            'charges_enabled' => true,
            'is_default'      => true,
        ]);
        $tier    = SubscriptionTier::create([
            'user_id'             => $creator->id,
            'name'                => 'Gold',
            'slug'                => 'gold-' . Str::random(4),
            'is_active'           => true,
            'price_monthly_cents' => 500,
            'currency'            => 'USD',
            'sort_order'          => 0,
        ]);

        return [$creator, $fan, $tier];
    }

    /**
     * Start a real (preview) checkout so we obtain a genuine token/reference —
     * exactly what an attacker would have after initiating a legit checkout.
     * Returns [reference, token, subscription].
     */
    private function startCheckout(User $fan, User $creator, SubscriptionTier $tier): array
    {
        // Starting the checkout must be allowed to mint a token.
        config(['monetization.allow_preview_checkout' => true]);
        $result = app(MonetizationCheckout::class)->startSubscription(
            $fan, $creator, $tier, CreatorSubscription::CYCLE_MONTHLY,
        );
        $sub = $result['subscription'];

        // The preview checkout URL carries the reference + token the attacker
        // would replay against the return route.
        $query = [];
        parse_str((string) parse_url($result['url'], PHP_URL_QUERY), $query);

        return ['sub_' . $sub->id, (string) ($query['token'] ?? ''), $sub];
    }

    public function test_return_route_cannot_activate_subscription_when_preview_disabled(): void
    {
        [$creator, $fan, $tier] = $this->makeCreatorAndFan();
        [$reference, $token, $sub] = $this->startCheckout($fan, $creator, $tier);

        $this->assertNotEmpty($token, 'checkout should mint a return token');
        $this->assertSame(CreatorSubscription::STATUS_PAST_DUE, $sub->fresh()->status);

        // Attacker replays the return route directly in a production-like env.
        config(['monetization.allow_preview_checkout' => false]);

        $this->actingAs($fan)
            ->get(route('checkout.return', [
                'kind' => 'subscription', 'reference' => $reference, 'token' => $token,
            ]))
            ->assertRedirect('/');

        $this->assertSame(
            CreatorSubscription::STATUS_PAST_DUE,
            $sub->fresh()->status,
            'preview return must NOT activate the subscription when preview checkout is disabled',
        );
    }

    public function test_confirm_service_returns_null_when_preview_disabled(): void
    {
        [$creator, $fan, $tier] = $this->makeCreatorAndFan();
        [$reference, $token, $sub] = $this->startCheckout($fan, $creator, $tier);

        config(['monetization.allow_preview_checkout' => false]);

        $result = app(MonetizationCheckout::class)->confirm('subscription', $reference, $token);
        $this->assertNull($result, 'confirm() must refuse to grant entitlements when preview is disabled');
        $this->assertSame(CreatorSubscription::STATUS_PAST_DUE, $sub->fresh()->status);
    }

    public function test_preview_page_is_hidden_when_preview_disabled(): void
    {
        config(['monetization.allow_preview_checkout' => false]);

        // Even a validly-signed preview URL 404s in a production-like env.
        $signed = URL::signedRoute('checkout.preview', [
            'provider' => 'stripe', 'kind' => 'subscription',
            'reference' => 'sub_1', 'token' => Str::random(32),
        ]);
        $path = parse_url($signed, PHP_URL_PATH) . '?' . parse_url($signed, PHP_URL_QUERY);
        $this->get($path)->assertNotFound();
    }

    public function test_return_route_activates_subscription_when_preview_allowed(): void
    {
        // Sanity check: the demo flow still works when preview is enabled
        // (the default outside production), so we only closed the prod hole.
        [$creator, $fan, $tier] = $this->makeCreatorAndFan();
        [$reference, $token, $sub] = $this->startCheckout($fan, $creator, $tier);

        config(['monetization.allow_preview_checkout' => true]);

        $this->actingAs($fan)
            ->get(route('checkout.return', [
                'kind' => 'subscription', 'reference' => $reference, 'token' => $token,
            ]))
            ->assertRedirect();

        $this->assertSame(
            CreatorSubscription::STATUS_ACTIVE,
            $sub->fresh()->status,
            'preview return should still activate when preview checkout is allowed',
        );
    }
}
