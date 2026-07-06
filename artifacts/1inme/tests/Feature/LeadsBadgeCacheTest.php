<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Review;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\LeadAggregator;
use App\Modules\User\Services\LeadApprover;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Locks in the sidebar pending-leads badge caching (Task #3760): the count is
 * served from a short-lived per-owner/workspace cache so ordinary page loads
 * don't run one COUNT query per source, and approving/dismissing a lead drops
 * that cache so the badge stays accurate.
 */
class LeadsBadgeCacheTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        $plan = Plan::create([
            'name'               => 'Pro',
            'slug'               => 'plan-' . Str::lower(Str::random(6)),
            'monthly_price'      => 0,
            'annual_price'       => 0,
            'trial_days'         => 0,
            'grace_days'         => 0,
            'refund_window_days' => 0,
            'status'             => 'active',
            'sort_order'         => 1,
            'features'           => [],
        ]);

        return User::factory()->create([
            'plan_id'  => $plan->id,
        ])->fresh();
    }

    private function pendingReview(User $user): Review
    {
        return Review::create([
            'user_id'     => $user->id,
            'author_name' => 'Reviewer',
            'rating'      => 5,
            'body'        => 'Great!',
            'status'      => Review::STATUS_APPROVED,
            'is_spam'     => false,
        ]);
    }

    public function test_cached_count_is_served_from_cache_until_invalidated(): void
    {
        $user = $this->makeUser();
        $review = $this->pendingReview($user);

        $aggregator = new LeadAggregator($user->id);

        // First read counts and caches the result.
        $this->assertSame(1, $aggregator->cachedPendingCount());

        // Deleting the source row bypasses the cache, so the badge keeps
        // serving the cached value — proving no COUNT re-ran.
        $review->delete();
        $this->assertSame(1, $aggregator->cachedPendingCount());
        $this->assertSame(0, $aggregator->pendingCount());

        // Explicit invalidation forces a fresh count.
        LeadAggregator::forgetPendingCount($user->id);
        $this->assertSame(0, $aggregator->cachedPendingCount());
    }

    public function test_dismiss_invalidates_the_cached_count(): void
    {
        $user = $this->makeUser();
        $key  = LeadAggregator::pendingCountCacheKey($user->id);

        // Prime a stale cached value.
        Cache::put($key, 7, LeadAggregator::PENDING_COUNT_TTL);
        $this->assertTrue(Cache::has($key));

        (new LeadApprover())->dismiss($user, [
            'name'        => 'Reviewer',
            'email'       => null,
            'phone'       => null,
            'source_type' => LeadAggregator::SOURCE_REVIEW,
            'source_id'   => 999,
            'context'     => null,
        ]);

        $this->assertFalse(Cache::has($key));
    }

    public function test_approve_invalidates_the_cached_count(): void
    {
        $user = $this->makeUser();
        $key  = LeadAggregator::pendingCountCacheKey($user->id);

        Cache::put($key, 7, LeadAggregator::PENDING_COUNT_TTL);
        $this->assertTrue(Cache::has($key));

        (new LeadApprover())->approve($user, [
            'name'        => 'New Lead',
            'email'       => 'newlead-' . Str::lower(Str::random(6)) . '@ex.com',
            'phone'       => null,
            'source_type' => LeadAggregator::SOURCE_REVIEW,
            'source_id'   => 1001,
            'context'     => null,
        ]);

        $this->assertFalse(Cache::has($key));
    }

    /**
     * Owner-scoped sources (RSVP / order / review / event-interest) change the
     * pending count in EVERY one of the owner's workspaces, so handling one
     * must clear the cached badge for all of them — not just the workspace the
     * action happened in — otherwise a second workspace shows a stale badge
     * until the TTL self-heals (Task #3770).
     */
    public function test_owner_scoped_lead_clears_every_workspace_cache(): void
    {
        $user = $this->makeUser();

        $secondWorkspace = Workspace::create([
            'owner_user_id' => $user->id,
            'name'          => 'Second',
            'slug'          => 'ws-' . Str::lower(Str::random(6)),
            'is_personal'   => false,
        ]);

        $personalId = $user->ownedWorkspaces()
            ->where('is_personal', true)
            ->value('id');

        $personalKey = LeadAggregator::pendingCountCacheKey($user->id, $personalId);
        $secondKey   = LeadAggregator::pendingCountCacheKey($user->id, $secondWorkspace->id);
        $noneKey     = LeadAggregator::pendingCountCacheKey($user->id, null);

        // Prime a stale cached value for both workspaces plus the CLI/public key.
        Cache::put($personalKey, 3, LeadAggregator::PENDING_COUNT_TTL);
        Cache::put($secondKey, 3, LeadAggregator::PENDING_COUNT_TTL);
        Cache::put($noneKey, 3, LeadAggregator::PENDING_COUNT_TTL);

        (new LeadApprover())->dismiss($user, [
            'name'        => 'Reviewer',
            'email'       => null,
            'phone'       => null,
            'source_type' => LeadAggregator::SOURCE_REVIEW,
            'source_id'   => 555,
            'context'     => null,
        ]);

        $this->assertFalse(Cache::has($personalKey), 'personal workspace badge should be cleared');
        $this->assertFalse(Cache::has($secondKey), 'second workspace badge should be cleared');
        $this->assertFalse(Cache::has($noneKey), 'workspace-less badge should be cleared');
    }

    /**
     * Workspace-scoped sources (Form / Subscriber) genuinely differ per
     * workspace, so handling one only clears the current workspace's cache and
     * leaves the other workspace's cached badge untouched.
     */
    public function test_workspace_scoped_lead_clears_only_the_current_workspace(): void
    {
        $user = $this->makeUser();

        $current = $user->ownedWorkspaces()->where('is_personal', true)->first();
        $other   = Workspace::create([
            'owner_user_id' => $user->id,
            'name'          => 'Other',
            'slug'          => 'ws-' . Str::lower(Str::random(6)),
            'is_personal'   => false,
        ]);

        app()->instance('current_workspace', $current);

        $currentKey = LeadAggregator::pendingCountCacheKey($user->id, $current->id);
        $otherKey   = LeadAggregator::pendingCountCacheKey($user->id, $other->id);

        Cache::put($currentKey, 4, LeadAggregator::PENDING_COUNT_TTL);
        Cache::put($otherKey, 4, LeadAggregator::PENDING_COUNT_TTL);

        (new LeadApprover())->dismiss($user, [
            'name'        => 'Sub',
            'email'       => null,
            'phone'       => null,
            'source_type' => LeadAggregator::SOURCE_SUBSCRIBER,
            'source_id'   => 777,
            'context'     => null,
        ]);

        $this->assertFalse(Cache::has($currentKey), 'current workspace badge should be cleared');
        $this->assertTrue(Cache::has($otherKey), 'other workspace badge should NOT be cleared for a workspace-scoped source');
    }
}
