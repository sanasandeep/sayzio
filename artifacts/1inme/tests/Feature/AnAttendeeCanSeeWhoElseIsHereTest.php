<?php

namespace Tests\Feature;

use App\Modules\User\Models\EventDiscoverability;
use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Rsvp;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The contact-swap feature has been complete for a while and unreachable
 * from a browser. An attendee on a phone could see their own swaps
 * (Task #5052) but had no way to see who else was there to ask, so the
 * only route to the other half was the mobile app.
 *
 * These routes point at the API controller rather than a copy of it. The
 * rules are not incidental -- the event must be live, the viewer must
 * actually hold a seat, and only people who opted in are listed -- and a
 * second implementation of that would drift from the first. So what these
 * tests check is that the web surface enforces the same rules, not that
 * the rules are re-stated correctly somewhere new.
 */
class AnAttendeeCanSeeWhoElseIsHereTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name = 'Someone'): User
    {
        return User::factory()->create(['name' => $name, 'onboarded_at' => now()->subMonth()])->fresh();
    }

    private function liveEvent(User $host): Link
    {
        return $this->event($host, now()->subHour(), now()->addHours(2));
    }

    private function event(User $host, $start, $end): Link
    {
        $link = new Link([
            'user_id'   => $host->id,
            'type'      => 'ics',
            'alias'     => 'evt' . uniqid(),
            'title'     => 'Founder Sessions',
            'is_active' => true,
        ]);
        $link->workspace_id = $host->ownedWorkspaces()->first()->id;
        $link->save();

        IcsData::create([
            'link_id'    => $link->id,
            'event_name' => 'Founder Sessions',
            'start_date' => $start->toDateTimeString(),
            'end_date'   => $end->toDateTimeString(),
            'timezone'   => 'UTC',
        ]);

        return $link->fresh();
    }

    private function attend(User $user, Link $link): void
    {
        Rsvp::create([
            'link_id'  => $link->id,
            'user_id'  => $user->id,
            'name'     => $user->name,
            'email'    => $user->email,
            'response' => 'yes',
            'status'   => 'confirmed',
        ]);
    }

    private function beDiscoverable(User $user, Link $link): void
    {
        EventDiscoverability::create([
            'user_id'    => $user->id,
            'link_id'    => $link->id,
            'expires_at' => now()->addHours(6),
        ]);
    }

    public function test_an_attendee_sees_someone_who_opted_in(): void
    {
        $host = $this->user('Host');
        $link = $this->liveEvent($host);

        $me = $this->user('Priya');
        $them = $this->user('Ravi');
        $this->attend($me, $link);
        $this->attend($them, $link);
        $this->beDiscoverable($them, $link);

        $response = $this->actingAs($me)->getJson(route('user.event-people.index', $link->alias));

        $response->assertOk();
        $response->assertJsonPath('data.total', 1);
        $response->assertJsonPath('data.items.0.user.name', 'Ravi');
    }

    public function test_someone_who_did_not_opt_in_is_not_listed(): void
    {
        // The whole model rests on this: you are invisible until you say
        // otherwise, even to people in the same room.
        $host = $this->user('Host');
        $link = $this->liveEvent($host);

        $me = $this->user('Priya');
        $them = $this->user('Ravi');
        $this->attend($me, $link);
        $this->attend($them, $link);

        $response = $this->actingAs($me)->getJson(route('user.event-people.index', $link->alias));

        $response->assertOk();
        $response->assertJsonPath('data.total', 0);
    }

    public function test_i_am_not_in_my_own_list(): void
    {
        $host = $this->user('Host');
        $link = $this->liveEvent($host);

        $me = $this->user('Priya');
        $this->attend($me, $link);
        $this->beDiscoverable($me, $link);

        $response = $this->actingAs($me)->getJson(route('user.event-people.index', $link->alias));

        $response->assertOk();
        $response->assertJsonPath('data.total', 0);
        $response->assertJsonPath('data.my_discoverable', true);
    }

    public function test_someone_with_no_seat_cannot_browse_the_room(): void
    {
        $host = $this->user('Host');
        $link = $this->liveEvent($host);

        $them = $this->user('Ravi');
        $this->attend($them, $link);
        $this->beDiscoverable($them, $link);

        $stranger = $this->user('Passer-by');

        $response = $this->actingAs($stranger)->getJson(route('user.event-people.index', $link->alias));

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'not_attendee');
    }

    public function test_the_room_is_closed_before_the_event_starts(): void
    {
        $host = $this->user('Host');
        $link = $this->event($host, now()->addDay(), now()->addDay()->addHours(2));

        $me = $this->user('Priya');
        $this->attend($me, $link);

        $response = $this->actingAs($me)->getJson(route('user.event-people.index', $link->alias));

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'event_not_live');
    }

    public function test_a_signed_out_visitor_gets_nothing(): void
    {
        $host = $this->user('Host');
        $link = $this->liveEvent($host);

        $this->getJson(route('user.event-people.index', $link->alias))->assertStatus(401);
    }

    public function test_an_attendee_can_ask_to_swap_from_the_web(): void
    {
        $host = $this->user('Host');
        $link = $this->liveEvent($host);

        $me = $this->user('Priya');
        $them = $this->user('Ravi');
        $this->attend($me, $link);
        $this->attend($them, $link);
        $this->beDiscoverable($them, $link);

        $response = $this->actingAs($me)->postJson(
            route('user.event-people.request', $link->alias),
            ['recipient_id' => $them->id]
        );

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'pending');

        // And the list now says so rather than offering the button again.
        $this->actingAs($me)
            ->getJson(route('user.event-people.index', $link->alias))
            ->assertJsonPath('data.items.0.exchange_status', 'pending')
            ->assertJsonPath('data.items.0.sent_by_me', true);
    }

    public function test_asking_someone_who_never_opted_in_is_refused(): void
    {
        $host = $this->user('Host');
        $link = $this->liveEvent($host);

        $me = $this->user('Priya');
        $them = $this->user('Ravi');
        $this->attend($me, $link);
        $this->attend($them, $link);

        $response = $this->actingAs($me)->postJson(
            route('user.event-people.request', $link->alias),
            ['recipient_id' => $them->id]
        );

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'not_discoverable');
    }

    // ── Joining the list from a browser ──────────────────────────────

    /**
     * Opting in was bearer-token only (routes/api.php), so a signed-in
     * attendee on the web could read this list and never join it -- and
     * the page told them to turn on something it gave them no way to
     * turn on. Same controller as the mobile route, so these tests are
     * about the web surface enforcing the same rules, not about the
     * rules being restated somewhere new.
     */
    public function test_an_attendee_can_put_themselves_on_the_list_from_the_web(): void
    {
        $host = $this->user('Host');
        $link = $this->liveEvent($host);

        $me   = $this->user('Priya');
        $them = $this->user('Ravi');
        $this->attend($me, $link);
        $this->attend($them, $link);

        $this->actingAs($me)
            ->postJson(route('user.event-people.toggle', $link->alias), ['discoverable' => true])
            ->assertOk()
            ->assertJsonPath('data.discoverable', true);

        // The test that matters: somebody else can now see them.
        $this->actingAs($them)
            ->getJson(route('user.event-people.index', $link->alias))
            ->assertOk()
            ->assertJsonPath('data.items.0.user.name', 'Priya');
    }

    public function test_hiding_yourself_takes_you_off_the_list(): void
    {
        $host = $this->user('Host');
        $link = $this->liveEvent($host);

        $me   = $this->user('Priya');
        $them = $this->user('Ravi');
        $this->attend($me, $link);
        $this->attend($them, $link);
        $this->beDiscoverable($me, $link);

        $this->actingAs($me)
            ->postJson(route('user.event-people.toggle', $link->alias), ['discoverable' => false])
            ->assertOk();

        $this->assertSame(0, EventDiscoverability::where('user_id', $me->id)
            ->where('link_id', $link->id)->count(), 'hiding left the row behind');

        $this->actingAs($them)
            ->getJson(route('user.event-people.index', $link->alias))
            ->assertOk()
            ->assertJsonPath('data.total', 0);
    }

    public function test_being_listed_does_not_share_where_you_are(): void
    {
        // Two separate agreements, two separate presses. Turning yourself
        // on must never carry a coordinate with it -- the second step is
        // what does that, and this is the test that keeps them apart.
        $host = $this->user('Host');
        $link = $this->liveEvent($host);

        $me = $this->user('Priya');
        $this->attend($me, $link);

        $this->actingAs($me)
            ->postJson(route('user.event-people.toggle', $link->alias), ['discoverable' => true])
            ->assertOk();

        $row = EventDiscoverability::where('user_id', $me->id)->where('link_id', $link->id)->first();
        $this->assertNotNull($row);
        $this->assertNull($row->lat, 'opting in carried a location with it');
        $this->assertNull($row->lng);

        $this->actingAs($me)
            ->getJson(route('user.event-people.index', $link->alias))
            ->assertJsonPath('data.my_discoverable', true)
            ->assertJsonPath('data.my_location_shared', false);

        // The second press, explicitly.
        $this->actingAs($me)
            ->postJson(route('user.event-people.toggle', $link->alias), [
                'discoverable' => true, 'lat' => 12.9716, 'lng' => 77.5946,
            ])
            ->assertOk();

        $this->actingAs($me)
            ->getJson(route('user.event-people.index', $link->alias))
            ->assertJsonPath('data.my_location_shared', true);
    }

    public function test_the_list_says_whether_the_viewer_is_on_it(): void
    {
        // What the empty state on the page branches on: "nobody is here"
        // and "nobody is here and you are not listed either" are different
        // things to say, and only one of them has something to offer.
        $host = $this->user('Host');
        $link = $this->liveEvent($host);

        $me = $this->user('Priya');
        $this->attend($me, $link);

        $this->actingAs($me)
            ->getJson(route('user.event-people.index', $link->alias))
            ->assertOk()
            ->assertJsonPath('data.total', 0)
            ->assertJsonPath('data.my_discoverable', false);

        $this->beDiscoverable($me, $link);

        $this->actingAs($me)
            ->getJson(route('user.event-people.index', $link->alias))
            ->assertOk()
            ->assertJsonPath('data.total', 0, 'the viewer must not be listed to themselves')
            ->assertJsonPath('data.my_discoverable', true);
    }

    public function test_somebody_with_no_seat_cannot_put_themselves_on_the_list(): void
    {
        $host = $this->user('Host');
        $link = $this->liveEvent($host);

        $stranger = $this->user('Nobody');

        $this->actingAs($stranger)
            ->postJson(route('user.event-people.toggle', $link->alias), ['discoverable' => true])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'not_attendee');

        $this->assertSame(0, EventDiscoverability::where('user_id', $stranger->id)->count());
    }

    public function test_you_cannot_join_a_room_that_is_not_open_yet(): void
    {
        $host = $this->user('Host');
        $link = $this->event($host, now()->addDay(), now()->addDay()->addHours(2));

        $me = $this->user('Priya');
        $this->attend($me, $link);

        $this->actingAs($me)
            ->postJson(route('user.event-people.toggle', $link->alias), ['discoverable' => true])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'event_not_live');
    }

    public function test_a_signed_out_visitor_cannot_put_anybody_on_the_list(): void
    {
        $host = $this->user('Host');
        $link = $this->liveEvent($host);

        $this->postJson(route('user.event-people.toggle', $link->alias), ['discoverable' => true])
            ->assertStatus(401);
    }
}
