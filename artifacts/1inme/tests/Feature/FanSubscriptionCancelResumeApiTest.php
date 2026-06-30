<?php

namespace Tests\Feature;

use App\Modules\User\Models\CreatorSubscription;
use App\Modules\User\Models\SubscriptionTier;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * End-to-end Sanctum coverage for the fan-facing "manage my subscriptions"
 * surface (Task #3033): listing every creator subscription a fan holds
 * (GET /api/v1/me/subscriptions), cancelling one at period end
 * (POST /api/v1/creators/{handle}/my-subscription/cancel) and undoing that
 * cancellation (POST /api/v1/creators/{handle}/my-subscription/resume).
 *
 * Authenticated requests use a real personal access token (NOT
 * Sanctum::actingAs, which injects a Mockery mock that breaks the
 * TouchSessionToken middleware — every authed request would 500).
 */
class FanSubscriptionCancelResumeApiTest extends TestCase
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

    private function makeTier(User $creator): SubscriptionTier
    {
        return SubscriptionTier::create([
            'user_id'             => $creator->id,
            'name'                => 'Gold',
            'slug'                => 'gold-' . Str::random(4),
            'is_active'           => true,
            'price_monthly_cents' => 500,
            'currency'            => 'USD',
            'sort_order'          => 0,
        ]);
    }

    private function makeSubscription(User $fan, User $creator, SubscriptionTier $tier): CreatorSubscription
    {
        // current_period_end must be in the future so cancellation schedules
        // for period end (status stays active) rather than terminating now.
        return CreatorSubscription::create([
            'fan_user_id'          => $fan->id,
            'creator_user_id'      => $creator->id,
            'tier_id'              => $tier->id,
            'billing_cycle'        => CreatorSubscription::CYCLE_MONTHLY,
            'status'               => CreatorSubscription::STATUS_ACTIVE,
            'price_cents'          => 500,
            'currency'             => 'USD',
            'started_at'           => now()->subDays(3),
            'current_period_start' => now()->subDays(3),
            'current_period_end'   => now()->addDays(27),
            'cancel_at_period_end' => false,
        ]);
    }

    public function test_me_subscriptions_requires_authentication(): void
    {
        $this->getJson('/api/v1/me/subscriptions')->assertStatus(401);
    }

    public function test_fan_can_list_cancel_then_resume_a_subscription(): void
    {
        $creator = $this->makeUser(['handle' => 'creator' . Str::random(5)]);
        $fan     = $this->makeUser();
        $tier    = $this->makeTier($creator);
        $sub     = $this->makeSubscription($fan, $creator, $tier);

        $token = $this->token($fan);

        // 1. List — the fan sees their one active subscription.
        $list = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/v1/me/subscriptions')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $sub->id)
            ->assertJsonPath('data.items.0.status', CreatorSubscription::STATUS_ACTIVE)
            ->assertJsonPath('data.items.0.cancel_at_period_end', false);
        $this->assertCount(1, $list->json('data.items'));

        // 2. Cancel — schedules cancellation at period end; stays active.
        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/v1/creators/' . $creator->handle . '/my-subscription/cancel')
            ->assertOk()
            ->assertJsonPath('data.subscription.id', $sub->id)
            ->assertJsonPath('data.subscription.status', CreatorSubscription::STATUS_ACTIVE)
            ->assertJsonPath('data.subscription.cancel_at_period_end', true);

        $sub->refresh();
        $this->assertSame(CreatorSubscription::STATUS_ACTIVE, $sub->status);
        $this->assertTrue($sub->cancel_at_period_end);
        $this->assertNotNull($sub->canceled_at);

        // 3. Resume — undo the scheduled cancellation.
        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/v1/creators/' . $creator->handle . '/my-subscription/resume')
            ->assertOk()
            ->assertJsonPath('data.subscription.id', $sub->id)
            ->assertJsonPath('data.subscription.status', CreatorSubscription::STATUS_ACTIVE)
            ->assertJsonPath('data.subscription.cancel_at_period_end', false);

        $sub->refresh();
        $this->assertSame(CreatorSubscription::STATUS_ACTIVE, $sub->status);
        $this->assertFalse($sub->cancel_at_period_end);
        $this->assertNull($sub->canceled_at);
    }

    public function test_me_subscriptions_does_not_leak_another_fans_subscriptions(): void
    {
        $creator   = $this->makeUser(['handle' => 'creator' . Str::random(5)]);
        $tier      = $this->makeTier($creator);
        $fan       = $this->makeUser();
        $otherFan  = $this->makeUser();

        $mine      = $this->makeSubscription($fan, $creator, $tier);
        $theirs    = $this->makeSubscription($otherFan, $creator, $tier);

        $items = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token($fan)])
            ->getJson('/api/v1/me/subscriptions')
            ->assertOk()
            ->json('data.items');

        $ids = collect($items)->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($theirs->id, $ids);
        $this->assertCount(1, $items);
    }
}
