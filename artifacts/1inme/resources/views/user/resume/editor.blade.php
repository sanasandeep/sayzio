@extends('user.layouts.app')
@section('title', 'Resume / Portfolio')

@push('styles')
<style>
    .resume-shell { display: grid; grid-template-columns: minmax(0,1fr) minmax(0,1fr); gap: 20px; align-items: start; }
    @media (max-width: 1023px) { .resume-shell { grid-template-columns: minmax(0,1fr); } }
    .resume-pane { background: var(--bg-card, #1a1a1f); border: 1px solid var(--border-strong, #2a2a32); border-radius: 16px; }
    .resume-section { border-bottom: 1px solid var(--border-glass, #2a2a32); }
    .resume-section:last-child { border-bottom: none; }
    .resume-section-head { display:flex; align-items:center; justify-content:space-between; gap:8px; padding: 14px 16px; cursor: pointer; user-select:none; }
    .resume-section-head:hover { background: rgba(124,58,237,0.04); }
    .resume-section-head h3 { display:flex; align-items:center; gap:10px; font-size: 13px; font-weight: 700; color: var(--text-primary,#fff); margin:0; }
    .resume-section-head h3 i.head-icon { color:#7c3aed; width:16px; text-align:center; font-size: 12px; }
    .resume-section-body { padding: 6px 16px 16px; }
    .resume-field-row { display:grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px; }
    .resume-field-row.full { grid-template-columns: 1fr; }
    .resume-field label { display:block; font-size: 10px; font-weight: 600; color: var(--text-muted,#9ca3af); margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.05em; }
    .resume-input, .resume-textarea, .resume-select {
        width: 100%; padding: 8px 10px;
        background: var(--bg-glass-input, rgba(255,255,255,0.03));
        border: 1px solid var(--border-glass, #2a2a32);
        border-radius: 10px;
        color: var(--text-primary, #fff);
        font-size: 12px;
        outline: none; transition: border-color .15s, box-shadow .15s;
    }
    .resume-input:focus, .resume-textarea:focus, .resume-select:focus { border-color: rgba(124,58,237,0.5); box-shadow: 0 0 0 3px rgba(124,58,237,0.08); }
    .resume-textarea { min-height: 70px; resize: vertical; font-family: inherit; }
    .resume-card-item { background: rgba(124,58,237,0.04); border: 1px solid var(--border-glass, #2a2a32); border-radius: 12px; padding: 10px 12px 12px; margin-bottom: 10px; }
    .resume-card-item-head { display:flex; align-items:center; justify-content:space-between; gap:8px; padding: 2px 0 8px; cursor: grab; }
    .resume-card-item-title { font-size: 12px; font-weight: 600; color: var(--text-primary,#fff); flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .resume-card-item.sortable-ghost { opacity: 0.4; }
    .resume-card-item.sortable-chosen { box-shadow: 0 4px 18px rgba(124,58,237,0.18); }
    .resume-icon-btn { display:inline-flex; align-items:center; justify-content:center; width:26px; height:26px; border-radius:8px; color: var(--text-muted,#9ca3af); border:1px solid transparent; background:transparent; cursor:pointer; font-size:11px; transition: all .15s; }
    .resume-icon-btn:hover { background: rgba(124,58,237,0.1); color:#a78bfa; }
    .resume-icon-btn.danger:hover { background: rgba(239,68,68,0.1); color:#f87171; }
    .resume-add-btn { display:inline-flex; align-items:center; gap:6px; padding: 7px 12px; border-radius: 10px; background: rgba(124,58,237,0.12); color: #c4b5fd; font-size: 11px; font-weight: 600; border: 1px dashed rgba(124,58,237,0.3); cursor: pointer; transition: all .15s; }
    .resume-add-btn:hover { background: rgba(124,58,237,0.18); border-style: solid; }
    .resume-pill { display:inline-flex; align-items:center; gap: 4px; padding: 4px 9px; border-radius:999px; font-size:10px; font-weight:600; background: rgba(124,58,237,0.12); color:#c4b5fd; border: 1px solid rgba(124,58,237,0.2); }
    .resume-status-bar { display:flex; align-items:center; gap: 10px; padding: 10px 14px; }
    .resume-save-dot { width: 8px; height:8px; border-radius:50%; background:#10b981; box-shadow: 0 0 12px rgba(16,185,129,0.5); }
    .resume-save-dot.saving { background:#f59e0b; box-shadow: 0 0 12px rgba(245,158,11,0.5); animation: pulse-dot 1.2s ease-in-out infinite; }
    .resume-save-dot.error { background:#ef4444; box-shadow: 0 0 12px rgba(239,68,68,0.5); }
    @keyframes pulse-dot { 0%,100% { opacity:1; } 50% { opacity:0.35; } }
    .resume-toast { position: fixed; right: 16px; bottom: 16px; z-index: 200; padding: 11px 16px; border-radius: 12px; font-size: 12px; font-weight: 500; box-shadow: 0 10px 30px rgba(0,0,0,0.3); display:flex; align-items:center; gap:8px; max-width: 360px; }
    .resume-toast.error { background: rgba(239,68,68,0.15); color:#fecaca; border: 1px solid rgba(239,68,68,0.3); }
    .resume-toast.success { background: rgba(16,185,129,0.15); color:#a7f3d0; border:1px solid rgba(16,185,129,0.3); }
    .preview-frame { background:#fff; border-radius: 12px; overflow:hidden; min-height: 600px; box-shadow: inset 0 0 0 1px rgba(0,0,0,0.06); }
    .preview-page { padding: 32px 36px; min-height: 800px; font-family: 'Inter','Helvetica Neue',Arial,sans-serif; }
    .preview-page.serif { font-family: Georgia,'Times New Roman',serif; }
    .preview-page.display { font-family: 'Plus Jakarta Sans','Inter',sans-serif; }
    .preview-page.tight { font-size: 12px; line-height: 1.4; padding: 24px 28px; }
    .preview-page.spacious { font-size: 14px; line-height: 1.65; padding: 36px 40px; }
    .pv-name { font-size: 26px; font-weight: 800; margin: 0 0 4px; line-height: 1.1; }
    .pv-headline { font-size: 13px; margin: 0 0 6px; }
    .pv-contact { font-size: 11px; opacity: 0.85; display: flex; flex-wrap: wrap; gap: 4px 10px; margin-bottom: 16px; }
    .pv-contact span+span::before { content: '·'; margin-right: 10px; opacity: 0.5; }
    .pv-section { margin-top: 18px; }
    .pv-section h2 { font-size: 11px; font-weight: 800; letter-spacing: 0.18em; text-transform: uppercase; margin: 0 0 8px; padding-bottom: 4px; border-bottom: 1.5px solid currentColor; }
    .pv-item { margin-bottom: 12px; }
    .pv-item-row { display:flex; align-items:baseline; justify-content:space-between; gap: 10px; }
    .pv-item-title { font-size: 13px; font-weight: 700; }
    .pv-item-sub { font-size: 12px; }
    .pv-item-meta { font-size: 11px; opacity: 0.75; white-space: nowrap; }
    .pv-item-desc { font-size: 12px; margin-top: 4px; white-space: pre-wrap; }
    .pv-summary { font-size: 12.5px; line-height: 1.6; white-space: pre-wrap; }
    .pv-skill-row { display:flex; flex-wrap:wrap; gap: 6px; }
    .pv-skill-pill { padding: 3px 9px; border-radius: 999px; font-size: 11px; border: 1px solid currentColor; }
    .pv-link { font-size: 12px; text-decoration: underline; }
    .pv-sidebar { display: grid; grid-template-columns: 200px 1fr; gap: 22px; }
    .pv-sidebar > .pv-side-col { border-right: 1px solid rgba(0,0,0,0.08); padding-right: 18px; }
    .pv-portfolio-grid { display:grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .pv-portfolio-card { border: 1px solid currentColor; border-radius: 8px; padding: 10px; }
    .tpl-thumb { display:block; width: 100%; aspect-ratio: 4/5; border-radius: 10px; border: 2px solid var(--border-glass, #2a2a32); cursor:pointer; transition: all .15s; background:#fff; overflow:hidden; }
    .tpl-thumb img { width: 100%; height: 100%; object-fit: cover; display:block; }
    .tpl-thumb.active { border-color: #7c3aed; box-shadow: 0 0 0 3px rgba(124,58,237,0.2); }
    .tpl-card { display:flex; flex-direction:column; gap: 6px; }
    .tpl-card-name { font-size: 11px; font-weight: 600; color: var(--text-primary,#fff); text-align:center; }
    .swatch { width: 28px; height: 28px; border-radius: 50%; cursor:pointer; border: 2px solid transparent; transition: all .15s; position: relative; }
    .swatch:hover { transform: scale(1.08); }
    .swatch.active { border-color: var(--text-primary,#fff); box-shadow: 0 0 0 2px rgba(124,58,237,0.5); }
    .swatch::before { content:''; position:absolute; inset: 4px; border-radius:50%; background: var(--swatch-accent); }
    .level-stars { display:inline-flex; gap: 1px; }
    .level-stars button { background:none; border:none; color: rgba(124,58,237,0.25); cursor: pointer; font-size: 13px; padding: 0; }
    .level-stars button.on { color: #facc15; }
    .empty-state { text-align: center; padding: 32px 18px; }
    .empty-state .icon { width: 64px; height: 64px; border-radius: 50%; background: rgba(124,58,237,0.1); display: inline-flex; align-items:center; justify-content:center; font-size: 24px; color: #a78bfa; margin-bottom: 14px; }
    .resume-mobile-tabs { display: none; gap: 6px; margin-bottom: 14px; padding: 4px; background: var(--bg-card,#1a1a1f); border-radius: 12px; border: 1px solid var(--border-glass,#2a2a32); }
    .resume-mobile-tabs button { flex:1; padding: 8px; font-size: 12px; font-weight: 600; border-radius: 8px; background: transparent; color: var(--text-muted,#9ca3af); border: none; cursor:pointer; }
    .resume-mobile-tabs button.active { background: rgba(124,58,237,0.18); color:#fff; }
    @media (max-width: 1023px) {
        .resume-mobile-tabs { display: flex; }
        .resume-pane[data-pane="editor"]:not(.mobile-active) { display: none; }
        .resume-pane[data-pane="preview"]:not(.mobile-active) { display: none; }
    }
    [x-cloak] { display: none !important; }
    .chev { transition: transform .2s; }
    .chev.rot { transform: rotate(180deg); }

    /* Template picker controls */
    .tpl-picker-controls { display:flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    .tpl-search { flex: 1 1 220px; min-width: 0; padding: 8px 12px; font-size: 12px; }
    .tpl-cat-tabs { display:flex; flex-wrap: wrap; gap: 4px; max-width: 100%; }
    .tpl-cat-tab {
        display: inline-flex; align-items: center; gap: 5px; padding: 5px 10px;
        font-size: 11px; font-weight: 600; border-radius: 999px;
        background: rgba(124,58,237,0.06); border: 1px solid rgba(124,58,237,0.18);
        color: var(--text-muted, #9ca3af); cursor: pointer; transition: all .15s;
    }
    .tpl-cat-tab:hover { background: rgba(124,58,237,0.14); color: #fff; }
    .tpl-cat-tab.active { background: rgba(124,58,237,0.24); color:#fff; border-color: rgba(124,58,237,0.5); }
    .tpl-cat-count { font-size: 9px; padding: 1px 5px; border-radius: 999px; background: rgba(0,0,0,0.2); }
    .tpl-card-cat { font-size: 9px; text-align:center; color: var(--text-muted, #9ca3af); text-transform: uppercase; letter-spacing: 0.06em; }
    .preview-page.mono { font-family: 'SFMono-Regular','Menlo',monospace; }
</style>
@include('common.partials.resume-styles')
<style>

    /* ── Import modal ─────────────────────────────────── */
    .resume-import-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.55); z-index: 300; display:flex; align-items:flex-start; justify-content:center; padding: 4vh 16px; overflow-y:auto; }
    .resume-import-modal { width: 100%; max-width: 720px; background: var(--bg-card,#1a1a1f); border: 1px solid var(--border-strong,#2a2a32); border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.5); display:flex; flex-direction:column; max-height: 92vh; }
    .resume-import-head { display:flex; align-items:center; justify-content:space-between; padding: 14px 18px; border-bottom: 1px solid var(--border-glass,#2a2a32); }
    .resume-import-head h3 { display:flex; align-items:center; gap:10px; margin:0; font-size: 14px; font-weight:700; color: var(--text-primary,#fff); }
    .resume-import-head h3 i { color:#7c3aed; }
    .resume-import-close { background:transparent; border:none; color: var(--text-muted,#9ca3af); cursor:pointer; font-size: 16px; padding: 4px 8px; border-radius:8px; }
    .resume-import-close:hover { background: rgba(124,58,237,0.1); color:#fff; }
    .resume-import-body { padding: 16px 18px 20px; overflow-y:auto; }
    .resume-import-tabs { display:flex; gap:6px; margin-bottom: 14px; border-bottom: 1px solid var(--border-glass,#2a2a32); padding-bottom: 8px; flex-wrap:wrap; }
    .resume-import-tabs button { display:inline-flex; align-items:center; gap:6px; padding: 7px 11px; font-size: 11px; font-weight:600; border-radius: 8px; background: transparent; border: 1px solid transparent; color: var(--text-muted,#9ca3af); cursor:pointer; }
    .resume-import-tabs button:hover { color:#fff; background: rgba(124,58,237,0.06); }
    .resume-import-tabs button.active { background: rgba(124,58,237,0.18); color:#fff; border-color: rgba(124,58,237,0.3); }
    .resume-import-pane { padding: 6px 0; }
    .resume-import-help { font-size: 12px; color: var(--text-muted,#9ca3af); margin-bottom: 12px; line-height: 1.5; }
    .resume-import-drop { display:flex; align-items:center; gap:10px; padding: 14px 16px; border: 1.5px dashed rgba(124,58,237,0.35); border-radius: 12px; cursor: pointer; background: rgba(124,58,237,0.04); color: var(--text-primary,#fff); font-size: 12px; }
    .resume-import-drop:hover { background: rgba(124,58,237,0.08); border-style: solid; }
    .resume-import-drop input[type=file] { display: none; }
    .resume-import-drop i { color:#a78bfa; font-size: 18px; }
    .resume-import-actions { display:flex; gap:10px; justify-content:flex-end; margin-top: 14px; }
    .resume-import-actions.justify-between { justify-content: space-between; }
    .resume-import-error { margin-top: 12px; padding: 10px 12px; border-radius: 10px; background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color:#fecaca; font-size: 12px; }
    .resume-import-note { padding: 10px 12px; border-radius: 10px; background: rgba(124,58,237,0.08); border: 1px solid rgba(124,58,237,0.2); color:#c4b5fd; font-size: 12px; margin-bottom: 14px; }
    .resume-import-group { border: 1px solid var(--border-glass,#2a2a32); border-radius: 12px; padding: 12px; margin-bottom: 12px; background: rgba(255,255,255,0.015); }
    .resume-import-group-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom: 10px; flex-wrap:wrap; }
    .resume-import-group-head h4 { display:flex; align-items:center; gap:8px; margin:0; font-size: 12px; font-weight:700; color: var(--text-primary,#fff); }
    .resume-import-group-head select { width: auto; font-size: 11px; padding: 4px 8px; }
    .resume-import-count { font-size: 10px; color: var(--text-muted,#9ca3af); font-weight:500; }
    .resume-import-mini { padding: 3px 8px; font-size: 10px; font-weight:600; border-radius: 6px; background: rgba(124,58,237,0.1); color:#c4b5fd; border: 1px solid rgba(124,58,237,0.2); cursor:pointer; }
    .resume-import-mini:hover { background: rgba(124,58,237,0.2); }
    .resume-import-row { display:flex; gap: 10px; align-items:flex-start; padding: 8px; border-radius: 8px; cursor: pointer; }
    .resume-import-row:hover { background: rgba(124,58,237,0.05); }
    .resume-import-row.disabled { opacity: 0.45; cursor: not-allowed; }
    .resume-import-row input[type=checkbox] { margin-top: 3px; accent-color: #7c3aed; }
    .resume-import-row-key { font-size: 12px; font-weight:600; color: var(--text-primary,#fff); text-transform: capitalize; }
    .resume-import-row-val { font-size: 11px; color: var(--text-muted,#9ca3af); margin-top: 2px; word-break: break-word; }
    .resume-import-summary { font-size: 12px; color: var(--text-muted,#cbd5e1); white-space: pre-wrap; max-height: 160px; overflow-y:auto; padding: 8px; background: rgba(0,0,0,0.2); border-radius: 8px; }
    .resume-import-chip { display:inline-flex; align-items:center; gap: 6px; padding: 5px 10px; border-radius: 999px; background: rgba(124,58,237,0.06); border: 1px solid rgba(124,58,237,0.2); font-size: 11px; color: var(--text-primary,#fff); cursor: pointer; text-transform: capitalize; }
    .resume-import-chip input { accent-color: #7c3aed; }
    /* Wide variant + two-column layout used on the Review & Merge step
       so the user can see the resume update as they tick candidates. */
    .resume-import-modal-wide { max-width: 1180px; }
    .resume-import-review { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.05fr); gap: 16px; align-items: stretch; padding: 16px 18px 20px; }
    .resume-import-picks { min-width: 0; max-height: calc(92vh - 70px); overflow-y: auto; padding-right: 4px; }
    .resume-import-preview-pane { min-width: 0; display:flex; flex-direction:column; border: 1px solid var(--border-glass,#2a2a32); border-radius: 12px; background: rgba(255,255,255,0.02); overflow: hidden; max-height: calc(92vh - 70px); position: sticky; top: 0; }
    .resume-import-preview-head { display:flex; align-items:center; gap:8px; padding: 10px 12px; border-bottom: 1px solid var(--border-glass,#2a2a32); font-size: 11px; font-weight: 700; color: var(--text-primary,#fff); text-transform: uppercase; letter-spacing: 0.08em; }
    .resume-import-preview-head i { color:#a78bfa; }
    .resume-import-preview-hint { margin-left:auto; font-size: 10px; font-weight: 600; color:#c4b5fd; background: rgba(124,58,237,0.15); border:1px solid rgba(124,58,237,0.3); padding: 2px 8px; border-radius: 999px; letter-spacing: 0; text-transform: none; }
    .resume-import-preview-frame { flex: 1; overflow-y: auto; background: #f5f5f5; padding: 16px; }
    .resume-import-preview-frame .preview-page { transform: scale(0.62); transform-origin: top left; width: 161.3%; min-height: 0; box-shadow: 0 8px 24px rgba(0,0,0,0.25); border-radius: 6px; }
    @media (max-width: 900px) {
        .resume-import-modal-wide { max-width: 720px; }
        .resume-import-review { grid-template-columns: 1fr; }
        .resume-import-picks, .resume-import-preview-pane { max-height: none; }
        .resume-import-preview-pane { position: static; }
        .resume-import-preview-frame .preview-page { transform: scale(0.7); width: 142.85%; }
    }

    /* ── Tailor-to-job modal ─────────────────────────── */
    .resume-tailor-cost { display:flex; align-items:center; justify-content:space-between; gap: 12px; margin: 12px 0; padding: 10px 12px; border-radius: 10px; background: rgba(124,58,237,0.06); border: 1px solid rgba(124,58,237,0.18); font-size: 12px; color: var(--text-primary,#fff); flex-wrap:wrap; }
    .resume-tailor-cost i { color:#a78bfa; margin-right:6px; }
    .resume-tailor-cost strong { color:#fff; }
    .resume-tailor-cost-hint { color:#fbbf24; font-size: 11px; }
    .resume-tailor-history { margin-top: 18px; border-top: 1px solid var(--border-glass,#2a2a32); padding-top: 14px; }
    .resume-tailor-history h4 { display:flex; align-items:center; gap:8px; margin: 0 0 10px; font-size: 11px; font-weight:700; text-transform: uppercase; letter-spacing: .06em; color: var(--text-muted,#9ca3af); }
    .resume-tailor-history-row { padding: 8px 10px; border-radius: 8px; background: rgba(255,255,255,0.025); margin-bottom: 6px; }
    .resume-tailor-history-jd { font-size: 12px; color: var(--text-primary,#fff); white-space: nowrap; overflow:hidden; text-overflow:ellipsis; }
    .resume-tailor-history-meta { font-size: 10px; color: var(--text-muted,#9ca3af); margin-top: 2px; display:flex; gap:6px; }
    .resume-tailor-summary-bar { display:flex; align-items:center; justify-content:space-between; gap: 12px; margin-bottom: 14px; padding: 10px 12px; border-radius: 10px; background: rgba(16,185,129,0.06); border: 1px solid rgba(16,185,129,0.2); font-size: 11px; color: var(--text-primary,#fff); flex-wrap:wrap; }
    .resume-tailor-summary-bar i { color:#34d399; margin-right:4px; }
    .resume-tailor-summary-bar-bullet { color:#86efac; font-weight:600; }
    .resume-tailor-keywords { padding: 12px; margin-bottom: 12px; border: 1px solid var(--border-glass,#2a2a32); border-radius: 12px; background: rgba(255,255,255,0.015); }
    .resume-tailor-keywords h4 { display:flex; align-items:center; gap:8px; margin:0 0 10px; font-size: 12px; font-weight:700; color: var(--text-primary,#fff); }
    .resume-tailor-keyword-row { display:flex; flex-wrap:wrap; gap: 6px; }
    .resume-tailor-keyword-chip { font-size: 11px; padding: 3px 9px; border-radius: 999px; background: rgba(245,158,11,0.12); color:#fde68a; border: 1px solid rgba(245,158,11,0.25); }
    .resume-tailor-toggle { display:inline-flex; align-items:center; gap:6px; cursor:pointer; font-size: 11px; color: var(--text-muted,#9ca3af); }
    .resume-tailor-toggle input { accent-color: #10b981; }
    .resume-tailor-diff { display:grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 6px; }
    @media (max-width: 720px) { .resume-tailor-diff { grid-template-columns: 1fr; } }
    .resume-tailor-diff-col { min-width: 0; }
    .resume-tailor-diff-label { font-size: 10px; text-transform: uppercase; letter-spacing: .07em; color: var(--text-muted,#9ca3af); margin-bottom: 4px; font-weight: 700; }
    .resume-tailor-diff-text { font-size: 12px; line-height: 1.55; color: var(--text-primary,#e5e7eb); padding: 9px 10px; border-radius: 8px; background: rgba(0,0,0,0.22); white-space: pre-wrap; word-break: break-word; max-height: 220px; overflow-y:auto; }
    .resume-tailor-diff-old { border-left: 3px solid rgba(239,68,68,0.45); }
    .resume-tailor-diff-new { border-left: 3px solid rgba(16,185,129,0.55); }
    .resume-tailor-diff-text ins { background: rgba(16,185,129,0.22); color:#bbf7d0; text-decoration: none; padding: 0 2px; border-radius: 3px; font-weight: 600; }
    .resume-tailor-diff-text del { background: rgba(239,68,68,0.18); color:#fecaca; text-decoration: line-through; padding: 0 2px; border-radius: 3px; }
    .resume-tailor-diff-text mark.kw { background: rgba(245,158,11,0.25); color:#fde68a; padding: 0 2px; border-radius: 3px; }
    .resume-tailor-rationale { margin-top: 8px; font-size: 11px; color: var(--text-muted,#cbd5e1); display:flex; align-items:flex-start; gap: 6px; padding: 6px 8px; background: rgba(124,58,237,0.05); border-left: 2px solid rgba(124,58,237,0.4); border-radius: 0 6px 6px 0; }
    .resume-tailor-rationale i { color:#fbbf24; margin-top: 2px; }
    .resume-tailor-exp { padding: 12px; border: 1px solid var(--border-glass,#2a2a32); border-radius: 10px; margin-bottom: 10px; background: rgba(255,255,255,0.015); }
    .resume-tailor-exp-head { display:flex; align-items:center; justify-content:space-between; gap: 12px; margin-bottom: 8px; flex-wrap: wrap; }
    .resume-tailor-exp-role { font-size: 12px; font-weight: 700; color: var(--text-primary,#fff); }
    .resume-tailor-exp-company { font-size: 11px; color: var(--text-muted,#9ca3af); }
    .resume-tailor-skill-row { display:flex; gap: 10px; align-items:flex-start; padding: 8px; border-radius: 8px; cursor: pointer; }
    .resume-tailor-skill-row:hover { background: rgba(124,58,237,0.05); }
    .resume-tailor-skill-row input[type=checkbox] { margin-top: 3px; accent-color: #7c3aed; }
    .resume-tailor-skill-name { font-size: 12px; font-weight: 600; color: var(--text-primary,#fff); }
    .resume-tailor-skill-rationale { font-size: 11px; color: var(--text-muted,#9ca3af); margin-top: 2px; }
</style>
@endpush

@section('content')
<div x-data="resumeEditor()" x-init="init()" x-cloak>
    <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary,#fff);">Resume / Portfolio</h1>
            <p class="text-xs mt-1" style="color: var(--text-muted,#9ca3af);">Build a polished resume with a live preview. Switch templates and color themes any time.</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <button type="button" class="resume-add-btn" @click="openImport()" title="Import from PDF, LinkedIn, your bio link, or AI">
                <i class="fas fa-file-import"></i> Import
            </button>
            <button type="button" class="resume-add-btn" @click="openTailor()" title="Paste a job description and let AI tailor your resume">
                <i class="fas fa-wand-magic-sparkles"></i> Tailor to a job
            </button>
            <button type="button" class="resume-add-btn" @click="openCoverLetter()" title="Generate a tailored cover letter from a job description">
                <i class="fas fa-envelope-open-text"></i> Generate cover letter
            </button>
            <div class="resume-status-bar resume-pane">
                <span class="resume-save-dot" :class="{ saving: status==='saving', error: status==='error' }"></span>
                <span class="text-xs" style="color: var(--text-muted,#9ca3af);" x-text="statusLabel"></span>
            </div>
            {{-- ATS-readiness check. Opens a results panel that lists pass/
                 warn/fail items and deep-links each warning back to the
                 offending section in the editor. --}}
            <button type="button" class="resume-add-btn" @click="openAtsCheck()"
                    title="Scan for things that commonly trip up Applicant Tracking Systems">
                <i class="fas" :class="atsBusy ? 'fa-spinner fa-spin' : 'fa-shield-halved'"></i>
                <span>Check ATS readiness</span>
                <template x-if="atsReport && atsReport.has_unresolved">
                    <span class="ats-badge"
                          :class="atsReport.fail_count > 0 ? 'fail' : 'warn'"
                          x-text="atsReport.fail_count + atsReport.warn_count"></span>
                </template>
            </button>
            {{-- Paper size toggle + Download PDF. Disabled while a save is
                 in flight so the PDF never reflects an unpersisted edit. --}}
            <div class="resume-pane flex items-center gap-1 p-1" style="border-radius: 12px; position: relative;">
                <button type="button" class="resume-icon-btn" style="width:auto; padding: 4px 9px; font-size: 10px; font-weight: 700;"
                        :class="{ 'pdf-size-active': pdfSize === 'a4' }"
                        @click="pdfSize = 'a4'" title="A4 paper size">A4</button>
                <button type="button" class="resume-icon-btn" style="width:auto; padding: 4px 9px; font-size: 10px; font-weight: 700;"
                        :class="{ 'pdf-size-active': pdfSize === 'letter' }"
                        @click="pdfSize = 'letter'" title="US Letter paper size">Letter</button>
                <button type="button" class="resume-add-btn" style="margin-left: 4px; position: relative;"
                        :disabled="downloading || status === 'saving' || unsavedFields > 0"
                        :style="(downloading || status === 'saving' || unsavedFields > 0) ? 'opacity:0.6; cursor: not-allowed;' : ''"
                        @click="downloadPdf()">
                    <i class="fas" :class="downloading ? 'fa-spinner fa-spin' : 'fa-file-arrow-down'"></i>
                    <span x-text="downloading ? 'Preparing…' : 'Download PDF'"></span>
                    <template x-if="atsReport && atsReport.has_unresolved">
                        <span class="ats-badge ats-badge-corner"
                              :class="atsReport.fail_count > 0 ? 'fail' : 'warn'"
                              :title="(atsReport.fail_count + atsReport.warn_count) + ' unresolved ATS warning(s) — open Check ATS readiness for details.'"
                              x-text="atsReport.fail_count + atsReport.warn_count"></span>
                    </template>
                </button>
            </div>
        </div>
    </div>
    <style>
        .pdf-size-active { background: rgba(124,58,237,0.18); color:#fff; }
        .ats-badge { display:inline-flex; align-items:center; justify-content:center; min-width:18px; height:18px; padding:0 5px; border-radius:999px; font-size:10px; font-weight:700; margin-left:6px; }
        .ats-badge.warn { background:#f59e0b; color:#1f2937; }
        .ats-badge.fail { background:#ef4444; color:#fff; }
        .ats-badge-corner { position:absolute; top:-6px; right:-6px; margin-left:0; box-shadow:0 0 0 2px var(--bg-card,#1a1a1f); }

        .ats-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.55); z-index: 300; display:flex; align-items:flex-start; justify-content:center; padding: 4vh 16px; overflow-y:auto; }
        .ats-modal { width: 100%; max-width: 640px; background: var(--bg-card,#1a1a1f); border: 1px solid var(--border-strong,#2a2a32); border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.5); display:flex; flex-direction:column; max-height: 92vh; }
        .ats-head { display:flex; align-items:center; justify-content:space-between; padding: 14px 18px; border-bottom: 1px solid var(--border-glass,#2a2a32); }
        .ats-head h3 { display:flex; align-items:center; gap:10px; margin:0; font-size: 14px; font-weight:700; color: var(--text-primary,#fff); }
        .ats-head h3 i { color:#7c3aed; }
        .ats-close { background:transparent; border:none; color: var(--text-muted,#9ca3af); cursor:pointer; font-size: 16px; padding: 4px 8px; border-radius:8px; }
        .ats-close:hover { background: rgba(124,58,237,0.1); color:#fff; }
        .ats-body { padding: 14px 18px 18px; overflow-y:auto; }
        .ats-summary { display:flex; gap:10px; margin-bottom:14px; flex-wrap:wrap; }
        .ats-pill { display:inline-flex; align-items:center; gap:6px; padding: 5px 11px; border-radius: 999px; font-size: 11px; font-weight: 700; }
        .ats-pill.pass { background: rgba(16,185,129,0.15); color:#a7f3d0; border:1px solid rgba(16,185,129,0.3); }
        .ats-pill.warn { background: rgba(245,158,11,0.15); color:#fcd34d; border:1px solid rgba(245,158,11,0.3); }
        .ats-pill.fail { background: rgba(239,68,68,0.15); color:#fecaca; border:1px solid rgba(239,68,68,0.3); }
        .ats-check-row { display:flex; gap:10px; padding: 10px 12px; border-radius: 12px; border: 1px solid var(--border-glass,#2a2a32); margin-bottom: 8px; background: rgba(255,255,255,0.015); }
        .ats-check-row .ats-icon { font-size: 14px; padding-top: 2px; }
        .ats-check-row.pass .ats-icon { color:#10b981; }
        .ats-check-row.warn .ats-icon { color:#f59e0b; }
        .ats-check-row.fail .ats-icon { color:#ef4444; }
        .ats-check-row .ats-label { font-size: 12px; font-weight:700; color: var(--text-primary,#fff); margin-bottom: 2px; }
        .ats-check-row .ats-msg { font-size: 11.5px; color: var(--text-muted,#cbd5e1); line-height: 1.5; }
        .ats-check-row .ats-fix { display:inline-flex; align-items:center; gap:4px; margin-top:6px; font-size: 11px; font-weight:600; color:#a78bfa; background: transparent; border: 1px solid rgba(124,58,237,0.3); padding: 3px 9px; border-radius: 999px; cursor: pointer; }
        .ats-check-row .ats-fix:hover { background: rgba(124,58,237,0.12); color:#fff; }
        .ats-jd { width:100%; min-height: 80px; padding: 10px 12px; background: var(--bg-glass-input, rgba(255,255,255,0.03)); border: 1px solid var(--border-glass, #2a2a32); border-radius: 10px; color: var(--text-primary,#fff); font-size: 12px; font-family: inherit; resize: vertical; }
        .ats-jd-row { margin-top: 14px; padding-top: 14px; border-top: 1px dashed var(--border-glass,#2a2a32); }
        .ats-jd-row label { display:block; font-size: 10px; font-weight: 700; color: var(--text-muted,#9ca3af); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.05em; }
        .ats-kw-pills { display:flex; flex-wrap:wrap; gap: 5px; margin-top: 8px; }
        .ats-kw-pills .kw { display:inline-flex; align-items:center; padding: 3px 9px; border-radius: 999px; font-size: 10.5px; font-weight: 600; }
        .ats-kw-pills .kw.matched { background: rgba(16,185,129,0.12); color:#a7f3d0; border: 1px solid rgba(16,185,129,0.3); }
        .ats-kw-pills .kw.missing { background: rgba(239,68,68,0.10); color:#fecaca; border: 1px solid rgba(239,68,68,0.25); }
    </style>

    @include('user.resume.partials.import-modal')
    @include('user.resume.partials.tailor-modal')
    @include('user.resume.partials.cover-letter-modal')

    {{-- Empty-state coachmark for brand-new resumes (no items + empty header name) --}}
    <template x-if="isFreshResume && !resumeStarted">
        <div class="resume-pane mb-4 p-6 text-center">
            <div class="empty-state">
                <div class="icon"><i class="fas fa-file-circle-plus"></i></div>
                <h3 class="text-base font-bold mb-2" style="color: var(--text-primary,#fff);">Start your resume</h3>
                <p class="text-xs mb-4" style="color: var(--text-muted,#9ca3af);">Begin with a blank canvas and fill in the sections that fit you. You can switch templates and themes any time.</p>
                <button class="resume-add-btn" @click="startBlank()"><i class="fas fa-plus"></i> Start with a blank resume</button>
            </div>
        </div>
    </template>

    {{-- ────────── VERSION SWITCHER ────────── --}}
    {{-- Lists every named resume version on the account; tapping one
         reloads the editor with `?resume_id=N` so the controller's
         resolveResume() honours the selection. The default version
         is marked with a star and powers the bare /{handle}/resume URL.
         --}}
    <div class="resume-pane mb-3 p-3 flex flex-wrap items-center gap-2" x-show="versions.length">
        <span class="text-[10px] uppercase font-semibold tracking-wide" style="color: var(--text-muted,#9ca3af);">Version</span>
        <div class="flex flex-wrap items-center gap-2 grow">
            <template x-for="v in versions" :key="v.id">
                <button type="button"
                        class="resume-add-btn"
                        :class="v.id === resume.id ? '' : 'opacity-70'"
                        :title="v.is_default ? 'Default version (powers your public link)' : ''"
                        @click="switchVersion(v)">
                    <i class="fas" :class="v.is_default ? 'fa-star' : 'fa-file-lines'"></i>
                    <span x-text="v.name"></span>
                </button>
            </template>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" class="resume-add-btn" @click="versionDialogOpen = true" title="Manage versions">
                <i class="fas fa-layer-group"></i> Manage
            </button>
            <button type="button" class="resume-add-btn" @click="createVersion()" :disabled="versionsBusy">
                <i class="fas fa-plus"></i> New version
            </button>
        </div>
    </div>

    {{-- Manage versions modal --}}
    <div class="resume-modal-backdrop" x-show="versionDialogOpen" x-cloak @click.self="versionDialogOpen = false">
        <div class="resume-modal" style="max-width: 540px;">
            <div class="resume-modal-head">
                <h3><i class="fas fa-layer-group"></i> Resume versions</h3>
                <button type="button" @click="versionDialogOpen = false"><i class="fas fa-times"></i></button>
            </div>
            <div class="resume-modal-body">
                <p class="text-xs mb-3" style="color: var(--text-muted,#9ca3af);">
                    Keep tailored variants for different roles. The default version powers your public
                    <code>/{{ '{handle}' }}/resume</code> link; other versions live at <code>/v/&lt;slug&gt;</code>.
                </p>
                <ul class="flex flex-col gap-2">
                    <template x-for="v in versions" :key="v.id">
                        <li class="flex items-center gap-2 p-2 rounded" style="background: var(--surface-2,rgba(255,255,255,0.04));">
                            <i class="fas" :class="v.is_default ? 'fa-star text-yellow-400' : 'fa-file-lines'"></i>
                            <div class="grow min-w-0">
                                <div class="text-sm font-medium truncate" x-text="v.name"></div>
                                <div class="text-[10px] truncate" style="color: var(--text-muted,#9ca3af);" x-text="v.public_url || ''"></div>
                            </div>
                            <button type="button" class="resume-add-btn" @click="switchVersion(v)" x-show="v.id !== resume.id" title="Open">
                                <i class="fas fa-arrow-right-to-bracket"></i>
                            </button>
                            <button type="button" class="resume-add-btn" @click="renameVersion(v)" title="Rename">
                                <i class="fas fa-pen"></i>
                            </button>
                            <button type="button" class="resume-add-btn" @click="duplicateVersion(v)" title="Duplicate" :disabled="versionsBusy">
                                <i class="fas fa-clone"></i>
                            </button>
                            <button type="button" class="resume-add-btn" @click="setDefaultVersion(v)" title="Make default" x-show="!v.is_default" :disabled="versionsBusy">
                                <i class="fas fa-star"></i>
                            </button>
                            <button type="button" class="resume-add-btn" @click="deleteVersion(v)" title="Delete" x-show="!v.is_default && versions.length > 1" :disabled="versionsBusy">
                                <i class="fas fa-trash"></i>
                            </button>
                        </li>
                    </template>
                </ul>
                <button type="button" class="resume-add-btn mt-3" @click="createVersion()" :disabled="versionsBusy">
                    <i class="fas fa-plus"></i> Create new version
                </button>
            </div>
        </div>
    </div>

    <div class="resume-mobile-tabs">
        <button :class="{ active: mobilePane==='editor' }" @click="mobilePane='editor'"><i class="fas fa-pen-to-square"></i> Editor</button>
        <button :class="{ active: mobilePane==='preview' }" @click="mobilePane='preview'"><i class="fas fa-eye"></i> Preview</button>
    </div>

    <div class="resume-shell">
        {{-- ────────── EDITOR PANE ────────── --}}
        <div class="resume-pane" data-pane="editor" :class="{ 'mobile-active': mobilePane==='editor' }">

            {{-- Template & color picker --}}
            <div class="resume-section">
                <div class="resume-section-head" @click="toggle('design')">
                    <h3><i class="fas fa-palette head-icon"></i> Template &amp; theme</h3>
                    <i class="fas fa-chevron-down chev" :class="{ rot: open.design }"></i>
                </div>
                <div class="resume-section-body" x-show="open.design" x-collapse>
                    <p class="text-[10px] uppercase font-semibold mb-2" style="color: var(--text-muted,#9ca3af);">Template</p>
                    <div class="tpl-picker-controls mb-3">
                        <input type="search" class="resume-input tpl-search" placeholder="Search 50+ templates by name…"
                               x-model.debounce.150ms="tplSearch">
                        <div class="tpl-cat-tabs">
                            <button type="button" class="tpl-cat-tab" :class="{ active: tplCategory === 'all' }"
                                    @click="tplCategory = 'all'">All
                                <span class="tpl-cat-count" x-text="registries.templates.length"></span>
                            </button>
                            <template x-for="cat in templateCategories" :key="cat.id">
                                <button type="button" class="tpl-cat-tab"
                                        :class="{ active: tplCategory === cat.id }"
                                        @click="tplCategory = cat.id"
                                        x-text="cat.label + ' (' + cat.count + ')'"></button>
                            </template>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
                        <template x-for="tpl in filteredTemplates" :key="tpl.id">
                            <div class="tpl-card">
                                <div class="tpl-thumb" :class="{ active: resume.template_id === tpl.id }"
                                     :title="tpl.description"
                                     @click="setTemplate(tpl.id)">
                                    <img :src="tpl.thumbnail" :alt="tpl.name" loading="lazy" onerror="this.style.display='none'">
                                </div>
                                <div class="tpl-card-name" x-text="tpl.name"></div>
                                <div class="tpl-card-cat" x-text="tpl.category_label"></div>
                            </div>
                        </template>
                    </div>
                    <p x-show="!filteredTemplates.length" class="text-xs text-center py-4"
                       style="color: var(--text-muted,#9ca3af);">No templates match this search.</p>
                    <p class="text-[10px] uppercase font-semibold mb-2" style="color: var(--text-muted,#9ca3af);">Color theme</p>
                    <div class="flex flex-wrap items-center gap-3">
                        <template x-for="theme in registries.color_themes" :key="theme.id">
                            <button type="button"
                                    class="swatch"
                                    :title="theme.name"
                                    :style="`background:${theme.tokens.primary}; --swatch-accent:${theme.tokens.accent}`"
                                    :class="{ active: resume.color_theme_id === theme.id }"
                                    @click="setColorTheme(theme.id)"></button>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Publish & sharing --}}
            <div class="resume-section">
                <div class="resume-section-head" @click="toggle('publishing')">
                    <h3>
                        <i class="fas fa-share-nodes head-icon"></i> Publish &amp; sharing
                        <span class="resume-pill" x-show="publishing.is_public" style="background:#16a34a; color:#fff;">Live</span>
                        <span class="resume-pill" x-show="!publishing.is_public" style="background:#475569; color:#fff;">Off</span>
                    </h3>
                    <i class="fas fa-chevron-down chev" :class="{ rot: open.publishing }"></i>
                </div>
                <div class="resume-section-body" x-show="open.publishing" x-collapse>
                    {{-- Toggle row --}}
                    <div class="flex items-start justify-between gap-3 mb-4">
                        <div>
                            <p class="text-sm font-semibold" style="color: var(--text-primary,#fff);">Public résumé page</p>
                            <p class="text-xs" style="color: var(--text-muted,#9ca3af);">When on, your résumé is reachable at the link below and can be embedded in your bio link.</p>
                        </div>
                        <label class="inline-flex items-center cursor-pointer shrink-0">
                            <input type="checkbox" class="sr-only peer"
                                   :checked="publishing.is_public"
                                   @change="publishing.is_public = $event.target.checked; savePublishing()">
                            <div class="w-11 h-6 bg-slate-600 rounded-full peer peer-checked:bg-emerald-500 relative transition-colors">
                                <div class="absolute top-[2px] left-[2px] bg-white rounded-full h-5 w-5 transition-transform"
                                     :class="publishing.is_public ? 'translate-x-5' : ''></div>
                            </div>
                        </label>
                    </div>

                    {{-- Public URL --}}
                    <div class="resume-field" x-show="publishing.is_public">
                        <label>Public URL</label>
                        <div class="flex items-center gap-2">
                            <input class="resume-input" type="text" readonly :value="publishing.public_url" @focus="$event.target.select()">
                            <button type="button" class="resume-add-btn shrink-0"
                                    @click="copyPublicUrl()" :title="copied ? 'Copied!' : 'Copy link'">
                                <i class="fas" :class="copied ? 'fa-check' : 'fa-copy'"></i>
                                <span x-text="copied ? 'Copied' : 'Copy'"></span>
                            </button>
                            <a class="resume-add-btn shrink-0" :href="publishing.public_url" target="_blank" rel="noopener">
                                <i class="fas fa-external-link-alt"></i> Open
                            </a>
                        </div>
                    </div>

                    {{-- Visibility --}}
                    <div class="resume-field-row" x-show="publishing.is_public">
                        <div class="resume-field">
                            <label>Who can view</label>
                            <select class="resume-input"
                                    x-model="publishing.visibility"
                                    @change="savePublishing()">
                                <option value="public">Anyone with the link</option>
                                <option value="registered">Signed-in users only</option>
                                <option value="followers">My followers only</option>
                                <option value="subscribers">My newsletter subscribers</option>
                                <option value="password">Password-protected</option>
                            </select>
                        </div>
                        <div class="resume-field" x-show="publishing.visibility === 'password'">
                            <label x-text="publishing.has_password ? 'Replace password (leave blank to keep current)' : 'Set a password'"></label>
                            <input class="resume-input" type="password" maxlength="200"
                                   placeholder="••••••••"
                                   x-model="publishing.password_input"
                                   @blur="savePublishing()">
                        </div>
                    </div>

                    {{-- Optional expiration: when set + visibility=password,
                         non-owner traffic is gated after this moment so a
                         link sent to a recruiter goes dark on its own. --}}
                    <div class="resume-field-row" x-show="publishing.is_public && publishing.visibility === 'password'">
                        <div class="resume-field">
                            <label>Expires (optional)</label>
                            <input class="resume-input" type="datetime-local"
                                   x-model="publishing.expires_at_local"
                                   @change="savePublishing()">
                            <p class="text-[11px] mt-1" style="color: var(--text-muted,#9ca3af);">
                                <span x-show="publishing.is_share_expired" style="color:#f87171;">
                                    <i class="fas fa-clock"></i> This share has expired — visitors see an expiry message.
                                </span>
                                <span x-show="!publishing.is_share_expired && publishing.expires_at_local">
                                    Visitors will be blocked after this date and time.
                                </span>
                                <span x-show="!publishing.expires_at_local">
                                    Leave blank to share until you turn it off.
                                </span>
                            </p>
                        </div>
                        <div class="resume-field">
                            <label>Revoke share</label>
                            <button type="button" class="resume-add-btn"
                                    style="background: rgba(239,68,68,0.10); color:#f87171;"
                                    @click="revokeShare()" :disabled="revoking">
                                <i class="fas" :class="revoking ? 'fa-spinner fa-spin' : 'fa-rotate'"></i>
                                <span x-text="revoking ? 'Revoking…' : 'Revoke active sessions'"></span>
                            </button>
                            <p class="text-[11px] mt-1" style="color: var(--text-muted,#9ca3af);">
                                Forces everyone who already typed the password back to the prompt. The URL stays the same.
                            </p>
                        </div>
                    </div>

                    {{-- SEO + indexing --}}
                    <div class="resume-field" x-show="publishing.is_public">
                        <label>SEO description (optional, max 240 chars)</label>
                        <textarea class="resume-textarea" rows="2" maxlength="240"
                                  placeholder="Short summary search engines and social platforms will show."
                                  x-model="publishing.meta_description"
                                  @input.debounce.700ms="savePublishing()"></textarea>
                    </div>
                    <label class="flex items-start gap-2 mt-2 cursor-pointer" x-show="publishing.is_public">
                        <input type="checkbox" class="mt-1"
                               :checked="publishing.allow_indexing"
                               @change="publishing.allow_indexing = $event.target.checked; savePublishing()">
                        <span class="text-xs" style="color: var(--text-muted,#9ca3af);">
                            Allow search engines to index this page. Turn off if you'd prefer it stays unlisted (we'll add a noindex tag).
                        </span>
                    </label>

                    {{-- View counter + audit log --}}
                    <div class="mt-4 flex items-center gap-3 flex-wrap text-xs" style="color: var(--text-muted,#9ca3af);">
                        <span><i class="fas fa-eye"></i>
                            <strong x-text="publishing.view_count.toLocaleString()" style="color: var(--text-primary,#fff);"></strong> total views
                        </span>
                        <button type="button" class="resume-add-btn" @click="openViewLog()">
                            <i class="fas fa-list"></i> View log
                        </button>
                    </div>

                    {{-- Short links surfacing this résumé — public URL + a jump
                         to the link's click analytics. Shown whenever a
                         `resume`-type short link points at this résumé so the
                         builder ↔ link bridge is discoverable from both sides. --}}
                    @if(!empty($resumeLinks))
                    <div class="resume-field" style="margin-top: 16px; border-top: 1px solid var(--border-glass, rgba(255,255,255,0.08)); padding-top: 14px;">
                        <label><i class="fas fa-link"></i> Short {{ count($resumeLinks) > 1 ? 'links' : 'link' }} for this résumé</label>
                        <p class="text-[11px] mb-2" style="color: var(--text-muted,#9ca3af);">
                            This résumé is surfaced through {{ count($resumeLinks) > 1 ? 'these short links' : 'a short link' }}. Open the public page or jump to its click analytics.
                        </p>
                        @foreach($resumeLinks as $rl)
                        <div class="flex items-center gap-2 mb-2 flex-wrap">
                            <input class="resume-input" type="text" readonly value="{{ $rl['public_url'] }}" onfocus="this.select()" style="flex: 1 1 200px;">
                            <a class="resume-add-btn shrink-0" href="{{ $rl['public_url'] }}" target="_blank" rel="noopener">
                                <i class="fas fa-external-link-alt"></i> Open
                            </a>
                            <a class="resume-add-btn shrink-0" href="{{ $rl['analytics_url'] }}">
                                <i class="fas fa-chart-line"></i> Analytics
                            </a>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>

            {{-- View log modal --}}
            <div x-show="viewLog.open" x-cloak
                 style="position: fixed; inset: 0; background: rgba(15,23,42,0.55); z-index: 60; display:flex; align-items:flex-start; justify-content:center; padding: 40px 16px;"
                 @click.self="viewLog.open = false">
                <div style="background: var(--bg-glass,#1e1e26); color: var(--text-primary,#fff); width: 100%; max-width: 720px; border-radius: 14px; padding: 18px; max-height: calc(100vh - 80px); overflow:auto;">
                    <div class="flex items-center justify-between mb-3">
                        <h3 style="margin:0; font-size: 15px; font-weight: 700;"><i class="fas fa-eye"></i> Resume views</h3>
                        <button type="button" class="resume-icon-btn" @click="viewLog.open = false"><i class="fas fa-xmark"></i></button>
                    </div>
                    <p class="text-xs" style="color: var(--text-muted,#9ca3af); margin-bottom: 10px;">
                        Each row is one unique visitor per day. Bots and your own visits aren't logged.
                    </p>
                    <template x-if="viewLog.loading">
                        <p class="text-xs" style="color: var(--text-muted,#9ca3af);">Loading…</p>
                    </template>
                    <template x-if="!viewLog.loading && viewLog.entries.length === 0">
                        <p class="text-xs" style="color: var(--text-muted,#9ca3af);">No views yet.</p>
                    </template>
                    <template x-if="!viewLog.loading && viewLog.entries.length > 0">
                        <table style="width:100%; border-collapse: collapse; font-size: 12px;">
                            <thead>
                                <tr style="text-align: left; color: var(--text-muted,#9ca3af);">
                                    <th style="padding: 6px 4px;">When</th>
                                    <th style="padding: 6px 4px;">Country</th>
                                    <th style="padding: 6px 4px;">Visitor</th>
                                    <th style="padding: 6px 4px;">Referrer</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="row in viewLog.entries" :key="row.id">
                                    <tr style="border-top: 1px solid rgba(255,255,255,0.05);">
                                        <td style="padding: 8px 4px;" x-text="formatViewedAt(row.viewed_at)"></td>
                                        <td style="padding: 8px 4px;" x-text="row.country_code || '—'"></td>
                                        <td style="padding: 8px 4px;">
                                            <span x-show="row.viewer_handle" x-text="'@' + row.viewer_handle"></span>
                                            <span x-show="!row.viewer_handle" style="color: var(--text-muted,#9ca3af);">Anonymous</span>
                                        </td>
                                        <td style="padding: 8px 4px; max-width: 200px; overflow:hidden; text-overflow:ellipsis; white-space: nowrap;"
                                            :title="row.referrer || ''"
                                            x-text="row.referrer ? (new URL(row.referrer).hostname || row.referrer) : 'Direct'"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </template>
                    <div class="flex items-center justify-between mt-3" x-show="!viewLog.loading && viewLog.last_page > 1">
                        <button type="button" class="resume-add-btn" @click="loadViewLog(viewLog.current_page - 1)"
                                :disabled="viewLog.current_page <= 1">
                            <i class="fas fa-arrow-left"></i> Previous
                        </button>
                        <span class="text-xs" style="color: var(--text-muted,#9ca3af);">
                            Page <span x-text="viewLog.current_page"></span> of <span x-text="viewLog.last_page"></span>
                        </span>
                        <button type="button" class="resume-add-btn" @click="loadViewLog(viewLog.current_page + 1)"
                                :disabled="viewLog.current_page >= viewLog.last_page">
                            Next <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Sharing / public PDF link --}}
            <div class="resume-section">
                <div class="resume-section-head" @click="toggle('sharing')">
                    <h3><i class="fas fa-share-nodes head-icon"></i> Sharing</h3>
                    <i class="fas fa-chevron-down chev" :class="{ rot: open.sharing }"></i>
                </div>
                <div class="resume-section-body" x-show="open.sharing" x-collapse>
                    <div class="flex items-start gap-3 mb-2">
                        <label style="display:inline-flex; align-items:center; gap:8px; cursor: pointer;">
                            <input type="checkbox"
                                   :checked="resume.is_public_pdf"
                                   :disabled="sharingSaving"
                                   @change="setPublicPdf($event.target.checked)">
                            <span class="text-xs" style="color: var(--text-primary,#fff); font-weight:600;">
                                Allow public PDF download
                            </span>
                        </label>
                        <span class="text-[10px]" x-show="sharingSaving" style="color: var(--text-muted,#9ca3af);">
                            <i class="fas fa-spinner fa-spin"></i> Saving…
                        </span>
                    </div>
                    <p class="text-[11px] mb-3" style="color: var(--text-muted,#9ca3af);">
                        When enabled, anyone with the link below can download your resume PDF.
                        When disabled, the link returns a 404 and only you can download it.
                    </p>
                    <template x-if="resume.is_public_pdf && resume.public_pdf_url">
                        <div class="flex items-center gap-2">
                            <input class="resume-input" type="text" readonly
                                   :value="resume.public_pdf_url"
                                   @focus="$event.target.select()"
                                   style="font-family: ui-monospace, monospace; font-size: 11px;">
                            <button type="button" class="resume-add-btn" @click="copyPublicUrl()" title="Copy link">
                                <i class="fas fa-copy"></i> <span>Copy</span>
                            </button>
                            <a class="resume-add-btn" :href="resume.public_pdf_url" target="_blank" rel="noopener" title="Open in new tab">
                                <i class="fas fa-arrow-up-right-from-square"></i> <span>Open</span>
                            </a>
                        </div>
                    </template>
                    <template x-if="resume.is_public_pdf && !resume.handle">
                        <p class="text-[11px]" style="color:#fbbf24;">
                            <i class="fas fa-triangle-exclamation"></i>
                            Set a handle on your profile to get a shareable link.
                        </p>
                    </template>
                </div>
            </div>

            {{-- Header --}}
            <div class="resume-section">
                <div class="resume-section-head" @click="toggle('header')">
                    <h3><i class="fas fa-id-card head-icon"></i> Header</h3>
                    <i class="fas fa-chevron-down chev" :class="{ rot: open.header }"></i>
                </div>
                <div class="resume-section-body" x-show="open.header" x-collapse>
                    {{-- Photo upload. Routed through the user's vault so quota /
                         serving / cleanup logic stays uniform. The URL is
                         owner-only — the resume itself is never public. --}}
                    <div class="resume-field-row full">
                        <div class="resume-field">
                            <label>Photo</label>
                            <div class="flex items-center gap-3 flex-wrap">
                                <template x-if="resume.sections.header.photo_url">
                                    <img :src="resume.sections.header.photo_url" alt=""
                                         style="width:56px;height:56px;border-radius:50%;object-fit:cover;border:1px solid var(--border-glass,#2a2a32);background:#fff;">
                                </template>
                                <template x-if="!resume.sections.header.photo_url">
                                    <div style="width:56px;height:56px;border-radius:50%;background:rgba(124,58,237,0.10);display:flex;align-items:center;justify-content:center;color:#a78bfa;font-size:18px;border:1px dashed rgba(124,58,237,0.35);">
                                        <i class="fas fa-user"></i>
                                    </div>
                                </template>
                                <input type="file" accept="image/jpeg,image/png,image/webp"
                                       x-ref="photoInput" style="display:none;"
                                       @change="uploadPhoto($event)">
                                <button type="button" class="resume-add-btn"
                                        :disabled="photoUploading"
                                        :style="photoUploading ? 'opacity:0.6;cursor:not-allowed;' : ''"
                                        @click="$refs.photoInput.click()">
                                    <i class="fas" :class="photoUploading ? 'fa-spinner fa-spin' : 'fa-arrow-up-from-bracket'"></i>
                                    <span x-text="resume.sections.header.photo_url ? 'Change photo' : 'Upload photo'"></span>
                                </button>
                                <button type="button" class="resume-icon-btn danger"
                                        x-show="resume.sections.header.photo_url"
                                        :disabled="photoUploading"
                                        @click="removePhoto()" title="Remove photo">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                            <p class="text-[10px] mt-2" style="color: var(--text-muted,#9ca3af);">
                                JPG, PNG or WebP, up to 5&nbsp;MB. A square image works best.
                            </p>
                        </div>
                    </div>
                    <div class="resume-field-row">
                        <div class="resume-field"><label>Full name</label>
                            <input class="resume-input" type="text" maxlength="120" placeholder="Jane Doe"
                                   :value="resume.sections.header.name" @input="onHeader('name', $event.target.value)"></div>
                        <div class="resume-field"><label>Headline</label>
                            <input class="resume-input" type="text" maxlength="160" placeholder="Senior Product Designer"
                                   :value="resume.sections.header.headline" @input="onHeader('headline', $event.target.value)"></div>
                    </div>
                    <div class="resume-field-row">
                        <div class="resume-field"><label>Location</label>
                            <input class="resume-input" type="text" maxlength="160" placeholder="Berlin, Germany"
                                   :value="resume.sections.header.location" @input="onHeader('location', $event.target.value)"></div>
                        <div class="resume-field"><label>Email</label>
                            <input class="resume-input" type="email" maxlength="191" placeholder="jane@example.com"
                                   :value="resume.sections.header.email" @input="onHeader('email', $event.target.value)"></div>
                    </div>
                    <div class="resume-field-row">
                        <div class="resume-field"><label>Phone</label>
                            <input class="resume-input" type="text" maxlength="40" placeholder="+1 555 0100"
                                   :value="resume.sections.header.phone" @input="onHeader('phone', $event.target.value)"></div>
                        <div class="resume-field"><label>Website</label>
                            <input class="resume-input" type="url" maxlength="255" placeholder="https://janedoe.com"
                                   :value="resume.sections.header.website" @input="onHeader('website', $event.target.value)"></div>
                    </div>
                </div>
            </div>

            {{-- Summary --}}
            <div class="resume-section">
                <div class="resume-section-head" @click="toggle('summary')">
                    <h3><i class="fas fa-align-left head-icon"></i> Summary</h3>
                    <i class="fas fa-chevron-down chev" :class="{ rot: open.summary }"></i>
                </div>
                <div class="resume-section-body" x-show="open.summary" x-collapse>
                    <div class="resume-field">
                        <label>Professional summary</label>
                        <textarea class="resume-textarea" rows="4" maxlength="2000"
                                  placeholder="A short paragraph about who you are and what you do."
                                  :value="resume.sections.summary"
                                  @input="onSummary($event.target.value)"></textarea>
                    </div>
                </div>
            </div>

            {{-- List sections --}}
            <template x-for="def in listSections" :key="def.key">
                <div class="resume-section" x-show="templateSupports(def.key)">
                    <div class="resume-section-head" @click="toggle(def.key)">
                        <h3>
                            <i class="fas head-icon" :class="def.icon"></i>
                            <span x-text="def.label"></span>
                            <span class="resume-pill" x-show="(items[def.key]||[]).length" x-text="(items[def.key]||[]).length"></span>
                        </h3>
                        <i class="fas fa-chevron-down chev" :class="{ rot: open[def.key] }"></i>
                    </div>
                    <div class="resume-section-body" x-show="open[def.key]" x-collapse>
                        <div :data-section="def.key" x-init="$nextTick(() => initSortable($el, def.key))">
                            <template x-for="(item, idx) in (items[def.key] || [])" :key="item.id">
                                <div class="resume-card-item" :data-id="item.id">
                                    <div class="resume-card-item-head">
                                        <i class="fas fa-grip-vertical text-xs" style="color: var(--text-muted,#9ca3af);"></i>
                                        <span class="resume-card-item-title" x-text="itemLabel(def.key, item)"></span>
                                        <button type="button" class="resume-icon-btn"
                                                :title="item._open ? 'Collapse' : 'Expand'"
                                                @click="item._open = !item._open">
                                            <i class="fas" :class="item._open ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                                        </button>
                                        <button type="button" class="resume-icon-btn" title="Move up" @click="moveItem(def.key, idx, -1)">
                                            <i class="fas fa-arrow-up"></i>
                                        </button>
                                        <button type="button" class="resume-icon-btn" title="Move down" @click="moveItem(def.key, idx, 1)">
                                            <i class="fas fa-arrow-down"></i>
                                        </button>
                                        <button type="button" class="resume-icon-btn danger" title="Delete" @click="removeItem(def.key, item)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                    <div x-show="item._open" x-collapse>
                                        <div x-html="renderItemForm(def.key, item)"></div>
                                    </div>
                                </div>
                            </template>
                            <button type="button" class="resume-add-btn" @click="addItem(def.key)">
                                <i class="fas fa-plus"></i> <span x-text="def.addLabel"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            {{-- Custom sections --}}
            <div class="resume-section" x-show="templateSupports('custom')">
                <div class="resume-section-head" @click="toggle('custom_sections')">
                    <h3>
                        <i class="fas fa-shapes head-icon"></i> Custom sections
                        <span class="resume-pill" x-show="resume.sections.custom_sections.length" x-text="resume.sections.custom_sections.length"></span>
                    </h3>
                    <i class="fas fa-chevron-down chev" :class="{ rot: open.custom_sections }"></i>
                </div>
                <div class="resume-section-body" x-show="open.custom_sections" x-collapse>
                    <template x-for="cs in resume.sections.custom_sections" :key="cs.key">
                        <div class="resume-card-item">
                            <div class="resume-card-item-head">
                                <input class="resume-input" style="margin:0 6px 0 0;" type="text" maxlength="80"
                                       :value="cs.title" @input="renameCustomSection(cs.key, $event.target.value)">
                                <button type="button" class="resume-icon-btn danger" title="Delete section" @click="removeCustomSection(cs.key)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                            <div :data-section="'custom:'+cs.key" x-init="$nextTick(() => initSortable($el, 'custom', cs.key))">
                                <template x-for="(item, idx) in customItems(cs.key)" :key="item.id">
                                    <div class="resume-card-item" :data-id="item.id" style="background: rgba(167,139,250,0.04);">
                                        <div class="resume-card-item-head">
                                            <i class="fas fa-grip-vertical text-xs" style="color: var(--text-muted,#9ca3af);"></i>
                                            <span class="resume-card-item-title" x-text="item.data.title || 'Untitled entry'"></span>
                                            <button type="button" class="resume-icon-btn" @click="item._open = !item._open">
                                                <i class="fas" :class="item._open ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                                            </button>
                                            <button type="button" class="resume-icon-btn danger" @click="removeItem('custom', item)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                        <div x-show="item._open" x-collapse>
                                            <div x-html="renderItemForm('custom', item)"></div>
                                        </div>
                                    </div>
                                </template>
                                <button type="button" class="resume-add-btn" @click="addCustomItem(cs.key)">
                                    <i class="fas fa-plus"></i> Add entry
                                </button>
                            </div>
                        </div>
                    </template>
                    <div class="flex items-center gap-2 mt-3">
                        <input class="resume-input" type="text" maxlength="80" placeholder="New section name (e.g. Volunteering)"
                               x-model="newCustomTitle" @keydown.enter.prevent="createCustomSection()">
                        <button type="button" class="resume-add-btn" @click="createCustomSection()">
                            <i class="fas fa-plus"></i> Add section
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ────────── PREVIEW PANE ────────── --}}
        <div class="resume-pane" data-pane="preview" :class="{ 'mobile-active': mobilePane==='preview' }">
            <div class="flex items-center justify-between p-3" style="border-bottom: 1px solid var(--border-glass, #2a2a32);">
                <div class="flex items-center gap-2 text-xs" style="color: var(--text-muted,#9ca3af);">
                    <i class="fas fa-eye"></i> <span>Live preview</span>
                </div>
                <span class="resume-pill" x-text="(resume.template && resume.template.name) || 'Template'"></span>
            </div>
            <div class="p-4">
                <div class="preview-frame">
                    <div class="preview-page" x-html="previewHtml"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- ATS-readiness modal ---------------------------------------- --}}
    <template x-if="atsOpen">
        <div class="ats-overlay" @click.self="atsOpen = false">
            <div class="ats-modal">
                <div class="ats-head">
                    <h3><i class="fas fa-shield-halved"></i> ATS readiness</h3>
                    <button class="ats-close" type="button" @click="atsOpen = false" title="Close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="ats-body">
                    <template x-if="atsBusy && !atsReport">
                        <div class="text-center py-6" style="color: var(--text-muted,#9ca3af); font-size: 12px;">
                            <i class="fas fa-spinner fa-spin mr-2"></i> Scanning your resume…
                        </div>
                    </template>

                    <template x-if="atsReport">
                        <div>
                            <div class="ats-summary">
                                <span class="ats-pill pass"><i class="fas fa-circle-check"></i> <span x-text="atsReport.pass_count + ' pass'"></span></span>
                                <span class="ats-pill warn" x-show="atsReport.warn_count > 0"><i class="fas fa-triangle-exclamation"></i> <span x-text="atsReport.warn_count + ' warn'"></span></span>
                                <span class="ats-pill fail" x-show="atsReport.fail_count > 0"><i class="fas fa-circle-xmark"></i> <span x-text="atsReport.fail_count + ' fail'"></span></span>
                                <button type="button" class="resume-add-btn" style="margin-left:auto;"
                                        :disabled="atsBusy"
                                        @click="runAtsCheck()">
                                    <i class="fas" :class="atsBusy ? 'fa-spinner fa-spin' : 'fa-rotate'"></i>
                                    <span>Re-run</span>
                                </button>
                            </div>

                            <template x-for="check in atsReport.checks" :key="check.id">
                                <div class="ats-check-row" :class="check.status">
                                    <div class="ats-icon">
                                        <i class="fas"
                                           :class="check.status === 'pass' ? 'fa-circle-check'
                                                 : check.status === 'fail' ? 'fa-circle-xmark'
                                                 : 'fa-triangle-exclamation'"></i>
                                    </div>
                                    <div style="flex:1; min-width:0;">
                                        <div class="ats-label" x-text="check.label"></div>
                                        <div class="ats-msg" x-text="check.message"></div>
                                        <button type="button" class="ats-fix"
                                                x-show="check.status !== 'pass' && check.link"
                                                @click="jumpToAts(check.link)">
                                            <i class="fas fa-arrow-right"></i>
                                            <span>Fix in editor</span>
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <template x-if="atsReport.keywords">
                                <div style="margin-top: 10px;">
                                    <div class="ats-jd-row" style="border-top: none; padding-top: 0; margin-top: 0;">
                                        <label>Matched keywords (<span x-text="atsReport.keywords.matched.length"></span> / <span x-text="atsReport.keywords.total"></span>)</label>
                                        <div class="ats-kw-pills">
                                            <template x-for="kw in atsReport.keywords.matched" :key="'m-'+kw">
                                                <span class="kw matched" x-text="kw"></span>
                                            </template>
                                            <template x-if="!atsReport.keywords.matched.length">
                                                <span class="text-xs" style="color: var(--text-muted,#9ca3af);">No keywords matched yet.</span>
                                            </template>
                                        </div>
                                        <template x-if="atsReport.keywords.missing.length">
                                            <div style="margin-top: 12px;">
                                                <label>Missing keywords</label>
                                                <div class="ats-kw-pills">
                                                    <template x-for="kw in atsReport.keywords.missing" :key="'x-'+kw">
                                                        <span class="kw missing" x-text="kw"></span>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <div class="ats-jd-row">
                                <label>Optional: paste a target role / job description</label>
                                <textarea class="ats-jd" placeholder="Paste a job description or role keywords here, then click Check to see how well your resume covers them."
                                          x-model="atsJd"></textarea>
                                <div style="display:flex; gap:8px; margin-top: 10px; justify-content: flex-end;">
                                    <button type="button" class="resume-icon-btn" style="width:auto; padding: 6px 11px; font-size: 11px;"
                                            x-show="atsJd"
                                            @click="atsJd = ''; runAtsCheck()">Clear</button>
                                    <button type="button" class="resume-add-btn"
                                            :disabled="atsBusy"
                                            @click="runAtsCheck()">
                                        <i class="fas" :class="atsBusy ? 'fa-spinner fa-spin' : 'fa-magnifying-glass'"></i>
                                        <span x-text="atsReport.keywords ? 'Re-check coverage' : 'Check coverage'"></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>

    {{-- Toast --}}
    <template x-if="toast.visible">
        <div class="resume-toast" :class="toast.type">
            <i class="fas" :class="toast.type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'"></i>
            <span x-text="toast.message"></span>
        </div>
    </template>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
<script>
window.__resumeBootstrap = @json($bootstrap);

// Convert an ISO8601 datetime to "YYYY-MM-DDTHH:MM" suitable for an
// <input type="datetime-local">. Returns "" for null/invalid input.
function toLocalDt(iso) {
    if (!iso) return '';
    try {
        const d = new Date(iso);
        if (isNaN(d.getTime())) return '';
        const pad = (n) => String(n).padStart(2, '0');
        return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    } catch (e) { return ''; }
}

function resumeEditor() {
    return {
        // ── core state ────────────────────────────────────────
        resume: window.__resumeBootstrap.resume,
        registries: window.__resumeBootstrap.registries,
        // List of every named version on the account; the active one
        // is whichever id matches `resume.id`. Reloading with
        // `?resume_id=N` lets the controller resolve the selection.
        versions: window.__resumeBootstrap.versions || [],
        versionDialogOpen: false,
        versionsBusy: false,
        items: {},
        open: { design: true, publishing: false, sharing: false, header: true, summary: true,
            experience: true, education: true, skills: true, projects: true,
            certifications: false, awards: false, languages: false, links: false,
            custom_sections: false },
        sharingSaving: false,
        // Mirrors the publishing fields returned by present(); the
        // password is never echoed back from the server, so we keep a
        // local-only `password_input` buffer that is cleared after each
        // save and only sent up when the user typed a new value.
        publishing: {
            is_public: !!(window.__resumeBootstrap.resume.is_public),
            visibility: window.__resumeBootstrap.resume.visibility || 'public',
            allow_indexing: window.__resumeBootstrap.resume.allow_indexing !== false,
            has_password: !!(window.__resumeBootstrap.resume.has_password),
            password_input: '',
            meta_description: window.__resumeBootstrap.resume.meta_description || '',
            view_count: window.__resumeBootstrap.resume.view_count || 0,
            public_url: window.__resumeBootstrap.public_url || '',
            // Stored as ISO8601 from the server but the <datetime-local>
            // input expects "YYYY-MM-DDTHH:MM" — toLocalDt() converts.
            expires_at_local: toLocalDt(window.__resumeBootstrap.resume.expires_at),
            is_share_expired: !!(window.__resumeBootstrap.resume.is_share_expired),
        },
        revoking: false,
        viewLog: { open: false, loading: false, entries: [], current_page: 1, last_page: 1 },
        copied: false,
        mobilePane: 'editor',
        status: 'idle',
        statusLabel: 'All changes saved',
        toast: { visible: false, message: '', type: 'success' },
        previewHtml: '',
        newCustomTitle: '',
        debouncers: {},
        sortInstances: {},
        unsavedFields: 0,
        resumeStarted: false,
        pdfSize: (window.localStorage && localStorage.getItem('resume_pdf_size')) === 'letter' ? 'letter' : 'a4',
        downloading: false,
        photoUploading: false,

        // ── ATS readiness ─────────────────────────────────────
        atsOpen: false,
        atsBusy: false,
        atsReport: null,
        atsJd: '',

        // ── Template picker UI ────────────────────────────────
        tplSearch: '',
        tplCategory: 'all',
        get templateCategories() {
            const counts = {};
            const labels = {};
            (this.registries.templates || []).forEach(t => {
                const id = t.category || 'professional';
                counts[id] = (counts[id] || 0) + 1;
                labels[id] = t.category_label || id;
            });
            return Object.keys(counts).sort().map(id => ({ id, label: labels[id], count: counts[id] }));
        },
        get filteredTemplates() {
            const q = (this.tplSearch || '').trim().toLowerCase();
            const cat = this.tplCategory;
            return (this.registries.templates || []).filter(t => {
                if (cat !== 'all' && (t.category || '') !== cat) return false;
                if (!q) return true;
                return (t.name || '').toLowerCase().includes(q)
                    || (t.description || '').toLowerCase().includes(q)
                    || (t.category_label || '').toLowerCase().includes(q);
            });
        },

        // ── Import flow state ─────────────────────────────────
        importOpen: false,
        importStep: 'pick',          // 'pick' | 'review'
        importTab: 'file',           // file | linkedin | biolink | ai
        importTabs: [
            { key: 'file',     label: 'PDF / DOCX',  icon: 'fa-file-arrow-up' },
            { key: 'linkedin', label: 'LinkedIn',    icon: 'fa-brands fa-linkedin-in' },
            { key: 'biolink',  label: 'My bio link', icon: 'fa-link' },
            { key: 'ai',       label: 'AI assist',   icon: 'fa-wand-magic-sparkles' },
        ],
        importBusy: false,
        importError: '',
        importFile: null,
        importLinkedinUrl: '',
        importAiPrompt: '',
        importAiSections: ['summary','experience','skills'],
        importCandidates: { header: {}, summary: '', items: [], notes: null },
        importPicks: { header: { mode: 'replace', fields: [] }, summary: { mode: 'replace' }, items: [] },
        importPreviewHtml: '',

        // ── Tailor flow state ─────────────────────────────────
        tailorOpen: false,
        tailorStep: 'pick',          // 'pick' | 'review'
        tailorBusy: false,
        tailorError: '',
        tailorJd: '',
        tailorEstimate: null,        // worst-case credits (null = unknown)
        tailorBalance: 0,
        tailorLastSpent: 0,
        tailorSuggestions: { summary: null, experience: [], skills: { additions: [] }, keywords: [] },
        tailorPicks: { summary: false, experience: [], skills: [] },
        tailorHistory: [],
        _tailorEstimateSeq: 0,

        // ── Cover-letter generator state ──────────────────────
        coverLetterOpen: false,
        coverStep: 'pick',           // 'pick' | 'edit'
        coverBusy: false,
        coverError: '',
        coverJd: '',
        coverTone: 'professional',
        coverTones: [
            { value: 'professional', label: 'Professional' },
            { value: 'warm',         label: 'Warm' },
            { value: 'concise',      label: 'Concise' },
        ],
        coverEstimate: null,
        coverBalance: 0,
        coverLetter: null,           // { id, title, tone, content: { greeting, body[], sign_off }, ... }
        coverHistory: [],
        coverPersonas: [],           // [{ id, name }] — saved AI personas for the Voice picker
        coverPersonaId: null,        // null = "None" (no persona styling)
        coverSectionBusy: '',        // '' | 'greeting' | 'body' | 'sign_off'
        coverCopied: false,
        _coverEstimateSeq: 0,

        listSections: [
            { key: 'experience',     label: 'Experience',     icon: 'fa-briefcase',     addLabel: 'Add experience' },
            { key: 'education',      label: 'Education',      icon: 'fa-graduation-cap',addLabel: 'Add education' },
            { key: 'skills',         label: 'Skills',         icon: 'fa-bolt',          addLabel: 'Add skill' },
            { key: 'projects',       label: 'Projects',       icon: 'fa-diagram-project',addLabel: 'Add project' },
            { key: 'certifications', label: 'Certifications', icon: 'fa-certificate',   addLabel: 'Add certification' },
            { key: 'awards',         label: 'Awards',         icon: 'fa-trophy',        addLabel: 'Add award' },
            { key: 'languages',      label: 'Languages',      icon: 'fa-language',      addLabel: 'Add language' },
            { key: 'links',          label: 'Links',          icon: 'fa-link',          addLabel: 'Add link' },
        ],

        // ── lifecycle ─────────────────────────────────────────
        init() {
            this.hydrate(this.resume);
            this.renderPreview();
            window.addEventListener('beforeunload', (e) => {
                if (this.unsavedFields > 0 || this.status === 'saving') {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });
            // Keep the import-modal preview in sync with whatever the user
            // has currently selected. Deep watch so toggling a checkbox
            // (which mutates picks.items) or changing a header/summary mode
            // both trigger a re-render.
            this.$watch('importPicks', () => this.renderImportPreview(), { deep: true });
            this.$watch('importStep', (step) => {
                if (step === 'review') this.renderImportPreview();
            });
        },

        get isFreshResume() {
            const headerEmpty = !this.resume.sections.header.name && !this.resume.sections.header.headline
                && !this.resume.sections.header.email;
            const noItems = Object.values(this.items).every(arr => !arr || arr.length === 0);
            return headerEmpty && noItems && !this.resume.sections.summary;
        },

        hydrate(payload) {
            this.resume = payload;
            const grouped = {};
            const itemsBag = payload.items || {};
            Object.keys(itemsBag).forEach(type => {
                const arr = itemsBag[type] || [];
                grouped[type] = arr.map(it => Object.assign({}, it, { _open: false }));
            });
            this.listSections.forEach(s => { if (!grouped[s.key]) grouped[s.key] = []; });
            if (!grouped.custom) grouped.custom = [];
            this.items = grouped;
        },

        // ── helpers ───────────────────────────────────────────
        toggle(k) { this.open[k] = !this.open[k]; },
        templateSupports(sectionKey) {
            const tpl = this.resume.template || {};
            const allowed = tpl.sections || [];
            return !allowed.length || allowed.indexOf(sectionKey) !== -1;
        },
        customItems(key) {
            return (this.items.custom || []).filter(it =>
                (it.data || {}).custom_section_key === key);
        },
        async downloadPdf() {
            if (this.downloading) return;
            // Wait for any pending debounced saves to complete first so
            // the PDF reflects exactly what the editor shows.
            if (this.unsavedFields > 0 || this.status === 'saving') {
                this.showToast('Finishing save…', 'success');
                return;
            }
            try {
                this.downloading = true;
                if (window.localStorage) localStorage.setItem('resume_pdf_size', this.pdfSize);
                const url = '{{ route('user.resume.download') }}?size=' + encodeURIComponent(this.pdfSize);
                const res = await fetch(url, {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/pdf', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.ok) {
                    let msg = 'Could not generate PDF.';
                    try { const j = await res.json(); if (j && j.message) msg = j.message; } catch (e) {}
                    if (res.status === 429) msg = 'Too many downloads — please wait a moment and try again.';
                    throw new Error(msg);
                }
                const blob = await res.blob();
                // Pull the server-suggested filename out of Content-Disposition
                // so the download retains the friendly `firstname-lastname-resume.pdf`.
                let filename = 'resume.pdf';
                const cd = res.headers.get('Content-Disposition') || '';
                const m  = /filename="?([^";]+)"?/i.exec(cd);
                if (m) filename = m[1];
                const blobUrl = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = blobUrl; a.download = filename; a.style.display = 'none';
                document.body.appendChild(a); a.click();
                setTimeout(() => { URL.revokeObjectURL(blobUrl); a.remove(); }, 1000);
                this.showToast('Resume downloaded.', 'success');
            } catch (e) {
                this.showToast(e.message || 'Could not generate PDF.', 'error');
            } finally {
                this.downloading = false;
            }
        },
        startBlank() {
            this.resumeStarted = true;
            this.open.header = true;
            this.open.summary = true;
        },

        // ── ATS readiness ─────────────────────────────────────
        async openAtsCheck() {
            this.atsOpen = true;
            // Re-scan every time the panel opens — the resume might have
            // changed since the last run, and the badge needs fresh data.
            await this.runAtsCheck();
        },
        async runAtsCheck() {
            if (this.atsBusy) return;
            this.atsBusy = true;
            try {
                const payload = {};
                const jd = (this.atsJd || '').trim();
                if (jd) payload.target_role = jd;
                const r = await this.http('POST', '{{ route('user.resume.ats-check') }}', payload);
                this.atsReport = r.report;
            } catch (e) {
                this.showToast(e.message || 'Could not run ATS check.', 'error');
            } finally {
                this.atsBusy = false;
            }
        },
        jumpToAts(linkKey) {
            // Map check.link → editor `open` key, expand the section,
            // close the modal, and scroll the section into view so the
            // user lands on the offending field.
            if (!linkKey) return;
            // Anchor keys in the report match `open` keys directly. We
            // also force-open the design panel for layout/font fixes.
            if (linkKey in this.open) this.open[linkKey] = true;
            this.atsOpen = false;
            this.$nextTick(() => {
                // Find the section heading whose @click toggles this key.
                // Each `resume-section-head` renders the toggle — match by
                // text content to keep it lightweight (no extra ids).
                const heads = document.querySelectorAll('.resume-section-head');
                const labelMap = {
                    header: 'Header', summary: 'Summary', design: 'Template',
                    experience: 'Experience', education: 'Education',
                    skills: 'Skills', projects: 'Projects',
                    certifications: 'Certifications', awards: 'Awards',
                    languages: 'Languages', links: 'Links',
                };
                const want = labelMap[linkKey];
                if (!want) return;
                for (const el of heads) {
                    if ((el.textContent || '').trim().startsWith(want)) {
                        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        break;
                    }
                }
            });
        },

        // ── status / toast ────────────────────────────────────
        // ── publishing ────────────────────────────────────────
        async savePublishing() {
            this.markSaving();
            const payload = {
                is_public: !!this.publishing.is_public,
                visibility: this.publishing.visibility,
                allow_indexing: !!this.publishing.allow_indexing,
                meta_description: this.publishing.meta_description || null,
            };
            // Only ship the password when the user actually typed
            // something this session — sending an empty string while in
            // password mode would clear the existing hash unintentionally.
            if (this.publishing.visibility === 'password' && this.publishing.password_input) {
                payload.password = this.publishing.password_input;
            }
            // Always send expires_at — empty string clears it server-side.
            payload.expires_at = this.publishing.expires_at_local
                ? new Date(this.publishing.expires_at_local).toISOString()
                : '';
            try {
                const r = await this.http('PUT', '{{ route('user.resume.publishing.update') }}', payload);
                this.publishing.is_public      = !!r.resume.is_public;
                this.publishing.visibility     = r.resume.visibility;
                this.publishing.allow_indexing = r.resume.allow_indexing !== false;
                this.publishing.has_password   = !!r.resume.has_password;
                this.publishing.view_count     = r.resume.view_count || 0;
                this.publishing.password_input = '';
                this.publishing.expires_at_local = toLocalDt(r.resume.expires_at);
                this.publishing.is_share_expired = !!r.resume.is_share_expired;
                if (r.public_url) this.publishing.public_url = r.public_url;
                this.markSaved();
            } catch (e) { this.markError(e.message); }
        },
        async revokeShare() {
            if (this.revoking) return;
            if (!confirm('Force everyone who already typed the password back to the prompt? This keeps the URL but invalidates current sessions.')) return;
            this.revoking = true;
            try {
                const r = await this.http('POST', '{{ route('user.resume.share.revoke') }}', {});
                this.publishing.has_password = !!r.resume.has_password;
                this.showToast('Active sessions revoked.', 'success');
            } catch (e) { this.markError(e.message); }
            finally { this.revoking = false; }
        },
        async openViewLog() {
            this.viewLog.open = true;
            await this.loadViewLog(1);
        },
        async loadViewLog(page) {
            if (page < 1) return;
            this.viewLog.loading = true;
            try {
                const url = '{{ route('user.resume.views') }}?page=' + page;
                const res = await fetch(url, {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.ok) throw new Error('Could not load view log.');
                const j = await res.json();
                this.viewLog.entries = j.data || [];
                this.viewLog.current_page = j.meta?.current_page || 1;
                this.viewLog.last_page = j.meta?.last_page || 1;
            } catch (e) {
                this.showToast(e.message || 'Could not load view log.', 'error');
            } finally {
                this.viewLog.loading = false;
            }
        },
        formatViewedAt(iso) {
            if (!iso) return '—';
            try {
                const d = new Date(iso);
                return d.toLocaleString();
            } catch (e) { return iso; }
        },
        async copyPublicUrl() {
            try {
                await navigator.clipboard.writeText(this.publishing.public_url);
                this.copied = true;
                setTimeout(() => { this.copied = false; }, 1500);
            } catch (_) {
                this.showToast('Could not copy — select and copy manually.', 'error');
            }
        },

        markSaving()  { this.status = 'saving'; this.statusLabel = 'Saving…'; },
        markSaved()   { this.status = 'saved'; this.statusLabel = 'All changes saved'; },
        markError(msg){ this.status = 'error'; this.statusLabel = 'Save failed'; this.showToast(msg || 'Could not save changes.', 'error'); },
        showToast(msg, type='success') {
            this.toast = { visible: true, message: msg, type };
            clearTimeout(this._toastT);
            this._toastT = setTimeout(() => { this.toast.visible = false; }, 3500);
        },

        // ── Version management ────────────────────────────────
        // Reload the editor pointed at the chosen version. We could
        // hot-swap state in place, but a hard navigation guarantees
        // every nested computed (preview HTML, sortable handles,
        // bootstrap-only fields) gets a clean re-init.
        switchVersion(v) {
            if (!v || v.id === this.resume.id) { this.versionDialogOpen = false; return; }
            const u = new URL(window.location.href);
            u.searchParams.set('resume_id', String(v.id));
            window.location.assign(u.toString());
        },
        async createVersion() {
            const name = window.prompt('Name this resume version', 'New version');
            if (!name) return;
            this.versionsBusy = true;
            try {
                const r = await this.http('POST', '{{ route('user.resume.versions.store') }}', { name });
                this.versions = r.versions || this.versions;
                this.showToast('Version created.');
                if (r.version && r.version.id) this.switchVersion(r.version);
            } catch (e) { this.showToast(e.message || 'Could not create version.', 'error'); }
            finally { this.versionsBusy = false; }
        },
        async renameVersion(v) {
            const name = window.prompt('Rename version', v.name);
            if (!name || name === v.name) return;
            this.versionsBusy = true;
            try {
                const r = await this.http('PUT', '/user/resume/versions/' + v.id, { name });
                this.versions = r.versions || this.versions;
                if (v.id === this.resume.id && r.version) {
                    this.resume.name = r.version.name;
                    this.resume.slug = r.version.slug;
                    this.resume.public_url = r.version.public_url;
                }
                this.showToast('Renamed.');
            } catch (e) { this.showToast(e.message || 'Could not rename.', 'error'); }
            finally { this.versionsBusy = false; }
        },
        async duplicateVersion(v) {
            this.versionsBusy = true;
            try {
                const r = await this.http('POST', '/user/resume/versions/' + v.id + '/duplicate', {});
                this.versions = r.versions || this.versions;
                this.showToast('Duplicated.');
                if (r.version && r.version.id) this.switchVersion(r.version);
            } catch (e) { this.showToast(e.message || 'Could not duplicate.', 'error'); }
            finally { this.versionsBusy = false; }
        },
        async setDefaultVersion(v) {
            this.versionsBusy = true;
            try {
                const r = await this.http('POST', '/user/resume/versions/' + v.id + '/default', {});
                this.versions = r.versions || this.versions;
                this.showToast('Default version updated.');
            } catch (e) { this.showToast(e.message || 'Could not set default.', 'error'); }
            finally { this.versionsBusy = false; }
        },
        async deleteVersion(v) {
            if (!window.confirm('Delete version "' + v.name + '"? Items inside will be removed.')) return;
            this.versionsBusy = true;
            try {
                const r = await this.http('DELETE', '/user/resume/versions/' + v.id);
                this.versions = r.versions || this.versions;
                this.showToast('Version deleted.');
                // If we just deleted the active one, jump to the
                // (new) default so the editor isn't pointing at a
                // dangling id after the next reload.
                if (v.id === this.resume.id) {
                    const def = (this.versions || []).find(x => x.is_default) || this.versions[0];
                    if (def) this.switchVersion(def);
                }
            } catch (e) { this.showToast(e.message || 'Could not delete.', 'error'); }
            finally { this.versionsBusy = false; }
        },

        // ── HTTP ──────────────────────────────────────────────
        async http(method, url, body) {
            const opts = {
                method,
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            };
            if (body !== undefined) opts.body = JSON.stringify(body);
            const res = await fetch(url, opts);
            if (!res.ok) {
                let msg = 'Request failed';
                try { const j = await res.json(); msg = j.message || msg; } catch (e) {}
                throw new Error(msg);
            }
            if (res.status === 204) return null;
            return res.json();
        },

        // ── debounced field saves ─────────────────────────────
        debounce(key, fn, ms=600) {
            this.unsavedFields++;
            clearTimeout(this.debouncers[key]);
            this.markSaving();
            // Any edit invalidates the cached ATS report — drop it so
            // the warning badge doesn't show a stale count until the
            // user re-runs the scan.
            this.atsReport = null;
            this.debouncers[key] = setTimeout(async () => {
                try {
                    await fn();
                    this.unsavedFields = Math.max(0, this.unsavedFields - 1);
                    if (this.unsavedFields === 0) this.markSaved();
                } catch (e) {
                    this.unsavedFields = Math.max(0, this.unsavedFields - 1);
                    this.markError(e.message);
                }
            }, ms);
        },

        onHeader(field, value) {
            this.resume.sections.header[field] = value;
            this.renderPreview();
            this.debounce('header', async () => {
                const r = await this.http('PUT', '{{ route('user.resume.header.update') }}', this.resume.sections.header);
                this.hydrate(r.resume);
                this.renderPreview();
            });
        },

        async uploadPhoto(event) {
            const input = event.target;
            const file  = input.files && input.files[0];
            // Reset the input immediately so re-selecting the same file
            // re-fires `change`. Without this, picking the same name twice
            // is a no-op.
            input.value = '';
            if (!file) return;
            if (file.size > 5 * 1024 * 1024) {
                this.showToast('Photo must be 5 MB or smaller.', 'error');
                return;
            }
            this.photoUploading = true;
            this.markSaving();
            try {
                const fd = new FormData();
                fd.append('photo', file);
                const res = await fetch('{{ route('user.resume.header.photo.upload') }}', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: fd,
                });
                if (!res.ok) {
                    let msg = 'Photo upload failed.';
                    try {
                        const j = await res.json();
                        if (j && j.message) msg = j.message;
                        else if (j && j.errors && j.errors.photo) msg = j.errors.photo[0];
                    } catch (e) {}
                    throw new Error(msg);
                }
                const j = await res.json();
                this.hydrate(j.resume);
                this.renderPreview();
                this.markSaved();
                this.showToast('Photo uploaded.', 'success');
            } catch (e) {
                this.markError(e.message);
            } finally {
                this.photoUploading = false;
            }
        },
        async removePhoto() {
            if (!confirm('Remove your header photo?')) return;
            this.photoUploading = true;
            this.markSaving();
            try {
                const r = await this.http('DELETE', '{{ route('user.resume.header.photo.destroy') }}');
                this.hydrate(r.resume);
                this.renderPreview();
                this.markSaved();
                this.showToast('Photo removed.', 'success');
            } catch (e) {
                this.markError(e.message);
            } finally {
                this.photoUploading = false;
            }
        },
        onSummary(value) {
            this.resume.sections.summary = value;
            this.renderPreview();
            this.debounce('summary', async () => {
                const r = await this.http('PUT', '{{ route('user.resume.summary.update') }}', { summary: value });
                this.hydrate(r.resume);
                this.renderPreview();
            });
        },
        async setTemplate(id) {
            if (this.resume.template_id === id) return;
            const prev = this.resume.template_id;
            this.resume.template_id = id;
            const found = (this.registries.templates || []).find(t => t.id === id);
            if (found) this.resume.template = found;
            this.renderPreview();
            try {
                this.markSaving();
                const r = await this.http('PUT', '{{ route('user.resume.template.update') }}', { template_id: id });
                this.hydrate(r.resume); this.renderPreview(); this.markSaved();
            } catch (e) {
                this.resume.template_id = prev; this.markError(e.message);
            }
        },
        async setPublicPdf(enabled) {
            const prev = !!this.resume.is_public_pdf;
            const next = !!enabled;
            if (prev === next) return;
            this.resume.is_public_pdf = next;
            this.sharingSaving = true;
            try {
                const r = await this.http('PUT', '{{ route('user.resume.public-pdf.update') }}', { is_public_pdf: next });
                this.hydrate(r.resume);
                this.renderPreview();
                this.showToast(next ? 'Public download enabled.' : 'Public download disabled.', 'success');
            } catch (e) {
                this.resume.is_public_pdf = prev;
                this.showToast(e.message || 'Could not update sharing.', 'error');
            } finally {
                this.sharingSaving = false;
            }
        },
        async copyPublicUrl() {
            const url = this.resume.public_pdf_url;
            if (!url) return;
            try {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    await navigator.clipboard.writeText(url);
                } else {
                    const ta = document.createElement('textarea');
                    ta.value = url; ta.style.position='fixed'; ta.style.opacity='0';
                    document.body.appendChild(ta); ta.select();
                    document.execCommand('copy'); ta.remove();
                }
                this.showToast('Link copied.', 'success');
            } catch (e) {
                this.showToast('Could not copy link.', 'error');
            }
        },
        async setColorTheme(id) {
            if (this.resume.color_theme_id === id) return;
            const prev = this.resume.color_theme_id;
            this.resume.color_theme_id = id;
            const found = (this.registries.color_themes || []).find(t => t.id === id);
            if (found) this.resume.color_theme = found;
            this.renderPreview();
            try {
                this.markSaving();
                const r = await this.http('PUT', '{{ route('user.resume.color-theme.update') }}', { color_theme_id: id });
                this.hydrate(r.resume); this.renderPreview(); this.markSaved();
            } catch (e) {
                this.resume.color_theme_id = prev; this.markError(e.message);
            }
        },

        // ── items ─────────────────────────────────────────────
        defaultItemData(type) {
            return ({
                experience:     { company:'', role:'', location:'', start_date:'', end_date:'', is_current:false, description:'' },
                education:      { school:'', degree:'', field:'', start_date:'', end_date:'', description:'' },
                skills:         { name:'', level:3, group:'' },
                projects:       { name:'', role:'', url:'', description:'', start_date:'', end_date:'' },
                certifications: { name:'', issuer:'', issued_on:'', expires_on:'', credential_url:'' },
                awards:         { title:'', issuer:'', date:'', description:'' },
                languages:      { name:'', proficiency:'professional' },
                links:          { label:'', url:'', icon:'' },
            })[type] || {};
        },
        async addItem(type) {
            const stub = this.defaultItemData(type);
            // Some types have required fields; pre-fill placeholders so the
            // POST validates and the user can immediately edit them.
            const seed = Object.assign({}, stub);
            if (type === 'experience') { seed.company = 'Company'; seed.role = 'Role'; }
            if (type === 'education')  { seed.school = 'School'; }
            if (type === 'skills')     { seed.name = 'New skill'; }
            if (type === 'projects')   { seed.name = 'Project'; }
            if (type === 'certifications') { seed.name = 'Certification'; }
            if (type === 'awards')     { seed.title = 'Award'; }
            if (type === 'languages')  { seed.name = 'Language'; }
            if (type === 'links')      { seed.label = 'Website'; seed.url = 'https://example.com'; }
            try {
                this.markSaving();
                const r = await this.http('POST', '{{ route('user.resume.items.store') }}', { section_type: type, data: seed });
                this.hydrate(r.resume);
                const last = (this.items[type] || []).slice(-1)[0];
                if (last) last._open = true;
                this.renderPreview();
                this.markSaved();
            } catch (e) { this.markError(e.message); }
        },
        async addCustomItem(sectionKey) {
            const seed = { custom_section_key: sectionKey, title: 'Untitled entry', subtitle: '', date: '', description: '', url: '' };
            try {
                this.markSaving();
                const r = await this.http('POST', '{{ route('user.resume.items.store') }}', { section_type: 'custom', data: seed });
                this.hydrate(r.resume);
                const created = this.customItems(sectionKey).slice(-1)[0];
                if (created) created._open = true;
                this.renderPreview();
                this.markSaved();
            } catch (e) { this.markError(e.message); }
        },
        async removeItem(type, item) {
            if (!confirm('Delete this entry?')) return;
            try {
                this.markSaving();
                await this.http('DELETE', '/user/resume/items/' + item.id);
                this.items[type] = (this.items[type] || []).filter(i => i.id !== item.id);
                this.renderPreview();
                this.markSaved();
            } catch (e) { this.markError(e.message); }
        },
        moveItem(type, idx, dir) {
            const arr = this.items[type] || [];
            const target = idx + dir;
            if (target < 0 || target >= arr.length) return;
            const tmp = arr[idx]; arr[idx] = arr[target]; arr[target] = tmp;
            this.items[type] = arr.slice();
            this.persistOrder(type);
            this.renderPreview();
        },
        async persistOrder(type) {
            try {
                this.markSaving();
                const ids = (this.items[type] || []).map(i => i.id);
                const r = await this.http('POST', '{{ route('user.resume.items.reorder') }}', { section_type: type, item_ids: ids });
                this.hydrate(r.resume);
                this.renderPreview();
                this.markSaved();
            } catch (e) { this.markError(e.message); }
        },

        initSortable(el, type, customKey) {
            if (!el || !window.Sortable) return;
            // Use the inner card wrapper for sorting; only one per type.
            const key = customKey ? `custom:${customKey}` : type;
            if (this.sortInstances[key]) return;
            this.sortInstances[key] = new Sortable(el, {
                animation: 150,
                handle: '.resume-card-item-head',
                draggable: '.resume-card-item',
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                onEnd: () => {
                    const ids = Array.from(el.querySelectorAll(':scope > .resume-card-item'))
                        .map(n => parseInt(n.dataset.id, 10)).filter(Boolean);
                    if (type === 'custom') {
                        // Only items in this custom section were rearranged;
                        // splice them back into the canonical custom list.
                        const others = (this.items.custom || []).filter(i =>
                            (i.data || {}).custom_section_key !== customKey);
                        const inThis = ids.map(id =>
                            (this.items.custom || []).find(i => i.id === id)).filter(Boolean);
                        this.items.custom = others.concat(inThis);
                        this.persistOrder('custom');
                    } else {
                        const reordered = ids.map(id => (this.items[type] || []).find(i => i.id === id)).filter(Boolean);
                        this.items[type] = reordered;
                        this.persistOrder(type);
                    }
                    this.renderPreview();
                },
            });
        },

        // Per-item field updates: change item.data, debounce-PUT.
        updateItemField(type, id, field, value) {
            const arr = this.items[type] || [];
            const item = arr.find(i => i.id === id);
            if (!item) return;
            item.data[field] = value;
            this.renderPreview();
            this.debounce('item:' + id, async () => {
                const r = await this.http('PUT', '/user/resume/items/' + id, { data: item.data });
                if (r && r.item) item.data = r.item.data;
            });
        },

        itemLabel(type, item) {
            const d = item.data || {};
            switch (type) {
                case 'experience':     return [d.role, d.company].filter(Boolean).join(' · ') || 'New experience';
                case 'education':      return [d.school, d.degree].filter(Boolean).join(' · ') || 'New education';
                case 'skills':         return d.name || 'New skill';
                case 'projects':       return d.name || 'New project';
                case 'certifications': return d.name || 'New certification';
                case 'awards':         return d.title || 'New award';
                case 'languages':      return d.name || 'New language';
                case 'links':          return d.label || d.url || 'New link';
                default:               return d.title || 'Entry';
            }
        },

        // Inline form HTML — rendered with x-html. Inputs call window.__resumeBus
        // to bridge back to Alpine state since x-html escapes Alpine directives.
        renderItemForm(type, item) {
            const d = item.data || {};
            const id = item.id;
            const onInput = (field) =>
                `oninput="window.__resumeBus.update(${id}, '${type}', '${field}', this.value)"`;
            const onCheck = (field) =>
                `onchange="window.__resumeBus.update(${id}, '${type}', '${field}', this.checked)"`;
            const v = (s) => (s == null ? '' : String(s)
                .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                .replace(/"/g,'&quot;'));
            const tInput = (label, field, attrs='') =>
                `<div class="resume-field"><label>${label}</label>
                  <input class="resume-input" type="text" value="${v(d[field])}" ${attrs} ${onInput(field)}></div>`;
            const tMonth = (label, field) =>
                `<div class="resume-field"><label>${label}</label>
                  <input class="resume-input" type="month" value="${v(d[field])}" ${onInput(field)}></div>`;
            const tArea = (label, field, max=2000) =>
                `<div class="resume-field"><label>${label}</label>
                  <textarea class="resume-textarea" rows="3" maxlength="${max}" ${onInput(field)}>${v(d[field])}</textarea></div>`;
            const tUrl = (label, field) =>
                `<div class="resume-field"><label>${label}</label>
                  <input class="resume-input" type="url" placeholder="https://…" value="${v(d[field])}" ${onInput(field)}></div>`;

            switch (type) {
                case 'experience':
                    return `<div class="resume-field-row">${tInput('Role','role','maxlength="160"')}${tInput('Company','company','maxlength="160"')}</div>
                            <div class="resume-field-row">${tInput('Location','location','maxlength="160"')}
                              <div class="resume-field"><label>Currently working here</label>
                                <input type="checkbox" ${d.is_current ? 'checked' : ''} ${onCheck('is_current')}></div></div>
                            <div class="resume-field-row">${tMonth('Start date','start_date')}${tMonth('End date','end_date')}</div>
                            ${tArea('Description','description')}`;
                case 'education':
                    return `<div class="resume-field-row">${tInput('School','school','maxlength="160"')}${tInput('Degree','degree','maxlength="160"')}</div>
                            <div class="resume-field-row">${tInput('Field of study','field','maxlength="160"')}<div></div></div>
                            <div class="resume-field-row">${tMonth('Start date','start_date')}${tMonth('End date','end_date')}</div>
                            ${tArea('Description','description', 1000)}`;
                case 'skills':
                    const stars = [1,2,3,4,5].map(n =>
                        `<button type="button" class="${(d.level||3) >= n ? 'on' : ''}"
                                 onclick="window.__resumeBus.update(${id},'skills','level',${n})">★</button>`).join('');
                    return `<div class="resume-field-row">${tInput('Skill','name','maxlength="80"')}${tInput('Group (optional)','group','maxlength="80"')}</div>
                            <div class="resume-field"><label>Proficiency</label><div class="level-stars">${stars}</div></div>`;
                case 'projects':
                    return `<div class="resume-field-row">${tInput('Project name','name','maxlength="160"')}${tInput('Your role','role','maxlength="160"')}</div>
                            ${tUrl('URL','url')}
                            <div class="resume-field-row">${tMonth('Start date','start_date')}${tMonth('End date','end_date')}</div>
                            ${tArea('Description','description')}`;
                case 'certifications':
                    return `<div class="resume-field-row">${tInput('Name','name','maxlength="160"')}${tInput('Issuer','issuer','maxlength="160"')}</div>
                            <div class="resume-field-row">${tMonth('Issued','issued_on')}${tMonth('Expires','expires_on')}</div>
                            ${tUrl('Credential URL','credential_url')}`;
                case 'awards':
                    return `<div class="resume-field-row">${tInput('Title','title','maxlength="160"')}${tInput('Issuer','issuer','maxlength="160"')}</div>
                            <div class="resume-field-row">${tMonth('Date','date')}<div></div></div>
                            ${tArea('Description','description', 1000)}`;
                case 'languages':
                    const opts = ['basic','conversational','professional','fluent','native']
                        .map(l => `<option value="${l}" ${d.proficiency===l?'selected':''}>${l[0].toUpperCase()+l.slice(1)}</option>`).join('');
                    return `<div class="resume-field-row">${tInput('Language','name','maxlength="80"')}
                            <div class="resume-field"><label>Proficiency</label>
                              <select class="resume-select" onchange="window.__resumeBus.update(${id},'languages','proficiency',this.value)">${opts}</select></div></div>`;
                case 'links':
                    return `<div class="resume-field-row">${tInput('Label','label','maxlength="80"')}${tInput('Icon (optional)','icon','maxlength="40"')}</div>
                            ${tUrl('URL','url')}`;
                case 'custom':
                    return `<div class="resume-field-row">${tInput('Title','title','maxlength="160"')}${tInput('Subtitle','subtitle','maxlength="160"')}</div>
                            <div class="resume-field-row">${tMonth('Date','date')}${tUrl('URL','url')}</div>
                            ${tArea('Description','description')}`;
                default: return '';
            }
        },

        // ── custom sections ───────────────────────────────────
        async createCustomSection() {
            const title = (this.newCustomTitle || '').trim();
            if (!title) return;
            const key = title.toLowerCase().replace(/[^a-z0-9_]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 40)
                || ('s_' + Math.random().toString(36).slice(2,8));
            try {
                this.markSaving();
                const r = await this.http('POST', '{{ route('user.resume.sections.store') }}', { key, title });
                this.hydrate(r.resume);
                this.newCustomTitle = '';
                this.renderPreview();
                this.markSaved();
            } catch (e) { this.markError(e.message); }
        },
        renameCustomSection(key, title) {
            this.resume.sections.custom_sections = this.resume.sections.custom_sections.map(s =>
                s.key === key ? Object.assign({}, s, { title }) : s);
            this.renderPreview();
            this.debounce('cs:' + key, async () => {
                await this.http('PUT', '/user/resume/sections/' + key, { title });
            });
        },
        async removeCustomSection(key) {
            if (!confirm('Delete this section and all its entries?')) return;
            try {
                this.markSaving();
                await this.http('DELETE', '/user/resume/sections/' + key);
                this.resume.sections.custom_sections = this.resume.sections.custom_sections.filter(s => s.key !== key);
                this.items.custom = (this.items.custom || []).filter(i => (i.data || {}).custom_section_key !== key);
                this.renderPreview();
                this.markSaved();
            } catch (e) { this.markError(e.message); }
        },

        // ── PREVIEW ───────────────────────────────────────────
        renderPreview() {
            this.previewHtml = this.buildPreviewHtml(this.resume.sections, this.items);
        },
        // Pure render: produces the inline-styled preview HTML for any
        // (sections, items) pair. Reused for both the live editor preview
        // and the import-modal "what will it look like?" pane so picks
        // reflect immediately without round-tripping the merge API.
        buildPreviewHtml(sections, itemsArg) {
            const tpl = this.resume.template || {};
            const theme = (this.resume.color_theme && this.resume.color_theme.tokens) || {
                primary:'#111827', accent:'#4b5563', text:'#1f2937', muted:'#6b7280', background:'#ffffff' };
            const style = tpl.style || { layout: 'single', headings: 'sans', density: 'comfortable' };
            const layout = style.layout || 'single';

            const esc = (s) => s == null ? '' : String(s)
                .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
            const fmtMonth = (s) => {
                if (!s) return '';
                const m = /^(\d{4})-(\d{2})$/.exec(s); if (!m) return esc(s);
                const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                return months[parseInt(m[2],10)-1] + ' ' + m[1];
            };
            const dateRange = (s, e, current) => {
                const parts = [fmtMonth(s), current ? 'Present' : fmtMonth(e)].filter(Boolean);
                return parts.join(' – ');
            };

            const h = sections.header || {};
            const summary = sections.summary || '';
            const items = itemsArg;

            const sectionBox = (title, body, key='') =>
                body ? `<section class="pv-section" data-key="${esc(key)}"><h2 style="color:${theme.primary}; border-color:${theme.primary}">${esc(title)}</h2>${body}</section>` : '';

            const expBlock = (arr) => (arr||[]).map(it => {
                const d = it.data||{};
                return `<div class="pv-item">
                    <div class="pv-item-row">
                        <div><div class="pv-item-title">${esc(d.role)||''}</div>
                             <div class="pv-item-sub" style="color:${theme.accent}">${esc(d.company)||''}${d.location?'<span style="color:'+theme.muted+'"> · '+esc(d.location)+'</span>':''}</div></div>
                        <div class="pv-item-meta" style="color:${theme.muted}">${esc(dateRange(d.start_date, d.end_date, d.is_current))}</div>
                    </div>
                    ${d.description?`<div class="pv-item-desc">${esc(d.description)}</div>`:''}
                </div>`;
            }).join('');

            const eduBlock = (arr) => (arr||[]).map(it => {
                const d = it.data||{};
                return `<div class="pv-item">
                    <div class="pv-item-row">
                        <div><div class="pv-item-title">${esc(d.school)||''}</div>
                             <div class="pv-item-sub" style="color:${theme.accent}">${[esc(d.degree), esc(d.field)].filter(Boolean).join(', ')}</div></div>
                        <div class="pv-item-meta" style="color:${theme.muted}">${esc(dateRange(d.start_date, d.end_date, false))}</div>
                    </div>
                    ${d.description?`<div class="pv-item-desc">${esc(d.description)}</div>`:''}
                </div>`;
            }).join('');

            const skillBlock = (arr) => {
                if (!arr || !arr.length) return '';
                return `<div class="pv-skill-row" style="color:${theme.accent}">${arr.map(it => {
                    const d = it.data||{};
                    const lvl = d.level ? ' '+'★'.repeat(Math.max(0,Math.min(5,d.level))) : '';
                    return `<span class="pv-skill-pill">${esc(d.name)}${lvl}</span>`;
                }).join('')}</div>`;
            };

            const projBlock = (arr, portfolio=false) => {
                if (!arr || !arr.length) return '';
                if (portfolio) {
                    return `<div class="pv-portfolio-grid" style="color:${theme.accent}">${arr.map(it => {
                        const d = it.data||{};
                        return `<div class="pv-portfolio-card">
                            <div class="pv-item-title" style="color:${theme.primary}">${esc(d.name)||''}</div>
                            <div class="pv-item-sub" style="color:${theme.muted}">${esc(d.role)||''}</div>
                            ${d.description?`<div class="pv-item-desc" style="color:${theme.text}">${esc(d.description)}</div>`:''}
                            ${d.url?`<div style="margin-top:6px"><a class="pv-link" style="color:${theme.accent}" href="${esc(d.url)}">${esc(d.url)}</a></div>`:''}
                        </div>`;
                    }).join('')}</div>`;
                }
                return arr.map(it => {
                    const d = it.data||{};
                    return `<div class="pv-item">
                        <div class="pv-item-row">
                            <div><div class="pv-item-title">${esc(d.name)||''}</div>
                                 <div class="pv-item-sub" style="color:${theme.accent}">${esc(d.role)||''}</div></div>
                            <div class="pv-item-meta" style="color:${theme.muted}">${esc(dateRange(d.start_date, d.end_date, false))}</div>
                        </div>
                        ${d.description?`<div class="pv-item-desc">${esc(d.description)}</div>`:''}
                        ${d.url?`<div><a class="pv-link" style="color:${theme.accent}" href="${esc(d.url)}">${esc(d.url)}</a></div>`:''}
                    </div>`;
                }).join('');
            };

            const certBlock = (arr) => (arr||[]).map(it => {
                const d=it.data||{};
                return `<div class="pv-item">
                    <div class="pv-item-row">
                        <div><div class="pv-item-title">${esc(d.name)||''}</div>
                            <div class="pv-item-sub" style="color:${theme.accent}">${esc(d.issuer)||''}</div></div>
                        <div class="pv-item-meta" style="color:${theme.muted}">${esc(fmtMonth(d.issued_on))}${d.expires_on?' – '+esc(fmtMonth(d.expires_on)):''}</div>
                    </div>
                    ${d.credential_url?`<div><a class="pv-link" style="color:${theme.accent}" href="${esc(d.credential_url)}">${esc(d.credential_url)}</a></div>`:''}
                </div>`;
            }).join('');

            const awardBlock = (arr) => (arr||[]).map(it => {
                const d=it.data||{};
                return `<div class="pv-item">
                    <div class="pv-item-row">
                        <div><div class="pv-item-title">${esc(d.title)||''}</div>
                            <div class="pv-item-sub" style="color:${theme.accent}">${esc(d.issuer)||''}</div></div>
                        <div class="pv-item-meta" style="color:${theme.muted}">${esc(fmtMonth(d.date))}</div>
                    </div>
                    ${d.description?`<div class="pv-item-desc">${esc(d.description)}</div>`:''}
                </div>`;
            }).join('');

            const langBlock = (arr) => {
                if (!arr || !arr.length) return '';
                return `<div class="pv-skill-row" style="color:${theme.accent}">${arr.map(it => {
                    const d=it.data||{};
                    return `<span class="pv-skill-pill">${esc(d.name)}${d.proficiency?` · ${esc(d.proficiency)}`:''}</span>`;
                }).join('')}</div>`;
            };

            const linkBlock = (arr) => {
                if (!arr || !arr.length) return '';
                return `<div style="display:flex; flex-direction:column; gap:3px">${arr.map(it => {
                    const d=it.data||{};
                    return `<a class="pv-link" style="color:${theme.accent}" href="${esc(d.url)}">${esc(d.label)||esc(d.url)}</a>`;
                }).join('')}</div>`;
            };

            const customBlocks = () => {
                const customSecs = sections.custom_sections || [];
                return customSecs.map(s => {
                    const its = (items.custom || []).filter(i => (i.data||{}).custom_section_key === s.key);
                    if (!its.length) return '';
                    const body = its.map(it => {
                        const d=it.data||{};
                        return `<div class="pv-item">
                            <div class="pv-item-row">
                                <div><div class="pv-item-title">${esc(d.title)||''}</div>
                                    <div class="pv-item-sub" style="color:${theme.accent}">${esc(d.subtitle)||''}</div></div>
                                <div class="pv-item-meta" style="color:${theme.muted}">${esc(fmtMonth(d.date))}</div>
                            </div>
                            ${d.description?`<div class="pv-item-desc">${esc(d.description)}</div>`:''}
                            ${d.url?`<div><a class="pv-link" style="color:${theme.accent}" href="${esc(d.url)}">${esc(d.url)}</a></div>`:''}
                        </div>`;
                    }).join('');
                    return sectionBox(s.title || s.key, body);
                }).join('');
            };

            const headerStyle = style.header_style || 'rule';
            const monogram = (esc(h.name)||'').trim().split(/\s+/).map(s=>s[0]||'').slice(0,2).join('').toUpperCase();
            const headerText = `
                <h1 class="pv-name" style="color:${theme.primary}">${esc(h.name) || 'Your name'}</h1>
                ${h.headline?`<p class="pv-headline" style="color:${theme.accent}">${esc(h.headline)}</p>`:''}
                <div class="pv-contact" style="color:${theme.muted}">
                    ${h.email?`<span>${esc(h.email)}</span>`:''}
                    ${h.phone?`<span>${esc(h.phone)}</span>`:''}
                    ${h.location?`<span>${esc(h.location)}</span>`:''}
                    ${h.website?`<span>${esc(h.website)}</span>`:''}
                </div>`;
            const headerBlock = (h.photo_url && (headerStyle === 'photo-left' || headerStyle === 'sidebar-photo'))
                ? `<header class="rr-header" data-monogram="${monogram}" style="border-color:${theme.primary};">
                       <table style="width:100%; border-collapse:collapse;"><tbody><tr>
                           <td style="width:80px; vertical-align:top; padding:0 14px 0 0;">
                               <img src="${esc(h.photo_url)}" alt=""
                                    style="width:72px;height:72px;object-fit:cover;border-radius:50%;display:block;border:1px solid ${theme.primary}33;background:#fff;">
                           </td>
                           <td style="vertical-align:top; padding:0;">${headerText}</td>
                       </tr></tbody></table>
                   </header>`
                : `<header class="rr-header" data-monogram="${monogram}" style="border-color:${theme.primary};">${headerText}</header>`;

            const summaryBlock = summary ? sectionBox('Profile', `<div class="pv-summary">${esc(summary)}</div>`, 'summary') : '';

            const mainBlocks = [
                summaryBlock,
                sectionBox('Experience', expBlock(items.experience), 'experience'),
                sectionBox('Projects', projBlock(items.projects, false), 'projects'),
                sectionBox('Education', eduBlock(items.education), 'education'),
                sectionBox('Skills', skillBlock(items.skills), 'skills'),
                sectionBox('Certifications', certBlock(items.certifications), 'certifications'),
                sectionBox('Awards', awardBlock(items.awards), 'awards'),
                sectionBox('Languages', langBlock(items.languages), 'languages'),
                sectionBox('Links', linkBlock(items.links), 'links'),
                customBlocks(),
            ];

            // Layout assembly
            let body = '';
            if (layout === 'sidebar' || layout === 'sidebar-right') {
                const side = [
                    sectionBox('Skills', skillBlock(items.skills), 'skills'),
                    sectionBox('Languages', langBlock(items.languages), 'languages'),
                    sectionBox('Links', linkBlock(items.links), 'links'),
                ].join('');
                const main = [
                    summaryBlock,
                    sectionBox('Experience', expBlock(items.experience), 'experience'),
                    sectionBox('Projects', projBlock(items.projects, false), 'projects'),
                    sectionBox('Education', eduBlock(items.education), 'education'),
                    sectionBox('Certifications', certBlock(items.certifications), 'certifications'),
                    sectionBox('Awards', awardBlock(items.awards), 'awards'),
                    customBlocks(),
                ].join('');
                body = `${headerBlock}<div class="pv-sidebar"><div class="pv-side-col">${side}</div><div>${main}</div></div>`;
            } else if (layout === 'portfolio' || layout === 'portfolio-grid') {
                body = headerBlock + [
                    summaryBlock,
                    sectionBox('Featured projects', projBlock(items.projects, true), 'projects'),
                    sectionBox('Experience', expBlock(items.experience), 'experience'),
                    sectionBox('Skills', skillBlock(items.skills), 'skills'),
                    sectionBox('Education', eduBlock(items.education), 'education'),
                    sectionBox('Awards', awardBlock(items.awards), 'awards'),
                    sectionBox('Languages', langBlock(items.languages), 'languages'),
                    sectionBox('Links', linkBlock(items.links), 'links'),
                    customBlocks(),
                ].join('');
            } else if (layout === 'two-col') {
                body = headerBlock + summaryBlock
                    + `<div class="pv-twocol">${[
                        sectionBox('Experience', expBlock(items.experience), 'experience'),
                        sectionBox('Education', eduBlock(items.education), 'education'),
                        sectionBox('Projects', projBlock(items.projects, false), 'projects'),
                        sectionBox('Skills', skillBlock(items.skills), 'skills'),
                        sectionBox('Certifications', certBlock(items.certifications), 'certifications'),
                        sectionBox('Awards', awardBlock(items.awards), 'awards'),
                        sectionBox('Languages', langBlock(items.languages), 'languages'),
                        sectionBox('Links', linkBlock(items.links), 'links'),
                        customBlocks(),
                    ].join('')}</div>`;
            } else {
                body = headerBlock + mainBlocks.join('');
            }

            const fontClass = style.headings === 'serif' ? 'serif'
                : style.headings === 'display' ? 'display'
                : style.headings === 'mono' ? 'mono' : '';
            const densityClass = style.density === 'tight' ? 'tight'
                : style.density === 'spacious' ? 'spacious' : '';

            const rrCls = [
                'rr',
                'rr-layout-' + (style.layout || 'single'),
                'rr-h-' + headerStyle,
                'rr-d-' + (style.divider || 'rule'),
                'rr-a-' + (style.accent || 'none'),
                'rr-t-' + (style.title_style || 'uppercase'),
            ].join(' ');

            // Wrap with theme background applied to the page
            return `<style>.preview-page{background:${theme.background};color:${theme.text}}</style>` +
                `<div class="${rrCls} ${fontClass} ${densityClass}" style="background:${theme.background}; color:${theme.text}; --rr-accent:${theme.accent}; min-height: 800px; margin:-32px -36px; padding:32px 36px;">${body}</div>`;
        },

        // Build a temporary (sections, items) pair that mirrors what the
        // server-side merge would produce for the current picks, so the
        // import preview matches what the user is about to commit. We
        // mirror header replace/append/skip and summary replace/append/skip
        // semantics from ResumeImportService::merge() — exact duplication
        // isn't required (this is preview only) but it should be close.
        mergedForPreview() {
            const sections = JSON.parse(JSON.stringify(this.resume.sections || {}));
            if (!sections.header) sections.header = {};
            const items = {};
            Object.keys(this.items || {}).forEach(k => {
                items[k] = (this.items[k] || []).map(it => ({
                    id: it.id,
                    data: Object.assign({}, it.data || {}),
                }));
            });

            const cand  = this.importCandidates || {};
            const picks = this.importPicks || {};

            // Header
            const hdr = picks.header || { mode: 'skip', fields: [] };
            if (hdr.mode && hdr.mode !== 'skip' && cand.header) {
                (hdr.fields || []).forEach(f => {
                    const incoming = cand.header[f];
                    if (incoming == null || incoming === '') return;
                    const current = sections.header[f] || '';
                    if (hdr.mode === 'replace' || current === '') {
                        sections.header[f] = incoming;
                    } else if (hdr.mode === 'append' && current !== incoming) {
                        sections.header[f] = current + ' · ' + incoming;
                    }
                });
            }

            // Summary
            const sum = picks.summary || { mode: 'skip' };
            if (sum.mode && sum.mode !== 'skip' && cand.summary) {
                const current = sections.summary || '';
                if (sum.mode === 'replace' || current === '') {
                    sections.summary = cand.summary;
                } else if (sum.mode === 'append'
                        && current.toLowerCase().indexOf(cand.summary.toLowerCase()) === -1) {
                    sections.summary = (current ? current + '\n\n' : '') + cand.summary;
                }
            }

            // Items — append picked candidates onto the corresponding lists
            // with synthetic ids so the renderer treats them like real items.
            (picks.items || []).forEach(idx => {
                const c = (cand.items || [])[idx];
                if (!c || !c.section_type) return;
                const t = c.section_type;
                if (!items[t]) items[t] = [];
                items[t].push({ id: '__import_' + idx, data: Object.assign({}, c.data || {}) });
            });

            return { sections, items };
        },

        renderImportPreview() {
            if (this.importStep !== 'review') return;
            const { sections, items } = this.mergedForPreview();
            this.importPreviewHtml = this.buildPreviewHtml(sections, items);
        },

        // ── IMPORT FLOW ───────────────────────────────────────
        // Open the modal in pick-method mode and reset previous state so
        // a second import doesn't show stale candidates.
        openImport() {
            this.importOpen = true;
            this.importStep = 'pick';
            this.importTab  = 'file';
            this.importFile = null;
            this.importLinkedinUrl = '';
            this.importAiPrompt = '';
            this.importAiSections = ['summary','experience','skills'];
            this.importError = '';
            this.importCandidates = { header: {}, summary: '', items: [], notes: null };
            this.importPicks = { header: { mode: 'replace', fields: [] }, summary: { mode: 'replace' }, items: [] };
            this.importPreviewHtml = '';
        },
        closeImport() { this.importOpen = false; this.importBusy = false; this.importPreviewHtml = ''; },

        // FormData variant of http() — needed for multipart uploads.
        // We deliberately don't set Content-Type so the browser fills in
        // the multipart boundary itself.
        async httpForm(url, formData) {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: formData,
            });
            if (!res.ok) {
                let msg = 'Request failed';
                try { const j = await res.json(); msg = j.message || msg; } catch (e) {}
                throw new Error(msg);
            }
            return res.json();
        },

        // Per-method runners. Each one populates importCandidates and
        // jumps to the Review step on success; errors stay on the picker
        // so the user can adjust their input without losing it.
        async runImportFile() {
            if (!this.importFile) return;
            this.importBusy = true; this.importError = '';
            try {
                const fd = new FormData(); fd.append('file', this.importFile);
                const r = await this.httpForm('{{ route('user.resume.import.file') }}', fd);
                this.afterParse(r.candidates);
            } catch (e) { this.importError = e.message; }
            finally { this.importBusy = false; }
        },
        async runImportLinkedin() {
            this.importBusy = true; this.importError = '';
            try {
                const fd = new FormData();
                if (this.importLinkedinUrl) fd.append('url', this.importLinkedinUrl);
                if (this.importFile)        fd.append('file', this.importFile);
                const r = await this.httpForm('{{ route('user.resume.import.linkedin') }}', fd);
                this.afterParse(r.candidates);
            } catch (e) { this.importError = e.message; }
            finally { this.importBusy = false; }
        },
        async runImportBiolink() {
            this.importBusy = true; this.importError = '';
            try {
                const r = await this.http('POST', '{{ route('user.resume.import.biolink') }}', {});
                this.afterParse(r.candidates);
            } catch (e) { this.importError = e.message; }
            finally { this.importBusy = false; }
        },
        async runImportAi() {
            this.importBusy = true; this.importError = '';
            try {
                const r = await this.http('POST', '{{ route('user.resume.import.ai') }}', {
                    prompt:   this.importAiPrompt,
                    sections: this.importAiSections,
                });
                this.afterParse(r.candidates);
            } catch (e) { this.importError = e.message; }
            finally { this.importBusy = false; }
        },

        // Common post-parse setup: store candidates, pre-select all list
        // items + every populated header field so the default action is
        // "merge everything", and switch to the Review step.
        afterParse(candidates) {
            this.importCandidates = Object.assign({ header: {}, summary: '', items: [], notes: null }, candidates || {});
            const headerKeys = Object.keys(this.importCandidates.header || {});
            const itemIdxs = (this.importCandidates.items || []).map((_, i) => i);
            this.importPicks = {
                header:  { mode: headerKeys.length ? 'replace' : 'skip', fields: headerKeys },
                summary: { mode: this.importCandidates.summary ? 'replace' : 'skip' },
                items:   itemIdxs,
            };
            this.importStep = 'review';
        },

        // ── Review helpers ──
        hasHeaderCandidates() {
            return this.importCandidates.header && Object.keys(this.importCandidates.header).length > 0;
        },
        hasAnyCandidates() {
            return this.hasHeaderCandidates() || this.importCandidates.summary
                || (this.importCandidates.items || []).length > 0;
        },
        groupedCandidateItems() {
            const meta = {};
            this.listSections.forEach(s => meta[s.key] = { type: s.key, label: s.label, icon: s.icon, items: [] });
            (this.importCandidates.items || []).forEach((cand, idx) => {
                const t = cand.section_type;
                if (!meta[t]) return;
                meta[t].items.push({ idx, cand });
            });
            return Object.values(meta).filter(g => g.items.length);
        },
        selectAllOfType(type, on) {
            const idxs = (this.importCandidates.items || [])
                .map((c, i) => c.section_type === type ? i : -1).filter(i => i >= 0);
            const set = new Set(this.importPicks.items);
            idxs.forEach(i => on ? set.add(i) : set.delete(i));
            this.importPicks.items = Array.from(set);
        },
        pickCount() {
            const items = (this.importPicks.items || []).length;
            const hdr = (this.importPicks.header.mode !== 'skip')
                ? (this.importPicks.header.fields || []).length : 0;
            const sum = (this.importPicks.summary.mode !== 'skip' && this.importCandidates.summary) ? 1 : 0;
            return items + hdr + sum;
        },
        // One-line description per candidate row, type-aware so the user
        // can tell two "Acme Corp" experience entries apart in Review.
        describeCandidate(cand) {
            const d = cand.data || {};
            switch (cand.section_type) {
                case 'experience':     return [d.role, d.company].filter(Boolean).join(' @ ') || '(unnamed role)';
                case 'education':      return [d.school, d.degree].filter(Boolean).join(' — ') || '(unnamed school)';
                case 'skills':         return d.name || '(skill)';
                case 'projects':       return d.name || '(project)';
                case 'certifications': return d.name || '(certification)';
                case 'awards':         return d.title || '(award)';
                case 'languages':      return d.name || '(language)';
                case 'links':          return d.label || d.url || '(link)';
            }
            return '(item)';
        },
        describeCandidateSub(cand) {
            const d = cand.data || {};
            const dr = (s,e,c) => {
                const fmt = v => /^\d{4}-\d{2}$/.test(v||'') ? v : '';
                const a = fmt(s), b = c ? 'Present' : fmt(e);
                return [a,b].filter(Boolean).join(' – ');
            };
            switch (cand.section_type) {
                case 'experience':     return [dr(d.start_date,d.end_date,d.is_current), d.location].filter(Boolean).join(' · ') || (d.description||'').slice(0,80);
                case 'education':      return [dr(d.start_date,d.end_date,false), d.field].filter(Boolean).join(' · ');
                case 'skills':         return d.level ? '★'.repeat(Math.max(0,Math.min(5,d.level))) : '';
                case 'projects':       return [d.role, d.url].filter(Boolean).join(' · ');
                case 'certifications': return [d.issuer, d.issued_on].filter(Boolean).join(' · ');
                case 'awards':         return [d.issuer, d.date].filter(Boolean).join(' · ');
                case 'languages':      return d.proficiency || '';
                case 'links':          return d.url || '';
            }
            return '';
        },

        // ── TAILOR FLOW ───────────────────────────────────────
        // Open the tailor modal: reset state and load history so the
        // user can see (and reuse) their recent runs.
        openTailor() {
            this.tailorOpen = true;
            this.tailorStep = 'pick';
            this.tailorBusy = false;
            this.tailorError = '';
            this.tailorJd = '';
            this.tailorEstimate = null;
            this.tailorLastSpent = 0;
            this.tailorSuggestions = { summary: null, experience: [], skills: { additions: [] }, keywords: [] };
            this.tailorPicks = { summary: false, experience: [], skills: [] };
            this.loadTailorHistory();
        },
        closeTailor() { this.tailorOpen = false; this.tailorBusy = false; },

        async loadTailorHistory() {
            try {
                const r = await this.http('GET', '{{ route('user.resume.tailor.history') }}');
                this.tailorHistory = r.runs || [];
            } catch (e) { /* non-fatal */ }
        },

        // Debounced upfront cost lookup. We tag each request with a
        // monotonically-increasing seq so that a slow earlier response
        // can't overwrite a faster later one.
        async refreshTailorEstimate() {
            const jd = (this.tailorJd || '').trim();
            if (jd.length < 30) { this.tailorEstimate = null; return; }
            const seq = ++this._tailorEstimateSeq;
            try {
                const r = await this.http('POST', '{{ route('user.resume.tailor.estimate') }}', { job_description: jd });
                if (seq !== this._tailorEstimateSeq) return;
                this.tailorEstimate = r.estimated_credits;
                this.tailorBalance = r.balance;
            } catch (e) { /* leave estimate as-is */ }
        },

        async runTailor() {
            const jd = (this.tailorJd || '').trim();
            if (jd.length < 30 || this.tailorBusy) return;
            this.tailorBusy = true; this.tailorError = '';
            try {
                const r = await this.http('POST', '{{ route('user.resume.tailor.run') }}', { job_description: jd });
                this.tailorSuggestions = r.suggestions;
                this.tailorBalance     = r.balance;
                this.tailorLastSpent   = r.credits_spent;
                this.tailorHistory     = r.history || this.tailorHistory;
                // Default to "accept everything" so users can one-click
                // apply when the suggestions look good.
                this.tailorPicks = {
                    summary:    !!(r.suggestions.summary && r.suggestions.summary.changed),
                    experience: (r.suggestions.experience || []).map(x => x.item_id),
                    skills:     (r.suggestions.skills && r.suggestions.skills.additions || []).map((_, i) => i),
                };
                this.tailorStep = 'review';
            } catch (e) {
                this.tailorError = e.message;
            } finally {
                this.tailorBusy = false;
            }
        },

        async applyTailor() {
            if (this.tailorBusy || !this.tailorAcceptCount()) return;
            this.tailorBusy = true; this.tailorError = '';
            try {
                const r = await this.http('POST', '{{ route('user.resume.tailor.apply') }}', {
                    suggestions: this.tailorSuggestions,
                    picks:       this.tailorPicks,
                });
                this.hydrate(r.resume);
                this.renderPreview();
                this.markSaved();
                const c = r.changed || {};
                const bits = [];
                if (c.summary)            bits.push('summary');
                if (c.experience)         bits.push(c.experience + ' bullet' + (c.experience===1?'':'s'));
                if (c.skills)             bits.push(c.skills + ' skill' + (c.skills===1?'':'s'));
                this.showToast('Applied ' + (bits.join(', ') || 'changes') + '.', 'success');
                this.closeTailor();
            } catch (e) {
                this.tailorError = e.message;
            } finally {
                this.tailorBusy = false;
            }
        },

        tailorAcceptCount() {
            return (this.tailorPicks.summary ? 1 : 0)
                + (this.tailorPicks.experience || []).length
                + (this.tailorPicks.skills || []).length;
        },
        tailorHasAnyChanges() {
            const s = this.tailorSuggestions || {};
            return (s.summary && s.summary.changed)
                || (s.experience && s.experience.length)
                || (s.skills && (s.skills.additions || []).length);
        },
        acceptAllExp(on) {
            this.tailorPicks.experience = on
                ? (this.tailorSuggestions.experience || []).map(x => x.item_id) : [];
        },
        acceptAllSkills(on) {
            this.tailorPicks.skills = on
                ? ((this.tailorSuggestions.skills && this.tailorSuggestions.skills.additions) || []).map((_, i) => i) : [];
        },
        formatTailorWhen(iso) {
            if (!iso) return '';
            try {
                const d = new Date(iso);
                return d.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
            } catch (e) { return iso; }
        },

        // Word-level diff used in the side-by-side panes. We compute
        // the longest-common-subsequence on whitespace-split tokens and
        // emit `<ins>` for additions, `<del>` for removals, and
        // `<mark class="kw">` for tokens that appear in both texts and
        // also in the JD-keyword list (so users can see which JD terms
        // landed). `mode` is 'old' (omit ins) or 'new' (omit del).
        renderTailorDiff(oldText, newText, mode) {
            const esc = (s) => String(s).replace(/[&<>]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]));
            const a = String(oldText || '').split(/(\s+)/);
            const b = String(newText || '').split(/(\s+)/);
            const n = a.length, m = b.length;
            // Bail out cheaply for very large inputs to keep the editor responsive.
            if (n * m > 90000) {
                return mode === 'old' ? esc(oldText || '') : esc(newText || '');
            }
            const dp = Array.from({ length: n + 1 }, () => new Uint16Array(m + 1));
            for (let i = n - 1; i >= 0; i--) for (let j = m - 1; j >= 0; j--) {
                dp[i][j] = a[i] === b[j] ? dp[i+1][j+1] + 1 : Math.max(dp[i+1][j], dp[i][j+1]);
            }
            const kws = new Set((this.tailorSuggestions.keywords || []).map(k => String(k).toLowerCase()));
            const out = [];
            let i = 0, j = 0;
            const wrapKw = (tok) => kws.has(tok.toLowerCase().replace(/[^\p{L}\p{N}+\-#.]/gu, '')) && tok.trim()
                ? '<mark class="kw">' + esc(tok) + '</mark>' : esc(tok);
            while (i < n && j < m) {
                if (a[i] === b[j]) { out.push(wrapKw(a[i])); i++; j++; }
                else if (dp[i+1][j] >= dp[i][j+1]) {
                    if (mode === 'old') out.push('<del>' + esc(a[i]) + '</del>');
                    i++;
                } else {
                    if (mode === 'new') out.push('<ins>' + esc(b[j]) + '</ins>');
                    j++;
                }
            }
            while (i < n) { if (mode === 'old') out.push('<del>' + esc(a[i]) + '</del>'); i++; }
            while (j < m) { if (mode === 'new') out.push('<ins>' + esc(b[j]) + '</ins>'); j++; }
            return out.join('');
        },

        // ── COVER LETTER FLOW ─────────────────────────────────
        // Open the cover-letter modal: reset state, load saved letters
        // so the creator can revisit any previous draft without paying
        // for a fresh generation.
        openCoverLetter() {
            this.coverLetterOpen = true;
            this.coverStep = 'pick';
            this.coverBusy = false;
            this.coverError = '';
            this.coverJd = '';
            this.coverTone = 'professional';
            this.coverPersonaId = null;
            this.coverEstimate = null;
            this.coverLetter = null;
            this.coverSectionBusy = '';
            this.coverCopied = false;
            this.loadCoverHistory();
        },
        closeCoverLetter() { this.coverLetterOpen = false; this.coverBusy = false; this.coverSectionBusy = ''; },

        coverToneHint() {
            switch (this.coverTone) {
                case 'warm':    return 'Personable and slightly conversational. Shows enthusiasm without being overfamiliar.';
                case 'concise': return 'No-fluff voice with short sentences. Body is kept to two paragraphs maximum.';
                default:        return 'Professional and confident. Focused paragraphs, free of clichés.';
            }
        },

        async loadCoverHistory() {
            try {
                const r = await this.http('GET', '{{ route('user.resume.cover-letters.index') }}');
                this.coverHistory = r.letters || [];
                this.coverPersonas = r.personas || [];
                this.coverBalance = r.balance ?? this.coverBalance;
                // If the previously-picked persona was deleted between
                // opens, reset the picker to "None" so we don't send a
                // stale id (the server would drop it anyway).
                if (this.coverPersonaId &&
                    !this.coverPersonas.some(p => p.id === this.coverPersonaId)) {
                    this.coverPersonaId = null;
                }
            } catch (e) { /* non-fatal */ }
        },

        // Debounced upfront cost lookup. Tagged with a sequence so a
        // slower earlier response can't overwrite a fresher later one.
        async refreshCoverEstimate() {
            const jd = (this.coverJd || '').trim();
            if (jd.length < 30) { this.coverEstimate = null; return; }
            const seq = ++this._coverEstimateSeq;
            try {
                const r = await this.http('POST', '{{ route('user.resume.cover-letters.estimate') }}',
                    { job_description: jd, tone: this.coverTone, persona_id: this.coverPersonaId });
                if (seq !== this._coverEstimateSeq) return;
                this.coverEstimate = r.estimated_credits;
                this.coverBalance  = r.balance;
            } catch (e) { /* leave estimate as-is */ }
        },

        async runCoverLetter() {
            const jd = (this.coverJd || '').trim();
            if (jd.length < 30 || this.coverBusy) return;
            this.coverBusy = true; this.coverError = '';
            try {
                const r = await this.http('POST', '{{ route('user.resume.cover-letters.store') }}',
                    { job_description: jd, tone: this.coverTone, persona_id: this.coverPersonaId });
                this.coverLetter  = this.normalizeCoverLetter(r.letter);
                this.coverBalance = r.balance;
                this.coverHistory = r.history || this.coverHistory;
                this.coverStep    = 'edit';
            } catch (e) {
                this.coverError = e.message;
            } finally {
                this.coverBusy = false;
            }
        },

        async loadCoverLetter(id) {
            if (!id) return;
            this.coverError = '';
            try {
                const r = await this.http('GET', '{{ url('/user/resume/cover-letters') }}/' + id);
                this.coverLetter = this.normalizeCoverLetter(r.letter);
                this.coverStep   = 'edit';
                this.coverCopied = false;
            } catch (e) {
                this.coverError = e.message;
            }
        },

        normalizeCoverLetter(l) {
            if (!l) return null;
            const c = l.content || {};
            return Object.assign({}, l, {
                content: {
                    greeting: String(c.greeting || ''),
                    body:     Array.isArray(c.body) ? c.body.map(p => String(p || '')) : [],
                    sign_off: String(c.sign_off || ''),
                },
            });
        },

        async saveCoverEdits() {
            if (!this.coverLetter || this.coverSectionBusy) return;
            // Strip empty trailing paragraphs so the saved doc is tidy.
            const body = (this.coverLetter.content.body || [])
                .map(p => String(p || '').trim())
                .filter(p => p.length);
            try {
                const r = await this.http('PATCH',
                    '{{ url('/user/resume/cover-letters') }}/' + this.coverLetter.id,
                    {
                        title: this.coverLetter.title,
                        content: {
                            greeting: this.coverLetter.content.greeting || '',
                            body:     body,
                            sign_off: this.coverLetter.content.sign_off || '',
                        },
                    });
                // Sync any history-row title / updated_at without
                // clobbering the in-progress edit buffer.
                const idx = this.coverHistory.findIndex(h => h.id === r.letter.id);
                if (idx >= 0) {
                    this.coverHistory[idx] = Object.assign({}, this.coverHistory[idx], {
                        title:      r.letter.title,
                        updated_at: r.letter.updated_at,
                    });
                }
            } catch (e) {
                this.coverError = e.message;
            }
        },

        async regenCoverSection(section) {
            if (!this.coverLetter || this.coverSectionBusy) return;
            this.coverSectionBusy = section;
            this.coverError = '';
            try {
                const r = await this.http('POST',
                    '{{ url('/user/resume/cover-letters') }}/' + this.coverLetter.id + '/regenerate',
                    { section: section });
                this.coverLetter  = this.normalizeCoverLetter(r.letter);
                this.coverBalance = r.balance;
                // Refresh history row so the new credits_spent total shows.
                const idx = this.coverHistory.findIndex(h => h.id === r.letter.id);
                if (idx >= 0) {
                    this.coverHistory[idx] = Object.assign({}, this.coverHistory[idx], {
                        credits_spent: r.letter.credits_spent,
                        updated_at:    r.letter.updated_at,
                    });
                }
            } catch (e) {
                this.coverError = e.message;
            } finally {
                this.coverSectionBusy = '';
            }
        },

        addCoverParagraph() {
            if (!this.coverLetter) return;
            const body = this.coverLetter.content.body || [];
            if (body.length >= 5) return;
            body.push('');
            this.coverLetter.content.body = body;
        },
        removeCoverParagraph(idx) {
            if (!this.coverLetter) return;
            const body = (this.coverLetter.content.body || []).slice();
            body.splice(idx, 1);
            this.coverLetter.content.body = body;
            this.saveCoverEdits();
        },

        async copyCoverLetter() {
            if (!this.coverLetter) return;
            const c = this.coverLetter.content || {};
            const body = (c.body || []).map(p => String(p || '').trim()).filter(p => p.length);
            const parts = [];
            if (c.greeting) parts.push(c.greeting);
            if (body.length) parts.push(body.join('\n\n'));
            if (c.sign_off) parts.push(c.sign_off);
            const text = parts.join('\n\n');
            try {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    await navigator.clipboard.writeText(text);
                } else {
                    // execCommand fallback for older browsers.
                    const ta = document.createElement('textarea');
                    ta.value = text;
                    ta.style.position = 'fixed'; ta.style.opacity = '0';
                    document.body.appendChild(ta);
                    ta.select(); document.execCommand('copy');
                    document.body.removeChild(ta);
                }
                this.coverCopied = true;
                setTimeout(() => { this.coverCopied = false; }, 1800);
            } catch (e) {
                this.coverError = 'Could not copy to clipboard.';
            }
        },

        async deleteCoverLetter(id) {
            if (!id) return;
            if (!confirm('Delete this cover letter? This cannot be undone.')) return;
            try {
                const r = await this.http('DELETE', '{{ url('/user/resume/cover-letters') }}/' + id);
                this.coverHistory = r.history || [];
                if (this.coverLetter && this.coverLetter.id === id) {
                    this.coverLetter = null;
                    this.coverStep = 'pick';
                }
            } catch (e) {
                this.coverError = e.message;
            }
        },

        async applyMerge() {
            this.importBusy = true; this.importError = '';
            try {
                const r = await this.http('POST', '{{ route('user.resume.import.merge') }}', {
                    candidates: this.importCandidates,
                    picks:      this.importPicks,
                });
                this.hydrate(r.resume);
                this.renderPreview();
                this.markSaved();
                const c = r.changed || {};
                const bits = [];
                if (c.items)         bits.push(c.items + ' item' + (c.items===1?'':'s'));
                if (c.header_fields) bits.push(c.header_fields + ' header field' + (c.header_fields===1?'':'s'));
                if (c.summary)       bits.push('summary');
                this.showToast('Imported ' + (bits.join(', ') || 'changes') + '.', 'success');
                this.closeImport();
            } catch (e) {
                this.importError = e.message;
            } finally {
                this.importBusy = false;
            }
        },
    };
}

// Bridge so x-html-rendered inputs can talk back to Alpine state.
window.__resumeBus = {
    update(id, type, field, value) {
        const root = document.querySelector('[x-data^="resumeEditor"]');
        if (!root || !root._x_dataStack) return;
        const data = root._x_dataStack[0];
        data.updateItemField(type, id, field, value);
    },
};
</script>
@endsection
