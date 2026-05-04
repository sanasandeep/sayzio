@extends('user.layouts.app')
@section('title', 'Conversational Flow - ' . ($link->title ?: $link->alias))
@section('breadcrumb_parent', 'Links')
@section('breadcrumb_parent_url', route('user.links.index'))
@section('content')
<style>
    .cv-builder { display: grid; grid-template-columns: 1fr 360px; gap: 20px; }
    @media (max-width: 1100px) { .cv-builder { grid-template-columns: 1fr; } }
    .cv-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px; margin-bottom: 14px; }
    .cv-step { border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; margin-bottom: 10px; background: #fafbff; }
    .cv-step-head { display: flex; gap: 8px; align-items: center; margin-bottom: 10px; }
    .cv-key { font-family: monospace; font-size: 12px; padding: 3px 8px; background: #ede9fe; color: #6d28d9; border-radius: 6px; }
    .cv-row { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 8px; }
    .cv-choice-row { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr auto; gap: 6px; align-items: center; margin-bottom: 6px; }
    .cv-action-row { display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 6px; align-items: center; margin-bottom: 6px; }
    .cv-pill { background: #8b5cf6; color: white; padding: 4px 10px; border-radius: 999px; font-size: 11px; }
    .cv-preview-frame { width: 100%; height: 600px; border: 1px solid #e2e8f0; border-radius: 12px; background: #0f172a; }
    .btn-mini { font-size: 12px; padding: 4px 8px; }
    .cv-toggle { display: flex; align-items: center; gap: 12px; padding: 14px 18px; background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%); color: white; border-radius: 12px; margin-bottom: 16px; }
    .cv-toggle .form-check-input { transform: scale(1.4); }
</style>

<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-1">Conversational Flow</h2>
            <div class="text-muted small">{{ $link->title ?: $link->alias }} · /{{ $link->alias }}</div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('user.links.blocks.editor', $link) }}" class="btn btn-outline-secondary btn-sm">Back to blocks</a>
            <a href="{{ route('user.links.conversational.analytics', $link) }}" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-chart-line"></i> Funnel analytics
            </a>
        </div>
    </div>

    <div class="cv-toggle">
        <div class="form-check form-switch m-0">
            <input type="checkbox" class="form-check-input" id="cv-mode-toggle"
                {{ data_get($link->settings, 'biolink.mode') === 'conversational' ? 'checked' : '' }}>
        </div>
        <div>
            <div class="fw-bold">Conversational mode</div>
            <div class="small opacity-75">When ON, visitors see this chat instead of the normal block list.</div>
        </div>
    </div>

    <div class="cv-builder">
        <div>
            <div class="cv-card">
                <h5>Flow basics</h5>
                <div class="mb-2">
                    <label class="form-label small">Flow name</label>
                    <input id="cv-name" class="form-control form-control-sm" value="{{ $flow->name }}">
                </div>
                <div class="mb-2">
                    <label class="form-label small">Opening line (sent before the first question)</label>
                    <textarea id="cv-intro" class="form-control form-control-sm" rows="2">{{ $flow->intro_message }}</textarea>
                </div>
                <div class="form-check form-switch">
                    <input id="cv-published" type="checkbox" class="form-check-input" {{ $flow->is_published ? 'checked' : '' }}>
                    <label for="cv-published" class="form-check-label small">Published — visitors will see this flow</label>
                </div>
            </div>

            <div class="cv-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="mb-0">Steps</h5>
                    <button id="cv-add-step" class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> Add step</button>
                </div>
                <div class="text-muted small mb-3">Each step is a chat bubble. Quick-reply questions branch the visitor to other steps. End steps fire an action.</div>
                <div id="cv-steps"></div>
            </div>

            <div class="cv-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="mb-0">End actions</h5>
                    <button id="cv-add-action" class="btn btn-sm btn-outline-primary btn-mini"><i class="fas fa-plus"></i> Add action</button>
                </div>
                <div class="text-muted small mb-2">Reusable end actions you can attach to a step or a quick-reply choice.</div>
                <div id="cv-actions"></div>
            </div>

            <div class="d-flex gap-2 mb-4">
                <button id="cv-save" class="btn btn-success"><i class="fas fa-save"></i> Save flow</button>
                <span id="cv-save-status" class="text-muted small align-self-center"></span>
            </div>
        </div>

        <div>
            <div class="cv-card sticky-top" style="top: 80px;">
                <h6>Live preview</h6>
                <div class="text-muted small mb-2">Save and publish to see real visitor behaviour.</div>
                <iframe class="cv-preview-frame" src="{{ $previewUrl }}" id="cv-preview"></iframe>
                <button class="btn btn-sm btn-outline-secondary mt-2 w-100" onclick="document.getElementById('cv-preview').src=document.getElementById('cv-preview').src">
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
            <select class="form-select form-select-sm cv-action-kind">
                ${Object.entries(ACTION_KINDS).map(([k, l]) => `<option value="${k}" ${a.kind===k?'selected':''}>${l}</option>`).join('')}
            </select>
            <input class="form-control form-control-sm cv-action-label" placeholder="Label" value="${escapeAttr(a.label||'')}">
            <input class="form-control form-control-sm cv-action-payload" placeholder="URL / text / block id"
                   value="${escapeAttr(payloadDisplay(a))}">
            <button class="btn btn-sm btn-outline-danger" data-rm="${idx}">×</button>
        `;
        wrap.appendChild(row);
        row.querySelector('.cv-action-kind').addEventListener('change', e => { actions[idx].kind = e.target.value; renderSteps(); });
        row.querySelector('.cv-action-label').addEventListener('input', e => { actions[idx].label = e.target.value; });
        row.querySelector('.cv-action-payload').addEventListener('input', e => { actions[idx].payload = payloadFromInput(actions[idx].kind, e.target.value); });
        row.querySelector('[data-rm]').addEventListener('click', () => { actions.splice(idx, 1); renderActions(); renderSteps(); });
    });
    if (!actions.length) wrap.innerHTML = '<div class="text-muted small">No actions yet.</div>';
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
        wrap.innerHTML = '<div class="text-muted small">No steps. Add one to get started.</div>';
        return;
    }
    const stepKeys = steps.map(s => s.key);
    steps.forEach((s, idx) => {
        const card = document.createElement('div');
        card.className = 'cv-step';
        card.innerHTML = `
            <div class="cv-step-head">
                <span class="cv-key">${escapeHtml(s.key)}</span>
                <select class="form-select form-select-sm cv-step-kind" style="max-width:240px;">
                    ${Object.entries(STEP_KINDS).map(([k, l]) => `<option value="${k}" ${s.kind===k?'selected':''}>${l}</option>`).join('')}
                </select>
                <label class="small ms-2"><input type="checkbox" class="cv-step-entry" ${s.is_entry?'checked':''}> Entry</label>
                <label class="small ms-2"><input type="checkbox" class="cv-step-skip" ${s.skip_if_known?'checked':''}> Skip if known</label>
                <button class="btn btn-sm btn-outline-danger ms-auto" data-rm-step="${idx}">×</button>
            </div>
            <div class="cv-row">
                <input class="form-control form-control-sm cv-step-keyinput" value="${escapeAttr(s.key)}" placeholder="step_key">
                <input class="form-control form-control-sm cv-step-field" value="${escapeAttr(s.answer_field||'')}" placeholder="answer field (e.g. intent)">
            </div>
            <textarea class="form-control form-control-sm cv-step-msg mb-2" rows="2" placeholder="Bot message">${escapeHtml(s.message_text||'')}</textarea>
            <div class="cv-row">
                <select class="form-select form-select-sm cv-step-next">
                    <option value="">— No next step (ends here) —</option>
                    ${stepKeys.filter(k => k !== s.key).map(k => `<option value="${k}" ${s.next_step_key===k?'selected':''}>→ ${k}</option>`).join('')}
                </select>
                <select class="form-select form-select-sm cv-step-action">
                    <option value="">— No action —</option>
                    ${actions.map(a => `<option value="${a.client_id}" ${s.action_client_id===a.client_id?'selected':''}>⚡ ${escapeHtml(a.label||a.kind)}</option>`).join('')}
                </select>
            </div>
            <div class="cv-input-wrap" style="${s.kind==='input'?'':'display:none;'}">
                <div class="cv-row mt-2">
                    <select class="form-select form-select-sm cv-step-inputkind" style="max-width:200px;">
                        <option value="text" ${(s.input_kind||'text')==='text'?'selected':''}>Free-text input</option>
                        <option value="email" ${s.input_kind==='email'?'selected':''}>Email capture</option>
                    </select>
                    <input class="form-control form-control-sm cv-step-placeholder" placeholder="Input placeholder (e.g. you@example.com)" value="${escapeAttr(s.placeholder||'')}">
                </div>
                <div class="small text-muted mt-1">Email-kind inputs are saved to your Subscribers and the visitor's Contact record.</div>
            </div>
            <div class="cv-choices-wrap" style="${s.kind==='question'?'':'display:none;'}">
                <div class="d-flex justify-content-between align-items-center mt-2 mb-1">
                    <strong class="small">Quick-reply choices</strong>
                    <button class="btn btn-sm btn-outline-primary btn-mini cv-add-choice"><i class="fas fa-plus"></i> Choice</button>
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
                    <input class="form-control form-control-sm" placeholder="Label" value="${escapeAttr(c.label||'')}">
                    <input class="form-control form-control-sm" placeholder="value" value="${escapeAttr(c.value||'')}">
                    <select class="form-select form-select-sm">
                        <option value="">— next step —</option>
                        ${stepKeys.filter(k => k !== s.key).map(k => `<option value="${k}" ${c.next_step_key===k?'selected':''}>→ ${k}</option>`).join('')}
                    </select>
                    <select class="form-select form-select-sm">
                        <option value="">— action —</option>
                        ${actions.map(a => `<option value="${a.client_id}" ${c.action_client_id===a.client_id?'selected':''}>⚡ ${escapeHtml(a.label||a.kind)}</option>`).join('')}
                    </select>
                    <button class="btn btn-sm btn-outline-danger">×</button>
                `;
                const inputs = row.querySelectorAll('input, select');
                inputs[0].addEventListener('input', e => c.label = e.target.value);
                inputs[1].addEventListener('input', e => c.value = e.target.value);
                inputs[2].addEventListener('change', e => c.next_step_key = e.target.value || null);
                inputs[3].addEventListener('change', e => c.action_client_id = e.target.value || null);
                row.querySelector('button').addEventListener('click', () => { s.choices.splice(ci, 1); rebuildChoices(); });
                choicesList.appendChild(row);
            });
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
    status.textContent = 'Saving…'; status.className = 'text-muted small align-self-center';
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
        if (!j.ok) { status.textContent = '❌ ' + (j.error || 'Save failed'); status.className = 'text-danger small align-self-center'; return; }
        status.textContent = '✓ Saved (v' + j.version + ')'; status.className = 'text-success small align-self-center';
        document.getElementById('cv-preview').src = document.getElementById('cv-preview').src;
    } catch (e) {
        status.textContent = '❌ Network error';
        status.className = 'text-danger small align-self-center';
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
