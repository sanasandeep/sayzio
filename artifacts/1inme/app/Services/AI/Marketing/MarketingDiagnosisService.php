<?php

namespace App\Services\AI\Marketing;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Task #3281 — grounded performance diagnosis.
 *
 * Turns a creator's OWN real tracking data (link clicks, page sessions,
 * block views) into a deterministic, PII-free diagnosis: headline metrics,
 * breakdowns (device / geo / referrer / best time), a trend vs the prior
 * window, dead links (live but no traffic) and conversion-blind links
 * (traffic but no measurable dwell). The AI layer later *explains* these
 * numbers — it never invents them.
 *
 * Every query is table-guarded + try/catch wrapped so a partially-migrated
 * or empty account degrades to an honest "not enough data" result rather
 * than throwing. Scoped by links.user_id (mirrors the strategist's own
 * link snapshot).
 */
class MarketingDiagnosisService
{
    /** Rolling analysis window, in days. */
    public const WINDOW_DAYS = 30;

    /** Goal metrics we can baseline / re-measure deterministically. */
    public const METRICS = ['clicks', 'reach', 'engagement', 'sessions', 'views', 'subscribers', 'followers', 'orders', 'revenue'];

    /**
     * Full diagnosis payload used by the scorecard, forecast and the AI
     * prompt. Shape is stable so persisted snapshots round-trip.
     *
     * @return array{
     *   window_days:int, has_data:bool,
     *   metrics:array<string,int|float>,
     *   trend:array{clicks_prev:int, clicks_delta_pct:int|null},
     *   breakdowns:array<string,mixed>,
     *   findings:array<int,array{key:string,label:string,detail:string,direction:string,severity:string}>,
     *   dead_links:array<int,array{title:string,alias:string,type:string}>,
     *   conversion_blind:array<int,array{title:string,alias:string,clicks:int}>
     * }
     */
    public function diagnose(User $user, ?int $workspaceId = null): array
    {
        $linkIds = $this->linkIds($user);

        $now   = Carbon::now();
        $start = (clone $now)->subDays(self::WINDOW_DAYS);
        $prev  = (clone $now)->subDays(self::WINDOW_DAYS * 2);

        $metrics = [
            'clicks'          => 0,
            'human_clicks'    => 0,
            'impressions'     => 0,
            'sessions'        => 0,
            'avg_seconds'     => 0,
            'bounce_rate'     => 0,
            'countries'       => 0,
            'active_days'     => 0,
            'engaged_blocks'  => 0,
        ];
        $breakdowns = [
            'device'    => [],
            'geo'       => [],
            'referrer'  => [],
            'best_day'  => null,
            'best_hour' => null,
        ];
        $trend      = ['clicks_prev' => 0, 'clicks_delta_pct' => null];
        $findings   = [];
        $deadLinks  = [];
        $convBlind  = [];

        if (empty($linkIds)) {
            return [
                'window_days'      => self::WINDOW_DAYS,
                'has_data'         => false,
                'metrics'          => $metrics,
                'trend'            => $trend,
                'breakdowns'       => $breakdowns,
                'findings'         => [[
                    'key' => 'no_links', 'label' => 'No links yet',
                    'detail' => 'Create your first link so we can start measuring reach and engagement.',
                    'direction' => 'flat', 'severity' => 'info',
                ]],
                'dead_links'       => [],
                'conversion_blind' => [],
            ];
        }

        // ---- link_clicks: volume, trend, breakdowns -------------------
        if ($this->hasTable('link_clicks')) {
            try {
                $base = DB::table('link_clicks')->whereIn('link_id', $linkIds);

                $metrics['clicks'] = (int) (clone $base)
                    ->whereBetween('clicked_at', [$start, $now])->count();

                $humanBase = (clone $base)->whereBetween('clicked_at', [$start, $now]);
                if (Schema::hasColumn('link_clicks', 'is_bot')) {
                    $humanBase->where(function ($q) {
                        $q->where('is_bot', false)->orWhereNull('is_bot');
                    });
                }
                $metrics['human_clicks'] = (int) $humanBase->count();

                $trend['clicks_prev'] = (int) (clone $base)
                    ->whereBetween('clicked_at', [$prev, $start])->count();
                if ($trend['clicks_prev'] > 0) {
                    $trend['clicks_delta_pct'] = (int) round(
                        (($metrics['clicks'] - $trend['clicks_prev']) / max(1, $trend['clicks_prev'])) * 100
                    );
                } elseif ($metrics['clicks'] > 0) {
                    $trend['clicks_delta_pct'] = 100;
                }

                $metrics['countries'] = (int) (clone $base)
                    ->whereBetween('clicked_at', [$start, $now])
                    ->whereNotNull('country_code')
                    ->distinct()->count('country_code');

                $metrics['active_days'] = (int) (clone $base)
                    ->whereBetween('clicked_at', [$start, $now])
                    ->select(DB::raw('DATE(clicked_at) as d'))
                    ->distinct()->get()->count();

                $breakdowns['device']   = $this->topCounts((clone $base)->whereBetween('clicked_at', [$start, $now]), 'device_type', 4);
                $breakdowns['geo']      = $this->topCounts((clone $base)->whereBetween('clicked_at', [$start, $now]), 'country_code', 5);
                $refCol = Schema::hasColumn('link_clicks', 'channel') ? 'channel' : 'referrer';
                $breakdowns['referrer'] = $this->topCounts((clone $base)->whereBetween('clicked_at', [$start, $now]), $refCol, 5);

                $best = (clone $base)->whereBetween('clicked_at', [$start, $now])
                    ->select(DB::raw('EXTRACT(DOW FROM clicked_at)::int as dow'), DB::raw('count(*) as c'))
                    ->groupBy('dow')->orderByDesc('c')->first();
                if ($best) {
                    $names = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                    $breakdowns['best_day'] = $names[(int) $best->dow] ?? null;
                }
                $bestHour = (clone $base)->whereBetween('clicked_at', [$start, $now])
                    ->select(DB::raw('EXTRACT(HOUR FROM clicked_at)::int as h'), DB::raw('count(*) as c'))
                    ->groupBy('h')->orderByDesc('c')->first();
                if ($bestHour) {
                    $breakdowns['best_hour'] = (int) $bestHour->h;
                }
            } catch (\Throwable $e) {
                // leave metrics at defaults
            }
        }

        // ---- page_sessions: dwell + bounce ---------------------------
        if ($this->hasTable('page_sessions')) {
            try {
                $sess = DB::table('page_sessions')->whereIn('link_id', $linkIds)
                    ->whereBetween('started_at', [$start, $now]);
                $metrics['sessions']    = (int) (clone $sess)->count();
                $metrics['avg_seconds'] = (int) round((float) (clone $sess)->avg('duration_seconds'));
                if ($metrics['sessions'] > 0) {
                    $bounced = (int) (clone $sess)->where('duration_seconds', '<', 10)->count();
                    $metrics['bounce_rate'] = (int) round(($bounced / $metrics['sessions']) * 100);
                }
            } catch (\Throwable $e) {
            }
        }

        // ---- block_views: impressions + engaged rate -----------------
        if ($this->hasTable('block_views')) {
            try {
                $bv = DB::table('block_views')->whereIn('link_id', $linkIds)
                    ->whereBetween('first_viewed_at', [$start, $now]);
                $metrics['impressions'] = (int) (clone $bv)->sum('impression_count');
                $seen = (int) (clone $bv)->count();
                if ($seen > 0) {
                    $engaged = (int) (clone $bv)->where('view_duration_ms', '>=', 1000)->count();
                    $metrics['engaged_blocks'] = (int) round(($engaged / $seen) * 100);
                }
            } catch (\Throwable $e) {
            }
        }

        // ---- dead links (live, no clicks in window) ------------------
        try {
            $liveLinks = Link::query()->where('user_id', $user->id)
                ->when(Schema::hasColumn('links', 'is_active'), fn ($q) => $q->where('is_active', true))
                ->get(['id', 'title', 'alias', 'type', 'total_clicks']);

            $clickedIds = [];
            if ($this->hasTable('link_clicks')) {
                $clickedIds = DB::table('link_clicks')->whereIn('link_id', $linkIds)
                    ->whereBetween('clicked_at', [$start, $now])
                    ->distinct()->pluck('link_id')->all();
            }
            $clickedSet = array_flip(array_map('intval', $clickedIds));

            $sessionByLink = [];
            if ($this->hasTable('page_sessions')) {
                $sessionByLink = array_flip(array_map('intval',
                    DB::table('page_sessions')->whereIn('link_id', $linkIds)
                        ->whereBetween('started_at', [$start, $now])
                        ->distinct()->pluck('link_id')->all()
                ));
            }

            foreach ($liveLinks as $l) {
                $hasClicks = isset($clickedSet[(int) $l->id]);
                if (!$hasClicks && count($deadLinks) < 8) {
                    $deadLinks[] = [
                        'title' => (string) ($l->title ?: 'Untitled'),
                        'alias' => (string) $l->alias,
                        'type'  => (string) $l->type,
                    ];
                }
                // conversion-blind: got clicks but no measured session/dwell
                if ($hasClicks && !isset($sessionByLink[(int) $l->id]) && count($convBlind) < 8) {
                    $convBlind[] = [
                        'title'  => (string) ($l->title ?: 'Untitled'),
                        'alias'  => (string) $l->alias,
                        'clicks' => (int) $l->total_clicks,
                    ];
                }
            }
        } catch (\Throwable $e) {
        }

        $hasData = $metrics['clicks'] > 0 || $metrics['sessions'] > 0 || $metrics['impressions'] > 0;
        $findings = $this->deriveFindings($metrics, $trend, $breakdowns, $deadLinks, $convBlind, $hasData);

        return [
            'window_days'      => self::WINDOW_DAYS,
            'has_data'         => $hasData,
            'metrics'          => $metrics,
            'trend'            => $trend,
            'breakdowns'       => $breakdowns,
            'findings'         => $findings,
            'dead_links'       => $deadLinks,
            'conversion_blind' => $convBlind,
        ];
    }

    /**
     * A single measurable value for a goal metric over a window, used for
     * baseline capture and later outcome re-measurement. Deterministic.
     */
    public function metricValue(User $user, string $metric, int $days = self::WINDOW_DAYS): int
    {
        $linkIds = $this->linkIds($user);
        if (empty($linkIds)) {
            return 0;
        }
        $now   = Carbon::now();
        $start = (clone $now)->subDays(max(1, $days));

        try {
            switch ($metric) {
                case 'subscribers':
                    return $this->hasTable('subscribers')
                        ? (int) DB::table('subscribers')->where('user_id', $user->id)
                            ->where('status', 'active')
                            ->whereBetween('subscribed_at', [$start, $now])->count()
                        : 0;
                case 'followers':
                    return $this->hasTable('follows')
                        ? (int) DB::table('follows')->where('creator_id', $user->id)
                            ->whereBetween('created_at', [$start, $now])->count()
                        : 0;
                case 'orders':
                    return $this->countOrders($user, $linkIds, $start, $now);
                case 'revenue':
                    return $this->sumRevenue($user, $linkIds, $start, $now);
                case 'views':
                    // Page views across the creator's links: prefer session
                    // starts (one per visit), fall back to block impressions.
                    if ($this->hasTable('page_sessions')) {
                        return (int) DB::table('page_sessions')->whereIn('link_id', $linkIds)
                            ->whereBetween('started_at', [$start, $now])->count();
                    }
                    return $this->hasTable('block_views')
                        ? (int) DB::table('block_views')->whereIn('link_id', $linkIds)
                            ->whereBetween('first_viewed_at', [$start, $now])->sum('impression_count')
                        : 0;
                case 'sessions':
                    return $this->hasTable('page_sessions')
                        ? (int) DB::table('page_sessions')->whereIn('link_id', $linkIds)
                            ->whereBetween('started_at', [$start, $now])->count()
                        : 0;
                case 'engagement':
                    return $this->hasTable('page_sessions')
                        ? (int) round((float) DB::table('page_sessions')->whereIn('link_id', $linkIds)
                            ->whereBetween('started_at', [$start, $now])->avg('duration_seconds'))
                        : 0;
                case 'reach':
                    return $this->hasTable('block_views')
                        ? (int) DB::table('block_views')->whereIn('link_id', $linkIds)
                            ->whereBetween('first_viewed_at', [$start, $now])->sum('impression_count')
                        : 0;
                case 'clicks':
                default:
                    if (!$this->hasTable('link_clicks')) {
                        return 0;
                    }
                    $q = DB::table('link_clicks')->whereIn('link_id', $linkIds)
                        ->whereBetween('clicked_at', [$start, $now]);
                    if (Schema::hasColumn('link_clicks', 'is_bot')) {
                        $q->where(fn ($w) => $w->where('is_bot', false)->orWhereNull('is_bot'));
                    }
                    return (int) $q->count();
            }
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Count fulfilled-intent orders across every commerce surface tied to
     * the creator: restaurant orders, store order-requests (both scoped by
     * link_id) and paid product-storefront checkouts (scoped by creator).
     * Cancelled orders never count.
     */
    protected function countOrders(User $user, array $linkIds, Carbon $start, Carbon $now): int
    {
        $total = 0;
        foreach (['restaurant_orders', 'store_orders'] as $table) {
            if (!$this->hasTable($table)) {
                continue;
            }
            try {
                $total += (int) DB::table($table)->whereIn('link_id', $linkIds)
                    ->where('status', '!=', 'cancelled')
                    ->whereBetween('created_at', [$start, $now])->count();
            } catch (\Throwable $e) {
            }
        }
        if ($this->hasTable('product_orders')) {
            try {
                $total += (int) DB::table('product_orders')
                    ->where('creator_user_id', $user->id)
                    ->whereIn('status', ['paid', 'fulfilled'])
                    ->whereBetween('created_at', [$start, $now])->count();
            } catch (\Throwable $e) {
            }
        }
        return $total;
    }

    /**
     * Sum gross revenue (whole currency units, rounded) from the same
     * commerce surfaces as countOrders(). Restaurant/store totals are an
     * estimated bill (no payment collected); product orders are real paid
     * checkouts stored in cents.
     */
    protected function sumRevenue(User $user, array $linkIds, Carbon $start, Carbon $now): int
    {
        $total = 0.0;
        foreach (['restaurant_orders', 'store_orders'] as $table) {
            if (!$this->hasTable($table)) {
                continue;
            }
            try {
                $col = Schema::hasColumn($table, 'total') ? 'total' : 'subtotal';
                $total += (float) DB::table($table)->whereIn('link_id', $linkIds)
                    ->where('status', '!=', 'cancelled')
                    ->whereBetween('created_at', [$start, $now])->sum($col);
            } catch (\Throwable $e) {
            }
        }
        if ($this->hasTable('product_orders')) {
            try {
                $cents = (float) DB::table('product_orders')
                    ->where('creator_user_id', $user->id)
                    ->whereIn('status', ['paid', 'fulfilled'])
                    ->whereBetween('created_at', [$start, $now])->sum('subtotal_cents');
                $total += $cents / 100;
            } catch (\Throwable $e) {
            }
        }
        return (int) round($total);
    }

    /** Normalise a requested goal metric to a supported key. */
    public function normalizeMetric(?string $metric): string
    {
        $metric = strtolower(trim((string) $metric));
        return in_array($metric, self::METRICS, true) ? $metric : 'clicks';
    }

    /** @return array<int,int> owned link ids (user-scoped) */
    protected function linkIds(User $user): array
    {
        try {
            return Link::query()->where('user_id', $user->id)->pluck('id')->map(fn ($v) => (int) $v)->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Top-N value counts for a column, dropping NULL/blank buckets. */
    protected function topCounts($query, string $column, int $limit): array
    {
        try {
            $rows = $query->select($column, DB::raw('count(*) as c'))
                ->whereNotNull($column)
                ->groupBy($column)->orderByDesc('c')->limit($limit)->get();
            $out = [];
            foreach ($rows as $r) {
                $key = trim((string) $r->{$column});
                if ($key === '') {
                    continue;
                }
                $out[] = ['label' => $key, 'count' => (int) $r->c];
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Rule-based findings from the numbers. Each finding carries a
     * direction (up/down/flat) and severity (good/warn/critical/info) so
     * the UI can colour it and the AI can explain it.
     */
    protected function deriveFindings(array $m, array $trend, array $bd, array $dead, array $blind, bool $hasData): array
    {
        $f = [];
        if (!$hasData) {
            $f[] = ['key' => 'cold_start', 'label' => 'Not enough traffic yet',
                'detail' => 'We need a bit more activity in the last ' . self::WINDOW_DAYS . ' days to diagnose trends. Share your links to start the data flowing.',
                'direction' => 'flat', 'severity' => 'info'];
            return $f;
        }

        if ($trend['clicks_delta_pct'] !== null) {
            if ($trend['clicks_delta_pct'] >= 15) {
                $f[] = ['key' => 'clicks_up', 'label' => 'Clicks trending up',
                    'detail' => 'Clicks rose ' . $trend['clicks_delta_pct'] . '% vs the previous ' . self::WINDOW_DAYS . ' days — keep the momentum.',
                    'direction' => 'up', 'severity' => 'good'];
            } elseif ($trend['clicks_delta_pct'] <= -15) {
                $f[] = ['key' => 'clicks_down', 'label' => 'Clicks slowing down',
                    'detail' => 'Clicks fell ' . abs($trend['clicks_delta_pct']) . '% vs the previous window — worth a fresh push.',
                    'direction' => 'down', 'severity' => 'warn'];
            }
        }

        if ($m['avg_seconds'] > 0) {
            if ($m['avg_seconds'] < 15) {
                $f[] = ['key' => 'low_dwell', 'label' => 'Visitors leave quickly',
                    'detail' => "Average dwell time is {$m['avg_seconds']}s. Lead with a stronger hero and put your key action higher.",
                    'direction' => 'down', 'severity' => 'warn'];
            } elseif ($m['avg_seconds'] >= 40) {
                $f[] = ['key' => 'good_dwell', 'label' => 'Strong dwell time',
                    'detail' => "Visitors spend {$m['avg_seconds']}s on average — your content is holding attention.",
                    'direction' => 'up', 'severity' => 'good'];
            }
        }
        if ($m['bounce_rate'] >= 60) {
            $f[] = ['key' => 'high_bounce', 'label' => 'High bounce rate',
                'detail' => "{$m['bounce_rate']}% of visits end in under 10s. Tighten your above-the-fold message.",
                'direction' => 'down', 'severity' => 'warn'];
        }

        if ($m['countries'] >= 8) {
            $f[] = ['key' => 'broad_reach', 'label' => 'Broad geographic reach',
                'detail' => "Traffic from {$m['countries']} countries — consider localised CTAs for your top regions.",
                'direction' => 'up', 'severity' => 'good'];
        }

        if (!empty($bd['device'])) {
            $top = $bd['device'][0]['label'] ?? '';
            if (strtolower($top) === 'mobile') {
                $f[] = ['key' => 'mobile_first', 'label' => 'Mobile-first audience',
                    'detail' => 'Most visitors are on mobile — keep buttons thumb-friendly and media light.',
                    'direction' => 'flat', 'severity' => 'info'];
            }
        }

        if (!empty($dead)) {
            $n = count($dead);
            $f[] = ['key' => 'dead_links', 'label' => $n . ' link' . ($n === 1 ? '' : 's') . ' getting no traffic',
                'detail' => 'These live links had zero clicks this window — refresh, promote or retire them.',
                'direction' => 'down', 'severity' => 'warn'];
        }
        if (!empty($blind)) {
            $n = count($blind);
            $f[] = ['key' => 'conversion_blind', 'label' => $n . ' link' . ($n === 1 ? '' : 's') . ' with clicks but no dwell',
                'detail' => 'People click but leave before engaging — check the landing content matches the promise.',
                'direction' => 'down', 'severity' => 'warn'];
        }

        if (empty($f)) {
            $f[] = ['key' => 'steady', 'label' => 'Steady performance',
                'detail' => 'No major red flags this window — the plan below focuses on growth from a stable base.',
                'direction' => 'flat', 'severity' => 'good'];
        }

        return $f;
    }

    protected function hasTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
