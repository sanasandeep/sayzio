<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Link;
use App\Modules\User\Services\EventBroadcastService;
use Illuminate\Http\Request;

/**
 * Organizer web surface for "Message guests" broadcasts: the compose form
 * (with live per-audience recipient counts), the send action, and a live
 * count endpoint the form polls when the audience changes.
 */
class EventBroadcastController extends Controller
{
    public function __construct(private EventBroadcastService $service) {}

    public function index(Request $request, Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);
        abort_if($link->type !== 'ics', 404);

        $counts     = $this->service->audienceCounts($link);
        $broadcasts = $this->service->history($link);
        $audiences  = \App\Modules\User\Models\EventBroadcast::AUDIENCES;

        return view('user.links.broadcast', compact('link', 'counts', 'broadcasts', 'audiences'));
    }

    /** JSON: live recipient count for the chosen audience. */
    public function count(Request $request, Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);
        abort_if($link->type !== 'ics', 404);

        $audience = (string) $request->query('audience', 'all_rsvps');
        if (!in_array($audience, EventBroadcastService::AUDIENCES, true)) {
            $audience = 'all_rsvps';
        }

        return response()->json(['audience' => $audience, 'count' => $this->service->recipientCount($link, $audience)]);
    }

    public function send(Request $request, Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);
        abort_if($link->type !== 'ics', 404);

        $data = $request->validate([
            'audience' => ['required', 'string', 'in:' . implode(',', EventBroadcastService::AUDIENCES)],
            'subject'  => ['required', 'string', 'max:200'],
            'message'  => ['required', 'string', 'max:5000'],
        ]);

        try {
            $broadcast = $this->service->send(
                $link,
                (int) workspace_owner_id(),
                $data['audience'],
                $data['subject'],
                $data['message'],
            );
        } catch (\App\Modules\User\Services\EventBroadcastLimitException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($broadcast->recipients_count === 0) {
            return back()->with('error', 'No guests matched that audience — nothing was sent.');
        }

        return back()->with('success', "Message sent to {$broadcast->recipients_count} guest(s).");
    }
}
