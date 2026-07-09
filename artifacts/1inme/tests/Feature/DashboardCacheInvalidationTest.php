<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The mobile home dashboard endpoint caches its aggregate payload per
 * user for 30s. Creating or deleting a link must bust that cache so
 * the counts are instantly fresh across devices — the TTL is only a
 * safety net.
 */
class DashboardCacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $u = User::create([
            'name'     => 'u' . Str::random(4),
            'email'    => 'u' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);

        return $u;
    }

    private function cacheKey(User $user): string
    {
        return "api.dashboard.v1.{$user->id}";
    }

    public function test_creating_a_link_busts_the_dashboard_cache(): void
    {
        $user = $this->user();

        Cache::put($this->cacheKey($user), ['totals' => ['total_links' => 0]], 30);
        $this->assertTrue(Cache::has($this->cacheKey($user)));

        Link::create([
            'user_id'  => $user->id,
            'type'     => 'short',
            'alias'    => 'a' . Str::random(6),
            'long_url' => 'https://example.com',
        ]);

        $this->assertFalse(Cache::has($this->cacheKey($user)));
    }

    public function test_deleting_a_link_busts_the_dashboard_cache(): void
    {
        $user = $this->user();

        $link = Link::create([
            'user_id'  => $user->id,
            'type'     => 'short',
            'alias'    => 'a' . Str::random(6),
            'long_url' => 'https://example.com',
        ]);

        Cache::put($this->cacheKey($user), ['totals' => ['total_links' => 1]], 30);
        $this->assertTrue(Cache::has($this->cacheKey($user)));

        $link->delete();

        $this->assertFalse(Cache::has($this->cacheKey($user)));
    }
}
