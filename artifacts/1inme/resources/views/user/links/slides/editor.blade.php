@extends('user.layouts.app')
@section('title', 'Slides Mode - ' . ($link->title ?: $link->alias))
@section('breadcrumb_parent', 'Links')
@section('breadcrumb_parent_url', route('user.links.index'))
@section('content')
<style>
    .sl-builder { display: grid; grid-template-columns: minmax(0, 1fr) minmax(320px, 420px); gap: 24px; align-items: start; }
    @media (max-width: 1100px) { .sl-builder { grid-template-columns: minmax(0, 1fr); } }
    /* Per-slide background panel — same option-set as the page background
       card but inline with each slide. Each block is a stack of small inputs
       that swap in via JS when the bg type changes. */
    .sl-bg-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(46px, 1fr)); gap: 6px; max-height: 220px; overflow-y: auto; padding: 4px; background: var(--bg-glass-input); border: 1px solid var(--border-glass); border-radius: 8px; }
    .sl-bg-tpl  { aspect-ratio: 9/14; border-radius: 6px; cursor: pointer; border: 1px solid var(--border-glass); position: relative; transition: transform .15s ease, box-shadow .15s ease; overflow: hidden; }
    .sl-bg-tpl:hover { transform: scale(1.06); z-index: 2; box-shadow: 0 4px 12px rgba(0,0,0,.4); }
    .sl-bg-tpl.is-selected { box-shadow: 0 0 0 2px #a78bfa, 0 4px 12px rgba(0,0,0,.4); }
    .sl-bg-tpl-name { position: absolute; bottom: 0; left: 0; right: 0; font-size: 9px; padding: 1px 3px; background: rgba(0,0,0,.55); color: #fff; text-align: center; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .sl-bg-list { display: flex; flex-direction: column; gap: 6px; }
    .sl-bg-list-row { display: flex; gap: 6px; }
    .sl-bg-list-row input { flex: 1; }
    .sl-bg-list-row .sl-btn { padding: 4px 8px; font-size: 12px; }

    .sl-card {
        background: var(--bg-card);
        border: 1px solid var(--border-glass);
        border-radius: 1rem;
        padding: 18px;
        margin-bottom: 16px;
        backdrop-filter: blur(20px);
    }
    .sl-card h5 { color: var(--text-primary); font-weight: 700; margin: 0 0 10px; }

    .sl-toggle {
        display: flex; align-items: center; gap: 14px;
        padding: 16px 20px; color: #fff;
        background: linear-gradient(135deg, #ec4899 0%, #8b5cf6 100%);
        border-radius: 1rem; margin-bottom: 18px;
        box-shadow: 0 10px 30px -12px rgba(139,92,246,0.55);
    }
    .sl-toggle .form-check-input { transform: scale(1.4); cursor: pointer; }
    .sl-toggle-title { font-weight: 700; font-size: 14px; }
    .sl-toggle-sub   { font-size: 12px; opacity: 0.85; }

    .sl-field-label {
        display: block; font-size: 11px; font-weight: 600;
        text-transform: uppercase; letter-spacing: 0.04em;
        color: var(--text-muted); margin-bottom: 6px;
    }
    .sl-input, .sl-select {
        width: 100%;
        background: var(--bg-glass-input);
        border: 1px solid var(--border-glass);
        border-radius: 10px;
        color: var(--text-primary);
        padding: 9px 12px; font-size: 14px;
    }
    .sl-row { display: flex; gap: 10px; flex-wrap: wrap; }
    .sl-row > * { flex: 1; min-width: 140px; }

    .sl-list { display: flex; flex-direction: column; gap: 12px; }
    .sl-slide-card {
        background: var(--bg-card-alt, rgba(255,255,255,0.04));
        border: 1px solid var(--border-glass);
        border-radius: 12px; padding: 14px;
    }
    .sl-slide-head {
        display: flex; align-items: center; justify-content: space-between;
        gap: 10px; margin-bottom: 10px;
    }
    .sl-slide-idx {
        background: #8b5cf6; color: #fff; border-radius: 999px;
        min-width: 28px; height: 28px; display: inline-flex;
        align-items: center; justify-content: center;
        font-weight: 700; font-size: 13px;
    }

    .sl-btn { background: rgba(139,92,246,0.18); color: #c4b5fd;
        border: 1px solid rgba(139,92,246,0.4); padding: 7px 12px;
        border-radius: 8px; font-size: 13px; cursor: pointer;
        transition: background 0.15s; }
    .sl-btn:hover { background: rgba(139,92,246,0.32); }
    .sl-btn-primary { background: #7c3aed; color: white; border-color: #7c3aed; }
    .sl-btn-primary:hover { background: #6d28d9; }
    .sl-btn-danger  { background: rgba(239,68,68,0.18); color: #fca5a5;
        border-color: rgba(239,68,68,0.4); }
    .sl-btn-ghost   { background: transparent; color: var(--text-muted);
        border: 1px solid var(--border-glass); }

    /* Light-mode contrast fixes — the chip/button colors above were tuned
       for the dark canvas and become invisible on a white surface. */
    html.light-mode .sl-btn {
        background: rgba(124,58,237,0.10);
        color: #5b21b6;
        border-color: rgba(124,58,237,0.30);
    }
    html.light-mode .sl-btn:hover { background: rgba(124,58,237,0.18); }
    html.light-mode .sl-btn-primary { background: #7c3aed; color: #fff; border-color: #7c3aed; }
    html.light-mode .sl-btn-primary:hover { background: #6d28d9; }
    html.light-mode .sl-btn-danger  { background: #fde7ec; color: #b91c1c; border-color: #fca5a5; }
    html.light-mode .sl-btn-ghost   { color: var(--text-secondary); }

    .sl-block-chip {
        display: inline-flex; align-items: center; gap: 6px;
        background: rgba(139,92,246,0.18); color: var(--text-primary);
        border: 1px solid rgba(139,92,246,0.35);
        border-radius: 999px; padding: 4px 10px;
        font-size: 12px; margin: 0 4px 4px 0; font-weight: 600;
    }
    .sl-block-chip button {
        background: transparent; border: 0; color: #ef4444;
        font-size: 14px; cursor: pointer; padding: 0; line-height: 1;
    }
    html.light-mode .sl-block-chip {
        background: #f3eeff; color: #5b21b6; border-color: #d8c3ff;
    }
    html.light-mode .sl-block-chip button { color: #dc2626; }

    .sl-empty { color: var(--text-faint); font-size: 13px; padding: 8px 0; }
    html.light-mode .sl-empty { color: var(--text-dimmed); }

    /* Width selector — same 6-stop pattern as biolink-editor (3,4,6,8,9,12). */
    .sl-span-row { display: flex; align-items: center; gap: 8px; flex-wrap: nowrap; }
    .sl-span-row > .sl-span-label {
        font-size: 10px; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.05em; color: var(--text-faint); flex-shrink: 0;
    }
    .sl-span-btns { display: flex; gap: 3px; flex: 1; }
    .sl-span-btn {
        flex: 1; min-width: 28px;
        font-size: 11px; font-weight: 700; padding: 4px 6px; border-radius: 6px;
        background: var(--bg-glass-input); border: 1px solid var(--border-glass);
        color: var(--text-muted); cursor: pointer; transition: all .12s ease;
    }
    .sl-span-btn:hover { color: var(--text-primary); border-color: rgba(124,58,237,0.5); }
    .sl-span-btn.active {
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        color: #fff; border-color: transparent;
        box-shadow: 0 2px 8px -2px rgba(124,58,237,0.55);
    }

    /* Per-block compact row — the cluster of Anim/Delay/Align/Width below each chip. */
    .sl-block-row {
        display: flex; flex-direction: column; gap: 6px;
        background: var(--bg-glass-input); border: 1px solid var(--border-glass);
        border-radius: 10px; padding: 8px 10px; margin-bottom: 6px;
    }
    .sl-block-row-top { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .sl-block-row-controls {
        display: grid; grid-template-columns: repeat(3, minmax(0,1fr));
        gap: 6px; align-items: center;
    }
    .sl-block-row-controls > label {
        font-size: 10px; font-weight: 600; color: var(--text-muted);
        display: flex; flex-direction: column; gap: 2px;
    }
    .sl-block-row-controls .sl-select,
    .sl-block-row-controls .sl-input { padding: 4px 6px; font-size: 11px; border-radius: 6px; }

    /* Autosave status pill — top-right of the Slides card. */
    .sl-autosave {
        display: inline-flex; align-items: center; gap: 6px;
        font-size: 11px; font-weight: 600; color: var(--text-faint);
        padding: 4px 10px; border-radius: 999px;
        background: var(--bg-glass-input); border: 1px solid var(--border-glass);
        transition: opacity .25s ease, color .15s ease;
    }
    .sl-autosave .dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
    .sl-autosave.is-saving { color: #f59e0b; }
    .sl-autosave.is-saved  { color: #10b981; }
    .sl-autosave.is-error  { color: #ef4444; }
    .sl-autosave.is-idle   { opacity: 0; pointer-events: none; }

    /* "Advanced" disclosure — keeps secondary fields out of the way. */
    .sl-advanced { margin-top: 10px; }
    .sl-advanced > summary {
        cursor: pointer; list-style: none; user-select: none;
        font-size: 11px; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.05em; color: var(--text-muted); padding: 4px 0;
        display: inline-flex; align-items: center; gap: 6px;
    }
    .sl-advanced > summary::-webkit-details-marker { display: none; }
    .sl-advanced > summary::before {
        content: '\f105'; font-family: 'Font Awesome 6 Free'; font-weight: 900;
        font-size: 10px; transition: transform .15s ease;
    }
    .sl-advanced[open] > summary::before { transform: rotate(90deg); }
    .sl-advanced > summary:hover { color: var(--text-primary); }
    .sl-advanced > .sl-row { margin-top: 8px; }

    .sl-preview-wrap {
        background: rgba(0,0,0,0.35); border-radius: 18px;
        padding: 14px; position: sticky; top: 12px;
    }
    .sl-preview-frame {
        width: 100%; aspect-ratio: 9 / 19.5; max-height: 720px;
        border: 0; border-radius: 28px; background: #000;
        box-shadow: 0 12px 40px -8px rgba(0,0,0,0.6);
    }

    .sl-status-pill {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 4px 10px; border-radius: 999px; font-size: 11px;
        font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;
    }
    .sl-status-pill.draft { background: rgba(234,179,8,0.15); color: #facc15; }
    .sl-status-pill.live  { background: rgba(34,197,94,0.18); color: #4ade80; }

    .sl-actions-bar {
        display: flex; gap: 10px; justify-content: flex-end;
        margin-top: 16px;
    }

    .sl-deck-bg {
        width: 30px; height: 30px; border-radius: 8px; border: 1px solid var(--border-glass);
        display: inline-block; vertical-align: middle;
    }
</style>

@include('user.links.partials.editor-header', ['link' => $link, 'activeMainTab' => 'slides'])

@include('user.links.partials.mode-selector', ['link' => $link])

<div class="sl-builder">
    <div>
        <div class="sl-card">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                <h5>Deck settings</h5>
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <span id="sl-autosave" class="sl-autosave is-idle"><span class="dot"></span><span class="label">Saved</span></span>
                    <a href="{{ route('user.links.slides.analytics', $link) }}" class="sl-btn" title="View slide analytics">
                        <i class="fas fa-chart-bar"></i> Analytics
                    </a>
                    <span id="sl-status-pill" class="sl-status-pill {{ $deckPayload['is_published'] ? 'live' : 'draft' }}">
                        {{ $deckPayload['is_published'] ? 'Published v'.$deckPayload['version'] : 'Draft' }}
                    </span>
                </div>
            </div>
            <div class="sl-row" style="margin-top:10px;">
                <div>
                    <label class="sl-field-label">Default slide background</label>
                    <input type="color" id="sl-theme-bg"  class="sl-input" value="{{ $deckPayload['settings']['theme']['background'] ?? '#0f172a' }}">
                </div>
                <div>
                    <label class="sl-field-label">Text color</label>
                    <input type="color" id="sl-theme-text" class="sl-input" value="{{ $deckPayload['settings']['theme']['text'] ?? '#f8fafc' }}">
                </div>
            </div>

            <details class="sl-advanced">
                <summary>Advanced</summary>
                <div class="sl-row">
                    <div>
                        <label class="sl-field-label">Accent</label>
                        <input type="color" id="sl-theme-acc" class="sl-input" value="{{ $deckPayload['settings']['theme']['accent'] ?? '#8b5cf6' }}">
                    </div>
                    <div>
                        <label class="sl-field-label">Default transition</label>
                        <select id="sl-transition" class="sl-select">
                            @foreach(['slide'=>'Slide','fade'=>'Fade','zoom'=>'Zoom','flip'=>'Flip','none'=>'None'] as $k=>$v)
                                <option value="{{ $k }}" {{ ($deckPayload['settings']['transition'] ?? 'slide')===$k?'selected':'' }}>{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="sl-row">
                    <div>
                        <label class="sl-field-label">Auto-advance (ms, 0=off)</label>
                        <input id="sl-auto" class="sl-input" type="number" min="0" max="60000" step="500" value="{{ (int)($deckPayload['settings']['auto_advance'] ?? 0) }}">
                    </div>
                    <div>
                        <label class="sl-field-label">Loop</label>
                        <select id="sl-loop" class="sl-select">
                            <option value="0" {{ empty($deckPayload['settings']['loop'])?'selected':'' }}>Off</option>
                            <option value="1" {{ !empty($deckPayload['settings']['loop'])?'selected':'' }}>On</option>
                        </select>
                    </div>
                </div>
            </details>
        </div>

        <div class="sl-card">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
                <h5>Slides</h5>
                <button type="button" class="sl-btn sl-btn-primary" id="sl-add-slide">+ Add slide</button>
            </div>
            <p style="margin: 8px 0 0; font-size: 12px; color: var(--text-muted);">
                Changes save automatically. Click <strong>Publish</strong> below to push them live.
            </p>
            <div id="sl-slides" class="sl-list" style="margin-top:14px;"></div>
        </div>

        <div class="sl-actions-bar">
            <button type="button" class="sl-btn sl-btn-primary" id="sl-publish">
                <i class="fas fa-rocket text-[10px] mr-1"></i> Publish changes
            </button>
        </div>
    </div>

    <div>
        @include('user.links.partials.device-preview', ['link' => $link])
    </div>
</div>

<script>
const DECK = @json($deckPayload);
const BLOCKS = @json($blockOptions);
const URLS = {
    save:    @json(route('user.links.slides.save', $link)),
    toggle:  @json(route('user.links.slides.toggle', $link)),
    preview: @json($previewUrl),
};
const CSRF = '{{ csrf_token() }}';

let slides = (DECK.slides || []).map(s => Object.assign({}, s, {
    block_ids: Array.isArray(s.block_ids) ? s.block_ids.slice() : [],
    block_settings: (s.block_settings && typeof s.block_settings === 'object') ? Object.assign({}, s.block_settings) : {},
    background: Object.assign({type:'color', color:'#0f172a'}, s.background || {}),
    animation:  Object.assign({enter:'fade', duration_ms:400}, s.animation || {}),
}));
let isPublished = !!DECK.is_published;
let version     = DECK.version || 1;

const ENTERS = ['fade','slide_up','slide_down','slide_left','slide_right','zoom','flip','none'];
const TRANS  = ['slide','fade','zoom','flip','none'];
const SPANS  = [{v:3,l:'¼'},{v:4,l:'⅓'},{v:6,l:'½'},{v:8,l:'⅔'},{v:9,l:'¾'},{v:12,l:'Full'}];
// Background templates served from the bg_templates catalog. Each entry is
// { id, name, slug, preview_color, category } — we render thumbnails using
// preview_color as a flat colour swatch since we don't load the template's
// full CSS into the editor (the public renderer applies that).
const BG_TEMPLATES = @json($bgTemplates);

function escAttr(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

function blockLabel(id) {
    const b = BLOCKS.find(x => x.id === id);
    if (!b) return '#' + id + ' (missing)';
    return (b.label ? (b.label + ' · ') : '') + b.type;
}

// Builds the per-slide background editor. Mirrors the option-set of the
// page-background card (color/gradient/image/slideshow/video/template) but
// keeps everything inside the slide JSON so each slide carries its own
// treatment with no extra endpoint round-trip.
function renderSlideBgFields(wrap, i) {
    const bg = slides[i].background;
    const t  = bg.type || 'color';
    if (t === 'color') {
        wrap.innerHTML = `
            <div class="sl-row"><div><label class="sl-field-label">Background colour</label>
                <input type="color" class="sl-input sl-bg-color" value="${bg.color || '#0f172a'}"></div></div>`;
        wrap.querySelector('.sl-bg-color').addEventListener('input', e => { slides[i].background.color = e.target.value; scheduleAutoSave(); });
    } else if (t === 'gradient') {
        wrap.innerHTML = `
            <div class="sl-row">
                <div><label class="sl-field-label">From</label>
                    <input type="color" class="sl-input sl-bg-from" value="${bg.from_color || '#1e293b'}"></div>
                <div><label class="sl-field-label">To</label>
                    <input type="color" class="sl-input sl-bg-to" value="${bg.to_color || '#0f172a'}"></div>
            </div>`;
        wrap.querySelector('.sl-bg-from').addEventListener('input', e => { slides[i].background.from_color = e.target.value; scheduleAutoSave(); });
        wrap.querySelector('.sl-bg-to'  ).addEventListener('input', e => { slides[i].background.to_color   = e.target.value; scheduleAutoSave(); });
    } else if (t === 'image') {
        wrap.innerHTML = `
            <label class="sl-field-label">Image URL</label>
            <input class="sl-input sl-bg-url" value="${escAttr(bg.image_url || '')}" placeholder="https://…">`;
        wrap.querySelector('.sl-bg-url').addEventListener('input', e => { slides[i].background.image_url = e.target.value; scheduleAutoSave(); });
    } else if (t === 'slideshow') {
        const imgs = Array.isArray(bg.images) ? bg.images : [];
        const rows = imgs.length ? imgs : [''];
        wrap.innerHTML = `
            <label class="sl-field-label">Image URLs (cycles automatically)</label>
            <div class="sl-bg-list">
                ${rows.map((u, k) => `<div class="sl-bg-list-row"><input class="sl-input sl-bg-img" data-k="${k}" value="${escAttr(u)}" placeholder="https://…"><button type="button" class="sl-btn sl-btn-danger" data-rm-img="${k}">×</button></div>`).join('')}
            </div>
            <button type="button" class="sl-btn" style="margin-top:6px;" data-add-img>+ Add image</button>
            <div class="sl-row" style="margin-top:8px;">
                <div><label class="sl-field-label">Interval (ms)</label>
                    <input type="number" min="500" max="30000" step="100" class="sl-input sl-bg-interval" value="${bg.interval_ms || 3500}"></div>
            </div>`;
        wrap.querySelectorAll('.sl-bg-img').forEach(el => el.addEventListener('input', e => {
            slides[i].background.images = slides[i].background.images || [];
            const k = parseInt(el.dataset.k, 10);
            slides[i].background.images[k] = e.target.value;
            scheduleAutoSave();
        }));
        wrap.querySelectorAll('[data-rm-img]').forEach(el => el.addEventListener('click', () => {
            const k = parseInt(el.dataset.rmImg, 10);
            slides[i].background.images = (slides[i].background.images || []).filter((_, j) => j !== k);
            renderSlideBgFields(wrap, i); scheduleAutoSave();
        }));
        wrap.querySelector('[data-add-img]').addEventListener('click', () => {
            slides[i].background.images = (slides[i].background.images || []).concat(['']);
            renderSlideBgFields(wrap, i); scheduleAutoSave();
        });
        wrap.querySelector('.sl-bg-interval').addEventListener('input', e => { slides[i].background.interval_ms = parseInt(e.target.value, 10) || 3500; scheduleAutoSave(); });
    } else if (t === 'video') {
        wrap.innerHTML = `
            <label class="sl-field-label">Video URL (mp4 / webm)</label>
            <input class="sl-input sl-bg-vurl" value="${escAttr(bg.video_url || '')}" placeholder="https://…">
            <div class="sl-row" style="margin-top:8px;">
                <label class="sl-field-label" style="display:flex;align-items:center;gap:6px;font-size:12px;text-transform:none;letter-spacing:0;"><input type="checkbox" class="sl-bg-vauto" ${bg.video_autoplay !== false ? 'checked' : ''}> Autoplay</label>
                <label class="sl-field-label" style="display:flex;align-items:center;gap:6px;font-size:12px;text-transform:none;letter-spacing:0;"><input type="checkbox" class="sl-bg-vmute" ${bg.video_muted !== false ? 'checked' : ''}> Muted</label>
                <label class="sl-field-label" style="display:flex;align-items:center;gap:6px;font-size:12px;text-transform:none;letter-spacing:0;"><input type="checkbox" class="sl-bg-vloop" ${bg.video_loop !== false ? 'checked' : ''}> Loop</label>
            </div>`;
        wrap.querySelector('.sl-bg-vurl' ).addEventListener('input',  e => { slides[i].background.video_url      = e.target.value;   scheduleAutoSave(); });
        wrap.querySelector('.sl-bg-vauto').addEventListener('change', e => { slides[i].background.video_autoplay = e.target.checked; scheduleAutoSave(); });
        wrap.querySelector('.sl-bg-vmute').addEventListener('change', e => { slides[i].background.video_muted    = e.target.checked; scheduleAutoSave(); });
        wrap.querySelector('.sl-bg-vloop').addEventListener('change', e => { slides[i].background.video_loop     = e.target.checked; scheduleAutoSave(); });
    } else if (t === 'template') {
        const sel = bg.template_id || null;
        wrap.innerHTML = `
            <label class="sl-field-label">Template (${BG_TEMPLATES.length})</label>
            <div class="sl-bg-grid">
                ${BG_TEMPLATES.map(tpl => `
                    <div class="sl-bg-tpl ${sel == tpl.id ? 'is-selected' : ''}" data-tpl="${tpl.id}" title="${escAttr(tpl.name)}" style="background:${escAttr(tpl.preview_color || '#0f172a')};">
                        <div class="sl-bg-tpl-name">${escAttr(tpl.name)}</div>
                    </div>
                `).join('')}
            </div>
            ${BG_TEMPLATES.length === 0 ? '<p class="sl-empty" style="margin-top:6px;">No templates available yet.</p>' : ''}`;
        wrap.querySelectorAll('.sl-bg-tpl').forEach(el => el.addEventListener('click', () => {
            slides[i].background.template_id = parseInt(el.dataset.tpl, 10);
            wrap.querySelectorAll('.sl-bg-tpl').forEach(x => x.classList.toggle('is-selected', x === el));
            scheduleAutoSave();
        }));
    }
}

function renderSlides() {
    const wrap = document.getElementById('sl-slides');
    wrap.innerHTML = '';
    if (!slides.length) {
        wrap.innerHTML = '<div class="sl-empty">No slides yet. Click "Add slide" to start.</div>';
        return;
    }
    slides.forEach((s, i) => {
        const card = document.createElement('div');
        card.className = 'sl-slide-card';
        card.innerHTML = `
            <div class="sl-slide-head">
                <div style="display:flex;align-items:center;gap:10px;">
                    <span class="sl-slide-idx">${i+1}</span>
                    <input class="sl-input sl-title" placeholder="Slide title (optional)"
                           value="${escAttr(s.title || '')}" style="min-width:200px;">
                </div>
                <div style="display:flex;gap:6px;">
                    <button type="button" class="sl-btn" data-act="up"   ${i===0?'disabled style="opacity:.4;"':''}>↑</button>
                    <button type="button" class="sl-btn" data-act="down" ${i===slides.length-1?'disabled style="opacity:.4;"':''}>↓</button>
                    <button type="button" class="sl-btn" data-act="dup" title="Duplicate slide">⎘</button>
                    <button type="button" class="sl-btn sl-btn-danger" data-act="rm">×</button>
                </div>
            </div>

            <div>
                <label class="sl-field-label">Slide background</label>
                <select class="sl-select sl-bg-type" style="max-width:220px;">
                    <option value="color"     ${s.background.type==='color'?'selected':''}>Color</option>
                    <option value="gradient"  ${s.background.type==='gradient'?'selected':''}>Gradient</option>
                    <option value="image"     ${s.background.type==='image'?'selected':''}>Image</option>
                    <option value="slideshow" ${s.background.type==='slideshow'?'selected':''}>Slideshow</option>
                    <option value="video"     ${s.background.type==='video'?'selected':''}>Video</option>
                    <option value="template"  ${s.background.type==='template'?'selected':''}>Template</option>
                </select>
                <div class="sl-bg-fields" style="margin-top:8px;"></div>
            </div>

            <div class="sl-row" style="margin-top:8px;">
                <div>
                    <label class="sl-field-label">Enter animation</label>
                    <select class="sl-select sl-anim">
                        ${ENTERS.map(e => `<option value="${e}" ${s.animation.enter===e?'selected':''}>${e}</option>`).join('')}
                    </select>
                </div>
                <div>
                    <label class="sl-field-label">Duration (ms)</label>
                    <input type="number" min="0" max="5000" step="50" class="sl-input sl-anim-dur"
                           value="${s.animation.duration_ms || 400}">
                </div>
                <div>
                    <label class="sl-field-label">Transition</label>
                    <select class="sl-select sl-trans">
                        ${TRANS.map(t => `<option value="${t}" ${s.transition===t?'selected':''}>${t}</option>`).join('')}
                    </select>
                </div>
            </div>

            <div style="margin-top:12px;">
                <label class="sl-field-label">Hosted blocks</label>
                <div class="sl-chips" style="margin-bottom:8px;"></div>
                <div style="display:flex;gap:8px;">
                    <select class="sl-select sl-add-block" style="flex:1;">
                        <option value="">— Add a block —</option>
                        ${BLOCKS.map(b => `<option value="${b.id}">${escAttr((b.label?b.label+' · ':'')+b.type)}</option>`).join('')}
                    </select>
                </div>
            </div>
        `;
        wrap.appendChild(card);

        // Wire events — every state change schedules a debounced autosave so
        // the device-preview iframe and the saved snapshot stay in sync without
        // forcing the user to remember to click Save.
        card.querySelector('.sl-title').addEventListener('input', e => { slides[i].title = e.target.value; scheduleAutoSave(); });
        card.querySelector('.sl-bg-type').addEventListener('change', e => {
            slides[i].background = { type: e.target.value };
            if (e.target.value === 'color')    slides[i].background.color = '#0f172a';
            if (e.target.value === 'gradient') Object.assign(slides[i].background, {from_color:'#1e293b', to_color:'#0f172a'});
            renderSlides();
            scheduleAutoSave();
        });
        card.querySelector('.sl-anim').addEventListener('change', e => { slides[i].animation.enter = e.target.value; scheduleAutoSave(); });
        card.querySelector('.sl-anim-dur').addEventListener('input', e => { slides[i].animation.duration_ms = parseInt(e.target.value, 10) || 0; scheduleAutoSave(); });
        card.querySelector('.sl-trans').addEventListener('change', e => { slides[i].transition = e.target.value; scheduleAutoSave(); });

        card.querySelectorAll('[data-act]').forEach(btn => {
            btn.addEventListener('click', () => {
                const act = btn.dataset.act;
                if (act === 'rm')   { slides.splice(i, 1); renderSlides(); scheduleAutoSave(); }
                if (act === 'up'   && i > 0) { [slides[i-1], slides[i]] = [slides[i], slides[i-1]]; renderSlides(); scheduleAutoSave(); }
                if (act === 'down' && i < slides.length-1) { [slides[i+1], slides[i]] = [slides[i], slides[i+1]]; renderSlides(); scheduleAutoSave(); }
                if (act === 'dup') {
                    // Deep clone via JSON so nested settings don't share refs.
                    const copy = JSON.parse(JSON.stringify(slides[i]));
                    copy.title = (copy.title || 'Slide') + ' (copy)';
                    slides.splice(i + 1, 0, copy);
                    renderSlides();
                    scheduleAutoSave();
                }
            });
        });

        // Background-type-specific fields. Mirrors the option-set of the
        // page-background card but operates entirely inside the slide JSON
        // payload so each slide can carry its own visual treatment.
        renderSlideBgFields(card.querySelector('.sl-bg-fields'), i);

        // Block chips + per-block animation/width rows
        const chips = card.querySelector('.sl-chips');
        if (!s.block_ids.length) {
            chips.innerHTML = '<span class="sl-empty">No blocks yet — pick one below.</span>';
        } else {
            s.block_ids.forEach((bid, bi) => {
                const ov = (slides[i].block_settings && slides[i].block_settings[bid]) || {};
                const span = parseInt(ov.grid_span, 10) || 12;
                const row = document.createElement('div');
                row.className = 'sl-block-row';
                row.innerHTML = `
                    <div class="sl-block-row-top">
                        <span class="sl-block-chip" style="margin:0;">${escAttr(blockLabel(bid))}
                            <button type="button" data-rm title="Remove">×</button>
                        </span>
                        <div class="sl-span-row" style="flex:1; min-width: 220px;">
                            <span class="sl-span-label"><i class="fas fa-columns"></i> Width</span>
                            <div class="sl-span-btns">
                                ${SPANS.map(sp => `<button type="button" class="sl-span-btn ${span===sp.v?'active':''}" data-span="${sp.v}" title="${sp.l} (${sp.v}/12)">${sp.l}</button>`).join('')}
                            </div>
                        </div>
                    </div>
                    <div class="sl-block-row-controls">
                        <label>Anim
                            <select class="sl-select sl-bs-enter">
                                ${ENTERS.map(e => `<option value="${e}" ${(ov.enter||'fade')===e?'selected':''}>${e}</option>`).join('')}
                            </select>
                        </label>
                        <label>Delay (ms)
                            <input type="number" min="0" max="10000" step="50" class="sl-input sl-bs-delay" value="${parseInt(ov.delay_ms,10)||0}">
                        </label>
                        <label>Align
                            <select class="sl-select sl-bs-align">
                                ${['left','center','right','stretch'].map(a => `<option value="${a}" ${(ov.align||'center')===a?'selected':''}>${a}</option>`).join('')}
                            </select>
                        </label>
                    </div>
                `;
                chips.appendChild(row);

                row.querySelector('[data-rm]').addEventListener('click', () => {
                    slides[i].block_ids.splice(bi, 1);
                    if (slides[i].block_settings) delete slides[i].block_settings[bid];
                    renderSlides();
                    scheduleAutoSave();
                });
                const ensure = () => {
                    if (!slides[i].block_settings) slides[i].block_settings = {};
                    if (!slides[i].block_settings[bid]) slides[i].block_settings[bid] = {};
                    return slides[i].block_settings[bid];
                };
                row.querySelector('.sl-bs-enter').addEventListener('change', e => { ensure().enter = e.target.value; scheduleAutoSave(); });
                row.querySelector('.sl-bs-delay').addEventListener('input',  e => { ensure().delay_ms = parseInt(e.target.value, 10) || 0; scheduleAutoSave(); });
                row.querySelector('.sl-bs-align').addEventListener('change', e => { ensure().align = e.target.value; scheduleAutoSave(); });
                row.querySelectorAll('.sl-span-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        row.querySelectorAll('.sl-span-btn').forEach(b => b.classList.remove('active'));
                        btn.classList.add('active');
                        ensure().grid_span = parseInt(btn.dataset.span, 10) || 12;
                        scheduleAutoSave();
                    });
                });
            });
        }

        const addBlock = card.querySelector('.sl-add-block');
        addBlock.addEventListener('change', e => {
            const v = parseInt(e.target.value, 10);
            if (v && !slides[i].block_ids.includes(v)) {
                slides[i].block_ids.push(v); renderSlides(); scheduleAutoSave();
            }
        });
    });
}

document.getElementById('sl-add-slide').addEventListener('click', () => {
    slides.push({
        title: '', block_ids: [], block_settings: {},
        background: { type: 'color', color: document.getElementById('sl-theme-bg').value },
        animation:  { enter: 'fade', duration_ms: 400 },
        transition: document.getElementById('sl-transition').value,
        settings:   {},
    });
    renderSlides();
    scheduleAutoSave();
});

// Deck-level inputs — autosave on any change so the live preview reflects
// theme/transition/auto-advance/loop tweaks without requiring a Publish click.
['sl-theme-bg','sl-theme-acc','sl-theme-text'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input',  () => scheduleAutoSave());
});
['sl-transition','sl-auto','sl-loop'].forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('change', () => scheduleAutoSave());
    if (el.tagName === 'INPUT') el.addEventListener('input', () => scheduleAutoSave());
});

function buildPayload(publish) {
    return {
        is_published: !!publish,
        settings: {
            theme: {
                background: document.getElementById('sl-theme-bg').value,
                accent:     document.getElementById('sl-theme-acc').value,
                text:       document.getElementById('sl-theme-text').value,
            },
            transition:   document.getElementById('sl-transition').value,
            auto_advance: parseInt(document.getElementById('sl-auto').value, 10) || 0,
            loop:         document.getElementById('sl-loop').value === '1',
        },
        slides: slides.map(s => ({
            title:          s.title || null,
            block_ids:      s.block_ids,
            block_settings: s.block_settings || {},
            background:     s.background,
            animation:      s.animation,
            transition:     s.transition,
            settings:       s.settings || {},
        })),
    };
}

// Autosave status pill helpers — small DOM updates, no alerts.
function setAutoSaveState(state, label) {
    const pill = document.getElementById('sl-autosave');
    if (!pill) return;
    pill.classList.remove('is-idle','is-saving','is-saved','is-error');
    pill.classList.add('is-' + state);
    const lab = pill.querySelector('.label');
    if (lab && label) lab.textContent = label;
}

// Core save — `mode` is 'autosave' (silent, draft) or 'publish' (loud, push live).
async function save(mode) {
    const isPublish  = (mode === 'publish');
    const isAutosave = (mode === 'autosave');
    const btn = isPublish ? document.getElementById('sl-publish') : null;
    let orig = '';
    if (btn) { btn.disabled = true; orig = btn.innerHTML; btn.textContent = 'Publishing…'; }
    if (isAutosave) setAutoSaveState('saving', 'Saving…');
    try {
        const r = await fetch(URLS.save, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            credentials: 'same-origin',
            // Autosave is always a draft save (is_published=false). The
            // controller's `if ($publish)` block — which bumps the version and
            // overwrites the public snapshot — is only entered for the
            // explicit Publish click, so the live version stays frozen until
            // the user clicks Publish, matching the on-screen contract.
            body: JSON.stringify(buildPayload(isPublish)),
        });
        const j = await r.json();
        if (!r.ok) throw new Error(j.message || 'Save failed');
        isPublished = !!j.is_published;
        version     = j.version || version;
        const pill = document.getElementById('sl-status-pill');
        pill.className = 'sl-status-pill ' + (isPublished ? 'live' : 'draft');
        pill.textContent = isPublished ? ('Published v' + version) : 'Draft';
        reloadDevicePreview();
        if (isAutosave) {
            setAutoSaveState('saved', 'Saved');
            // Fade back to idle after a couple seconds so it doesn't shout.
            clearTimeout(window._slAutoSaveIdleTimer);
            window._slAutoSaveIdleTimer = setTimeout(() => setAutoSaveState('idle', 'Saved'), 2200);
        }
    } catch (e) {
        if (isAutosave) {
            setAutoSaveState('error', e.message || 'Save failed');
        } else {
            alert(e.message || 'Save failed');
        }
    } finally {
        if (btn) { btn.disabled = false; btn.innerHTML = orig; }
    }
}

// Debounced autosave — collapses bursts of input events into one save.
// 700ms feels responsive while a user types into a number/color picker but
// avoids hammering the server on every keystroke or color-picker drag.
let _slAutoSaveTimer = null;
function scheduleAutoSave() {
    setAutoSaveState('saving', 'Pending…');
    clearTimeout(_slAutoSaveTimer);
    _slAutoSaveTimer = setTimeout(() => save('autosave'), 700);
}

document.getElementById('sl-publish').addEventListener('click', () => save('publish'));

// Reload every device-preview iframe on the page (cache-busting query param).
function reloadDevicePreview() {
    if (typeof window._reloadAllPreviewIframes === 'function') {
        window._reloadAllPreviewIframes();
        return;
    }
    document.querySelectorAll('.preview-iframe').forEach(function(f) {
        if (!f.src) return;
        try {
            const u = new URL(f.src, window.location.href);
            u.searchParams.set('_t', Date.now());
            f.src = u.toString();
        } catch (_) {
            const sep = f.src.includes('?') ? '&' : '?';
            f.src = f.src + sep + '_t=' + Date.now();
        }
    });
}

renderSlides();
</script>
@endsection
