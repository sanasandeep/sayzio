<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Rsvp;
use Illuminate\Http\Request;
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
}
