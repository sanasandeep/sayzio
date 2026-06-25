@extends('user.layouts.app')

@section('title', 'Stats')

@push('styles')
<style>
    /* Re-use the same themed visual language as the analytics pages so the
       Stats home adapts to light/dark instead of staying white on dark. */
    .stats-card {
        background: var(--bg-card);
        border: 1px solid var(--border-glass);
        border-radius: 14px;
        backdrop-filter: blur(20px);
    }
    .stats-select {
        background: var(--bg-glass-input);
        border: 1px solid var(--border-glass);
        color: var(--text-primary);
        border-radius: 11px;
    }
    .stats-select option { color: #0f172a; }
    .stats-kpi-icon {
        width: 32px; height: 32px; border-radius: 11px;
        display: flex; align-items: center; justify-content: center;
        font-size: 13px;
    }
    .stats-table thead th {
        font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em;
        color: var(--text-faint);
        padding: 8px 0; text-align: left;
    }
    .stats-table tbody td { padding: 11px 0; color: var(--text-muted); border-top: 1px solid var(--border-glass); vertical-align: middle; }
    .stats-table tbody tr:first-child td { border-top: 0; }
</style>
@endpush

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

    <div class="flex flex-wrap items-end justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-extrabold" style="color: var(--text-primary);">Stats home</h1>
            <p class="text-sm" style="color: var(--text-faint);">{{ $start->format('M j, Y') }} – {{ $end->format('M j, Y') }} · {{ $rangeLabel }}</p>
        </div>
        <form method="GET" class="flex gap-2">
            <select name="range" onchange="this.form.submit()" class="stats-select px-3 py-2 text-sm">
                @foreach($ranges as $key => $r)
                    <option value="{{ $key }}" {{ $range === $key ? 'selected' : '' }}>{{ $r['label'] }}</option>
                @endforeach
            </select>
            @if(workspace_owner()?->getPlanFeature('analytics_export', true))
                <a href="{{ route('user.stats.export', ['range' => $range]) }}" class="px-3 py-2 rounded-lg bg-gradient-to-br from-blue-600 to-fuchsia-600 text-white text-sm font-semibold hover:opacity-90 transition">
                    <i class="fas fa-download mr-1"></i> CSV
                </a>
            @else
                <a href="{{ route('user.upgrade') }}" class="px-3 py-2 rounded-lg bg-white/10 text-sm font-semibold hover:bg-white/20 transition" style="color: var(--text-faint);" title="Exporting stats is a paid feature. Upgrade your plan to download CSV exports.">
                    <i class="fas fa-lock mr-1"></i> CSV
                </a>
            @endif
        </form>
    </div>

    {{-- KPI tiles --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        @php
            $tiles = [
                ['label' => 'Followers',         'value' => number_format($kpis['followers_total']),    'delta' => '+' . number_format($kpis['followers_new']),     'icon' => 'fa-user-plus',    'tint' => 'violet'],
                ['label' => 'Posts published',   'value' => number_format($kpis['posts_published']),    'delta' => $rangeLabel,                                     'icon' => 'fa-file-lines',   'tint' => 'sky'],
                ['label' => 'Engagement',        'value' => number_format($kpis['reactions'] + $kpis['comments']), 'delta' => $kpis['reactions'] . ' reactions · ' . $kpis['comments'] . ' comments', 'icon' => 'fa-heart', 'tint' => 'rose'],
                ['label' => 'Subscribers',       'value' => number_format($kpis['subscribers_active']), 'delta' => '+' . $kpis['subscribers_new'] . ' new',         'icon' => 'fa-crown',        'tint' => 'amber'],
            ];
            $tints = [
                'violet' => 'background: rgba(61,107,255,0.14); color: #90acff;',
                'sky'    => 'background: rgba(14,165,233,0.14); color: #38bdf8;',
                'rose'   => 'background: rgba(244,63,94,0.14); color: #fb7185;',
                'amber'  => 'background: rgba(245,158,11,0.16); color: #fbbf24;',
            ];
        @endphp
        @foreach($tiles as $t)
            <div class="stats-card p-4">
                <div class="flex items-center gap-2">
                    <span class="stats-kpi-icon" style="{{ $tints[$t['tint']] }}"><i class="fas {{ $t['icon'] }}"></i></span>
                    <span class="text-[11px] uppercase tracking-wider" style="color: var(--text-faint);">{{ $t['label'] }}</span>
                </div>
                <div class="mt-3 text-2xl font-extrabold" style="color: var(--text-primary);">{{ $t['value'] }}</div>
                <div class="text-xs" style="color: var(--text-faint);">{{ $t['delta'] }}</div>
            </div>
        @endforeach
    </div>

    {{-- Earnings strip --}}
    <div class="bg-gradient-to-br from-blue-600 to-fuchsia-600 text-white rounded-xl p-5 mb-6 flex items-center justify-between">
        <div>
            <div class="text-[11px] uppercase tracking-wider opacity-80">Unlock revenue · {{ $rangeLabel }}</div>
            <div class="text-3xl font-extrabold mt-1">${{ number_format($kpis['unlock_revenue_cents']/100, 2) }}</div>
        </div>
        <a href="{{ route('user.monetization.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white/15 hover:bg-white/25 text-sm font-semibold">
            Open Monetization <i class="fas fa-arrow-right"></i>
        </a>
    </div>

    {{-- Daily series chart (Chart.js via CDN). --}}
    <div class="stats-card p-5 mb-6">
        <h2 class="text-sm font-bold mb-3" style="color: var(--text-primary);">Daily activity</h2>
        <canvas id="statsChart" height="80"></canvas>
    </div>

    {{-- Top posts --}}
    <div class="stats-card p-5">
        <h2 class="text-sm font-bold mb-3" style="color: var(--text-primary);">Top posts in this range</h2>
        @if($topPosts->count() === 0)
            <p class="text-sm" style="color: var(--text-faint);">No posts published in this range yet.</p>
        @else
            <table class="stats-table w-full text-sm">
                <thead><tr>
                    <th>Post</th><th>Reactions</th><th>Comments</th><th>Published</th>
                </tr></thead>
                <tbody>
                @foreach($topPosts as $p)
                    <tr>
                        <td class="pr-4 font-semibold" style="color: var(--text-primary);">{{ \Illuminate\Support\Str::limit($p->title ?: $p->body, 80) }}</td>
                        <td style="color: var(--text-muted);">{{ number_format((int) $p->reactions_count) }}</td>
                        <td style="color: var(--text-muted);">{{ number_format((int) $p->comments_count) }}</td>
                        <td style="color: var(--text-faint);">{{ optional($p->published_at)->format('M j, Y') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    const labels   = @json(array_keys($audienceSeries));
    const audience = @json(array_values($audienceSeries));
    const content  = @json(array_values($contentSeries));

    function statsThemeColors() {
        const light = document.documentElement.classList.contains('light-mode');
        return {
            tick: light ? '#475569' : 'rgba(255,255,255,0.65)',
            grid: light ? 'rgba(0,0,0,0.08)' : 'rgba(255,255,255,0.08)',
            tipBg: light ? 'rgba(255,255,255,0.98)' : 'rgba(20,15,40,0.95)',
        };
    }

    const __c = statsThemeColors();
    Chart.defaults.color = __c.tick;
    Chart.defaults.borderColor = __c.grid;

    new Chart(document.getElementById('statsChart'), {
        type: 'line',
        data: {
            labels,
            datasets: [
                { label: 'New followers',  data: audience, borderColor: '#3d6bff', backgroundColor: 'rgba(61,107,255,.15)', tension: .3, fill: true },
                { label: 'Posts published',data: content,  borderColor: '#0ea5e9', backgroundColor: 'rgba(14,165,233,.15)', tension: .3, fill: true },
            ]
        },
        options: {
            plugins: {
                legend: { position: 'bottom', labels: { color: __c.tick } },
                tooltip: {
                    backgroundColor: __c.tipBg,
                    titleColor: __c.tick, bodyColor: __c.tick,
                    borderColor: 'rgba(61,107,255,0.35)', borderWidth: 1,
                    padding: 10, cornerRadius: 10,
                },
            },
            scales: {
                x: { grid: { color: __c.grid }, ticks: { color: __c.tick }, border: { display: false } },
                y: { beginAtZero: true, grid: { color: __c.grid }, ticks: { color: __c.tick, precision: 0 }, border: { display: false } },
            },
        }
    });

    // Re-colour every Chart.js instance when the app theme is toggled, so axis
    // labels, gridlines, legends and tooltips stay legible without a reload.
    function reThemeCharts() {
        if (!window.Chart || !Chart.instances) return;
        const c = statsThemeColors();
        Chart.defaults.color = c.tick;
        Chart.defaults.borderColor = c.grid;
        Object.values(Chart.instances).forEach((ch) => {
            const o = ch.options || {};
            if (o.scales) Object.values(o.scales).forEach((sc) => {
                sc.ticks = sc.ticks || {}; sc.ticks.color = c.tick;
                sc.grid = sc.grid || {}; sc.grid.color = c.grid;
            });
            o.plugins = o.plugins || {};
            if (o.plugins.legend) { o.plugins.legend.labels = o.plugins.legend.labels || {}; o.plugins.legend.labels.color = c.tick; }
            if (o.plugins.tooltip) { o.plugins.tooltip.backgroundColor = c.tipBg; o.plugins.tooltip.titleColor = c.tick; o.plugins.tooltip.bodyColor = c.tick; }
            ch.update('none');
        });
    }
    new MutationObserver(reThemeCharts)
        .observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
</script>
@endsection
