<?php

namespace Tests\Feature;

use App\Modules\User\Models\DialerFavorite;
use App\Modules\User\Models\DialerLookup;
use App\Modules\User\Models\DialerNumberFlag;
use App\Modules\User\Models\User;
use App\Modules\User\Support\DialerData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
 */
class DialerLiveSyncTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $prefix = 'u'): User
    {
        return User::create([
            'name'     => $prefix . Str::random(4),
            'email'    => $prefix . '-' . Str::random(8) . '@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
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
}
