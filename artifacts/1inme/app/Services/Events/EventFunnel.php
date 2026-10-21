<?php

namespace App\Services\Events;

use App\Modules\User\Models\EventContactExchange;
use App\Modules\User\Models\EventDiscoverability;
use App\Modules\User\Models\EventTicket;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Rsvp;

/**
 * The attendee journey for one event, stage by stage, counted from the
 * tables that already record it. Nothing here is estimated.
 *
 * WHAT THE STAGES ARE, AND WHY THEY ARE NOT THE OBVIOUS ONES
 *
 * The obvious funnel -- views, RSVPs, tickets sold, checked in -- has a
 * stage in it that cannot lose anybody. RsvpTicketService::sync() issues
 * a ticket for every confirmed "yes" RSVP, tier-less, with rsvp_id
 * attached. So "RSVPs -> tickets" is 1:1 by construction and drawing it
 * as a step would show a 0% drop-off that means nothing at all.
 *
 * Tickets are not a step; they are how attendance is RECORDED, for the
 * free and the paid alike. What somebody paid is a breakdown of who is
 * coming, which belongs in the report, not here.
 *
 *   opened     the event page was loaded
 *   responded  an RSVP arrived -- yes, no or maybe, because a "no" is
 *              still somebody who answered
 *   coming     a valid ticket exists: paid buyers and confirmed-yes
 *              RSVPs together
 *   arrived    that ticket was scanned at the door
 *   connected  two attendees swapped contacts and one accepted
 *
 * WHAT IS DELIBERATELY NOT COUNTED
 *
 * Plus-ones. An RSVP for three people issues ONE ticket (quantity 1),
 * so adding plus_ones to "coming" would count heads the door cannot
 * scan and would make "arrived" look like a collapse. The headcount is
 * reported beside the stage instead of inside it.
 *
 * Bots. `$link->clicks()` carries a default scope that excludes them,
 * and this uses that relation rather than a second count, so the first
 * stage agrees with the figure the Overview tab already shows. A funnel
 * whose top number disagrees with the page above it is worse than no
 * funnel.
 */
class EventFunnel
{
    /**
     * @return array{stages: array<int, array<string, mixed>>, headcount: int, notes: array<int, string>}
     */
    public static function for(Link $link): array
    {
        $opened = (int) $link->clicks()->count();

        $responded = (int) Rsvp::where('link_id', $link->id)->count();

        $tickets = EventTicket::where('link_id', $link->id);
        $coming  = (int) (clone $tickets)
            ->whereIn('status', [EventTicket::STATUS_VALID, EventTicket::STATUS_CHECKED_IN])
            ->count();
        $arrived = (int) (clone $tickets)
            ->where('status', EventTicket::STATUS_CHECKED_IN)
            ->count();

        $connected = (int) EventContactExchange::where('link_id', $link->id)
            ->where('status', EventContactExchange::STATUS_ACCEPTED)
            ->count();

        // Nobody opted in means the networking stage was never offered,
        // which is a different thing from being offered and ignored. A
        // bare 0 would read as the second.
        $optedIn = (int) EventDiscoverability::where('link_id', $link->id)->count();

        $stages = [
            self::stage('opened',    'Opened the page', $opened,    null),
            self::stage('responded', 'Responded',       $responded, $opened),
            self::stage('coming',    'Coming',          $coming,    $responded),
            self::stage('arrived',   'Arrived',         $arrived,   $coming),
            self::stage('connected', 'Connected',       $connected, $arrived, $optedIn > 0),
        ];

        return [
            'stages'    => $stages,
            // Plus-ones are heads, not tickets. Reported, never summed in.
            'headcount' => $coming + (int) Rsvp::where('link_id', $link->id)
                ->where('response', 'yes')
                ->sum('plus_ones'),
            'notes'     => self::notes($link, $responded, $coming, $optedIn),
        ];
    }

    /**
     * One stage. `rate` is the share of the PREVIOUS stage that reached
     * this one, and is null rather than 0 when the previous stage is
     * empty -- "0% of nobody" is not a fact, and a zero drawn like a
     * measurement is how a dashboard starts lying quietly.
     */
    private static function stage(string $key, string $label, int $count, ?int $previous, bool $applicable = true): array
    {
        // (float) is not decoration. PHP's / returns an INT when both
        // operands are ints and the division is exact, so a stage that
        // kept everybody gave 1 while every other stage gave a float.
        // The type varied with the data, which a consumer formatting or
        // serialising this would eventually trip over.
        $rate = ($previous === null || $previous === 0) ? null : (float) ($count / $previous);

        return [
            'key'        => $key,
            'label'      => $label,
            'count'      => $count,
            'rate'       => $rate,
            'applicable' => $applicable,
        ];
    }

    /**
     * Things a reader would otherwise have to infer, and would get
     * wrong. Each one is only emitted when it is true of this event.
     *
     * @return array<int, string>
     */
    private static function notes(Link $link, int $responded, int $coming, int $optedIn): array
    {
        $notes = [];

        $paid = (int) EventTicket::where('link_id', $link->id)
            ->whereNotNull('tier_id')
            ->whereIn('status', [EventTicket::STATUS_VALID, EventTicket::STATUS_CHECKED_IN])
            ->count();

        if ($coming > 0 && $paid > 0 && $paid < $coming) {
            $notes[] = sprintf('%d of the %d coming bought a ticket; the rest are confirmed RSVPs.', $paid, $coming);
        }

        $declined = (int) Rsvp::where('link_id', $link->id)->where('response', '!=', 'yes')->count();
        if ($declined > 0) {
            $notes[] = sprintf('%d of the %d who responded are not coming.', $declined, $responded);
        }

        if ($optedIn === 0) {
            $notes[] = 'Nobody turned on "Who else is here", so there was nothing to connect through.';
        }

        $cancelled = (int) EventTicket::where('link_id', $link->id)
            ->whereIn('status', [EventTicket::STATUS_CANCELLED, EventTicket::STATUS_REFUNDED])
            ->count();
        if ($cancelled > 0) {
            $notes[] = sprintf('%d ticket(s) were cancelled or refunded and are not counted above.', $cancelled);
        }

        return $notes;
    }
}
