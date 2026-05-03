<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Services\ChannelClassifier;
use App\Modules\User\Models\Backlink;
use App\Modules\User\Models\LinkClick;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $user->load('plan');

        $totalLinks = $user->links()->count();
        $totalProjects = $user->projects()->count();
        $activeLinks = $user->links()->where('is_active', true)->count();

        // Optional workspace-wide channel filter — narrows the click-derived
        // tiles (Total Clicks, Today) to a single user-agent bucket so creators
        // can ask "what share of all my traffic comes from in-app webviews?"
        // without drilling into every link. Validated against the classifier's
        // own key list so query-string tampering can't smuggle SQL into the
        // where clause downstream.
        $channelFilter = $request->query('channel');
        if (!is_string($channelFilter) || !in_array($channelFilter, ChannelClassifier::validKeys(), true)) {
            $channelFilter = null;
        }

        $linkIds = $user->links()->pluck('id');

        // When no channel filter is active we keep using the denormalized
        // total_clicks counter on the links table (cheap, lifetime-accurate).
        // With a filter active we have to count rows from link_clicks because
        // that's the only place the channel classification lives.
        if ($channelFilter === null) {
            $totalClicks = (int) $user->links()->sum('total_clicks');
        } else {
            $totalClicks = LinkClick::whereIn('link_id', $linkIds)
                ->where('channel', $channelFilter)
                ->count();
        }

        $clicksToday = LinkClick::whereIn('link_id', $linkIds)
            ->where('clicked_at', '>=', now()->startOfDay())
            ->when($channelFilter, fn ($q) => $q->where('channel', $channelFilter))
            ->count();

        // Workspace-wide channel breakdown — aggregated across every link the
        // creator owns. Intentionally NOT filtered by $channelFilter so the
        // breakdown card always shows the full split (and lets the user
        // switch buckets by clicking the pills). Older clicks logged before
        // the column was added surface as 'unknown' so totals reconcile.
        // Group by the COALESCE expression itself rather than the raw column
        // so NULL rows (pre-classifier clicks) and any literal 'unknown'
        // rows merge into a single Unknown bucket instead of rendering as
        // two adjacent rows that share the same label.
        $channelStats = LinkClick::whereIn('link_id', $linkIds)
            ->selectRaw("COALESCE(channel, 'unknown') as channel, COUNT(*) as count")
            ->groupByRaw("COALESCE(channel, 'unknown')")
            ->orderByDesc('count')
            ->get();

        $recentLinks = $user->links()
            ->with('project')
            ->latest()
            ->take(5)
            ->get();

        // Backlink radar at-a-glance — count of new backlinks the
        // browser extension has captured in the last 7 days. Cheap
        // single-row count; deep view lives at user.backlinks.index.
        $backlinksThisWeek = Backlink::where('user_id', $user->id)
            ->where('first_seen_at', '>=', now()->subDays(7))
            ->count();

        return view('user.dashboard.index', compact(
            'user', 'totalLinks', 'totalClicks', 'totalProjects',
            'activeLinks', 'recentLinks', 'clicksToday',
            'channelStats', 'channelFilter', 'backlinksThisWeek'
        ));
    }
}
