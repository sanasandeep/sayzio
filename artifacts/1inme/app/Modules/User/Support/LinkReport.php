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
        'sources'   => ['column' => 'source',       'label' => 'Sources',           'empty' => 'Direct'],
        'referrers' => ['column' => 'referrer',     'label' => 'Referring sites',   'empty' => 'Direct'],
        'channels'  => ['column' => 'channel',      'label' => 'Channels',          'empty' => 'Unknown'],
        'countries' => ['column' => 'country_code', 'label' => 'Countries',         'empty' => 'Unknown'],
        'cities'    => ['column' => 'city',         'label' => 'Cities',            'empty' => 'Unknown'],
        'devices'   => ['column' => 'device_type',  'label' => 'Devices',           'empty' => 'Unknown'],
        'browsers'  => ['column' => 'browser',      'label' => 'Browsers',          'empty' => 'Unknown'],
        'systems'   => ['column' => 'os',           'label' => 'Operating systems', 'empty' => 'Unknown'],
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
}
