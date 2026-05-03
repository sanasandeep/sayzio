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

<script>
fetch(@json(route('user.links.conversational.analytics.json', $link)))
    .then(r => r.json())
    .then(data => {
        document.getElementById('m-sessions').textContent = data.total_sessions;
        document.getElementById('m-completed').textContent = data.completed;
        document.getElementById('m-rate').textContent = data.completion_pct + '%';
        const f = document.getElementById('funnel');
        if (!data.funnel.length) { f.innerHTML = '<em class="text-muted small">No traffic yet.</em>'; return; }
        const max = Math.max(...data.funnel.map(s => s.entered)) || 1;
        f.innerHTML = data.funnel.map(s => {
            const pct = Math.round((s.entered / max) * 100);
            const choices = (s.choices || []).map(c =>
                `<span class="badge bg-light text-dark me-1 mb-1">${c.value}: ${c.count}</span>`
            ).join('');
            return `
                <div class="mb-3">
                    <div class="d-flex justify-content-between small">
                        <strong>${s.key}</strong>
                        <span class="text-muted">${s.entered} entered · ${s.answered} answered · ${s.drop_off_pct}% drop-off</span>
                    </div>
                    <div class="text-muted small mb-1">${s.preview}</div>
                    <div class="progress" style="height:18px;">
                        <div class="progress-bar bg-primary" style="width:${pct}%;">${s.entered}</div>
                    </div>
                    ${choices ? `<div class="mt-2">${choices}</div>` : ''}
                </div>
            `;
        }).join('');
    })
    .catch(() => {
        document.getElementById('funnel').innerHTML = '<div class="alert alert-danger">Failed to load analytics.</div>';
    });
</script>
@endsection
