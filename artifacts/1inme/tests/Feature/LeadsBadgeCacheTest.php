<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Review;
use App\Modules\User\Models\User;
use App\Modules\User\Services\LeadAggregator;
use App\Modules\User\Services\LeadApprover;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
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

        $user = User::create([
            'name'     => 'U ' . Str::random(4),
            'email'    => 'u' . Str::lower(Str::random(8)) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'plan_id'  => $plan->id,
        ]);
        $user->ensureDefaultWorkspace();

        return $user->fresh();
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
}
