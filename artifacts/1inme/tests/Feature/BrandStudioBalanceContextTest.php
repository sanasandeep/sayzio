<?php

namespace Tests\Feature;

use App\Modules\User\Models\BrandStudioKit;
use App\Modules\User\Models\User;
use App\Services\AI\AiEngineSettings;
use App\Services\Billing\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Credit context at the kit review (second decision) point — Task #5568.
 *
 * Both the web proposal page (/user/brand-studio/{kit}) and the mobile API
 * detail endpoint (/api/v1/brand-studio/{kit}) must surface the credits
 * already spent on the plan plus the user's remaining AI credit balance,
 * with a low-balance top-up hint when the wallet is at/below the shared
 * AiUsageCharger threshold.
 */
class BrandStudioBalanceContextTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'name'     => 'Studio Reviewer',
            'email'    => 'studio-' . Str::random(8) . '@example.com',
            'password' => bcrypt('secret-password'),
        ]);
    }

    private function makeKit(User $user, int $creditsSpent = 12): BrandStudioKit
    {
        return BrandStudioKit::create([
            'user_id'       => $user->id,
            'name'          => 'Launch kit',
            'mode'          => BrandStudioKit::MODE_KIT,
            'status'        => BrandStudioKit::STATUS_PROPOSAL,
            'request'       => 'Launch assets for my brand',
            'proposal'      => ['assets' => [['kind' => 'short_link', 'title' => 'Promo', 'url' => 'https://example.com']]],
            'credits_spent' => $creditsSpent,
        ]);
    }

    public function test_web_review_page_shows_credits_spent_and_balance_with_low_hint(): void
    {
        AiEngineSettings::setEnabled(true);
        $user = $this->makeUser();
        $kit  = $this->makeKit($user);
        // Balance stays 0 (fresh wallet) → below threshold → low hint shown.

        $res = $this->actingAs($user)->get(route('user.brand-studio.show', $kit));

        $res->assertOk();
        $res->assertSee('Coins spent on this plan');
        $res->assertSee('Your coin balance');
        $res->assertSee('Top up coins');
    }

    public function test_web_review_page_hides_low_hint_with_comfortable_balance(): void
    {
        AiEngineSettings::setEnabled(true);
        $user = $this->makeUser();
        $kit  = $this->makeKit($user);
        app(WalletService::class)->credit($user, 500, ['reason' => 'test top-up']);

        $res = $this->actingAs($user)->get(route('user.brand-studio.show', $kit));

        $res->assertOk();
        $res->assertSee('Your coin balance');
        $res->assertDontSee('Top up coins');
    }

    public function test_api_detail_returns_balance_and_threshold(): void
    {
        AiEngineSettings::setEnabled(true);
        $user = $this->makeUser();
        $kit  = $this->makeKit($user, 7);
        app(WalletService::class)->credit($user, 40, ['reason' => 'test top-up']);

        $token = $user->createToken('t')->plainTextToken;

        $res = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/brand-studio/' . $kit->id);

        $res->assertOk();
        $res->assertJsonPath('data.kit.credits_spent', 7);
        $res->assertJsonPath('data.balance', 40);
        $this->assertIsInt($res->json('data.low_balance_threshold'));
        $this->assertGreaterThan(0, $res->json('data.low_balance_threshold'));
    }
}
