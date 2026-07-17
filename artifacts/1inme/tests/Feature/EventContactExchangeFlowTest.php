<?php

namespace Tests\Feature;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\EventContactExchange;
use App\Modules\User\Models\EventDiscoverability;
use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Rsvp;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task #5009 — full round-trip coverage for the event contact-exchange
 * flow (Task #5008): requestExchange creates a pending row and notifies
 * the recipient; acceptExchange flips it to accepted, creates MUTUAL
 * Contact records on both sides, and notifies the requester.
 *
 * A regression anywhere in this chain would leave attendees seeing
 * "Connected" with no contact actually saved — exactly what these tests
 * pin down.
 */
class EventContactExchangeFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $name): User
    {
        $u = User::factory()->create(['name' => $name]);
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);

        return $u;
    }

    /** A live (started, not ended) public ics event owned by $host. */
    private function makeLiveEvent(User $host): Link
    {
        $link = Link::create([
            'user_id'    => $host->id,
            'type'       => 'ics',
            'alias'      => 'evt' . Str::random(8),
            'title'      => 'Meetup Night',
            'settings'   => [],
            'visibility' => 'public',
            'is_active'  => true,
        ]);

        IcsData::create([
            'link_id'    => $link->id,
            'event_name' => $link->title,
            'start_date' => now()->subHour()->toDateTimeString(),
            'end_date'   => now()->addHours(3)->toDateTimeString(),
            'timezone'   => 'UTC',
            'all_day'    => false,
        ]);

        return $link;
    }

    /** Confirmed "yes" RSVP so $user counts as an attendee. */
    private function rsvp(User $user, Link $link): void
    {
        Rsvp::create([
            'link_id'  => $link->id,
            'name'     => $user->name,
            'email'    => $user->email,
            'response' => 'yes',
            'status'   => 'confirmed',
        ]);
    }

    private function optIn(User $user, Link $link): void
    {
        EventDiscoverability::create([
            'user_id'    => $user->id,
            'link_id'    => $link->id,
            'expires_at' => now()->addHours(3),
        ]);
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_full_round_trip_saves_mutual_contacts_and_notifies_both_sides(): void
    {
        $host  = $this->makeUser('Holly Host');
        $alice = $this->makeUser('Alice Attendee');
        $bob   = $this->makeUser('Bob Attendee');

        $link = $this->makeLiveEvent($host);
        $this->rsvp($alice, $link);
        $this->rsvp($bob, $link);
        $this->optIn($alice, $link);
        $this->optIn($bob, $link);

        // ── Alice sends Bob an exchange request ──────────────────────
        $resp = $this->withToken($this->token($alice))
            ->postJson('/api/v1/events/' . $link->alias . '/exchange', [
                'recipient_id' => $bob->id,
            ]);
        $resp->assertStatus(201)->assertJsonPath('data.status', 'pending');
        $exchangeId = $resp->json('data.exchange_id');
        $this->flushHeaders();

        // Pending row exists, directed Alice → Bob.
        $exchange = EventContactExchange::find($exchangeId);
        $this->assertNotNull($exchange);
        $this->assertTrue($exchange->isPending());
        $this->assertSame($alice->id, (int) $exchange->requester_id);
        $this->assertSame($bob->id, (int) $exchange->recipient_id);
        $this->assertSame($link->id, (int) $exchange->link_id);

        // No contacts saved yet — request alone must not connect anyone.
        $this->assertSame(0, Contact::where('user_id', $alice->id)->count());
        $this->assertSame(0, Contact::where('user_id', $bob->id)->count());

        // Bob got the request notification.
        $reqNotif = UserNotification::where('user_id', $bob->id)
            ->where('type', 'event_exchange_request')
            ->first();
        $this->assertNotNull($reqNotif);
        $this->assertSame('Alice Attendee', $reqNotif->data['requester_name'] ?? null);
        $this->assertSame($exchangeId, $reqNotif->data['exchange_id'] ?? null);

        // ── Bob accepts ──────────────────────────────────────────────
        $accept = $this->withToken($this->token($bob))
            ->postJson('/api/v1/me/contact-exchanges/' . $exchangeId . '/accept');
        $accept->assertOk()->assertJsonPath('data.status', 'accepted');
        $this->flushHeaders();

        $exchange->refresh();
        $this->assertTrue($exchange->isAccepted());
        $this->assertNotNull($exchange->accepted_at);

        // Mutual contacts: each user appears in the OTHER's address book.
        $bobInAlices = Contact::where('user_id', $alice->id)
            ->where('biolink_user_id', $bob->id)
            ->first();
        $aliceInBobs = Contact::where('user_id', $bob->id)
            ->where('biolink_user_id', $alice->id)
            ->first();

        $this->assertNotNull($bobInAlices, 'Bob was not saved into Alice\'s contacts');
        $this->assertNotNull($aliceInBobs, 'Alice was not saved into Bob\'s contacts');
        $this->assertSame('Bob Attendee', $bobInAlices->display_name);
        $this->assertSame('Alice Attendee', $aliceInBobs->display_name);

        // Each contact records the event as its source and carries the
        // counterpart's email.
        foreach ([[$bobInAlices, $bob], [$aliceInBobs, $alice]] as [$contact, $other]) {
            $sources = collect((array) $contact->sources);
            $this->assertTrue(
                $sources->contains(fn ($s) => ($s['kind'] ?? null) === 'event_exchange'
                    && (int) ($s['event_id'] ?? 0) === $link->id),
                'Contact is missing the event_exchange source entry',
            );
            $this->assertTrue(
                $contact->emails()->whereRaw('LOWER(value) = ?', [strtolower($other->email)])->exists(),
                'Contact is missing the counterpart\'s email',
            );
        }

        // Alice (the requester) got the accepted notification.
        $accNotif = UserNotification::where('user_id', $alice->id)
            ->where('type', 'event_exchange_accepted')
            ->first();
        $this->assertNotNull($accNotif);
        $this->assertSame('Bob Attendee', $accNotif->data['acceptor_name'] ?? null);
    }

    public function test_accept_is_recipient_only_and_not_repeatable(): void
    {
        $host  = $this->makeUser('Hank Host');
        $alice = $this->makeUser('Ana Attendee');
        $bob   = $this->makeUser('Ben Attendee');

        $link = $this->makeLiveEvent($host);
        $this->rsvp($alice, $link);
        $this->rsvp($bob, $link);
        $this->optIn($alice, $link);
        $this->optIn($bob, $link);

        $exchange = EventContactExchange::create([
            'requester_id' => $alice->id,
            'recipient_id' => $bob->id,
            'link_id'      => $link->id,
            'status'       => EventContactExchange::STATUS_PENDING,
        ]);

        // The requester cannot accept their own request.
        $this->withToken($this->token($alice))
            ->postJson('/api/v1/me/contact-exchanges/' . $exchange->id . '/accept')
            ->assertStatus(403);
        $this->flushHeaders();
        $this->assertTrue($exchange->fresh()->isPending());

        // The recipient accepts once…
        $this->withToken($this->token($bob))
            ->postJson('/api/v1/me/contact-exchanges/' . $exchange->id . '/accept')
            ->assertOk();

        // …and a second accept is rejected as already resolved, without
        // duplicating contacts.
        $this->postJson('/api/v1/me/contact-exchanges/' . $exchange->id . '/accept')
            ->assertStatus(409);
        $this->flushHeaders();

        $this->assertSame(1, Contact::where('user_id', $alice->id)->where('biolink_user_id', $bob->id)->count());
        $this->assertSame(1, Contact::where('user_id', $bob->id)->where('biolink_user_id', $alice->id)->count());
    }

    // ─── Task #5042: decline an exchange request ──────────────────────

    public function test_recipient_can_decline_a_pending_request_silently(): void
    {
        $host  = $this->makeUser('Hix Host');
        $alice = $this->makeUser('Ash Attendee');
        $bob   = $this->makeUser('Bex Attendee');

        $link = $this->makeLiveEvent($host);
        $this->rsvp($alice, $link);
        $this->rsvp($bob, $link);

        $exchange = EventContactExchange::create([
            'requester_id' => $alice->id,
            'recipient_id' => $bob->id,
            'link_id'      => $link->id,
            'status'       => EventContactExchange::STATUS_PENDING,
        ]);

        $this->withToken($this->token($bob))
            ->postJson('/api/v1/me/contact-exchanges/' . $exchange->id . '/decline')
            ->assertOk()
            ->assertJsonPath('data.status', 'declined');
        $this->flushHeaders();

        $exchange->refresh();
        $this->assertSame(EventContactExchange::STATUS_DECLINED, $exchange->status);
        $this->assertNull($exchange->accepted_at);

        // No contacts were created on either side.
        $this->assertSame(0, Contact::where('user_id', $alice->id)->where('biolink_user_id', $bob->id)->count());
        $this->assertSame(0, Contact::where('user_id', $bob->id)->where('biolink_user_id', $alice->id)->count());

        // Declines are silent — the requester receives no notification.
        $this->assertSame(
            0,
            \App\Modules\User\Models\UserNotification::where('user_id', $alice->id)
                ->whereIn('type', ['event_exchange_declined', 'event_exchange_accepted'])
                ->count(),
        );
    }

    public function test_decline_is_recipient_only_and_resolved_requests_conflict(): void
    {
        $host  = $this->makeUser('Hob Host');
        $alice = $this->makeUser('Ala Attendee');
        $bob   = $this->makeUser('Bud Attendee');

        $link = $this->makeLiveEvent($host);
        $this->rsvp($alice, $link);
        $this->rsvp($bob, $link);

        $exchange = EventContactExchange::create([
            'requester_id' => $alice->id,
            'recipient_id' => $bob->id,
            'link_id'      => $link->id,
            'status'       => EventContactExchange::STATUS_PENDING,
        ]);

        // The requester cannot decline their own request.
        $this->withToken($this->token($alice))
            ->postJson('/api/v1/me/contact-exchanges/' . $exchange->id . '/decline')
            ->assertStatus(403);
        $this->flushHeaders();
        $this->assertTrue($exchange->fresh()->isPending());

        // A stranger cannot decline it either.
        $carl = $this->makeUser('Cal Attendee');
        $this->withToken($this->token($carl))
            ->postJson('/api/v1/me/contact-exchanges/' . $exchange->id . '/decline')
            ->assertStatus(403);
        $this->flushHeaders();

        // Unknown id → 404.
        $this->withToken($this->token($bob))
            ->postJson('/api/v1/me/contact-exchanges/999999/decline')
            ->assertStatus(404);

        // The recipient declines once…
        $this->postJson('/api/v1/me/contact-exchanges/' . $exchange->id . '/decline')
            ->assertOk();

        // …a second decline is 409 already_resolved…
        $this->postJson('/api/v1/me/contact-exchanges/' . $exchange->id . '/decline')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'already_resolved');

        // …and it can no longer be accepted.
        $this->postJson('/api/v1/me/contact-exchanges/' . $exchange->id . '/accept')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'already_resolved');
        $this->flushHeaders();

        $this->assertSame(EventContactExchange::STATUS_DECLINED, $exchange->fresh()->status);
    }

    public function test_decline_is_blocked_on_an_already_accepted_request(): void
    {
        $host  = $this->makeUser('Han Host');
        $alice = $this->makeUser('Aya Attendee');
        $bob   = $this->makeUser('Boe Attendee');

        $link = $this->makeLiveEvent($host);
        $this->rsvp($alice, $link);
        $this->rsvp($bob, $link);

        $exchange = EventContactExchange::create([
            'requester_id' => $alice->id,
            'recipient_id' => $bob->id,
            'link_id'      => $link->id,
            'status'       => EventContactExchange::STATUS_PENDING,
        ]);

        $token = $this->token($bob);
        $this->withToken($token)
            ->postJson('/api/v1/me/contact-exchanges/' . $exchange->id . '/accept')
            ->assertOk();

        $this->postJson('/api/v1/me/contact-exchanges/' . $exchange->id . '/decline')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'already_resolved');
        $this->flushHeaders();

        // Still accepted, and the mutual contacts survive.
        $this->assertSame(EventContactExchange::STATUS_ACCEPTED, $exchange->fresh()->status);
        $this->assertSame(1, Contact::where('user_id', $alice->id)->where('biolink_user_id', $bob->id)->count());
        $this->assertSame(1, Contact::where('user_id', $bob->id)->where('biolink_user_id', $alice->id)->count());
    }

    /** An event that already ended (started and finished in the past). */
    private function makeEndedEvent(User $host, int $endedHoursAgo = 2): Link
    {
        $link = Link::create([
            'user_id'    => $host->id,
            'type'       => 'ics',
            'alias'      => 'evt' . Str::random(8),
            'title'      => 'Finished Meetup',
            'settings'   => [],
            'visibility' => 'public',
            'is_active'  => true,
        ]);

        IcsData::create([
            'link_id'    => $link->id,
            'event_name' => $link->title,
            'start_date' => now()->subHours($endedHoursAgo + 3)->toDateTimeString(),
            'end_date'   => now()->subHours($endedHoursAgo)->toDateTimeString(),
            'timezone'   => 'UTC',
            'all_day'    => false,
        ]);

        return $link;
    }

    // ─── Task #5033: accept window after event end ────────────────────

    public function test_accept_is_blocked_after_event_grace_window(): void
    {
        $host  = $this->makeUser('Hugo Host');
        $alice = $this->makeUser('Amy Attendee');
        $bob   = $this->makeUser('Bo Attendee');

        // Event ended well past the 24h grace window.
        $link = $this->makeEndedEvent($host, endedHoursAgo: 30);
        $this->rsvp($alice, $link);
        $this->rsvp($bob, $link);

        $exchange = EventContactExchange::create([
            'requester_id' => $alice->id,
            'recipient_id' => $bob->id,
            'link_id'      => $link->id,
            'status'       => EventContactExchange::STATUS_PENDING,
        ]);

        $this->withToken($this->token($bob))
            ->postJson('/api/v1/me/contact-exchanges/' . $exchange->id . '/accept')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'event_not_live');
        $this->flushHeaders();

        // Still pending; no contacts were created on either side.
        $this->assertTrue($exchange->fresh()->isPending());
        $this->assertSame(0, Contact::where('user_id', $alice->id)->where('biolink_user_id', $bob->id)->count());
        $this->assertSame(0, Contact::where('user_id', $bob->id)->where('biolink_user_id', $alice->id)->count());
    }

    public function test_accept_is_allowed_within_grace_window_after_event_end(): void
    {
        $host  = $this->makeUser('Hetty Host');
        $alice = $this->makeUser('Ava Attendee');
        $bob   = $this->makeUser('Burt Attendee');

        // Event ended 2 hours ago — inside the 24h grace window.
        $link = $this->makeEndedEvent($host, endedHoursAgo: 2);
        $this->rsvp($alice, $link);
        $this->rsvp($bob, $link);

        $exchange = EventContactExchange::create([
            'requester_id' => $alice->id,
            'recipient_id' => $bob->id,
            'link_id'      => $link->id,
            'status'       => EventContactExchange::STATUS_PENDING,
        ]);

        $this->withToken($this->token($bob))
            ->postJson('/api/v1/me/contact-exchanges/' . $exchange->id . '/accept')
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');
        $this->flushHeaders();

        $this->assertSame(1, Contact::where('user_id', $alice->id)->where('biolink_user_id', $bob->id)->count());
        $this->assertSame(1, Contact::where('user_id', $bob->id)->where('biolink_user_id', $alice->id)->count());
    }

    // ─── Task #5013: privacy gates after event end / opt-in expiry ────

    public function test_ended_event_blocks_people_list_and_exchange_requests(): void
    {
        $host  = $this->makeUser('Hilda Host');
        $alice = $this->makeUser('Ada Attendee');
        $bob   = $this->makeUser('Bill Attendee');

        $link = $this->makeEndedEvent($host);
        $this->rsvp($alice, $link);
        $this->rsvp($bob, $link);

        // Simulate rows created while the event was still live — even with
        // an opt-in still on the books, an ended event must gate everything.
        EventDiscoverability::create([
            'user_id'    => $bob->id,
            'link_id'    => $link->id,
            'expires_at' => now()->addHour(),
        ]);

        $token = $this->token($alice);

        $this->withToken($token)
            ->getJson('/api/v1/events/' . $link->alias . '/people')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'event_not_live');

        $this->withToken($token)
            ->postJson('/api/v1/events/' . $link->alias . '/exchange', ['recipient_id' => $bob->id])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'event_not_live');
        $this->flushHeaders();

        // Nothing was created by the blocked request.
        $this->assertSame(0, EventContactExchange::where('link_id', $link->id)->count());
    }

    public function test_expired_opt_in_is_excluded_from_people_list(): void
    {
        $host  = $this->makeUser('Hope Host');
        $alice = $this->makeUser('Abby Attendee');
        $bob   = $this->makeUser('Brad Attendee');
        $carol = $this->makeUser('Cara Attendee');

        $link = $this->makeLiveEvent($host);
        $this->rsvp($alice, $link);
        $this->rsvp($bob, $link);
        $this->rsvp($carol, $link);

        // Bob's opt-in expired; Carol's is still active.
        EventDiscoverability::create([
            'user_id'    => $bob->id,
            'link_id'    => $link->id,
            'expires_at' => now()->subMinute(),
        ]);
        $this->optIn($carol, $link);

        $resp = $this->withToken($this->token($alice))
            ->getJson('/api/v1/events/' . $link->alias . '/people');
        $this->flushHeaders();

        $resp->assertOk()->assertJsonPath('data.total', 1);
        $ids = collect($resp->json('data.items'))->pluck('user.id')->all();
        $this->assertSame([$carol->id], $ids);
        $this->assertNotContains($bob->id, $ids, 'Expired opt-in leaked into the People list');
    }

    public function test_expired_opt_in_blocks_new_exchange_requests(): void
    {
        $host  = $this->makeUser('Hugh Host');
        $alice = $this->makeUser('Anne Attendee');
        $bob   = $this->makeUser('Bert Attendee');

        $link = $this->makeLiveEvent($host);
        $this->rsvp($alice, $link);
        $this->rsvp($bob, $link);

        EventDiscoverability::create([
            'user_id'    => $bob->id,
            'link_id'    => $link->id,
            'expires_at' => now()->subMinute(),
        ]);

        $this->withToken($this->token($alice))
            ->postJson('/api/v1/events/' . $link->alias . '/exchange', ['recipient_id' => $bob->id])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'not_discoverable');
        $this->flushHeaders();

        $this->assertSame(0, EventContactExchange::where('link_id', $link->id)->count());
    }

    public function test_non_attendee_gets_403_on_people_list_and_exchange(): void
    {
        $host    = $this->makeUser('Hal Host');
        $alice   = $this->makeUser('Avis Attendee');
        $lurker  = $this->makeUser('Lou Lurker'); // no RSVP, no ticket

        $link = $this->makeLiveEvent($host);
        $this->rsvp($alice, $link);
        $this->optIn($alice, $link);

        $token = $this->token($lurker);

        $this->withToken($token)
            ->getJson('/api/v1/events/' . $link->alias . '/people')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'not_attendee');

        $this->withToken($token)
            ->postJson('/api/v1/events/' . $link->alias . '/exchange', ['recipient_id' => $alice->id])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'not_attendee');
        $this->flushHeaders();

        $this->assertSame(0, EventContactExchange::where('link_id', $link->id)->count());
    }

    public function test_reverse_request_auto_accepts_pending_exchange(): void
    {
        $host  = $this->makeUser('Hana Host');
        $alice = $this->makeUser('Amy Attendee');
        $bob   = $this->makeUser('Bo Attendee');

        $link = $this->makeLiveEvent($host);
        $this->rsvp($alice, $link);
        $this->rsvp($bob, $link);
        $this->optIn($alice, $link);
        $this->optIn($bob, $link);

        $this->withToken($this->token($alice))
            ->postJson('/api/v1/events/' . $link->alias . '/exchange', ['recipient_id' => $bob->id])
            ->assertStatus(201);
        $this->flushHeaders();

        // Bob "requesting back" resolves the pending request instead of
        // creating a duplicate row.
        $this->withToken($this->token($bob))
            ->postJson('/api/v1/events/' . $link->alias . '/exchange', ['recipient_id' => $alice->id])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');
        $this->flushHeaders();

        $this->assertSame(1, EventContactExchange::where('link_id', $link->id)->count());
        $this->assertNotNull(Contact::where('user_id', $alice->id)->where('biolink_user_id', $bob->id)->first());
        $this->assertNotNull(Contact::where('user_id', $bob->id)->where('biolink_user_id', $alice->id)->first());
    }

    /**
     * Seed $count sent exchanges from $requester at $link, each to a distinct
     * throwaway recipient (the unique index forbids duplicate pairs), stamped
     * with the given created_at.
     */
    private function seedSentExchanges(User $requester, Link $link, int $count, \DateTimeInterface $createdAt): void
    {
        for ($i = 0; $i < $count; $i++) {
            $recipient = User::factory()->create();
            $exchange  = EventContactExchange::create([
                'requester_id' => $requester->id,
                'recipient_id' => $recipient->id,
                'link_id'      => $link->id,
                'status'       => EventContactExchange::STATUS_PENDING,
            ]);
            // Bypass model timestamp handling so the stamp sticks exactly.
            EventContactExchange::where('id', $exchange->id)
                ->update(['created_at' => $createdAt]);
        }
    }

    public function test_eleventh_exchange_request_within_the_hour_is_rate_limited(): void
    {
        $host  = $this->makeUser('Rate Host');
        $alice = $this->makeUser('Rapid Alice');
        $bob   = $this->makeUser('Target Bob');

        $link = $this->makeLiveEvent($host);
        $this->rsvp($alice, $link);
        $this->rsvp($bob, $link);
        $this->optIn($alice, $link);
        $this->optIn($bob, $link);

        // 10 requests already sent within the last hour → cap reached.
        $this->seedSentExchanges($alice, $link, 10, now()->subMinutes(30));

        $resp = $this->withToken($this->token($alice))
            ->postJson('/api/v1/events/' . $link->alias . '/exchange', [
                'recipient_id' => $bob->id,
            ]);
        $this->flushHeaders();

        $resp->assertStatus(429)->assertJsonPath('error.code', 'rate_limited');

        // No 11th row was created.
        $this->assertSame(
            0,
            EventContactExchange::where('requester_id', $alice->id)
                ->where('recipient_id', $bob->id)
                ->where('link_id', $link->id)
                ->count(),
        );
    }

    public function test_requests_older_than_an_hour_do_not_count_toward_the_cap(): void
    {
        $host  = $this->makeUser('Old Host');
        $alice = $this->makeUser('Patient Alice');
        $bob   = $this->makeUser('Fresh Bob');

        $link = $this->makeLiveEvent($host);
        $this->rsvp($alice, $link);
        $this->rsvp($bob, $link);
        $this->optIn($alice, $link);
        $this->optIn($bob, $link);

        // 10 requests sent two hours ago — outside the rolling window.
        $this->seedSentExchanges($alice, $link, 10, now()->subHours(2));

        $resp = $this->withToken($this->token($alice))
            ->postJson('/api/v1/events/' . $link->alias . '/exchange', [
                'recipient_id' => $bob->id,
            ]);
        $this->flushHeaders();

        $resp->assertStatus(201)->assertJsonPath('data.status', 'pending');
    }
}
