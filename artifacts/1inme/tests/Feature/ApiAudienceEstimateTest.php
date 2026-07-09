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
