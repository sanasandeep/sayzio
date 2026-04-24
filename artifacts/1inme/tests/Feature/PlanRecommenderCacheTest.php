<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Contact;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\PlanRecommender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Verifies the per-user usage cache that backs the upgrade banner on
 * /pricing and /user/upgrade. The banner used to fan out into six
 * count() queries per request; this test pins down both halves of the
 * fix:
 *  1) repeated PlanRecommender::for calls don't re-query the DB while
 *     the cache entry is fresh, and
 *  2) creating one of the tracked models busts the cache so the gauge
 *     reflects reality on the very next request.
 */
class PlanRecommenderCacheTest extends TestCase
{
    use RefreshDatabase;

    private function plan(array $features = []): Plan
    {
        $slug = 'p' . Str::random(6);
        return Plan::create([
            'name'          => $slug,
            'slug'          => $slug,
            'monthly_price' => 0,
            'annual_price'  => 0,
            'trial_days'    => 0,
            'status'        => 'active',
            'features'      => $features + [
                'max_links'    => 10,
                'max_biolinks' => 5,
                'contacts_max' => 50,
            ],
        ]);
    }

    private function user(?Plan $plan = null): User
    {
        $u = User::create([
            'name'     => 'u' . Str::random(4),
            'email'    => 'u' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'plan_id'  => $plan?->id,
        ]);
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);
        return $u;
    }

    public function test_repeated_calls_do_not_rerun_count_queries(): void
    {
        $plan = $this->plan();
        $user = $this->user($plan);
        $plans = collect([$plan]);

        // First call primes the cache (issues the count queries).
        PlanRecommender::for($user, $plans);

        // Second call should hit the cache. Watch every query on the
        // tracked tables and assert none fire during the recompute.
        $tracked = ['links', 'contacts', 'user_files', 'projects'];
        $hits = 0;
        DB::listen(function ($q) use ($tracked, &$hits) {
            foreach ($tracked as $t) {
                if (str_contains($q->sql, "\"{$t}\"") || str_contains($q->sql, "`{$t}`")) {
                    $hits++;
                    break;
                }
            }
        });

        $payload = PlanRecommender::for($user, $plans);

        $this->assertSame(0, $hits, 'Cached call should not re-query usage tables.');
        $this->assertNotNull($payload);
        $this->assertSame([], array_filter($payload['usage'], fn ($r) => $r['used'] !== 0));
    }

    public function test_creating_a_link_busts_the_cache_for_that_user(): void
    {
        $plan = $this->plan();
        $user = $this->user($plan);
        $plans = collect([$plan]);

        $first = PlanRecommender::for($user, $plans);
        $linksRow = collect($first['usage'])->firstWhere('key', 'max_links');
        $this->assertSame(0, $linksRow['used']);

        // Cache entry exists.
        $this->assertTrue(Cache::has(PlanRecommender::cacheKey((int) $user->id)));

        Link::create([
            'user_id'  => $user->id,
            'type'     => 'short',
            'alias'    => 'a' . Str::random(6),
            'long_url' => 'https://example.com',
        ]);

        // The created hook should have dropped the cache entry.
        $this->assertFalse(Cache::has(PlanRecommender::cacheKey((int) $user->id)));

        $second = PlanRecommender::for($user, $plans);
        $linksRow = collect($second['usage'])->firstWhere('key', 'max_links');
        $this->assertSame(1, $linksRow['used']);
    }

    public function test_creating_one_users_contact_does_not_bust_anothers_cache(): void
    {
        $plan = $this->plan();
        $alice = $this->user($plan);
        $bob   = $this->user($plan);
        $plans = collect([$plan]);

        PlanRecommender::for($alice, $plans);
        PlanRecommender::for($bob, $plans);

        $this->assertTrue(Cache::has(PlanRecommender::cacheKey((int) $alice->id)));
        $this->assertTrue(Cache::has(PlanRecommender::cacheKey((int) $bob->id)));

        Contact::create([
            'user_id'      => $alice->id,
            'display_name' => 'Z',
        ]);

        $this->assertFalse(Cache::has(PlanRecommender::cacheKey((int) $alice->id)));
        $this->assertTrue(Cache::has(PlanRecommender::cacheKey((int) $bob->id)));
    }

    public function test_updating_a_link_type_busts_the_cache(): void
    {
        // Flipping a Link from "short" to "biolink" doesn't change the
        // row count but it does change the max_biolinks gauge — so the
        // updated event must bust the cache too.
        $plan = $this->plan();
        $user = $this->user($plan);
        $plans = collect([$plan]);

        $link = Link::create([
            'user_id'  => $user->id,
            'type'     => 'short',
            'alias'    => 'a' . Str::random(6),
            'long_url' => 'https://example.com',
        ]);

        PlanRecommender::for($user, $plans);
        $this->assertTrue(Cache::has(PlanRecommender::cacheKey((int) $user->id)));

        $link->update(['type' => 'biolink']);

        $this->assertFalse(Cache::has(PlanRecommender::cacheKey((int) $user->id)));

        $payload = PlanRecommender::for($user, $plans);
        $biolinkRow = collect($payload['usage'])->firstWhere('key', 'max_biolinks');
        $this->assertSame(1, $biolinkRow['used']);
    }

    public function test_forget_usage_accepts_user_int_or_null(): void
    {
        $plan = $this->plan();
        $user = $this->user($plan);

        PlanRecommender::for($user, collect([$plan]));
        $this->assertTrue(Cache::has(PlanRecommender::cacheKey((int) $user->id)));

        PlanRecommender::forgetUsage(null); // no-op
        PlanRecommender::forgetUsage(0);    // no-op
        $this->assertTrue(Cache::has(PlanRecommender::cacheKey((int) $user->id)));

        PlanRecommender::forgetUsage((int) $user->id);
        $this->assertFalse(Cache::has(PlanRecommender::cacheKey((int) $user->id)));
    }
}
