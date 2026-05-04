@extends('user.layouts.app')
@section('title', 'Conversation Funnel - ' . ($link->title ?: $link->alias))
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-1">Conversation funnel</h2>
            <div class="text-muted small">{{ $link->title ?: $link->alias }} · /{{ $link->alias }}</div>
        </div>
        <a href="{{ route('user.links.conversational.editor', $link) }}" class="btn btn-outline-secondary btn-sm">Back to flow</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card p-3"><div class="text-muted small">Sessions started</div><div class="h3 mb-0" id="m-sessions">—</div></div></div>
        <div class="col-md-3"><div class="card p-3"><div class="text-muted small">Completed</div><div class="h3 mb-0" id="m-completed">—</div></div></div>
        <div class="col-md-3"><div class="card p-3"><div class="text-muted small">Completion rate</div><div class="h3 mb-0" id="m-rate">—</div></div></div>
        <div class="col-md-3"><div class="card p-3"><div class="text-muted small">Flow version</div><div class="h3 mb-0" id="m-version">v{{ $flow->version }}</div></div></div>
    </div>

    <div class="card p-3">
        <h5>Step-by-step drop-off</h5>
        <div id="funnel" class="mt-3"><em class="text-muted small">Loading…</em></div>
    </div>
</div>

<style>
    .cv-bar { height: 6px; background: rgba(0,0,0,0.06); border-radius: 3px; margin: 6px 0; overflow: hidden; }
    .cv-bar-fill { height: 100%; background: #6366f1; }
    .cv-pill { display: inline-block; padding: 2px 8px; border-radius: 999px; background: #eef2ff; color: #4338ca; font-size: 11px; font-weight: 600; margin-right: 6px; }
    .cv-warn-pill { background: #fef2f2; color: #b91c1c; }
    .cv-rating-row { display: flex; align-items: center; gap: 8px; font-size: 12px; margin: 2px 0; }
    .cv-rating-row .cv-bar { flex: 1; }
</style>
<script>
fetch(@json(route('user.links.conversational.analytics.json', $link)))
    .then(r => r.json())
    .then(data => {
        document.getElementById('m-sessions').textContent  = data.total_sessions;
        document.getElementById('m-completed').textContent = data.completed;
        document.getElementById('m-rate').textContent      = data.completion_pct + '%';
        const f = document.getElementById('funnel');
        if (!data.funnel.length) { f.innerHTML = '<em class="text-muted small">No traffic yet.</em>'; return; }
        const max = Math.max(...data.funnel.map(s => s.entered)) || 1;
        f.innerHTML = data.funnel.map(s => {
            const pct = Math.round((s.entered / max) * 100);
            const choices = (s.choices || []).map(c =>
                `<span class="badge bg-light text-dark me-1 mb-1">${escapeHtml(c.value)}: ${c.count}</span>`
            ).join('');

            // Validation failure pill (only for input/datetime/file/rating with non-zero failures).
            const vf = s.validation_failures
                ? `<span class="cv-pill cv-warn-pill">${s.validation_failures} validation fails</span>`
                : '';

            // Rating distribution + average.
            let rating = '';
            if (s.rating) {
                const totalR = s.rating.count;
                rating = `<div class="mt-2 small">
                    <strong>Avg ${s.rating.avg ?? '—'}</strong> over ${totalR} ratings (${s.rating.scale})
                    ${Object.entries(s.rating.hist || {}).sort((a,b)=>parseFloat(a[0])-parseFloat(b[0])).map(([v,c]) => {
                        const w = totalR ? Math.round((c/totalR)*100) : 0;
                        return `<div class="cv-rating-row"><span style="width:24px;">${escapeHtml(v)}</span><div class="cv-bar"><div class="cv-bar-fill" style="width:${w}%"></div></div><span>${c}</span></div>`;
                    }).join('')}
                </div>`;
            }

            // AI intent distribution + fallback rate.
            let ai = '';
            if (s.ai) {
                const total = s.ai.total;
                const intents = s.ai.intents.map(i => {
                    const w = total ? Math.round((i.count/total)*100) : 0;
                    const isFb = i.value === '__fallback__';
                    return `<div class="cv-rating-row">
                        <span style="width:120px;">${isFb ? '⚠ fallback' : escapeHtml(i.value)}</span>
                        <div class="cv-bar"><div class="cv-bar-fill" style="width:${w}%; background:${isFb?'#ef4444':'#6366f1'}"></div></div>
                        <span>${i.count}</span>
                    </div>`;
                }).join('');
                ai = `<div class="mt-2 small">
                    <strong>AI routing</strong> · ${total} classified · fallback rate ${s.ai.fallback_pct}%
                    ${intents}
                </div>`;
            }

            return `
                <div class="mb-3">
                    <div class="d-flex justify-content-between small">
                        <strong>${escapeHtml(s.key)} <span class="cv-pill">${escapeHtml(s.kind)}</span></strong>
                        <span class="text-muted">${s.entered} entered · ${s.answered} answered · ${s.drop_off_pct}% drop-off ${vf}</span>
                    </div>
                    <div class="text-muted small mb-1">${escapeHtml(s.preview)}</div>
                    <div class="progress" style="height:18px;">
                        <div class="progress-bar bg-primary" style="width:${pct}%;">${s.entered}</div>
                    </div>
                    ${choices ? `<div class="mt-2">${choices}</div>` : ''}
                    ${rating}
                    ${ai}
                </div>
            `;
        }).join('');
    })
    .catch(() => {
        document.getElementById('funnel').innerHTML = '<div class="alert alert-danger">Failed to load analytics.</div>';
    });

function escapeHtml(s) { return String(s ?? '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }
</script>
@endsection
