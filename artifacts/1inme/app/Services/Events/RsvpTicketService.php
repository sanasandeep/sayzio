<?php

namespace App\Services\Events;

use App\Modules\User\Models\EventTicket;
use App\Modules\User\Models\Rsvp;

/**
 * Task #3606 — free RSVP attendees get a unique QR check-in ticket, just
 * like paid tier ticket buyers, without a tier (`tier_id` is null,
 * `rsvp_id` links back). `sync()` is the single place that keeps an
 * RSVP's ticket in lockstep with its current response/status: confirmed
 * + "yes" gets (or keeps/revives) a valid ticket; anything else
 * (waitlisted, maybe, not going, cancelled) cancels it rather than
 * deleting it, so a previously-issued code never gets silently reused.
 * Called from both the initial submit and the guest self-manage page.
 */
class RsvpTicketService
{
    public static function sync(Rsvp $rsvp): ?EventTicket
    {
        $rsvp->loadMissing('ticket');
        $ticket = $rsvp->ticket;
        $wantsTicket = $rsvp->status === 'confirmed' && $rsvp->response === 'yes';

        if (!$wantsTicket) {
            if ($ticket && !in_array($ticket->status, [EventTicket::STATUS_CANCELLED, EventTicket::STATUS_REFUNDED], true)) {
                $ticket->update(['status' => EventTicket::STATUS_CANCELLED]);
            }
            return $ticket;
        }

        if ($ticket) {
            // Keep attendee details fresh (name/email/phone edits on the
            // manage page) and revive a ticket cancelled by an earlier
            // "no"/waitlist edit rather than minting a second QR.
            $ticket->fill([
                'attendee_name'  => $rsvp->name,
                'attendee_email' => $rsvp->email,
                'attendee_phone' => $rsvp->phone,
            ]);
            if ($ticket->status === EventTicket::STATUS_CANCELLED) {
                $ticket->status = EventTicket::STATUS_VALID;
            }
            if ($ticket->isDirty()) {
                $ticket->save();
            }
            return $ticket;
        }

        return EventTicket::create([
            'tier_id'        => null,
            'rsvp_id'        => $rsvp->id,
            'link_id'        => $rsvp->link_id,
            'buyer_user_id'  => null,
            'attendee_name'  => $rsvp->name,
            'attendee_email' => $rsvp->email,
            'attendee_phone' => $rsvp->phone,
            'quantity'       => 1,
            'price_cents'    => 0,
            'currency'       => 'USD',
            'code'           => EventTicket::generateCode(),
            'status'         => EventTicket::STATUS_VALID,
            'gateway'        => 'rsvp',
        ]);
    }
}
