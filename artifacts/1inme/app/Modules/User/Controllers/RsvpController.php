<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Rsvp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RsvpController extends Controller
{
    public function index(Request $request, Link $link)
    {
        abort_if($link->user_id !== $request->user()->id, 403);
        abort_if($link->type !== 'ics', 404);

        $rsvps = $link->rsvps()->orderByDesc('created_at')->paginate(50);

        $counts = [
            'yes'   => $link->rsvps()->where('response', 'yes')->sum('plus_ones')
                       + $link->rsvps()->where('response', 'yes')->count(),
            'maybe' => $link->rsvps()->where('response', 'maybe')->count(),
            'no'    => $link->rsvps()->where('response', 'no')->count(),
            'total' => $link->rsvps()->count(),
        ];

        return view('user.links.rsvps', compact('link', 'rsvps', 'counts'));
    }

    public function export(Request $request, Link $link): StreamedResponse
    {
        abort_if($link->user_id !== $request->user()->id, 403);
        abort_if($link->type !== 'ics', 404);

        $filename = 'rsvps-' . $link->alias . '-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($link) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name', 'Email', 'Phone', 'Response', 'Plus ones', 'Message', 'Source', 'Submitted at']);
            $link->rsvps()->orderBy('created_at')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    fputcsv($out, [
                        $r->name, $r->email, $r->phone, $r->response,
                        $r->plus_ones, $r->message, $r->source,
                        $r->created_at?->toDateTimeString(),
                    ]);
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function destroy(Request $request, Link $link, Rsvp $rsvp)
    {
        abort_if($link->user_id !== $request->user()->id, 403);
        abort_if($rsvp->link_id !== $link->id, 404);
        $rsvp->delete();
        return back()->with('success', 'RSVP removed.');
    }

    /**
     * Erase every RSVP tied to a single guest (by email) across ALL
     * Event Invite links owned by the current creator. Mirrors the
     * poll-vote eraser for GDPR-style takedown requests. RSVPs only
     * carry an email, so that's the matching identifier.
     */
    public function eraseVoter(Request $request, Link $link)
    {
        abort_if($link->user_id !== $request->user()->id, 403);
        abort_if($link->type !== 'ics', 404);

        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
        ]);

        $needle = trim($data['identifier']);
        $creatorId = $request->user()->id;

        // Reach across every workspace owned by this creator.
        $ownedLinkIds = Link::query()->withoutGlobalScope('workspace')
            ->where('user_id', $creatorId)
            ->where('type', 'ics')
            ->pluck('id');

        $query = Rsvp::query()
            ->whereIn('link_id', $ownedLinkIds)
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($needle)]);

        $count = (clone $query)->count();

        if ($count === 0) {
            return back()->with('error', 'No RSVPs matched “' . e($needle) . '”.');
        }

        $query->delete();

        Log::info('rsvp guest erased', [
            'creator_id' => $creatorId,
            'identifier' => $needle,
            'removed'    => $count,
            'from_link'  => $link->id,
        ]);

        return back()->with('success', "Erased {$count} RSVP(s) tied to “{$needle}”.");
    }
}
