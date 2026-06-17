@extends('user.layouts.app')
@section('title', 'Conversation Funnel - ' . ($link->title ?: $link->alias))
@section('content')

@push('styles')
<style>
    /* Themed funnel + pills for the conversation analytics page — mirrors the
       app's glass surfaces + accent gradient instead of Bootstrap defaults
       (which aren't loaded here). Everything reads theme CSS variables so it
       stays legible in both light and dark mode. */
    .cv-row + .cv-row { margin-top: 1.25rem; }

    /* Main per-step bar (entered count). */
    .cv-bar-track {
        height: 22px;
        border-radius: 9px;
        background: var(--bg-glass-input);
        border: 1px solid var(--border-glass);
        overflow: hidden;
    }
    .cv-bar-fill {
        height: 100%;
        display: flex;
        align-items: center;
        padding: 0 9px;
        font-size: 11px;
        font-weight: 700;
        color: #fff;
        background: linear-gradient(90deg, #7c3aed, #8b5cf6);
        border-radius: 9px;
        box-shadow: 0 4px 14px rgba(124,58,237,0.32);
        min-width: 1.75rem;
        white-space: nowrap;
    }

    /* Small horizontal bars used by rating histograms + AI routing. */
    .cv-mini-bar {
        flex: 1;
        height: 6px;
        border-radius: 3px;
        background: var(--bg-glass-input);
        border: 1px solid var(--border-glass);
        overflow: hidden;
    }
    .cv-mini-bar-fill {
        height: 100%;
        background: linear-gradient(90deg, #7c3aed, #8b5cf6);
    }
    .cv-mini-bar-fill.is-fallback {
        background: var(--c-danger);
    }
    .cv-rating-row {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 12px;
        margin: 4px 0;
        color: var(--text-muted);
    }

    /* Pills. */
    .cv-pill {
        display: inline-block;
        padding: 2px 9px;
        border-radius: 999px;
        background: var(--accent-soft, rgba(124,58,237,0.14));
        color: var(--accent);
        font-size: 11px;
        font-weight: 700;
        margin-right: 6px;
    }
    .cv-warn-pill {
        background: var(--c-danger-soft);
        color: var(--c-danger);
    }
    .cv-choice {
        display: inline-block;
        padding: 2px 9px;
        border-radius: 999px;
        background: var(--bg-glass-input);
        border: 1px solid var(--border-glass);
        color: var(--text-secondary, var(--text-muted));
        font-size: 11px;
        font-weight: 600;
        margin: 0 6px 6px 0;
    }
    .cv-error {
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
            <h2 class="mb-1 text-2xl font-bold" style="color: var(--text-primary);">Conversation funnel</h2>
            <div class="text-sm" style="color: var(--text-muted);">{{ $link->title ?: $link->alias }} · /{{ $link->alias }}</div>
        </div>
        <a href="{{ route('user.links.conversational.editor', $link) }}" class="btn-ghost text-xs">
            <i class="fas fa-arrow-left text-[10px]"></i> Back to flow
        </a>
    </div>

    {{-- ===================== HEADLINE METRICS ===================== --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        <div class="stat-card group shimmer" style="--stat-accent: linear-gradient(90deg, #8b5cf6, #a78bfa); --stat-glow: rgba(124,58,237,0.12); --stat-border-color: rgba(124,58,237,0.2);">
            <div class="flex items-center justify-between relative z-10">
                <div>
                    <p class="text-[10px] uppercase tracking-wider font-bold mb-1.5" style="color: var(--text-faint);">Sessions started</p>
                    <p class="text-xl font-bold" id="m-sessions" style="color: var(--text-primary);">—</p>
                </div>
                <div class="w-10 h-10 rounded-xl flex items-center justify-center group-hover:scale-110 transition-all duration-500" style="background: rgba(124,58,237,0.1); border: 1px solid rgba(124,58,237,0.15);">
                    <i class="fas fa-play text-violet-400 text-sm"></i>
                </div>
            </div>
        </div>

        <div class="stat-card group shimmer" style="--stat-accent: linear-gradient(90deg, #10b981, #34d399); --stat-glow: rgba(16,185,129,0.12); --stat-border-color: rgba(16,185,129,0.2);">
            <div class="flex items-center justify-between relative z-10">
                <div>
                    <p class="text-[10px] uppercase tracking-wider font-bold mb-1.5" style="color: var(--text-faint);">Completed</p>
                    <p class="text-xl font-bold" id="m-completed" style="color: var(--text-primary);">—</p>
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
                    <p class="text-xl font-bold" id="m-rate" style="color: var(--text-primary);">—</p>
                </div>
                <div class="w-10 h-10 rounded-xl flex items-center justify-center group-hover:scale-110 transition-all duration-500" style="background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.15);">
                    <i class="fas fa-percent text-amber-400 text-sm"></i>
                </div>
            </div>
        </div>

        <div class="stat-card group shimmer" style="--stat-accent: linear-gradient(90deg, #3b82f6, #818cf8); --stat-glow: rgba(59,130,246,0.12); --stat-border-color: rgba(59,130,246,0.2);">
            <div class="flex items-center justify-between relative z-10">
                <div>
                    <p class="text-[10px] uppercase tracking-wider font-bold mb-1.5" style="color: var(--text-faint);">Flow version</p>
                    <p class="text-xl font-bold" id="m-version" style="color: var(--text-primary);">v{{ $flow->version }}</p>
                </div>
                <div class="w-10 h-10 rounded-xl flex items-center justify-center group-hover:scale-110 transition-all duration-500" style="background: rgba(59,130,246,0.1); border: 1px solid rgba(59,130,246,0.15);">
                    <i class="fas fa-code-branch text-blue-400 text-sm"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== STEP-BY-STEP DROP-OFF ===================== --}}
    <div class="card-premium p-5">
        <h5 class="text-base font-bold mb-1" style="color: var(--text-primary);">Step-by-step drop-off</h5>
        <div id="funnel" class="mt-3"><em class="text-xs" style="color: var(--text-faint);">Loading…</em></div>
    </div>
</div>

<script>
fetch(@json(route('user.links.conversational.analytics.json', $link)))
    .then(r => r.json())
    .then(data => {
        document.getElementById('m-sessions').textContent  = data.total_sessions;
        document.getElementById('m-completed').textContent = data.completed;
        document.getElementById('m-rate').textContent      = data.completion_pct + '%';
        const f = document.getElementById('funnel');
        if (!data.funnel.length) { f.innerHTML = '<em class="text-xs" style="color: var(--text-faint);">No traffic yet.</em>'; return; }
        const max = Math.max(...data.funnel.map(s => s.entered)) || 1;
        f.innerHTML = data.funnel.map(s => {
            const pct = Math.round((s.entered / max) * 100);
            const choices = (s.choices || []).map(c =>
                `<span class="cv-choice">${escapeHtml(c.value)}: ${c.count}</span>`
            ).join('');

            // Validation failure pill (only for input/datetime/file/rating with non-zero failures).
            const vf = s.validation_failures
                ? `<span class="cv-pill cv-warn-pill">${s.validation_failures} validation fails</span>`
                : '';

            // Rating distribution + average.
            let rating = '';
            if (s.rating) {
                const totalR = s.rating.count;
                rating = `<div class="mt-2 text-xs" style="color: var(--text-muted);">
                    <strong style="color: var(--text-primary);">Avg ${s.rating.avg ?? '—'}</strong> over ${totalR} ratings (${s.rating.scale})
                    ${Object.entries(s.rating.hist || {}).sort((a,b)=>parseFloat(a[0])-parseFloat(b[0])).map(([v,c]) => {
                        const w = totalR ? Math.round((c/totalR)*100) : 0;
                        return `<div class="cv-rating-row"><span style="width:24px;">${escapeHtml(v)}</span><div class="cv-mini-bar"><div class="cv-mini-bar-fill" style="width:${w}%"></div></div><span>${c}</span></div>`;
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
                        <div class="cv-mini-bar"><div class="cv-mini-bar-fill ${isFb ? 'is-fallback' : ''}" style="width:${w}%"></div></div>
                        <span>${i.count}</span>
                    </div>`;
                }).join('');
                ai = `<div class="mt-2 text-xs" style="color: var(--text-muted);">
                    <strong style="color: var(--text-primary);">AI routing</strong> · ${total} classified · fallback rate ${s.ai.fallback_pct}%
                    ${intents}
                </div>`;
            }

            return `
                <div class="cv-row">
                    <div class="flex justify-between items-baseline text-xs gap-2 mb-1.5">
                        <strong style="color: var(--text-primary);">${escapeHtml(s.key)} <span class="cv-pill">${escapeHtml(s.kind)}</span></strong>
                        <span style="color: var(--text-muted);">${s.entered} entered · ${s.answered} answered · ${s.drop_off_pct}% drop-off ${vf}</span>
                    </div>
                    <div class="text-xs mb-1.5" style="color: var(--text-muted);">${escapeHtml(s.preview)}</div>
                    <div class="cv-bar-track">
                        <div class="cv-bar-fill" style="width:${pct}%;">${s.entered}</div>
                    </div>
                    ${choices ? `<div class="mt-2">${choices}</div>` : ''}
                    ${rating}
                    ${ai}
                </div>
            `;
        }).join('');
    })
    .catch(() => {
        document.getElementById('funnel').innerHTML = '<div class="cv-error">Failed to load analytics.</div>';
    });

function escapeHtml(s) { return String(s ?? '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }
</script>
@endsection
