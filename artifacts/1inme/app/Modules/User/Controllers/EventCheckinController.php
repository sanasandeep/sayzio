<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\EventTicket;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Workspace;
use App\Services\Events\EventCheckinProgress;
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
     * Live door progress (checked-in / sold, overall + per tier) polled by
     * the scanner so every staffer's screen updates as scans land across
     * devices, without a manual refresh.
     */
    public function progress(Request $request, Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);
        abort_if($link->type !== 'ics', 404);

        return response()->json(EventCheckinProgress::for($link));
    }

    /**
     * The QR on the ticket links here so a staffer who scans it with a
     * generic camera app (not the in-app scanner) lands on a page that
     * performs the same check-in when they're signed in as the owner.
     */
    public function lookup(Request $request, Link $link, string $code)
    {
        abort_if($link->type !== 'ics', 404);

        $isOwner = $request->user() && $this->canCheckIn($request->user(), $link);
        $ticket = EventTicket::where('link_id', $link->id)->where('code', $code)->with('tier')->first();

        $result = null;
        if ($isOwner && $ticket) {
            $result = $this->checkIn($link, $code, $request->user()->id);
        }

        return view('user.links.event-checkin-lookup', compact('link', 'ticket', 'isOwner', 'result'));
    }

    /**
     * Task #3606: check-in authorization must extend from "owner only" to
     * any authorized team member with workspace access to this link (the
     * scanner/scan/progress routes already enforce this via the
     * `workspace.can:links.*` route middleware; `lookup` is reachable
     * unauthenticated as the QR landing page, so it has to check itself).
     */
    private function canCheckIn($user, Link $link): bool
    {
        if ((int) $link->user_id === (int) $user->id) return true;
        if (!$link->workspace_id) return false;
        $workspace = Workspace::find($link->workspace_id);
        return $workspace ? $user->canInWorkspace($workspace, 'links.edit') : false;
    }

    private function checkIn(Link $link, string $code, ?int $staffId): array
    {
        $ticket = EventTicket::where('link_id', $link->id)->where('code', $code)->with('tier')->first();

        if (!$ticket) {
            return ['ok' => false, 'status' => 'not_found', 'message' => 'No ticket matches that code.'];
        }
        if ($ticket->status === EventTicket::STATUS_CHECKED_IN) {
            $ticket->loadMissing('checkedInBy');
            $scannerName = $ticket->checkedInBy?->name;
            return [
                'ok' => false, 'status' => 'already_checked_in',
                'message' => 'Already checked in at ' . optional($ticket->checked_in_at)->format('g:i A')
                    . ($scannerName ? ' by ' . $scannerName : ''),
                'ticket' => $this->ticketSummary($ticket),
            ];
        }
        if (in_array($ticket->status, [EventTicket::STATUS_CANCELLED, EventTicket::STATUS_REFUNDED], true)) {
            return ['ok' => false, 'status' => $ticket->status, 'message' => 'This ticket was ' . $ticket->status . ' and is not valid.'];
        }

        $requiredBadgeId = $link->icsData?->required_badge_id;
        if ($requiredBadgeId && !$this->attendeeHasBadge($ticket, $requiredBadgeId)) {
            return [
                'ok' => false, 'status' => 'badge_required',
                'message' => 'This event requires a badge that the attendee does not hold. Entry denied.',
                'ticket' => $this->ticketSummary($ticket),
            ];
        }

        $ticket->update([
            'status'        => EventTicket::STATUS_CHECKED_IN,
            'checked_in_at' => now(),
            'checked_in_by' => $staffId,
        ]);

        $this->awardAttendanceBadge($link, $ticket);

        return [
            'ok' => true, 'status' => 'checked_in', 'message' => 'Checked in successfully.',
            'ticket' => $this->ticketSummary($ticket),
        ];
    }

    /**
     * Badge-powered entry rules: when an event has a `required_badge_id`,
     * check-in is denied unless the attendee (resolved by email against a
     * registered account) already holds that badge. Guest attendees with
     * no matching account never satisfy a required badge.
     */
    private function attendeeHasBadge(EventTicket $ticket, int $badgeId): bool
    {
        $user = $ticket->attendee_email
            ? \App\Modules\User\Models\User::where('email', $ticket->attendee_email)->first()
            : null;
        if (!$user) return false;

        return $user->accountBadges()->where('account_badges.id', $badgeId)->exists();
    }

    /**
     * Badge-powered invites: checking in an attendee who is a
     * registered account and matches the event's `award_badge_id` attaches
     * that badge, if not already held. Guest (non-account) attendees have
     * nothing to attach a badge to and are silently skipped.
     */
    private function awardAttendanceBadge(Link $link, EventTicket $ticket): void
    {
        $awardBadgeId = $link->icsData?->award_badge_id;
        if (!$awardBadgeId) return;

        $user = $ticket->attendee_email
            ? \App\Modules\User\Models\User::where('email', $ticket->attendee_email)->first()
            : null;
        if (!$user) return;

        if (!$user->accountBadges()->where('account_badges.id', $awardBadgeId)->exists()) {
            $user->accountBadges()->attach($awardBadgeId);
        }
    }

    private function ticketSummary(EventTicket $ticket): array
    {
        $ticket->loadMissing('checkedInBy');

        return [
            'attendee_name'  => $ticket->attendee_name,
            'tier_name'      => $ticket->tier?->name ?? ($ticket->rsvp_id ? 'RSVP' : null),
            'quantity'       => $ticket->quantity,
            'code'           => $ticket->code,
            'checked_in_at'  => optional($ticket->checked_in_at)->toIso8601String(),
            'checked_in_by'  => $ticket->checkedInBy?->name,
        ];
    }
}
