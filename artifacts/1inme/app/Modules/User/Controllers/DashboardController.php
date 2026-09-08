<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Services\ChannelClassifier;
use App\Modules\User\Models\Backlink;
use App\Modules\User\Models\LinkClick;
use App\Modules\User\Support\DashboardPresets;
use App\Modules\User\Support\DashboardWidgetCatalog;
use App\Services\AI\AiPlanAccess;
use App\Services\AI\DashboardAiDesignerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $user->load('plan', 'accountBadges');

        // One aggregate over the links table for the three headline tiles
        // (total / active / lifetime clicks) instead of three separate
        // COUNT/SUM round-trips. Over a distant DB each round-trip costs
        // hundreds of ms, so folding them into a single query is a direct
        // first-paint win. total_clicks is the denormalized lifetime counter.
        $linkAgg = $user->links()
            ->selectRaw("COUNT(*) as total, COUNT(*) FILTER (WHERE is_active) as active, COALESCE(SUM(total_clicks), 0) as clicks")
            ->first();
        $totalLinks = (int) ($linkAgg->total ?? 0);
        $activeLinks = (int) ($linkAgg->active ?? 0);
        $lifetimeClicks = (int) ($linkAgg->clicks ?? 0);

        $totalProjects = $user->projects()->count();

        // Folders desk on the dashboard (replaces the separate Folders page):
        // most recently touched folders first, with live link counts. Scoped
        // to the workspace owner (like ProjectController) so team members see
        // the active workspace's folders, not their own personal ones. No
        // limit — folder counts are already bounded by the plan cap.
        $deskFolders = workspace_owner()->projects()
            ->withCount([
                'links as links_count',
                'links as active_links_count' => fn ($q) => $q->where('is_active', true),
            ])
            ->orderByDesc('updated_at')
            ->get();

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

        // Single pass over link_clicks for the channel breakdown AND the
        // "today" tally, scoped via a subquery on the user's link ids so we
        // neither pluck every id into PHP nor ship a giant IN(...) list.
        // Older clicks logged before the channel column existed surface as
        // 'unknown' (grouped on the COALESCE expression so NULLs and any
        // literal 'unknown' rows merge into one bucket). The per-channel
        // `today` FILTER count lets us derive clicksToday (and, when a
        // channel filter is active, that channel's totals) from this one
        // query instead of two more round-trips.
        $linkIdSub = $user->links()->select('id')->getQuery();
        $channelRows = LinkClick::whereIn('link_id', $linkIdSub)
            ->selectRaw(
                "COALESCE(channel, 'unknown') as channel, COUNT(*) as count, COUNT(*) FILTER (WHERE clicked_at >= ?) as today",
                [now()->startOfDay()]
            )
            ->groupByRaw("COALESCE(channel, 'unknown')")
            ->orderByDesc('count')
            ->get();

        // Channel breakdown card is intentionally NOT filtered by
        // $channelFilter so it always shows the full split.
        $channelStats = $channelRows->map(fn ($r) => (object) [
            'channel' => $r->channel,
            'count'   => (int) $r->count,
        ])->values();

        // When no channel filter is active we keep the denormalized lifetime
        // total_clicks counter (matches historical clicks predating the
        // link_clicks table); with a filter we use that channel's row.
        if ($channelFilter === null) {
            $totalClicks = $lifetimeClicks;
            $clicksToday = (int) $channelRows->sum('today');
        } else {
            $filteredRow = $channelRows->firstWhere('channel', $channelFilter);
            $totalClicks = $filteredRow ? (int) $filteredRow->count : 0;
            $clicksToday = $filteredRow ? (int) $filteredRow->today : 0;
        }

        $recentLinks = $user->links()
            ->with('project')
            ->latest()
            ->take(5)
            ->get();

        // Task #3848 — compact "last 7 days" trend series for the stats
        // tile sparklines. Each series is zero-filled so a day with no
        // activity still renders as a point, not a gap. Computed with
        // calendar-day buckets on the server clock (the sparklines are a
        // lightweight trend accent, not a timezone-sensitive figure —
        // unlike the greeting, which does need the user's timezone).
        $sparklineSince = now()->subDays(6)->startOfDay();
        $zeroFillSeries = function (\Illuminate\Support\Collection $byDay) use ($sparklineSince) {
            $out = [];
            for ($i = 0; $i < 7; $i++) {
                $d = $sparklineSince->copy()->addDays($i)->format('Y-m-d');
                $out[] = (int) ($byDay[$d] ?? 0);
            }
            return $out;
        };

        $clicksByDay = LinkClick::whereIn('link_id', $linkIdSub)
            ->where('clicked_at', '>=', $sparklineSince)
            ->selectRaw('DATE(clicked_at) as d, COUNT(*) as c')
            ->groupBy('d')
            ->pluck('c', 'd');
        $linksByDay = $user->links()
            ->where('created_at', '>=', $sparklineSince)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->groupBy('d')
            ->pluck('c', 'd');
        $projectsByDay = $user->projects()
            ->where('created_at', '>=', $sparklineSince)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->groupBy('d')
            ->pluck('c', 'd');

        $clicksSparkline = $zeroFillSeries($clicksByDay);
        $linksSparkline = $zeroFillSeries($linksByDay);
        $projectsSparkline = $zeroFillSeries($projectsByDay);

        // Backlink radar at-a-glance — count of new backlinks the
        // browser extension has captured in the last 7 days. Cheap
        // single-row count; deep view lives at user.backlinks.index.
        $backlinksThisWeek = Backlink::where('user_id', $user->id)
            ->where('first_seen_at', '>=', now()->subDays(7))
            ->count();

        // WhatsApp nudge — shown to any user who hasn't shared a verified
        // WhatsApp number yet, letting them add/verify one inline and follow
        // our channel. Dismissing it snoozes the card for a week rather than
        // hiding it forever, so it returns on the weekly cadence until a
        // number is added; users who already have one are never nagged.
        $dismissedAt = $user->settings['whatsapp_prompt_dismissed_at'] ?? null;
        $whatsappPromptSnoozed = $dismissedAt
            && \Illuminate\Support\Carbon::parse($dismissedAt)->gt(now()->subWeek());
        $showWhatsappPrompt = !$whatsappPromptSnoozed && !$user->hasWhatsappNumber();
        $whatsappChannelUrl = trim((string) \App\Modules\Admin\Models\AppSetting::get('marketing_whatsapp_channel_url', ''));

        // Task #3564 — active Delivery Projects for the dashboard widget, with
        // task counts so the tile can render a live progress bar per project.
        $deliveryProjects = \App\Modules\User\Models\DeliveryProject::query()
            ->where('status', \App\Modules\User\Models\DeliveryProject::STATUS_ACTIVE)
            ->withCount('tasks')
            ->withCount(['tasks as done_tasks_count' => fn ($q) => $q->where('status', \App\Modules\User\Models\DeliveryProjectTask::STATUS_DONE)])
            ->orderByDesc('id')
            ->take(5)
            ->get();

        // Task #3525 — resolve the user's chosen widget layout (default =
        // every widget, i.e. today's exact page) plus the picker payload for
        // the "Customize dashboard" modal.
        $layout = DashboardPresets::resolveFor($user);
        $dashboardWidgets = $layout['widgets'];
        $dashboardTabs = DashboardWidgetCatalog::tabVisibility($dashboardWidgets);
        $dashboardCurrentPreset = $layout['preset'];
        $dashboardIsCustom = $layout['is_custom'];
        $dashboardCatalog = DashboardWidgetCatalog::forFrontend();
        // Task #3803 — same catalog, grouped by tab, for the "Design with
        // AI" widget picker.
        $dashboardGroupedCatalog = DashboardWidgetCatalog::groupedForFrontend();
        $dashboardPresets = DashboardPresets::forFrontend();
        $dashboardAiAllowed = AiPlanAccess::featureAllowed($user, DashboardAiDesignerService::FEATURE);
        // Task #3617 — active-layout badge label + per-tab "tiles are hidden"
        // hint, both derived from the layout already resolved above.
        $dashboardLayoutLabel = DashboardPresets::labelFor($layout);
        $dashboardTrimmedTabs = DashboardWidgetCatalog::trimmedTabs(
            $dashboardWidgets,
            DashboardPresets::widgetsForPreset(DashboardPresets::DEFAULT_PRESET)
        );

        // Aurora dashboard only. The link graph needs one row per link with its
        // folder and its click count, and the top-links panel wants those same
        // rows ordered, so one query serves both. Capped at 300 because the
        // graph is a picture rather than a list: past a few hundred nodes it
        // stops reading as clusters and starts reading as static, and an
        // account with thousands of links would be paying render time for
        // dots nobody can tell apart.
        $auroraLinks = collect();
        $auroraHeat = [];
        $auroraActivity = collect();
        $auroraSources = collect();

        if ($user->usesAuroraUi()) {
            $auroraLinks = $user->links()
                ->select('id', 'project_id', 'alias', 'title', 'total_clicks')
                ->orderByDesc('total_clicks')
                ->limit(300)
                ->get();

            // When clicks land, as a 7 x 12 grid: ISO weekday down, two-hour
            // blocks across. Bucketed in SQL so the rows that come back are at
            // most 84 whatever the traffic. Server clock, matching the
            // sparkline's calendar-day buckets rather than the greeting's
            // timezone-aware one.
            $heatSince = now()->subDays(6)->startOfDay();
            $auroraHeat = array_fill(0, 7, array_fill(0, 12, 0));
            LinkClick::whereIn('link_id', $linkIdSub)
                ->where('clicked_at', '>=', $heatSince)
                ->selectRaw('EXTRACT(ISODOW FROM clicked_at)::int AS dow, (EXTRACT(HOUR FROM clicked_at)::int / 2) AS blk, COUNT(*) AS c')
                ->groupBy('dow', 'blk')
                ->get()
                ->each(function ($r) use (&$auroraHeat) {
                    $d = ((int) $r->dow) - 1;   // ISODOW is 1..7, Monday first
                    $b = min(11, max(0, (int) $r->blk));
                    if ($d >= 0 && $d < 7) {
                        $auroraHeat[$d][$b] = (int) $r->c;
                    }
                });

            // The five most recent clicks, for the live feed.
            $auroraActivity = LinkClick::whereIn('link_id', $linkIdSub)
                ->orderByDesc('clicked_at')
                ->limit(5)
                ->get(['alias', 'city', 'country_code', 'referrer', 'clicked_at']);

            // Where clicks come from, by referring host.
            //
            // This replaces a ring built on the `channel` column, which
            // classifies the user agent (browser vs in-app webview) and so
            // answered a question nobody asked, at 84% "unknown". The referrer
            // is the field that means what the panel's title says. Grouped in
            // SQL and bucketed in PHP, capped at 200 distinct referrers, which
            // is far more than the handful that survive bucketing.
            $auroraSources = LinkClick::whereIn('link_id', $linkIdSub)
                ->selectRaw('referrer, COUNT(*) AS c')
                ->groupBy('referrer')
                ->orderByDesc('c')
                ->limit(200)
                ->get()
                ->reduce(function (\Illuminate\Support\Collection $acc, $row) {
                    $host = strtolower((string) parse_url((string) $row->referrer, PHP_URL_HOST));
                    $label = match (true) {
                        $host === ''                              => 'Direct',
                        str_contains($host, 'instagram')          => 'Instagram',
                        str_contains($host, 'whatsapp')           => 'WhatsApp',
                        str_contains($host, 'facebook') || str_contains($host, 'fb.')  => 'Facebook',
                        str_contains($host, 'youtube')  || str_contains($host, 'youtu.be') => 'YouTube',
                        str_contains($host, 'linkedin')           => 'LinkedIn',
                        str_contains($host, 'twitter')  || $host === 't.co' || str_contains($host, 'x.com') => 'X',
                        str_contains($host, 'google')   || str_contains($host, 'bing') || str_contains($host, 'duckduckgo') => 'Search',
                        str_contains($host, 'telegram') || $host === 't.me' => 'Telegram',
                        default => preg_replace('/^www\./', '', $host),
                    };
                    return $acc->put($label, ($acc->get($label, 0)) + (int) $row->c);
                }, collect())
                ->sortDesc()
                ->take(5);
        }

        $payload = compact(
            'user', 'totalLinks', 'totalClicks', 'totalProjects', 'deskFolders',
            'auroraLinks', 'auroraHeat', 'auroraActivity', 'auroraSources',
            'activeLinks', 'recentLinks', 'clicksToday',
            'channelStats', 'channelFilter', 'backlinksThisWeek',
            'showWhatsappPrompt', 'whatsappChannelUrl', 'deliveryProjects',
            'dashboardWidgets', 'dashboardTabs', 'dashboardCurrentPreset',
            'dashboardIsCustom', 'dashboardCatalog', 'dashboardGroupedCatalog', 'dashboardPresets',
            'dashboardAiAllowed', 'dashboardLayoutLabel', 'dashboardTrimmedTabs',
            'clicksSparkline', 'linksSparkline', 'projectsSparkline'
        );

        // The Aurora dashboard is a preview behind an opt-in flag, so a fault
        // in it must never cost anyone their dashboard. Render it inside the
        // try (rather than returning the view and letting the response
        // pipeline render it later) so a view-level error is caught here, and
        // fall back to the dashboard that has always worked.
        //
        // report() is the whole diagnostic path now. This briefly also put the
        // message on a response header, because the box takes no inbound SSH
        // and the log was unreachable from where the preview was being built;
        // that came out once it had found what it was added for.
        if ($user->usesAuroraUi()) {
            try {
                return response(view('user.dashboard.aurora', $payload)->render());
            } catch (\Throwable $e) {
                report($e);

                return view('user.dashboard.index', $payload);
            }
        }

        return view('user.dashboard.index', $payload);
    }
}
