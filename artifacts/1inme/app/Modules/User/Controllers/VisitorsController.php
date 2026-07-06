<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Link;
use App\Modules\User\Support\AnalyticsRangeResolver;
use App\Modules\User\Support\LinkTypeCategories;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Account-wide Visitors page (Task #3812) — totals + new/returning + a daily
 * trend + a visitors-by-link-type breakdown + a source breakdown, across ALL
 * of the creator's links, filterable by link type and date range (presets +
 * custom, both clamped to the plan's stats retention).
 *
 * This intentionally mirrors the per-link VisitorAnalyticsController's
 * "first click on THIS link before the window started = returning" logic,
 * generalized to "first click on ANY of the owner's (filtered) links".
 */
class VisitorsController extends Controller
{
    public function index(Request $request)
    {
        $owner = workspace_owner();

        [$startDate, $endDate, $period] = AnalyticsRangeResolver::resolve(
            $request,
            $owner->statsRetentionDays()
        );

        $typeFilter = $request->query('type', 'all');

        $availableTypes = Link::query()
            ->where('user_id', $owner->id)
            ->select('type')
            ->distinct()
            ->orderBy('type')
            ->pluck('type');

        $linksQuery = Link::query()->where('user_id', $owner->id);
        if ($typeFilter !== 'all') {
            $linksQuery->where('type', $typeFilter);
        }
        $linkIds = $linksQuery->pluck('id');

        if ($linkIds->isEmpty()) {
            return view('user.visitors.account', [
                'period'          => $period,
                'startDate'       => $startDate,
                'endDate'         => $endDate,
                'typeFilter'      => $typeFilter,
                'availableTypes'  => $availableTypes,
                'totalVisitors'   => 0,
                'newCount'        => 0,
                'returningCount'  => 0,
                'dailySeries'     => collect(),
                'typeBreakdown'   => collect(),
                'sourceBreakdown' => collect(),
                'hasLinks'        => Link::where('user_id', $owner->id)->exists(),
            ]);
        }

        $inRangeIps = DB::table('link_clicks')
            ->whereIn('link_id', $linkIds)
            ->whereNull('block_id')
            ->where('is_bot', false)
            ->where('clicked_at', '>=', $startDate)
            ->where('clicked_at', '<=', $endDate)
            ->distinct()
            ->pluck('ip_address')
            ->filter()
            ->values();

        $totalVisitors = $inRangeIps->count();

        // First-ever click per IP across the FILTERED link set's full
        // history, so "returning" means "had visited one of these links
        // before this window", consistent with the per-link page.
        $firstSeen = $inRangeIps->isEmpty() ? collect() : DB::table('link_clicks')
            ->selectRaw('ip_address, MIN(clicked_at) as first_seen')
            ->whereIn('link_id', $linkIds)
            ->whereNull('block_id')
            ->where('is_bot', false)
            ->whereIn('ip_address', $inRangeIps->all())
            ->groupBy('ip_address')
            ->pluck('first_seen', 'ip_address');

        $returningCount = 0;
        foreach ($inRangeIps as $ip) {
            $fs = $firstSeen[$ip] ?? null;
            if ($fs && Carbon::parse($fs)->lt($startDate)) {
                $returningCount++;
            }
        }
        $newCount = max(0, $totalVisitors - $returningCount);

        // Daily trend: unique visitors + returning count per day.
        $perDay = DB::table('link_clicks')
            ->selectRaw('DATE(clicked_at) as d, ip_address')
            ->whereIn('link_id', $linkIds)
            ->whereNull('block_id')
            ->where('is_bot', false)
            ->where('clicked_at', '>=', $startDate)
            ->where('clicked_at', '<=', $endDate)
            ->get();

        $bucket = [];
        foreach ($perDay as $row) {
            $day = $row->d;
            if (!isset($bucket[$day])) $bucket[$day] = ['ips' => [], 'returning_ips' => []];
            if (!in_array($row->ip_address, $bucket[$day]['ips'])) {
                $bucket[$day]['ips'][] = $row->ip_address;
                $fs = $firstSeen[$row->ip_address] ?? null;
                if ($fs && Carbon::parse($fs)->lt(Carbon::parse($day))) {
                    $bucket[$day]['returning_ips'][] = $row->ip_address;
                }
            }
        }
        ksort($bucket);
        $dailySeries = collect($bucket)->map(function ($v, $d) {
            $u = count($v['ips']);
            $r = count($v['returning_ips']);
            return (object) [
                'd'         => $d,
                'visitors'  => $u,
                'new'       => max(0, $u - $r),
                'returning' => $r,
            ];
        })->values();

        // Visitors-by-link-type breakdown (unique IPs per type, within the
        // already-filtered link set — so picking a single type simply shows
        // one bar, which matches "the filter narrows every chart").
        $typeBreakdown = DB::table('link_clicks')
            ->join('links', 'links.id', '=', 'link_clicks.link_id')
            ->whereIn('link_clicks.link_id', $linkIds)
            ->whereNull('link_clicks.block_id')
            ->where('link_clicks.is_bot', false)
            ->where('link_clicks.clicked_at', '>=', $startDate)
            ->where('link_clicks.clicked_at', '<=', $endDate)
            ->selectRaw('links.type as type, COUNT(DISTINCT link_clicks.ip_address) as n')
            ->groupBy('links.type')
            ->orderByDesc('n')
            ->get()
            ->map(fn ($r) => (object) ['type' => $r->type, 'label' => Link::typeLabel($r->type), 'n' => (int) $r->n]);

        $sourceBreakdown = DB::table('link_clicks')
            ->whereIn('link_id', $linkIds)
            ->whereNull('block_id')
            ->where('is_bot', false)
            ->where('clicked_at', '>=', $startDate)
            ->where('clicked_at', '<=', $endDate)
            ->selectRaw("COALESCE(NULLIF(source, ''), 'web') as src, COUNT(*) as n")
            ->groupBy('src')
            ->orderByDesc('n')
            ->limit(8)
            ->get();

        return view('user.visitors.account', [
            'period'          => $period,
            'startDate'       => $startDate,
            'endDate'         => $endDate,
            'typeFilter'      => $typeFilter,
            'availableTypes'  => $availableTypes,
            'totalVisitors'   => $totalVisitors,
            'newCount'        => $newCount,
            'returningCount'  => $returningCount,
            'dailySeries'     => $dailySeries,
            'typeBreakdown'   => $typeBreakdown,
            'sourceBreakdown' => $sourceBreakdown,
            'hasLinks'        => true,
        ]);
    }
}
