<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\CreatorPaymentEvent;
use App\Modules\User\Models\CreatorPost;
use App\Modules\User\Models\CreatorPostComment;
use App\Modules\User\Models\CreatorPostReaction;
use App\Modules\User\Models\CreatorSubscription;
use App\Modules\User\Models\Follow;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;

/**
 * Mobile parity for the unified creator Stats home (Task #1211, web
 * route /user/stats served by User\Controllers\CreatorStatsController).
 *
 * The Expo Stats screen (artifacts/1inme-mobile/app/stats.tsx) renders
 * KPI tiles from the {audience, content, engagement, earnings} envelope
 * returned here. Data sources mirror the web controller — Follow,
 * CreatorPost, CreatorPostReaction, CreatorPostComment,
 * CreatorSubscription — plus the CreatorPaymentEvent ledger for the
 * earnings split the phone surfaces. Earnings are returned in major
 * currency units (dollars) because the mobile screen formats them as
 * `${currency} ${value.toFixed(2)}`.
 */
class CreatorStatsApiController extends Controller
{
    use ApiResponses;

    /** Allowed range presets, mirroring the web dashboard. */
    public const RANGES = [
        '7d'  => 7,
        '30d' => 30,
        '90d' => 90,
        '1y'  => 365,
    ];

    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) return $this->unauthorized();

        [, $start, $end] = $this->resolveRange($request);

        $postIds = CreatorPost::query()->withoutGlobalScope('workspace')
            ->where('user_id', $user->id)->pluck('id');

        $currency = $user->preferred_currency ?: 'USD';

        $tipsCents = (int) CreatorPaymentEvent::query()
            ->where('creator_user_id', $user->id)
            ->where('source', CreatorPaymentEvent::SOURCE_TIP)
            ->where('amount_cents', '>', 0)
            ->whereBetween('occurred_at', [$start, $end])
            ->sum('amount_cents');

        $subsCents = (int) CreatorPaymentEvent::query()
            ->where('creator_user_id', $user->id)
            ->where('source', CreatorPaymentEvent::SOURCE_SUB)
            ->where('amount_cents', '>', 0)
            ->whereBetween('occurred_at', [$start, $end])
            ->sum('amount_cents');

        // Lifetime net (after refunds). Platform fee is 0%, so this is the
        // creator's lifetime take-home — matching the screen's "Lifetime,
        // after fees" sub-label on the Payouts tile.
        $payoutsCents = (int) CreatorPaymentEvent::query()
            ->where('creator_user_id', $user->id)
            ->sum('amount_cents');

        return $this->ok([
            'range' => [
                'from' => $start->format('Y-m-d'),
                'to'   => $end->format('Y-m-d'),
            ],
            'audience' => [
                'followers'       => (int) ($user->followers_count ?? 0),
                'followers_delta' => Follow::where('creator_id', $user->id)
                    ->whereBetween('created_at', [$start, $end])->count(),
                'subscribers'     => CreatorSubscription::where('creator_user_id', $user->id)
                    ->whereIn('status', [CreatorSubscription::STATUS_ACTIVE, CreatorSubscription::STATUS_TRIALING])
                    ->count(),
            ],
            'content' => [
                'posts'    => CreatorPost::query()->withoutGlobalScope('workspace')
                    ->where('user_id', $user->id)->whereNotNull('published_at')
                    ->whereBetween('published_at', [$start, $end])->count(),
                'views'    => (int) CreatorPost::query()->withoutGlobalScope('workspace')
                    ->where('user_id', $user->id)->whereNotNull('published_at')
                    ->sum('view_count_7d'),
                'comments' => CreatorPostComment::whereIn('post_id', $postIds)
                    ->whereBetween('created_at', [$start, $end])->count(),
            ],
            'engagement' => [
                'reactions' => CreatorPostReaction::whereIn('post_id', $postIds)
                    ->whereBetween('created_at', [$start, $end])->count(),
                'tips'      => CreatorPaymentEvent::query()
                    ->where('creator_user_id', $user->id)
                    ->where('source', CreatorPaymentEvent::SOURCE_TIP)
                    ->where('amount_cents', '>', 0)
                    ->whereBetween('occurred_at', [$start, $end])->count(),
            ],
            'earnings' => [
                'tips_total'    => round($tipsCents / 100, 2),
                'subs_total'    => round($subsCents / 100, 2),
                'payouts_total' => round($payoutsCents / 100, 2),
                'currency'      => $currency,
            ],
        ]);
    }

    /** @return array{0:string,1:Carbon,2:Carbon} */
    protected function resolveRange(Request $request): array
    {
        $range = $request->query('range', '30d');
        if (!isset(self::RANGES[$range])) $range = '30d';
        $days  = self::RANGES[$range];
        $end   = now()->endOfDay();
        $start = now()->subDays($days - 1)->startOfDay();
        return [$range, $start, $end];
    }
}
