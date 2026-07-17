<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\EventContactExchange;
use App\Modules\User\Models\EventDiscoverability;
use App\Modules\User\Models\Link;
use App\Services\Events\EventPeopleStats;
use Illuminate\Http\Request;

/**
 * Task #5010 — Organizer "People / Connections" dashboard for an event.
 *
 * Shows how attendees are using the contact-exchange feature: how many
 * opted in to be discoverable, how many exchange requests were sent and
 * how many were accepted. The page polls `stats` for near-real-time
 * updates while the event is live (same pattern as the door check-in
 * progress card).
 */
class EventPeopleController extends Controller
{
    public function dashboard(Request $request, Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);
        abort_if($link->type !== 'ics', 404);

        return view('user.links.event-people', compact('link'));
    }

    /** JSON stats polled by the dashboard. */
    public function stats(Request $request, Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);
        abort_if($link->type !== 'ics', 404);

        return response()->json(EventPeopleStats::for($link));
    }
}
