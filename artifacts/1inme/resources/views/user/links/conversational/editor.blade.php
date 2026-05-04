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
    .cv-card h5, .cv-card h6 {
        color: var(--text-primary);
        font-weight: 700;
        margin: 0;
    }
    .cv-card-title {
        display: flex; align-items: center; justify-content: space-between;
        gap: 12px; margin-bottom: 14px;
    }
    .cv-card-subtitle {
        color: var(--text-faint);
        font-size: 12px;
        margin-top: 2px;
    }

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
        display: block;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--text-muted);
        margin-bottom: 6px;
    }
    .cv-input, .cv-select, .cv-textarea {
        width: 100%;
        background: var(--bg-glass-input);
        border: 1px solid var(--border-glass);
        border-radius: 10px;
        color: var(--text-primary);
        font-size: 13px;
        padding: 9px 12px;
        line-height: 1.4;
        transition: border-color .15s ease, box-shadow .15s ease, background .15s ease;
    }
    .cv-textarea { resize: vertical; min-height: 60px; font-family: inherit; }
    .cv-input:focus, .cv-select:focus, .cv-textarea:focus {
        outline: none;
        border-color: rgba(139, 92, 246, 0.55);
        box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.15);
        background: var(--bg-card);
    }
    .cv-input::placeholder, .cv-textarea::placeholder { color: var(--text-faint); }
    .cv-select {
        appearance: none; -webkit-appearance: none;
        background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'><path fill='%2394a3b8' d='M0 0l5 6 5-6z'/></svg>");
        background-repeat: no-repeat;
        background-position: right 12px center;
        padding-right: 32px;
    }

    .cv-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px; }
    @media (max-width: 640px) { .cv-row { grid-template-columns: 1fr; } }

    .cv-checkbox-line {
        display: inline-flex; align-items: center; gap: 8px;
        font-size: 12px; color: var(--text-muted); cursor: pointer;
    }
    .cv-checkbox-line input[type="checkbox"] { accent-color: #8b5cf6; width: 14px; height: 14px; }

    .cv-step {
        border: 1px solid var(--border-glass);
        border-radius: 12px;
        padding: 14px;
        margin-bottom: 12px;
        background: var(--bg-glass-input);
    }
    .cv-step-head {
        display: flex; gap: 10px; align-items: center; flex-wrap: wrap;
        margin-bottom: 12px;
    }
    .cv-key {
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        font-size: 11px;
        padding: 4px 10px;
        background: rgba(139, 92, 246, 0.15);
        color: #a78bfa;
        border-radius: 999px;
        font-weight: 600;
        letter-spacing: 0.02em;
    }

    .cv-choice-row {
        display: grid;
        grid-template-columns: minmax(0,1.2fr) minmax(0,1fr) minmax(0,1.1fr) minmax(0,1.1fr) auto;
        gap: 8px; align-items: center; margin-bottom: 8px;
    }
    .cv-action-row {
        display: grid;
        grid-template-columns: minmax(0,1fr) minmax(0,1fr) minmax(0,1.4fr) auto;
        gap: 8px; align-items: center; margin-bottom: 8px;
    }
    @media (max-width: 900px) {
        .cv-choice-row { grid-template-columns: 1fr 1fr auto; }
        .cv-action-row { grid-template-columns: 1fr 1fr auto; }
    }

    .cv-btn {
        display: inline-flex; align-items: center; gap: 6px;
        font-size: 12px; font-weight: 600;
        padding: 8px 14px;
        border-radius: 10px;
        border: 1px solid transparent;
        cursor: pointer;
        transition: transform .15s ease, box-shadow .15s ease, background .15s ease, color .15s ease, border-color .15s ease;
        line-height: 1;
    }
    .cv-btn-primary {
        color: #fff;
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        box-shadow: 0 6px 18px -8px rgba(124,58,237,0.55);
    }
    .cv-btn-primary:hover { transform: translateY(-1px); box-shadow: 0 10px 22px -10px rgba(124,58,237,0.7); }
    .cv-btn-success {
        color: #fff;
        background: linear-gradient(135deg, #10b981, #059669);
        box-shadow: 0 6px 18px -8px rgba(16,185,129,0.5);
    }
    .cv-btn-success:hover { transform: translateY(-1px); }
    .cv-btn-ghost {
        color: var(--text-muted);
        background: var(--bg-glass-input);
        border-color: var(--border-glass);
    }
    .cv-btn-ghost:hover { color: var(--text-primary); background: var(--bg-glass-hover); }
    .cv-btn-outline {
        color: #a78bfa;
        background: transparent;
        border-color: rgba(139, 92, 246, 0.4);
    }
    .cv-btn-outline:hover { background: rgba(139, 92, 246, 0.1); }
    .cv-btn-danger {
        color: #ef4444;
        background: rgba(239, 68, 68, 0.08);
        border-color: rgba(239, 68, 68, 0.25);
        padding: 6px 10px;
        font-size: 14px;
        line-height: 1;
    }
    .cv-btn-danger:hover { background: rgba(239, 68, 68, 0.18); color: #fff; }

    .cv-empty { color: var(--text-faint); font-size: 12px; padding: 12px 0; text-align: center; }
    .cv-help  { color: var(--text-faint); font-size: 12px; margin-bottom: 10px; }

    .cv-preview-frame {
        width: 100%; height: 600px;
        border: 1px solid var(--border-glass);
        border-radius: 12px;
        background: #0f172a;
    }
    .cv-preview-card { position: sticky; top: 80px; }

    .cv-save-row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 24px; }
    .cv-save-status { font-size: 12px; color: var(--text-faint); }
    .cv-save-status.is-error { color: #ef4444; }
    .cv-save-status.is-ok { color: #10b981; }
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
                        <div class="cv-card-subtitle">Name your flow and set the opening line visitors see first.</div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="cv-field-label">Flow name</label>
                    <input id="cv-name" class="cv-input" value="{{ $flow->name }}">
                </div>
                <div class="mb-3">
                    <label class="cv-field-label">Opening line (sent before the first question)</label>
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
                        <div class="cv-card-subtitle">Each step is a chat bubble. Quick-reply questions branch the visitor; end steps fire an action.</div>
                    </div>
                    <button id="cv-add-step" class="cv-btn cv-btn-primary"><i class="fas fa-plus"></i> Add step</button>
                </div>
                <div id="cv-steps"></div>
            </div>

            <div class="cv-card">
                <div class="cv-card-title">
                    <div>
                        <h5>End actions</h5>
                        <div class="cv-card-subtitle">Reusable end actions you can attach to a step or a quick-reply choice.</div>
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
                <div class="cv-card-title">
                    <div>
                        <h6>Live preview</h6>
                        <div class="cv-card-subtitle">Save and publish to see real visitor behaviour.</div>
                    </div>
                </div>
                <iframe class="cv-preview-frame" src="{{ $previewUrl }}" id="cv-preview"></iframe>
                <button class="cv-btn cv-btn-ghost mt-2" style="width:100%; justify-content:center;" onclick="document.getElementById('cv-preview').src=document.getElementById('cv-preview').src">
                    <i class="fas fa-sync"></i> Reload preview
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const STEP_KINDS = @json($stepKinds);
const ACTION_KINDS = @json($actionKinds);
const BLOCK_OPTIONS = @json($blockOptions);
const FLOW = @json($flowPayload);

const URLS = {
    save:   @json(route('user.links.conversational.save', $link)),
    toggle: @json(route('user.links.conversational.toggle', $link)),
};
const CSRF = '{{ csrf_token() }}';

let actions = FLOW.actions.map(a => Object.assign({}, a));
let steps = FLOW.steps.map(s => Object.assign({}, s, { choices: (s.choices||[]).map(c => Object.assign({}, c)) }));
let actionCounter = actions.length + 1;

function newKey(prefix) {
    let i = 1; let k;
    do { k = prefix + '_' + i; i++; } while (steps.some(s => s.key === k));
    return k;
}

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
            <input class="cv-input cv-action-payload" placeholder="URL / text / block id"
                   value="${escapeAttr(payloadDisplay(a))}">
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

function renderSteps() {
    const wrap = document.getElementById('cv-steps');
    wrap.innerHTML = '';
    if (!steps.length) {
        wrap.innerHTML = '<div class="cv-empty">No steps yet. Add one to get started.</div>';
        return;
    }
    const stepKeys = steps.map(s => s.key);
    steps.forEach((s, idx) => {
        const card = document.createElement('div');
        card.className = 'cv-step';
        card.innerHTML = `
            <div class="cv-step-head">
                <span class="cv-key">${escapeHtml(s.key)}</span>
                <select class="cv-select cv-step-kind" style="max-width:200px;">
                    ${Object.entries(STEP_KINDS).map(([k, l]) => `<option value="${k}" ${s.kind===k?'selected':''}>${l}</option>`).join('')}
                </select>
                <label class="cv-checkbox-line"><input type="checkbox" class="cv-step-entry" ${s.is_entry?'checked':''}> Entry</label>
                <label class="cv-checkbox-line"><input type="checkbox" class="cv-step-skip" ${s.skip_if_known?'checked':''}> Skip if known</label>
                <button class="cv-btn cv-btn-danger ms-auto" data-rm-step="${idx}" title="Remove step">×</button>
            </div>
            <div class="cv-row">
                <div>
                    <label class="cv-field-label">Step key</label>
                    <input class="cv-input cv-step-keyinput" value="${escapeAttr(s.key)}" placeholder="step_key">
                </div>
                <div>
                    <label class="cv-field-label">Answer field</label>
                    <input class="cv-input cv-step-field" value="${escapeAttr(s.answer_field||'')}" placeholder="e.g. intent">
                </div>
            </div>
            <label class="cv-field-label">Bot message</label>
            <textarea class="cv-textarea cv-step-msg mb-2" rows="2" placeholder="Bot message">${escapeHtml(s.message_text||'')}</textarea>
            <div class="cv-row">
                <div>
                    <label class="cv-field-label">Next step</label>
                    <select class="cv-select cv-step-next">
                        <option value="">— No next step (ends here) —</option>
                        ${stepKeys.filter(k => k !== s.key).map(k => `<option value="${k}" ${s.next_step_key===k?'selected':''}>→ ${k}</option>`).join('')}
                    </select>
                </div>
                <div>
                    <label class="cv-field-label">Action on completion</label>
                    <select class="cv-select cv-step-action">
                        <option value="">— No action —</option>
                        ${actions.map(a => `<option value="${a.client_id}" ${s.action_client_id===a.client_id?'selected':''}>⚡ ${escapeHtml(a.label||a.kind)}</option>`).join('')}
                    </select>
                </div>
            </div>
            <div class="cv-input-wrap" style="${s.kind==='input'?'':'display:none;'}">
                <div class="cv-row mt-2">
                    <div>
                        <label class="cv-field-label">Input kind</label>
                        <select class="cv-select cv-step-inputkind">
                            <option value="text" ${(s.input_kind||'text')==='text'?'selected':''}>Free-text input</option>
                            <option value="email" ${s.input_kind==='email'?'selected':''}>Email capture</option>
                        </select>
                    </div>
                    <div>
                        <label class="cv-field-label">Placeholder</label>
                        <input class="cv-input cv-step-placeholder" placeholder="e.g. you@example.com" value="${escapeAttr(s.placeholder||'')}">
                    </div>
                </div>
                <div class="cv-help mt-1">Email-kind inputs are saved to your Subscribers and the visitor's Contact record.</div>
            </div>
            <div class="cv-choices-wrap" style="${s.kind==='question'?'':'display:none;'}">
                <div class="d-flex justify-content-between align-items-center mt-3 mb-2">
                    <strong style="font-size:12px; color: var(--text-primary);">Quick-reply choices</strong>
                    <button class="cv-btn cv-btn-outline cv-add-choice"><i class="fas fa-plus"></i> Choice</button>
                </div>
                <div class="cv-choices-list"></div>
            </div>
        `;
        wrap.appendChild(card);

        const choicesList = card.querySelector('.cv-choices-list');
        function rebuildChoices() {
            choicesList.innerHTML = '';
            (s.choices || []).forEach((c, ci) => {
                const row = document.createElement('div');
                row.className = 'cv-choice-row';
                row.innerHTML = `
                    <input class="cv-input" placeholder="Label" value="${escapeAttr(c.label||'')}">
                    <input class="cv-input" placeholder="value" value="${escapeAttr(c.value||'')}">
                    <select class="cv-select">
                        <option value="">— next step —</option>
                        ${stepKeys.filter(k => k !== s.key).map(k => `<option value="${k}" ${c.next_step_key===k?'selected':''}>→ ${k}</option>`).join('')}
                    </select>
                    <select class="cv-select">
                        <option value="">— action —</option>
                        ${actions.map(a => `<option value="${a.client_id}" ${c.action_client_id===a.client_id?'selected':''}>⚡ ${escapeHtml(a.label||a.kind)}</option>`).join('')}
                    </select>
                    <button class="cv-btn cv-btn-danger" title="Remove choice">×</button>
                `;
                const inputs = row.querySelectorAll('input, select');
                inputs[0].addEventListener('input', e => c.label = e.target.value);
                inputs[1].addEventListener('input', e => c.value = e.target.value);
                inputs[2].addEventListener('change', e => c.next_step_key = e.target.value || null);
                inputs[3].addEventListener('change', e => c.action_client_id = e.target.value || null);
                row.querySelector('button').addEventListener('click', () => { s.choices.splice(ci, 1); rebuildChoices(); });
                choicesList.appendChild(row);
            });
            if (!(s.choices || []).length) {
                choicesList.innerHTML = '<div class="cv-empty">No choices yet.</div>';
            }
        }
        rebuildChoices();

        card.querySelector('.cv-add-choice').addEventListener('click', () => {
            s.choices = s.choices || [];
            s.choices.push({ label: 'New choice', value: 'choice_' + (s.choices.length + 1), next_step_key: null, action_client_id: null });
            rebuildChoices();
        });
        card.querySelector('.cv-step-kind').addEventListener('change', e => {
            s.kind = e.target.value;
            card.querySelector('.cv-choices-wrap').style.display = s.kind === 'question' ? '' : 'none';
            card.querySelector('.cv-input-wrap').style.display   = s.kind === 'input'    ? '' : 'none';
        });
        card.querySelector('.cv-step-inputkind').addEventListener('change', e => s.input_kind = e.target.value);
        card.querySelector('.cv-step-placeholder').addEventListener('input', e => s.placeholder = e.target.value || null);
        card.querySelector('.cv-step-entry').addEventListener('change', e => {
            if (e.target.checked) {
                steps.forEach(o => o.is_entry = false);
                s.is_entry = true;
                renderSteps();
            } else {
                s.is_entry = false;
            }
        });
        card.querySelector('.cv-step-skip').addEventListener('change', e => s.skip_if_known = e.target.checked);
        card.querySelector('.cv-step-keyinput').addEventListener('change', e => {
            const newK = (e.target.value || '').toLowerCase().replace(/[^a-z0-9_]/g, '_');
            if (!newK || steps.some((o, oi) => oi !== idx && o.key === newK)) { e.target.value = s.key; return; }
            s.key = newK;
            renderSteps();
        });
        card.querySelector('.cv-step-field').addEventListener('input', e => s.answer_field = e.target.value || null);
        card.querySelector('.cv-step-msg').addEventListener('input', e => s.message_text = e.target.value);
        card.querySelector('.cv-step-next').addEventListener('change', e => s.next_step_key = e.target.value || null);
        card.querySelector('.cv-step-action').addEventListener('change', e => s.action_client_id = e.target.value || null);
        card.querySelector('[data-rm-step]').addEventListener('click', () => { steps.splice(idx, 1); renderSteps(); });
    });
}

function escapeHtml(s) { return String(s ?? '').replace(/[&<>]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c])); }
function escapeAttr(s) { return String(s ?? '').replace(/"/g, '&quot;'); }

document.getElementById('cv-add-step').addEventListener('click', () => {
    steps.push({
        key: newKey('step'), kind: 'question', message_text: 'New question?',
        answer_field: null, is_entry: false, skip_if_known: true,
        next_step_key: null, action_client_id: null,
        input_kind: 'text', placeholder: null,
        choices: [],
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
        actions: actions,
        steps: steps,
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
        status.textContent = '❌ Network error';
        status.className = 'cv-save-status is-error';
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
</script>
@endsection
