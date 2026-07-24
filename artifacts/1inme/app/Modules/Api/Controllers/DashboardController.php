<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Api\Resources\LinkResource;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\NfcWrite;
use App\Modules\User\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Lightweight summary endpoint for the mobile home tab. Aggregates the
 * counters the user expects to see at a glance: total links, total
 * clicks, NFC writes, follower count, recent links, and the top link
 * (by total_clicks). Designed to keep the home screen to a single
 * round-trip on cold launch.
 *
 * Performance notes:
 * - Four separate count/sum queries collapsed into one aggregate SELECT.
 * - Pixel-fires for the recent + top links are batch-preloaded via
 *   LinkResource::preload() to eliminate the per-link N+1.
 * - The bulk of the payload (aggregates, by_type, recent/top links) is
 *   cached for 30 seconds with the plain-array convention to avoid the
 *   file-cache __PHP_Incomplete_Class hazard.
 * - unread_notifs is deliberately excluded from the cache so the
 *   notification badge stays accurate on every poll.
 */
class DashboardController extends Controller
{
    use ApiResponses;

    private const CACHE_TTL = 30;

    public function index(Request $request)
    {
        $user   = $request->user();
        $userId = $user->id;

        // Scope all link aggregates to the ACTIVE workspace (same rule as the
        // web dashboard/links list): links owned by the workspace owner and
        // tagged with the workspace id. Falls back to caller-owned links when
        // the workspace column is unavailable (old DBs).
        $ws = null;
        try {
            if (\Illuminate\Support\Facades\Schema::hasColumn('links', 'workspace_id')) {
                $ws = $this->activeWorkspace($user);
            }
        } catch (\Throwable) {
            $ws = null;
        }
        $linksQuery = function () use ($ws, $userId) {
            return $ws
                ? Link::withoutWorkspaceScope()
                    ->where('user_id', $ws->owner_user_id)
                    ->where('workspace_id', $ws->id)
                : Link::where('user_id', $userId);
        };

        // Cache varies by workspace so switching never serves stale scope.
        $cacheKey = 'api.dashboard.v2.' . $userId . '.' . ($ws?->id ?? 0);

        $cached = cache()->get($cacheKey);
        if ($cached !== null && is_array($cached)) {
            // Unread notification count is always live — never cached —
            // so the in-app badge reflects the real state every poll.
            $cached['totals']['unread_notifs'] = (int) UserNotification::where('user_id', $userId)
                ->whereNull('read_at')
                ->count();
            $cached['totals']['followers'] = (int) ($user->followers_count ?? 0);
            return $this->ok($cached);
        }

        // Single aggregate query replaces four separate count/sum queries.
        $agg = $linksQuery()
            ->selectRaw(
                'count(*) as total_links,'
                . ' sum(case when is_active then 1 else 0 end) as active_links,'
                . ' sum(coalesce(total_clicks, 0)) as total_clicks,'
                . ' sum(coalesce(unique_clicks, 0)) as total_unique'
            )
            ->first();

        $byType = $linksQuery()
            ->selectRaw('type, count(*) as c, sum(coalesce(total_clicks,0)) as clicks')
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        $nfcCount    = (int) NfcWrite::where('user_id', $userId)->count();
        $unreadNotif = (int) UserNotification::where('user_id', $userId)->whereNull('read_at')->count();

        // Eager-load the `domain` relation so LinkResource::toArray() doesn't
        // trigger a per-link lazy load when reading `$l->domain?->domain`.
        $recentLinks = $linksQuery()
            ->with('domain')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $topLink = $linksQuery()
            ->with('domain')
            ->orderByDesc('total_clicks')
            ->limit(1)
            ->first();

        // Batch-preload pixel-fire data for all links that will be serialised
        // through LinkResource, eliminating up to 12 per-link queries.
        $allLinks = $topLink
            ? $recentLinks->concat([$topLink])->all()
            : $recentLinks->all();
        LinkResource::preload($allLinks);

        $recent = $recentLinks->map(fn ($l) => LinkResource::toArray($l))->all();
        $top    = $topLink ? LinkResource::toArray($topLink) : null;

        $payload = [
            'totals' => [
                'links'          => (int) ($agg->total_links ?? 0),
                'active_links'   => (int) ($agg->active_links ?? 0),
                'total_clicks'   => (int) ($agg->total_clicks ?? 0),
                'unique_clicks'  => (int) ($agg->total_unique ?? 0),
                'nfc_writes'     => $nfcCount,
                'followers'      => (int) ($user->followers_count ?? 0),
                'unread_notifs'  => $unreadNotif,
            ],
            'by_type' => $byType->map(fn ($r) => [
                'type'   => $r->type,
                'count'  => (int) $r->c,
                'clicks' => (int) $r->clicks,
            ])->values()->all(),
            'recent_links' => $recent,
            'top_link'     => $top,
        ];

        // Cache the plain-array payload. Exclude live fields (unread_notifs,
        // followers) — they are re-attached from the DB / model on every hit.
        $toCache = $payload;
        $toCache['totals']['unread_notifs'] = 0;
        $toCache['totals']['followers']     = 0;
        cache()->put($cacheKey, $toCache, self::CACHE_TTL);

        return $this->ok($payload);
    }
}
