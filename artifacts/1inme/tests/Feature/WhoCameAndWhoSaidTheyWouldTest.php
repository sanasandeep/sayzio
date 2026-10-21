<?php

namespace Tests\Feature;

use App\Modules\User\Models\EventTicket;
use App\Modules\User\Models\EventTicketTier;
use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Rsvp;
use App\Modules\User\Models\User;
use App\Services\Events\EventAttendeeReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The attendee list, and the one number on it that is easy to get
 * wrong: no-shows.
 *
 * Before an event starts nobody is a no-show -- they are early. A count
 * of unscanned tickets on an event that has not begun is not a
 * measurement, and printed as "12 no-shows" it is false. So it is null
 * until the event starts, the same way the funnel returns a null rate
 * rather than 0% off an empty stage.
 *
 * Cancelled and refunded tickets are not failures to turn up either.
 * Somebody who cancelled told you. They are counted separately and left
 * out of what was expected.
 */
class WhoCameAndWhoSaidTheyWouldTest extends TestCase
{
    use RefreshDatabase;

    private function host(): User
    {
        return User::factory()->create(['onboarded_at' => now()->subMonths(6)])->fresh();
    }

    private function event(User $host, string $when = '+2 days'): Link
    {
        $link = Link::create([
            'user_id' => $host->id, 'type' => 'ics',
            'alias'   => 'evt' . Str::random(8), 'title' => 'Founder Sessions', 'is_active' => true,
        ]);

        $start = Carbon::parse($when);

        IcsData::create([
            'link_id'    => $link->id, 'event_name' => 'Founder Sessions',
            'start_date' => $start->toDateTimeString(),
            'end_date'   => $start->copy()->addHours(4)->toDateTimeString(),
            'timezone'   => 'Asia/Kolkata',
        ]);

        return $link->fresh();
    }

    private function ticket(Link $link, array $extra = []): EventTicket
    {
        return EventTicket::create(array_merge([
            'link_id' => $link->id, 'tier_id' => null, 'quantity' => 1,
            'price_cents' => 0, 'status' => EventTicket::STATUS_VALID,
            'code' => Str::upper(Str::random(10)),
            'attendee_name' => 'Guest ' . Str::random(4),
        ], $extra));
    }

    public function test_nobody_is_a_no_show_before_the_doors_open(): void
    {
        // The number this whole file exists for.
        $event = $this->event($this->host(), '+2 days');

        $this->ticket($event);
        $this->ticket($event);

        $report = EventAttendeeReport::for($event);

        $this->assertFalse($report['started']);
        $this->assertSame(2, $report['expected']);
        $this->assertSame(0, $report['arrived']);
        $this->assertNull($report['no_shows'], 'an event that has not started reported no-shows');
    }

    public function test_an_unscanned_ticket_is_expected_not_missing_beforehand(): void
    {
        $event = $this->event($this->host(), '+2 days');
        $this->ticket($event);

        $row = EventAttendeeReport::for($event)['rows']->first();

        $this->assertSame('expected', $row['status']);
    }

    public function test_once_it_has_started_the_gap_is_real(): void
    {
        $event = $this->event($this->host(), '-1 hour');

        $this->ticket($event, ['status' => EventTicket::STATUS_CHECKED_IN, 'checked_in_at' => now()->subMinutes(20)]);
        $this->ticket($event);
        $this->ticket($event);

        $report = EventAttendeeReport::for($event);

        $this->assertTrue($report['started']);
        $this->assertSame(3, $report['expected']);
        $this->assertSame(1, $report['arrived']);
        $this->assertSame(2, $report['no_shows']);
    }

    public function test_somebody_who_cancelled_did_not_fail_to_turn_up(): void
    {
        $event = $this->event($this->host(), '-1 hour');

        $this->ticket($event, ['status' => EventTicket::STATUS_CHECKED_IN, 'checked_in_at' => now()]);
        $this->ticket($event, ['status' => EventTicket::STATUS_CANCELLED]);
        $this->ticket($event, ['status' => EventTicket::STATUS_REFUNDED]);

        $report = EventAttendeeReport::for($event);

        $this->assertSame(1, $report['expected'], 'a cancelled ticket was still expected to arrive');
        $this->assertSame(0, $report['no_shows'], 'somebody who cancelled was counted as a no-show');
        $this->assertSame(2, $report['released']);
    }

    public function test_a_free_rsvp_ticket_says_where_it_came_from(): void
    {
        // A tier-less ticket came from an RSVP. Leaving the column blank
        // would make the report look broken rather than free.
        $host  = $this->host();
        $event = $this->event($host, '-1 hour');

        $rsvp = Rsvp::create([
            'link_id' => $event->id, 'name' => 'Priya Sharma',
            'email'   => 'priya@example.com', 'response' => 'yes', 'status' => 'confirmed',
            'plus_ones' => 2,
        ]);

        $this->ticket($event, ['rsvp_id' => $rsvp->id, 'attendee_name' => 'Priya Sharma']);

        $tier = EventTicketTier::create([
            'link_id' => $event->id, 'name' => 'General', 'price_cents' => 2500,
        ]);
        $this->ticket($event, ['tier_id' => $tier->id, 'price_cents' => 2500, 'attendee_name' => 'Ravi Kumar']);

        $rows = EventAttendeeReport::for($event)['rows']->keyBy('name');

        $this->assertSame('RSVP', $rows['Priya Sharma']['via']);
        $this->assertFalse($rows['Priya Sharma']['paid']);
        $this->assertSame('General', $rows['Ravi Kumar']['via']);
        $this->assertTrue($rows['Ravi Kumar']['paid']);
    }

    public function test_plus_ones_are_heads_beside_the_count_not_inside_it(): void
    {
        // Same rule as the funnel: an RSVP for three is one scannable
        // ticket. Folding the guests into `expected` would make the
        // arrival rate wrong in the one direction nobody checks.
        $host  = $this->host();
        $event = $this->event($host, '-1 hour');

        $rsvp = Rsvp::create([
            'link_id' => $event->id, 'name' => 'Priya Sharma',
            'email'   => 'priya@example.com', 'response' => 'yes', 'status' => 'confirmed',
            'plus_ones' => 2,
        ]);
        $this->ticket($event, ['rsvp_id' => $rsvp->id, 'attendee_name' => 'Priya Sharma']);

        $report = EventAttendeeReport::for($event);

        $this->assertSame(1, $report['expected']);
        $this->assertSame(3, $report['heads']);
        $this->assertSame(2, $report['rows']->first()['guests']);
    }

    public function test_arrivals_are_listed_in_the_order_they_turned_up(): void
    {
        $event = $this->event($this->host(), '-3 hours');

        $this->ticket($event, ['attendee_name' => 'Third',  'status' => EventTicket::STATUS_CHECKED_IN, 'checked_in_at' => now()->subMinutes(10)]);
        $this->ticket($event, ['attendee_name' => 'First',  'status' => EventTicket::STATUS_CHECKED_IN, 'checked_in_at' => now()->subMinutes(90)]);
        $this->ticket($event, ['attendee_name' => 'Absent']);
        $this->ticket($event, ['attendee_name' => 'Second', 'status' => EventTicket::STATUS_CHECKED_IN, 'checked_in_at' => now()->subMinutes(50)]);

        $names = EventAttendeeReport::for($event)['rows']->pluck('name')->all();

        $this->assertSame(['First', 'Second', 'Third', 'Absent'], $names,
            'the list did not put the people who came first, in arrival order');
    }

    public function test_the_export_carries_raw_values_for_the_writer_to_escape(): void
    {
        // A name beginning with = is a formula to a spreadsheet. It must
        // not reach a file unescaped -- but escaping belongs to
        // CsvSafe::put(), which every other export in this codebase
        // streams through. Sanitising here as well would prefix twice
        // and corrupt the names it was meant to protect.
        $event = $this->event($this->host(), '-1 hour');
        $this->ticket($event, ['attendee_name' => '=SUM(A1:A9)', 'attendee_email' => 'odd@example.com']);

        $rows = EventAttendeeReport::exportRows($event);

        $this->assertSame(['Name', 'Email', 'Via', 'Guests', 'Status', 'Arrived at'], $rows[0]);
        $this->assertSame('=SUM(A1:A9)', $rows[1][0], 'the service escaped a value the writer will escape again');
    }

    public function test_an_event_with_nobody_on_it_reports_nothing_rather_than_zeroes_that_look_like_findings(): void
    {
        $event = $this->event($this->host(), '+2 days');

        $report = EventAttendeeReport::for($event);

        $this->assertCount(0, $report['rows']);
        $this->assertSame(0, $report['expected']);
        $this->assertNull($report['no_shows']);
    }
}
