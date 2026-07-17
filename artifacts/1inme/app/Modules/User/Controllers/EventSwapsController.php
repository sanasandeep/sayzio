<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Link;
use App\Services\Events\EventContactSwaps;
use Illuminate\Http\Request;

/**
 * Task #5052 — web (session-auth) surface for "My swaps": the public event
 * page shows a signed-in attendee their own pending/accepted contact-swap
 * requests at that event and lets them withdraw a pending one they sent.
 *
 * Both endpoints delegate to EventContactSwaps so the JSON shape and rules
 * stay identical to the mobile /api/v1 endpoints.
 */
class EventSwapsController extends Controller
{
    /** JSON list of the viewer's swaps at an event. */
    public function index(Request $request, string $alias)
    {
        // The event usually belongs to ANOTHER user's workspace — bypass the
        // viewer-bound workspace global scope or the lookup 404s for attendees.
        $link = Link::withoutGlobalScopes()->where('alias', $alias)->where('type', 'ics')->first();
        abort_if(!$link, 404);

        $items = EventContactSwaps::listForUser($request->user(), $link);

        return response()->json(['items' => $items, 'total' => count($items)]);
    }

    /** Withdraw a pending swap request the viewer sent. */
    public function cancel(Request $request, int $exchangeId)
    {
        $error = EventContactSwaps::cancel($request->user(), $exchangeId);

        return match ($error) {
            'not_found'        => response()->json(['error' => ['message' => 'Exchange request not found.', 'code' => 'not_found']], 404),
            'not_sender'       => response()->json(['error' => ['message' => 'Only the sender can withdraw this request.', 'code' => 'not_sender']], 403),
            'already_resolved' => response()->json(['error' => ['message' => 'This request has already been resolved and can no longer be withdrawn.', 'code' => 'already_resolved']], 409),
            default            => response()->json(['exchange_id' => $exchangeId, 'cancelled' => true]),
        };
    }
}
