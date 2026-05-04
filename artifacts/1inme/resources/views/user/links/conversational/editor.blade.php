@extends('user.layouts.app')
@section('title', 'Conversational Flow - ' . ($link->title ?: $link->alias))
@section('breadcrumb_parent', 'Links')
@section('breadcrumb_parent_url', route('user.links.index'))
@section('content')
<style>
    .cv-builder { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 20px; align-items: start; }
    @media (max-width: 1100px) { .cv-builder { grid-template-columns: minmax(0, 1fr); } }

    .cv-card {
        background: var(--bg-card);
        border: 1px solid var(--border-glass);
        border-radius: 1rem;
        padding: 20px;
        margin-bottom: 16px;
        backdrop-filter: blur(20px);
        box-shadow: 0 4px 18px -8px rgba(15, 23, 42, 0.18);
    }
    .cv-card h5, .cv-card h6 { color: var(--text-primary); font-weight: 700; margin: 0; }
    .cv-card-title { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
    .cv-card-subtitle { color: var(--text-faint); font-size: 12px; margin-top: 2px; }

    .cv-toggle {
        display: flex; align-items: center; gap: 14px;
        padding: 16px 20px;
        background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%);
        color: #fff;
        border-radius: 1rem;
        margin-bottom: 18px;
        box-shadow: 0 10px 30px -12px rgba(139,92,246,0.55);
    }
    .cv-toggle .form-check-input { transform: scale(1.4); cursor: pointer; }
    .cv-toggle .cv-toggle-title { font-weight: 700; font-size: 14px; }
    .cv-toggle .cv-toggle-sub { font-size: 12px; opacity: 0.85; }

    .cv-field-label {
        display: block; font-size: 11px; font-weight: 600; text-transform: uppercase;
        letter-spacing: 0.04em; color: var(--text-muted); margin-bottom: 6px;
    }
    .cv-input, .cv-select, .cv-textarea {
        width: 100%; background: var(--bg-glass-input);
        border: 1px solid var(--border-glass); border-radius: 10px;
        color: var(--text-primary); font-size: 13px; padding: 9px 12px; line-height: 1.4;
    }
    .cv-textarea { resize: vertical; min-height: 60px; font-family: inherit; }
    .cv-input:focus, .cv-select:focus, .cv-textarea:focus {
        outline: none; border-color: rgba(139, 92, 246, 0.55);
        box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.15); background: var(--bg-card);
    }
    .cv-select {
        appearance: none; -webkit-appearance: none;
        background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'><path fill='%2394a3b8' d='M0 0l5 6 5-6z'/></svg>");
        background-repeat: no-repeat; background-position: right 12px center; padding-right: 32px;
    }

    .cv-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px; }
    .cv-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-bottom: 10px; }
    @media (max-width: 640px) { .cv-row, .cv-row-3 { grid-template-columns: 1fr; } }

    .cv-checkbox-line { display: inline-flex; align-items: center; gap: 8px; font-size: 12px; color: var(--text-muted); cursor: pointer; }
    .cv-checkbox-line input[type="checkbox"] { accent-color: #8b5cf6; width: 14px; height: 14px; }

    .cv-step { border: 1px solid var(--border-glass); border-radius: 12px; padding: 14px; margin-bottom: 12px; background: var(--bg-glass-input); }
    .cv-step-head { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 12px; }
    .cv-key { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11px; padding: 4px 10px;
              background: rgba(139, 92, 246, 0.15); color: #a78bfa; border-radius: 999px; font-weight: 600; }

    .cv-choice-row { display: grid; grid-template-columns: minmax(0,1.2fr) minmax(0,1fr) minmax(0,1.1fr) minmax(0,1.1fr) auto; gap: 8px; align-items: center; margin-bottom: 6px; }
    .cv-cond-row { display: grid; grid-template-columns: minmax(0,1fr) minmax(0,0.8fr) minmax(0,1fr) minmax(0,1fr) auto; gap: 6px; margin-bottom: 6px; }
    .cv-action-row { display: grid; grid-template-columns: minmax(0,1fr) minmax(0,1fr) minmax(0,1.4fr) auto; gap: 8px; align-items: center; margin-bottom: 8px; }
    .cv-intent-row { display: grid; grid-template-columns: minmax(0,1fr) minmax(0,1fr) minmax(0,1.2fr) minmax(0,1fr) auto; gap: 6px; margin-bottom: 6px; }
    @media (max-width: 900px) { .cv-choice-row, .cv-action-row, .cv-cond-row, .cv-intent-row { grid-template-columns: 1fr 1fr auto; } }

    .cv-btn { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600;
              padding: 8px 14px; border-radius: 10px; border: 1px solid transparent; cursor: pointer; line-height: 1; }
    .cv-btn-primary { color: #fff; background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
    .cv-btn-success { color: #fff; background: linear-gradient(135deg, #10b981, #059669); }
    .cv-btn-ghost   { color: var(--text-muted); background: var(--bg-glass-input); border-color: var(--border-glass); }
    .cv-btn-outline { color: #a78bfa; background: transparent; border-color: rgba(139, 92, 246, 0.4); }
    .cv-btn-danger  { color: #ef4444; background: rgba(239,68,68,0.08); border-color: rgba(239,68,68,0.25); padding: 6px 10px; font-size: 14px; }
    .cv-btn-danger:hover { background: rgba(239,68,68,0.18); color: #fff; }

    .cv-empty { color: var(--text-faint); font-size: 12px; padding: 8px 0; text-align: center; }
    .cv-help  { color: var(--text-faint); font-size: 12px; margin-bottom: 6px; }
    .cv-section { margin-top: 12px; padding: 10px 12px; border: 1px dashed rgba(139,92,246,0.25); border-radius: 8px; background: rgba(139,92,246,0.04); }
    .cv-section-title { font-size: 11px; font-weight: 700; color: #a78bfa; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px; }

    .cv-preview-frame { width: 100%; height: 600px; border: 1px solid var(--border-glass); border-radius: 12px; background: #0f172a; }
    .cv-preview-card { position: sticky; top: 80px; }

    .cv-tabs { display: flex; gap: 4px; padding: 4px; background: var(--bg-glass-input); border: 1px solid var(--border-glass); border-radius: 10px; margin-bottom: 12px; }
    .cv-tab { flex: 1; text-align: center; padding: 7px 10px; font-size: 12px; font-weight: 600; border-radius: 7px; cursor: pointer; color: var(--text-muted); border: 0; background: transparent; }
    .cv-tab.is-active { background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; box-shadow: 0 4px 12px -6px rgba(139,92,246,0.6); }

    .cv-sim-shell { display: flex; flex-direction: column; gap: 10px; height: 600px; }
    .cv-sim-transcript { flex: 1; overflow-y: auto; padding: 10px; border: 1px solid var(--border-glass); border-radius: 12px; background: #0f172a; display: flex; flex-direction: column; gap: 8px; }
    .cv-sim-bubble { max-width: 85%; padding: 8px 12px; border-radius: 14px; font-size: 13px; line-height: 1.4; word-wrap: break-word; white-space: pre-wrap; }
    .cv-sim-bubble.bot  { align-self: flex-start; background: rgba(255,255,255,0.08); color: #e2e8f0; border-bottom-left-radius: 4px; }
    .cv-sim-bubble.user { align-self: flex-end;   background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; border-bottom-right-radius: 4px; }
    .cv-sim-meta { font-size: 10px; color: #64748b; margin-top: 2px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
    .cv-sim-bubble.bot .cv-sim-meta { color: #94a3b8; }
    .cv-sim-bubble.user .cv-sim-meta { color: rgba(255,255,255,0.75); }
    .cv-sim-bubble.system { align-self: center; background: rgba(239,68,68,0.12); color: #fca5a5; font-size: 11px; padding: 6px 10px; border-radius: 8px; }
    .cv-sim-bubble.system.is-info { background: rgba(16,185,129,0.12); color: #6ee7b7; }
    .cv-sim-controls { padding: 10px; border: 1px dashed rgba(139,92,246,0.35); border-radius: 12px; background: rgba(139,92,246,0.05); display: flex; flex-direction: column; gap: 8px; }
    .cv-sim-controls .cv-input, .cv-sim-controls .cv-select, .cv-sim-controls .cv-textarea { background: var(--bg-card); }
    .cv-sim-quick { display: flex; flex-wrap: wrap; gap: 6px; }
    .cv-sim-chip { padding: 6px 12px; border-radius: 999px; border: 1px solid rgba(139,92,246,0.4); background: rgba(139,92,246,0.08); color: #c4b5fd; font-size: 12px; cursor: pointer; }
    .cv-sim-chip.is-picked { background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; border-color: transparent; }
    .cv-sim-row { display: flex; gap: 8px; align-items: center; }
    .cv-sim-state { font-size: 11px; color: var(--text-faint); padding: 8px 10px; background: var(--bg-glass-input); border-radius: 8px; border: 1px solid var(--border-glass); font-family: ui-monospace, SFMono-Regular, Menlo, monospace; max-height: 100px; overflow-y: auto; }
    .cv-sim-state strong { color: #a78bfa; }

    .cv-save-row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 24px; }
    .cv-save-status { font-size: 12px; color: var(--text-faint); }
    .cv-save-status.is-error { color: #ef4444; }
    .cv-save-status.is-ok    { color: #10b981; }
</style>

<div class="max-w-7xl mx-auto">
    @include('user.links.partials.editor-header', ['link' => $link, 'activeMainTab' => 'conversational'])

    <div class="flex items-center justify-between mb-4 gap-3 flex-wrap">
        <a href="{{ route('user.links.conversational.analytics', $link) }}" class="cv-btn cv-btn-outline no-underline">
            <i class="fas fa-chart-line"></i> Funnel analytics
        </a>
    </div>

    <div class="cv-toggle">
        <div class="form-check form-switch m-0">
            <input type="checkbox" class="form-check-input" id="cv-mode-toggle"
                {{ data_get($link->settings, 'biolink.mode') === 'conversational' ? 'checked' : '' }}>
        </div>
        <div>
            <div class="cv-toggle-title">Conversational mode</div>
            <div class="cv-toggle-sub">When ON, visitors see this chat instead of the normal block list.</div>
        </div>
    </div>

    <div class="cv-builder">
        <div>
            <div class="cv-card">
                <div class="cv-card-title">
                    <div>
                        <h5>Flow basics</h5>
                        <div class="cv-card-subtitle">Name your flow, set the opening line, and tune pacing.</div>
                    </div>
                </div>
                <div class="cv-row">
                    <div>
                        <label class="cv-field-label">Flow name</label>
                        <input id="cv-name" class="cv-input" value="{{ $flow->name }}">
                    </div>
                    <div>
                        <label class="cv-field-label">Default typing pause (ms)</label>
                        <input id="cv-typing" class="cv-input" type="number" min="0" max="5000"
                               value="{{ (int) data_get($flow->settings, 'default_typing_ms', 600) }}">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="cv-field-label">Opening line — supports <code>{{ '{{name}}' }}</code> and <code>{{ '{{answer:field}}' }}</code></label>
                    <textarea id="cv-intro" class="cv-textarea" rows="2">{{ $flow->intro_message }}</textarea>
                </div>
                <label class="cv-checkbox-line">
                    <input id="cv-published" type="checkbox" {{ $flow->is_published ? 'checked' : '' }}>
                    <span>Published — visitors will see this flow</span>
                </label>
            </div>

            <div class="cv-card">
                <div class="cv-card-title">
                    <div>
                        <h5>Steps</h5>
                        <div class="cv-card-subtitle">Each step is a chat bubble. Use conditions, multi-select, and AI routing for smarter flows.</div>
                    </div>
                    <button id="cv-add-step" class="cv-btn cv-btn-primary"><i class="fas fa-plus"></i> Add step</button>
                </div>
                <div id="cv-steps"></div>
            </div>

            <div class="cv-card">
                <div class="cv-card-title">
                    <div>
                        <h5>End actions</h5>
                        <div class="cv-card-subtitle">Reusable actions to attach to a step or a quick-reply choice.</div>
                    </div>
                    <button id="cv-add-action" class="cv-btn cv-btn-outline"><i class="fas fa-plus"></i> Add action</button>
                </div>
                <div id="cv-actions"></div>
            </div>

            <div class="cv-save-row">
                <button id="cv-save" class="cv-btn cv-btn-success"><i class="fas fa-save"></i> Save flow</button>
                <span id="cv-save-status" class="cv-save-status"></span>
            </div>
        </div>

        <div>
            <div class="cv-card cv-preview-card">
                <div class="cv-tabs" role="tablist">
                    <button class="cv-tab is-active" data-cv-tab="preview" type="button"><i class="fas fa-eye"></i> Live preview</button>
                    <button class="cv-tab" data-cv-tab="sim" type="button"><i class="fas fa-vial"></i> Simulator</button>
                </div>

                <div data-cv-pane="preview">
                    <div class="cv-card-subtitle mb-2">Save to refresh — draft flows are visible to you only.</div>
                    <iframe class="cv-preview-frame" src="{{ $previewUrl }}" id="cv-preview"></iframe>
                    <button class="cv-btn cv-btn-ghost mt-2" style="width:100%; justify-content:center;"
                            onclick="document.getElementById('cv-preview').src=document.getElementById('cv-preview').src">
                        <i class="fas fa-sync"></i> Reload preview
                    </button>
                </div>

                <div data-cv-pane="sim" style="display:none;">
                    <div class="cv-card-subtitle mb-2">
                        Dry-run the unsaved flow with mock answers. Conditions, choice overrides, AI intents, and merge tags resolve live.
                        <span style="display:block; margin-top:4px; color: var(--text-faint);">Approximations: AI intent is picked manually (no model call), file uploads are mocked, and AI credits aren't charged.</span>
                    </div>
                    <div class="cv-sim-shell">
                        <div class="cv-sim-transcript" id="cv-sim-transcript"></div>
                        <div class="cv-sim-controls" id="cv-sim-controls"></div>
                        <div class="cv-sim-state" id="cv-sim-state"></div>
                        <div class="cv-sim-row">
                            <button class="cv-btn cv-btn-outline" id="cv-sim-restart" style="flex:1; justify-content:center;">
                                <i class="fas fa-redo"></i> Restart simulation
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const STEP_KINDS    = @json($stepKinds);
const ACTION_KINDS  = @json($actionKinds);
const BLOCK_OPTIONS = @json($blockOptions);
const FLOW          = @json($flowPayload);
const INPUT_KINDS   = @json($inputKinds);
const COND_OPS      = @json($conditionOps);

const URLS = {
    save:   @json(route('user.links.conversational.save', $link)),
    toggle: @json(route('user.links.conversational.toggle', $link)),
};
const CSRF = '{{ csrf_token() }}';

let actions = (FLOW.actions || []).map(a => ({ ...a }));
let steps   = (FLOW.steps   || []).map(s => ({
    ...s,
    settings: s.settings && typeof s.settings === 'object' ? { ...s.settings } : {},
    choices: (s.choices || []).map(c => ({
        ...c,
        settings: c.settings && typeof c.settings === 'object' ? { ...c.settings } : {},
    })),
}));
let actionCounter = actions.length + 1;

function newKey(prefix) {
    let i = 1, k;
    do { k = prefix + '_' + i; i++; } while (steps.some(s => s.key === k));
    return k;
}

function escapeHtml(s) { return String(s ?? '').replace(/[&<>]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c])); }
function escapeAttr(s) { return String(s ?? '').replace(/"/g, '&quot;'); }

function renderActions() {
    const wrap = document.getElementById('cv-actions');
    wrap.innerHTML = '';
    actions.forEach((a, idx) => {
        const row = document.createElement('div');
        row.className = 'cv-action-row';
        row.innerHTML = `
            <select class="cv-select cv-action-kind">
                ${Object.entries(ACTION_KINDS).map(([k, l]) => `<option value="${k}" ${a.kind===k?'selected':''}>${l}</option>`).join('')}
            </select>
            <input class="cv-input cv-action-label" placeholder="Label" value="${escapeAttr(a.label||'')}">
            <input class="cv-input cv-action-payload" placeholder="URL / text / block id" value="${escapeAttr(payloadDisplay(a))}">
            <button class="cv-btn cv-btn-danger" data-rm="${idx}" title="Remove action">×</button>
        `;
        wrap.appendChild(row);
        row.querySelector('.cv-action-kind').addEventListener('change', e => { actions[idx].kind = e.target.value; renderSteps(); });
        row.querySelector('.cv-action-label').addEventListener('input', e => { actions[idx].label = e.target.value; });
        row.querySelector('.cv-action-payload').addEventListener('input', e => { actions[idx].payload = payloadFromInput(actions[idx].kind, e.target.value); });
        row.querySelector('[data-rm]').addEventListener('click', () => { actions.splice(idx, 1); renderActions(); renderSteps(); });
    });
    if (!actions.length) wrap.innerHTML = '<div class="cv-empty">No actions yet.</div>';
}
function payloadDisplay(a) {
    const p = a.payload || {};
    if (a.kind === 'open_link')      return p.url || '';
    if (a.kind === 'book_calendar')  return p.booking_url || '';
    if (a.kind === 'show_block')     return p.block_id || '';
    if (a.kind === 'message')        return p.text || '';
    if (a.kind === 'capture_email')  return p.cta || '';
    return '';
}
function payloadFromInput(kind, val) {
    if (kind === 'open_link')      return { url: val };
    if (kind === 'book_calendar')  return { booking_url: val };
    if (kind === 'show_block')     return { block_id: parseInt(val, 10) || null };
    if (kind === 'message')        return { text: val };
    if (kind === 'capture_email')  return { cta: val };
    return {};
}

function stepKeyOptions(currentKey) {
    return steps.filter(s => s.key !== currentKey).map(s => s.key);
}
function gotoSelect(value, currentKey, extraEmpty) {
    const opts = stepKeyOptions(currentKey).map(k =>
        `<option value="${k}" ${value===k?'selected':''}>→ ${k}</option>`).join('');
    return `<select class="cv-select"><option value="">${extraEmpty || '— next step —'}</option>${opts}</select>`;
}

/** Per-kind editor UI for the step's settings panel. */
function renderKindPanel(s, card) {
    const panel = card.querySelector('.cv-kind-panel');
    panel.innerHTML = '';
    const ks = s.settings || (s.settings = {});

    if (s.kind === 'input') {
        ks.input_kind = ks.input_kind || 'text';
        ks.validation = ks.validation || {};
        const v = ks.validation;
        panel.innerHTML = `
            <div class="cv-section">
                <div class="cv-section-title">Input settings</div>
                <div class="cv-row">
                    <div>
                        <label class="cv-field-label">Input kind</label>
                        <select class="cv-select" data-bind="input_kind">
                            ${INPUT_KINDS.map(k => `<option value="${k}" ${ks.input_kind===k?'selected':''}>${k}</option>`).join('')}
                        </select>
                    </div>
                    <div>
                        <label class="cv-field-label">Placeholder</label>
                        <input class="cv-input" data-bind="placeholder" value="${escapeAttr(ks.placeholder||'')}">
                    </div>
                </div>
                <div class="cv-row-3">
                    <div>
                        <label class="cv-field-label">Min length</label>
                        <input class="cv-input" type="number" min="0" data-vbind="min_length" value="${escapeAttr(v.min_length ?? '')}">
                    </div>
                    <div>
                        <label class="cv-field-label">Max length</label>
                        <input class="cv-input" type="number" min="1" data-vbind="max_length" value="${escapeAttr(v.max_length ?? '')}">
                    </div>
                    <div>
                        <label class="cv-field-label">Regex (no delimiters)</label>
                        <input class="cv-input" data-vbind="regex" value="${escapeAttr(v.regex||'')}" placeholder="e.g. ^[A-Z]{2,3}$">
                    </div>
                </div>
                <label class="cv-field-label">Error message (shown on validation fail)</label>
                <input class="cv-input" data-vbind="error_message" value="${escapeAttr(v.error_message||'')}" placeholder="Defaults to a sensible message">
            </div>`;
    } else if (s.kind === 'question') {
        const isMulti = !!ks.multi_select;
        panel.innerHTML = `
            <div class="cv-section">
                <div class="cv-section-title">Question settings</div>
                <label class="cv-checkbox-line">
                    <input type="checkbox" data-bind="multi_select" ${isMulti?'checked':''}>
                    <span>Multi-select (visitor picks several choices)</span>
                </label>
                <div class="cv-row mt-2" style="${isMulti?'':'display:none;'}" data-multi-config>
                    <div><label class="cv-field-label">Min picks</label>
                        <input class="cv-input" type="number" min="1" data-bind="min_choices" value="${ks.min_choices ?? 1}"></div>
                    <div><label class="cv-field-label">Max picks</label>
                        <input class="cv-input" type="number" min="1" data-bind="max_choices" value="${ks.max_choices ?? (s.choices||[]).length}"></div>
                </div>
            </div>`;
    } else if (s.kind === 'media') {
        ks.media = ks.media || { kind: 'image', url: '', alt: '' };
        const m = ks.media;
        panel.innerHTML = `
            <div class="cv-section">
                <div class="cv-section-title">Media settings</div>
                <div class="cv-row">
                    <div><label class="cv-field-label">Media kind</label>
                        <select class="cv-select" data-mbind="kind">
                            ${['image','gif','video','audio'].map(k => `<option value="${k}" ${m.kind===k?'selected':''}>${k}</option>`).join('')}
                        </select>
                    </div>
                    <div><label class="cv-field-label">Alt text</label>
                        <input class="cv-input" data-mbind="alt" value="${escapeAttr(m.alt||'')}"></div>
                </div>
                <label class="cv-field-label">URL</label>
                <input class="cv-input" data-mbind="url" value="${escapeAttr(m.url||'')}" placeholder="https://...">
            </div>`;
    } else if (s.kind === 'file_upload') {
        ks.file = ks.file || { max_mb: 10, accept: '' };
        const f = ks.file;
        panel.innerHTML = `
            <div class="cv-section">
                <div class="cv-section-title">File upload</div>
                <div class="cv-row">
                    <div><label class="cv-field-label">Max size (MB, 1–50)</label>
                        <input class="cv-input" type="number" min="1" max="50" data-fbind="max_mb" value="${f.max_mb ?? 10}"></div>
                    <div><label class="cv-field-label">Accepted extensions (comma-list)</label>
                        <input class="cv-input" data-fbind="accept" value="${escapeAttr(f.accept||'')}" placeholder="pdf,jpg,png"></div>
                </div>
                <p class="cv-help" style="margin-top:8px;font-size:12px;opacity:.75">
                    Heads up: visitor uploads are kept for 7 days. Files from
                    abandoned chats — and previous uploads when a visitor
                    re-uploads on this step — are deleted automatically.
                </p>
            </div>`;
    } else if (s.kind === 'rating') {
        ks.rating = ks.rating || { scale: 'star', min: 1, max: 5 };
        const r = ks.rating;
        panel.innerHTML = `
            <div class="cv-section">
                <div class="cv-section-title">Rating settings</div>
                <div class="cv-row-3">
                    <div><label class="cv-field-label">Scale</label>
                        <select class="cv-select" data-rbind="scale">
                            ${['star','nps','emoji'].map(k => `<option value="${k}" ${r.scale===k?'selected':''}>${k}</option>`).join('')}
                        </select></div>
                    <div><label class="cv-field-label">Min</label>
                        <input class="cv-input" type="number" data-rbind="min" value="${r.min ?? 1}"></div>
                    <div><label class="cv-field-label">Max</label>
                        <input class="cv-input" type="number" data-rbind="max" value="${r.max ?? 5}"></div>
                </div>
            </div>`;
    } else if (s.kind === 'datetime') {
        ks.datetime = ks.datetime || { mode: 'datetime' };
        const d = ks.datetime;
        panel.innerHTML = `
            <div class="cv-section">
                <div class="cv-section-title">Date / time settings</div>
                <div class="cv-row-3">
                    <div><label class="cv-field-label">Mode</label>
                        <select class="cv-select" data-dbind="mode">
                            ${['date','time','datetime'].map(k => `<option value="${k}" ${d.mode===k?'selected':''}>${k}</option>`).join('')}
                        </select></div>
                    <div><label class="cv-field-label">Min (ISO)</label>
                        <input class="cv-input" data-dbind="min" value="${escapeAttr(d.min||'')}" placeholder="2027-01-01"></div>
                    <div><label class="cv-field-label">Max (ISO)</label>
                        <input class="cv-input" data-dbind="max" value="${escapeAttr(d.max||'')}" placeholder="2027-12-31"></div>
                </div>
            </div>`;
    } else if (s.kind === 'ai_freetext') {
        ks.ai = ks.ai || { intents: [], fallback_step_key: '' };
        const a = ks.ai;
        a.intents = a.intents || [];
        const intentRows = a.intents.map((it, ii) => `
            <div class="cv-intent-row" data-i="${ii}">
                <input class="cv-input" data-ibind="value" value="${escapeAttr(it.value||'')}" placeholder="value (e.g. pricing)">
                <input class="cv-input" data-ibind="label" value="${escapeAttr(it.label||'')}" placeholder="Label">
                <input class="cv-input" data-ibind="examples" value="${escapeAttr(it.examples||'')}" placeholder="Example utterances (comma list)">
                ${gotoSelect(it.next_step_key, s.key, '— route to —').replace('class="cv-select"', 'class="cv-select" data-ibind="next_step_key"')}
                <button class="cv-btn cv-btn-danger" data-rm-i="${ii}" title="Remove intent">×</button>
            </div>`).join('');
        panel.innerHTML = `
            <div class="cv-section">
                <div class="cv-section-title">AI free-text routing</div>
                <div class="cv-help">Visitor reply is classified into one of these intents and routed accordingly. Falls back if confidence is low.</div>
                <div data-intents>${intentRows || '<div class="cv-empty">No intents yet.</div>'}</div>
                <button class="cv-btn cv-btn-outline mt-1" data-add-intent><i class="fas fa-plus"></i> Add intent</button>
                <div class="cv-row mt-2">
                    <div><label class="cv-field-label">Fallback step</label>
                        ${gotoSelect(a.fallback_step_key, s.key, '— pick a fallback step —').replace('class="cv-select"', 'class="cv-select" data-aibind="fallback_step_key"')}</div>
                    <div><label class="cv-field-label">Min confidence (0–1)</label>
                        <input class="cv-input" type="number" min="0" max="1" step="0.05" data-aibind="min_confidence" value="${a.min_confidence ?? 0.4}"></div>
                </div>
            </div>`;
    }

    // Wire generic data-bind / data-vbind / data-mbind / etc.
    panel.querySelectorAll('[data-bind]').forEach(el => {
        el.addEventListener('input', e => {
            const k = el.getAttribute('data-bind');
            if (el.type === 'checkbox') ks[k] = el.checked;
            else if (el.type === 'number') ks[k] = el.value === '' ? null : Number(el.value);
            else ks[k] = el.value;
            if (k === 'multi_select') renderKindPanel(s, card);
        });
        el.addEventListener('change', e => el.dispatchEvent(new Event('input')));
    });
    panel.querySelectorAll('[data-vbind]').forEach(el => {
        el.addEventListener('input', e => {
            const k = el.getAttribute('data-vbind');
            ks.validation = ks.validation || {};
            ks.validation[k] = el.type === 'number' ? (el.value === '' ? null : Number(el.value)) : el.value;
        });
    });
    panel.querySelectorAll('[data-mbind]').forEach(el => {
        el.addEventListener('input', e => { ks.media[el.getAttribute('data-mbind')] = el.value; });
    });
    panel.querySelectorAll('[data-fbind]').forEach(el => {
        el.addEventListener('input', e => { ks.file[el.getAttribute('data-fbind')] = el.type==='number'?Number(el.value):el.value; });
    });
    panel.querySelectorAll('[data-rbind]').forEach(el => {
        el.addEventListener('input', e => { ks.rating[el.getAttribute('data-rbind')] = el.type==='number'?Number(el.value):el.value; });
    });
    panel.querySelectorAll('[data-dbind]').forEach(el => {
        el.addEventListener('input', e => { ks.datetime[el.getAttribute('data-dbind')] = el.value; });
    });
    panel.querySelectorAll('[data-aibind]').forEach(el => {
        el.addEventListener('input', e => { ks.ai[el.getAttribute('data-aibind')] = el.type==='number'?Number(el.value):el.value; });
    });
    panel.querySelectorAll('[data-ibind]').forEach(el => {
        el.addEventListener('input', e => {
            const idx = parseInt(el.closest('[data-i]').dataset.i, 10);
            const k = el.getAttribute('data-ibind');
            ks.ai.intents[idx][k] = el.value || null;
        });
    });
    panel.querySelectorAll('[data-rm-i]').forEach(el => {
        el.addEventListener('click', () => { ks.ai.intents.splice(parseInt(el.dataset.rmI,10), 1); renderKindPanel(s, card); });
    });
    const addI = panel.querySelector('[data-add-intent]');
    if (addI) addI.addEventListener('click', () => {
        ks.ai.intents.push({ value: 'intent_' + (ks.ai.intents.length+1), label: 'New intent', examples: '', next_step_key: null });
        renderKindPanel(s, card);
    });

    // Step-level branching conditions (works for any kind).
    const condBox = card.querySelector('.cv-step-conds');
    const stepConds = ks.conditions || (ks.conditions = []);
    condBox.innerHTML = `
        <div class="cv-section">
            <div class="cv-section-title">Branch conditions <span class="cv-help" style="font-weight:400; text-transform:none;">(first match wins, otherwise uses Next step)</span></div>
            <div data-conds>${stepConds.map((c, ci) => condRowHtml(c, ci, s.key)).join('') || '<div class="cv-empty">No conditions.</div>'}</div>
            <button class="cv-btn cv-btn-outline mt-1" data-add-cond><i class="fas fa-plus"></i> Add condition</button>
        </div>`;
    condBox.querySelectorAll('[data-cond-i]').forEach(row => {
        const ci = parseInt(row.dataset.condI, 10);
        const ins = row.querySelectorAll('input, select');
        ins[0].addEventListener('input', e => stepConds[ci].field = e.target.value);
        ins[1].addEventListener('change', e => stepConds[ci].op = e.target.value);
        ins[2].addEventListener('input', e => stepConds[ci].value = e.target.value);
        ins[3].addEventListener('change', e => stepConds[ci].goto = e.target.value || null);
        row.querySelector('[data-rm-cond]').addEventListener('click', () => { stepConds.splice(ci, 1); renderKindPanel(s, card); });
    });
    condBox.querySelector('[data-add-cond]').addEventListener('click', () => {
        stepConds.push({ field: s.answer_field || s.key, op: 'eq', value: '', goto: '' });
        renderKindPanel(s, card);
    });
}
function condRowHtml(c, ci, currentKey) {
    return `<div class="cv-cond-row" data-cond-i="${ci}">
        <input class="cv-input" placeholder="answer field" value="${escapeAttr(c.field||'')}">
        <select class="cv-select">${COND_OPS.map(o => `<option value="${o}" ${c.op===o?'selected':''}>${o}</option>`).join('')}</select>
        <input class="cv-input" placeholder="value" value="${escapeAttr(c.value ?? '')}">
        ${gotoSelect(c.goto, currentKey, '— go to —')}
        <button class="cv-btn cv-btn-danger" data-rm-cond title="Remove">×</button>
    </div>`;
}

function renderSteps() {
    const wrap = document.getElementById('cv-steps');
    wrap.innerHTML = '';
    if (!steps.length) { wrap.innerHTML = '<div class="cv-empty">No steps yet. Add one to get started.</div>'; return; }

    steps.forEach((s, idx) => {
        const card = document.createElement('div');
        card.className = 'cv-step';
        card.innerHTML = `
            <div class="cv-step-head">
                <span class="cv-key">${escapeHtml(s.key)}</span>
                <select class="cv-select cv-step-kind" style="max-width:240px;">
                    ${Object.entries(STEP_KINDS).map(([k, l]) => `<option value="${k}" ${s.kind===k?'selected':''}>${l}</option>`).join('')}
                </select>
                <label class="cv-checkbox-line"><input type="checkbox" class="cv-step-entry" ${s.is_entry?'checked':''}> Entry</label>
                <label class="cv-checkbox-line"><input type="checkbox" class="cv-step-skip"  ${s.skip_if_known?'checked':''}> Skip if known</label>
                <button class="cv-btn cv-btn-danger ms-auto" data-rm-step title="Remove step">×</button>
            </div>
            <div class="cv-row">
                <div><label class="cv-field-label">Step key</label>
                    <input class="cv-input cv-step-keyinput" value="${escapeAttr(s.key)}" placeholder="step_key"></div>
                <div><label class="cv-field-label">Answer field</label>
                    <input class="cv-input cv-step-field" value="${escapeAttr(s.answer_field||'')}" placeholder="e.g. intent"></div>
            </div>
            <label class="cv-field-label">Bot message — supports <code>{{ '{{name}}' }}</code>, <code>{{ '{{answer:field}}' }}</code></label>
            <textarea class="cv-textarea cv-step-msg mb-2" rows="2">${escapeHtml(s.message_text||'')}</textarea>
            <div class="cv-row-3">
                <div><label class="cv-field-label">Next step (default)</label>
                    ${gotoSelect(s.next_step_key, s.key, '— ends here —').replace('class="cv-select"', 'class="cv-select cv-step-next"')}
                </div>
                <div><label class="cv-field-label">Action on completion</label>
                    <select class="cv-select cv-step-action">
                        <option value="">— No action —</option>
                        ${actions.map(a => `<option value="${a.client_id}" ${s.action_client_id===a.client_id?'selected':''}>⚡ ${escapeHtml(a.label||a.kind)}</option>`).join('')}
                    </select></div>
                <div><label class="cv-field-label">Typing pause (ms)</label>
                    <input class="cv-input cv-step-typing" type="number" min="0" max="8000" value="${s.settings.typing_delay_ms ?? ''}" placeholder="default"></div>
            </div>
            <div class="cv-kind-panel"></div>
            <div class="cv-choices-wrap" style="${s.kind==='question'?'':'display:none;'}">
                <div class="d-flex justify-content-between align-items-center mt-3 mb-2">
                    <strong style="font-size:12px; color: var(--text-primary);">Quick-reply choices</strong>
                    <button class="cv-btn cv-btn-outline cv-add-choice"><i class="fas fa-plus"></i> Choice</button>
                </div>
                <div class="cv-choices-list"></div>
            </div>
            <div class="cv-step-conds"></div>
        `;
        wrap.appendChild(card);

        const choicesList = card.querySelector('.cv-choices-list');
        function rebuildChoices() {
            choicesList.innerHTML = '';
            (s.choices || []).forEach((c, ci) => {
                const cs = c.settings || (c.settings = {});
                const cond = cs.condition;
                const row = document.createElement('div');
                row.innerHTML = `
                    <div class="cv-choice-row">
                        <input class="cv-input" placeholder="Label" value="${escapeAttr(c.label||'')}">
                        <input class="cv-input" placeholder="value" value="${escapeAttr(c.value||'')}">
                        ${gotoSelect(c.next_step_key, s.key)}
                        <select class="cv-select">
                            <option value="">— action —</option>
                            ${actions.map(a => `<option value="${a.client_id}" ${c.action_client_id===a.client_id?'selected':''}>⚡ ${escapeHtml(a.label||a.kind)}</option>`).join('')}
                        </select>
                        <button class="cv-btn cv-btn-danger" title="Remove choice">×</button>
                    </div>
                    <div class="cv-cond-row" style="margin-left:8px; margin-bottom:8px;">
                        <input class="cv-input" placeholder="When field…" value="${escapeAttr(cond?.field||'')}" data-cb="field">
                        <select class="cv-select" data-cb="op">
                            <option value="">(always)</option>
                            ${COND_OPS.map(o => `<option value="${o}" ${cond?.op===o?'selected':''}>${o}</option>`).join('')}
                        </select>
                        <input class="cv-input" placeholder="value" value="${escapeAttr(cond?.value ?? '')}" data-cb="value">
                        ${gotoSelect(cond?.goto, s.key, '— override goto —').replace('class="cv-select"', 'class="cv-select" data-cb="goto"')}
                        <span></span>
                    </div>
                `;
                const choiceRow = row.firstElementChild;
                const condRow   = row.children[1];
                const inputs = choiceRow.querySelectorAll('input, select');
                inputs[0].addEventListener('input', e => c.label = e.target.value);
                inputs[1].addEventListener('input', e => c.value = e.target.value);
                inputs[2].addEventListener('change', e => c.next_step_key = e.target.value || null);
                inputs[3].addEventListener('change', e => c.action_client_id = e.target.value || null);
                choiceRow.querySelector('button').addEventListener('click', () => { s.choices.splice(ci, 1); rebuildChoices(); });
                condRow.querySelectorAll('[data-cb]').forEach(el => {
                    el.addEventListener('input', e => {
                        const k = el.getAttribute('data-cb');
                        cs.condition = cs.condition || {};
                        cs.condition[k] = el.value || null;
                        if (!cs.condition.op || (!cs.condition.field && !cs.condition.value && !cs.condition.goto)) {
                            // Empty op clears the condition entirely.
                            if (!cs.condition.op) delete cs.condition;
                        }
                    });
                    el.addEventListener('change', e => el.dispatchEvent(new Event('input')));
                });
                choicesList.appendChild(choiceRow);
                choicesList.appendChild(condRow);
            });
            if (!(s.choices || []).length) choicesList.innerHTML = '<div class="cv-empty">No choices yet.</div>';
        }
        rebuildChoices();

        card.querySelector('.cv-add-choice').addEventListener('click', () => {
            s.choices = s.choices || [];
            s.choices.push({ label: 'New choice', value: 'choice_' + (s.choices.length + 1), next_step_key: null, action_client_id: null, settings: {} });
            rebuildChoices();
        });
        card.querySelector('.cv-step-kind').addEventListener('change', e => {
            s.kind = e.target.value;
            card.querySelector('.cv-choices-wrap').style.display = s.kind === 'question' ? '' : 'none';
            renderKindPanel(s, card);
        });
        card.querySelector('.cv-step-typing').addEventListener('input', e => {
            const v = e.target.value;
            if (v === '') delete s.settings.typing_delay_ms;
            else s.settings.typing_delay_ms = Number(v);
        });
        card.querySelector('.cv-step-entry').addEventListener('change', e => {
            if (e.target.checked) { steps.forEach(o => o.is_entry = false); s.is_entry = true; renderSteps(); }
            else s.is_entry = false;
        });
        card.querySelector('.cv-step-skip').addEventListener('change', e => s.skip_if_known = e.target.checked);
        card.querySelector('.cv-step-keyinput').addEventListener('change', e => {
            const newK = (e.target.value || '').toLowerCase().replace(/[^a-z0-9_]/g, '_');
            if (!newK || steps.some((o, oi) => oi !== idx && o.key === newK)) { e.target.value = s.key; return; }
            s.key = newK; renderSteps();
        });
        card.querySelector('.cv-step-field').addEventListener('input', e => s.answer_field = e.target.value || null);
        card.querySelector('.cv-step-msg').addEventListener('input', e => s.message_text = e.target.value);
        card.querySelector('.cv-step-next').addEventListener('change', e => s.next_step_key = e.target.value || null);
        card.querySelector('.cv-step-action').addEventListener('change', e => s.action_client_id = e.target.value || null);
        card.querySelector('[data-rm-step]').addEventListener('click', () => { steps.splice(idx, 1); renderSteps(); });

        renderKindPanel(s, card);
    });
}

document.getElementById('cv-add-step').addEventListener('click', () => {
    steps.push({
        key: newKey('step'), kind: 'question', message_text: 'New question?',
        answer_field: null, is_entry: false, skip_if_known: true,
        next_step_key: null, action_client_id: null,
        settings: {}, choices: [],
    });
    renderSteps();
});
document.getElementById('cv-add-action').addEventListener('click', () => {
    actions.push({ client_id: 'new_' + (actionCounter++), kind: 'open_link', label: 'New action', payload: {} });
    renderActions(); renderSteps();
});

document.getElementById('cv-save').addEventListener('click', async () => {
    const status = document.getElementById('cv-save-status');
    status.textContent = 'Saving…'; status.className = 'cv-save-status';
    const body = {
        name: document.getElementById('cv-name').value,
        intro_message: document.getElementById('cv-intro').value,
        is_published: document.getElementById('cv-published').checked,
        settings: { default_typing_ms: Number(document.getElementById('cv-typing').value || 600) },
        actions, steps,
    };
    try {
        const r = await fetch(URLS.save, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify(body),
        });
        const j = await r.json();
        if (!j.ok) { status.textContent = '❌ ' + (j.error || 'Save failed'); status.className = 'cv-save-status is-error'; return; }
        status.textContent = '✓ Saved (v' + j.version + ')'; status.className = 'cv-save-status is-ok';
        document.getElementById('cv-preview').src = document.getElementById('cv-preview').src;
    } catch (e) {
        status.textContent = '❌ Network error'; status.className = 'cv-save-status is-error';
    }
});

document.getElementById('cv-mode-toggle').addEventListener('change', async (e) => {
    await fetch(URLS.toggle, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ enabled: e.target.checked }),
    });
    document.getElementById('cv-preview').src = document.getElementById('cv-preview').src;
});

renderActions();
renderSteps();

// ───────────────────────── Simulator ─────────────────────────
// Dry-runs the in-memory flow (unsaved edits included) so creators
// can validate branching, choice overrides, AI intent routing, and
// merge tags without clicking through the live chat.

document.querySelectorAll('[data-cv-tab]').forEach(btn => {
    btn.addEventListener('click', () => {
        const tab = btn.dataset.cvTab;
        document.querySelectorAll('[data-cv-tab]').forEach(b => b.classList.toggle('is-active', b === btn));
        document.querySelectorAll('[data-cv-pane]').forEach(p => {
            p.style.display = p.dataset.cvPane === tab ? '' : 'none';
        });
        if (tab === 'sim') simRestart();
    });
});

const sim = {
    answers: {},
    currentKey: null,
    history: [],     // visited step keys
    transcript: [],  // {role, text, meta}
    done: false,
};

function simStepsByKey() {
    const m = {};
    steps.forEach(s => { if (s.key) m[s.key] = s; });
    return m;
}

function simEntryStep() {
    const entry = steps.find(s => s.is_entry);
    return entry || steps[0] || null;
}

function simRenderTemplate(tpl) {
    if (!tpl || tpl.indexOf('{{') === -1) return tpl || '';
    return String(tpl).replace(/\{\{\s*([a-z0-9_]+)(?::([a-z0-9_]+))?\s*\}\}/gi, (_, ns, key) => {
        const lookup = key || ns;
        const a = sim.answers;
        let v = '';
        if (ns.toLowerCase() === 'step' || ns.toLowerCase() === 'answer') v = a[key] ?? '';
        else v = a[lookup] ?? '';
        if (Array.isArray(v)) v = v.join(', ');
        return v == null ? '' : String(v);
    });
}

function simEvalCondition(cond) {
    if (!cond || !cond.field) return false;
    const actual = sim.answers[cond.field];
    const expected = cond.value;
    const op = cond.op || 'eq';
    const isArr = Array.isArray(actual);
    const sa = actual == null ? '' : String(actual);
    const se = expected == null ? '' : String(expected);
    switch (op) {
        case 'eq':  return isArr ? actual.map(String).includes(se) : sa === se;
        case 'neq': return isArr ? !actual.map(String).includes(se) : sa !== se;
        case 'contains':     return isArr ? actual.map(String).includes(se) : sa.toLowerCase().includes(se.toLowerCase());
        case 'not_contains': return !(isArr ? actual.map(String).includes(se) : sa.toLowerCase().includes(se.toLowerCase()));
        case 'in': {
            const arr = Array.isArray(expected) ? expected.map(String) : se.split(',').map(s => s.trim());
            return arr.includes(sa);
        }
        case 'gt': return !isNaN(parseFloat(sa)) && parseFloat(sa) > parseFloat(se);
        case 'lt': return !isNaN(parseFloat(sa)) && parseFloat(sa) < parseFloat(se);
        case 'exists': return actual !== null && actual !== undefined && actual !== '';
        case 'empty':  return actual === null || actual === undefined || actual === '' || (isArr && actual.length === 0);
    }
    return false;
}

function simResolveStep(stepKey) {
    const map = simStepsByKey();
    const seen = {};
    let s = map[stepKey];
    while (s) {
        const field = s.answer_field || s.key;
        const known = Object.prototype.hasOwnProperty.call(sim.answers, field);
        if (!s.skip_if_known || !known || s.kind === 'end') return s;
        let nextKey = s.next_step_key || null;
        if (s.kind === 'question') {
            (s.choices || []).forEach(c => {
                if (c.value === sim.answers[field] && c.next_step_key) nextKey = c.next_step_key;
            });
        }
        const conds = (s.settings && s.settings.conditions) || [];
        for (const c of conds) {
            if (simEvalCondition(c) && c.goto) { nextKey = c.goto; break; }
        }
        if (!nextKey || seen[nextKey]) return s;
        seen[nextKey] = true;
        s = map[nextKey];
    }
    return null;
}

function simBubble(role, text, meta) {
    sim.transcript.push({ role, text, meta: meta || '' });
}

function simRenderTranscript() {
    const wrap = document.getElementById('cv-sim-transcript');
    if (!wrap) return;
    wrap.innerHTML = sim.transcript.map(b => {
        const cls = 'cv-sim-bubble ' + b.role + (b.role === 'system' && b.meta === 'info' ? ' is-info' : '');
        const meta = (b.role !== 'system' && b.meta) ? `<div class="cv-sim-meta">${escapeHtml(b.meta)}</div>` : '';
        return `<div class="${cls}">${escapeHtml(b.text)}${meta}</div>`;
    }).join('');
    wrap.scrollTop = wrap.scrollHeight;
}

function simRenderState() {
    const el = document.getElementById('cv-sim-state');
    if (!el) return;
    const path = sim.history.join(' → ') || '—';
    const ans = Object.keys(sim.answers).length
        ? Object.entries(sim.answers).map(([k, v]) => `${k}=${Array.isArray(v) ? v.join('|') : v}`).join(', ')
        : '—';
    el.innerHTML = `<strong>Path:</strong> ${escapeHtml(path)}<br><strong>Answers:</strong> ${escapeHtml(ans)}`;
}

function simEnter(stepKey, sourceMeta) {
    const map = simStepsByKey();
    const resolved = simResolveStep(stepKey);
    if (!resolved) {
        simBubble('system', 'Flow ended (no next step).', 'info');
        sim.done = true;
        simRenderTranscript();
        simRenderState();
        simRenderControls(null);
        return;
    }
    sim.currentKey = resolved.key;
    sim.history.push(resolved.key);
    const text = simRenderTemplate(resolved.message_text || '(no message)');
    let meta = `${resolved.kind} · ${resolved.key}`;
    if (sourceMeta) meta = sourceMeta + ' → ' + meta;
    simBubble('bot', text, meta);
    simRenderTranscript();
    simRenderState();

    // Auto-advance kinds with no input.
    if (['message', 'media', 'end'].includes(resolved.kind)) {
        const nextKey = simResolveAfter(resolved, null);
        if (resolved.kind === 'end' || !nextKey) {
            sim.done = true;
            simFinish(resolved);
            return;
        }
        // small delay for readability when chaining auto-steps
        setTimeout(() => simEnter(nextKey, 'auto'), 250);
        simRenderControls(resolved); // briefly show "auto-advancing"
        return;
    }

    simRenderControls(resolved);
}

function simResolveAfter(step, choiceValue) {
    let nextKey = step.next_step_key || null;
    if (step.kind === 'question' && choiceValue != null && !Array.isArray(choiceValue)) {
        const c = (step.choices || []).find(x => x.value === choiceValue);
        if (c) {
            const cs = c.settings || {};
            if (cs.condition && simEvalCondition(cs.condition) && cs.condition.goto) {
                nextKey = cs.condition.goto;
            } else {
                if (c.next_step_key) nextKey = c.next_step_key;
            }
        }
    }
    // Step-level conditions evaluated last (first match wins, overrides above).
    const conds = (step.settings && step.settings.conditions) || [];
    for (const cond of conds) {
        if (simEvalCondition(cond) && cond.goto) { nextKey = cond.goto; break; }
    }
    return nextKey;
}

function simFinish(step) {
    let actionLabel = null;
    const findAction = (clientId) => actions.find(a => a.client_id === clientId);
    if (step.kind === 'question' && step.action_client_id) {
        const a = findAction(step.action_client_id);
        if (a) actionLabel = a.label || a.kind;
    } else if (step.action_client_id) {
        const a = findAction(step.action_client_id);
        if (a) actionLabel = a.label || a.kind;
    }
    simBubble('system', actionLabel ? `Flow complete · action: ${actionLabel}` : 'Flow complete.', 'info');
    simRenderTranscript();
    simRenderControls(null);
}

function simAdvance(step, choiceValue, displayValue, choiceObj) {
    // Record the user-visible answer bubble.
    simBubble('user', displayValue);
    // Persist the answer.
    const field = step.answer_field || step.key;
    sim.answers[field] = choiceValue;

    // Compute the route + which rule fired (for the meta caption).
    let nextKey = step.next_step_key || null;
    let routeNote = nextKey ? `default → ${nextKey}` : 'no default';
    if (step.kind === 'question' && choiceObj && !Array.isArray(choiceValue)) {
        const cs = choiceObj.settings || {};
        if (cs.condition && simEvalCondition(cs.condition) && cs.condition.goto) {
            nextKey = cs.condition.goto;
            routeNote = `choice condition → ${nextKey}`;
        } else if (choiceObj.next_step_key) {
            nextKey = choiceObj.next_step_key;
            routeNote = `choice route → ${nextKey}`;
        }
    }
    const conds = (step.settings && step.settings.conditions) || [];
    for (let i = 0; i < conds.length; i++) {
        if (simEvalCondition(conds[i]) && conds[i].goto) {
            nextKey = conds[i].goto;
            routeNote = `step condition #${i + 1} (${conds[i].field} ${conds[i].op} ${conds[i].value}) → ${nextKey}`;
            break;
        }
    }

    // Trailing action on the step or chosen choice.
    let chosenAction = null;
    if (step.kind === 'question' && choiceObj && choiceObj.action_client_id) {
        chosenAction = actions.find(a => a.client_id === choiceObj.action_client_id);
    }
    if (!chosenAction && step.action_client_id) {
        chosenAction = actions.find(a => a.client_id === step.action_client_id);
    }

    simRenderTranscript();
    simRenderState();

    if (!nextKey) {
        simBubble('system', chosenAction
            ? `Flow complete · action: ${chosenAction.label || chosenAction.kind} (${routeNote})`
            : `Flow complete (${routeNote}).`, 'info');
        sim.done = true;
        simRenderTranscript();
        simRenderControls(null);
        return;
    }

    simEnter(nextKey, routeNote);
}

function simRenderControls(step) {
    const wrap = document.getElementById('cv-sim-controls');
    if (!wrap) return;
    wrap.innerHTML = '';
    if (!step || sim.done) {
        wrap.innerHTML = `<div class="cv-empty" style="padding:0;">Simulation finished. Use “Restart” to try a different path.</div>`;
        return;
    }

    if (step.kind === 'message' || step.kind === 'media' || step.kind === 'end') {
        wrap.innerHTML = `<div class="cv-empty" style="padding:0;">Auto-advancing…</div>`;
        return;
    }

    if (step.kind === 'question') {
        const isMulti = !!(step.settings && step.settings.multi_select);
        const choices = step.choices || [];
        if (!choices.length) {
            wrap.innerHTML = `<div class="cv-empty" style="padding:0;">No choices configured for this question.</div>`;
            return;
        }
        if (!isMulti) {
            const chips = document.createElement('div');
            chips.className = 'cv-sim-quick';
            choices.forEach(c => {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'cv-sim-chip';
                b.textContent = simRenderTemplate(c.label || c.value || '?');
                b.addEventListener('click', () => simAdvance(step, c.value, simRenderTemplate(c.label || c.value || ''), c));
                chips.appendChild(b);
            });
            wrap.appendChild(chips);
        } else {
            const min = parseInt(step.settings.min_choices || 1, 10);
            const max = parseInt(step.settings.max_choices || choices.length, 10);
            const chips = document.createElement('div');
            chips.className = 'cv-sim-quick';
            const picked = new Set();
            choices.forEach(c => {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'cv-sim-chip';
                b.textContent = simRenderTemplate(c.label || c.value || '?');
                b.addEventListener('click', () => {
                    if (picked.has(c.value)) picked.delete(c.value);
                    else picked.add(c.value);
                    b.classList.toggle('is-picked', picked.has(c.value));
                });
                chips.appendChild(b);
            });
            const submit = document.createElement('button');
            submit.className = 'cv-btn cv-btn-primary';
            submit.style.alignSelf = 'flex-start';
            submit.innerHTML = `<i class="fas fa-paper-plane"></i> Send (${min}–${max})`;
            submit.addEventListener('click', () => {
                if (picked.size < min || picked.size > max) {
                    alert(`Pick between ${min} and ${max} options.`);
                    return;
                }
                const arr = Array.from(picked);
                const labels = arr.map(v => {
                    const c = choices.find(x => x.value === v);
                    return simRenderTemplate(c ? (c.label || c.value) : v);
                });
                simAdvance(step, arr, labels.join(', '), null);
            });
            wrap.appendChild(chips);
            wrap.appendChild(submit);
        }
        return;
    }

    if (step.kind === 'input') {
        const ks = step.settings || {};
        const inp = document.createElement('input');
        inp.className = 'cv-input';
        inp.type = (ks.input_kind === 'number') ? 'number'
                 : (ks.input_kind === 'email') ? 'email'
                 : (ks.input_kind === 'url') ? 'url' : 'text';
        inp.placeholder = ks.placeholder || 'Type a reply…';
        const send = document.createElement('button');
        send.className = 'cv-btn cv-btn-primary';
        send.innerHTML = '<i class="fas fa-paper-plane"></i> Send';
        const row = document.createElement('div');
        row.className = 'cv-sim-row';
        row.appendChild(inp);
        row.appendChild(send);
        wrap.appendChild(row);
        const submit = () => {
            const v = (inp.value || '').trim();
            if (!v) return;
            const err = simValidateInput(step, v);
            if (err) { alert(err); return; }
            simAdvance(step, v, v, null);
        };
        send.addEventListener('click', submit);
        inp.addEventListener('keydown', e => { if (e.key === 'Enter') submit(); });
        setTimeout(() => inp.focus(), 30);
        return;
    }

    if (step.kind === 'rating') {
        const r = (step.settings && step.settings.rating) || { scale: 'star', min: 1, max: 5 };
        const min = parseInt(r.min ?? 1, 10);
        const max = parseInt(r.max ?? 5, 10);
        const chips = document.createElement('div');
        chips.className = 'cv-sim-quick';
        for (let i = min; i <= max; i++) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'cv-sim-chip';
            b.textContent = (r.scale === 'star') ? ('★'.repeat(i)) : String(i);
            b.addEventListener('click', () => simAdvance(step, i, `Rated ${i}`, null));
            chips.appendChild(b);
        }
        wrap.appendChild(chips);
        return;
    }

    if (step.kind === 'datetime') {
        const d = (step.settings && step.settings.datetime) || { mode: 'datetime' };
        const inp = document.createElement('input');
        inp.className = 'cv-input';
        inp.type = d.mode === 'date' ? 'date' : (d.mode === 'time' ? 'time' : 'datetime-local');
        if (d.min) inp.min = d.min;
        if (d.max) inp.max = d.max;
        const send = document.createElement('button');
        send.className = 'cv-btn cv-btn-primary';
        send.innerHTML = '<i class="fas fa-paper-plane"></i> Send';
        const row = document.createElement('div');
        row.className = 'cv-sim-row';
        row.appendChild(inp); row.appendChild(send);
        wrap.appendChild(row);
        send.addEventListener('click', () => {
            if (!inp.value) return;
            const v = inp.value;
            const t = Date.parse(v);
            if (isNaN(t)) { alert('Pick a valid date / time'); return; }
            if (d.min) { const mn = Date.parse(d.min); if (!isNaN(mn) && t < mn) { alert('Pick a later date / time'); return; } }
            if (d.max) { const mx = Date.parse(d.max); if (!isNaN(mx) && t > mx) { alert('Pick an earlier date / time'); return; } }
            simAdvance(step, v, v, null);
        });
        return;
    }

    if (step.kind === 'file_upload') {
        const note = document.createElement('div');
        note.className = 'cv-help';
        note.textContent = 'Simulator skips actual upload — pretend a file was attached.';
        const send = document.createElement('button');
        send.className = 'cv-btn cv-btn-primary';
        send.innerHTML = '<i class="fas fa-file-upload"></i> Pretend to upload';
        send.addEventListener('click', () => {
            const field = step.answer_field || step.key;
            sim.answers[field + '_url'] = 'https://example.test/uploaded.pdf';
            simAdvance(step, 'sample.pdf', '📎 sample.pdf', null);
        });
        wrap.appendChild(note);
        wrap.appendChild(send);
        return;
    }

    if (step.kind === 'ai_freetext') {
        const ai = (step.settings && step.settings.ai) || { intents: [], fallback_step_key: '' };
        const intents = ai.intents || [];
        const note = document.createElement('div');
        note.className = 'cv-help';
        note.innerHTML = 'AI classification is simulated — type a reply <em>and</em> pick the intent the model would resolve to.';
        wrap.appendChild(note);

        const inp = document.createElement('textarea');
        inp.className = 'cv-textarea';
        inp.rows = 2;
        inp.placeholder = 'Visitor reply…';
        wrap.appendChild(inp);

        const sel = document.createElement('select');
        sel.className = 'cv-select';
        sel.innerHTML = `<option value="__fallback__">↳ fallback (low confidence / no match)</option>`
            + intents.map(it => `<option value="${escapeAttr(it.value || '')}">→ ${escapeHtml(it.label || it.value || '?')}${it.next_step_key ? ' (→ ' + escapeHtml(it.next_step_key) + ')' : ''}</option>`).join('');
        wrap.appendChild(sel);

        const send = document.createElement('button');
        send.className = 'cv-btn cv-btn-primary';
        send.style.alignSelf = 'flex-start';
        send.innerHTML = '<i class="fas fa-paper-plane"></i> Classify & route';
        wrap.appendChild(send);

        send.addEventListener('click', () => {
            const text = (inp.value || '').trim();
            if (!text) return;
            const field = step.answer_field || step.key;
            sim.answers[field] = text;
            const picked = sel.value;
            let nextKey = step.next_step_key || null;
            let routeNote;
            if (picked === '__fallback__') {
                sim.answers[field + '_intent'] = '__fallback__';
                nextKey = ai.fallback_step_key || nextKey;
                routeNote = `AI fallback → ${nextKey || '(none)'}`;
            } else {
                const matched = intents.find(i => i.value === picked);
                sim.answers[field + '_intent'] = picked;
                if (matched && matched.next_step_key) nextKey = matched.next_step_key;
                routeNote = `AI intent "${picked}" → ${nextKey || '(none)'}`;
            }
            // Step-level conditions still apply, mirroring the controller.
            const conds = (step.settings && step.settings.conditions) || [];
            for (let i = 0; i < conds.length; i++) {
                if (simEvalCondition(conds[i]) && conds[i].goto) {
                    nextKey = conds[i].goto;
                    routeNote = `step condition #${i + 1} → ${nextKey}`;
                    break;
                }
            }
            simBubble('user', text, picked === '__fallback__' ? 'AI: fallback' : ('AI intent: ' + picked));
            simRenderTranscript();
            simRenderState();
            if (!nextKey) {
                simBubble('system', `Flow complete (${routeNote}).`, 'info');
                sim.done = true;
                simRenderTranscript();
                simRenderControls(null);
                return;
            }
            simEnter(nextKey, routeNote);
        });
        return;
    }

    wrap.innerHTML = `<div class="cv-empty" style="padding:0;">Unsupported step kind: ${escapeHtml(step.kind)}</div>`;
}

function simValidateInput(step, value) {
    const ks = step.settings || {};
    const v = ks.validation || {};
    const msg = v.error_message || null;
    const kind = ks.input_kind || 'text';
    if (kind === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) return msg || 'Please enter a valid email';
    if (kind === 'url')   { try { new URL(value); } catch { return msg || 'Please enter a valid URL'; } }
    if (kind === 'phone' && !/^[\d\s+\-()]{7,}$/.test(value)) return msg || 'Please enter a valid phone number';
    if (kind === 'number') {
        if (isNaN(parseFloat(value))) return msg || 'Please enter a number';
        if (v.min != null && parseFloat(value) < +v.min) return msg || `Must be at least ${v.min}`;
        if (v.max != null && parseFloat(value) > +v.max) return msg || `Must be at most ${v.max}`;
    }
    if (v.min_length != null && value.length < +v.min_length) return msg || `Must be at least ${v.min_length} characters`;
    if (v.max_length != null && value.length > +v.max_length) return msg || `Must be at most ${v.max_length} characters`;
    if (v.regex) {
        try { if (!(new RegExp(v.regex, 'u')).test(value)) return msg || "That doesn't look right"; } catch {}
    }
    return null;
}

function simRestart() {
    sim.answers = {};
    sim.currentKey = null;
    sim.history = [];
    sim.transcript = [];
    sim.done = false;

    const flowName = (document.getElementById('cv-name').value || '').trim();
    const intro = simRenderTemplate(document.getElementById('cv-intro').value || '');
    if (intro) simBubble('bot', intro, flowName ? `intro · ${flowName}` : 'intro');

    const entry = simEntryStep();
    if (!entry) {
        simBubble('system', 'No steps configured yet — add a step to start simulating.');
        simRenderTranscript();
        simRenderState();
        simRenderControls(null);
        return;
    }
    simEnter(entry.key, 'entry');
}

document.getElementById('cv-sim-restart').addEventListener('click', simRestart);
</script>
@endsection
