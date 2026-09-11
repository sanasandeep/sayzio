<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\CoinPackage;
use App\Modules\User\Models\User;
use App\Services\Billing\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The wallet Buy page renders for the people who can reach it.
 *
 * Why this exists: `buy.blade.php` calls
 * `CoinPlanBonus::breakdownFor(auth()->user(), $pkg)`, and that method is
 * typed `?User`. On /pricing the same call was a TypeError, so this page
 * looked like the same latent crash waiting to happen.
 *
 * It is not, and the difference is worth recording so nobody "fixes" it
 * twice. /pricing is public: whatever `auth()->user()` returns there depends
 * on which guard happens to be the default, and a test that calls
 * `actingAs($admin, 'admin')` makes the admin guard the default for the rest
 * of the test -- which is how an Admin reached a `?User` parameter. This page
 * sits behind `auth`, which is the web guard, whose provider is
 * `App\Modules\User\Models\User`. A visitor here is a User or is redirected
 * to sign in; there is no third case.
 *
 * So the guard belongs on the public page, and this test holds the reasoning
 * in place: it asserts the page renders for a real user, and that an
 * admin-guard session does not reach it at all.
 */
class WalletBuyPageLoadsTest extends TestCase
{
    use RefreshDatabase;

    /** The whole wallet is behind an admin switch; without it every route 404s. */
    private function enableTheWallet(): void
    {
        AppSetting::put(WalletService::FEATURE_KEY, true);
    }

    private function aPackage(): CoinPackage
    {
        // updateOrCreate, not create: the migrations seed the real coin
        // packages, so `coins-pro` already exists and create() hits the
        // slug unique index.
        return CoinPackage::updateOrCreate(
            ['slug' => 'coins-pro'],
            [
                'name' => 'Pro pack',
                'coin_amount' => 10000,
                'bonus_coins' => 1000,
                'price' => 4900,
                'is_active' => true,
            ]
        );
    }

    public function test_a_signed_in_user_can_load_the_buy_page(): void
    {
        $this->enableTheWallet();
        $this->aPackage();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('user.wallet.buy'))
            ->assertOk()
            ->assertSee('Buy coins');
    }

    /**
     * The plan bonus is computed for the buyer, not for whoever else might
     * be signed in. A package below Pro earns nothing; a Pro package on a
     * free plan earns nothing either.
     */
    public function test_the_page_prices_the_package_without_a_plan_bonus_for_a_free_user(): void
    {
        $this->enableTheWallet();
        $package = $this->aPackage();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('user.wallet.buy'));
        $response->assertOk();

        // 10,000 base + 1,000 built-in bonus, and no plan bonus on free.
        $response->assertSee(number_format(11000));
    }

    /** Nobody reaches this page unauthenticated, which is the real guard. */
    public function test_a_signed_out_visitor_is_sent_to_sign_in(): void
    {
        $this->enableTheWallet();
        $this->aPackage();

        $this->get(route('user.wallet.buy'))->assertRedirect();
    }
}
