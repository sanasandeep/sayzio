<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\MarketingStrategy;
use App\Modules\User\Models\User;
use App\Services\MarketingPlanAiSeed;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task #6739 — pre-fill the Marketing Plan Calculator from an AI
 * Marketing Strategist plan (`?from_strategy=`): re-weighted channel
 * allocations, parsed budget, company carry-over, ownership scoping.
 */
class MarketingPlanAiSeedTest extends TestCase
{
    use RefreshDatabase;

    private function plan(): Plan
    {
        $slug = 'p' . Str::random(6);

        return Plan::create([
            'name' => 'Professional ' . $slug, 'slug' => $slug,
            'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'status' => 'active',
            'features' => ['marketing_plan_calculator' => true, 'max_marketing_plans' => -1],
        ]);
    }

    private function user(): User
    {
        return User::create([
            'name'         => 'u' . Str::random(4),
            'email'        => 'u' . Str::random(8) . '@ex.com',
            'password'     => Hash::make('x'),
            'status'       => 'active',
            'plan_id'      => $this->plan()->id,
            'onboarded_at' => now(),
        ]);
    }

    private function wsId(User $user): ?int
    {
        return app(WorkspaceContext::class)->resolve($user)?->id;
    }

    private function strategy(User $user, array $overrides = []): MarketingStrategy
    {
        return MarketingStrategy::create(array_merge([
            'user_id'      => $user->id,
            'workspace_id' => $this->wsId($user),
            'title'        => 'Grow the newsletter',
            'goal'         => 'Grow newsletter subscribers.',
            'status'       => 'ready',
            'parameters'   => ['budget' => '₹50,000/month'],
            'profile_snapshot' => ['business_name' => 'Acme Fitness App'],
            'strategy'     => [
                'summary' => 'Plan.',
                'organic' => [
                    ['channel' => 'SEO & Content', 'title' => 'Blog series'],
                    ['channel' => 'Email', 'title' => 'Weekly newsletter'],
                ],
                'paid' => [
                    ['channel' => 'Instagram Ads', 'title' => 'Reels push', 'budget_hint' => '₹500/day'],
                    ['channel' => 'Google Search', 'title' => 'Brand terms'],
                ],
            ],
        ], $overrides));
    }

    public function test_seed_reweights_channels_and_parses_budget(): void
    {
        $user = $this->user();
        $seed = MarketingPlanAiSeed::fromStrategy($this->strategy($user), $user);

        $payload = $seed['payload'];

        // Budget: ₹50,000/month → ₹600,000/year.
        $this->assertSame(600000.0, (float) $payload['annual_budget']);
        $this->assertSame('Acme Fitness App', $payload['company']);
        $this->assertSame('AI: Grow the newsletter', $seed['name']);

        $byKey = collect($payload['channels'])->keyBy('key');

        // Recommended channels got boosted above their spreadsheet defaults…
        $this->assertGreaterThan(5.5, $byKey['instagram']['alloc']);
        $this->assertGreaterThan(17.0, $byKey['search']['alloc']);
        $this->assertStringStartsWith('AI-recommended', $byKey['instagram']['notes']);
        // …unrecommended ones were reduced.
        $this->assertLessThan(11.0, $byKey['events']['alloc']);

        // Non-fixed allocations still total exactly 100%.
        $total = collect($payload['channels'])->reject(fn ($c) => !empty($c['fixed']))->sum('alloc');
        $this->assertEqualsWithDelta(100.0, $total, 0.001);

        // Matched names surfaced for the editor banner.
        $this->assertContains('Instagram Ads', $seed['matched']);
    }

    public function test_seed_without_recognisable_channels_keeps_defaults(): void
    {
        $user = $this->user();
        $strategy = $this->strategy($user, [
            'strategy'   => ['summary' => 'Plan.', 'organic' => [], 'paid' => []],
            'parameters' => [],
            'profile_snapshot' => null,
        ]);

        $seed = MarketingPlanAiSeed::fromStrategy($strategy, $user);
        $byKey = collect($seed['payload']['channels'])->keyBy('key');

        $this->assertSame(5.5, (float) $byKey['instagram']['alloc']);
        $this->assertSame([], $seed['matched']);
        $this->assertSame(180000, $seed['payload']['annual_budget']);
    }

    public function test_create_page_prefills_from_owned_strategy(): void
    {
        $user = $this->user();
        $strategy = $this->strategy($user);

        $this->actingAs($user, 'web')
            ->get(route('user.marketing-plan.create', ['from_strategy' => $strategy->id]))
            ->assertStatus(200)
            ->assertSee('Pre-filled from your AI strategy')
            ->assertSee('Grow the newsletter')
            ->assertSee('AI: Grow the newsletter');
    }

    public function test_foreign_strategy_404s(): void
    {
        $owner = $this->user();
        $other = $this->user();
        $strategy = $this->strategy($owner);

        $this->actingAs($other, 'web')
            ->get(route('user.marketing-plan.create', ['from_strategy' => $strategy->id]))
            ->assertStatus(404);
    }

    public function test_strategist_show_links_to_calculator_seed(): void
    {
        $user = $this->user();
        $strategy = $this->strategy($user);

        \App\Services\AI\AiEngineSettings::setEnabled(true);

        $this->actingAs($user, 'web')
            ->get(route('user.ai.marketing-strategist.show', $strategy->id))
            ->assertStatus(200)
            ->assertSee('Build plan in calculator')
            ->assertSee(route('user.marketing-plan.create', ['from_strategy' => $strategy->id]), false);
    }
}
