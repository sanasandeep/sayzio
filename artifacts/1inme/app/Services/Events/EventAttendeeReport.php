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
            // Heads, not tickets: one RSVP can bring people the door
            // never scans individually. Reported, never used as a
            // denominator for the arrival rate.
            'heads'    => $live->sum(fn (EventTicket $t) => 1 + (int) ($t->rsvp->plus_ones ?? 0)),
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
            'guests' => (int) ($ticket->rsvp->plus_ones ?? 0),
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
