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
}
