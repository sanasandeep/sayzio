<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Backlink;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Web-dashboard view of the backlink radar. Same data the browser
 * extension popup reads via the JSON API in
 * App\Modules\Api\Controllers\BacklinkController, but rendered as a
 * Blade page so creators on a different browser / mobile can still
 * see the rows. Reads/writes the same `backlinks` table so popup and
 * dashboard stay in sync automatically.
 */
class BacklinkController extends Controller
{
    protected const PROPERTY_TYPES = ['short_link', 'biolink_username', 'custom_domain'];

    public function index(Request $request)
    {
        $data = $request->validate([
            'days'          => ['nullable', 'integer', 'in:7,30,90'],
            'property_type' => ['nullable', Rule::in(self::PROPERTY_TYPES)],
        ]);

        $userId = $request->user()->id;
        $days   = isset($data['days']) ? (int) $data['days'] : null;
        $type   = $data['property_type'] ?? null;

        $q = Backlink::where('user_id', $userId);
        if ($days) {
            $q->where('first_seen_at', '>=', now()->subDays($days));
        }
        if ($type) {
            $q->where('matched_property_type', $type);
        }

        $backlinks = $q->orderByDesc('first_seen_at')
            ->paginate(50)
            ->withQueryString();

        $totalAll      = Backlink::where('user_id', $userId)->count();
        $totalThisWeek = Backlink::where('user_id', $userId)
            ->where('first_seen_at', '>=', now()->subDays(7))
            ->count();

        return view('user.backlinks.index', [
            'backlinks'      => $backlinks,
            'days'           => $days,
            'propertyType'   => $type,
            'totalAll'       => $totalAll,
            'totalThisWeek'  => $totalThisWeek,
            'propertyTypes'  => self::PROPERTY_TYPES,
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $row = Backlink::where('user_id', $request->user()->id)->find($id);
        if ($row) {
            $row->delete();
        }
        return redirect()->back()->with('status', 'Backlink removed.');
    }

    public function export(Request $request): StreamedResponse
    {
        $request->validate([
            'days'          => ['nullable', 'integer', 'in:7,30,90'],
            'property_type' => ['nullable', Rule::in(self::PROPERTY_TYPES)],
        ]);

        $userId = $request->user()->id;
        $days   = $request->integer('days');
        $type   = $request->string('property_type')->toString() ?: null;

        $filename = 'backlinks-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($userId, $days, $type) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'first_seen_at', 'page_url', 'page_title', 'anchor_text',
                'matched_url', 'matched_property_type', 'matched_property_value',
            ]);
            $q = Backlink::where('user_id', $userId);
            if ($days) $q->where('first_seen_at', '>=', now()->subDays($days));
            if ($type) $q->where('matched_property_type', $type);
            $q->orderByDesc('first_seen_at')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    fputcsv($out, [
                        optional($r->first_seen_at)->toIso8601String(),
                        $r->page_url,
                        $r->page_title,
                        $r->anchor_text,
                        $r->matched_url,
                        $r->matched_property_type,
                        $r->matched_property_value,
                    ]);
                }
            });
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
