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
use Illuminate\Support\Facades\DB;

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
 *
 * On top of the KPI totals this also returns daily `trends` (new
 * followers + posts published per day, zero-filled across the range) so
 * the phone can draw the same growth sparkline the web dashboard charts,
 * and the `analytics_export` capability so the screen can gate its CSV
 * export action without a second profile round-trip. The requested range
 * is clamped to the plan's `stats_retention_days` window (mirroring the
 * web LinkController analytics clamp) so callers can't read history older
 * than their plan retains.
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

        [, $start, $end] = $this->resolveRange($request, $user);

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
            // Daily, zero-filled series mirroring the web dashboard's
            // audience/content growth charts so the phone can draw a
            // sparkline instead of just standalone totals.
            'trends' => [
                'followers' => $this->dailySeries(
                    Follow::query()->where('creator_id', $user->id),
                    'created_at', $start, $end
                ),
                'posts' => $this->dailySeries(
                    CreatorPost::query()->withoutGlobalScope('workspace')
                        ->where('user_id', $user->id)->whereNotNull('published_at'),
                    'published_at', $start, $end
                ),
            ],
            // Mirror the web `analytics_export` ("Stats CSV export") paid
            // gate so the screen can show/hide the export action from the
            // same payload (defaults true, matching the server fallback).
            'capabilities' => [
                'analytics_export' => (bool) $user->getPlanFeature('analytics_export', true),
            ],
        ]);
    }

    /**
     * Resolve the requested preset range, clamping the start to the plan's
     * stats-history retention window so callers can't read analytics older
     * than their plan retains (`-1` = unlimited, no clamp). Mirrors the web
     * LinkController analytics clamp.
     *
     * @return array{0:string,1:Carbon,2:Carbon}
     */
    protected function resolveRange(Request $request, $user): array
    {
        $range = $request->query('range', '30d');
        if (!isset(self::RANGES[$range])) $range = '30d';
        $days  = self::RANGES[$range];
        $end   = now()->endOfDay();
        $start = now()->subDays($days - 1)->startOfDay();

        $retentionDays = $user->statsRetentionDays();
        if ($retentionDays !== -1) {
            $earliest = now()->subDays($retentionDays)->startOfDay();
            if ($start->lt($earliest)) {
                $start = $earliest;
            }
        }

        return [$range, $start, $end];
    }

    /**
     * Walk every day in the range and return a list of {date, value}
     * points (zero-filled). Looped in PHP rather than relying on
     * DB-specific date_trunc so the same code runs on Postgres + MySQL,
     * matching the web CreatorStatsController::dailySeries().
     *
     * @return list<array{date:string,value:int}>
     */
    protected function dailySeries($baseQuery, string $column, Carbon $start, Carbon $end): array
    {
        $rows = (clone $baseQuery)
            ->select(DB::raw("DATE($column) as d"), DB::raw('COUNT(*) as c'))
            ->whereBetween($column, [$start, $end])
            ->groupBy(DB::raw("DATE($column)"))
            ->pluck('c', 'd')->all();

        $out = [];
        for ($d = $start->copy(); $d <= $end; $d->addDay()) {
            $key = $d->format('Y-m-d');
            $out[] = ['date' => $key, 'value' => (int) ($rows[$key] ?? 0)];
        }
        return $out;
    }
}
