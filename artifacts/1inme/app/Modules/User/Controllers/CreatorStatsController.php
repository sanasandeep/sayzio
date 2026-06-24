<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\CreatorPost;
use App\Modules\User\Models\CreatorPostComment;
use App\Modules\User\Models\CreatorPostReaction;
use App\Modules\User\Models\CreatorSubscription;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\PostUnlock;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unified Stats home for creators (Task #1211).
 *
 * Pulls from existing models — Follow, CreatorPost, CreatorPostReaction,
 * CreatorPostComment, CreatorSubscription, PostUnlock — and renders one
 * dashboard with the four KPIs you actually look at: audience growth,
 * content output, engagement, and earnings. Independent from the
 * Monetization page (which focuses on tiers/promos/payouts) so creators
 * can land here for the "how am I doing this week?" question and bounce
 * to Monetization only when they need to act on it.
 */
class CreatorStatsController extends Controller
{
    /** Allowed range presets for the date picker. */
    public const RANGES = [
        '7d'  => ['label' => 'Last 7 days',  'days' => 7],
        '30d' => ['label' => 'Last 30 days', 'days' => 30],
        '90d' => ['label' => 'Last 90 days', 'days' => 90],
        '1y'  => ['label' => 'Last 12 months', 'days' => 365],
    ];

    public function index(Request $request)
    {
        $user = Auth::user();
        [$range, $start, $end] = $this->resolveRange($request);

        $kpis = $this->kpis($user, $start, $end);
        $audienceSeries = $this->dailySeries(
            Follow::query()->where('creator_id', $user->id),
            'created_at', $start, $end
        );
        $contentSeries  = $this->dailySeries(
            CreatorPost::query()->withoutGlobalScope('workspace')
                ->where('user_id', $user->id)->whereNotNull('published_at'),
            'published_at', $start, $end
        );

        $topPosts = CreatorPost::query()
            ->withoutGlobalScope('workspace')
            ->where('user_id', $user->id)
            ->whereNotNull('published_at')
            ->where('published_at', '>=', $start)
            ->orderByDesc(DB::raw('COALESCE(reactions_count,0) + COALESCE(comments_count,0)'))
            ->limit(10)
            ->get(['id', 'title', 'body', 'reactions_count', 'comments_count', 'published_at']);

        return view('user.stats.index', [
            'user'           => $user,
            'range'          => $range,
            'rangeLabel'     => self::RANGES[$range]['label'] ?? $range,
            'ranges'         => self::RANGES,
            'start'          => $start,
            'end'            => $end,
            'kpis'           => $kpis,
            'audienceSeries' => $audienceSeries,
            'contentSeries'  => $contentSeries,
            'topPosts'       => $topPosts,
        ]);
    }

    /**
     * CSV export of the table the dashboard shows. We export per-day
     * counters because that's the granularity creators want to drop into
     * a spreadsheet for monthly reports — anything richer is in-app.
     */
    public function export(Request $request)
    {
        if (!workspace_owner()?->getPlanFeature('analytics_export', true)) {
            return back()->with('error', 'Exporting stats is a paid feature. Upgrade your plan to download CSV exports.');
        }

        $user = Auth::user();
        [$range, $start, $end] = $this->resolveRange($request);

        $followsByDay   = $this->dailySeries(
            Follow::query()->where('creator_id', $user->id),
            'created_at', $start, $end);
        $postsByDay     = $this->dailySeries(
            CreatorPost::query()->withoutGlobalScope('workspace')
                ->where('user_id', $user->id)->whereNotNull('published_at'),
            'published_at', $start, $end);
        $reactionsByDay = $this->dailySeries(
            CreatorPostReaction::query()
                ->whereIn('post_id', CreatorPost::query()->withoutGlobalScope('workspace')->where('user_id', $user->id)->pluck('id')),
            'created_at', $start, $end);
        $commentsByDay  = $this->dailySeries(
            CreatorPostComment::query()
                ->whereIn('post_id', CreatorPost::query()->withoutGlobalScope('workspace')->where('user_id', $user->id)->pluck('id')),
            'created_at', $start, $end);

        $filename = "1inme-stats-{$user->handle}-{$range}.csv";
        return response()->streamDownload(function () use ($followsByDay, $postsByDay, $reactionsByDay, $commentsByDay) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'New followers', 'Posts published', 'Reactions', 'Comments']);
            foreach ($followsByDay as $day => $followers) {
                fputcsv($out, [
                    $day,
                    $followers,
                    $postsByDay[$day]     ?? 0,
                    $reactionsByDay[$day] ?? 0,
                    $commentsByDay[$day]  ?? 0,
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** @return array{0:string,1:Carbon,2:Carbon} */
    protected function resolveRange(Request $request): array
    {
        $range = $request->query('range', '30d');
        if (!isset(self::RANGES[$range])) $range = '30d';
        $days = self::RANGES[$range]['days'];
        $end   = now()->endOfDay();
        $start = now()->subDays($days - 1)->startOfDay();
        return [$range, $start, $end];
    }

    protected function kpis($user, Carbon $start, Carbon $end): array
    {
        $postIds = CreatorPost::query()->withoutGlobalScope('workspace')
            ->where('user_id', $user->id)->pluck('id');

        return [
            'followers_total'     => (int) ($user->followers_count ?? 0),
            'followers_new'       => Follow::where('creator_id', $user->id)
                ->whereBetween('created_at', [$start, $end])->count(),
            'posts_published'     => CreatorPost::query()->withoutGlobalScope('workspace')
                ->where('user_id', $user->id)->whereNotNull('published_at')
                ->whereBetween('published_at', [$start, $end])->count(),
            'reactions'           => CreatorPostReaction::whereIn('post_id', $postIds)
                ->whereBetween('created_at', [$start, $end])->count(),
            'comments'            => CreatorPostComment::whereIn('post_id', $postIds)
                ->whereBetween('created_at', [$start, $end])->count(),
            'subscribers_active'  => CreatorSubscription::where('creator_user_id', $user->id)
                ->whereIn('status', ['active', 'trialing'])->count(),
            'subscribers_new'     => CreatorSubscription::where('creator_user_id', $user->id)
                ->whereBetween('created_at', [$start, $end])
                ->whereIn('status', ['active', 'trialing'])->count(),
            'unlock_revenue_cents'=> (int) PostUnlock::whereIn('post_id', $postIds)
                ->whereBetween('unlocked_at', [$start, $end])->whereNull('refunded_at')
                ->sum('price_cents'),
        ];
    }

    /**
     * Walk every day in the range and return [YYYY-MM-DD => count]. We
     * loop in PHP rather than relying on DB-specific date_trunc so the
     * same code runs on Postgres + MySQL.
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
            $out[$key] = (int) ($rows[$key] ?? 0);
        }
        return $out;
    }
}
