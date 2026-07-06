<?php

namespace Tests\Feature;

use App\Modules\User\Models\DialerFavorite;
use App\Modules\User\Models\DialerLookup;
use App\Modules\User\Models\DialerNumberFlag;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\DialerData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Confirms the dialer stays in sync across two signed-in devices
 * (.agents/memory/dialer-everyday.md). The sync mechanism is a pollable
 * cursor: DialerData::liveSignature() folds favorites, spam/block flags and
 * the append-only call log into a single monotonic string, and
 * DialerData::liveState() returns fresh favorites/frequent/recents ONLY when
 * the caller's cursor no longer matches. A regression here means a change made
 * on device A silently never appears on device B.
 *
 * (1) liveState() unit-style coverage: unchanged cursor => changed:false and no
 *     payload; null or stale cursor => changed:true + fresh lists.
 * (2) The API endpoint GET /api/v1/dialer/live?since=<cursor> hit with a REAL
 *     Bearer token (see .agents/memory/sanctum-api-tests.md — Sanctum::actingAs
 *     breaks the TouchSessionToken middleware), asserting the {data} envelope
 *     and the cursor round-trip.
 * (3) The WEB endpoint GET /user/dialer/live?since=<cursor> that powers the
 *     browser dialer's polling. Unlike the Sanctum path, this route carries
 *     `workspace.can:settings.view`, so the test binds an active workspace in
 *     the session (see .agents/memory/api-workspace-scope.md) before asserting
 *     the same {data} envelope and cursor-driven change detection.
 */
class DialerLiveSyncTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $prefix = 'u'): User
    {
        return User::factory()->create([
            'name' => $prefix . Str::random(4),
            'email' => $prefix . '-' . Str::random(8) . '@example.com',
        ]);
    }

    // ===== (1) liveState cursor semantics =====

    public function test_matching_cursor_reports_no_change_and_omits_payload(): void
    {
        $user = $this->makeUser('a');

        // Seed some dialer state so the two devices have something to sync.
        DialerFavorite::create([
            'user_id'     => $user->id,
            'number_e164' => '+15550001111',
            'label'       => 'Speed 1',
            'sort_order'  => 1,
        ]);
        DialerLookup::create([
            'user_id'      => $user->id,
            'number_e164'  => '+15550001111',
            'looked_up_at' => now(),
        ]);

        // Device A takes a first snapshot (null cursor => always changed).
        $first = DialerData::liveState($user->id, null);
        $this->assertTrue($first['changed']);
        $this->assertArrayHasKey('cursor', $first);
        $this->assertArrayHasKey('favorites', $first);
        $this->assertArrayHasKey('frequent', $first);
        $this->assertArrayHasKey('recents', $first);

        // Device A polls again with the cursor it just received. Nothing changed
        // in between, so no payload should come back — only the same cursor.
        $second = DialerData::liveState($user->id, $first['cursor']);
        $this->assertFalse($second['changed']);
        $this->assertSame($first['cursor'], $second['cursor']);
        $this->assertArrayNotHasKey('favorites', $second);
        $this->assertArrayNotHasKey('frequent', $second);
        $this->assertArrayNotHasKey('recents', $second);
    }

    public function test_new_call_on_another_device_advances_cursor_and_returns_fresh_payload(): void
    {
        $user = $this->makeUser('b');

        $cursor = DialerData::liveState($user->id, null)['cursor'];

        // No change yet -> steady cursor.
        $this->assertFalse(DialerData::liveState($user->id, $cursor)['changed']);

        // Device B logs a brand-new call (append-only log => max(id) advances).
        DialerLookup::create([
            'user_id'      => $user->id,
            'number_e164'  => '+15559998888',
            'looked_up_at' => now(),
        ]);

        // Device A polls with its now-stale cursor and must see the change plus
        // the fresh recents payload reflecting the new call.
        $state = DialerData::liveState($user->id, $cursor);
        $this->assertTrue($state['changed']);
        $this->assertNotSame($cursor, $state['cursor']);
        $this->assertArrayHasKey('recents', $state);
        $numbers = array_column($state['recents'], 'number_e164');
        $this->assertContains('+15559998888', $numbers);
    }

    public function test_new_favorite_advances_cursor_and_appears_in_fresh_payload(): void
    {
        $user = $this->makeUser('c');

        $cursor = DialerData::liveState($user->id, null)['cursor'];
        $this->assertFalse(DialerData::liveState($user->id, $cursor)['changed']);

        // Device B adds a speed-dial favorite.
        DialerFavorite::create([
            'user_id'     => $user->id,
            'number_e164' => '+15551234567',
            'label'       => 'New Fav',
            'sort_order'  => 1,
        ]);

        $state = DialerData::liveState($user->id, $cursor);
        $this->assertTrue($state['changed']);
        $labels = array_column($state['favorites'], 'label');
        $this->assertContains('New Fav', $labels);
    }

    public function test_new_follower_advances_cursor(): void
    {
        $user  = $this->makeUser('g');
        $other = $this->makeUser('h');

        $cursor = DialerData::liveState($user->id, null)['cursor'];
        $this->assertFalse(DialerData::liveState($user->id, $cursor)['changed']);

        // Someone follows the user while the dialer tab is open: the cursor
        // must advance so the pre-query suggestions (new_followers group)
        // refresh on the next poll without a page reload.
        Follow::withoutGlobalScope('workspace')->create([
            'follower_id' => $other->id,
            'creator_id'  => $user->id,
            'created_at'  => now(),
        ]);

        $state = DialerData::liveState($user->id, $cursor);
        $this->assertTrue($state['changed']);
        $this->assertNotSame($cursor, $state['cursor']);
    }

    public function test_new_subscriber_advances_cursor(): void
    {
        $user = $this->makeUser('i');

        $cursor = DialerData::liveState($user->id, null)['cursor'];
        $this->assertFalse(DialerData::liveState($user->id, $cursor)['changed']);

        // A new subscriber (a "new lead" in the suggestions) must advance the
        // cursor too, so the suggestions stay fresh via the same poll.
        Subscriber::withoutGlobalScope('workspace')->create([
            'user_id' => $user->id,
            'type'    => 'email',
            'email'   => 'lead-' . Str::random(6) . '@example.com',
            'status'  => 'active',
        ]);

        $state = DialerData::liveState($user->id, $cursor);
        $this->assertTrue($state['changed']);
        $this->assertNotSame($cursor, $state['cursor']);
    }

    public function test_flag_toggle_advances_cursor(): void
    {
        $user = $this->makeUser('d');

        $cursor = DialerData::liveState($user->id, null)['cursor'];

        // Device B blocks a number (a spam/block flag change must sync too).
        DialerNumberFlag::create([
            'user_id'     => $user->id,
            'number_e164' => '+15557776666',
            'is_blocked'  => true,
        ]);

        $state = DialerData::liveState($user->id, $cursor);
        $this->assertTrue($state['changed']);
        $this->assertNotSame($cursor, $state['cursor']);
    }

    // ===== (2) API endpoint envelope + cursor round-trip =====

    private function asUser(User $user): self
    {
        // Real Sanctum token (see .agents/memory/sanctum-api-tests.md): the
        // TouchSessionToken middleware can't operate on a Sanctum::actingAs mock.
        $this->withToken($user->createToken('dialer-live-test')->plainTextToken);
        return $this;
    }

    public function test_api_live_endpoint_returns_envelope_and_round_trips_cursor(): void
    {
        $user = $this->makeUser('e');

        DialerLookup::create([
            'user_id'      => $user->id,
            'number_e164'  => '+15550002222',
            'looked_up_at' => now(),
        ]);

        // First poll with no cursor: changed:true + fresh lists in the {data}
        // envelope, plus the current cursor.
        $first = $this->asUser($user)->getJson('/api/v1/dialer/live');
        $first->assertOk();
        $first->assertJsonPath('data.changed', true);
        $first->assertJsonStructure([
            'data' => ['cursor', 'changed', 'favorites', 'frequent', 'recents'],
        ]);

        $cursor = $first->json('data.cursor');
        $this->assertIsString($cursor);
        $this->assertNotSame('', $cursor);

        // Second poll echoing the cursor back: nothing changed, so the endpoint
        // reports changed:false, returns the same cursor and omits the payload.
        $second = $this->asUser($user)->getJson('/api/v1/dialer/live?since=' . urlencode($cursor));
        $second->assertOk();
        $second->assertJsonPath('data.changed', false);
        $second->assertJsonPath('data.cursor', $cursor);
        $second->assertJsonMissingPath('data.favorites');
        $second->assertJsonMissingPath('data.recents');
    }

    public function test_api_live_endpoint_signals_change_when_cursor_is_stale(): void
    {
        $user = $this->makeUser('f');

        $cursor = $this->asUser($user)->getJson('/api/v1/dialer/live')->json('data.cursor');

        // Simulate a change made on the other device.
        DialerLookup::create([
            'user_id'      => $user->id,
            'number_e164'  => '+15553334444',
            'looked_up_at' => now(),
        ]);

        $resp = $this->asUser($user)->getJson('/api/v1/dialer/live?since=' . urlencode($cursor));
        $resp->assertOk();
        $resp->assertJsonPath('data.changed', true);
        $this->assertNotSame($cursor, $resp->json('data.cursor'));
        $numbers = array_column($resp->json('data.recents'), 'number_e164');
        $this->assertContains('+15553334444', $numbers);
    }

    public function test_api_live_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/v1/dialer/live')->assertUnauthorized();
    }

    // ===== (3) WEB endpoint envelope + workspace binding =====

    /**
     * Bind an active workspace in the session, mirroring what the
     * `workspace.scope` (SetActiveWorkspace) middleware resolves at request
     * time. The web dialer route is gated by `workspace.can:settings.view`,
     * so without a bound workspace the permission middleware denies the
     * request (see .agents/memory/api-workspace-scope.md). The user owns the
     * resolved workspace, so `settings.view` is granted.
     */
    private function actingAsWeb(User $user): self
    {
        $ws = app(WorkspaceContext::class)->resolve($user);
        $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        return $this;
    }

    public function test_web_live_endpoint_returns_envelope_and_round_trips_cursor(): void
    {
        $user = $this->makeUser('w');

        DialerLookup::create([
            'user_id'      => $user->id,
            'number_e164'  => '+15550005555',
            'looked_up_at' => now(),
        ]);

        // First poll (no cursor): the browser gets changed:true, the current
        // cursor and fresh lists inside the {data} envelope.
        $first = $this->actingAsWeb($user)->getJson(route('user.dialer.live'));
        $first->assertOk();
        $first->assertJsonPath('data.changed', true);
        $first->assertJsonStructure([
            'data' => ['cursor', 'changed', 'favorites', 'frequent', 'recents'],
        ]);

        $cursor = $first->json('data.cursor');
        $this->assertIsString($cursor);
        $this->assertNotSame('', $cursor);

        // Second poll echoing the cursor: nothing changed, so no payload comes
        // back — the same cursor and changed:false.
        $second = $this->actingAsWeb($user)->getJson(route('user.dialer.live', ['since' => $cursor]));
        $second->assertOk();
        $second->assertJsonPath('data.changed', false);
        $second->assertJsonPath('data.cursor', $cursor);
        $second->assertJsonMissingPath('data.favorites');
        $second->assertJsonMissingPath('data.frequent');
        $second->assertJsonMissingPath('data.recents');
    }

    public function test_web_live_endpoint_signals_new_call_from_another_device(): void
    {
        $user = $this->makeUser('x');

        $cursor = $this->actingAsWeb($user)->getJson(route('user.dialer.live'))->json('data.cursor');

        // A call logged on the other browser device advances the cursor.
        DialerLookup::create([
            'user_id'      => $user->id,
            'number_e164'  => '+15551112222',
            'looked_up_at' => now(),
        ]);

        $resp = $this->actingAsWeb($user)->getJson(route('user.dialer.live', ['since' => $cursor]));
        $resp->assertOk();
        $resp->assertJsonPath('data.changed', true);
        $this->assertNotSame($cursor, $resp->json('data.cursor'));
        $numbers = array_column($resp->json('data.recents'), 'number_e164');
        $this->assertContains('+15551112222', $numbers);
    }

    public function test_web_live_endpoint_reflects_new_favorite_and_flag(): void
    {
        $user = $this->makeUser('y');

        $cursor = $this->actingAsWeb($user)->getJson(route('user.dialer.live'))->json('data.cursor');

        // Favoriting a number on device B must sync to device A.
        DialerFavorite::create([
            'user_id'     => $user->id,
            'number_e164' => '+15553334444',
            'label'       => 'Web Fav',
            'sort_order'  => 1,
        ]);

        $afterFav = $this->actingAsWeb($user)->getJson(route('user.dialer.live', ['since' => $cursor]));
        $afterFav->assertOk();
        $afterFav->assertJsonPath('data.changed', true);
        $labels = array_column($afterFav->json('data.favorites'), 'label');
        $this->assertContains('Web Fav', $labels);

        // Blocking a number (a flag change) must advance the cursor too.
        $favCursor = $afterFav->json('data.cursor');
        DialerNumberFlag::create([
            'user_id'     => $user->id,
            'number_e164' => '+15555556666',
            'is_blocked'  => true,
        ]);

        $afterFlag = $this->actingAsWeb($user)->getJson(route('user.dialer.live', ['since' => $favCursor]));
        $afterFlag->assertOk();
        $afterFlag->assertJsonPath('data.changed', true);
        $this->assertNotSame($favCursor, $afterFlag->json('data.cursor'));
    }

    public function test_web_live_endpoint_requires_authentication(): void
    {
        $this->get(route('user.dialer.live'))->assertRedirect('/user/login');
    }
}
