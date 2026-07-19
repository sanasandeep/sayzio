@extends('user.layouts.app')
@section('title', 'Slides Analytics - ' . ($link->title ?: $link->alias))
@section('content')

@push('styles')
<style>
    /* Themed horizontal bar for the per-slide funnel — mirrors the app's
       glass surfaces + accent gradient instead of Bootstrap's .progress. */
    .sl-bar-track {
        height: 22px;
        border-radius: 9px;
        background: var(--bg-glass-input);
        border: 1px solid var(--border-glass);
        overflow: hidden;
    }
    .sl-bar-fill {
        height: 100%;
        display: flex;
        align-items: center;
        padding: 0 9px;
        font-size: 11px;
        font-weight: 700;
        color: #fff;
        background: linear-gradient(90deg, #3d6bff, #5c83ff);
        border-radius: 9px;
        box-shadow: 0 4px 14px rgba(61,107,255,0.32);
        min-width: 1.75rem;
        white-space: nowrap;
    }
    .sl-row + .sl-row { margin-top: 1.1rem; }
    .sl-error {
        padding: 12px 14px;
        border-radius: 12px;
        font-size: 13px;
        font-weight: 600;
        color: var(--c-danger);
        background: var(--c-danger-soft);
        border: 1px solid var(--c-danger);
    }
</style>
@endpush

<div class="container-fluid py-3">
    <div class="flex justify-between items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="mb-1 text-2xl font-bold" style="color: var(--text-primary);">Slides analytics</h2>
            <div class="text-sm" style="color: var(--text-muted);">{{ $link->title ?: $link->alias }} · /{{ $link->alias }}</div>
        </div>
        <a href="{{ route('user.links.slides.editor', $link) }}" class="btn-ghost text-xs">
            <i class="fas fa-arrow-left text-[10px]"></i> Back to deck
        </a>
    </div>

    {{-- ===================== PERIOD CONTROLS =====================
        Filter pills + custom from/to. Mirrors the period-bar styling
        used on the link overview page. State lives in the query string
        so the window survives reloads / back-button navigation. --}}
    @php
        $period = request('period', '30d');
        $fromQ  = request('from', '');
        $toQ    = request('to', '');
        $csvQuery = http_build_query(array_filter([
            'period' => $period,
            'from'   => $fromQ ?: null,
            'to'     => $toQ ?: null,
        ], fn ($v) => $v !== null && $v !== ''));
    @endphp
    <div class="period-bar mb-4">
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-[10px] uppercase tracking-wider font-bold mr-1" style="color: var(--text-faint);">
                <i class="fas fa-clock text-blue-400"></i> Period
            </span>
            @foreach(['today'=>'Today','7d'=>'7d','30d'=>'30d','90d'=>'90d','year'=>'Year','all'=>'All'] as $k => $lbl)
                <a href="?period={{ $k }}"
                   data-period-pill="{{ $k }}"
                   class="pill {{ $period === $k ? 'pill-active' : '' }}">{{ $lbl }}</a>
            @endforeach
            <span class="mx-3 h-5 w-px hidden md:inline-block" style="background: var(--border-glass);"></span>
            <form method="GET" class="flex items-center gap-2">
                <input type="hidden" name="period" value="custom">
                <input type="date" name="from" value="{{ $fromQ }}" class="theme-input text-xs py-1.5 px-2">
                <span class="text-xs" style="color:var(--text-faint);">to</span>
                <input type="date" name="to" value="{{ $toQ }}" class="theme-input text-xs py-1.5 px-2">
                <button class="pill {{ $period === 'custom' ? 'pill-active' : '' }}"><i class="fas fa-check text-[9px]"></i> Apply</button>
            </form>
            @if(workspace_owner()?->getPlanFeature('analytics_export', true))
            <a id="sl-csv-btn"
               href="{{ route('user.links.slides.analytics.csv', $link) }}{{ $csvQuery ? '?' . $csvQuery : '' }}"
               class="pill ml-auto">
                <i class="fas fa-download text-[9px]"></i> Download CSV
            </a>
            @else
            <a href="{{ route('user.upgrade') }}"
               class="pill ml-auto"
               title="CSV export is a paid feature, upgrade your plan to download stats.">
                <i class="fas fa-lock text-[9px]"></i> Upgrade to export
            </a>
            @endif
        </div>
    </div>

    {{-- ===================== HEADLINE METRICS ===================== --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        <div class="stat-card group shimmer" style="--stat-accent: linear-gradient(90deg, #5c83ff, #90acff); --stat-glow: rgba(61,107,255,0.12); --stat-border-color: rgba(61,107,255,0.2);">
            <div class="flex items-center justify-between relative z-10">
                <div>
                    <p class="text-[10px] uppercase tracking-wider font-bold mb-1.5" style="color: var(--text-faint);">
                        Impressions <span id="m-range-label" style="color: var(--text-faint);"></span>
                    </p>
                    <p class="text-xl font-bold" id="m-impressions" style="color: var(--text-primary);">-</p>
                </div>
                <div class="w-10 h-10 rounded-xl flex items-center justify-center group-hover:scale-110 transition-all duration-500" style="background: rgba(61,107,255,0.1); border: 1px solid rgba(61,107,255,0.15);">
                    <i class="fas fa-eye text-blue-400 text-sm"></i>
                </div>
            </div>
        </div>

        <div class="stat-card group shimmer" style="--stat-accent: linear-gradient(90deg, #3b82f6, #818cf8); --stat-glow: rgba(59,130,246,0.12); --stat-border-color: rgba(59,130,246,0.2);">
            <div class="flex items-center justify-between relative z-10">
                <div>
                    <p class="text-[10px] uppercase tracking-wider font-bold mb-1.5" style="color: var(--text-faint);">Unique sessions</p>
                    <p class="text-xl font-bold" id="m-sessions" style="color: var(--text-primary);">-</p>
                </div>
                <div class="w-10 h-10 rounded-xl flex items-center justify-center group-hover:scale-110 transition-all duration-500" style="background: rgba(59,130,246,0.1); border: 1px solid rgba(59,130,246,0.15);">
                    <i class="fas fa-users text-blue-400 text-sm"></i>
                </div>
            </div>
        </div>

        <div class="stat-card group shimmer" style="--stat-accent: linear-gradient(90deg, #10b981, #34d399); --stat-glow: rgba(16,185,129,0.12); --stat-border-color: rgba(16,185,129,0.2);">
            <div class="flex items-center justify-between relative z-10">
                <div>
                    <p class="text-[10px] uppercase tracking-wider font-bold mb-1.5" style="color: var(--text-faint);">Completed decks</p>
                    <p class="text-xl font-bold" id="m-completed" style="color: var(--text-primary);">-</p>
                </div>
                <div class="w-10 h-10 rounded-xl flex items-center justify-center group-hover:scale-110 transition-all duration-500" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.15);">
                    <i class="fas fa-flag-checkered text-emerald-400 text-sm"></i>
                </div>
            </div>
        </div>

        <div class="stat-card group shimmer" style="--stat-accent: linear-gradient(90deg, #f59e0b, #fbbf24); --stat-glow: rgba(245,158,11,0.12); --stat-border-color: rgba(245,158,11,0.2);">
            <div class="flex items-center justify-between relative z-10">
                <div>
                    <p class="text-[10px] uppercase tracking-wider font-bold mb-1.5" style="color: var(--text-faint);">Completion rate</p>
                    <p class="text-xl font-bold" id="m-rate" style="color: var(--text-primary);">-</p>
                </div>
                <div class="w-10 h-10 rounded-xl flex items-center justify-center group-hover:scale-110 transition-all duration-500" style="background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.15);">
                    <i class="fas fa-percent text-amber-400 text-sm"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== TREND ===================== --}}
    <div class="card-premium p-5 mb-4">
        <h5 id="sl-trend-title" class="text-base font-bold mb-1" style="color: var(--text-primary);">Views over the selected window</h5>
        <svg id="sl-trend" viewBox="0 0 600 160" preserveAspectRatio="none" style="width:100%;height:160px;display:block;margin-top:8px;">
            <text x="300" y="80" text-anchor="middle" font-size="12" style="fill: var(--text-faint);">Loading…</text>
        </svg>
        <div id="sl-trend-legend" class="flex justify-between text-xs mt-1" style="color: var(--text-faint);"></div>
    </div>

    {{-- ===================== PER-SLIDE FUNNEL ===================== --}}
    <div class="card-premium p-5">
        <h5 class="text-base font-bold mb-1" style="color: var(--text-primary);">Per-slide views, drop-off &amp; avg time</h5>
        <div class="text-xs" style="color: var(--text-muted);">Avg time is the average seconds a viewer spends on a slide before moving on.</div>
        <div id="sl-funnel" class="mt-3"><em class="text-xs" style="color: var(--text-faint);">Loading…</em></div>
    </div>
</div>

<script>
function formatDwell(ms) {
    if (ms == null || !isFinite(ms)) return '-';
    if (ms < 1000) return ms + 'ms';
    const s = ms / 1000;
    if (s < 60) return (s < 10 ? s.toFixed(1) : Math.round(s)) + 's';
    const m = Math.floor(s / 60);
    const r = Math.round(s - m * 60);
    return m + 'm ' + r + 's';
}

(function () {
    // Forward the page's current ?period=… (and from/to for custom) to the
    // JSON endpoint so the headline cards, funnel, and trend all reflect the
    // same window the user picked in the pills above.
    const baseUrl = @json(route('user.links.slides.analytics.json', $link));
    const pageParams = new URLSearchParams(window.location.search);
    const fwd = new URLSearchParams();
    ['period', 'from', 'to'].forEach(k => {
        const v = pageParams.get(k);
        if (v) fwd.set(k, v);
    });
    const url = baseUrl + (fwd.toString() ? (baseUrl.includes('?') ? '&' : '?') + fwd.toString() : '');

    const PERIOD_LABELS = {
        today: 'today', '7d': 'last 7 days', '30d': 'last 30 days',
        '90d': 'last 90 days', year: 'this year', all: 'all time', custom: 'custom range'
    };

    fetch(url)
        .then(r => r.json())
        .then(data => {
            const range = data.range || {};
            const label = PERIOD_LABELS[range.period] || PERIOD_LABELS['30d'];
            const rangeLbl = document.getElementById('m-range-label');
            if (rangeLbl) rangeLbl.textContent = '· ' + label;
            const trendTitle = document.getElementById('sl-trend-title');
            if (trendTitle) trendTitle.textContent = 'Views, ' + label;

            document.getElementById('m-impressions').textContent = data.total_impressions;
            document.getElementById('m-sessions').textContent    = data.unique_sessions;
            document.getElementById('m-completed').textContent   = data.completed;
            document.getElementById('m-rate').textContent        = data.completion_pct + '%';

            // ── Per-slide funnel ────────────────────────────────────────
            const f = document.getElementById('sl-funnel');
            if (!data.slides.length) {
                f.innerHTML = '<em class="text-xs" style="color: var(--text-faint);">No slides in this deck yet.</em>';
            } else if (data.total_impressions === 0) {
                f.innerHTML = '<em class="text-xs" style="color: var(--text-faint);">No views recorded in this window.</em>';
            } else {
                const max = Math.max(...data.slides.map(s => s.views)) || 1;
                f.innerHTML = data.slides.map(s => {
                    const pct = Math.round((s.views / max) * 100);
                    const dwell = formatDwell(s.avg_dwell_ms);
                    const dwellTitle = s.dwell_samples > 0
                        ? `Average across ${s.dwell_samples} viewer${s.dwell_samples === 1 ? '' : 's'} who moved on`
                        : 'No dwell samples yet';
                    return `
                        <div class="sl-row">
                            <div class="flex justify-between items-baseline text-xs gap-2 mb-1.5">
                                <strong style="color: var(--text-primary);">#${s.index + 1} · ${s.title.replace(/</g,'&lt;')}</strong>
                                <span style="color: var(--text-muted);">
                                    ${s.views} views · ${s.unique} unique · ${s.drop_off_pct}% drop-off ·
                                    <span title="${dwellTitle}">avg ${dwell}</span>
                                </span>
                            </div>
                            <div class="sl-bar-track">
                                <div class="sl-bar-fill" style="width:${pct}%;">${s.views}</div>
                            </div>
                        </div>
                    `;
                }).join('');
            }

            // ── Trend sparkline (scoped to the selected window) ────────
            // Colors are driven by the theme's --accent / --text-faint vars so
            // the chart stays legible in both light and dark mode.
            const svg = document.getElementById('sl-trend');
            const series = data.series || [];
            const w = 600, h = 160, pad = 8;
            const maxV = Math.max(1, ...series.map(p => p.views));
            const stepX = series.length > 1 ? (w - pad * 2) / (series.length - 1) : 0;
            const points = series.map((p, i) => {
                const x = pad + i * stepX;
                const y = h - pad - ((p.views / maxV) * (h - pad * 2));
                return [x, y, p];
            });
            const path = points.map((pt, i) => (i === 0 ? 'M' : 'L') + pt[0].toFixed(1) + ',' + pt[1].toFixed(1)).join(' ');
            // Only draw dots when the series is short enough to make them
            // legible; on big windows they collapse into a smear.
            const showDots = points.length <= 60;
            const area = points.length
                ? path + ` L${(pad + (series.length - 1) * stepX).toFixed(1)},${h - pad} L${pad},${h - pad} Z`
                : '';
            const dots = showDots
                ? points.map(pt =>
                    `<circle cx="${pt[0].toFixed(1)}" cy="${pt[1].toFixed(1)}" r="2.5" style="fill: var(--accent);"><title>${pt[2].date}: ${pt[2].views} views</title></circle>`
                ).join('')
                : '';
            svg.innerHTML = points.length
                ? `<path d="${area}" style="fill: var(--accent); fill-opacity: 0.15;" /><path d="${path}" fill="none" style="stroke: var(--accent);" stroke-width="2" />${dots}`
                : '<text x="300" y="80" text-anchor="middle" font-size="12" style="fill: var(--text-faint);">No views in this window.</text>';

            const legend = document.getElementById('sl-trend-legend');
            if (series.length) {
                legend.innerHTML = `<span>${series[0].date}</span><span>${series[series.length - 1].date}</span>`;
            } else {
                legend.innerHTML = '';
            }
        })
        .catch(() => {
            document.getElementById('sl-funnel').innerHTML = '<div class="sl-error">Failed to load analytics.</div>';
            document.getElementById('sl-trend').innerHTML = '';
        });
})();
</script>
@endsection
