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
 */
class DashboardController extends Controller
{
    use ApiResponses;

    public function index(Request $request)
    {
        $user   = $request->user();
        $userId = $user->id;

        $byType = Link::where('user_id', $userId)
            ->selectRaw('type, count(*) as c, sum(coalesce(total_clicks,0)) as clicks')
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        $totalLinks  = (int) Link::where('user_id', $userId)->count();
        $activeLinks = (int) Link::where('user_id', $userId)->where('is_active', true)->count();
        $totalClicks = (int) Link::where('user_id', $userId)->sum('total_clicks');
        $totalUnique = (int) Link::where('user_id', $userId)->sum('unique_clicks');
        $nfcCount    = (int) NfcWrite::where('user_id', $userId)->count();
        $unreadNotif = (int) UserNotification::where('user_id', $userId)->whereNull('read_at')->count();

        $recent = Link::where('user_id', $userId)
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn ($l) => LinkResource::toArray($l))
            ->all();

        $top = Link::where('user_id', $userId)
            ->orderByDesc('total_clicks')
            ->limit(1)
            ->first();

        return $this->ok([
            'totals' => [
                'links'          => $totalLinks,
                'active_links'   => $activeLinks,
                'total_clicks'   => $totalClicks,
                'unique_clicks'  => $totalUnique,
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
            'top_link'     => $top ? LinkResource::toArray($top) : null,
        ]);
    }
}
