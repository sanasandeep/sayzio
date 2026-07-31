<?php

namespace Tests\Feature;

use App\Modules\Common\Services\ExpoPushNotifier;
use App\Modules\User\Models\DevicePushToken;
use App\Modules\User\Models\DialerCallEvent;
use App\Modules\User\Models\DialerDevice;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage for the desktop ⇄ phone call-handoff endpoints backing the
 * Zio Browser Dialer pane (task #5780):
 *
 *   POST /api/v1/dialer/handoff/call   — click-to-call push to the phone
 *   POST /api/v1/dialer/call-events    — phone reports ringing/answered/ended
 *   GET  /api/v1/dialer/call-events    — desktop short-poll with `since` cursor
 *
 * Assertions:
 *   (1) All three endpoints reject unauthenticated requests (401).
 *   (2) requestCall with no registered phone → 404 `no_dialer_device`.
 *   (3) requestCall with a registered phone sends an Expo push carrying
 *       {type: dialer.call_request, number, name} and returns requested=true.
 *   (4) requestCall validates the number format (422).
 *   (5) reportCallEvent stores the event and rejects bad statuses (422).
 *   (6) reportCallEvent clamps bogus phone clocks into a sane window.
 *   (7) callEvents returns only the caller's events, oldest-first, with a
 *       cursor, and honors the `since` cursor.
 *   (8) Old events (outside the 60-min window) are not returned.
 */
class DialerHandoffTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $prefix = 'u'): User
    {
        return User::factory()->create([
            'name'  => $prefix . Str::random(4),
            'email' => $prefix . '-' . Str::random(8) . '@example.com',
        ]);
    }

    /** Real Sanctum Bearer token (Sanctum::actingAs breaks TouchSessionToken). */
    private function tokenFor(User $user): string
    {
        return $user->createToken('handoff-test')->plainTextToken;
    }

    private function registerPhone(User $user): void
    {
        DevicePushToken::create([
            'user_id' => $user->id,
            'token'   => 'ExponentPushToken[' . Str::random(20) . ']',
        ]);
    }

    public function test_endpoints_require_authentication(): void
    {
        $this->postJson('/api/v1/dialer/handoff/call', ['number' => '+15551234567'])
            ->assertStatus(401);
        $this->postJson('/api/v1/dialer/call-events', ['status' => 'ringing', 'number' => '+15551234567'])
            ->assertStatus(401);
        $this->getJson('/api/v1/dialer/call-events')
            ->assertStatus(401);
    }

    public function test_handoff_status_reports_device_linked_and_push_available(): void
    {
        $this->getJson('/api/v1/dialer/handoff/status')->assertStatus(401);

        $user = $this->makeUser();
        $token = $this->tokenFor($user);

        // Nothing registered at all.
        $this->withToken($token)
            ->getJson('/api/v1/dialer/handoff/status')
            ->assertStatus(200)
            ->assertJsonPath('data.device_linked', false)
            ->assertJsonPath('data.push_available', false);

        // Device record only (app installed, notifications denied):
        // linked, but push unavailable — the desktop shows the
        // "enable notifications" hint, NOT the download promo.
        DialerDevice::create([
            'user_id'    => $user->id,
            'device_key' => 'device-key-' . Str::random(12),
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/dialer/handoff/status')
            ->assertStatus(200)
            ->assertJsonPath('data.device_linked', true)
            ->assertJsonPath('data.push_available', false);

        // Push token added → both true.
        $this->registerPhone($user);

        $this->withToken($token)
            ->getJson('/api/v1/dialer/handoff/status')
            ->assertStatus(200)
            ->assertJsonPath('data.device_linked', true)
            ->assertJsonPath('data.push_available', true);
    }

    public function test_handoff_status_push_token_alone_counts_as_linked(): void
    {
        // Legacy installs registered a push token but never a device
        // record — they must still count as linked.
        $user = $this->makeUser();
        $this->registerPhone($user);

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/dialer/handoff/status')
            ->assertStatus(200)
            ->assertJsonPath('data.device_linked', true)
            ->assertJsonPath('data.push_available', true);
    }

    public function test_register_device_upserts_and_heartbeats(): void
    {
        $this->postJson('/api/v1/dialer/device', ['device_key' => 'abcdef123456'])
            ->assertStatus(401);

        $user  = $this->makeUser();
        $token = $this->tokenFor($user);

        $this->withToken($token)
            ->postJson('/api/v1/dialer/device', [
                'device_key'  => 'my-device-key-01',
                'platform'    => 'android',
                'device_name' => 'Pixel 9',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.registered', true);

        $this->assertDatabaseHas('dialer_devices', [
            'user_id'     => $user->id,
            'device_key'  => 'my-device-key-01',
            'platform'    => 'android',
            'device_name' => 'Pixel 9',
        ]);

        // Re-registering the same key heartbeats the existing row, never
        // duplicates it.
        $this->withToken($token)
            ->postJson('/api/v1/dialer/device', [
                'device_key' => 'my-device-key-01',
                'platform'   => 'android',
            ])
            ->assertStatus(200);

        $this->assertSame(1, DialerDevice::where('user_id', $user->id)->count());

        // Bad keys are rejected: too short, or disallowed characters.
        $this->withToken($token)
            ->postJson('/api/v1/dialer/device', ['device_key' => 'short'])
            ->assertStatus(422);
        $this->withToken($token)
            ->postJson('/api/v1/dialer/device', ['device_key' => 'bad key with spaces!'])
            ->assertStatus(422);
    }

    public function test_unregister_device_removes_only_own_row(): void
    {
        $user  = $this->makeUser();
        $other = $this->makeUser('o');

        DialerDevice::create(['user_id' => $user->id,  'device_key' => 'shared-key-123']);
        DialerDevice::create(['user_id' => $other->id, 'device_key' => 'shared-key-123']);

        $this->withToken($this->tokenFor($user))
            ->deleteJson('/api/v1/dialer/device', ['device_key' => 'shared-key-123'])
            ->assertStatus(200)
            ->assertJsonPath('data.unregistered', true);

        $this->assertDatabaseMissing('dialer_devices', [
            'user_id'    => $user->id,
            'device_key' => 'shared-key-123',
        ]);
        // The other user's identically-keyed row is untouched.
        $this->assertDatabaseHas('dialer_devices', [
            'user_id'    => $other->id,
            'device_key' => 'shared-key-123',
        ]);
    }

    public function test_request_call_with_device_but_no_push_returns_no_push_token(): void
    {
        $user = $this->makeUser();
        DialerDevice::create([
            'user_id'    => $user->id,
            'device_key' => 'pushless-device-1',
        ]);

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/dialer/handoff/call', ['number' => '+15551234567'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'no_push_token');
    }

    public function test_request_call_without_phone_returns_no_dialer_device(): void
    {
        $user = $this->makeUser();

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/dialer/handoff/call', ['number' => '+15551234567'])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'no_dialer_device');
    }

    public function test_request_call_pushes_call_request_to_phone(): void
    {
        $user = $this->makeUser();
        $this->registerPhone($user);

        $captured = [];
        $this->mock(ExpoPushNotifier::class, function ($mock) use (&$captured, $user) {
            $mock->shouldReceive('sendToUser')
                ->once()
                ->withArgs(function ($userId, $title, $body, $data) use (&$captured, $user) {
                    $captured = $data;
                    return $userId === $user->id
                        && ($data['type'] ?? null) === 'dialer.call_request';
                })
                ->andReturn(1);
        });

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/dialer/handoff/call', [
                'number' => '+1 555 123-4567',
                'name'   => 'Ada Lovelace',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.requested', true)
            ->assertJsonPath('data.sent', 1);

        $this->assertSame('+1 555 123-4567', $captured['number']);
        $this->assertSame('Ada Lovelace', $captured['name']);
    }

    public function test_request_call_validates_number(): void
    {
        $user = $this->makeUser();
        $this->registerPhone($user);

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/dialer/handoff/call', ['number' => 'not-a-number'])
            ->assertStatus(422);
    }

    public function test_report_call_event_stores_and_validates(): void
    {
        $user  = $this->makeUser();
        $token = $this->tokenFor($user);

        $this->withToken($token)
            ->postJson('/api/v1/dialer/call-events', [
                'status'      => 'ringing',
                'number'      => '+15559876543',
                'caller_name' => 'Bob',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.recorded', true);

        $this->assertDatabaseHas('dialer_call_events', [
            'user_id'     => $user->id,
            'status'      => 'ringing',
            'number'      => '+15559876543',
            'caller_name' => 'Bob',
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/dialer/call-events', [
                'status' => 'exploded',
                'number' => '+15559876543',
            ])
            ->assertStatus(422);
    }

    public function test_report_call_event_clamps_bogus_clock(): void
    {
        $user = $this->makeUser();

        $id = $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/dialer/call-events', [
                'status'         => 'ringing',
                'number'         => '+15550001111',
                'occurred_at_ms' => 1000, // 1970 — obviously bogus
            ])
            ->assertStatus(200)
            ->json('data.id');

        $event = DialerCallEvent::findOrFail($id);
        $this->assertTrue($event->occurred_at->gt(now()->subMinutes(5)));
    }

    public function test_call_events_are_owner_scoped_with_cursor(): void
    {
        $user  = $this->makeUser();
        $other = $this->makeUser('o');

        $mine = [];
        foreach (['ringing', 'answered', 'ended'] as $status) {
            $mine[] = DialerCallEvent::create([
                'user_id'     => $user->id,
                'status'      => $status,
                'number'      => '+15551230000',
                'caller_name' => 'Carol',
                'occurred_at' => now(),
            ]);
        }
        DialerCallEvent::create([
            'user_id'     => $other->id,
            'status'      => 'ringing',
            'number'      => '+15559990000',
            'occurred_at' => now(),
        ]);

        $token = $this->tokenFor($user);

        $res = $this->withToken($token)
            ->getJson('/api/v1/dialer/call-events')
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(3, $res['events']);
        $this->assertSame(
            array_map(fn ($e) => $e->id, $mine),
            array_column($res['events'], 'id'),
        );
        $this->assertSame('+15551230000', $res['events'][0]['number']);
        $this->assertSame($mine[2]->id, $res['cursor']);

        // Cursor: only events after `since` come back.
        $res2 = $this->withToken($token)
            ->getJson('/api/v1/dialer/call-events?since=' . $mine[0]->id)
            ->assertStatus(200)
            ->json('data');
        $this->assertCount(2, $res2['events']);
        $this->assertSame($mine[1]->id, $res2['events'][0]['id']);

        // Cursor at tip: nothing new, cursor echoed back.
        $res3 = $this->withToken($token)
            ->getJson('/api/v1/dialer/call-events?since=' . $mine[2]->id)
            ->assertStatus(200)
            ->json('data');
        $this->assertCount(0, $res3['events']);
        $this->assertSame($mine[2]->id, $res3['cursor']);
    }

    public function test_stale_events_are_excluded(): void
    {
        $user = $this->makeUser();

        DialerCallEvent::create([
            'user_id'     => $user->id,
            'status'      => 'ringing',
            'number'      => '+15550002222',
            'occurred_at' => now()->subHours(2),
        ]);
        $fresh = DialerCallEvent::create([
            'user_id'     => $user->id,
            'status'      => 'ringing',
            'number'      => '+15550003333',
            'occurred_at' => now(),
        ]);

        $res = $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/dialer/call-events')
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(1, $res['events']);
        $this->assertSame($fresh->id, $res['events'][0]['id']);
    }
}
