<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Locks in the plan gate on the Zio Browser device-sync surface (Task #6647):
 *
 * - `browser_sync` boolean: OFF plans get 402 plan_upgrade_required on
 *   register / push / pull; purge stays available (cleanup is never gated).
 * - Legacy-safe default: plans whose features JSON lacks the key behave as ON.
 * - `max_browser_sync_items`: per-entity row cap — new rows over the cap come
 *   back in `rejected`, while updates/tombstones of existing rows still land.
 *
 * Sanctum API tests authenticate with a real Bearer token — Sanctum::actingAs
 * breaks the TouchSessionToken middleware, so we mint a real token.
 */
class BrowserSyncPlanGatingTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $features): User
    {
        $plan = Plan::create([
            'name'               => 'Plan',
            'slug'               => 'plan-' . Str::lower(Str::random(6)),
            'monthly_price'      => 0,
            'annual_price'       => 0,
            'trial_days'         => 0,
            'grace_days'         => 0,
            'refund_window_days' => 0,
            'status'             => 'active',
            'sort_order'         => 1,
            'features'           => $features,
        ]);

        return User::factory()->create(['plan_id' => $plan->id])->fresh();
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    private function pushBookmarks(string $token, array $items, string $device = 'dev-1')
    {
        return $this->withToken($token)->postJson("/api/v1/browser/devices/{$device}/bookmarks", [
            'items' => $items,
        ]);
    }

    private function item(string $localId, array $extra = []): array
    {
        return array_merge([
            'local_id'   => $localId,
            'updated_at' => now()->toIso8601String(),
            'data'       => ['url' => 'https://example.com/' . $localId, 'title' => $localId],
        ], $extra);
    }

    public function test_gated_off_plan_gets_402_on_register_push_and_pull(): void
    {
        $user  = $this->makeUser(['browser_sync' => false]);
        $token = $this->token($user);

        $this->withToken($token)->postJson('/api/v1/browser/devices', [
            'label' => 'MacBook', 'platform' => 'mac',
        ])->assertStatus(402)->assertJsonPath('error.code', 'plan_upgrade_required')
          ->assertJsonPath('error.details.feature', 'browser_sync');

        $this->pushBookmarks($token, [$this->item('a')])->assertStatus(402);

        $this->withToken($token)->getJson('/api/v1/browser/devices/dev-1/pull')->assertStatus(402);

        // Cleanup is never gated.
        $this->withToken($token)->postJson('/api/v1/browser/history/purge')->assertOk();
    }

    public function test_missing_feature_key_is_legacy_safe_on(): void
    {
        $user  = $this->makeUser([]);
        $token = $this->token($user);

        $this->pushBookmarks($token, [$this->item('a')])
            ->assertOk()
            ->assertJsonPath('data.accepted.0', 'a')
            ->assertJsonPath('data.rejected', []);

        $this->assertSame(1, DB::table('browser_bookmarks')->where('user_id', $user->id)->count());
    }

    public function test_row_cap_rejects_new_rows_but_allows_updates_and_tombstones(): void
    {
        $user  = $this->makeUser(['browser_sync' => true, 'max_browser_sync_items' => 2]);
        $token = $this->token($user);

        // Fill the cap.
        $this->pushBookmarks($token, [$this->item('a'), $this->item('b')])
            ->assertOk()
            ->assertJsonPath('data.rejected', []);

        // Third NEW row is rejected.
        $res = $this->pushBookmarks($token, [$this->item('c')])->assertOk();
        $res->assertJsonPath('data.rejected.0', 'c')->assertJsonPath('data.limit', 2);
        $this->assertSame(2, DB::table('browser_bookmarks')->where('user_id', $user->id)->count());

        // Update of an existing row still lands.
        $this->pushBookmarks($token, [$this->item('a', ['updated_at' => now()->addMinute()->toIso8601String()])])
            ->assertOk()->assertJsonPath('data.accepted.0', 'a');

        // Tombstoning an existing row still lands and frees a slot.
        $this->pushBookmarks($token, [$this->item('b', [
            'deleted'    => true,
            'updated_at' => now()->addMinutes(2)->toIso8601String(),
        ])])->assertOk()->assertJsonPath('data.accepted.0', 'b');

        $this->pushBookmarks($token, [$this->item('d', ['updated_at' => now()->addMinutes(3)->toIso8601String()])])
            ->assertOk()->assertJsonPath('data.accepted.0', 'd');
    }

    public function test_resurrecting_a_tombstone_pays_the_cap_like_a_fresh_insert(): void
    {
        $user  = $this->makeUser(['browser_sync' => true, 'max_browser_sync_items' => 2]);
        $token = $this->token($user);

        // Two live rows fill the cap; 'z' arrives as a tombstone (allowed).
        $this->pushBookmarks($token, [
            $this->item('a'),
            $this->item('b'),
            $this->item('z', ['deleted' => true]),
        ])->assertOk()->assertJsonPath('data.rejected', []);

        // Reviving 'z' would grow the live count past the cap — rejected.
        $this->pushBookmarks($token, [$this->item('z', [
            'deleted'    => false,
            'updated_at' => now()->addMinute()->toIso8601String(),
        ])])->assertOk()->assertJsonPath('data.rejected.0', 'z');
        $this->assertTrue((bool) DB::table('browser_bookmarks')
            ->where('user_id', $user->id)->where('local_id', 'z')->value('deleted'));

        // One batch: tombstone 'a' then revive 'z' — the freed slot must be
        // visible to the later item in the same request.
        $this->pushBookmarks($token, [
            $this->item('a', ['deleted' => true, 'updated_at' => now()->addMinutes(2)->toIso8601String()]),
            $this->item('z', ['updated_at' => now()->addMinutes(2)->toIso8601String()]),
        ])->assertOk()->assertJsonPath('data.rejected', [])
          ->assertJsonPath('data.accepted', ['a', 'z']);

        $this->assertSame(2, DB::table('browser_bookmarks')
            ->where('user_id', $user->id)->where('deleted', false)->count());
    }
}
