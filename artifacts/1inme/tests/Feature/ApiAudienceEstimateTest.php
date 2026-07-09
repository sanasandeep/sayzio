<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Services\AI\AudienceTypeEstimationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Mobile-parity endpoint POST /api/v1/links/{id}/audience-estimate —
 * same paid-plan gate + settings caching as the web
 * POST /user/links/{link}/audience/estimate route.
 */
class ApiAudienceEstimateTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUser(array $planFeatures = []): User
    {
        $plan = Plan::create([
            'name'     => 'Test Plan ' . Str::random(4),
            'slug'     => 'plan-' . Str::lower(Str::random(8)),
            'status'   => true,
            'features' => $planFeatures,
        ]);

        return User::factory()->create(['plan_id' => $plan->id]);
    }

    protected function makeLink(User $user): Link
    {
        return Link::create([
            'user_id'  => $user->id,
            'type'     => 'biolink',
            'alias'    => 'aud-' . Str::lower(Str::random(10)),
            'url'      => null,
            'settings' => [],
        ]);
    }

    public function test_free_plan_is_rejected_with_402(): void
    {
        $user = $this->makeUser(['audience_type_estimation' => false]);
        $link = $this->makeLink($user);
        $token = $user->createToken('test')->plainTextToken;

        $res = $this->withToken($token)
            ->postJson("/api/v1/links/{$link->id}/audience-estimate");

        $res->assertStatus(402);
        $this->assertNotEmpty($res->json('error.message'));
    }

    public function test_paid_plan_runs_estimation_and_caches_result(): void
    {
        $user = $this->makeUser(['audience_type_estimation' => true]);
        $link = $this->makeLink($user);
        $token = $user->createToken('test')->plainTextToken;

        $estimated = [
            ['type' => 'professional', 'label' => 'Professional / Employee', 'pct' => 60],
            ['type' => 'student', 'label' => 'Student', 'pct' => 40],
        ];

        $mock = \Mockery::mock(AudienceTypeEstimationService::class);
        $mock->shouldReceive('estimate')->once()->andReturn([
            'estimated'     => $estimated,
            'tokens_in'     => 100,
            'tokens_out'    => 50,
            'credits_spent' => 3,
        ]);
        $this->app->instance(AudienceTypeEstimationService::class, $mock);

        $res = $this->withToken($token)
            ->postJson("/api/v1/links/{$link->id}/audience-estimate");

        $res->assertOk();
        $this->assertSame($estimated, $res->json('data.estimated'));
        $this->assertSame(3, $res->json('data.credits_spent'));

        $cached = $link->fresh()->settings['biolink']['audience_estimate'] ?? null;
        $this->assertIsArray($cached);
        $this->assertEquals($estimated, $cached['data']);
        $this->assertSame(3, $cached['credits_spent']);
        $this->assertNotEmpty($cached['generated_at']);
    }

    public function test_fresh_cached_estimate_short_circuits_without_charging(): void
    {
        $user = $this->makeUser(['audience_type_estimation' => true]);
        $link = $this->makeLink($user);

        $cachedRows = [
            ['type' => 'creator', 'label' => 'Creator / Artist', 'pct' => 100],
        ];
        $generatedAt = now()->subMinutes(3)->toIso8601String();
        $link->update([
            'settings' => [
                'biolink' => [
                    'audience_estimate' => [
                        'data'          => $cachedRows,
                        'generated_at'  => $generatedAt,
                        'credits_spent' => 4,
                    ],
                ],
            ],
        ]);
        $token = $user->createToken('test')->plainTextToken;

        // The estimation service must never be called (= no charge).
        $mock = \Mockery::mock(AudienceTypeEstimationService::class);
        $mock->shouldNotReceive('estimate');
        $this->app->instance(AudienceTypeEstimationService::class, $mock);

        $res = $this->withToken($token)
            ->postJson("/api/v1/links/{$link->id}/audience-estimate");

        $res->assertOk();
        $this->assertTrue($res->json('data.cached'));
        $this->assertSame(0, $res->json('data.credits_spent'));
        $this->assertEquals($cachedRows, $res->json('data.estimated'));
        $this->assertSame($generatedAt, $res->json('data.generated_at'));

        // Cached settings untouched.
        $cached = $link->fresh()->settings['biolink']['audience_estimate'];
        $this->assertSame($generatedAt, $cached['generated_at']);
        $this->assertSame(4, $cached['credits_spent']);
    }

    public function test_force_flag_bypasses_fresh_cooldown_and_charges(): void
    {
        $user = $this->makeUser(['audience_type_estimation' => true]);
        $link = $this->makeLink($user);

        // Fresh cached estimate (well inside the cooldown window).
        $link->update([
            'settings' => [
                'biolink' => [
                    'audience_estimate' => [
                        'data'          => [['type' => 'creator', 'label' => 'Creator / Artist', 'pct' => 100]],
                        'generated_at'  => now()->subMinutes(2)->toIso8601String(),
                        'credits_spent' => 4,
                    ],
                ],
            ],
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $estimated = [
            ['type' => 'business', 'label' => 'Business Owner', 'pct' => 100],
        ];
        $mock = \Mockery::mock(AudienceTypeEstimationService::class);
        $mock->shouldReceive('estimate')->once()->andReturn([
            'estimated'     => $estimated,
            'tokens_in'     => 10,
            'tokens_out'    => 5,
            'credits_spent' => 6,
        ]);
        $this->app->instance(AudienceTypeEstimationService::class, $mock);

        $res = $this->withToken($token)
            ->postJson("/api/v1/links/{$link->id}/audience-estimate", ['force' => true]);

        $res->assertOk();
        $this->assertNull($res->json('data.cached'));
        $this->assertSame($estimated, $res->json('data.estimated'));
        $this->assertSame(6, $res->json('data.credits_spent'));

        // Cached settings replaced with the forced run.
        $cached = $link->fresh()->settings['biolink']['audience_estimate'];
        $this->assertEquals($estimated, $cached['data']);
        $this->assertSame(6, $cached['credits_spent']);
    }

    public function test_force_false_still_short_circuits_on_fresh_estimate(): void
    {
        $user = $this->makeUser(['audience_type_estimation' => true]);
        $link = $this->makeLink($user);

        $cachedRows = [
            ['type' => 'student', 'label' => 'Student', 'pct' => 100],
        ];
        $link->update([
            'settings' => [
                'biolink' => [
                    'audience_estimate' => [
                        'data'          => $cachedRows,
                        'generated_at'  => now()->subMinutes(1)->toIso8601String(),
                        'credits_spent' => 2,
                    ],
                ],
            ],
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $mock = \Mockery::mock(AudienceTypeEstimationService::class);
        $mock->shouldNotReceive('estimate');
        $this->app->instance(AudienceTypeEstimationService::class, $mock);

        $res = $this->withToken($token)
            ->postJson("/api/v1/links/{$link->id}/audience-estimate", ['force' => false]);

        $res->assertOk();
        $this->assertTrue($res->json('data.cached'));
        $this->assertSame(0, $res->json('data.credits_spent'));
        $this->assertEquals($cachedRows, $res->json('data.estimated'));
    }

    public function test_web_route_force_flag_bypasses_fresh_cooldown(): void
    {
        $user = $this->makeUser(['audience_type_estimation' => true]);
        $link = $this->makeLink($user);

        $link->update([
            'settings' => [
                'biolink' => [
                    'audience_estimate' => [
                        'data'          => [['type' => 'business', 'label' => 'Business Owner', 'pct' => 100]],
                        'generated_at'  => now()->subMinutes(2)->toIso8601String(),
                        'credits_spent' => 5,
                    ],
                ],
            ],
        ]);

        $estimated = [
            ['type' => 'professional', 'label' => 'Professional / Employee', 'pct' => 100],
        ];
        $mock = \Mockery::mock(AudienceTypeEstimationService::class);
        $mock->shouldReceive('estimate')->once()->andReturn([
            'estimated'     => $estimated,
            'tokens_in'     => 10,
            'tokens_out'    => 5,
            'credits_spent' => 7,
        ]);
        $this->app->instance(AudienceTypeEstimationService::class, $mock);

        $res = $this->actingAs($user, 'web')
            ->postJson(route('user.links.audience.estimate', $link), ['force' => true]);

        $res->assertOk();
        $this->assertNull($res->json('cached'));
        $this->assertSame($estimated, $res->json('estimated'));
        $this->assertSame(7, $res->json('credits_spent'));
    }

    public function test_stale_cached_estimate_runs_a_new_estimation(): void
    {
        $user = $this->makeUser(['audience_type_estimation' => true]);
        $link = $this->makeLink($user);
        $link->update([
            'settings' => [
                'biolink' => [
                    'audience_estimate' => [
                        'data'          => [['type' => 'student', 'label' => 'Student', 'pct' => 100]],
                        'generated_at'  => now()->subMinutes(30)->toIso8601String(),
                        'credits_spent' => 2,
                    ],
                ],
            ],
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $estimated = [
            ['type' => 'professional', 'label' => 'Professional / Employee', 'pct' => 100],
        ];
        $mock = \Mockery::mock(AudienceTypeEstimationService::class);
        $mock->shouldReceive('estimate')->once()->andReturn([
            'estimated'     => $estimated,
            'tokens_in'     => 10,
            'tokens_out'    => 5,
            'credits_spent' => 3,
        ]);
        $this->app->instance(AudienceTypeEstimationService::class, $mock);

        $res = $this->withToken($token)
            ->postJson("/api/v1/links/{$link->id}/audience-estimate");

        $res->assertOk();
        $this->assertNull($res->json('data.cached'));
        $this->assertSame($estimated, $res->json('data.estimated'));
        $this->assertSame(3, $res->json('data.credits_spent'));
    }

    public function test_web_route_short_circuits_on_fresh_estimate(): void
    {
        $user = $this->makeUser(['audience_type_estimation' => true]);
        $link = $this->makeLink($user);

        $cachedRows = [
            ['type' => 'business', 'label' => 'Business Owner', 'pct' => 100],
        ];
        $link->update([
            'settings' => [
                'biolink' => [
                    'audience_estimate' => [
                        'data'          => $cachedRows,
                        'generated_at'  => now()->subMinutes(2)->toIso8601String(),
                        'credits_spent' => 5,
                    ],
                ],
            ],
        ]);

        $mock = \Mockery::mock(AudienceTypeEstimationService::class);
        $mock->shouldNotReceive('estimate');
        $this->app->instance(AudienceTypeEstimationService::class, $mock);

        $res = $this->actingAs($user, 'web')
            ->postJson(route('user.links.audience.estimate', $link));

        $res->assertOk();
        $this->assertTrue($res->json('cached'));
        $this->assertSame(0, $res->json('credits_spent'));
        $this->assertEquals($cachedRows, $res->json('estimated'));
    }

    public function test_analytics_payload_exposes_estimate_and_coin_cost(): void
    {
        $user = $this->makeUser(['audience_type_estimation' => true]);
        $link = $this->makeLink($user);
        $link->update([
            'settings' => [
                'biolink' => [
                    'audience_estimate' => [
                        'data' => [
                            ['type' => 'student', 'label' => 'Student', 'pct' => 100],
                        ],
                        'generated_at'  => now()->toIso8601String(),
                        'credits_spent' => 2,
                    ],
                ],
            ],
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $res = $this->withToken($token)
            ->getJson("/api/v1/links/{$link->id}/analytics");

        $res->assertOk();
        $estimate = $res->json('data.analytics.audience_estimate');
        $this->assertIsArray($estimate);
        $this->assertSame('student', $estimate['data'][0]['type']);
        $this->assertIsInt($res->json('data.analytics.audience_estimate_coins'));
        $this->assertIsInt($res->json('data.analytics.coin_balance'));
    }

    public function test_analytics_payload_exposes_wallet_coin_balance(): void
    {
        $user = $this->makeUser(['audience_type_estimation' => true]);
        $link = $this->makeLink($user);
        \App\Modules\User\Models\Wallet::updateOrCreate(
            ['user_id' => $user->id],
            ['balance' => 42],
        );
        $token = $user->createToken('test')->plainTextToken;

        $res = $this->withToken($token)
            ->getJson("/api/v1/links/{$link->id}/analytics");

        $res->assertOk();
        $this->assertSame(42, $res->json('data.analytics.coin_balance'));
    }

    public function test_insufficient_coins_maps_to_friendly_402(): void
    {
        $user = $this->makeUser(['audience_type_estimation' => true]);
        $link = $this->makeLink($user);
        $token = $user->createToken('test')->plainTextToken;

        $mock = \Mockery::mock(AudienceTypeEstimationService::class);
        $mock->shouldReceive('estimate')->once()->andThrow(
            new \App\Services\AI\InsufficientCoinsForAiException(5, 1)
        );
        $this->app->instance(AudienceTypeEstimationService::class, $mock);

        $res = $this->withToken($token)
            ->postJson("/api/v1/links/{$link->id}/audience-estimate");

        $res->assertStatus(402);
        $this->assertSame('insufficient_credits', $res->json('error.code'));
        $this->assertStringContainsString('Top up', $res->json('error.message'));
        $this->assertSame(5, $res->json('error.details.required'));
        $this->assertSame(1, $res->json('error.details.balance'));
    }

    public function test_analytics_coin_cost_is_zero_on_free_plan(): void
    {
        $user = $this->makeUser(['audience_type_estimation' => false]);
        $link = $this->makeLink($user);
        $token = $user->createToken('test')->plainTextToken;

        $res = $this->withToken($token)
            ->getJson("/api/v1/links/{$link->id}/analytics");

        $res->assertOk();
        $this->assertSame(0, $res->json('data.analytics.audience_estimate_coins'));
    }

    public function test_other_users_link_is_404(): void
    {
        $owner = $this->makeUser(['audience_type_estimation' => true]);
        $link  = $this->makeLink($owner);

        $stranger = $this->makeUser(['audience_type_estimation' => true]);
        $token = $stranger->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/v1/links/{$link->id}/audience-estimate")
            ->assertStatus(404);
    }
}
