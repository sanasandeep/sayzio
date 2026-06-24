<?php

namespace Tests\Feature;

use App\Modules\User\Models\CreatorPaymentEvent;
use App\Modules\User\Models\CreatorPost;
use App\Modules\User\Models\CreatorSubscription;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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
        return User::create(array_merge([
            'name'     => 'Test ' . Str::random(4),
            'email'    => 'u' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ], $attrs));
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
                ],
            ]);
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
