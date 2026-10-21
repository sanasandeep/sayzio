<?php

namespace App\Services\Events;

use App\Modules\User\Models\EventTicket;
use App\Modules\User\Models\Link;
use Illuminate\Support\Collection;

/**
 * Who said they were coming, and who actually turned up.
 *
 * One row per ticket, because a ticket is the only thing the door can
 * scan and therefore the only thing attendance can be known about. A
 * paid buyer and a confirmed-yes RSVP both have one; that is the whole
 * reason EventFunnel counts tickets rather than RSVPs.
 *
 * THE NO-SHOW NUMBER IS THE ONE TO BE CAREFUL WITH
 *
 * Before an event starts, nobody is a no-show -- they are early. A
 * count of unscanned tickets on an event that has not begun is not a
 * measurement of anything, and shown as "12 no-shows" it is simply
 * false. So no_shows is null until the event has started, the same way
 * EventFunnel returns a null rate rather than 0% off an empty stage.
 *
 * Cancelled and refunded tickets are excluded from the expected count
 * and reported separately. Somebody who cancelled did not fail to turn
 * up; they told you.
 */
class EventAttendeeReport
{
    /**
     * @return array{
     *     rows: Collection,
     *     expected: int,
     *     arrived: int,
     *     no_shows: int|null,
     *     released: int,
     *     heads: int,
     *     started: bool
     * }
     */
    public static function for(Link $link): array
    {
        $tickets = EventTicket::where('link_id', $link->id)
            ->with(['tier', 'rsvp'])
            ->orderByRaw('checked_in_at is null')          // arrivals first, in order
            ->orderBy('checked_in_at')
            ->orderBy('id')
            ->get();

        $live = $tickets->whereIn('status', [
            EventTicket::STATUS_VALID,
            EventTicket::STATUS_CHECKED_IN,
        ]);

        $arrived  = $live->where('status', EventTicket::STATUS_CHECKED_IN);
        $released = $tickets->whereIn('status', [
            EventTicket::STATUS_CANCELLED,
            EventTicket::STATUS_REFUNDED,
        ]);

        $started = self::hasStarted($link);

        return [
            'rows'     => $tickets->map(fn (EventTicket $t) => self::row($t, $started))->values(),
            'expected' => $live->count(),
            'arrived'  => $arrived->count(),
            // Null, not 0, until there is an event to have missed.
            'no_shows' => $started ? ($live->count() - $arrived->count()) : null,
            'released' => $released->count(),
            // Heads, not tickets. A group turns up on ONE scannable
            // code two different ways and both have to count: an RSVP
            // carries its party in plus_ones, a paid purchase carries
            // it in quantity -- MonetizationCheckout writes the seats
            // bought into that column and the line total into
            // price_cents.
            //
            // This first shipped counting every ticket as one person:
            // right for RSVPs, silently wrong for anybody who bought
            // more than one seat.
            //
            // Reported, never the denominator for an arrival rate. The
            // door scans codes, not people.
            'heads'    => $live->sum(fn (EventTicket $t) => max(1, (int) $t->quantity) + (int) ($t->rsvp->plus_ones ?? 0)),
            'started'  => $started,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function row(EventTicket $ticket, bool $started): array
    {
        $cancelled = in_array($ticket->status, [
            EventTicket::STATUS_CANCELLED,
            EventTicket::STATUS_REFUNDED,
        ], true);

        $checkedIn = $ticket->status === EventTicket::STATUS_CHECKED_IN;

        return [
            'name'  => $ticket->attendee_name ?: ($ticket->rsvp->name ?? 'Guest'),
            'email' => $ticket->attendee_email ?: ($ticket->rsvp->email ?? null),
            // A tier-less ticket came from an RSVP. Saying "RSVP" rather
            // than leaving it blank is the difference between a report
            // and a spreadsheet with holes in it.
            'via'   => $ticket->tier?->name ?: 'RSVP',
            'paid'  => $ticket->tier !== null,
            // People beyond the one holding the code: extra seats on a
            // paid purchase, plus-ones on an RSVP. Same correction as
            // `heads` above, for the same reason.
            'guests' => max(0, (int) $ticket->quantity - 1) + (int) ($ticket->rsvp->plus_ones ?? 0),
            'status' => match (true) {
                $cancelled => $ticket->status,
                $checkedIn => 'arrived',
                // Before the doors open, an unscanned ticket is expected,
                // not missing.
                $started   => 'no_show',
                default    => 'expected',
            },
            'arrived_at' => $ticket->checked_in_at,
        ];
    }

    /**
     * Has the event begun? Same question EventPerson asks, and for the
     * same reason: a number that only means something afterwards must
     * not be shown before.
     */
    private static function hasStarted(Link $link): bool
    {
        $start = $link->icsData?->start_date;

        return $start instanceof \DateTimeInterface && $start < now();
    }

    /**
     * What was sold, by tier.
     *
     * TWO THINGS THAT LOOK LIKE DETAILS AND ARE NOT.
     *
     * The money comes off the TICKET, never the tier. A tier's price is
     * what it costs today; the ticket's price_cents is what was actually
     * charged, and those differ the moment anybody edits a price after
     * a sale. Reading the tier would quietly restate history at the
     * current price.
     *
     * And takings are grouped by currency rather than summed. An event
     * that sold in two currencies has two totals, not one meaningless
     * one, and nothing here is allowed to invent an exchange rate.
     *
     * price_cents is the LINE TOTAL for the whole purchase, not a unit
     * price -- MonetizationCheckout writes the charged amount into it
     * and the seat count into quantity -- so revenue is a plain sum and
     * multiplying by quantity would double-count every group booking.
     *
     * @return array{tiers: array<int, array<string, mixed>>, takings: array<string, int>, refunded: array<string, int>}
     */
    public static function sales(Link $link): array
    {
        $tickets = EventTicket::where('link_id', $link->id)->with('tier')->get();

        $live     = $tickets->whereIn('status', [EventTicket::STATUS_VALID, EventTicket::STATUS_CHECKED_IN]);
        $returned = $tickets->where('status', EventTicket::STATUS_REFUNDED);

        $tiers = $live->whereNotNull('tier_id')
            ->groupBy('tier_id')
            ->map(function ($rows) {
                $first = $rows->first();

                return [
                    'tier'     => $first->tier?->name ?? 'Removed tier',
                    'currency' => $first->currency ?: ($first->tier->currency ?? 'USD'),
                    'orders'   => $rows->count(),
                    'seats'    => $rows->sum(fn (EventTicket $t) => max(1, (int) $t->quantity)),
                    'cents'    => $rows->sum(fn (EventTicket $t) => (int) $t->price_cents),
                ];
            })
            ->sortByDesc('cents')
            ->values()
            ->all();

        return [
            'tiers'    => $tiers,
            'takings'  => self::byCurrency($live),
            'refunded' => self::byCurrency($returned),
        ];
    }

    /** @return array<string, int> */
    private static function byCurrency($tickets): array
    {
        return $tickets
            ->groupBy(fn (EventTicket $t) => $t->currency ?: 'USD')
            ->map(fn ($rows) => (int) $rows->sum(fn (EventTicket $t) => (int) $t->price_cents))
            ->filter(fn (int $cents) => $cents > 0)
            ->all();
    }

    /**
     * When people actually walked in, in half-hour buckets measured
     * from the event's start.
     *
     * Measured from the START rather than from the first arrival,
     * because "who came early" is the question an organiser is asking
     * and a first-arrival baseline makes the earliest person look
     * punctual by definition. Arrivals before the start get negative
     * buckets, which is the honest place to put them.
     *
     * Empty when nobody has been scanned. A timing chart drawn through
     * no arrivals is a shape with no data behind it.
     *
     * @return array<int, array{offset_minutes: int, count: int}>
     */
    public static function arrivals(Link $link): array
    {
        $start = $link->icsData?->start_date;

        if (! $start instanceof \DateTimeInterface) {
            return [];
        }

        $scans = EventTicket::where('link_id', $link->id)
            ->where('status', EventTicket::STATUS_CHECKED_IN)
            ->whereNotNull('checked_in_at')
            ->pluck('checked_in_at');

        if ($scans->isEmpty()) {
            return [];
        }

        $buckets = [];
        foreach ($scans as $at) {
            // intdiv floors toward zero, which would put -10 and +10
            // minutes in the same bucket. floor() keeps the half hour
            // before the doors distinct from the half hour after.
            $minutes = ($at->getTimestamp() - $start->getTimestamp()) / 60;
            $bucket  = (int) (floor($minutes / 30) * 30);

            $buckets[$bucket] = ($buckets[$bucket] ?? 0) + 1;
        }

        ksort($buckets);

        return array_map(
            fn ($offset, $count) => ['offset_minutes' => (int) $offset, 'count' => (int) $count],
            array_keys($buckets),
            array_values($buckets)
        );
    }

    /**
     * What the networking surface actually did.
     *
     * The acceptance rate is null when nobody asked -- there is no
     * share of zero requests, and a 0% printed here would read as
     * "everyone was turned down".
     *
     * @return array{opted_in: int, requested: int, accepted: int, rate: float|null}
     */
    public static function networking(Link $link): array
    {
        $optedIn = (int) \App\Modules\User\Models\EventDiscoverability::where('link_id', $link->id)->count();

        $exchanges = \App\Modules\User\Models\EventContactExchange::where('link_id', $link->id)->get();
        $requested = $exchanges->count();
        $accepted  = $exchanges
            ->where('status', \App\Modules\User\Models\EventContactExchange::STATUS_ACCEPTED)
            ->count();

        return [
            'opted_in'  => $optedIn,
            'requested' => $requested,
            'accepted'  => $accepted,
            'rate'      => $requested > 0 ? (float) ($accepted / $requested) : null,
        ];
    }

    /**
     * The report shaped for export, header row first.
     *
     * RAW VALUES, DELIBERATELY. An attendee list is user-supplied text
     * and a name beginning =, +, - or @ is a formula to a spreadsheet,
     * so these must not reach a file unescaped -- but escaping belongs
     * to the writer, not here. Every export in this codebase streams
     * through CsvSafe::put(), which calls CsvSafe::cell() on each
     * value; sanitising here as well would prefix twice and corrupt the
     * very names it was meant to protect.
     *
     * So: callers write these rows with CsvSafe::put(), the same as
     * ActivityLogController and the rest.
     *
     * @return array<int, array<int, string>>
     */
    public static function exportRows(Link $link): array
    {
        $out = [['Name', 'Email', 'Via', 'Guests', 'Status', 'Arrived at']];

        foreach (self::for($link)['rows'] as $row) {
            $out[] = [
                (string) $row['name'],
                (string) ($row['email'] ?? ''),
                (string) $row['via'],
                (string) $row['guests'],
                (string) $row['status'],
                $row['arrived_at'] ? $row['arrived_at']->toDateTimeString() : '',
            ];
        }

        return $out;
    }
}
