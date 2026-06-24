<?php

namespace Tests\Feature;

use App\Modules\User\Models\SubscriptionPromoCode;
use App\Modules\User\Models\SubscriptionTier;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubscribePromoPreviewTest extends TestCase
{
    use RefreshDatabase;

    private function makeCreator(): User
    {
        return User::create([
            'name'     => 'Creator ' . Str::random(4),
            'email'    => 'c' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'handle'   => 'creator' . Str::lower(Str::random(8)),
        ]);
    }

    private function makeTier(User $creator, int $monthly = 1000): SubscriptionTier
    {
        return SubscriptionTier::create([
            'user_id'             => $creator->id,
            'name'                => 'Pro',
            'slug'                => 'pro-' . Str::random(4),
            'is_free'             => false,
            'is_active'           => true,
            'sort_order'          => 1,
            'price_monthly_cents' => $monthly,
            'price_yearly_cents'  => $monthly * 10,
            'currency'            => 'USD',
        ]);
    }

    private function previewUrl(User $creator): string
    {
        return '/@' . $creator->handle . '/subscribe/preview-promo';
    }

    public function test_percent_code_returns_discounted_price(): void
    {
        $creator = $this->makeCreator();
        $tier    = $this->makeTier($creator, 1000);
        SubscriptionPromoCode::create([
            'user_id' => $creator->id, 'code' => 'SAVE20', 'kind' => SubscriptionPromoCode::KIND_PERCENT,
            'value' => 20, 'is_active' => true,
        ]);

        $response = $this->postJson($this->previewUrl($creator), [
            'tier_id' => $tier->id, 'cycle' => 'monthly', 'promo_code' => 'save20',
        ]);

        $response->assertOk();
        $response->assertJson([
            'ok'             => true,
            'code'           => 'SAVE20',
            'original_cents' => 1000,
            'final_cents'    => 800,
        ]);
    }

    public function test_unknown_code_returns_not_found_reason(): void
    {
        $creator = $this->makeCreator();
        $tier    = $this->makeTier($creator);

        $response = $this->postJson($this->previewUrl($creator), [
            'tier_id' => $tier->id, 'cycle' => 'monthly', 'promo_code' => 'NOPE',
        ]);

        $response->assertOk();
        $response->assertJson(['ok' => false]);
        $this->assertStringContainsString('couldn\'t find', $response->json('reason'));
    }

    public function test_disabled_code_surfaces_unusable_reason(): void
    {
        $creator = $this->makeCreator();
        $tier    = $this->makeTier($creator);
        SubscriptionPromoCode::create([
            'user_id' => $creator->id, 'code' => 'OFF', 'kind' => SubscriptionPromoCode::KIND_PERCENT,
            'value' => 50, 'is_active' => false,
        ]);

        $response = $this->postJson($this->previewUrl($creator), [
            'tier_id' => $tier->id, 'cycle' => 'monthly', 'promo_code' => 'OFF',
        ]);

        $response->assertOk();
        $response->assertJson(['ok' => false]);
        $this->assertStringContainsString('disabled', $response->json('reason'));
    }

    public function test_yearly_cycle_applies_to_yearly_price(): void
    {
        $creator = $this->makeCreator();
        $tier    = $this->makeTier($creator, 1000); // yearly = 10000
        SubscriptionPromoCode::create([
            'user_id' => $creator->id, 'code' => 'TEN', 'kind' => SubscriptionPromoCode::KIND_AMOUNT,
            'value' => 500, 'is_active' => true,
        ]);

        $response = $this->postJson($this->previewUrl($creator), [
            'tier_id' => $tier->id, 'cycle' => 'yearly', 'promo_code' => 'TEN',
        ]);

        $response->assertOk();
        $response->assertJson([
            'ok'             => true,
            'cycle'          => 'yearly',
            'original_cents' => 10000,
            'final_cents'    => 9500,
        ]);
    }

    public function test_free_tier_rejects_promo_preview(): void
    {
        $creator = $this->makeCreator();
        $tier    = SubscriptionTier::create([
            'user_id' => $creator->id, 'name' => 'Free', 'slug' => 'free-' . Str::random(4),
            'is_free' => true, 'is_active' => true, 'sort_order' => 0,
            'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'USD',
        ]);

        $response = $this->postJson($this->previewUrl($creator), [
            'tier_id' => $tier->id, 'cycle' => 'monthly', 'promo_code' => 'ANY',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['ok' => false]);
    }

    public function test_missing_code_is_a_validation_error(): void
    {
        $creator = $this->makeCreator();
        $tier    = $this->makeTier($creator);

        $response = $this->postJson($this->previewUrl($creator), [
            'tier_id' => $tier->id, 'cycle' => 'monthly',
        ]);

        $response->assertStatus(422);
    }
}
