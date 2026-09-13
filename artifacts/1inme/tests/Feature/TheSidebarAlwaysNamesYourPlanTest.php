<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Which plan you are on is standing information, not a prompt.
 *
 * The card used to be wrapped in canUpgradePlan(), so an account on the top
 * tier lost the whole thing -- the one place in the product that answered
 * "what am I paying for" disappeared precisely for the people paying most,
 * and the rail ended below the nav with nothing in it.
 *
 * What stays conditional is the ASK. Showing "Compare plans" to someone with
 * nothing to move up to is a dark pattern, so that account gets its plan name
 * and a closing line instead of a button.
 */
class TheSidebarAlwaysNamesYourPlanTest extends TestCase
{
    use RefreshDatabase;

    private function plan(string $name, int $sort): Plan
    {
        return Plan::create([
            'name'          => $name,
            'slug'          => \Illuminate\Support\Str::slug($name).'-'.$sort,
            'sort_order'    => $sort,
            'status'        => 'active',
            'is_archived'   => false,
            'is_internal'   => false,
            'is_default'    => false,
            'monthly_price' => $sort * 100,
            'annual_price'  => $sort * 1000,
        ]);
    }

    private function railFor(User $user): string
    {
        Cache::forget('plans:max-sort-order');
        $ws = $user->ownedWorkspaces()->first();

        // A fresh account is bounced to onboarding; the rail is in the shared
        // layout either way, so follow the redirect rather than fight it.
        return $this->actingAs($user)
            ->withSession($ws ? [WorkspaceContext::SESSION_KEY => $ws->id] : [])
            ->followingRedirects()
            ->get('/user/dashboard')
            ->assertOk()
            ->getContent();
    }

    public function test_a_lower_tier_is_offered_the_higher_ones(): void
    {
        $mid = $this->plan('Starter', 900);
        $this->plan('Pro', 901);

        $user = User::factory()->create(['plan_id' => $mid->id]);

        $html = $this->railFor($user->fresh());

        $this->assertStringContainsString('Starter Plan', $html, 'the rail does not name the current plan');
        $this->assertStringContainsString('Compare plans', $html, 'a mid-tier account is not offered the higher plans');
    }

    public function test_the_top_tier_still_sees_its_plan_but_is_not_sold_to(): void
    {
        $this->plan('Starter', 900);
        $top = $this->plan('Pro', 901);

        $user = User::factory()->create(['plan_id' => $top->id]);

        $html = $this->railFor($user->fresh());

        $this->assertStringContainsString(
            'Pro Plan',
            $html,
            'the top tier lost the plan card entirely, which is what this change fixed'
        );
        $this->assertStringNotContainsString(
            'Compare plans',
            $html,
            'the top tier is being offered an upgrade that does not exist'
        );
    }
}
