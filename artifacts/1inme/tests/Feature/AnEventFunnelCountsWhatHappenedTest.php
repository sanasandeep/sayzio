<?php

namespace Tests\Feature;

use App\Modules\User\Models\EventContactExchange;
use App\Modules\User\Models\EventDiscoverability;
use App\Modules\User\Models\EventTicket;
use App\Modules\User\Models\EventTicketTier;
use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkClick;
use App\Modules\User\Models\Rsvp;
use App\Modules\User\Models\User;
use App\Services\Events\EventFunnel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Every number the funnel shows has to be one the database already
 * knows. These tests exist to make a wrong one fail rather than look
 * plausible, which is the only thing separating a funnel from a
 * decoration.
 *
 * Two of them matter more than the rest.
 *
 * A confirmed "yes" RSVP issues a ticket automatically, so there is no
 * drop between responding and coming for free attendees. The funnel
 * must not invent a stage there, and the test that would catch it
 * asserts the two counts move together.
 *
 * And a conversion rate off an empty stage must be null, not 0%. "0% of
 * nobody" reads as a measurement of failure when it is the absence of a
 * measurement -- the same mistake as colouring a zero like good news.
 */
class AnEventFunnelCountsWhatHappenedTest extends TestCase
{
    use RefreshDatabase;

    private function event(User $host): Link
    {
        $link = Link::create([
            'user_id' => $host->id, 'type' => 'ics',
            'alias'   => 'evt' . Str::random(8), 'title' => 'Founder Sessions', 'is_active' => true,
        ]);

        IcsData::create([
            'link_id'    => $link->id, 'event_name' => 'Founder Sessions',
            'start_date' => now()->subHours(2)->toDateTimeString(),
            'end_date'   => now()->addHours(2)->toDateTimeString(),
            'timezone'   => 'Asia/Kolkata',
        ]);

        return $link->fresh();
    }

    private function host(): User
    {
        return User::factory()->create(['onboarded_at' => now()->subMonths(6)])->fresh();
    }

    private function views(Link $link, int $n): void
    {
        foreach (range(1, $n) as $i) {
            // is_bot and is_throttled are set explicitly rather than left
            // to column defaults: LinkClick's global scope filters on
            // both, so a fixture that relied on a default would silently
            // produce an empty first stage if either default ever moved.
            LinkClick::create([
                'link_id'      => $link->id,
                'alias'        => $link->alias,
                'clicked_at'   => now()->subMinutes($i),
                'is_bot'       => false,
                'is_throttled' => false,
            ]);
        }
    }

    private function rsvp(Link $link, string $name, string $response = 'yes', array $extra = []): Rsvp
    {
        return Rsvp::create(array_merge([
            'link_id'  => $link->id, 'name' => $name,
            'email'    => Str::slug($name) . '@example.com',
            'response' => $response, 'status' => 'confirmed',
        ], $extra));
    }

    private function ticket(Link $link, array $extra = []): EventTicket
    {
        return EventTicket::create(array_merge([
            'link_id' => $link->id, 'tier_id' => null, 'quantity' => 1,
            'price_cents' => 0, 'status' => EventTicket::STATUS_VALID,
            'code' => Str::upper(Str::random(10)),
        ], $extra));
    }

    /** @return array<string, array<string, mixed>> */
    private function stages(Link $link): array
    {
        $out = [];
        foreach (EventFunnel::for($link)['stages'] as $s) {
            $out[$s['key']] = $s;
        }

        return $out;
    }

    public function test_each_stage_counts_the_table_that_records_it(): void
    {
        $host  = $this->host();
        $event = $this->event($host);

        $this->views($event, 40);
        $this->rsvp($event, 'Priya Sharma');
        $this->rsvp($event, 'Ravi Kumar');
        $this->rsvp($event, 'Nilesh Echai', 'no');

        $this->ticket($event);                                                   // coming
        $this->ticket($event, ['status' => EventTicket::STATUS_CHECKED_IN]);     // coming + arrived

        $s = $this->stages($event);

        $this->assertSame(40, $s['opened']['count']);
        $this->assertSame(3,  $s['responded']['count'], 'a "no" is still somebody who answered');
        $this->assertSame(2,  $s['coming']['count']);
        $this->assertSame(1,  $s['arrived']['count']);
    }

    public function test_a_cancelled_ticket_is_not_somebody_coming(): void
    {
        $host  = $this->host();
        $event = $this->event($host);

        $this->ticket($event);
        $this->ticket($event, ['status' => EventTicket::STATUS_CANCELLED]);
        $this->ticket($event, ['status' => EventTicket::STATUS_REFUNDED]);

        $s = $this->stages($event);

        $this->assertSame(1, $s['coming']['count'], 'a refunded ticket was counted as an attendee');
    }

    public function test_a_rate_off_an_empty_stage_is_null_not_zero(): void
    {
        // The one most likely to be "simplified" later. 0% of nobody is
        // not a measurement, and drawn as one it reads as failure.
        $host  = $this->host();
        $event = $this->event($host);

        $s = $this->stages($event);

        $this->assertNull($s['responded']['rate'], 'an empty funnel reported a 0% conversion');
        $this->assertNull($s['coming']['rate']);
        $this->assertNull($s['arrived']['rate']);
    }

    public function test_a_rate_is_the_share_of_the_stage_before_it(): void
    {
        $host  = $this->host();
        $event = $this->event($host);

        $this->views($event, 100);
        foreach (range(1, 10) as $i) {
            $this->rsvp($event, 'Guest ' . $i);
        }
        foreach (range(1, 4) as $i) {
            $this->ticket($event, ['status' => EventTicket::STATUS_CHECKED_IN]);
        }

        $s = $this->stages($event);

        $this->assertSame(0.1, $s['responded']['rate']);
        $this->assertSame(0.4, $s['coming']['rate']);
        $this->assertSame(1.0, $s['arrived']['rate'], 'everyone holding a ticket turned up');
    }

    public function test_plus_ones_are_reported_as_heads_not_added_to_tickets(): void
    {
        // An RSVP for three issues ONE ticket. Adding plus-ones into
        // "coming" would count heads the door cannot scan and make
        // "arrived" look like a collapse.
        $host  = $this->host();
        $event = $this->event($host);

        $rsvp = $this->rsvp($event, 'Priya Sharma', 'yes', ['plus_ones' => 2]);
        $this->ticket($event, ['rsvp_id' => $rsvp->id]);

        $funnel = EventFunnel::for($event);
        $s      = $this->stages($event);

        $this->assertSame(1, $s['coming']['count'], 'plus-ones were counted as tickets');
        $this->assertSame(3, $funnel['headcount'], 'the headcount forgot the plus-ones');
    }

    public function test_a_declined_rsvp_does_not_add_plus_ones_to_the_headcount(): void
    {
        $host  = $this->host();
        $event = $this->event($host);

        $this->rsvp($event, 'Not Coming', 'no', ['plus_ones' => 5]);

        $this->assertSame(0, EventFunnel::for($event)['headcount']);
    }

    public function test_connections_count_only_accepted_swaps(): void
    {
        $host  = $this->host();
        $event = $this->event($host);
        $a     = $this->host();
        $b     = $this->host();

        EventDiscoverability::create([
            'user_id' => $a->id, 'link_id' => $event->id, 'expires_at' => now()->addDay(),
        ]);

        EventContactExchange::create([
            'link_id' => $event->id, 'requester_id' => $a->id,
            'recipient_id' => $b->id, 'status' => EventContactExchange::STATUS_ACCEPTED,
        ]);
        EventContactExchange::create([
            'link_id' => $event->id, 'requester_id' => $b->id,
            'recipient_id' => $a->id, 'status' => EventContactExchange::STATUS_PENDING,
        ]);

        $s = $this->stages($event);

        $this->assertSame(1, $s['connected']['count'], 'a pending swap was counted as a connection');
        $this->assertTrue($s['connected']['applicable']);
    }

    public function test_nobody_opting_in_marks_the_stage_not_applicable(): void
    {
        // "Offered and ignored" and "never offered" are different, and a
        // bare 0 reads as the first.
        $host  = $this->host();
        $event = $this->event($host);

        $s = $this->stages($event);

        $this->assertSame(0, $s['connected']['count']);
        $this->assertFalse($s['connected']['applicable'], 'a stage nobody could reach was presented as a result');
    }

    public function test_the_notes_say_what_the_counts_cannot(): void
    {
        $host  = $this->host();
        $event = $this->event($host);

        $tier = EventTicketTier::create([
            'link_id' => $event->id, 'name' => 'General', 'price_cents' => 2500,
        ]);

        $this->ticket($event, ['tier_id' => $tier->id, 'price_cents' => 2500]);
        $this->ticket($event);                                   // free RSVP ticket
        $this->ticket($event, ['status' => EventTicket::STATUS_REFUNDED]);
        $this->rsvp($event, 'Not Coming', 'no');

        $notes = implode(' | ', EventFunnel::for($event)['notes']);

        $this->assertStringContainsString('bought a ticket', $notes);
        $this->assertStringContainsString('not coming', $notes);
        $this->assertStringContainsString('cancelled or refunded', $notes);
    }

    public function test_the_first_stage_agrees_with_the_number_the_page_already_shows(): void
    {
        // The funnel reads $link->clicks(), the same relation the
        // Overview tab counts. A funnel whose top number disagrees with
        // the page above it is worse than no funnel.
        $host  = $this->host();
        $event = $this->event($host);

        $this->views($event, 7);

        $this->assertSame(
            (int) $event->clicks()->count(),
            $this->stages($event)['opened']['count']
        );
    }
}
