<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\LinkClick;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The combined report behind the Reports tab: whatever set of links the
 * filters resolve to, reported on as one project.
 *
 * Two things this class exists to get right.
 *
 * First, TIME ZONE. `link_clicks.clicked_at` is a naive timestamp holding
 * UTC. A heatmap or a daily total built on the raw column is silently wrong
 * by the viewer's offset — for an owner in Asia/Kolkata that is five and a
 * half hours, enough to move half the evening's traffic onto the previous
 * day and make "busiest hour" useless. Every date and hour below is derived
 * as `clicked_at AT TIME ZONE 'UTC' AT TIME ZONE <viewer zone>`.
 *
 * Second, DRIFT. Every breakdown the report offers is declared once in
 * {@see self::BREAKDOWNS}. The view renders that list, the CSV export walks
 * that list, and the guard test walks it too, so a breakdown can never be
 * offered by one and missing from another — the failure this codebase keeps
 * repeating in other shapes.
 *
 * Bot traffic is excluded by the global scope on {@see LinkClick}; passing
 * `includeBots` opts back in via `LinkClick::withBots()`.
 */
class LinkReport
{
    /** Range presets offered in the filter bar, in days. */
    public const PRESETS = [7, 30, 90, 365];

    /** Hard ceiling on a custom range, before plan retention is applied. */
    public const MAX_RANGE_DAYS = 366;

    /** Rows kept per breakdown table. */
    public const TOP_ROWS = 12;

    /** Rows kept in the all-links table. */
    public const LINK_ROWS = 100;

    /**
     * Every breakdown this report can produce.
     *
     * key => [column, label, empty] where `empty` is what to show for rows
     * whose column is null — "Direct" reads better than "(none)" for a
     * referrer, "Unknown" is right for a country.
     */
    public const BREAKDOWNS = [
        'sources'   => ['column' => 'source',       'label' => 'Traffic Source',    'empty' => 'Direct'],
        'referrers' => ['column' => 'referrer',     'label' => 'Top Referrers',     'empty' => 'Direct'],
        'channels'  => ['column' => 'channel',      'label' => 'Channel',           'empty' => 'Unknown'],
        'countries' => ['column' => 'country_code', 'label' => 'Top Countries',     'empty' => 'Unknown'],
        'cities'    => ['column' => 'city',         'label' => 'Top Cities',        'empty' => 'Unknown'],
        'devices'   => ['column' => 'device_type',  'label' => 'Devices',           'empty' => 'Unknown'],
        'browsers'  => ['column' => 'browser',      'label' => 'Browsers',          'empty' => 'Unknown'],
        'systems'   => ['column' => 'os',           'label' => 'Operating Systems', 'empty' => 'Unknown'],
        'languages' => ['column' => 'language',     'label' => 'Languages',         'empty' => 'Unknown'],
    ];

    /** Short weekday labels, Sunday first, matching Postgres EXTRACT(DOW). */
    public const DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    private Builder $links;

    /**
     * `$links` is any way of naming a set of links: an Eloquent builder, or
     * the `HasMany` relation the My Links page already assembles from the
     * request filters. A relation is unwrapped to its underlying builder
     * here, once, so every query below has one type to reason about.
     */
    public function __construct(
        Builder|Relation $links,
        private Carbon $from,
        private Carbon $to,
        private string $timezone,
        private bool $includeBots = false,
    ) {
        $this->links = $links instanceof Relation ? $links->getQuery() : $links;
    }

    /**
     * Clamp a requested range to something sane and to what the plan keeps.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function range(?string $from, ?string $to, int $retentionDays, string $timezone): array
    {
        $end = self::parse($to, $timezone) ?? Carbon::now($timezone);
        $start = self::parse($from, $timezone) ?? $end->copy()->subDays(29);

        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        if ($start->diffInDays($end) > self::MAX_RANGE_DAYS) {
            $start = $end->copy()->subDays(self::MAX_RANGE_DAYS);
        }

        // A plan that keeps 30 days of statistics cannot answer a 90-day
        // question; silently returning zeros would read as "no traffic".
        if ($retentionDays >= 0 && $retentionDays !== PHP_INT_MAX) {
            $earliest = Carbon::now($timezone)->subDays(max(1, $retentionDays));
            if ($start->lt($earliest)) {
                $start = $earliest;
            }
        }

        return [$start->startOfDay(), $end->endOfDay()];
    }

    private static function parse(?string $value, string $timezone): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value, $timezone);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Headline figures. Uniques are distinct IPs, which is all we store. */
    public function totals(): array
    {
        $row = $this->clicks()
            ->selectRaw('COUNT(*) AS clicks')
            ->selectRaw('COUNT(DISTINCT ip_address) AS visitors')
            ->selectRaw('COUNT(DISTINCT link_id) AS links')
            ->first();

        $daily = $this->daily();
        $busiest = collect($daily)->sortDesc()->keys()->first();

        return [
            'clicks'   => (int) ($row->clicks ?? 0),
            'visitors' => (int) ($row->visitors ?? 0),
            'links'    => (int) ($row->links ?? 0),
            'busiest'  => ($busiest && ($daily[$busiest] ?? 0) > 0) ? $busiest : null,
            'perDay'   => count($daily) > 0 ? round(array_sum($daily) / count($daily), 1) : 0.0,
        ];
    }

    /**
     * Clicks per local day across the whole range, zero-filled so the chart
     * has a bar for every day rather than only the days with traffic.
     *
     * @return array<string, int>
     */
    public function daily(): array
    {
        $rows = $this->clicks()
            ->selectRaw('('.$this->localExpression().')::date AS day')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $series = [];
        $cursor = $this->from->copy();
        while ($cursor->lte($this->to)) {
            $key = $cursor->toDateString();
            $series[$key] = (int) ($rows[$key] ?? 0);
            $cursor->addDay();
        }

        return $series;
    }

    /**
     * Weekday × hour grid in the viewer's zone: when this audience actually
     * clicks. Returns every cell, including the empty ones, so the grid is
     * a real 7×24 and not a sparse list the view has to pad.
     *
     * @return array{grid: array<int, array<int, int>>, max: int, peak: ?array{day: int, hour: int}}
     */
    public function heatmap(): array
    {
        $rows = $this->clicks()
            ->selectRaw('EXTRACT(DOW FROM '.$this->localExpression().')::int AS dow')
            ->selectRaw('EXTRACT(HOUR FROM '.$this->localExpression().')::int AS hour')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('dow', 'hour')
            ->get();

        $grid = [];
        for ($day = 0; $day < 7; $day++) {
            $grid[$day] = array_fill(0, 24, 0);
        }

        $max = 0;
        $peak = null;
        foreach ($rows as $row) {
            $total = (int) $row->total;
            $grid[(int) $row->dow][(int) $row->hour] = $total;
            if ($total > $max) {
                $max = $total;
                $peak = ['day' => (int) $row->dow, 'hour' => (int) $row->hour];
            }
        }

        return ['grid' => $grid, 'max' => $max, 'peak' => $peak];
    }

    /**
     * One breakdown table. Null values collapse into the declared `empty`
     * label rather than being dropped, so the column totals still add up to
     * the headline figure.
     *
     * @return Collection<int, array{label: string, clicks: int, share: float}>
     */
    public function breakdown(string $key): Collection
    {
        $spec = self::BREAKDOWNS[$key] ?? throw new \InvalidArgumentException("Unknown breakdown [$key].");
        $column = $spec['column'];

        $rows = $this->clicks()
            ->selectRaw("COALESCE(NULLIF($column, ''), ?) AS label", [$spec['empty']])
            ->selectRaw('COUNT(*) AS clicks')
            ->groupBy('label')
            ->orderByDesc('clicks')
            ->limit(self::TOP_ROWS)
            ->get();

        return $this->withShares($rows);
    }

    /** UTM campaigns live inside a jsonb column, so they get their own query. */
    public function campaigns(): Collection
    {
        $rows = $this->clicks()
            ->whereRaw("utm_params ->> 'utm_campaign' IS NOT NULL")
            ->selectRaw("utm_params ->> 'utm_campaign' AS label")
            ->selectRaw('COUNT(*) AS clicks')
            ->groupBy('label')
            ->orderByDesc('clicks')
            ->limit(self::TOP_ROWS)
            ->get();

        return $this->withShares($rows);
    }

    /**
     * Every link in scope with traffic in the range — the "all links detail"
     * table, not a top-N. Links with no clicks in the range are included so
     * the table answers "what is not working" as well as "what is".
     */
    public function links(): Collection
    {
        $stats = $this->clicks()
            ->selectRaw('link_id')
            ->selectRaw('COUNT(*) AS clicks')
            ->selectRaw('COUNT(DISTINCT ip_address) AS visitors')
            ->selectRaw('MAX(clicked_at) AS last_click')
            ->groupBy('link_id')
            ->get()
            ->keyBy('link_id');

        return (clone $this->links)
            ->select('links.id', 'links.title', 'links.alias', 'links.type', 'links.is_active', 'links.project_id')
            ->limit(self::LINK_ROWS)
            ->get()
            ->map(function ($link) use ($stats) {
                $row = $stats->get($link->id);

                return [
                    'link'     => $link,
                    'clicks'   => (int) ($row->clicks ?? 0),
                    'visitors' => (int) ($row->visitors ?? 0),
                    // MAX(clicked_at) comes back from the query builder as a
                    // string. Everything downstream wants a date, so it is
                    // parsed here rather than in each of the view and the
                    // export -- the raw value is UTC wall clock.
                    'lastClick' => isset($row->last_click)
                        ? Carbon::parse($row->last_click, 'UTC')
                        : null,
                ];
            })
            ->sortByDesc('clicks')
            ->values();
    }

    /** @return Collection<int, array{label: string, clicks: int, share: float}> */
    private function withShares(Collection $rows): Collection
    {
        $total = (int) $rows->sum('clicks');

        return $rows->map(fn ($row) => [
            'label'  => (string) $row->label,
            'clicks' => (int) $row->clicks,
            'share'  => $total > 0 ? round($row->clicks / $total * 100, 1) : 0.0,
        ])->values();
    }

    /** The click query every section starts from. */
    private function clicks(): \Illuminate\Database\Query\Builder
    {
        $query = $this->includeBots ? LinkClick::withBots() : LinkClick::query();

        return $query
            ->whereIn('link_id', (clone $this->links)->select('links.id'))
            ->whereBetween('clicked_at', [$this->from->copy()->utc(), $this->to->copy()->utc()])
            ->toBase();
    }

    /** `clicked_at` read in the viewer's zone rather than raw UTC. */
    private function localExpression(): string
    {
        // The zone comes from PlatformTimezone, which resolves to a known
        // identifier, but it is still quoted rather than interpolated raw.
        $zone = str_replace("'", "''", $this->timezone);

        return "clicked_at AT TIME ZONE 'UTC' AT TIME ZONE '{$zone}'";
    }

    // ------------------------------------------------------------------
    // The traffic layer the per-link stats page, the folder report and the
    // account report all read from.
    //
    // Everything below hangs off one filtered click query, so the only thing
    // that changes between "one link", "one folder" and "the whole account"
    // is the set of links handed to the constructor. That is the point: the
    // shapes returned here are the ones `show.blade.php` already consumes,
    // so the existing partials -- click-heatmap, chart-actions, chart-export,
    // recent-clicks-table -- render a folder without being touched.
    // ------------------------------------------------------------------

    /** Dimension filters, as the stats page already names them in the query string. */
    public const FILTERS = ['source', 'country_code', 'device_type', 'browser', 'os', 'language', 'channel', 'alias'];

    private array $filters = [];

    private ?string $baseLanguage = null;

    /** @param array<string, string|null> $filters */
    public function withFilters(array $filters, ?string $baseLanguage = null): static
    {
        $this->filters = array_filter(
            array_intersect_key($filters, array_flip(self::FILTERS)),
            fn ($v) => is_string($v) && $v !== '',
        );
        $this->baseLanguage = $baseLanguage ?: null;

        return $this;
    }

    /** The click query every figure on the page starts from. */
    private function scoped(): \Illuminate\Database\Eloquent\Builder
    {
        $query = ($this->includeBots ? LinkClick::withBots() : LinkClick::query())
            ->whereIn('link_id', (clone $this->links)->select('links.id'))
            ->whereBetween('clicked_at', [$this->from->copy()->utc(), $this->to->copy()->utc()]);

        foreach ($this->filters as $column => $value) {
            $query->where($column, $value);
        }

        if ($this->baseLanguage) {
            $query->whereRaw(
                "LOWER(SPLIT_PART(REPLACE(language, '_', '-'), '-', 1)) = ?",
                [$this->baseLanguage],
            );
        }

        return $query;
    }

    /**
     * Clicks per bucket, in the viewer's zone.
     *
     * `DATE_TRUNC` on the raw column buckets by UTC day. For an owner in
     * Asia/Kolkata that puts everything after 18:30 local on the next day's
     * bar, so the chart and the heatmap disagree with the clock on the wall.
     */
    public function series(string $groupBy = 'day'): Collection
    {
        $expr = $this->bucketExpression($groupBy);

        return $this->scoped()
            ->selectRaw("$expr as bucket, COUNT(*) as count, COUNT(DISTINCT ip_address) as unique_count")
            ->groupByRaw($expr)
            ->orderBy('bucket')
            ->get();
    }

    /** The same buckets for bot traffic, which the global scope hides by default. */
    public function botSeries(string $groupBy = 'day'): Collection
    {
        $expr = $this->bucketExpression($groupBy);

        return LinkClick::withBots()
            ->whereIn('link_id', (clone $this->links)->select('links.id'))
            ->where('is_bot', true)
            ->whereBetween('clicked_at', [$this->from->copy()->utc(), $this->to->copy()->utc()])
            ->selectRaw("$expr as bucket, COUNT(*) as count")
            ->groupByRaw($expr)
            ->orderBy('bucket')
            ->pluck('count', 'bucket');
    }

    private function bucketExpression(string $groupBy): string
    {
        $local = $this->localExpression();

        return match ($groupBy) {
            'week'  => "TO_CHAR(DATE_TRUNC('week', $local), 'YYYY-MM-DD')",
            'month' => "TO_CHAR(DATE_TRUNC('month', $local), 'YYYY-MM')",
            'year'  => "TO_CHAR(DATE_TRUNC('year', $local), 'YYYY')",
            default => "TO_CHAR(DATE_TRUNC('day', $local), 'YYYY-MM-DD')",
        };
    }

    /**
     * The 7 x 12 grid the shared click-heatmap partial draws: ISO weekday down
     * (Monday first), two-hour blocks across.
     *
     * @return array{rows: array<int, array{day: string, full: string, date: null, today: bool, blocks: array<int, int>}>, peak: int, peakLabel: string}
     */
    public function heat(): array
    {
        $local = $this->localExpression();
        $rows = array_fill(0, 7, array_fill(0, 12, 0));
        $peak = 0;
        $peakLabel = null;

        $this->scoped()
            ->selectRaw("EXTRACT(ISODOW FROM $local)::int AS dow, (EXTRACT(HOUR FROM $local)::int / 2) AS blk, COUNT(*) AS c")
            ->groupByRaw("EXTRACT(ISODOW FROM $local), (EXTRACT(HOUR FROM $local)::int / 2)")
            ->get()
            ->each(function ($r) use (&$rows, &$peak, &$peakLabel) {
                $day = ((int) $r->dow) - 1;          // ISODOW is 1..7, Monday first
                $block = min(11, max(0, (int) $r->blk));
                $count = (int) $r->c;

                if ($day < 0 || $day > 6) {
                    return;
                }

                $rows[$day][$block] = $count;

                if ($count > $peak) {
                    $peak = $count;
                    $peakLabel = self::ISO_DAYS[$day].' '.str_pad((string) ($block * 2), 2, '0', STR_PAD_LEFT).':00';
                }
            });

        $fullDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $displayRows = [];
        foreach ($rows as $day => $blocks) {
            $displayRows[] = [
                'day' => self::ISO_DAYS[$day],
                'full' => $fullDays[$day],
                'date' => null,
                'today' => false,
                'blocks' => $blocks,
            ];
        }

        return ['rows' => $displayRows, 'peak' => $peak, 'peakLabel' => $peakLabel ?? 'No clicks'];
    }

    /** Monday-first, matching EXTRACT(ISODOW) and the shared partial's rows. */
    public const ISO_DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    /**
     * One dimension card — browsers, devices, countries and the rest.
     *
     * `$ignore` drops a filter from its own card, so choosing Chrome does not
     * reduce the browser card to a single row the reader cannot get out of.
     * The stats page does this already; doing it here keeps that behaviour
     * when the same card is drawn for a folder.
     */
    public function dimension(string $column, int $limit = 10, ?string $ignore = null): Collection
    {
        $saved = $this->filters;
        if ($ignore !== null) {
            unset($this->filters[$ignore]);
        }

        $rows = $this->scoped()
            ->selectRaw("$column, COUNT(*) as count")
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->groupBy($column)
            ->orderByDesc('count')
            ->limit($limit)
            ->get();

        $this->filters = $saved;

        return $rows;
    }

    /**
     * Engagement, from page_sessions. A bounce is under five seconds, the
     * same threshold the per-link page has always used.
     *
     * @return array{sessions: int, avgSeconds: int, engagedSeconds: int, bounceRate: float}
     */
    public function sessions(): array
    {
        $query = \App\Modules\User\Models\PageSession::whereIn('link_id', (clone $this->links)->select('links.id'))
            ->whereBetween('started_at', [$this->from->copy()->utc(), $this->to->copy()->utc()]);

        $total = (clone $query)->count();
        $bounced = (clone $query)->where('duration_seconds', '<', 5)->count();

        return [
            'sessions'       => $total,
            'avgSeconds'     => (int) round((clone $query)->avg('duration_seconds') ?? 0),
            'engagedSeconds' => (int) ((clone $query)->sum('duration_seconds') ?? 0),
            'bounceRate'     => $total > 0 ? round($bounced / $total * 100, 1) : 0.0,
        ];
    }

    /** Bot hits the global scope removed from every figure above. */
    public function botTotal(): int
    {
        return LinkClick::withBots()
            ->whereIn('link_id', (clone $this->links)->select('links.id'))
            ->where('is_bot', true)
            ->whereBetween('clicked_at', [$this->from->copy()->utc(), $this->to->copy()->utc()])
            ->count();
    }

    /** The recent-clicks table, paginated, newest first. */
    public function recent(int $perPage = 25)
    {
        return $this->scoped()->orderByDesc('clicked_at')->paginate($perPage)->withQueryString();
    }
}
