<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkClick;
use App\Modules\User\Models\NfcWrite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VisitorAnalyticsController extends Controller
{
    public function index(Request $request, Link $link)
    {
        abort_unless($link->user_id === auth()->id() || auth()->user()->isSuperAdmin(), 403);

        $period = (int) $request->query('days', 30);
        $since = now()->subDays($period);

        // Compute per-IP first-seen across the full history of THIS link.
        // A visitor in the selected period is "returning" iff their first-ever
        // click on this link happened strictly before the period started.
        $inRangeIps = DB::table('link_clicks')
            ->where('link_id', $link->id)
            ->whereNull('block_id')
            ->where('clicked_at', '>=', $since)
            ->distinct()
            ->pluck('ip_address')
            ->filter()
            ->values();

        $totalVisitors = $inRangeIps->count();

        $firstSeen = $inRangeIps->isEmpty() ? collect() : DB::table('link_clicks')
            ->selectRaw('ip_address, MIN(clicked_at) as first_seen')
            ->where('link_id', $link->id)
            ->whereNull('block_id')
            ->whereIn('ip_address', $inRangeIps->all())
            ->groupBy('ip_address')
            ->pluck('first_seen', 'ip_address');

        $returningCount = 0;
        foreach ($inRangeIps as $ip) {
            $fs = $firstSeen[$ip] ?? null;
            if ($fs && \Carbon\Carbon::parse($fs)->lt($since)) $returningCount++;
        }
        $newCount = max(0, $totalVisitors - $returningCount);

        // Daily series within the selected period: for each day, count unique
        // IPs whose first-ever click on this link was before that day.
        $perDay = DB::table('link_clicks')
            ->selectRaw('DATE(clicked_at) as d, ip_address')
            ->where('link_id', $link->id)
            ->whereNull('block_id')
            ->where('clicked_at', '>=', $since)
            ->get();

        $bucket = [];
        foreach ($perDay as $row) {
            $day = $row->d;
            if (!isset($bucket[$day])) $bucket[$day] = ['ips' => [], 'returning_ips' => []];
            if (!in_array($row->ip_address, $bucket[$day]['ips'])) {
                $bucket[$day]['ips'][] = $row->ip_address;
                $fs = $firstSeen[$row->ip_address] ?? null;
                if ($fs && \Carbon\Carbon::parse($fs)->lt(\Carbon\Carbon::parse($day))) {
                    $bucket[$day]['returning_ips'][] = $row->ip_address;
                }
            }
        }
        ksort($bucket);
        $dailySeries = collect($bucket)->map(function ($v, $d) {
            $u = count($v['ips']);
            $r = count($v['returning_ips']);
            return (object)[
                'd'             => $d,
                'visitors'      => $u,
                'returning'     => $r,
                'returning_pct' => $u > 0 ? round(($r / $u) * 100, 1) : 0,
            ];
        })->values();

        // Identified visitors (logged in)
        $identified = DB::table('link_clicks')
            ->join('users', 'users.id', '=', 'link_clicks.viewer_user_id')
            ->select(
                'users.id',
                'users.name',
                'users.email',
                'users.avatar',
                DB::raw('MIN(link_clicks.clicked_at) as first_seen'),
                DB::raw('MAX(link_clicks.clicked_at) as last_seen'),
                DB::raw('COUNT(*) as visit_count')
            )
            ->where('link_clicks.link_id', $link->id)
            ->whereNotNull('link_clicks.viewer_user_id')
            ->where('link_clicks.clicked_at', '>=', $since)
            ->groupBy('users.id', 'users.name', 'users.email', 'users.avatar')
            ->orderByDesc('visit_count')
            ->limit(100)
            ->get();

        $followerSet = Follow::where('creator_id', $link->user_id)
            ->whereIn('follower_id', $identified->pluck('id'))
            ->pluck('follower_id')
            ->flip();

        // NFC writes for this link — counter + recent rows for the
        // history strip on the analytics page. Full history is on the
        // dedicated NFC history page (links/{link}/nfc-writes).
        $nfcCount  = NfcWrite::where('link_id', $link->id)->count();
        $nfcRecent = NfcWrite::where('link_id', $link->id)
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return view('user.visitors.index', [
            'link'           => $link,
            'period'         => $period,
            'totalVisitors'  => $totalVisitors,
            'returningCount' => $returningCount,
            'newCount'       => $newCount,
            'dailySeries'    => $dailySeries,
            'identified'     => $identified,
            'followerSet'    => $followerSet,
            'nfcCount'       => $nfcCount,
            'nfcRecent'      => $nfcRecent,
        ]);
    }

    public function nfcHistory(Request $request, Link $link)
    {
        abort_unless($link->user_id === auth()->id() || auth()->user()->isSuperAdmin(), 403);

        $writes = NfcWrite::where('link_id', $link->id)
            ->orderByDesc('id')
            ->paginate(50);

        return view('user.visitors.nfc-history', [
            'link'   => $link,
            'writes' => $writes,
            'total'  => NfcWrite::where('link_id', $link->id)->count(),
        ]);
    }
}
