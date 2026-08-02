<?php

namespace Tests\Feature;

use App\Modules\User\Models\EventBroadcast;
use App\Modules\User\Models\EventTicket;
use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Rsvp;
use App\Modules\User\Models\User;
use App\Modules\User\Services\EventBroadcastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Organizer → guest broadcast messaging: audience resolution + dedupe,
 * preference-consistent recipient filtering, sent-log persistence, and API
 * auth. Mail::fake never records Mail::raw sent through the Emailer
 * pipeline, so we assert via DB state (the broadcast log row) and the
 * service's resolved recipient list rather than mail assertions.
 */
class EventGuestBroadcastTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create()->fresh();
    }

    private function auth(User $user): void
    {
        $this->withToken($user->createToken('test')->plainTextToken);
    }

    /** Link has no factory — create directly + bind the workspace. */
    private function makeEvent(User $user, array $overrides = []): Link
    {
        $ws = $user->ownedWorkspaces()->first();
        $link = new Link(array_merge([
            'user_id'   => $user->id,
            'type'      => 'ics',
            'alias'     => 'evt' . uniqid(),
            'title'     => 'Launch Party',
            'is_active' => true,
        ], $overrides));
        $link->workspace_id = $ws->id;
        $link->save();

        IcsData::create([
            'link_id'    => $link->id,
            'event_name' => $link->title,
            'start_date' => '2030-01-01 18:00:00',
            'end_date'   => '2030-01-01 20:00:00',
            'timezone'   => 'UTC',
        ]);

        return $link->fresh('icsData');
    }

    private function rsvp(Link $link, array $attrs): Rsvp
    {
        return Rsvp::create(array_merge([
            'link_id'  => $link->id,
            'name'     => 'Guest',
            'response' => 'yes',
            'status'   => 'confirmed',
        ], $attrs));
    }

    public function test_audience_resolution_going_waitlist_all_rsvps(): void
    {
        $user = $this->makeUser();
        $link = $this->makeEvent($user);

        $this->rsvp($link, ['email' => 'going@example.com', 'response' => 'yes', 'status' => 'confirmed']);
        $this->rsvp($link, ['email' => 'maybe@example.com', 'response' => 'maybe', 'status' => 'confirmed']);
        $this->rsvp($link, ['email' => 'wait@example.com', 'response' => 'yes', 'status' => 'waitlist']);
        $this->rsvp($link, ['email' => 'gone@example.com', 'response' => 'yes', 'status' => 'cancelled']);

        $svc = app(EventBroadcastService::class);

        $going = collect($svc->recipients($link, 'going'))->pluck('email')->all();
        $this->assertSame(['going@example.com'], $going);

        $wait = collect($svc->recipients($link, 'waitlist'))->pluck('email')->all();
        $this->assertSame(['wait@example.com'], $wait);

        $all = collect($svc->recipients($link, 'all_rsvps'))->pluck('email')->sort()->values()->all();
        $this->assertSame(
            ['going@example.com', 'maybe@example.com', 'wait@example.com'],
            $all,
            'all_rsvps excludes cancelled RSVPs'
        );
    }

    public function test_ticket_holders_audience_and_dedupe_by_email(): void
    {
        $user = $this->makeUser();
        $link = $this->makeEvent($user);

        // A guest who both RSVP'd and holds a ticket (same email, mixed case).
        $this->rsvp($link, ['email' => 'Dup@Example.com', 'response' => 'yes', 'status' => 'confirmed']);
        EventTicket::create([
            'link_id'        => $link->id,
            'attendee_name'  => 'Dup Holder',
            'attendee_email' => 'dup@example.com',
            'quantity'       => 1,
            'price_cents'    => 0,
            'currency'       => 'usd',
            'code'           => EventTicket::generateCode(),
            'status'         => EventTicket::STATUS_VALID,
        ]);
        EventTicket::create([
            'link_id'        => $link->id,
            'attendee_name'  => 'Refunded',
            'attendee_email' => 'refunded@example.com',
            'quantity'       => 1,
            'price_cents'    => 0,
            'currency'       => 'usd',
            'code'           => EventTicket::generateCode(),
            'status'         => EventTicket::STATUS_REFUNDED,
        ]);

        $svc = app(EventBroadcastService::class);

        $holders = collect($svc->recipients($link, 'ticket_holders'))->pluck('email')->all();
        $this->assertSame(['dup@example.com'], $holders, 'refunded ticket excluded');

        // all_rsvps + ticket_holders union would double-count the dup email;
        // each audience individually dedupes. Here confirm the RSVP audience
        // still returns the single dup once.
        $going = collect($svc->recipients($link, 'going'))->pluck('email')->all();
        $this->assertSame(['Dup@Example.com'], $going);
    }

    public function test_recipients_without_email_are_skipped(): void
    {
        $user = $this->makeUser();
        $link = $this->makeEvent($user);

        $this->rsvp($link, ['email' => 'has@example.com', 'response' => 'yes', 'status' => 'confirmed']);
        $this->rsvp($link, ['email' => null, 'name' => 'No Email', 'response' => 'yes', 'status' => 'confirmed']);

        $svc = app(EventBroadcastService::class);
        $going = collect($svc->recipients($link, 'going'))->pluck('email')->all();
        $this->assertSame(['has@example.com'], $going);
    }

    public function test_send_persists_log_row_with_recipient_count(): void
    {
        $user = $this->makeUser();
        $link = $this->makeEvent($user);
        $this->rsvp($link, ['email' => 'a@example.com', 'response' => 'yes', 'status' => 'confirmed']);
        $this->rsvp($link, ['email' => 'b@example.com', 'response' => 'yes', 'status' => 'confirmed']);

        // Mail::fake never records the Mail::raw the Emailer pipeline emits,
        // so we assert via the persisted broadcast log (recipients_count).
        \Illuminate\Support\Facades\Mail::fake();

        $svc = app(EventBroadcastService::class);
        $broadcast = $svc->send($link, $user->id, 'going', 'Venue moved', 'New address inside.');

        $this->assertInstanceOf(EventBroadcast::class, $broadcast);
        $this->assertSame('going', $broadcast->audience);
        $this->assertSame(2, $broadcast->recipients_count);
        $this->assertDatabaseHas('event_broadcasts', [
            'link_id'          => $link->id,
            'audience'         => 'going',
            'subject'          => 'Venue moved',
            'recipients_count' => 2,
        ]);
    }

    public function test_api_requires_auth(): void
    {
        $user = $this->makeUser();
        $link = $this->makeEvent($user);

        $this->postJson("/api/v1/links/{$link->id}/broadcasts", [
            'audience' => 'all_rsvps',
            'subject'  => 'Hi',
            'message'  => 'Test',
        ])->assertUnauthorized();

        $this->getJson("/api/v1/links/{$link->id}/broadcasts")->assertUnauthorized();
    }

    public function test_api_send_and_list_for_owner(): void
    {
        $user = $this->makeUser();
        $link = $this->makeEvent($user);
        $this->rsvp($link, ['email' => 'guest@example.com', 'response' => 'yes', 'status' => 'confirmed']);
        $this->auth($user);

        \Illuminate\Support\Facades\Mail::fake();

        $resp = $this->postJson("/api/v1/links/{$link->id}/broadcasts", [
            'audience' => 'going',
            'subject'  => 'Time changed',
            'message'  => 'Now starts at 7pm.',
        ]);
        $resp->assertCreated();
        $this->assertSame(1, $resp->json('data.recipients_count'));

        $list = $this->getJson("/api/v1/links/{$link->id}/broadcasts");
        $list->assertOk();
        $this->assertSame(1, count($list->json('data.broadcasts')));
        $this->assertSame('Time changed', $list->json('data.broadcasts.0.subject'));
        $this->assertSame(1, $list->json('data.counts.going'));
    }

    public function test_api_forbidden_for_non_owner(): void
    {
        $owner = $this->makeUser();
        $link  = $this->makeEvent($owner);
        $other = $this->makeUser();
        $this->auth($other);

        $this->getJson("/api/v1/links/{$link->id}/broadcasts")->assertNotFound();
    }

    public function test_api_rejects_oversized_subject_and_message(): void
    {
        $user = $this->makeUser();
        $link = $this->makeEvent($user);
        $this->auth($user);

        $this->postJson("/api/v1/links/{$link->id}/broadcasts", [
            'audience' => 'all_rsvps',
            'subject'  => str_repeat('a', 201),
            'message'  => 'ok',
        ])->assertStatus(422)->assertJsonStructure(['error' => ['details' => ['subject']]]);

        $this->postJson("/api/v1/links/{$link->id}/broadcasts", [
            'audience' => 'all_rsvps',
            'subject'  => 'ok',
            'message'  => str_repeat('a', 5001),
        ])->assertStatus(422)->assertJsonStructure(['error' => ['details' => ['message']]]);
    }

    public function test_service_enforces_daily_cap(): void
    {
        $user = $this->makeUser();
        $link = $this->makeEvent($user);
        $this->rsvp($link, ['email' => 'g@example.com', 'response' => 'yes', 'status' => 'confirmed']);

        // Seed the cap's worth of recent broadcasts directly.
        for ($i = 0; $i < EventBroadcastService::DAILY_CAP; $i++) {
            EventBroadcast::create([
                'link_id'          => $link->id,
                'user_id'          => $user->id,
                'audience'         => 'all_rsvps',
                'subject'          => "Prior {$i}",
                'message'          => 'x',
                'recipients_count' => 1,
                'created_at'       => now()->subHours(2),
                'updated_at'       => now()->subHours(2),
            ]);
        }

        \Illuminate\Support\Facades\Mail::fake();

        $this->expectException(\App\Modules\User\Services\EventBroadcastLimitException::class);
        app(EventBroadcastService::class)->send($link, $user->id, 'going', 'One more', 'Body');
    }

    public function test_service_enforces_per_link_cooldown(): void
    {
        $user = $this->makeUser();
        $link = $this->makeEvent($user);
        $this->rsvp($link, ['email' => 'g@example.com', 'response' => 'yes', 'status' => 'confirmed']);

        // A very recent broadcast (within the cooldown window).
        EventBroadcast::create([
            'link_id'          => $link->id,
            'user_id'          => $user->id,
            'audience'         => 'going',
            'subject'          => 'Just now',
            'message'          => 'x',
            'recipients_count' => 1,
            'created_at'       => now()->subSeconds(5),
            'updated_at'       => now()->subSeconds(5),
        ]);

        \Illuminate\Support\Facades\Mail::fake();

        $this->expectException(\App\Modules\User\Services\EventBroadcastLimitException::class);
        app(EventBroadcastService::class)->send($link, $user->id, 'going', 'Too soon', 'Body');
    }

    public function test_api_returns_429_when_rate_limited_by_cooldown(): void
    {
        $user = $this->makeUser();
        $link = $this->makeEvent($user);
        $this->rsvp($link, ['email' => 'g@example.com', 'response' => 'yes', 'status' => 'confirmed']);
        $this->auth($user);

        EventBroadcast::create([
            'link_id'          => $link->id,
            'user_id'          => $user->id,
            'audience'         => 'going',
            'subject'          => 'Just now',
            'message'          => 'x',
            'recipients_count' => 1,
            'created_at'       => now()->subSeconds(5),
            'updated_at'       => now()->subSeconds(5),
        ]);

        \Illuminate\Support\Facades\Mail::fake();

        $this->postJson("/api/v1/links/{$link->id}/broadcasts", [
            'audience' => 'going',
            'subject'  => 'Too soon',
            'message'  => 'Body',
        ])->assertStatus(429);
    }
}
