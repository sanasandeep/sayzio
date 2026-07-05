<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\EventTicket;
use App\Modules\User\Models\Link;
use Illuminate\Http\Request;

/**
 * Door check-in scanner (Task #3589). Web camera-based QR scanner posts
 * the scanned code (or a manually-typed one) to `scan`, which validates
 * and flips the ticket to `checked_in`. `lookup` renders a lightweight,
 * unauthenticated-safe status page for the QR the buyer holds (used as the
 * QR payload target) — it reveals nothing except pass/fail once scanned by
 * an authenticated door staffer.
 */
class EventCheckinController extends Controller
{
    public function scanner(Request $request, Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);
        abort_if($link->type !== 'ics', 404);

        return view('user.links.event-checkin', compact('link'));
    }

    public function scan(Request $request, Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);
        abort_if($link->type !== 'ics', 404);

        $data = $request->validate(['code' => ['required', 'string', 'max:64']]);

        return response()->json($this->checkIn($link, $data['code'], $request->user()?->id));
    }

    /**
     * The QR on the ticket links here so a staffer who scans it with a
     * generic camera app (not the in-app scanner) lands on a page that
     * performs the same check-in when they're signed in as the owner.
     */
    public function lookup(Request $request, Link $link, string $code)
    {
        abort_if($link->type !== 'ics', 404);

        $isOwner = $request->user() && $link->user_id === workspace_owner_id();
        $ticket = EventTicket::where('link_id', $link->id)->where('code', $code)->with('tier')->first();

        $result = null;
        if ($isOwner && $ticket) {
            $result = $this->checkIn($link, $code, $request->user()->id);
        }

        return view('user.links.event-checkin-lookup', compact('link', 'ticket', 'isOwner', 'result'));
    }

    private function checkIn(Link $link, string $code, ?int $staffId): array
    {
        $ticket = EventTicket::where('link_id', $link->id)->where('code', $code)->with('tier')->first();

        if (!$ticket) {
            return ['ok' => false, 'status' => 'not_found', 'message' => 'No ticket matches that code.'];
        }
        if ($ticket->status === EventTicket::STATUS_CHECKED_IN) {
            return [
                'ok' => false, 'status' => 'already_checked_in',
                'message' => 'Already checked in at ' . optional($ticket->checked_in_at)->format('g:i A'),
                'ticket' => $this->ticketSummary($ticket),
            ];
        }
        if (in_array($ticket->status, [EventTicket::STATUS_CANCELLED, EventTicket::STATUS_REFUNDED], true)) {
            return ['ok' => false, 'status' => $ticket->status, 'message' => 'This ticket was ' . $ticket->status . ' and is not valid.'];
        }

        $ticket->update([
            'status'        => EventTicket::STATUS_CHECKED_IN,
            'checked_in_at' => now(),
            'checked_in_by' => $staffId,
        ]);

        return [
            'ok' => true, 'status' => 'checked_in', 'message' => 'Checked in successfully.',
            'ticket' => $this->ticketSummary($ticket),
        ];
    }

    private function ticketSummary(EventTicket $ticket): array
    {
        return [
            'attendee_name' => $ticket->attendee_name,
            'tier_name'     => $ticket->tier?->name,
            'quantity'      => $ticket->quantity,
            'code'          => $ticket->code,
        ];
    }
}
