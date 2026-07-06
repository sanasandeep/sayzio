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

        return view('user.dashboard.index', compact(
            'user', 'totalLinks', 'totalClicks', 'totalProjects',
            'activeLinks', 'recentLinks', 'clicksToday',
            'channelStats', 'channelFilter', 'backlinksThisWeek',
            'showWhatsappPrompt', 'whatsappChannelUrl', 'deliveryProjects',
            'dashboardWidgets', 'dashboardTabs', 'dashboardCurrentPreset',
            'dashboardIsCustom', 'dashboardCatalog', 'dashboardGroupedCatalog', 'dashboardPresets',
            'dashboardAiAllowed', 'dashboardLayoutLabel', 'dashboardTrimmedTabs'
        ));
    }
}
