<?php

namespace Tests\Feature;

use App\Modules\User\Models\EventContactExchange;
use App\Modules\User\Models\EventDiscoverability;
use App\Modules\User\Models\EventTicket;
use App\Modules\User\Models\EventTicketTier;
use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Services\Events\EventAttendeeReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The other three sections of the event report: what was sold, when
 * people walked in, and what the networking surface did.
 *
 * Three of these tests are about refusing to state something the data
 * does not support, which is most of what separates a report from a
 * dashboard: takings are never summed across currencies, an acceptance
 * rate off zero requests is null rather than 0%, and a timing chart
 * with no arrivals behind it is empty rather than flat.
 */
class AnEventReportsItsMoneyAndItsTimingTest extends TestCase
{
    use RefreshDatabase;

    private function host(): User
    {
        return User::factory()->create(['onboarded_at' => now()->subMonths(6)])->fresh();
    }

    private function event(User $host, string $when = '-2 hours'): Link
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

    private function tier(Link $link, string $name, int $cents, string $currency = 'INR'): EventTicketTier
    {
        return EventTicketTier::create([
            'link_id' => $link->id, 'name' => $name,
            'price_cents' => $cents, 'currency' => $currency,
        ]);
    }

    private function ticket(Link $link, array $extra = []): EventTicket
    {
        return EventTicket::create(array_merge([
            'link_id' => $link->id, 'tier_id' => null, 'quantity' => 1,
            'price_cents' => 0, 'currency' => 'INR',
            'status' => EventTicket::STATUS_VALID,
            'code' => Str::upper(Str::random(10)),
            'attendee_name' => 'Guest ' . Str::random(4),
        ], $extra));
    }

    // ── What was sold ────────────────────────────────────────────────

    public function test_takings_come_off_the_ticket_not_the_tier(): void
    {
        // The one that matters. A tier's price is what it costs TODAY;
        // the ticket's price_cents is what was actually charged. Read
        // the tier and every past sale is quietly restated at the new
        // price the moment somebody edits it.
        $event = $this->event($this->host());
        $tier  = $this->tier($event, 'Early bird', 100000);

        $this->ticket($event, ['tier_id' => $tier->id, 'price_cents' => 50000]);

        $tier->update(['price_cents' => 250000]);   // price goes up after the sale

        $sales = EventAttendeeReport::sales($event);

        $this->assertSame(50000, $sales['takings']['INR'], 'a price change rewrote what somebody already paid');
        $this->assertSame(50000, $sales['tiers'][0]['cents']);
    }

    public function test_two_currencies_are_two_totals(): void
    {
        // Summing them would require an exchange rate, and inventing
        // one is exactly the kind of number this report must not
        // produce.
        $event = $this->event($this->host());
        $inr   = $this->tier($event, 'India', 50000, 'INR');
        $usd   = $this->tier($event, 'Worldwide', 3000, 'USD');

        $this->ticket($event, ['tier_id' => $inr->id, 'price_cents' => 50000, 'currency' => 'INR']);
        $this->ticket($event, ['tier_id' => $usd->id, 'price_cents' => 3000,  'currency' => 'USD']);

        $takings = EventAttendeeReport::sales($event)['takings'];

        $this->assertSame(['INR' => 50000, 'USD' => 3000], $takings);
    }

    public function test_a_group_purchase_is_one_order_and_several_seats(): void
    {
        // price_cents is the LINE TOTAL, not a unit price, so revenue is
        // a plain sum. Multiplying by quantity would double-count every
        // group booking.
        $event = $this->event($this->host());
        $tier  = $this->tier($event, 'Table of four', 100000);

        $this->ticket($event, ['tier_id' => $tier->id, 'quantity' => 4, 'price_cents' => 100000]);

        $row = EventAttendeeReport::sales($event)['tiers'][0];

        $this->assertSame(1, $row['orders']);
        $this->assertSame(4, $row['seats']);
        $this->assertSame(100000, $row['cents'], 'the line total was multiplied by the seat count');
    }

    public function test_a_refund_leaves_the_takings_and_is_reported_on_its_own(): void
    {
        $event = $this->event($this->host());
        $tier  = $this->tier($event, 'General', 50000);

        $this->ticket($event, ['tier_id' => $tier->id, 'price_cents' => 50000]);
        $this->ticket($event, ['tier_id' => $tier->id, 'price_cents' => 50000, 'status' => EventTicket::STATUS_REFUNDED]);

        $sales = EventAttendeeReport::sales($event);

        $this->assertSame(50000, $sales['takings']['INR'], 'a refunded sale was still counted as income');
        $this->assertSame(50000, $sales['refunded']['INR']);
    }

    public function test_a_free_event_reports_no_takings_rather_than_a_zero(): void
    {
        $event = $this->event($this->host());
        $this->ticket($event);          // tier-less RSVP ticket, 0 cents

        $sales = EventAttendeeReport::sales($event);

        $this->assertSame([], $sales['takings']);
        $this->assertSame([], $sales['tiers'], 'a tier-less ticket invented a tier row');
    }

    // ── When people walked in ────────────────────────────────────────

    public function test_arrivals_are_bucketed_from_the_start_of_the_event(): void
    {
        $event = $this->event($this->host(), '-3 hours');
        $start = $event->icsData->start_date;

        $this->ticket($event, ['status' => EventTicket::STATUS_CHECKED_IN, 'checked_in_at' => $start->copy()->addMinutes(5)]);
        $this->ticket($event, ['status' => EventTicket::STATUS_CHECKED_IN, 'checked_in_at' => $start->copy()->addMinutes(20)]);
        $this->ticket($event, ['status' => EventTicket::STATUS_CHECKED_IN, 'checked_in_at' => $start->copy()->addMinutes(45)]);

        $buckets = collect(EventAttendeeReport::arrivals($event))->keyBy('offset_minutes');

        $this->assertSame(2, $buckets[0]['count']);
        $this->assertSame(1, $buckets[30]['count']);
    }

    public function test_somebody_who_arrived_early_is_not_filed_as_being_on_time(): void
    {
        // intdiv(-10, 30) is 0, which would put a guest who turned up
        // ten minutes BEFORE the doors into the first half hour after
        // them. floor() keeps the two apart.
        $event = $this->event($this->host(), '-3 hours');
        $start = $event->icsData->start_date;

        $this->ticket($event, ['status' => EventTicket::STATUS_CHECKED_IN, 'checked_in_at' => $start->copy()->subMinutes(10)]);
        $this->ticket($event, ['status' => EventTicket::STATUS_CHECKED_IN, 'checked_in_at' => $start->copy()->addMinutes(10)]);

        $buckets = collect(EventAttendeeReport::arrivals($event))->keyBy('offset_minutes');

        $this->assertSame(1, $buckets[-30]['count'], 'an early arrival was filed as on time');
        $this->assertSame(1, $buckets[0]['count']);
    }

    public function test_no_arrivals_means_no_chart_rather_than_a_flat_one(): void
    {
        $event = $this->event($this->host());
        $this->ticket($event);          // sold, never scanned

        $this->assertSame([], EventAttendeeReport::arrivals($event));
    }

    // ── Networking ───────────────────────────────────────────────────

    public function test_networking_counts_opt_ins_requests_and_acceptances(): void
    {
        $event = $this->event($this->host());
        $a = $this->host();
        $b = $this->host();

        EventDiscoverability::create(['user_id' => $a->id, 'link_id' => $event->id, 'expires_at' => now()->addDay()]);
        EventDiscoverability::create(['user_id' => $b->id, 'link_id' => $event->id, 'expires_at' => now()->addDay()]);

        EventContactExchange::create([
            'link_id' => $event->id, 'requester_id' => $a->id,
            'recipient_id' => $b->id, 'status' => EventContactExchange::STATUS_ACCEPTED,
        ]);
        EventContactExchange::create([
            'link_id' => $event->id, 'requester_id' => $b->id,
            'recipient_id' => $a->id, 'status' => EventContactExchange::STATUS_DECLINED,
        ]);

        $net = EventAttendeeReport::networking($event);

        $this->assertSame(2, $net['opted_in']);
        $this->assertSame(2, $net['requested']);
        $this->assertSame(1, $net['accepted']);
        $this->assertSame(0.5, $net['rate']);
    }

    public function test_an_acceptance_rate_off_no_requests_is_null(): void
    {
        // 0% here would read as "everybody was turned down" when the
        // truth is that nobody asked.
        $event = $this->event($this->host());

        $net = EventAttendeeReport::networking($event);

        $this->assertSame(0, $net['requested']);
        $this->assertNull($net['rate'], 'an event where nobody asked reported a 0% acceptance rate');
    }
}
