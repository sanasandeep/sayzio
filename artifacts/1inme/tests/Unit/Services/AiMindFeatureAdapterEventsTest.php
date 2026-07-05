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
 * Task #3611 — Events & Calendar as an AI Mind grounding source.
 *
 * Asserts the snapshot summarizes upcoming/recent calendar events and
 * ticketed/RSVP `ics` event links (counts only, no attendee PII), returns
 * the friendly empty-state string for a user with none, and that the
 * feature key is registered/dispatched like the other per-user features.
 */
class AiMindFeatureAdapterEventsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'name'     => 'Events Tester',
            'email'    => 'events-' . bin2hex(random_bytes(4)) . '@example.test',
            'password' => bcrypt('pw'),
            'status'   => 'active',
            'timezone' => 'UTC',
            'language' => 'en',
        ]);
    }

    public function test_events_is_registered_as_a_per_user_feature(): void
    {
        $this->assertArrayHasKey('events', AiMindFeatureAdapter::FEATURES);
        $this->assertSame('Events & Calendar', AiMindFeatureAdapter::FEATURES['events']);
        $this->assertTrue(AiMindFeatureAdapter::isFeature('events'));
        $this->assertFalse(AiMindFeatureAdapter::isPublicFeature('events'));
    }

    public function test_empty_state_when_user_has_no_events(): void
    {
        $user = $this->makeUser();
        $adapter = new AiMindFeatureAdapter();

        $this->assertSame('You have no events yet.', $adapter->snapshot($user, 'events'));
    }

    public function test_snapshot_includes_calendar_event_and_ticket_rsvp_counts_without_pii(): void
    {
        $user = $this->makeUser();

        // A followable-calendar occurrence (no ticketing).
        CalendarEvent::create([
            'calendar_id' => 1,
            'user_id'     => $user->id,
            'title'       => 'Community Meetup',
            'start_at'    => now()->addDays(3),
            'end_at'      => now()->addDays(3)->addHours(2),
            'timezone'    => 'UTC',
            'location'    => 'Downtown Hall',
        ]);

        // A ticketed `ics` event link with one tier + tickets (sold/checked-in).
        $eventLink = Link::create([
            'user_id'   => $user->id,
            'type'      => 'ics',
            'alias'     => 'ev' . bin2hex(random_bytes(3)),
            'title'     => 'Product Launch Party',
            'is_active' => true,
        ]);
        IcsData::create([
            'link_id'    => $eventLink->id,
            'event_name' => 'Product Launch Party',
            'location'   => 'Main Stage',
            'start_date' => now()->addDays(5),
            'end_date'   => now()->addDays(5)->addHours(3),
            'timezone'   => 'UTC',
        ]);
        $tier = EventTicketTier::create([
            'link_id'  => $eventLink->id,
            'name'     => 'General',
            'capacity' => 100,
        ]);
        EventTicket::create([
            'tier_id'        => $tier->id,
            'link_id'        => $eventLink->id,
            'attendee_name'  => 'Alice Attendee',
            'attendee_email' => 'alice@example.test',
            'attendee_phone' => '+15551234567',
            'quantity'       => 2,
            'code'           => EventTicket::generateCode(),
            'status'         => EventTicket::STATUS_VALID,
        ]);
        EventTicket::create([
            'tier_id'        => $tier->id,
            'link_id'        => $eventLink->id,
            'attendee_name'  => 'Bob Attendee',
            'attendee_email' => 'bob@example.test',
            'quantity'       => 1,
            'code'           => EventTicket::generateCode(),
            'status'         => EventTicket::STATUS_CHECKED_IN,
        ]);

        // A free RSVP-only `ics` event link (no ticket tiers).
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
            'start_date' => now()->subDays(2),
            'end_date'   => now()->subDays(2)->addHours(3),
            'timezone'   => 'UTC',
        ]);
        Rsvp::create([
            'link_id'  => $rsvpLink->id,
            'name'     => 'Carol Guest',
            'email'    => 'carol@example.test',
            'response' => 'yes',
        ]);
        Rsvp::create([
            'link_id'  => $rsvpLink->id,
            'name'     => 'Dave Guest',
            'email'    => 'dave@example.test',
            'response' => 'no',
        ]);

        $adapter = new AiMindFeatureAdapter();
        $snapshot = $adapter->snapshot($user, 'events');

        $this->assertStringContainsString('Community Meetup', $snapshot);
        $this->assertStringContainsString('Downtown Hall', $snapshot);
        $this->assertStringContainsString('Product Launch Party', $snapshot);
        $this->assertStringContainsString('3 sold of 100', $snapshot);
        $this->assertStringContainsString('1 checked in', $snapshot);
        $this->assertStringContainsString('Backyard Cookout', $snapshot);
        $this->assertStringContainsString("1 RSVP'd yes (2 responses)", $snapshot);

        // Never leak attendee PII.
        $this->assertStringNotContainsString('Alice Attendee', $snapshot);
        $this->assertStringNotContainsString('alice@example.test', $snapshot);
        $this->assertStringNotContainsString('+15551234567', $snapshot);
        $this->assertStringNotContainsString('Carol Guest', $snapshot);
        $this->assertStringNotContainsString('carol@example.test', $snapshot);
    }
}
