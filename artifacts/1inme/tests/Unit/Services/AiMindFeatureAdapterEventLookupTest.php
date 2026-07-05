<?php

namespace Tests\Unit\Services;

use App\Modules\User\Models\CalendarEvent;
use App\Modules\User\Models\EventTicket;
use App\Modules\User\Models\EventTicketTier;
use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Rsvp;
use App\Modules\User\Models\User;
use App\Services\AI\AiMindFeatureAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task #3613 — dedicated single-event lookup for the AI Mind.
 *
 * The events() snapshot is capped at 8 rows, so an event outside that
 * window is invisible to the grounded prompt. eventLookup() lets the AI
 * fetch ONE named/dated event's full stats on demand. These assert it
 * finds events by name (incl. ones outside the summary cap), reports full
 * ticketing/RSVP counts without leaking attendee PII, stays owner-scoped,
 * matches by year, and returns a friendly not-found string.
 */
class AiMindFeatureAdapterEventLookupTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'name'     => 'Lookup Tester',
            'email'    => 'lookup-' . bin2hex(random_bytes(4)) . '@example.test',
            'password' => bcrypt('pw'),
            'status'   => 'active',
            'timezone' => 'UTC',
            'language' => 'en',
        ]);
    }

    private function ticketedEvent(User $user, string $name, \DateTimeInterface $start): Link
    {
        $link = Link::create([
            'user_id'   => $user->id,
            'type'      => 'ics',
            'alias'     => 'ev' . bin2hex(random_bytes(3)),
            'title'     => $name,
            'is_active' => true,
        ]);
        IcsData::create([
            'link_id'    => $link->id,
            'event_name' => $name,
            'location'   => 'Main Stage',
            'start_date' => $start,
            'end_date'   => (clone $start)->modify('+3 hours'),
            'timezone'   => 'UTC',
        ]);
        $tier = EventTicketTier::create([
            'link_id'  => $link->id,
            'name'     => 'General',
            'capacity' => 100,
        ]);
        EventTicket::create([
            'tier_id'        => $tier->id,
            'link_id'        => $link->id,
            'attendee_name'  => 'Alice Attendee',
            'attendee_email' => 'alice@example.test',
            'attendee_phone' => '+15551234567',
            'quantity'       => 2,
            'code'           => EventTicket::generateCode(),
            'status'         => EventTicket::STATUS_VALID,
        ]);
        EventTicket::create([
            'tier_id'        => $tier->id,
            'link_id'        => $link->id,
            'attendee_name'  => 'Bob Attendee',
            'attendee_email' => 'bob@example.test',
            'quantity'       => 1,
            'code'           => EventTicket::generateCode(),
            'status'         => EventTicket::STATUS_CHECKED_IN,
        ]);

        return $link;
    }

    public function test_looks_up_a_ticketed_event_with_full_stats_and_no_pii(): void
    {
        $user = $this->makeUser();
        $this->ticketedEvent($user, 'Product Launch Party', now()->addDays(5)->setTime(18, 0));

        $adapter = new AiMindFeatureAdapter();
        $out = $adapter->eventLookup($user, 'Product Launch Party');

        $this->assertStringContainsString('Product Launch Party', $out);
        $this->assertStringContainsString('Main Stage', $out);
        $this->assertStringContainsString('3 sold of 100 capacity', $out);
        $this->assertStringContainsString('1 checked in', $out);
        $this->assertStringContainsString('General', $out);

        // Never leak attendee PII.
        $this->assertStringNotContainsString('Alice Attendee', $out);
        $this->assertStringNotContainsString('alice@example.test', $out);
        $this->assertStringNotContainsString('+15551234567', $out);
        $this->assertStringNotContainsString('Bob Attendee', $out);
    }

    public function test_finds_an_event_outside_the_summary_cap(): void
    {
        $user = $this->makeUser();

        // Flood the summary window: events() shows the 6 soonest upcoming
        // events + up to 2 most-recent past ones (cap 8). Enough upcoming
        // fillers AND more-recent past fillers guarantee the target (a
        // long-ago past event) falls outside that capped view.
        for ($i = 1; $i <= 10; $i++) {
            $this->ticketedEvent($user, "Filler Event {$i}", now()->addDays($i));
        }
        for ($i = 1; $i <= 4; $i++) {
            $this->ticketedEvent($user, "Recent Filler {$i}", now()->subDays($i));
        }
        $this->ticketedEvent($user, 'Autumn Retreat', now()->subDays(120)->setTime(9, 0));

        $adapter = new AiMindFeatureAdapter();

        // The capped summary should NOT include the buried past event...
        $this->assertStringNotContainsString('Autumn Retreat', $adapter->snapshot($user, 'events'));
        // ...but the dedicated lookup finds it by name.
        $out = $adapter->eventLookup($user, 'Autumn Retreat');
        $this->assertStringContainsString('Autumn Retreat', $out);
        $this->assertStringContainsString('past', $out);
    }

    public function test_looks_up_a_calendar_event_and_rsvp_breakdown(): void
    {
        $user = $this->makeUser();

        CalendarEvent::create([
            'calendar_id' => 1,
            'user_id'     => $user->id,
            'title'       => 'Community Meetup',
            'start_at'    => now()->addDays(3),
            'end_at'      => now()->addDays(3)->addHours(2),
            'timezone'    => 'UTC',
            'location'    => 'Downtown Hall',
        ]);

        $rsvpLink = Link::create([
            'user_id'   => $user->id,
            'type'      => 'ics',
            'alias'     => 'ev' . bin2hex(random_bytes(3)),
            'title'     => 'Backyard Cookout',
            'is_active' => true,
        ]);
        IcsData::create([
            'link_id'    => $rsvpLink->id,
            'event_name' => 'Backyard Cookout',
            'start_date' => now()->addDays(2),
            'end_date'   => now()->addDays(2)->addHours(3),
            'timezone'   => 'UTC',
        ]);
        Rsvp::create(['link_id' => $rsvpLink->id, 'name' => 'Carol Guest', 'email' => 'carol@example.test', 'response' => 'yes']);
        Rsvp::create(['link_id' => $rsvpLink->id, 'name' => 'Dave Guest',  'email' => 'dave@example.test',  'response' => 'no']);
        Rsvp::create(['link_id' => $rsvpLink->id, 'name' => 'Eve Guest',   'email' => 'eve@example.test',   'response' => 'maybe']);

        $adapter = new AiMindFeatureAdapter();

        $calendar = $adapter->eventLookup($user, 'Community Meetup');
        $this->assertStringContainsString('Community Meetup', $calendar);
        $this->assertStringContainsString('Downtown Hall', $calendar);

        $cookout = $adapter->eventLookup($user, 'Backyard Cookout');
        $this->assertStringContainsString('Backyard Cookout', $cookout);
        $this->assertStringContainsString('1 going, 1 maybe, 1 not going (3 total)', $cookout);
        $this->assertStringNotContainsString('Carol Guest', $cookout);
        $this->assertStringNotContainsString('carol@example.test', $cookout);
    }

    public function test_matches_by_year_token(): void
    {
        $user = $this->makeUser();
        $this->ticketedEvent($user, 'Summit', \Carbon\Carbon::create(2024, 6, 1, 10));
        $this->ticketedEvent($user, 'Summit', \Carbon\Carbon::create(2025, 6, 1, 10));

        $adapter = new AiMindFeatureAdapter();
        $out = $adapter->eventLookup($user, 'Summit 2025');

        $this->assertStringContainsString('Summit', $out);
        $this->assertStringContainsString('2025', $out);
        $this->assertStringNotContainsString('2024', $out);
    }

    public function test_is_owner_scoped(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $this->ticketedEvent($other, 'Secret Gala', now()->addDays(4));

        $adapter = new AiMindFeatureAdapter();
        $out = $adapter->eventLookup($owner, 'Secret Gala');

        $this->assertStringNotContainsString('Secret Gala', $out);
        $this->assertStringContainsString('No event matching', $out);
    }

    public function test_friendly_not_found_and_empty_query(): void
    {
        $user = $this->makeUser();
        $adapter = new AiMindFeatureAdapter();

        $this->assertStringContainsString(
            'No event matching "Nonexistent"',
            $adapter->eventLookup($user, 'Nonexistent')
        );
        $this->assertStringContainsString(
            'specify an event name',
            $adapter->eventLookup($user, '   ')
        );
    }
}
