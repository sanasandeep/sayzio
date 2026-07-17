<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkClick;
use App\Modules\User\Models\NfcWrite;
use App\Modules\User\Models\PageSession;
use App\Modules\User\Support\AnalyticsRangeResolver;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Mobile parity for the web Visitors analytics (Task #3812 → #3816).
 *
 * Mirrors the data shape of the web
 *   - User\Controllers\VisitorsController@index          (account-wide)
 *   - User\Controllers\VisitorAnalyticsController@index  (per-link)
 * so the Expo app can render the same totals, new/returning split, daily
 * trend, and breakdowns natively (no dependency on the web Chart.js bundle).
 *
 * The "returning" definition is intentionally identical to the web pages:
 * a visitor (unique IP) is returning iff their first-ever click on any of
 * the scoped link(s) happened strictly before the selected window started.
 * Both endpoints honour the same preset/custom date windows and the plan's
 * stats-retention clamp via the shared AnalyticsRangeResolver, and scope to
 * the signed-in creator's own links (the API path has no active-workspace
 * middleware, matching CreatorStatsApiController).
 */
class VisitorAnalyticsApiController extends Controller
{
    use ApiResponses;

    /**
     * Account-wide visitors across all of the creator's links, filterable by
     * link type + date range. Mirrors VisitorsController@index.
     */
    public function account(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return $this->unauthorized();
        }

        [$startDate, $endDate, $period] = AnalyticsRangeResolver::resolve(
            $request,
            $user->statsRetentionDays()
        );

        $typeFilter = (string) $request->query('type', 'all');

        $availableTypes = Link::query()
            ->where('user_id', $user->id)
            ->select('type')
            ->distinct()
            ->orderBy('type')
            ->pluck('type')
            ->map(fn ($t) => [
                'type'  => $t,
                'label' => Link::typeLabel($t),
            ])
            ->values();

        $linksQuery = Link::query()->where('user_id', $user->id);
        if ($typeFilter !== 'all') {
            $linksQuery->where('type', $typeFilter);
        }
        $linkIds = $linksQuery->pluck('id');

        $range = [
            'from'   => $startDate->format('Y-m-d'),
            'to'     => $endDate->format('Y-m-d'),
            'period' => $period,
        ];

        if ($linkIds->isEmpty()) {
            return $this->ok([
                'range'            => $range,
                'type'             => $typeFilter,
                'available_types'  => $availableTypes,
                'has_links'        => Link::where('user_id', $user->id)->exists(),
                'total_visitors'   => 0,
                'new_count'        => 0,
                'returning_count'  => 0,
                'daily_series'     => [],
                'type_breakdown'   => [],
                'source_breakdown' => [],
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

        $dailySeries = $this->dailySeries($linkIds, $firstSeen, $startDate, $endDate, false);

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
            ->map(fn ($r) => [
                'type'  => $r->type,
                'label' => Link::typeLabel($r->type),
                'n'     => (int) $r->n,
            ])
            ->values();

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
            ->get()
            ->map(fn ($r) => ['src' => $r->src, 'n' => (int) $r->n])
            ->values();

        return $this->ok([
            'range'            => $range,
            'type'             => $typeFilter,
            'available_types'  => $availableTypes,
            'has_links'        => true,
            'total_visitors'   => $totalVisitors,
            'new_count'        => $newCount,
            'returning_count'  => $returningCount,
            'daily_series'     => $dailySeries,
            'type_breakdown'   => $typeBreakdown,
            'source_breakdown' => $sourceBreakdown,
        ]);
    }

    /**
     * Per-link visitor insights. Mirrors VisitorAnalyticsController@index.
     */
    public function link(Request $request, int $id)
    {
        $user = $request->user();
        if (!$user) {
            return $this->unauthorized();
        }

        $link = Link::where('user_id', $user->id)->find($id);
        if (!$link) {
            return $this->notFound('Link not found');
        }

        [$startDate, $endDate, $period] = AnalyticsRangeResolver::resolve(
            $request,
            $user->statsRetentionDays()
        );
        $since = $startDate;
        $until = $endDate;

        $inRangeIps = DB::table('link_clicks')
            ->where('link_id', $link->id)
            ->where('is_bot', false)
            ->whereNull('block_id')
            ->where('clicked_at', '>=', $since)
            ->where('clicked_at', '<=', $until)
            ->distinct()
            ->pluck('ip_address')
            ->filter()
            ->values();

        $totalVisitors = $inRangeIps->count();

        $firstSeen = $inRangeIps->isEmpty() ? collect() : DB::table('link_clicks')
            ->selectRaw('ip_address, MIN(clicked_at) as first_seen')
            ->where('link_id', $link->id)
            ->where('is_bot', false)
            ->whereNull('block_id')
            ->whereIn('ip_address', $inRangeIps->all())
            ->groupBy('ip_address')
            ->pluck('first_seen', 'ip_address');

        $returningCount = 0;
        foreach ($inRangeIps as $ip) {
            $fs = $firstSeen[$ip] ?? null;
            if ($fs && Carbon::parse($fs)->lt($since)) {
                $returningCount++;
            }
        }
        $newCount = max(0, $totalVisitors - $returningCount);

        $dailySeries = $this->dailySeries(collect([$link->id]), $firstSeen, $since, $until, true);

        // Identified (logged-in) visitors.
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
            ->where('link_clicks.is_bot', false)
            ->whereNotNull('link_clicks.viewer_user_id')
            ->where('link_clicks.clicked_at', '>=', $since)
            ->where('link_clicks.clicked_at', '<=', $until)
            ->groupBy('users.id', 'users.name', 'users.email', 'users.avatar')
            ->orderByDesc('visit_count')
            ->limit(100)
            ->get();

        $followerSet = Follow::where('creator_id', $link->user_id)
            ->whereIn('follower_id', $identified->pluck('id'))
            ->pluck('follower_id')
            ->flip();

        $identifiedList = $identified->map(fn ($r) => [
            'id'           => (int) $r->id,
            'name'         => $r->name,
            'email'        => $r->email,
            'avatar'       => \App\Support\PublicStorageUrl::resolve($r->avatar),
            'visit_count'  => (int) $r->visit_count,
            'first_seen'   => $r->first_seen ? Carbon::parse($r->first_seen)->toDateString() : null,
            'last_seen'    => $r->last_seen ? Carbon::parse($r->last_seen)->toDateString() : null,
            'is_follower'  => $followerSet->has($r->id),
        ])->values();

        $nfcCount  = NfcWrite::where('link_id', $link->id)->count();
        $nfcRecent = NfcWrite::where('link_id', $link->id)
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn ($w) => [
                'id'         => (int) $w->id,
                'created_at' => optional($w->created_at)->toIso8601String(),
            ])
            ->values();

        $arSessions = PageSession::where('link_id', $link->id)
            ->where('source', 'ar')
            ->where('created_at', '>=', $since)
            ->where('created_at', '<=', $until)
            ->count();
        $arClicks = LinkClick::where('link_id', $link->id)
            ->where('source', 'ar')
            ->where('is_bot', false)
            ->whereNotNull('block_id')
            ->where('clicked_at', '>=', $since)
            ->where('clicked_at', '<=', $until)
            ->count();

        $sourceBreakdown = LinkClick::where('link_id', $link->id)
            ->where('is_bot', false)
            ->where('clicked_at', '>=', $since)
            ->where('clicked_at', '<=', $until)
            ->selectRaw("COALESCE(NULLIF(source, ''), 'web') as src, COUNT(*) as n")
            ->groupBy('src')
            ->orderByDesc('n')
            ->limit(8)
            ->get()
            ->map(fn ($r) => ['src' => $r->src, 'n' => (int) $r->n])
            ->values();

        return $this->ok([
            'link' => [
                'id'    => (int) $link->id,
                'alias' => $link->alias,
                'title' => $link->title,
                'type'  => $link->type,
            ],
            'range' => [
                'from'   => $startDate->format('Y-m-d'),
                'to'     => $endDate->format('Y-m-d'),
                'period' => $period,
            ],
            'total_visitors'   => $totalVisitors,
            'new_count'        => $newCount,
            'returning_count'  => $returningCount,
            'daily_series'     => $dailySeries,
            'identified'       => $identifiedList,
            'nfc_count'        => $nfcCount,
            'nfc_recent'       => $nfcRecent,
            'ar_sessions'      => $arSessions,
            'ar_clicks'        => $arClicks,
            'source_breakdown' => $sourceBreakdown,
        ]);
    }

    /**
     * Build the per-day visitors series over the scoped link set. For each
     * day: unique IPs (visitors), those whose first-ever click was before
     * that day (returning), and the remainder (new). When $withPct is true
     * the per-day returning percentage is included (per-link page parity).
     *
     * @param  \Illuminate\Support\Collection<int,int>  $linkIds
     * @param  \Illuminate\Support\Collection<string,string>  $firstSeen  ip => first_seen
     * @return list<array<string,int|string|float>>
     */
    protected function dailySeries($linkIds, $firstSeen, Carbon $start, Carbon $end, bool $withPct): array
    {
        $perDay = DB::table('link_clicks')
            ->selectRaw('DATE(clicked_at) as d, ip_address')
            ->whereIn('link_id', $linkIds)
            ->whereNull('block_id')
            ->where('is_bot', false)
            ->where('clicked_at', '>=', $start)
            ->where('clicked_at', '<=', $end)
            ->get();

        $bucket = [];
        foreach ($perDay as $row) {
            $day = $row->d;
            if (!isset($bucket[$day])) {
                $bucket[$day] = ['ips' => [], 'returning_ips' => []];
            }
            if (!in_array($row->ip_address, $bucket[$day]['ips'], true)) {
                $bucket[$day]['ips'][] = $row->ip_address;
                $fs = $firstSeen[$row->ip_address] ?? null;
                if ($fs && Carbon::parse($fs)->lt(Carbon::parse($day))) {
                    $bucket[$day]['returning_ips'][] = $row->ip_address;
                }
            }
        }
        ksort($bucket);

        $out = [];
        foreach ($bucket as $d => $v) {
            $u = count($v['ips']);
            $r = count($v['returning_ips']);
            $point = [
                'd'         => $d,
                'visitors'  => $u,
                'new'       => max(0, $u - $r),
                'returning' => $r,
            ];
            if ($withPct) {
                $point['returning_pct'] = $u > 0 ? round(($r / $u) * 100, 1) : 0;
            }
            $out[] = $point;
        }
        return $out;
    }
}
