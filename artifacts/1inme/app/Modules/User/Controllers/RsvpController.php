<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Rsvp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RsvpController extends Controller
{
    public function index(Request $request, Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);
        abort_if($link->type !== 'ics', 404);

        $rsvps = $link->rsvps()->orderByDesc('created_at')->paginate(50);

        $counts = [
            'yes'   => $link->rsvps()->where('response', 'yes')->where('status', '!=', 'cancelled')
                       ->sum(DB::raw('plus_ones + 1')),
            'maybe' => $link->rsvps()->where('response', 'maybe')->count(),
            'no'    => $link->rsvps()->where('response', 'no')->count(),
            'waitlist' => $link->rsvps()->where('status', 'waitlist')->count(),
            'total' => $link->rsvps()->count(),
        ];

        $s = (array) ($link->settings ?? []);
        $rsvpSettings = (array) ($s['rsvp_settings'] ?? []);

        return view('user.links.rsvps', compact('link', 'rsvps', 'counts', 'rsvpSettings'));
    }

    public function export(Request $request, Link $link): StreamedResponse
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);
        abort_if($link->type !== 'ics', 404);

        $filename = 'rsvps-' . $link->alias . '-' . now()->format('Ymd-His') . '.csv';
        $s = (array) ($link->settings ?? []);
        $questions = collect((array)($s['rsvp_settings']['questions'] ?? []))->pluck('label')->all();

        return response()->streamDownload(function () use ($link, $questions) {
            $out = fopen('php://output', 'w');
            $headers = ['Name', 'Email', 'Phone', 'Company', 'Role', 'Response', 'Status',
                        'Plus ones', 'Occurrences', 'Message', 'Source', 'Submitted at'];
            foreach ($questions as $q) $headers[] = 'Q: ' . $q;
            fputcsv($out, $headers);

            $link->rsvps()->orderBy('created_at')->chunk(500, function ($rows) use ($out, $questions) {
                foreach ($rows as $r) {
                    $row = [
                        $r->name, $r->email, $r->phone, $r->company, $r->role,
                        $r->response, $r->status,
                        $r->plus_ones,
                        is_array($r->occurrences) ? implode('; ', $r->occurrences) : '',
                        $r->message, $r->source,
                        $r->created_at?->toDateTimeString(),
                    ];
                    foreach ($questions as $q) {
                        $val = $r->answers[$q] ?? null;
                        $row[] = is_array($val) ? implode(', ', $val) : (string) ($val ?? '');
                    }
                    fputcsv($out, $row);
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function destroy(Request $request, Link $link, Rsvp $rsvp)
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);
        abort_if($rsvp->link_id !== $link->id, 404);
        $rsvp->delete();
        return back()->with('success', 'RSVP removed.');
    }

    /**
     * Promote a waitlisted RSVP to confirmed. Owner-driven action that
     * runs after a confirmed guest cancels and frees a seat.
     */
    public function promote(Request $request, Link $link, Rsvp $rsvp)
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);
        abort_if($rsvp->link_id !== $link->id, 404);
        if ($rsvp->status !== 'waitlist') {
            return back()->with('error', 'That RSVP is not on the waitlist.');
        }
        $rsvp->update(['status' => 'confirmed']);
        // Task #3606: a waitlist promotion is a status change like any
        // other and must mint/revive the RSVP's QR check-in ticket, same as
        // the initial submit and guest self-manage paths.
        \App\Services\Events\RsvpTicketService::sync($rsvp->fresh());
        return back()->with('success', "Promoted {$rsvp->name} from the waitlist.");
    }

    public function eraseVoter(Request $request, Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);
        abort_if($link->type !== 'ics', 404);

        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
        ]);

        $needle = trim($data['identifier']);
        $creatorId = workspace_owner_id();

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
