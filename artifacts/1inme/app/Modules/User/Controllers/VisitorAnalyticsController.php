<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkClick;
use App\Modules\User\Models\NfcWrite;
use App\Modules\User\Models\PageSession;
use App\Modules\User\Support\AnalyticsRangeResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VisitorAnalyticsController extends Controller
{
    public function index(Request $request, Link $link)
    {
        abort_unless($link->user_id === auth()->id() || auth()->user()->hasPermission('user.analytics.view_any'), 403);

        return view('user.visitors.index', $this->compute($request, $link));
    }

    /**
     * CSV export of the per-link visitor totals + breakdowns — respects the
     * currently selected date range, gated behind the shared `analytics_export`
     * plan feature (see stats-csv-export-gating memory topic). One file with
     * clearly-labelled sections (totals, daily trend, by source, identified
     * visitors) so it drops straight into a spreadsheet.
     */
    public function export(Request $request, Link $link)
    {
        abort_unless($link->user_id === auth()->id() || auth()->user()->hasPermission('user.analytics.view_any'), 403);

        if (!workspace_owner()?->getPlanFeature('analytics_export', true)) {
            return back()->with('error', 'Exporting visitor data is a paid feature. Upgrade your plan to download CSV exports.');
        }

        $data = $this->compute($request, $link);

        $filename = sprintf(
            '1inme-visitors-%s-%s_%s.csv',
            $link->alias ?: $link->id,
            $data['startDate']->format('Y-m-d'),
            $data['endDate']->format('Y-m-d')
        );

        return response()->streamDownload(function () use ($data, $link) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Visitor Insights export']);
            fputcsv($out, ['Link', $link->title ?: $link->alias]);
            fputcsv($out, ['Range', $data['startDate']->format('Y-m-d') . ' to ' . $data['endDate']->format('Y-m-d')]);
            fputcsv($out, []);

            fputcsv($out, ['Totals']);
            fputcsv($out, ['Metric', 'Visitors']);
            fputcsv($out, ['Unique visitors', $data['totalVisitors']]);
            fputcsv($out, ['New', $data['newCount']]);
            fputcsv($out, ['Returning', $data['returningCount']]);
            fputcsv($out, []);

            fputcsv($out, ['Daily trend']);
            fputcsv($out, ['Date', 'Unique visitors', 'Returning', 'Returning %']);
            foreach ($data['dailySeries'] as $row) {
                fputcsv($out, [$row->d, $row->visitors, $row->returning, $row->returning_pct]);
            }
            fputcsv($out, []);

            fputcsv($out, ['Visitors by source']);
            fputcsv($out, ['Source', 'Clicks']);
            foreach ($data['sourceBreakdown'] as $row) {
                fputcsv($out, [$row->src, $row->n]);
            }
            fputcsv($out, []);

            // QR Connect funnel (event links only) — mirrors the on-page panel:
            // totals + the per-day scans-vs-connects table.
            if ($data['qrConnect'] !== null) {
                $qr = $data['qrConnect'];
                fputcsv($out, ['QR Connect']);
                fputcsv($out, ['Metric', 'Count']);
                fputcsv($out, ['Scans', $qr['scans']]);
                fputcsv($out, ['Connects', $qr['connected']]);
                fputcsv($out, ['New users', $qr['new_users']]);
                fputcsv($out, ['Existing users', $qr['existing']]);
                fputcsv($out, ['RSVPs', $qr['rsvps']]);
                fputcsv($out, ['Follows', $qr['follows']]);
                fputcsv($out, ['Conversion %', $qr['conversion_pct'] !== null ? $qr['conversion_pct'] : '—']);
                fputcsv($out, []);

                fputcsv($out, ['QR Connect daily']);
                fputcsv($out, ['Date', 'Scans', 'Connects']);
                foreach ($qr['daily'] as $row) {
                    fputcsv($out, [$row->d, $row->scans, $row->connects]);
                }
                fputcsv($out, []);
            }

            fputcsv($out, ['Identified visitors']);
            fputcsv($out, ['Name', 'Email', 'Visits', 'First seen', 'Last seen', 'Follower']);
            foreach ($data['identified'] as $row) {
                fputcsv($out, [
                    $row->name,
                    $row->email,
                    $row->visit_count,
                    $row->first_seen,
                    $row->last_seen,
                    $data['followerSet']->has($row->id) ? 'Yes' : 'No',
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Shared computation for the page and the CSV export so both stay in
     * lockstep.
     */
    protected function compute(Request $request, Link $link): array
    {
        // Mirror the same period pills the Overview / Followers tabs use so all
        // three views share one date-window control instead of a bespoke
        // 7/30/90 "days" dropdown.
        [$startDate, $endDate, $period] = $this->resolveRange($request);
        $since = $startDate;
        $until = $endDate;

        // Compute per-IP first-seen across the full history of THIS link.
        // A visitor in the selected period is "returning" iff their first-ever
        // click on this link happened strictly before the period started.
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
            if ($fs && \Carbon\Carbon::parse($fs)->lt($since)) $returningCount++;
        }
        $newCount = max(0, $totalVisitors - $returningCount);

        // Daily series within the selected period: for each day, count unique
        // IPs whose first-ever click on this link was before that day.
        $perDay = DB::table('link_clicks')
            ->selectRaw('DATE(clicked_at) as d, ip_address')
            ->where('link_id', $link->id)
            ->where('is_bot', false)
            ->whereNull('block_id')
            ->where('clicked_at', '>=', $since)
            ->where('clicked_at', '<=', $until)
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

        // NFC writes for this link — counter + recent rows for the
        // history strip on the analytics page. Full history is on the
        // dedicated NFC history page (links/{link}/nfc-writes).
        $nfcCount  = NfcWrite::where('link_id', $link->id)->count();
        $nfcRecent = NfcWrite::where('link_id', $link->id)
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        // AR Business Card breakdown — sessions and block taps that came
        // through the /ar/{alias} renderer carry source = 'ar', so we can
        // surface AR scans + AR-attributed block clicks alongside the
        // standard web visitor numbers without a new pipeline.
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
        // QR Connect stats (Task #6685) — event links only. Scans come from
        // the click pipeline (source = 'connect_qr', counted even when the
        // visitor never signs in); completions/new-signups/RSVPs/follows
        // come from the event_qr_connects attribution rows. All respect the
        // selected date range.
        $qrConnect = null;
        if ($link->type === 'ics') {
            $connects = \App\Modules\User\Models\EventQrConnect::where('link_id', $link->id)
                ->where('created_at', '>=', $since)
                ->where('created_at', '<=', $until)
                ->get(['was_new_user', 'rsvp_id', 'followed', 'created_at']);

            // Daily funnel series (Task #6689): scans vs completed connects per
            // day so multi-day promoters can see which day the printed QR
            // performed best. Days come from the union of both series (no
            // zero-fill across an "all time" range).
            $scansByDay = LinkClick::where('link_id', $link->id)
                ->where('source', 'connect_qr')
                ->where('is_bot', false)
                ->whereNull('block_id')
                ->where('clicked_at', '>=', $since)
                ->where('clicked_at', '<=', $until)
                ->selectRaw('DATE(clicked_at) as d, COUNT(*) as n')
                ->groupBy('d')
                ->pluck('n', 'd');
            $connectsByDay = $connects
                ->groupBy(fn ($c) => $c->created_at->format('Y-m-d'))
                ->map->count();

            $qrDays = $scansByDay->keys()->merge($connectsByDay->keys())->unique()->sort()->values();
            $qrDaily = $qrDays->map(fn ($d) => (object)[
                'd'        => $d,
                'scans'    => (int) ($scansByDay[$d] ?? 0),
                'connects' => (int) ($connectsByDay[$d] ?? 0),
            ])->values();

            $qrScans = (int) $scansByDay->sum();
            $qrConnect = [
                'scans'          => $qrScans,
                'connected'      => $connects->count(),
                'new_users'      => $connects->where('was_new_user', true)->count(),
                'existing'       => $connects->where('was_new_user', false)->count(),
                'rsvps'          => $connects->whereNotNull('rsvp_id')->count(),
                'follows'        => $connects->where('followed', true)->count(),
                'daily'          => $qrDaily,
                // Conversion for the selected range: scans → completed connects.
                'conversion_pct' => $qrScans > 0
                    ? round(($connects->count() / $qrScans) * 100, 1)
                    : null,
            ];
        }
        $sourceBreakdown = LinkClick::where('link_id', $link->id)
            ->where('is_bot', false)
            ->where('clicked_at', '>=', $since)
            ->where('clicked_at', '<=', $until)
            ->selectRaw("COALESCE(NULLIF(source, ''), 'web') as src, COUNT(*) as n")
            ->groupBy('src')
            ->orderByDesc('n')
            ->limit(8)
            ->get();

        return [
            'link'             => $link,
            'period'           => $period,
            'startDate'        => $startDate,
            'endDate'          => $endDate,
            'totalVisitors'    => $totalVisitors,
            'returningCount'   => $returningCount,
            'newCount'         => $newCount,
            'dailySeries'      => $dailySeries,
            'identified'       => $identified,
            'followerSet'      => $followerSet,
            'nfcCount'         => $nfcCount,
            'nfcRecent'        => $nfcRecent,
            'arSessions'       => $arSessions,
            'arClicks'         => $arClicks,
            'sourceBreakdown'  => $sourceBreakdown,
            'qrConnect'        => $qrConnect,
        ];
    }

    /**
     * Resolve the selected period pill (or custom start/end range) into a
     * [start, end, period] window, matching the pills the Overview /
     * Followers tabs use (today/7d/30d/90d/year/all/custom) and honouring
     * the plan's stats retention. Delegates to the shared resolver so this
     * page and the account-wide Visitors page stay in lockstep.
     */
    private function resolveRange(Request $request): array
    {
        return AnalyticsRangeResolver::resolve($request, workspace_owner()->statsRetentionDays());
    }

    public function nfcHistory(Request $request, Link $link)
    {
        abort_unless($link->user_id === auth()->id() || auth()->user()->hasPermission('user.analytics.view_any'), 403);

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
