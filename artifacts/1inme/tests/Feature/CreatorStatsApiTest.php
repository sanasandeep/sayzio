<?php

namespace Tests\Feature;

use App\Modules\User\Models\CreatorPaymentEvent;
use App\Modules\User\Models\CreatorPost;
use App\Modules\User\Models\CreatorSubscription;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sanctum (bearer-token) coverage for the mobile Stats home
 * (GET /api/v1/stats), which feeds artifacts/1inme-mobile/app/stats.tsx.
 *
 * Authenticated requests use a real personal access token (NOT
 * Sanctum::actingAs, which injects a mock that breaks the
 * TouchSessionToken middleware — every authed request would 500).
 */
class CreatorStatsApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attrs = []): User
    {
        return User::factory()->create($attrs);
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_stats_requires_authentication(): void
    {
        $this->getJson('/api/v1/stats')->assertStatus(401);
    }

    public function test_stats_returns_the_expected_envelope_shape(): void
    {
        $user = $this->makeUser();

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->token($user)])
            ->getJson('/api/v1/stats')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'range'      => ['from', 'to'],
                    'audience'   => ['followers', 'followers_delta', 'subscribers'],
                    'content'    => ['posts', 'views', 'comments'],
                    'engagement' => ['reactions', 'tips'],
                    'earnings'   => ['tips_total', 'subs_total', 'payouts_total', 'currency'],
                    'trends'     => [
                        'followers' => [['date', 'value']],
                        'posts'     => [['date', 'value']],
                    ],
                    'capabilities' => ['analytics_export'],
                ],
            ]);
    }

    public function test_trends_are_zero_filled_across_the_range(): void
    {
        $user = $this->makeUser();

        $res = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token($user)])
            ->getJson('/api/v1/stats?range=7d')
            ->assertOk();

        // 7d preset = 7 zero-filled daily points per series.
        $this->assertCount(7, $res->json('data.trends.followers'));
        $this->assertCount(7, $res->json('data.trends.posts'));
        $this->assertSame(0, $res->json('data.trends.followers.0.value'));
    }

    public function test_trend_series_counts_activity_by_day(): void
    {
        $creator = $this->makeUser();
        $fan     = $this->makeUser();

        Follow::create([
            'follower_id' => $fan->id,
            'creator_id'  => $creator->id,
            'created_at'  => now()->subDay(),
        ]);

        $res = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token($creator)])
            ->getJson('/api/v1/stats?range=7d')
            ->assertOk();

        $followerTrend = collect($res->json('data.trends.followers'));
        $this->assertSame(1, $followerTrend->sum('value'));
        $this->assertSame(1, $followerTrend->firstWhere('date', now()->subDay()->format('Y-m-d'))['value']);
    }

    public function test_range_start_is_clamped_to_plan_retention_window(): void
    {
        // A plan-less user resolves to the 30-day retention default
        // (User::statsRetentionDays()). Asking for 1y (365 days) must clamp
        // the window so callers can't read history older than the plan
        // retains.
        $user = $this->makeUser();

        $res = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token($user)])
            ->getJson('/api/v1/stats?range=1y')
            ->assertOk();

        $from = $res->json('data.range.from');
        $earliest = now()->subDays(30)->startOfDay()->format('Y-m-d');
        $this->assertSame($earliest, $from);
    }

    public function test_stats_reflects_creator_activity_in_range(): void
    {
        $creator = $this->makeUser();
        $fan     = $this->makeUser();

        Follow::create([
            'follower_id' => $fan->id,
            'creator_id'  => $creator->id,
            'created_at'  => now()->subDay(),
        ]);

        CreatorPost::create([
            'user_id'      => $creator->id,
            'title'        => 'Hello',
            'body'         => 'World',
            'published_at' => now()->subDay(),
        ]);

        CreatorSubscription::create([
            'fan_user_id'     => $fan->id,
            'creator_user_id' => $creator->id,
            'status'          => CreatorSubscription::STATUS_ACTIVE,
        ]);

        CreatorPaymentEvent::create([
            'creator_user_id' => $creator->id,
            'fan_user_id'     => $fan->id,
            'source'          => CreatorPaymentEvent::SOURCE_TIP,
            'type'            => CreatorPaymentEvent::TYPE_TIP_RECEIVED,
            'amount_cents'    => 500,
            'currency'        => 'USD',
            'occurred_at'     => now()->subDay(),
        ]);

        $res = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token($creator)])
            ->getJson('/api/v1/stats')
            ->assertOk();

        $res->assertJsonPath('data.audience.followers_delta', 1);
        $res->assertJsonPath('data.audience.subscribers', 1);
        $res->assertJsonPath('data.content.posts', 1);
        $res->assertJsonPath('data.engagement.tips', 1);
        $res->assertJsonPath('data.earnings.tips_total', 5);
        $res->assertJsonPath('data.earnings.payouts_total', 5);
    }
}
