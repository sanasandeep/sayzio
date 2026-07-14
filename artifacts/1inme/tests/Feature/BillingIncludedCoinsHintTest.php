<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Subscription;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The billing page's active-subscription card must surface the forward-looking
 * coin grant ("Included coins: X per month/year") pulled from the plan's
 * features['included_coins_monthly'/'included_coins_yearly'], matching the
 * subscription's billing cycle — and stay silent when the plan grants none.
 */
class BillingIncludedCoinsHintTest extends TestCase
{
    use RefreshDatabase;

    private function makePlan(array $features): Plan
    {
        return Plan::create([
            'name'          => 'Coins Plan',
            'slug'          => 'coins-' . Str::lower(Str::random(6)),
            'description'   => 'x',
            'monthly_price' => 100,
            'annual_price'  => 1000,
            'trial_days'    => 0,
            'grace_days'    => 7,
            'status'        => 'active',
            'is_default'    => false,
            'is_archived'   => false,
            'sort_order'    => 1,
            'features'      => $features,
        ]);
    }

    private function makeSub(User $user, Plan $plan, string $cycle): Subscription
    {
        return Subscription::create([
            'user_id'              => $user->id,
            'plan_id'              => $plan->id,
            'status'               => 'active',
            'billing_cycle'        => $cycle,
            'currency'             => 'INR',
            'gateway'              => 'offline',
            'cancel_at_period_end' => false,
            'current_period_start' => now()->subDays(10),
            'current_period_end'   => now()->addDays(20),
        ]);
    }

    public function test_monthly_subscription_shows_the_monthly_coin_grant(): void
    {
        $user = User::factory()->create(['country' => 'IN']);
        $plan = $this->makePlan(['included_coins_monthly' => 500, 'included_coins_yearly' => 6000]);
        $this->makeSub($user, $plan, 'monthly');

        $this->actingAs($user)
            ->get(route('user.billing.show'))
            ->assertOk()
            ->assertSee('Included coins:', false)
            ->assertSee('500', false)
            ->assertSee('per month', false)
            ->assertDontSee('per year', false);
    }

    public function test_yearly_subscription_shows_the_yearly_coin_grant(): void
    {
        $user = User::factory()->create(['country' => 'IN']);
        $plan = $this->makePlan(['included_coins_monthly' => 500, 'included_coins_yearly' => 6000]);
        $this->makeSub($user, $plan, 'yearly');

        $this->actingAs($user)
            ->get(route('user.billing.show'))
            ->assertOk()
            ->assertSee('Included coins:', false)
            ->assertSee('6,000', false)
            ->assertSee('per year', false);
    }

    public function test_plan_without_coin_grant_shows_no_hint(): void
    {
        $user = User::factory()->create(['country' => 'IN']);
        $plan = $this->makePlan([]);
        $this->makeSub($user, $plan, 'monthly');

        $this->actingAs($user)
            ->get(route('user.billing.show'))
            ->assertOk()
            ->assertDontSee('Included coins:', false);
    }
}
