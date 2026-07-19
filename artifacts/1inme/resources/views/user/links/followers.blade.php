@extends('user.layouts.app')
@section('title', 'Followers · ' . ($link->title ?: $link->alias))

@section('content')
@php
    $qs = request()->query();
    $buildUrl = fn($overrides = []) => route('user.links.followers', $link) . '?' . http_build_query(array_merge($qs, $overrides));
@endphp

@push('styles')
<style>
    /* Re-use the same visual language as show.blade.php so the Followers
       tab feels like part of the same dashboard, not a separate page. */
    .period-bar {
        background: var(--bg-glass);
        border: 1px solid var(--border-glass);
        border-radius: 18px;
        padding: 10px 14px;
        backdrop-filter: blur(20px);
    }
    .pill {
        padding: 7px 13px;
        border-radius: 11px;
        font-size: 11px;
        font-weight: 600;
        transition: all .2s ease;
        color: var(--text-muted);
    }
    .pill:hover { background: var(--bg-glass-hover); color: var(--text-primary); transform: translateY(-1px); }
    .pill-active {
        background: linear-gradient(135deg, #3d6bff, #5c83ff);
        color: #fff !important;
        box-shadow: 0 6px 18px rgba(61,107,255,0.4);
    }
    .kpi-hero {
        position: relative;
        background: var(--bg-card);
        border: 1px solid var(--border-glass);
        border-radius: 18px;
        padding: 18px 20px;
        backdrop-filter: blur(20px);
    }
    .kpi-hero-label { font-size: 11px; font-weight: 600; color: var(--text-muted); }
    .kpi-hero-value { font-size: 32px; font-weight: 800; line-height: 1.05; color: var(--text-primary); letter-spacing: -0.025em; }
    .kpi-hero-sub { font-size: 11px; color: var(--text-faint); margin-top: 4px; }
    .section-card {
        position: relative;
        background: var(--bg-glass);
        border: 1px solid var(--border-glass);
        border-radius: 14px;
        padding: 28px 32px;
        overflow: hidden;
    }
    .section-card::before {
        content: ""; position: absolute; top: 0; left: 0; right: 0; height: 2px;
        background: var(--sc-accent, linear-gradient(90deg, #5c83ff, #90acff));
        opacity: 0.7;
    }
    .section-title { display: flex; align-items: center; gap: 12px; font-size: 13px; font-weight: 700; color: var(--text-primary); }
    .section-icon {
        width: 36px; height: 36px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-size: 13px;
        background: var(--sc-accent, linear-gradient(135deg, #5c83ff, #90acff));
        box-shadow: 0 8px 22px var(--sc-glow, rgba(61,107,255,0.35)), inset 0 1px 0 rgba(255,255,255,0.25);
    }
    .fancy-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 12.5px; }
    .fancy-table thead th {
        font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em;
        color: var(--text-faint);
        padding: 10px 12px; text-align: left;
        background: linear-gradient(180deg, var(--bg-glass-light), var(--bg-glass));
        border-bottom: 1px solid var(--border-glass-light);
    }
    .fancy-table thead th.text-right { text-align: right; }
    .fancy-table tbody td { padding: 11px 12px; color: var(--text-muted); border-bottom: 1px solid var(--border-glass); vertical-align: middle; }
    .fancy-table tbody tr { transition: background .15s ease; cursor: pointer; }
    .fancy-table tbody tr:hover { background: var(--bg-glass-hover); }
    .fancy-table tbody tr:last-child td { border-bottom: 0; }
    .rank-badge {
        display: inline-flex; align-items: center; justify-content: center;
        width: 24px; height: 24px; border-radius: 8px;
        font-size: 10px; font-weight: 800;
        background: var(--bg-glass-input); color: var(--text-faint);
        border: 1px solid var(--border-glass);
        margin-right: 8px;
    }
    .rank-1 { background: linear-gradient(135deg,#fbbf24,#f59e0b); color: #fff; border-color: transparent; }
    .rank-2 { background: linear-gradient(135deg,#cbd5e1,#94a3b8); color: #fff; border-color: transparent; }
    .rank-3 { background: linear-gradient(135deg,#f97316,#ea580c); color: #fff; border-color: transparent; }
    .empty-card {
        background: var(--bg-glass);
        border: 1px dashed var(--border-glass);
        border-radius: 18px;
        padding: 48px 32px;
        text-align: center;
        color: var(--text-muted);
    }
</style>
@endpush

@php
    $heroActions = [
        ['label' => 'Back to Overview', 'url' => route('user.links.show', $link), 'icon' => 'fa-arrow-left', 'class' => 'btn-ghost'],
    ];
    if (workspace_owner()?->getPlanFeature('analytics_export', true)) {
        $heroActions[] = ['label' => 'Export CSV', 'url' => route('user.links.followers.export', $link) . (empty($qs) ? '?' . http_build_query(['period' => $period ?? '30d']) : '?' . http_build_query($qs)), 'icon' => 'fa-file-csv', 'class' => 'btn-primary'];
    } else {
        $heroActions[] = ['label' => 'Upgrade to export', 'url' => route('user.upgrade'), 'icon' => 'fa-lock', 'class' => 'btn-ghost', 'title' => 'CSV export is a paid feature, upgrade your plan to download follower stats.'];
    }
@endphp
@include('user.partials.page-hero', [
    'title'    => ($link->title ?: $link->alias) . ' · Followers',
    'icon'     => 'fa-user-group',
    'url'      => $link->getShortUrl(),
    'chips'    => [
        ['icon' => 'fa-users', 'text' => number_format($totalFollowerCount) . ' total followers'],
        ['icon' => 'fa-calendar', 'text' => $startDate->format('M d') . ' – ' . $endDate->format('M d, Y')],
    ],
    'back'     => route('user.links.show', $link),
    'actions'  => $heroActions,
])

@include('user.links.partials.analytics-tabs', ['link' => $link, 'active' => 'followers'])

{{-- ===================== PERIOD CONTROLS ===================== --}}
<div class="period-bar mb-6">
    <div class="flex flex-wrap items-center gap-2">
        <span class="text-[10px] uppercase tracking-wider font-bold mr-1" style="color: var(--text-faint);"><i class="fas fa-clock text-blue-400"></i> Period</span>
        @foreach(['today'=>'Today','7d'=>'7d','30d'=>'30d','90d'=>'90d','year'=>'Year','all'=>'All'] as $k=>$lbl)
            <a href="{{ $buildUrl(['period'=>$k]) }}" class="pill {{ ($period ?? '30d')===$k ? 'pill-active' : '' }}">{{ $lbl }}</a>
        @endforeach
    </div>
</div>

{{-- ===================== KPI ROW ===================== --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="kpi-hero">
        <div class="kpi-hero-label"><i class="fas fa-percentage text-blue-400 mr-1"></i> Visitors who follow you</div>
        <div class="kpi-hero-value mt-2">{{ $followerVisitorPct }}%</div>
        <div class="kpi-hero-sub">{{ number_format($uniqueFollowerVisitors) }} of {{ number_format($uniqueVisitors) }} unique visitors</div>
    </div>
    <div class="kpi-hero">
        <div class="kpi-hero-label"><i class="fas fa-mouse-pointer text-emerald-400 mr-1"></i> Follower clicks</div>
        <div class="kpi-hero-value mt-2">{{ number_format($followerClicks) }}</div>
        <div class="kpi-hero-sub">{{ $totalClicks > 0 ? round(($followerClicks / $totalClicks) * 100, 1) : 0 }}% of all {{ number_format($totalClicks) }} clicks</div>
    </div>
    <div class="kpi-hero">
        <div class="kpi-hero-label"><i class="fas fa-user-group text-fuchsia-400 mr-1"></i> Total followers</div>
        <div class="kpi-hero-value mt-2">{{ number_format($totalFollowerCount) }}</div>
        <div class="kpi-hero-sub">people following this creator profile</div>
    </div>
</div>

{{-- ===================== TREND CHART ===================== --}}
<div class="section-card mb-7" style="--sc-accent: linear-gradient(90deg,#3d6bff,#ec4899); --sc-glow: rgba(61,107,255,0.35);">
    <div class="section-title mb-4">
        <div class="section-icon"><i class="fas fa-chart-line"></i></div>
        Follower vs Non-Follower Clicks
        <span class="text-[11px] font-medium ml-1" style="color:var(--text-faint);">(daily)</span>
    </div>
    @if($dailySeries->isEmpty() || ($followerClicks + $nonFollowerClicks) === 0)
        <div class="text-sm py-10 text-center" style="color: var(--text-faint);">
            No click data in this period yet.
        </div>
    @else
        <div style="height: 320px;"><canvas id="followerTrendChart"></canvas></div>
    @endif
</div>

{{-- ===================== COHORT RETENTION CHART ===================== --}}
@php
    $retFollowers = $retentionSeries['followers'];
    $retNonFollowers = $retentionSeries['nonfollowers'];
    $hasRetentionData = $retFollowers['week1_count'] > 0 || $retNonFollowers['week1_count'] > 0;
    $__retFollowersPct = $retFollowers['pct'] ?? [0, 0, 0, 0];
    $__retNonFollowersPct = $retNonFollowers['pct'] ?? [0, 0, 0, 0];
@endphp
<div class="section-card mb-7" style="--sc-accent: linear-gradient(90deg,#06b6d4,#5c83ff); --sc-glow: rgba(6,182,212,0.35);">
    <div class="section-title mb-4">
        <div class="section-icon" style="background: linear-gradient(135deg,#06b6d4,#5c83ff);"><i class="fas fa-arrows-rotate"></i></div>
        Weekly Retention by Cohort
        <span class="text-[11px] font-medium ml-1" style="color:var(--text-faint);">
            (% of week-1 visitors who came back · {{ $retentionStart->format('M d') }} – {{ $retentionEnd->format('M d, Y') }})
        </span>
    </div>
    @if(!$hasRetentionData)
        <div class="text-sm py-10 text-center" style="color: var(--text-faint);">
            Not enough visit history in the last 28 days to compute retention.
        </div>
    @else
        <div style="height: 320px;"><canvas id="cohortRetentionChart"></canvas></div>
        <div class="flex flex-wrap gap-4 mt-4 text-[11px]" style="color: var(--text-faint);">
            <span><i class="fas fa-circle text-blue-400 mr-1"></i> Followers · {{ number_format($retFollowers['week1_count']) }} week-1 visitors</span>
            <span><i class="fas fa-circle text-emerald-400 mr-1"></i> Non-followers · {{ number_format($retNonFollowers['week1_count']) }} week-1 visitors</span>
        </div>
    @endif
</div>

{{-- ===================== TOP FOLLOWERS TABLE ===================== --}}
<div class="section-card mb-7" style="--sc-accent: linear-gradient(90deg,#5c83ff,#d946ef); --sc-glow: rgba(92,131,255,0.35);">
    <div class="section-title mb-4">
        <div class="section-icon"><i class="fas fa-trophy"></i></div>
        Top 10 Most-Engaged Followers
        <span class="text-[11px] font-medium ml-1" style="color:var(--text-faint);">click a row to see their visit history</span>
    </div>

    @if($topFollowers->isEmpty())
        <div class="empty-card">
            @if($totalFollowerCount === 0)
                <p class="text-base font-semibold mb-1" style="color: var(--text-primary);">No followers yet</p>
                <p class="text-sm">When viewers follow your creator profile, they'll appear here ranked by how much they click on this link.</p>
            @else
                <p class="text-base font-semibold mb-1" style="color: var(--text-primary);">No follower clicks in this period</p>
                <p class="text-sm">Try a wider date range, or share this link with your followers to start collecting data.</p>
            @endif
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="fancy-table">
                <thead>
                    <tr>
                        <th>Follower</th>
                        <th>Email</th>
                        <th class="text-right">Clicks</th>
                        <th class="text-right">Block clicks</th>
                        <th>First seen</th>
                        <th>Last seen</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($topFollowers as $i => $f)
                        @php
                            $rankCls = $i === 0 ? 'rank-1' : ($i === 1 ? 'rank-2' : ($i === 2 ? 'rank-3' : ''));
                            $href = route('user.links.followers.history', ['link' => $link, 'follower' => $f->id])
                                . '?' . http_build_query(['period' => $period]);
                        @endphp
                        <tr onclick="window.location='{{ $href }}'">
                            <td>
                                <div class="flex items-center gap-2">
                                    <span class="rank-badge {{ $rankCls }}">{{ $i + 1 }}</span>
                                    @if($f->avatar)
                                        <img src="{{ \App\Support\PublicStorageUrl::resolve($f->avatar) }}" class="w-7 h-7 rounded-full object-cover" alt=""/>
                                    @else
                                        <div class="w-7 h-7 rounded-full bg-gradient-to-br from-blue-500 to-fuchsia-500 text-white flex items-center justify-center text-xs font-bold">
                                            {{ strtoupper(substr($f->name ?? '?', 0, 1)) }}
                                        </div>
                                    @endif
                                    <span style="color: var(--text-primary); font-weight: 600;">{{ $f->name ?: 'Anonymous' }}</span>
                                </div>
                            </td>
                            <td style="color: var(--text-muted);">{{ $f->email }}</td>
                            <td class="text-right" style="color: var(--text-primary); font-weight: 700;">{{ number_format($f->click_count) }}</td>
                            <td class="text-right">{{ number_format($f->block_click_count) }}</td>
                            <td class="text-xs" style="color: var(--text-faint);">{{ \Carbon\Carbon::parse($f->first_seen)->diffForHumans() }}</td>
                            <td class="text-xs" style="color: var(--text-faint);">{{ \Carbon\Carbon::parse($f->last_seen)->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/vendor/chart.umd.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.Chart) return;
    const el = document.getElementById('followerTrendChart');

    const isLight = document.documentElement.classList.contains('light-mode');
    const tickColor = isLight ? '#475569' : 'rgba(255,255,255,0.65)';
    const gridColor = isLight ? 'rgba(0,0,0,0.08)' : 'rgba(255,255,255,0.08)';
    Chart.defaults.color = tickColor;
    Chart.defaults.borderColor = gridColor;
    Chart.defaults.font.family = "'Inter', system-ui, -apple-system, sans-serif";

    if (el) {
    const ctx = el.getContext('2d');
    const g1 = ctx.createLinearGradient(0, 0, 0, 320);
    g1.addColorStop(0, 'rgba(92,131,255,0.45)');
    g1.addColorStop(1, 'rgba(92,131,255,0.0)');
    const g2 = ctx.createLinearGradient(0, 0, 0, 320);
    g2.addColorStop(0, 'rgba(52,211,153,0.40)');
    g2.addColorStop(1, 'rgba(52,211,153,0.0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: @json($dailySeries->pluck('d')),
            datasets: [
                {
                    label: 'Follower clicks',
                    data: @json($dailySeries->pluck('followers')),
                    borderColor: '#5c83ff',
                    backgroundColor: g1,
                    tension: 0.4, fill: true, borderWidth: 2.5,
                    pointRadius: 0, pointHoverRadius: 6,
                    pointHoverBackgroundColor: '#5c83ff',
                    pointHoverBorderColor: '#fff', pointHoverBorderWidth: 2,
                },
                {
                    label: 'Non-follower clicks',
                    data: @json($dailySeries->pluck('nonfollowers')),
                    borderColor: '#34d399',
                    backgroundColor: g2,
                    tension: 0.4, fill: true, borderWidth: 2.5,
                    pointRadius: 0, pointHoverRadius: 6,
                    pointHoverBackgroundColor: '#34d399',
                    pointHoverBorderColor: '#fff', pointHoverBorderWidth: 2,
                },
            ],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, padding: 16 } } },
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, ticks: { precision: 0 } },
            },
        },
    });
    }

    // ---- Cohort retention chart ----
    const retEl = document.getElementById('cohortRetentionChart');
    if (retEl) {
        const rctx = retEl.getContext('2d');
        const rg1 = rctx.createLinearGradient(0, 0, 0, 320);
        rg1.addColorStop(0, 'rgba(92,131,255,0.35)');
        rg1.addColorStop(1, 'rgba(92,131,255,0.0)');
        const rg2 = rctx.createLinearGradient(0, 0, 0, 320);
        rg2.addColorStop(0, 'rgba(52,211,153,0.30)');
        rg2.addColorStop(1, 'rgba(52,211,153,0.0)');

        new Chart(rctx, {
            type: 'line',
            data: {
                labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                datasets: [
                    {
                        label: 'Followers',
                        data: @json($__retFollowersPct),
                        borderColor: '#5c83ff',
                        backgroundColor: rg1,
                        tension: 0.4, fill: true, borderWidth: 2.5,
                        pointRadius: 4, pointHoverRadius: 7,
                        pointBackgroundColor: '#5c83ff',
                        pointBorderColor: '#fff', pointBorderWidth: 2,
                    },
                    {
                        label: 'Non-followers',
                        data: @json($__retNonFollowersPct),
                        borderColor: '#34d399',
                        backgroundColor: rg2,
                        tension: 0.4, fill: true, borderWidth: 2.5,
                        pointRadius: 4, pointHoverRadius: 7,
                        pointBackgroundColor: '#34d399',
                        pointBorderColor: '#fff', pointBorderWidth: 2,
                    },
                ],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, padding: 16 } },
                    tooltip: { callbacks: { label: (c) => `${c.dataset.label}: ${c.parsed.y}%` } },
                },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, max: 100, ticks: { callback: (v) => v + '%' } },
                },
            },
        });
    }

    // Re-colour every Chart.js instance when the app theme is toggled, so axis
    // labels, gridlines, legends and tooltips stay legible without a reload.
    function reThemeCharts() {
        if (!window.Chart || !Chart.instances) return;
        const light = document.documentElement.classList.contains('light-mode');
        const tick = light ? '#475569' : 'rgba(255,255,255,0.65)';
        const grid = light ? 'rgba(0,0,0,0.08)' : 'rgba(255,255,255,0.08)';
        const tipBg = light ? 'rgba(255,255,255,0.98)' : 'rgba(20,15,40,0.95)';
        Chart.defaults.color = tick;
        Chart.defaults.borderColor = grid;
        Object.values(Chart.instances).forEach((ch) => {
            const o = ch.options || {};
            if (o.scales) Object.values(o.scales).forEach((sc) => {
                sc.ticks = sc.ticks || {}; sc.ticks.color = tick;
                sc.grid = sc.grid || {}; sc.grid.color = grid;
            });
            o.plugins = o.plugins || {};
            if (o.plugins.legend) { o.plugins.legend.labels = o.plugins.legend.labels || {}; o.plugins.legend.labels.color = tick; }
            if (o.plugins.tooltip) { o.plugins.tooltip.backgroundColor = tipBg; o.plugins.tooltip.titleColor = tick; o.plugins.tooltip.bodyColor = tick; }
            ch.update('none');
        });
    }
    new MutationObserver(reThemeCharts)
        .observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
});
</script>
@endpush
