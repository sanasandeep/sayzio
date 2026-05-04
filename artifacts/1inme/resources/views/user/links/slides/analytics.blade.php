@extends('user.layouts.app')
@section('title', 'Slides Analytics - ' . ($link->title ?: $link->alias))
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-1">Slides analytics</h2>
            <div class="text-muted small">{{ $link->title ?: $link->alias }} · /{{ $link->alias }}</div>
        </div>
        <a href="{{ route('user.links.slides.editor', $link) }}" class="btn btn-outline-secondary btn-sm">Back to deck</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card p-3"><div class="text-muted small">Total impressions</div><div class="h3 mb-0" id="m-impressions">—</div></div></div>
        <div class="col-md-3"><div class="card p-3"><div class="text-muted small">Unique sessions</div><div class="h3 mb-0" id="m-sessions">—</div></div></div>
        <div class="col-md-3"><div class="card p-3"><div class="text-muted small">Completed decks</div><div class="h3 mb-0" id="m-completed">—</div></div></div>
        <div class="col-md-3"><div class="card p-3"><div class="text-muted small">Completion rate</div><div class="h3 mb-0" id="m-rate">—</div></div></div>
    </div>

    <div class="card p-3 mb-4">
        <h5>Views over the last 30 days</h5>
        <svg id="sl-trend" viewBox="0 0 600 160" preserveAspectRatio="none" style="width:100%;height:160px;display:block;margin-top:8px;">
            <text x="300" y="80" text-anchor="middle" fill="#888" font-size="12">Loading…</text>
        </svg>
        <div id="sl-trend-legend" class="d-flex justify-content-between text-muted small mt-1"></div>
    </div>

    <div class="card p-3">
        <h5>Per-slide views &amp; drop-off</h5>
        <div id="sl-funnel" class="mt-3"><em class="text-muted small">Loading…</em></div>
    </div>
</div>

<script>
fetch(@json(route('user.links.slides.analytics.json', $link)))
    .then(r => r.json())
    .then(data => {
        document.getElementById('m-impressions').textContent = data.total_impressions;
        document.getElementById('m-sessions').textContent    = data.unique_sessions;
        document.getElementById('m-completed').textContent   = data.completed;
        document.getElementById('m-rate').textContent        = data.completion_pct + '%';

        // ── Per-slide funnel ────────────────────────────────────────
        const f = document.getElementById('sl-funnel');
        if (!data.slides.length) {
            f.innerHTML = '<em class="text-muted small">No slides in this deck yet.</em>';
        } else if (data.total_impressions === 0) {
            f.innerHTML = '<em class="text-muted small">No views recorded yet.</em>';
        } else {
            const max = Math.max(...data.slides.map(s => s.views)) || 1;
            f.innerHTML = data.slides.map(s => {
                const pct = Math.round((s.views / max) * 100);
                return `
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small">
                            <strong>#${s.index + 1} · ${s.title.replace(/</g,'&lt;')}</strong>
                            <span class="text-muted">${s.views} views · ${s.unique} unique · ${s.drop_off_pct}% drop-off</span>
                        </div>
                        <div class="progress" style="height:18px;">
                            <div class="progress-bar bg-primary" style="width:${pct}%;">${s.views}</div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // ── 30-day trend sparkline ─────────────────────────────────
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
        const area = path + ` L${(pad + (series.length - 1) * stepX).toFixed(1)},${h - pad} L${pad},${h - pad} Z`;
        const dots = points.map(pt =>
            `<circle cx="${pt[0].toFixed(1)}" cy="${pt[1].toFixed(1)}" r="2.5" fill="#8b5cf6"><title>${pt[2].date}: ${pt[2].views} views</title></circle>`
        ).join('');
        svg.innerHTML = `
            <path d="${area}" fill="rgba(139,92,246,.18)" />
            <path d="${path}" fill="none" stroke="#8b5cf6" stroke-width="2" />
            ${dots}
        `;

        const legend = document.getElementById('sl-trend-legend');
        if (series.length) {
            legend.innerHTML = `<span>${series[0].date}</span><span>${series[series.length - 1].date}</span>`;
        }
    })
    .catch(() => {
        document.getElementById('sl-funnel').innerHTML = '<div class="alert alert-danger">Failed to load analytics.</div>';
        document.getElementById('sl-trend').innerHTML = '';
    });
</script>
@endsection
