@extends('user.layouts.app')
@section('title', 'Blocks - ' . ($link->title ?: $link->alias))
@section('breadcrumb_parent', 'Links')
@section('breadcrumb_parent_url', route('user.links.index'))

@section('content')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
{{-- Shared "drop a pin to fill address + lat/lng" map picker, reused by the
     map_location block settings form (loaded here on the host page since the
     edit form is injected via AJAX, which strips external <script src> tags). --}}
<script src="{{ asset('js/map-pin-picker.js') }}"></script>
<style>
    .mpp-map .leaflet-container { background:#1e2330 !important; font-family:'Space Grotesk', sans-serif; }
    html.light-mode .mpp-map .leaflet-container { background:#e6e9f0 !important; }
    .mpp-map .leaflet-control-attribution { background:rgba(30,35,48,0.85) !important; color:#9ca3af !important; }
    .mpp-map .leaflet-control-attribution a { color:#90acff !important; }
    .mpp-map .leaflet-control-zoom a { background:#1e2330 !important; color:#fff !important; border-color:rgba(255,255,255,0.15) !important; }
    .mpp-map .leaflet-control-zoom a:hover { background:#3d6bff !important; }
    .mpp-marker { width:30px; height:40px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.45)); }
    .mpp-marker svg { width:100%; height:100%; display:block; }
</style>
@include('user.links.partials.themed-confirm')

<style>
    .block-card {
        position: relative;
        border-radius: 1rem;
        background: var(--bg-card);
        border: 1px solid var(--border-glass);
        backdrop-filter: blur(20px);
        transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
        overflow: hidden;
    }
    .block-card:hover {
        border-color: rgba(61,107,255,0.15);
        background: var(--bg-card-hover);
    }
    .grid-span-row {
        border-top: 1px solid var(--border-subtle);
        margin-top: 2px;
        padding-top: 6px;
    }
    .span-btn {
        background: var(--bg-glass-input);
        color: var(--text-faint);
        border: 1px solid transparent;
        cursor: pointer;
        min-width: 28px;
        text-align: center;
    }
    .span-btn:hover {
        background: var(--bg-glass-hover);
        color: var(--text-muted);
        border-color: var(--border-glass);
    }
    .span-btn.active {
        background: rgba(61,107,255,0.15);
        color: #90acff;
        border-color: rgba(61,107,255,0.3);
    }

    .block-card-wrapper {
        position: relative;
    }
    .insert-block-btn {
        position: absolute;
        right: -14px;
        top: 50%;
        transform: translateY(-50%);
        width: 20px;
        height: 20px;
        border-radius: 50%;
        border: 1.5px solid rgba(16,185,129,0.3);
        background: var(--bg-card);
        color: rgba(16,185,129,0.5);
        font-size: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: all 0.2s ease;
        z-index: 5;
        padding: 0;
    }
    .block-card-wrapper:hover .insert-block-btn {
        opacity: 1;
    }
    .insert-block-btn:hover {
        border-color: #10b981;
        background: rgba(16,185,129,0.12);
        color: #10b981;
        transform: translateY(-50%) scale(1.15);
    }

    .card-container-block {
        border-color: rgba(61,107,255,0.2);
        background: linear-gradient(135deg, var(--bg-card), rgba(61,107,255,0.03));
    }
    .card-container-block:hover {
        border-color: rgba(61,107,255,0.3);
    }
    .child-span-btn {
        background: var(--bg-glass-input);
        color: var(--text-faint);
        border: 1px solid transparent;
        cursor: pointer;
        min-width: 22px;
        text-align: center;
    }
    .child-span-btn:hover {
        background: var(--bg-glass-hover);
        color: var(--text-muted);
        border-color: var(--border-glass);
    }
    .child-span-btn.active {
        background: rgba(61,107,255,0.15);
        color: #90acff;
        border-color: rgba(61,107,255,0.3);
    }
    .child-block-card.sortable-ghost,
    .block-card.sortable-ghost {
        opacity: 0.4;
        border: 1px dashed rgba(61,107,255,0.4) !important;
    }
    .child-block-card.sortable-chosen,
    .block-card.sortable-chosen {
        box-shadow: 0 4px 16px rgba(0,0,0,0.3);
        z-index: 10;
    }
    .card-child-list.sortable-drag-over {
        background: rgba(61,107,255,0.04);
        border: 1px dashed rgba(61,107,255,0.3) !important;
        border-radius: 8px;
    }

    .block-card-wrapper.sortable-ghost,
    .block-card.sortable-ghost {
        opacity: 0.4;
        border: 2px dashed rgba(61,107,255,0.4);
        background: rgba(61,107,255,0.04);
        transform: scale(0.97);
    }
    .block-card-wrapper.sortable-chosen,
    .block-card.sortable-chosen {
        box-shadow: 0 16px 48px rgba(0,0,0,0.4), 0 0 30px rgba(61,107,255,0.12);
        border-color: rgba(61,107,255,0.3);
        z-index: 10;
    }
    .block-card-wrapper.sortable-drag,
    .block-card.sortable-drag {
        opacity: 1 !important;
    }
    .drag-handle {
        cursor: grab;
        padding: 8px 4px;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 2px;
        opacity: 0.3;
        transition: opacity 0.2s;
    }
    .drag-handle:active { cursor: grabbing; }
    .block-card:hover .drag-handle { opacity: 0.7; }
    .drag-handle .dot {
        width: 3px; height: 3px; border-radius: 50%;
        background: var(--text-muted);
    }

    .block-preview-content {
        font-size: 12px;
        color: var(--text-faint);
        line-height: 1.4;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        max-width: 280px;
    }

    .block-action-btn {
        width: 30px; height: 30px;
        border-radius: 8px;
        display: flex; align-items: center; justify-content: center;
        transition: all 0.2s;
        color: var(--text-faint);
        background: transparent;
        border: none;
        cursor: pointer;
        font-size: 11px;
    }
    .block-action-btn:hover {
        background: var(--bg-glass-hover);
        color: var(--text-primary);
    }
    .block-action-btn.edit-btn:hover { color: #5c83ff; background: rgba(61,107,255,0.1); }
    .block-action-btn.toggle-btn:hover { color: #f59e0b; background: rgba(245,158,11,0.1); }
    .block-action-btn.delete-btn:hover { color: #ef4444; background: rgba(239,68,68,0.1); }

    .edit-modal-overlay {
        position: fixed; inset: 0; z-index: 60;
        background: rgba(0,0,0,0.6);
        backdrop-filter: blur(8px);
        opacity: 0; pointer-events: none;
        transition: opacity 0.3s ease;
        display: flex; align-items: center; justify-content: center;
        padding: 16px;
    }
    .edit-modal-overlay.open { opacity: 1; pointer-events: auto; }
    .edit-modal {
        width: 100%; max-width: 1200px;
        height: 92vh; max-height: 92vh;
        background: var(--bg-sidebar);
        backdrop-filter: blur(40px) saturate(1.4);
        border: 1px solid var(--border-subtle);
        border-radius: 1.5rem;
        display: flex; flex-direction: column;
        box-shadow: 0 32px 100px rgba(0,0,0,0.5);
        transform: scale(0.95) translateY(20px);
        transition: transform 0.35s cubic-bezier(0.4,0,0.2,1);
        overflow: hidden;
    }
    .edit-modal-overlay.open .edit-modal {
        transform: scale(1) translateY(0);
    }
    .edit-modal-body {
        flex: 1; display: flex; overflow: hidden;
    }
    .edit-modal-preview {
        flex: 0 0 380px;
        display: flex; align-items: center; justify-content: center;
        position: relative;
        overflow: hidden;
        border-right: 1px solid var(--border-subtle);
        background: rgba(0,0,0,0.15);
    }
    .edit-modal-form {
        flex: 1; overflow-y: auto; padding: 24px;
    }
    @media (max-width: 768px) {
        .edit-modal-preview { display: none; }
        .edit-modal { max-width: 100%; height: 100vh; max-height: 100vh; border-radius: 0; }
    }

    .gallery-tabs {
        display: flex;
        gap: 2px;
        overflow-x: auto;
        /* Bottom padding gives the category chips breathing room before the
           template grid. It lives in this shorthand (not a `pb-*` utility on the
           element) because this rule's `padding` shorthand would otherwise reset
           padding-bottom to 0 and override an equal-specificity utility class. */
        padding: 0 20px 14px;
        margin-bottom: 4px;
        scrollbar-width: none;
        -ms-overflow-style: none;
    }
    .gallery-tabs::-webkit-scrollbar { display: none; }
    .gallery-tab {
        padding: 8px 14px;
        font-size: 11px;
        font-weight: 600;
        white-space: nowrap;
        border-radius: 8px;
        transition: all 0.2s;
        color: var(--text-faint);
        background: transparent;
        border: none;
        cursor: pointer;
    }
    .gallery-tab:hover { color: var(--text-muted); background: var(--bg-glass); }
    .gallery-tab.active {
        color: white;
        background: linear-gradient(135deg, #5c83ff, #3d6bff);
        box-shadow: 0 2px 12px rgba(61,107,255,0.3);
    }
    .gallery-block-card {
        padding: 12px;
        border-radius: 12px;
        border: 1px solid var(--border-glass);
        background: transparent;
        transition: all 0.25s cubic-bezier(0.4,0,0.2,1);
        cursor: pointer;
        text-align: left;
        width: 100%;
    }
    .gallery-block-card:hover {
        border-color: rgba(61,107,255,0.3);
        background: rgba(61,107,255,0.04);
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    }

    /* ─────────────────── Block-picker preview thumbnails ─────────────────── */
    /* Mini visual representations of seeded block content rendered above
       the icon+label row in each gallery tile. Pointer-events disabled
       so the tile-level click handler still fires. */
    /* Block-picker preview thumbnail tokens.
       Skeleton fills are translucent white on the dark theme, but white-on-white
       in light mode, so they get a dark "ink" counterpart under html.light-mode.
       Dark values are unchanged from the original hardcoded rgba(255,255,255,...). */
    :root {
        --bpt-tile-bg: rgba(255,255,255,0.025);
        --bpt-tile-border: rgba(255,255,255,0.04);
        --bpt-skeleton: rgba(255,255,255,0.12);
        --bpt-divider: rgba(255,255,255,0.18);
        --bpt-dashed: rgba(255,255,255,0.2);
        --bpt-rank-bg: rgba(255,255,255,0.15);
        --bpt-border: rgba(255,255,255,0.1);
        --bpt-input-bg: rgba(255,255,255,0.06);
        --bpt-surface: rgba(255,255,255,0.05);
        --bpt-surface-faint: rgba(255,255,255,0.04);
        --bpt-media-bg: rgba(255,255,255,0.1);
        --bpt-wave: rgba(255,255,255,0.3);
        --bpt-icon-circle-bg: rgba(255,255,255,0.1);
    }
    html.light-mode {
        --bpt-tile-bg: rgba(7,20,55,0.03);
        --bpt-tile-border: rgba(7,20,55,0.10);
        --bpt-skeleton: rgba(7,20,55,0.16);
        --bpt-divider: rgba(7,20,55,0.20);
        --bpt-dashed: rgba(7,20,55,0.22);
        --bpt-rank-bg: rgba(7,20,55,0.55);
        --bpt-border: rgba(7,20,55,0.16);
        --bpt-input-bg: rgba(7,20,55,0.05);
        --bpt-surface: rgba(7,20,55,0.06);
        --bpt-surface-faint: rgba(7,20,55,0.05);
        --bpt-media-bg: rgba(7,20,55,0.10);
        --bpt-wave: rgba(7,20,55,0.35);
        --bpt-icon-circle-bg: rgba(7,20,55,0.55);
    }
    .block-preview-thumb {
        position: relative;
        height: 78px;
        margin: -2px -2px 10px -2px;
        padding: 8px 10px;
        border-radius: 8px;
        background: var(--bpt-tile-bg);
        border: 1px solid var(--bpt-tile-border);
        overflow: hidden;
        display: flex;
        flex-direction: column;
        gap: 4px;
        justify-content: center;
        pointer-events: none;
        font-size: 9px;
        color: var(--text-muted);
    }
    .bpt-line { height: 4px; border-radius: 2px; background: var(--bpt-skeleton); }
    .bpt-line-100 { width: 100%; }
    .bpt-line-90  { width: 90%; }
    .bpt-line-80  { width: 80%; }
    .bpt-line-70  { width: 70%; }
    .bpt-line-60  { width: 60%; }
    .bpt-line-50  { width: 50%; }
    .bpt-btn {
        display: flex; align-items: center; justify-content: center; gap: 6px;
        padding: 8px 10px; border-radius: 6px; color: white;
        font-size: 10px; font-weight: 600; text-align: center;
    }
    .bpt-btn i { font-size: 9px; }
    .bpt-btn-sm { padding: 5px 8px; font-size: 9px; }
    .bpt-heading { font-size: 13px; font-weight: 700; color: var(--text-primary); line-height: 1.1; }
    .bpt-underline { width: 28px; height: 2px; border-radius: 1px; margin-top: 2px; }
    .bpt-pill {
        display: inline-block; padding: 3px 9px; border-radius: 999px;
        font-size: 9px; font-weight: 700; color: white; align-self: center;
    }
    .bpt-pill-row { display: flex; gap: 4px; flex-wrap: wrap; justify-content: center; }
    .bpt-pill-sm { padding: 2px 6px; border-radius: 999px; font-size: 8px; font-weight: 700; }
    .bpt-divider { height: 1px; background: var(--bpt-divider); width: 100%; }
    .bpt-spacer { font-size: 16px; text-align: center; opacity: 0.4; }
    .bpt-card {
        border: 1px dashed var(--bpt-dashed);
        border-radius: 6px; padding: 10px; display: flex; flex-direction: column; gap: 4px;
    }
    .bpt-list-row, .bpt-faq-row, .bpt-tl-row, .bpt-menu-row, .bpt-lb-row, .bpt-chat-row {
        display: flex; align-items: center; gap: 6px;
    }
    .bpt-menu-row { justify-content: space-between; }
    .bpt-dot { width: 4px; height: 4px; border-radius: 50%; flex-shrink: 0; }
    .bpt-tl-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
    .bpt-rank {
        width: 14px; height: 14px; border-radius: 50%; background: var(--bpt-rank-bg);
        color: white; font-size: 8px; font-weight: 700; display: flex;
        align-items: center; justify-content: center; flex-shrink: 0;
    }
    .bpt-mini-num { font-size: 11px; font-weight: 700; color: var(--text-primary); }
    .bpt-pricing { display: flex; gap: 3px; align-items: stretch; }
    .bpt-price-col {
        flex: 1; padding: 5px 4px; border-radius: 4px;
        border: 1px solid var(--bpt-border); display: flex;
        flex-direction: column; align-items: center; gap: 3px;
    }
    .bpt-price-col.bpt-featured { border-width: 1.5px; transform: scale(1.05); }
    .bpt-image, .bpt-thumb, .bpt-avatar {
        background-size: cover; background-position: center;
        background-color: var(--bpt-media-bg);
    }
    .bpt-image { height: 100%; border-radius: 6px; }
    .bpt-thumb { aspect-ratio: 1; border-radius: 4px; }
    .bpt-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 3px; }
    .bpt-avatar { width: 26px; height: 26px; border-radius: 50%; flex-shrink: 0; }
    .bpt-avatar-sm { width: 16px; height: 16px; border-radius: 50%; flex-shrink: 0;
        background-size: cover; background-position: center; background-color: var(--bpt-media-bg); }
    .bpt-video {
        flex: 1; border-radius: 6px; position: relative;
        background: linear-gradient(135deg, rgba(0,0,0,0.5), rgba(0,0,0,0.7));
        display: flex; align-items: center; justify-content: center;
    }
    .bpt-play {
        width: 26px; height: 26px; border-radius: 50%;
        background: rgba(255,255,255,0.95); color: #000;
        display: flex; align-items: center; justify-content: center;
        font-size: 10px;
    }
    .bpt-play-sm {
        width: 22px; height: 22px; border-radius: 50%;
        color: white; display: flex; align-items: center; justify-content: center;
        font-size: 9px; flex-shrink: 0;
    }
    .bpt-audio { display: flex; align-items: center; gap: 6px; }
    .bpt-wave { display: flex; align-items: center; gap: 2px; flex: 1; height: 22px; }
    .bpt-wave span {
        flex: 1; background: var(--bpt-wave); border-radius: 1px;
    }
    .bpt-wave span:nth-child(1) { height: 30%; }
    .bpt-wave span:nth-child(2) { height: 70%; }
    .bpt-wave span:nth-child(3) { height: 100%; }
    .bpt-wave span:nth-child(4) { height: 50%; }
    .bpt-wave span:nth-child(5) { height: 80%; }
    .bpt-wave span:nth-child(6) { height: 40%; }
    .bpt-wave span:nth-child(7) { height: 90%; }
    .bpt-wave span:nth-child(8) { height: 60%; }
    .bpt-wave span:nth-child(9) { height: 20%; }
    .bpt-doc { display: flex; align-items: center; gap: 8px; }
    .bpt-doc i { font-size: 22px; flex-shrink: 0; }
    .bpt-doc-lines { flex: 1; display: flex; flex-direction: column; gap: 4px; }
    .bpt-socials { display: flex; gap: 5px; justify-content: center; align-items: center; }
    .bpt-socials span {
        width: 22px; height: 22px; border-radius: 50%; color: white;
        display: flex; align-items: center; justify-content: center; font-size: 10px;
    }
    .bpt-input {
        height: 18px; border-radius: 4px; background: var(--bpt-input-bg);
        border: 1px solid var(--bpt-border);
    }
    .bpt-input-sm { height: 14px; }
    .bpt-embed {
        flex: 1; display: flex; flex-direction: column; align-items: center;
        justify-content: center; gap: 4px; border: 1px dashed var(--bpt-dashed);
        border-radius: 6px;
    }
    .bpt-embed i { font-size: 16px; }
    .bpt-embed-label { font-size: 8px; opacity: 0.7; }
    .bpt-map {
        flex: 1; border-radius: 6px;
        background: linear-gradient(135deg, #1a3a4a 0%, #2a5a6a 100%);
        display: flex; align-items: center; justify-content: center;
    }
    .bpt-map i { font-size: 22px; }
    .bpt-profile, .bpt-product, .bpt-event { display: flex; align-items: center; gap: 8px; }
    .bpt-profile-meta, .bpt-product-meta, .bpt-event-meta {
        flex: 1; display: flex; flex-direction: column; gap: 4px; min-width: 0;
    }
    .bpt-product .bpt-thumb { width: 32px; height: 32px; flex-shrink: 0; }
    .bpt-stats { display: flex; gap: 4px; justify-content: space-around; }
    .bpt-stat {
        flex: 1; padding: 4px 0; border-radius: 4px;
        background: var(--bpt-surface-faint); text-align: center;
    }
    .bpt-countdown { display: flex; gap: 4px; justify-content: center; }
    .bpt-countdown span {
        width: 24px; height: 24px; border-radius: 4px;
        font-size: 11px; font-weight: 700;
        display: flex; align-items: center; justify-content: center;
    }
    .bpt-qr {
        flex: 1; display: flex; align-items: center; justify-content: center;
    }
    .bpt-qr i { font-size: 36px; color: var(--text-primary); }
    .bpt-poll-row { background: var(--bpt-surface); border-radius: 3px; height: 9px; overflow: hidden; }
    .bpt-poll-bar { height: 100%; border-radius: 3px; }
    .bpt-quote { display: flex; gap: 8px; align-items: flex-start; }
    .bpt-quote i { font-size: 14px; flex-shrink: 0; margin-top: 2px; }
    .bpt-quote-lines { flex: 1; display: flex; flex-direction: column; gap: 4px; }
    .bpt-event-date {
        width: 32px; padding: 2px 0; border-radius: 4px; color: white;
        text-align: center; flex-shrink: 0;
    }
    .bpt-event-date span { display: block; font-size: 13px; font-weight: 700; line-height: 1; }
    .bpt-event-date small { display: block; font-size: 7px; font-weight: 600; letter-spacing: 0.5px; }
    .bpt-tabs { display: flex; gap: 3px; }
    .bpt-tab, .bpt-tab-active {
        flex: 1; padding: 4px 0; border-radius: 4px; text-align: center;
        font-size: 9px; font-weight: 600;
    }
    .bpt-tab { background: var(--bpt-surface); color: var(--text-faint); }
    .bpt-tab-active { color: white; }
    .bpt-ticker {
        display: flex; align-items: center; gap: 6px; padding: 4px 8px;
        border: 1px solid; border-radius: 4px;
    }
    .bpt-ticker span { font-size: 8px; }
    .bpt-nav {
        display: flex; gap: 8px; justify-content: center; font-size: 9px;
        color: var(--text-muted); font-weight: 600;
    }
    .bpt-lock { text-align: center; }
    .bpt-lock i { font-size: 16px; }
    .bpt-bubble {
        height: 12px; border-radius: 8px;
        background: var(--bpt-media-bg);
    }
    .bpt-bubble-them { width: 60%; }
    .bpt-bubble-me { width: 50%; }
    .bpt-roadmap { display: flex; gap: 3px; }
    .bpt-roadmap span {
        flex: 1; padding: 4px 0; border-radius: 4px; text-align: center;
        font-size: 8px; font-weight: 700;
    }
    .bpt-generic {
        flex: 1; display: flex; align-items: center; justify-content: center;
        border-radius: 6px;
    }
    .bpt-generic i { font-size: 20px; }

    .empty-state-icon {
        width: 80px; height: 80px;
        border-radius: 24px;
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 20px;
        background: linear-gradient(135deg, rgba(61,107,255,0.1), rgba(92,131,255,0.05));
        border: 1px solid rgba(61,107,255,0.12);
        position: relative;
    }
    .empty-state-icon::after {
        content: '';
        position: absolute;
        inset: -8px;
        border-radius: 28px;
        border: 1px dashed rgba(61,107,255,0.15);
        animation: spin-slow 20s linear infinite;
    }
    @keyframes spin-slow { to { transform: rotate(360deg); } }

    .block-type-dot {
        width: 6px; height: 6px;
        border-radius: 50%;
        flex-shrink: 0;
    }
    .reorder-toast {
        position: fixed;
        bottom: 24px;
        left: 50%;
        transform: translateX(-50%) translateY(80px);
        padding: 10px 20px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        z-index: 70;
        transition: transform 0.4s cubic-bezier(0.34,1.56,0.64,1);
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
        box-shadow: 0 8px 32px rgba(16,185,129,0.3);
    }
    .reorder-toast.show { transform: translateX(-50%) translateY(0); }
</style>

@php
$catColors = [
    'basic' => '#5c83ff', 'media' => '#ec4899', 'social' => '#3b82f6',
    'music' => '#10b981', 'video_platforms' => '#ef4444', 'contact' => '#f59e0b',
    'interactive' => '#06b6d4', 'business' => '#f97316', 'utility' => '#6366f1',
    'layout' => '#5c83ff', 'integrations' => '#14b8a6', 'files' => '#64748b',
    'maps' => '#22c55e', 'identity' => '#5c83ff',
];
@endphp
<div x-data="biolinkEditor()" class="max-w-7xl mx-auto">
    @include('user.links.partials.editor-header', ['link' => $link, 'activeMainTab' => 'blocks'])

    <div class="flex items-center justify-end gap-2 mb-4">
        <button type="button" id="deleteAllBlocksBtn"
                onclick="ajaxDeleteAllBlocks(this)"
                class="delete-all-btn"
                style="{{ $blocks->count() ? '' : 'display:none;' }}"
                title="Delete all blocks">
            <i class="fas fa-trash-alt text-[10px]"></i>
            <span>Delete all</span>
        </button>
        <span id="blockCountChip" class="block-count-chip" style="{{ $blocks->count() ? '' : 'display:none;' }}">
            <i class="fas fa-layer-group text-[10px] opacity-70"></i>
            <span><strong data-block-count>{{ $blocks->count() }}</strong> blocks</span>
        </span>
    </div>
    <style>
        .block-count-chip {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 6px 12px; border-radius: 9999px;
            font-size: 11px; letter-spacing: 0.02em;
            color: var(--text-faint);
            background: var(--bg-glass);
            border: 1px solid var(--border-glass);
            backdrop-filter: blur(14px) saturate(140%);
            -webkit-backdrop-filter: blur(14px) saturate(140%);
        }
        .block-count-chip strong { color: var(--text-primary); font-weight: 700; }
        .delete-all-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 12px; border-radius: 9999px;
            font-size: 11px; font-weight: 600; letter-spacing: 0.02em;
            color: #ef4444; cursor: pointer;
            background: rgba(239,68,68,0.08);
            border: 1px solid rgba(239,68,68,0.32);
            transition: background .18s ease, color .18s ease, border-color .18s ease, transform .18s ease;
        }
        .delete-all-btn:hover {
            background: rgba(239,68,68,0.16);
            border-color: rgba(239,68,68,0.5);
            transform: translateY(-1px);
        }
        .delete-all-btn:disabled { opacity: 0.55; pointer-events: none; }
        /* Sticky behavior now lives inside the device-preview partial so
           it works on every page that includes it (editor, appearance, etc.). */

        /* Badges shown next to block titles (HIDDEN, grid-span like "6/12").
           They use white text, so the background must stay opaque enough to
           keep contrast in BOTH dark and light themes. */
        .editor-pill-badge {
            color: #ffffff;
            text-shadow: 0 1px 2px rgba(0,0,0,0.35);
            font-weight: 700;
            line-height: 1;
        }
        .editor-pill-badge--span {
            background: linear-gradient(135deg, #3d6bff, #2342c7);
            border: 1px solid rgba(61,107,255,0.7);
            box-shadow: 0 2px 6px rgba(61,107,255,0.35);
        }
        .editor-pill-badge--hidden {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            border: 1px solid rgba(220,38,38,0.7);
            box-shadow: 0 2px 6px rgba(239,68,68,0.3);
        }
    </style>

    <style>
        /* ── Page-builder layout: palette | canvas | device preview ──────────
           A bespoke grid (rather than Tailwind col-span utilities) so the
           persistent block palette can sit alongside the canvas without
           colliding with the shared device-preview partial's responsive
           overrides. Below 900px everything stacks; 900–1023px shows
           canvas + preview; the palette appears at lg+ where there's room. */
        #editorLayout {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 1.5rem;
            align-items: start;
        }
        /* Below lg the palette stacks full-width above the canvas (adding blocks
           is palette-driven now, so it must stay reachable on every size). */
        #editorPaletteCol { display: block; grid-column: 1 / -1; order: -1; }
        #editorPreviewCol { display: none; }
        @media (min-width: 900px) {
            #editorLayout { grid-template-columns: minmax(0, 7fr) minmax(0, 5fr); }
            #editorPreviewCol { display: block; }
        }
        @media (min-width: 1024px) {
            #editorLayout { grid-template-columns: minmax(0, 3.1fr) minmax(0, 5fr) minmax(0, 4fr); }
            #editorPaletteCol { grid-column: auto; order: 0; }
        }
        /* Stacked (sub-lg) palette: not full viewport height, scrolls internally.
           Use a DEFINITE height (not max-height) so the absolute Templates
           overlay inside it — and that overlay's flex scroll body — resolve a
           bounded height and scroll instead of growing to full content.
           NOTE: this override is declared AFTER the base .palette-panel rule
           below so it actually wins the cascade (equal specificity → source
           order decides); otherwise the base sticky/height would override it. */

        .palette-panel {
            position: sticky;
            top: 12px;
            display: flex;
            flex-direction: column;
            /* The scroll container is <main> (the app shell pins the page at
               h-screen overflow-hidden and lets <main> scroll), so the visible
               viewport excludes the in-app header. Subtract its height (via the
               shared --app-header-h var, defaulting to 4rem) plus the 12px top +
               12px bottom gap so the pinned panel — and the absolute Templates
               overlay layered inside it — never run below the fold. The var keeps
               this in lockstep if the header height ever changes. */
            /* A DEFINITE height (not just max-height) is required here: the
               Templates overlay is position:absolute; inset:0 inside this panel,
               and its flex scroll body only resolves a bounded height — and thus
               scrolls — when this containing block has a definite height. With
               only max-height/min-height (height:auto) the overlay's body grows
               to its full content height and is clipped by overflow:hidden,
               which is what broke scrolling on every Templates tab. This also
               guarantees room when the block palette's sections are collapsed. */
            height: calc(100vh - var(--app-header-h, 4rem) - 24px);
            background: var(--bg-glass);
            border: 1px solid var(--border-glass);
            border-radius: 18px;
            overflow: hidden;
            backdrop-filter: blur(16px) saturate(140%);
            -webkit-backdrop-filter: blur(16px) saturate(140%);
        }
        /* Stacked (sub-lg): palette flows in-page (not sticky) at a fixed 60vh
           so it — and the absolute Templates overlay inside it — stay bounded
           and scroll internally. Declared after the base rule so it wins. */
        @media (max-width: 1023px) {
            .palette-panel { position: relative; height: 60vh; max-height: none; min-height: 0; }
        }
        .palette-head { padding: 14px 14px 8px; flex-shrink: 0; }
        .palette-tabs {
            display: flex; flex-wrap: nowrap; gap: 4px; margin-top: 10px;
            overflow-x: auto; overflow-y: hidden; padding-bottom: 5px;
            scrollbar-width: thin; scrollbar-color: var(--border-glass) transparent;
            -webkit-overflow-scrolling: touch;
            -webkit-mask-image: linear-gradient(to right, transparent 0, #000 12px, #000 calc(100% - 12px), transparent 100%);
                    mask-image: linear-gradient(to right, transparent 0, #000 12px, #000 calc(100% - 12px), transparent 100%);
        }
        .palette-tabs::-webkit-scrollbar { height: 4px; }
        .palette-tabs::-webkit-scrollbar-track { background: transparent; }
        .palette-tabs::-webkit-scrollbar-thumb { background: var(--border-glass); border-radius: 4px; }
        .palette-tab {
            padding: 4px 10px; font-size: 10px; font-weight: 600;
            border-radius: 7px; white-space: nowrap; cursor: pointer; flex-shrink: 0;
            color: var(--text-faint); background: transparent; border: 1px solid transparent;
            transition: all 0.2s;
        }
        .palette-tab:hover { color: var(--text-muted); background: var(--bg-glass-hover); }
        .palette-tab.active {
            color: white; background: linear-gradient(135deg, #5c83ff, #3d6bff);
            box-shadow: 0 2px 10px rgba(61,107,255,0.3);
        }
        .palette-body {
            flex: 1; overflow-y: auto; padding: 6px 10px 10px;
            display: flex; flex-direction: column; gap: 4px;
        }
        .palette-block-item {
            display: flex; align-items: center; gap: 10px;
            padding: 9px 11px; border-radius: 10px; cursor: grab;
            text-align: left; width: 100%;
            background: transparent; border: 1px solid var(--border-glass);
            transition: all 0.18s cubic-bezier(0.4,0,0.2,1);
        }
        .palette-block-item:hover {
            border-color: rgba(61,107,255,0.35);
            background: rgba(61,107,255,0.06);
            transform: translateY(-1px);
        }
        .palette-block-item:active { cursor: grabbing; }
        .palette-block-icon {
            width: 26px; height: 26px; border-radius: 7px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center; font-size: 11px;
        }
        .palette-block-label {
            font-size: 11.5px; font-weight: 600; color: var(--text-primary);
            line-height: 1.2; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        /* Collapsible category section headers inside the palette body. The
           header is a direct child of #paletteList but is NOT a
           .palette-block-item, so SortableJS ignores it as a drag source. */
        .palette-section-header {
            display: flex; align-items: center; gap: 7px; width: 100%;
            padding: 9px 6px 5px; margin-top: 2px;
            background: transparent; border: none; cursor: pointer; text-align: left;
            color: var(--text-faint);
            transition: color 0.18s;
        }
        .palette-section-header:first-child { margin-top: 0; }
        .palette-section-header:hover { color: var(--text-muted); }
        .palette-section-chevron {
            font-size: 9px; width: 9px; flex-shrink: 0;
            transition: transform 0.2s cubic-bezier(0.4,0,0.2,1);
        }
        .palette-section-header[aria-expanded="true"] .palette-section-chevron { transform: rotate(90deg); }
        .palette-section-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
        .palette-section-title {
            font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;
            flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .palette-section-count {
            font-size: 9px; font-weight: 600; color: var(--text-faint);
            background: var(--bg-glass-hover); border: 1px solid var(--border-glass);
            border-radius: 999px; padding: 1px 6px; flex-shrink: 0; line-height: 1.4;
        }
        .palette-empty-note { padding: 22px 8px; text-align: center; font-size: 11px; color: var(--text-faint); }
        .palette-foot { flex-shrink: 0; padding: 8px 10px; border-top: 1px solid var(--border-glass); }

        /* Drop affordance: the palette ghost rendered inside the canvas / card
           becomes a highlighted insertion bar showing where the block lands. */
        .palette-block-item.palette-drop-ghost {
            grid-column: span 12;
            height: 44px; min-height: 44px; opacity: 1;
            justify-content: center;
            border: 2px dashed rgba(61,107,255,0.6);
            background: rgba(61,107,255,0.1);
            border-radius: 12px;
            box-shadow: 0 0 0 4px rgba(61,107,255,0.06);
        }
        .palette-block-item.palette-drop-ghost .palette-block-label { color: #90acff; }
        .palette-block-item.sortable-drag {
            opacity: 0.95;
            box-shadow: 0 16px 40px rgba(0,0,0,0.4);
            border-color: rgba(61,107,255,0.4);
        }
        /* Highlight valid drop containers while dragging a palette block. */
        #blockList.palette-dragging,
        .card-child-list.palette-dragging {
            outline: 2px dashed rgba(61,107,255,0.25);
            outline-offset: 4px;
            border-radius: 12px;
        }
        @media (prefers-reduced-motion: reduce) {
            .palette-block-item, .palette-tab, .palette-section-header, .palette-section-chevron { transition: none !important; }
            .palette-block-item:hover { transform: none; }
        }
    </style>

    <div id="editorLayout">
        {{-- BLOCK PALETTE (persistent drag source) --}}
        <div id="editorPaletteCol">
            <div class="palette-panel">
                <div class="palette-head">
                    <h3 class="text-sm font-bold gradient-text">Add blocks</h3>
                    <p class="text-[10px] mt-0.5" style="color: var(--text-faint);"><i class="fas fa-hand-pointer mr-1"></i>Drag onto your page — or click to add</p>
                    <div class="relative mt-2.5">
                        <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-[10px]" style="color: var(--text-faint);"></i>
                        <input type="text" x-model="paletteSearch" placeholder="Search blocks…" class="theme-input w-full pl-7 text-xs py-1.5" aria-label="Search blocks">
                    </div>
                    <div class="palette-tabs">
                        <button type="button" class="palette-tab" :class="paletteCategory === 'all' ? 'active' : ''" @click="paletteCategory = 'all'">All</button>
                        @foreach($blockCategories as $catKey => $catLabel)
                        <button type="button" class="palette-tab" :class="paletteCategory === '{{ $catKey }}' ? 'active' : ''" @click="paletteCategory = '{{ $catKey }}'">{{ $catLabel }}</button>
                        @endforeach
                    </div>
                </div>
                @php
                    // Group palette tiles under their category so the body can
                    // render collapsible section headers. Order follows
                    // CATEGORIES; same filtering as before (drop system/verified).
                    $paletteGroups = [];
                    foreach ($blockCategories as $gCatKey => $gCatLabel) { $paletteGroups[$gCatKey] = []; }
                    foreach ($blockTypes as $gTypeKey => $gTypeInfo) {
                        if (!empty($gTypeInfo['system']) || ($gTypeInfo['category'] ?? '') === 'verified') { continue; }
                        $gCat = $gTypeInfo['category'] ?? 'basic';
                        if (!array_key_exists($gCat, $paletteGroups)) { $paletteGroups[$gCat] = []; }
                        $paletteGroups[$gCat][$gTypeKey] = $gTypeInfo;
                    }
                    $paletteGroups = array_filter($paletteGroups, fn ($g) => count($g) > 0);
                    // Lowercased labels per section drive search-aware header visibility in Alpine.
                    $paletteSectionLabels = [];
                    foreach ($paletteGroups as $gCat => $gTypes) {
                        $paletteSectionLabels[$gCat] = array_values(array_map(fn ($t) => strtolower($t['label']), $gTypes));
                    }
                @endphp
                <div id="paletteList" class="palette-body">
                    @foreach($paletteGroups as $catKey => $catTypes)
                        @php $secColor = $catColors[$catKey] ?? '#5c83ff'; @endphp
                        <button type="button"
                                class="palette-section-header"
                                x-show="paletteSectionVisible('{{ $catKey }}')"
                                :aria-expanded="paletteSectionOpen('{{ $catKey }}') ? 'true' : 'false'"
                                @click="togglePaletteSection('{{ $catKey }}')"
                                title="Toggle {{ $blockCategories[$catKey] ?? $catKey }}"
                                x-cloak>
                            <i class="fas fa-chevron-right palette-section-chevron"></i>
                            <span class="palette-section-dot" style="background: {{ $secColor }};"></span>
                            <span class="palette-section-title">{{ $blockCategories[$catKey] ?? ucfirst($catKey) }}</span>
                            <span class="palette-section-count">{{ count($catTypes) }}</span>
                        </button>
                        @foreach($catTypes as $typeKey => $typeInfo)
                            @php
                                $pCatColor = $catColors[$typeInfo['category']] ?? '#5c83ff';
                                $pLocked = !auth()->user()->userCanUseBlockType($typeKey);
                            @endphp
                            @if($pLocked)
                                <a href="{{ route('user.upgrade') }}"
                                   class="palette-block-locked palette-block-item"
                                   style="cursor: pointer; opacity: 0.6;"
                                   title="Upgrade your plan to unlock this block"
                                   x-show="paletteItemVisible('{{ $catKey }}', '{{ strtolower($typeInfo['label']) }}')"
                                   x-cloak>
                                    <span class="palette-block-icon" style="background: {{ $pCatColor }}15; border: 1px solid {{ $pCatColor }}25;">
                                        <i class="fas fa-lock" style="color: {{ $pCatColor }};"></i>
                                    </span>
                                    <span class="palette-block-label">{{ $typeInfo['label'] }}</span>
                                </a>
                            @else
                                <button type="button"
                                        class="palette-block-item"
                                        data-block-type="{{ $typeKey }}"
                                        onclick="paletteClickAdd('{{ $typeKey }}')"
                                        title="Drag onto the canvas, or click to add"
                                        x-show="paletteItemVisible('{{ $catKey }}', '{{ strtolower($typeInfo['label']) }}')"
                                        x-cloak>
                                    <span class="palette-block-icon" style="background: {{ $pCatColor }}15; border: 1px solid {{ $pCatColor }}25;">
                                        <i class="fas {{ $typeInfo['icon'] }}" style="color: {{ $pCatColor }};"></i>
                                    </span>
                                    <span class="palette-block-label">{{ $typeInfo['label'] }}</span>
                                </button>
                            @endif
                        @endforeach
                    @endforeach
                    <div class="palette-empty-note" x-show="!paletteAnyVisible()" x-cloak>
                        <i class="fas fa-search mb-1.5 block opacity-60"></i>
                        No blocks match your search.
                    </div>
                </div>
                <div class="palette-foot">
                    <button type="button" @click="openSpecialPanel()" class="w-full py-1.5 rounded-lg text-[11px] font-semibold flex items-center justify-center gap-1.5 transition-all hover:bg-blue-500/10" style="border: 1px dashed rgba(61,107,255,0.3); color: #90acff;">
                        <i class="fas fa-grip text-[9px]"></i> Templates, forms &amp; more
                    </button>
                </div>
                @include('user.links.partials.editor-special-panel', ['link' => $link, 'userForms' => $userForms ?? [], 'userBuzz' => $userBuzz ?? [], 'userCompanions' => $userCompanions ?? []])
            </div>
        </div>

        {{-- CANVAS --}}
        <div id="editorCanvasCol">

            {{-- BLOCKS --}}
                <div id="reorderHint" class="flex items-center mb-3" style="{{ $blocks->count() ? '' : 'display:none;' }}">
                    <p class="text-xs" style="color: var(--text-faint);"><i class="fas fa-grip-vertical mr-1"></i> Drag to reorder blocks</p>
                </div>

                <div id="blockList" class="grid gap-2" style="grid-template-columns: repeat(12, 1fr); padding-right: 16px;">
                    @foreach($blocks as $block)
                    @include('user.links.partials.block-card', ['block' => $block, 'link' => $link, 'blockTypes' => $blockTypes, 'catColors' => $catColors, 'pollTallies' => $pollTallies ?? []])
                    @endforeach
                    <div id="blockListEmpty" class="flex flex-col items-center justify-center text-center rounded-2xl px-6 py-12 lg:min-h-[420px]"
                         style="grid-column: span 12; background: var(--bg-glass); border: 1px dashed var(--border-glass); {{ $blocks->count() ? 'display:none;' : '' }}">
                        <div class="empty-state-icon">
                            <i class="fas fa-layer-group text-3xl text-blue-400"></i>
                        </div>
                        <h3 class="text-lg font-bold mb-2" style="color: var(--text-primary);">No blocks yet</h3>
                        <p class="text-sm mb-6 max-w-xs mx-auto" style="color: var(--text-muted);"><span class="hidden lg:inline">Drag a block from the palette on the left onto this canvas, or start from a curated template.</span><span class="lg:hidden">Start from a curated template, or add blocks one at a time.</span> Your live preview updates instantly.</p>
                        <div class="flex items-center justify-center gap-2 flex-wrap">
                            <a href="{{ route('user.links.templates.picker', $link) }}" class="btn-primary text-sm py-2.5 px-6" style="background: linear-gradient(135deg, #5c83ff, #3d6bff);">
                                <i class="fas fa-layer-group text-xs"></i> Browse templates
                            </a>
                            <button type="button" onclick="focusBlockPalette()" class="btn-primary text-sm py-2.5 px-6" style="background: linear-gradient(135deg, #10b981, #059669);">
                                <i class="fas fa-plus text-xs"></i> Add a block
                            </button>
                        </div>
                    </div>
                </div>
        </div>

        {{-- DEVICE PREVIEW --}}
        <div id="editorPreviewCol" class="lg:self-stretch lg:h-full">
            @include('user.links.partials.device-preview', ['link' => $link])
        </div>
    </div>

    {{-- EDIT MODAL (full-screen split) --}}
    <div class="edit-modal-overlay" :class="editingBlockId ? 'open' : ''" @click.self="closeEditDrawer()" id="editModalOverlay">
        <div class="edit-modal" @click.stop>
            <div class="h-[52px] flex items-center justify-between px-5 flex-shrink-0" style="border-bottom: 1px solid var(--border-subtle);">
                <div class="flex items-center gap-3">
                    <h3 class="text-sm font-bold gradient-text">Edit Block</h3>
                    <span id="drawerAutoSaveStatus" class="text-[10px] font-medium hidden" style="color: var(--text-faint);"></span>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="manualSaveFromModal()" class="text-[10px] font-medium px-3 py-1.5 rounded-lg transition-all" style="background: linear-gradient(135deg, #5c83ff, #3d6bff); color: white;">
                        <i class="fas fa-save mr-1"></i>Save & Close
                    </button>
                    <button @click="closeEditDrawer()" class="block-action-btn" style="color: var(--text-faint);" title="Close"><i class="fas fa-times"></i></button>
                </div>
            </div>
            <div class="edit-modal-body">
                <div class="edit-modal-preview" id="editModalPreview">
                    <div class="relative" style="width: 300px;">
                        <div class="absolute -inset-1 rounded-[2.8rem]" style="background: linear-gradient(180deg, rgba(61,107,255,0.12), rgba(255,255,255,0.03), rgba(61,107,255,0.08)); filter: blur(1px);"></div>
                        <div class="relative bg-black rounded-[2.2rem] p-1.5 shadow-2xl" style="border: 2px solid rgba(255,255,255,0.06); box-shadow: 0 20px 60px rgba(0,0,0,0.5);">
                            <div class="absolute top-0 left-1/2 -translate-x-1/2 w-24 h-5 bg-black rounded-b-2xl z-10 flex items-center justify-center">
                                <div class="w-14 h-3 rounded-full" style="background: rgba(255,255,255,0.04);"></div>
                            </div>
                            <div class="rounded-[1.8rem] overflow-hidden" style="height: calc(92vh - 120px); max-height: 700px; background: var(--bg-body);">
                                <iframe id="editPreviewFrame" src="" class="w-full h-full border-0 rounded-[1.8rem]"></iframe>
                            </div>
                            <div class="absolute bottom-1.5 left-1/2 -translate-x-1/2 w-24 h-1 rounded-full" style="background: rgba(255,255,255,0.06);"></div>
                        </div>
                    </div>
                </div>
                <div class="edit-modal-form" id="editDrawerContent">
                </div>
            </div>
        </div>
    </div>

    <div class="reorder-toast" id="reorderToast"><i class="fas fa-check-circle mr-2"></i>Order saved</div>
</div>

{{-- Hidden edit forms for each block (including children) --}}
@php
    $allEditBlocks = collect();
    foreach($blocks as $block) {
        $allEditBlocks->push($block);
        if ($block->isContainer() && $block->children) {
            foreach($block->children as $child) {
                $allEditBlocks->push($child);
            }
        }
    }
@endphp
@foreach($allEditBlocks as $block)
<template id="editForm_{{ $block->id }}">
    <form method="POST" action="{{ route('user.links.blocks.update', [$link, $block]) }}" onsubmit="return ajaxSaveBlock(event, this)">
        @csrf @method('PUT')
        <div class="mb-4">
            <div class="flex items-center gap-2 mb-4">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(61,107,255,0.1); border: 1px solid rgba(61,107,255,0.15);">
                    <i class="fas {{ $blockTypes[$block->type]['icon'] ?? 'fa-cube' }} text-blue-400 text-sm"></i>
                </div>
                <span class="text-sm font-semibold" style="color: var(--text-primary);">{{ $blockTypes[$block->type]['label'] ?? ucfirst($block->type) }}</span>
            </div>
        </div>
        @include('user.links.partials.block-settings-form', ['block' => $block])
        <div class="flex items-center gap-2 mt-6 pt-4" style="border-top: 1px solid var(--border-subtle);">
            <button type="submit" class="btn-primary text-sm py-2.5 px-6 flex-1 justify-center" id="saveBtn_{{ $block->id }}">Save Changes</button>
            <button type="button" onclick="closeEditDrawerGlobal()" class="btn-ghost text-sm py-2.5 px-4">Cancel</button>
        </div>
    </form>
</template>
@endforeach

<script>
function biolinkEditor() {
    return {
        // Inline "Templates, forms & more" panel (replaces the old gallery modal).
        specialOpen: false,
        specialMode: 'templates',
        specialSearch: '',
        // Pending-insert context, mirrored into the global vars the add
        // handlers read. Drives the palette "inserting after / into card" banner.
        insertAfterId: null,
        cardParentId: null,
        paletteSearch: '',
        paletteCategory: 'all',
        // Per-category collapse state for the grouped "Add blocks" palette.
        // Empty => every section starts expanded. Search / a specific tab
        // force sections open so matches are never hidden behind a collapse.
        // Persisted to localStorage per user+biolink so power users keep
        // their collapsed sections across reloads (loaded in init()).
        paletteCollapsed: {},
        paletteCollapsedKey: 'biolink:paletteCollapsed:{{ auth()->id() }}:{{ $link->id }}',
        loadPaletteCollapsed() {
            try {
                var raw = window.localStorage.getItem(this.paletteCollapsedKey);
                if (!raw) return;
                var saved = JSON.parse(raw);
                if (saved && typeof saved === 'object') this.paletteCollapsed = saved;
            } catch (e) { /* storage blocked or corrupt — fall back to all-expanded */ }
        },
        savePaletteCollapsed() {
            try {
                window.localStorage.setItem(this.paletteCollapsedKey, JSON.stringify(this.paletteCollapsed));
            } catch (e) { /* storage blocked / full — non-fatal */ }
        },
        paletteSectionLabels: @json($paletteSectionLabels ?? []),
        paletteSearchTerm() { return (this.paletteSearch || '').trim().toLowerCase(); },
        paletteSectionOpen(cat) {
            if (this.paletteSearchTerm() !== '') return true;
            if (this.paletteCategory !== 'all') return true;
            return !this.paletteCollapsed[cat];
        },
        togglePaletteSection(cat) {
            this.paletteCollapsed[cat] = !this.paletteCollapsed[cat];
            this.savePaletteCollapsed();
        },
        paletteSectionVisible(cat) {
            if (this.paletteCategory !== 'all' && this.paletteCategory !== cat) return false;
            var term = this.paletteSearchTerm();
            if (term === '') return true;
            var labels = this.paletteSectionLabels[cat] || [];
            return labels.some(function(l) { return l.includes(term); });
        },
        paletteItemVisible(cat, label) {
            if (this.paletteCategory !== 'all' && this.paletteCategory !== cat) return false;
            var term = this.paletteSearchTerm();
            if (term !== '' && !label.includes(term)) return false;
            return this.paletteSectionOpen(cat);
        },
        paletteAnyVisible() {
            var self = this;
            return Object.keys(this.paletteSectionLabels).some(function(cat) {
                return self.paletteSectionVisible(cat);
            });
        },
        cardTemplates: [],
        cardCategories: {},
        cardCategory: 'all',
        cardTemplatesLimit: 24,
        cardTemplatesLoading: false,
        cardTemplatesLoaded: false,
        _cardSearchTimer: null,
        editingBlockId: null,
        openSpecialPanel(mode) {
            this.specialMode = mode || 'templates';
            this.specialOpen = true;
            if (this.specialMode === 'templates') this.loadCardTemplates();
        },
        // Set/clear the pending-insert target. Keeps the legacy global vars in
        // sync so ajaxAddBlock / ajaxAddBlockWithSettings / applyCardTemplate
        // pick the right position.
        beginInsert(afterId) {
            this.insertAfterId = afterId;
            this.cardParentId = null;
            _insertAfterId = afterId;
            _cardGalleryParentId = null;
        },
        beginCardInsert(cardId) {
            this.insertAfterId = null;
            this.cardParentId = cardId;
            _insertAfterId = null;
            _cardGalleryParentId = cardId;
        },
        cancelInsert() {
            this.insertAfterId = null;
            this.cardParentId = null;
            _insertAfterId = null;
            _cardGalleryParentId = null;
        },
        visibleCardTemplates() {
            var q = (this.specialSearch || '').toLowerCase();
            var filtered = q
                ? this.cardTemplates.filter(t => (t.name + ' ' + (t.description || '')).toLowerCase().includes(q))
                : this.cardTemplates;
            return filtered.slice(0, this.cardTemplatesLimit);
        },
        _cardReqId: 0,
        loadCardTemplates(force) {
            if (!force && (this.cardTemplatesLoaded || this.cardTemplatesLoading)) return;
            // Guard against out-of-order responses: rapid category-chip switching
            // fires overlapping fetches with no cancellation, so a slower earlier
            // response could land after a newer one and overwrite the grid with
            // stale (or empty) data — which looked like "All sometimes shows no
            // templates / no scroll". Only the latest request may mutate state.
            var reqId = ++this._cardReqId;
            this.cardTemplatesLoading = true;
            this.cardTemplatesLimit = 24;
            var params = new URLSearchParams();
            if (this.cardCategory && this.cardCategory !== 'all') params.set('category', this.cardCategory);
            if (this.specialSearch) params.set('q', this.specialSearch);
            var url = '{{ route('user.links.templates.cards', $link) }}' + (params.toString() ? '?' + params.toString() : '');
            fetch(url, { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(d => {
                    if (reqId !== this._cardReqId) return;
                    this.cardTemplates = d.items || [];
                    if (d.categories) this.cardCategories = d.categories;
                    this.cardTemplatesLoaded = true;
                })
                .catch(() => { if (reqId === this._cardReqId) showToast('Failed to load templates', 'error'); })
                .finally(() => { if (reqId === this._cardReqId) this.cardTemplatesLoading = false; });
        },
        applyCardTemplate(id) {
            var fd = new FormData();
            fd.append('_token', _csrfToken());
            fd.append('template_id', id);
            if (_insertAfterId) fd.append('insert_after', _insertAfterId);
            var self = this;
            fetch('{{ route('user.links.templates.apply-card', $link) }}', {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: fd
            }).then(r => r.json()).then(d => {
                if (d.success && d.html) {
                    window.__editorInsertBlockCard(d, {});
                    self.specialOpen = false;
                    self.cancelInsert();
                    showToast('Card template added', 'success');
                } else if (d.success) {
                    showToast('Card template added', 'success');
                    setTimeout(function() { location.reload(); }, 400);
                } else {
                    showToast(d.error || 'Failed to apply template', 'error');
                }
            }).catch(() => showToast('Failed to apply template', 'error'));
        },
        init() {
            var self = this;
            this.loadPaletteCollapsed();
            window.addEventListener('open-edit-drawer', function(e) {
                self.editingBlockId = e.detail.id;
            });
            window.addEventListener('close-edit-drawer', function(e) {
                // Same in-app confirm guard as closeEditDrawer() so the
                // dispatch-based close path (Cancel buttons, programmatic
                // closes) can't silently drop a failed design change either.
                if (!(e && e.detail && e.detail.skipDirtyCheck) && !_confirmDiscardDesignChange()) return;
                self.editingBlockId = null;
                var c = document.getElementById('editDrawerContent');
                Alpine.destroyTree(c);
                c.innerHTML = '';
                _hideEditPreview();
            });
            // Per-block "+" / "add to card" buttons request a pending insert
            // and (for cards) open the templates panel.
            window.addEventListener('editor-begin-insert', function(e) {
                self.beginInsert(e.detail.afterId);
            });
            window.addEventListener('editor-begin-card-insert', function(e) {
                self.beginCardInsert(e.detail.cardId);
            });
            window.addEventListener('editor-clear-insert', function() {
                self.cancelInsert();
            });
            window.addEventListener('editor-close-special', function() {
                self.specialOpen = false;
            });
        },
        closeEditDrawer() {
            // In-app confirm so creators don't silently drop a failed
            // design change by clicking the overlay or the close (X)
            // button. Cancelling keeps the drawer open with the chip and
            // the same block selected; confirming proceeds with the close.
            if (!_confirmDiscardDesignChange()) return;
            this.editingBlockId = null;
            var container = document.getElementById('editDrawerContent');
            Alpine.destroyTree(container);
            container.innerHTML = '';
            _hideEditPreview();
        }
    }
}

function closeEditDrawerGlobal() {
    window.dispatchEvent(new CustomEvent('close-edit-drawer'));
}

// Returns true when the currently-open edit drawer has a `blockDesignsGallery`
// child whose `_error` flag is set — i.e. the creator's last design change
// failed and the red chip is still visible. Used by the close / switch
// guards below so navigating away from that block doesn't silently drop
// the unsaved attempt. Mirrors the `beforeunload` guard added in #1031,
// but for in-editor transitions that never trigger `beforeunload`.
function _hasPendingDesignError() {
    var c = document.getElementById('editDrawerContent');
    if (!c) return false;
    var nodes = c.querySelectorAll('[x-data]');
    for (var i = 0; i < nodes.length; i++) {
        var stack = nodes[i]._x_dataStack;
        if (!stack || !stack.length) continue;
        var d = stack[0];
        // Duck-type the gallery: it's the only x-data scope on the
        // edit form that owns both `_error` and a `blockId` matching
        // the gallery contract. Avoids coupling to a brittle x-data
        // selector that would break if the attribute string changes.
        if (d && typeof d._error !== 'undefined' && typeof d.blockId !== 'undefined') {
            if (d._error) return true;
        }
    }
    return false;
}

function _confirmDiscardDesignChange() {
    if (!_hasPendingDesignError()) return true;
    return confirm('You have an unsaved design change — discard it?');
}

var _editingBlockId = null;
var _drawerAutoSaveTimer = null;
var _autoSaveObserver = null;
var _injectedScripts = [];

function _hideEditPreview() {
    if (_drawerAutoSaveTimer) clearTimeout(_drawerAutoSaveTimer);
    if (_autoSaveObserver) { _autoSaveObserver.disconnect(); _autoSaveObserver = null; }
    _injectedScripts.forEach(function(s) { if (s.parentNode) s.parentNode.removeChild(s); });
    _injectedScripts = [];
    var pFrame = document.getElementById('editPreviewFrame');
    if (pFrame) pFrame.src = '';
    var status = document.getElementById('drawerAutoSaveStatus');
    if (status) status.classList.add('hidden');
    _editingBlockId = null;
}

function openEditDrawer(blockId) {
    // Guard the in-editor "switch to a different block" transition the
    // same way we guard close: if the currently-open block has a failed
    // design change still showing in the chip, confirm before swapping
    // it out. Re-opening the same block (e.g. refreshBlockEditor() after
    // a successful action) is exempt — _error would already be cleared,
    // but we also short-circuit the check to keep refreshes silent.
    if (_editingBlockId && String(blockId) !== String(_editingBlockId)) {
        if (!_confirmDiscardDesignChange()) return;
    }
    _editingBlockId = blockId;
    var container = document.getElementById('editDrawerContent');
    container.innerHTML = '<div class="flex items-center justify-center py-16"><i class="fas fa-spinner fa-spin text-2xl" style="color: var(--text-faint);"></i></div>';
    window.dispatchEvent(new CustomEvent('open-edit-drawer', { detail: { id: blockId } }));

    var editFormUrl = '{{ route("user.links.blocks.editForm", [$link, "__ID__"]) }}'.replace('__ID__', blockId);
    fetch(editFormUrl, {
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': _csrfToken() }
    }).then(function(r) { return r.json(); }).then(function(data) {
        if (!data.html) { container.innerHTML = '<p class="text-center py-8" style="color:var(--text-muted);">Failed to load</p>'; return; }
        _injectEditFormHtml(container, data.html, blockId);
    }).catch(function() {
        var tmpl = document.getElementById('editForm_' + blockId);
        if (tmpl) {
            _injectEditFormHtml(container, tmpl.innerHTML, blockId);
        } else {
            container.innerHTML = '<p class="text-center py-8" style="color:var(--text-muted);">Failed to load</p>';
        }
    });
}

function _injectEditFormHtml(container, html, blockId) {
    var scripts = [];
    var div = document.createElement('div');
    div.innerHTML = html;
    div.querySelectorAll('script').forEach(function(s) {
        scripts.push(s.textContent);
        s.remove();
    });
    container.innerHTML = div.innerHTML;

    _injectedScripts.forEach(function(s) { if (s.parentNode) s.parentNode.removeChild(s); });
    _injectedScripts = [];
    scripts.forEach(function(code) {
        try {
            var script = document.createElement('script');
            script.textContent = code;
            document.body.appendChild(script);
            _injectedScripts.push(script);
        } catch(e) { console.warn('Script exec error:', e); }
    });

    Alpine.initTree(container);

    // Restore the style-tab selection captured before a refreshBlockEditor()
    // call, so applying a variant doesn't snap the user back to a default
    // tab or collapse the expanded Block Styling section.
    if (window.__pendingStyleTabState) {
        var pending = window.__pendingStyleTabState;
        window.__pendingStyleTabState = null;
        setTimeout(function() {
            var root = container.querySelector('[data-style-root]');
            if (root && root._x_dataStack && root._x_dataStack[0]) {
                root._x_dataStack[0].showStyle = pending.show;
                root._x_dataStack[0].activeStyleTab = pending.tab;
            }
        }, 0);
    }

    var previewUrl = '{{ url("/" . $link->alias) }}' + '?_editBlock=' + blockId + '&_t=' + Date.now();
    var pFrame = document.getElementById('editPreviewFrame');
    if (pFrame) pFrame.src = previewUrl;

    _initDrawerAutoSave(container);
}

function manualSaveFromModal() {
    var container = document.getElementById('editDrawerContent');
    var form = container ? container.querySelector('form') : null;
    if (form) {
        var btn = form.querySelector('button[type="submit"]');
        if (btn) btn.click();
    }
}

function _refreshEditPreview() {
    var pFrame = document.getElementById('editPreviewFrame');
    if (pFrame && pFrame.src) {
        var base = '{{ url("/" . $link->alias) }}';
        pFrame.src = base + '?_editBlock=' + (_editingBlockId || '') + '&_t=' + Date.now();
    }
    refreshPreview();
}

// Re-fetch the entire edit-block form so granular controls (Look / Layout /
// Text) reflect the latest server-side `_style` payload, e.g. after the
// Designs gallery applied a variant or restored the custom snapshot. We
// preserve the user's current style-tab selection (and the expanded state
// of the Block Styling section) so they don't get snapped back to defaults
// after every variant click. The freshly-rendered Designs gallery already
// receives the new `currentVariant` from the server, so the just-selected
// card stays highlighted automatically.
function refreshBlockEditor() {
    if (!_editingBlockId) return;
    var container = document.getElementById('editDrawerContent');
    if (!container) return;
    var styleRoot = container.querySelector('[data-style-root]');
    var saved = { tab: 'designs', show: true };
    if (styleRoot && styleRoot._x_dataStack && styleRoot._x_dataStack[0]) {
        var s = styleRoot._x_dataStack[0];
        saved.tab = s.activeStyleTab || 'designs';
        saved.show = (typeof s.showStyle === 'boolean') ? s.showStyle : true;
    }
    window.__pendingStyleTabState = saved;
    openEditDrawer(_editingBlockId);
}

function _showAutoSaveStatus(text, type) {
    var el = document.getElementById('drawerAutoSaveStatus');
    if (!el) return;
    el.classList.remove('hidden');
    el.style.color = type === 'saving' ? 'var(--text-faint)' : (type === 'saved' ? '#10b981' : '#ef4444');
    el.innerHTML = text;
    if (type === 'saved') {
        setTimeout(function() { el.classList.add('hidden'); }, 2000);
    }
}

function _drawerAutoSave(form) {
    _showAutoSaveStatus('<i class="fas fa-spinner fa-spin mr-1"></i>Saving...', 'saving');
    var fd = new FormData(form);
    fetch(form.action, {
        method: 'POST',
        headers: { 'Accept': 'application/json' },
        body: fd
    }).then(function(r) { return r.json(); }).then(function(data) {
        if (data.success) {
            _showAutoSaveStatus('<i class="fas fa-check mr-1"></i>Saved', 'saved');
            _refreshEditPreview();
        } else {
            _showAutoSaveStatus('<i class="fas fa-exclamation-circle mr-1"></i>Error', 'error');
        }
    }).catch(function() {
        _showAutoSaveStatus('<i class="fas fa-exclamation-circle mr-1"></i>Error', 'error');
    });
}

function _initDrawerAutoSave(container) {
    if (_drawerAutoSaveTimer) clearTimeout(_drawerAutoSaveTimer);
    if (_autoSaveObserver) { _autoSaveObserver.disconnect(); _autoSaveObserver = null; }

    function onFieldChange() {
        if (_drawerAutoSaveTimer) clearTimeout(_drawerAutoSaveTimer);
        _drawerAutoSaveTimer = setTimeout(function() {
            var form = container.querySelector('form');
            if (form) _drawerAutoSave(form);
        }, 800);
    }

    function onFileChange() {
        var form = container.querySelector('form');
        if (form) {
            if (_drawerAutoSaveTimer) clearTimeout(_drawerAutoSaveTimer);
            _drawerAutoSaveTimer = setTimeout(function() { _drawerAutoSave(form); }, 300);
        }
    }

    function bindElement(el) {
        if (el._autoSaveBound) return;
        el._autoSaveBound = true;
        if (el.type === 'file') {
            el.addEventListener('change', onFileChange);
        } else {
            el.addEventListener('input', onFieldChange);
            el.addEventListener('change', onFieldChange);
        }
    }

    setTimeout(function() {
        container.querySelectorAll('input, select, textarea').forEach(bindElement);

        _autoSaveObserver = new MutationObserver(function() {
            container.querySelectorAll('input, select, textarea').forEach(bindElement);
        });
        _autoSaveObserver.observe(container, { childList: true, subtree: true });
    }, 100);
}

// Live profile-card preview: push the current stats/badges repeater state into
// the edit-preview iframe as the owner types/reorders, so the card updates
// instantly without waiting for the debounced autosave + iframe reload. Caps
// (6 stats, 12 badges) mirror the renderer/repeaters.
function _postPcLive(payload) {
    var pFrame = document.getElementById('editPreviewFrame');
    if (!pFrame || !pFrame.contentWindow || !_editingBlockId) return;
    payload.type = '1inme-pc-live';
    payload.blockId = _editingBlockId;
    try { pFrame.contentWindow.postMessage(payload, window.location.origin); } catch (e) {}
}
function pcLivePreviewStats(statsJson) {
    var stats; try { stats = JSON.parse(statsJson); } catch (e) { return; }
    if (!Array.isArray(stats)) return;
    _postPcLive({ stats: stats.slice(0, 6) });
}
function pcLivePreviewBadges(badgesJson) {
    var badges; try { badges = JSON.parse(badgesJson); } catch (e) { return; }
    if (!Array.isArray(badges)) return;
    _postPcLive({ badges: badges.slice(0, 12) });
}

var _csrfToken = function() { return document.querySelector('meta[name="csrf-token"]').content; };

function showToast(msg, type) {
    var colors = { success: 'linear-gradient(135deg, #10b981, #059669)', error: 'linear-gradient(135deg, #ef4444, #dc2626)', info: 'linear-gradient(135deg, #5c83ff, #3d6bff)' };
    var icons = { success: 'fa-check-circle', error: 'fa-exclamation-circle', info: 'fa-info-circle' };
    var toast = document.createElement('div');
    toast.className = 'fixed bottom-4 right-4 z-[9999] px-4 py-2.5 rounded-xl text-xs font-medium text-white shadow-lg transition-all';
    toast.style.cssText = 'background:' + (colors[type] || colors.info) + ';';
    toast.innerHTML = '<i class="fas ' + (icons[type] || icons.info) + ' mr-1.5"></i>' + msg;
    document.body.appendChild(toast);
    setTimeout(function() { toast.style.opacity = '0'; setTimeout(function() { toast.remove(); }, 300); }, 2500);
}


function setGridSpan(blockId, span, btn) {
    var card = btn.closest('.block-card');
    var wrapper = card.closest('.block-card-wrapper') || card;
    var row = card.querySelector('.grid-span-row');
    row.querySelectorAll('.span-btn').forEach(function(b) { b.classList.remove('active'); });
    btn.classList.add('active');
    card.dataset.gridSpan = span;
    wrapper.style.gridColumn = 'span ' + span;
    var badge = document.querySelector('[data-span-badge="' + blockId + '"]');
    if (badge) {
        badge.textContent = span + '/12';
        badge.style.display = span >= 12 ? 'none' : '';
    }
    var url = '{{ route("user.links.blocks.update", [$link, "__ID__"]) }}'.replace('__ID__', blockId);
    fetch(url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': _csrfToken(),
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-HTTP-Method-Override': 'PUT'
        },
        body: JSON.stringify({ style: { grid_span: span } })
    }).then(function(r) { return r.json(); }).then(function(data) {
        if (data.success) {
            showToast('Width updated', 'success');
            refreshPreview();
        }
    }).catch(function() {
        showToast('Failed to save width', 'error');
    });
}

function setChildGridSpan(blockId, span, btn) {
    var card = btn.closest('.child-block-card');
    var row = card.querySelector('.child-span-row');
    row.querySelectorAll('.child-span-btn').forEach(function(b) { b.classList.remove('active'); });
    btn.classList.add('active');
    var badge = document.querySelector('[data-child-span-badge="' + blockId + '"]');
    if (badge) {
        badge.textContent = span + '/12';
        badge.style.display = span >= 12 ? 'none' : '';
    }
    var url = '{{ route("user.links.blocks.update", [$link, "__ID__"]) }}'.replace('__ID__', blockId);
    fetch(url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': _csrfToken(),
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-HTTP-Method-Override': 'PUT'
        },
        body: JSON.stringify({ style: { grid_span: span } })
    }).then(function(r) { return r.json(); }).then(function(data) {
        if (data.success) {
            showToast('Width updated', 'success');
            refreshPreview();
        }
    }).catch(function() {
        showToast('Failed to save width', 'error');
    });
}

function moveBlockToParent(blockId, parentId) {
    var url = '{{ route("user.links.blocks.move", [$link, "__ID__"]) }}'.replace('__ID__', blockId);
    return fetch(url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': _csrfToken(),
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ parent_id: parentId })
    }).then(function(r) { return r.json(); });
}

function ajaxToggleBlock(btn, url, blockId) {
    btn.disabled = true;
    fetch(url, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': _csrfToken(), 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: '{}'
    }).then(function(r) { return r.json(); }).then(function(data) {
        btn.disabled = false;
        if (data.success) {
            var icon = btn.querySelector('i');
            var card = btn.closest('.block-card');
            if (data.block && data.block.is_active) {
                icon.className = 'fas fa-eye';
                btn.title = 'Hide';
                if (card) card.style.opacity = '1';
            } else {
                icon.className = 'fas fa-eye-slash';
                btn.title = 'Show';
                if (card) card.style.opacity = '0.5';
            }
            showToast('Block ' + (data.block && data.block.is_active ? 'shown' : 'hidden'), 'success');
            refreshPreview();
        }
    }).catch(function() { btn.disabled = false; showToast('Failed to toggle', 'error'); });
}

function ajaxDeleteBlock(btn, url, blockId) {
    window.themedConfirm({
        title: 'Delete this block?',
        message: 'This permanently removes the block from your page. This cannot be undone.',
        confirmText: 'Delete block',
        confirmIcon: 'fa-trash',
        iconClass: 'fa-trash',
        onConfirm: function () {
            btn.disabled = true;
            fetch(url, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': _csrfToken(), 'Accept': 'application/json' }
            }).then(function(r) { return r.json(); }).then(function(data) {
                if (data.success) {
                    var card = document.querySelector('.block-card[data-block-id="' + blockId + '"]');
                    var wrapper = card ? (card.closest('.block-card-wrapper') || card) : null;
                    if (wrapper) { wrapper.style.transition = 'all 0.3s'; wrapper.style.opacity = '0'; wrapper.style.transform = 'translateX(-20px)'; setTimeout(function() { wrapper.remove(); }, 300); }
                    var tmpl = document.getElementById('editForm_' + blockId);
                    if (tmpl) tmpl.remove();
                    showToast('Block deleted', 'success');
                    refreshPreview();
                } else {
                    btn.disabled = false;
                    showToast(data.error || 'Failed to delete', 'error');
                }
            }).catch(function() { btn.disabled = false; showToast('Failed to delete', 'error'); });
        }
    });
}

function ajaxDeleteAllBlocks(btn) {
    window.themedConfirm({
        title: 'Delete all blocks?',
        message: 'This permanently removes every block on this page. Verified blocks are kept. This cannot be undone.',
        confirmText: 'Delete all',
        confirmIcon: 'fa-trash-alt',
        iconClass: 'fa-trash-alt',
        onConfirm: function () {
            btn.disabled = true;
            fetch(@json(route('user.links.blocks.bulkDestroy', $link)), {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': _csrfToken(), 'Accept': 'application/json' }
            }).then(function(r) { return r.json(); }).then(function(data) {
                if (data && data.success) {
                    showToast((data.deleted || 0) + ' block(s) deleted', 'success');
                    setTimeout(function() { window.location.reload(); }, 400);
                } else {
                    btn.disabled = false;
                    showToast((data && data.error) || 'Failed to delete blocks', 'error');
                }
            }).catch(function() { btn.disabled = false; showToast('Failed to delete blocks', 'error'); });
        }
    });
}

// Source of truth for the pending-insert target, read by every add handler.
// Alpine mirrors these into insertAfterId / cardParentId to drive the banner.
var _cardGalleryParentId = null;
var _insertAfterId = null;

// Clear the pending-insert target everywhere (globals + Alpine banner).
function _clearInsertState() {
    _insertAfterId = null;
    _cardGalleryParentId = null;
    window.dispatchEvent(new CustomEvent('editor-clear-insert'));
}

// Close the inline "Templates, forms & more" panel.
function _closeSpecialPanel() {
    window.dispatchEvent(new CustomEvent('editor-close-special'));
}

function ajaxAddBlock(type, url, parentId) {
    var fd = new FormData();
    fd.append('type', type);
    fd.append('_token', _csrfToken());
    var pid = parentId || _cardGalleryParentId;
    if (pid) fd.append('parent_id', pid);
    if (_insertAfterId) fd.append('insert_after', _insertAfterId);
    fetch(url, {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': _csrfToken() },
        body: fd
    }).then(function(r) { return r.json(); }).then(function(data) {
        if (data.success && (data.html || data.child_html)) {
            window.__editorInsertBlockCard(data, {});
            _clearInsertState();
            showToast('Block added', 'success');
        } else if (data.success) {
            showToast('Block added', 'success');
            setTimeout(function() { location.reload(); }, 400);
        } else {
            showToast(data.error || 'Failed to add block', 'error');
        }
    }).catch(function() { showToast('Failed to add block', 'error'); });
}

// Click-to-add for palette block tiles — honors any pending-insert target set
// via the per-block "+" / "add to card" buttons (does NOT reset it here).
function paletteClickAdd(type) {
    ajaxAddBlock(type, '{{ route("user.links.blocks.store", $link) }}', null);
}

function ajaxAddBlockWithSettings(type, settings, url) {
    var fd = new FormData();
    fd.append('type', type);
    fd.append('_token', _csrfToken());
    Object.keys(settings || {}).forEach(function(k) {
        var v = settings[k];
        fd.append('settings[' + k + ']', v === null || v === undefined ? '' : v);
    });
    var pid = _cardGalleryParentId;
    if (pid) fd.append('parent_id', pid);
    if (_insertAfterId) fd.append('insert_after', _insertAfterId);
    fetch(url, {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': _csrfToken() },
        body: fd
    }).then(function(r) { return r.json(); }).then(function(data) {
        if (data.success && (data.html || data.child_html)) {
            window.__editorInsertBlockCard(data, {});
            _clearInsertState();
            _closeSpecialPanel();
            showToast('Block added', 'success');
        } else if (data.success) {
            showToast('Block added', 'success');
            setTimeout(function() { location.reload(); }, 400);
        } else {
            showToast(data.error || 'Failed to add block', 'error');
        }
    }).catch(function() { showToast('Failed to add block', 'error'); });
}

// "Add block to card" — set a pending card-insert target and focus the palette
// so the next palette tile click drops the block into this card.
function openCardGallery(cardId) {
    window.dispatchEvent(new CustomEvent('editor-begin-card-insert', { detail: { cardId: cardId } }));
    focusBlockPalette();
}

// Per-block "+" — set a pending after-this-block target and focus the palette.
function openInsertGallery(afterBlockId) {
    window.dispatchEvent(new CustomEvent('editor-begin-insert', { detail: { afterId: afterBlockId } }));
    focusBlockPalette();
}

// Bring the left "Add blocks" palette into view and focus its search. On small
// screens the palette stacks above the canvas, so this scrolls to it.
function focusBlockPalette() {
    var col = document.getElementById('editorPaletteCol');
    if (col) {
        var rm = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        try { col.scrollIntoView({ behavior: rm ? 'auto' : 'smooth', block: 'nearest' }); } catch (e) { col.scrollIntoView(); }
        var search = col.querySelector('input[type="text"], input[type="search"]');
        if (search) { try { search.focus({ preventScroll: true }); } catch (e) {} }
    }
}

function ajaxSaveBlock(e, form) {
    e.preventDefault();
    var btn = form.querySelector('button[type="submit"]');
    var origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Saving...';

    var fd = new FormData(form);
    fetch(form.action, {
        method: 'POST',
        headers: { 'Accept': 'application/json' },
        body: fd
    }).then(function(r) { return r.json(); }).then(function(data) {
        btn.disabled = false;
        btn.innerHTML = origText;
        if (data.success) {
            showToast('Block saved', 'success');
            _refreshEditPreview();
            closeEditDrawerGlobal();
        } else {
            showToast(data.error || 'Failed to save', 'error');
        }
    }).catch(function() {
        btn.disabled = false;
        btn.innerHTML = origText;
        showToast('Failed to save', 'error');
    });
    return false;
}

document.addEventListener('DOMContentLoaded', function() {
    var el = document.getElementById('blockList');
    var _moveUrl = '{{ route("user.links.blocks.move", [$link, "__ID__"]) }}';
    var _reorderUrl = '{{ route("user.links.blocks.reorder", $link) }}';
    var _storeUrl = '{{ route("user.links.blocks.store", $link) }}';
    var _prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var _dropAnim = _prefersReducedMotion ? 0 : 250;
    var _topSortable = null;
    var _cardSortables = {};

    // Layout-container block types (card / grid / grid_auto). Kept in sync
    // with BiolinkBlock::CONTAINER_TYPES so the drag rules below treat every
    // container identically — containers can't nest inside one another.
    var CONTAINER_TYPES = @json(\App\Modules\User\Models\BiolinkBlock::CONTAINER_TYPES);

    // Whether a palette clone (or real block) of the given drag element is
    // allowed to land inside a container's child list. Containers can't nest
    // in containers. Kept as a named predicate so the put rule is unit-testable.
    function cardPutAllows(dragEl) {
        var bt = dragEl.dataset && dragEl.dataset.blockType;
        if (bt && CONTAINER_TYPES.indexOf(bt) !== -1) return false;
        var inner = dragEl.querySelector && dragEl.querySelector('.card-container-block');
        return !(dragEl.classList.contains('card-container-block') || inner);
    }

    // Build a DOM node from a rendered partial HTML string.
    function _makeNode(html) {
        var tpl = document.createElement('template');
        tpl.innerHTML = (html || '').trim();
        return tpl.content.firstElementChild;
    }

    // Activate a freshly-inserted node: hydrate Alpine and, for a card, wire its
    // child sortable so blocks can be dropped inside immediately.
    function _activateNode(node) {
        if (!node) return;
        if (window.Alpine && Alpine.initTree) { try { Alpine.initTree(node); } catch (e) {} }
        var cl = node.querySelector ? node.querySelector('.card-child-list') : null;
        if (cl) initCardChildSortable(cl);
    }

    // Keep the top-level count chip, reorder hint and empty-state in sync with
    // the number of top-level blocks currently in the canvas.
    function updateBlockChrome() {
        var count = el ? el.querySelectorAll(':scope > .block-card-wrapper').length : 0;
        var chip = document.getElementById('blockCountChip');
        if (chip) {
            chip.style.display = count > 0 ? '' : 'none';
            var strong = chip.querySelector('[data-block-count]');
            if (strong) strong.textContent = count;
        }
        var hint = document.getElementById('reorderHint');
        if (hint) hint.style.display = count > 1 ? '' : 'none';
        var empty = document.getElementById('blockListEmpty');
        if (empty) empty.style.display = count === 0 ? '' : 'none';
    }
    window.__editorUpdateBlockChrome = updateBlockChrome;

    function _refreshPreviewSafe() {
        if (typeof refreshPreview === 'function') refreshPreview();
    }

    // Update a card's "Child Blocks (N)" counter from its live child list.
    function _syncChildCount(parentId, listEl) {
        var cc = document.querySelector('[data-card-child-count="' + parentId + '"]');
        if (cc) cc.textContent = listEl.querySelectorAll('.child-block-card').length;
    }

    // Insert a server-rendered block in place. Handles both top-level
    // (data.html) and into-card (data.child_html + data.parent_id) inserts,
    // honoring data.insert_after for positioning. opts.referenceNode, when
    // given, inserts immediately before that node (used by palette drops).
    window.__editorInsertBlockCard = function(data, opts) {
        opts = opts || {};
        if (data.child_html && data.parent_id) {
            var list = el ? el.querySelector('.card-child-list[data-card-id="' + data.parent_id + '"]') : null;
            if (!list) { location.reload(); return; }
            var emptyHint = list.querySelector('.card-empty-hint');
            if (emptyHint) emptyHint.remove();
            var cnode = _makeNode(data.child_html);
            if (!cnode) return;
            if (opts.referenceNode && opts.referenceNode.parentNode === list) {
                list.insertBefore(cnode, opts.referenceNode);
            } else if (data.insert_after) {
                var cref = list.querySelector('.child-block-card[data-block-id="' + data.insert_after + '"]');
                if (cref) list.insertBefore(cnode, cref.nextSibling); else list.appendChild(cnode);
            } else {
                list.appendChild(cnode);
            }
            _activateNode(cnode);
            _syncChildCount(data.parent_id, list);
        } else if (data.html) {
            if (!el) { location.reload(); return; }
            var node = _makeNode(data.html);
            if (!node) return;
            if (opts.referenceNode && opts.referenceNode.parentNode === el) {
                el.insertBefore(node, opts.referenceNode);
            } else if (data.insert_after) {
                var ref = el.querySelector(':scope > .block-card-wrapper[data-block-id="' + data.insert_after + '"]');
                if (ref) el.insertBefore(node, ref.nextSibling); else el.appendChild(node);
            } else {
                el.appendChild(node);
            }
            _activateNode(node);
            updateBlockChrome();
        }
        _refreshPreviewSafe();
    };

    // Create a real block from a palette drop and place it where it landed
    // (before the hidden clone), then persist the new order — all in place, no
    // reload. Reuses the store() flow (BlockDefaults placeholder seeding).
    function paletteCreateBlock(type, parentId, cloneEl, listEl) {
        var fd = new FormData();
        fd.append('type', type);
        fd.append('_token', _csrfToken());
        if (parentId) fd.append('parent_id', parentId);
        var removeClone = function() { if (cloneEl && cloneEl.parentNode) cloneEl.parentNode.removeChild(cloneEl); };
        return fetch(_storeUrl, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': _csrfToken() },
            body: fd
        }).then(function(r) { return r.json(); }).then(function(data) {
            if (!data || !data.success || !(data.html || data.child_html)) {
                showToast((data && data.error) || 'Failed to add block', 'error');
                removeClone();
                return;
            }
            showToast('Block added', 'success');
            window.__editorInsertBlockCard(data, { referenceNode: cloneEl });
            removeClone();
            // Persist the resulting order for this level.
            var sel = parentId ? '.child-block-card' : ':scope > .block-card-wrapper';
            reorderList(listEl, sel).catch(function() {});
            _refreshPreviewSafe();
        }).catch(function() { showToast('Failed to add block', 'error'); removeClone(); });
    }

    // Detect a palette clone landing in a list (top-level canvas or a card
    // child list) and turn it into a real block creation. Returns true when it
    // handled the drop so the existing move-block logic is skipped. The clone is
    // hidden (kept in DOM to mark the drop slot) and removed after insertion.
    function handlePaletteDrop(evt, listEl, parentId) {
        var item = evt.item;
        if (!item || !item.classList || !item.classList.contains('palette-block-item')) return false;
        var type = item.dataset ? item.dataset.blockType : null;
        item.style.display = 'none';
        if (!type) { if (item.parentNode) item.parentNode.removeChild(item); return true; }
        paletteCreateBlock(type, parentId, item, listEl);
        return true;
    }

    function reorderList(container, selector) {
        var ids = [];
        container.querySelectorAll(selector).forEach(function(card) {
            ids.push(parseInt(card.dataset.blockId));
        });
        if (ids.length === 0) return Promise.resolve();
        return fetch(_reorderUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': _csrfToken(), 'Accept': 'application/json' },
            body: JSON.stringify({ blocks: ids })
        });
    }

    function doMoveBlock(blockId, parentId) {
        return fetch(_moveUrl.replace('__ID__', blockId), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': _csrfToken(), 'Accept': 'application/json' },
            body: JSON.stringify({ parent_id: parentId })
        }).then(function(r) { return r.json(); });
    }

    if (el) {
        _topSortable = new Sortable(el, {
            handle: '.handle, .child-handle',
            animation: _dropAnim,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            easing: 'cubic-bezier(0.4, 0, 0.2, 1)',
            group: { name: 'blocks', pull: true, put: function(to, from, dragEl) {
                var inner = dragEl.querySelector('.card-container-block') || dragEl.classList.contains('card-container-block');
                return !inner;
            }},
            draggable: '.block-card-wrapper',
            filter: '.card-children-area, .card-child-list, .child-span-row, .grid-span-row, .insert-block-btn',
            onAdd: function(evt) {
                if (handlePaletteDrop(evt, el, null)) return;
                var blockId = parseInt(evt.item.dataset.blockId);
                var fromList = evt.from;
                doMoveBlock(blockId, null).then(function(data) {
                    if (data.success && (data.html || data.child_html)) {
                        var node = _makeNode(data.html || data.child_html);
                        if (node) el.insertBefore(node, evt.item);
                        if (evt.item.parentNode) evt.item.parentNode.removeChild(evt.item);
                        _activateNode(node);
                        reorderList(el, ':scope > .block-card-wrapper').catch(function() {});
                        if (fromList && fromList.dataset && fromList.dataset.cardId) _syncChildCount(fromList.dataset.cardId, fromList);
                        updateBlockChrome();
                        showToast('Block moved out of card', 'success');
                        _refreshPreviewSafe();
                    } else {
                        showToast((data && data.error) || 'Move failed', 'error');
                        location.reload();
                    }
                }).catch(function() { location.reload(); });
            },
            onEnd: function(evt) {
                if (evt.from === evt.to) {
                    reorderList(el, ':scope > .block-card-wrapper').then(function(r) {
                        if (r && r.ok) {
                            showToast('Order saved', 'success');
                            refreshPreview();
                        }
                    });
                }
            }
        });
    }

    function initCardChildSortable(childList) {
        var cardId = parseInt(childList.dataset.cardId);
        _cardSortables[cardId] = new Sortable(childList, {
            handle: '.child-handle, .handle',
            animation: _dropAnim,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            easing: 'cubic-bezier(0.4, 0, 0.2, 1)',
            group: { name: 'blocks', pull: true, put: function(to, from, dragEl) {
                // Block cards (real or palette 'card' clones) can't nest in a card.
                return cardPutAllows(dragEl);
            }},
            draggable: '.child-block-card, .block-card, .block-card-wrapper',
            onAdd: function(evt) {
                if (handlePaletteDrop(evt, childList, cardId)) return;
                var blockId = parseInt(evt.item.dataset.blockId);
                var hasCard = evt.item.querySelector && evt.item.querySelector('.card-container-block');
                if (evt.item.classList.contains('card-container-block') || hasCard) {
                    showToast('Cannot put a card inside another card', 'error');
                    location.reload();
                    return;
                }
                var fromList = evt.from;
                doMoveBlock(blockId, cardId).then(function(data) {
                    if (data.success && (data.child_html || data.html)) {
                        var emptyHint = childList.querySelector('.card-empty-hint');
                        if (emptyHint) emptyHint.remove();
                        var node = _makeNode(data.child_html || data.html);
                        if (node) childList.insertBefore(node, evt.item);
                        if (evt.item.parentNode) evt.item.parentNode.removeChild(evt.item);
                        _activateNode(node);
                        reorderList(childList, '.child-block-card').catch(function() {});
                        _syncChildCount(cardId, childList);
                        if (fromList === el) updateBlockChrome();
                        else if (fromList && fromList.dataset && fromList.dataset.cardId) _syncChildCount(fromList.dataset.cardId, fromList);
                        showToast('Block moved into card', 'success');
                        _refreshPreviewSafe();
                    } else {
                        showToast((data && data.error) || 'Move failed', 'error');
                        location.reload();
                    }
                }).catch(function() { location.reload(); });
            },
            onEnd: function(evt) {
                if (evt.from === evt.to) {
                    var ids = [];
                    childList.querySelectorAll('.child-block-card').forEach(function(card) {
                        ids.push(parseInt(card.dataset.blockId));
                    });
                    fetch(_reorderUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': _csrfToken(), 'Accept': 'application/json' },
                        body: JSON.stringify({ blocks: ids })
                    }).then(function(r) {
                        if (r.ok) {
                            showToast('Order saved', 'success');
                            refreshPreview();
                        }
                    });
                }
            }
        });
    }

    document.querySelectorAll('.card-child-list').forEach(initCardChildSortable);

    // Persistent block-type palette — a clone source. Dragging an item drops a
    // clone into the canvas / a card child list, which the onAdd handlers above
    // turn into a real block at the drop position. Click-to-add is the fallback.
    var paletteEl = document.getElementById('paletteList');
    if (paletteEl) {
        new Sortable(paletteEl, {
            group: { name: 'blocks', pull: 'clone', put: false },
            sort: false,
            animation: _dropAnim,
            draggable: '.palette-block-item',
            ghostClass: 'palette-drop-ghost',
            dragClass: 'sortable-drag',
            easing: 'cubic-bezier(0.4, 0, 0.2, 1)',
            onStart: function() {
                if (el) el.classList.add('palette-dragging');
                document.querySelectorAll('.card-child-list').forEach(function(c) { c.classList.add('palette-dragging'); });
            },
            onEnd: function() {
                if (el) el.classList.remove('palette-dragging');
                document.querySelectorAll('.card-child-list').forEach(function(c) { c.classList.remove('palette-dragging'); });
            }
        });
    }

    // ── Test-only hooks ────────────────────────────────────────────────────
    // Enabled only when a Playwright init script sets window.__E2E__ before
    // page scripts run; production never sets the flag, so this stays inert.
    // Native HTML5 drag-and-drop is unreliable to drive headless, so the
    // browser test exercises the real palette-drop pipeline (handlePaletteDrop
    // → paletteCreateBlock → store/reorder) and the real put/animation config
    // deterministically through these handles.
    if (window.__E2E__) {
        window.__editorTest = {
            dropAnim: _dropAnim,
            prefersReducedMotion: _prefersReducedMotion,
            topAnimation: _topSortable ? _topSortable.options.animation : null,
            cardIds: function() { return Object.keys(_cardSortables); },
            cardAnimation: function(cardId) {
                var s = _cardSortables[cardId];
                return s ? s.options.animation : null;
            },
            // Mirror SortableJS's card-list put gate for a palette clone of
            // `type` (e.g. a 'card' tile must be rejected inside a card).
            canDropInCard: function(type) {
                return cardPutAllows({
                    dataset: { blockType: type },
                    classList: { contains: function() { return false; } },
                    querySelector: function() { return null; }
                });
            },
            // Clone a palette tile, insert it into the target list at `index`
            // (counting only real block cards), then run the real drop handler
            // — exactly what SortableJS's onAdd does after a manual drag. Pass
            // parentCardId to drop into a Card Container, or null for top level.
            simulatePaletteDrop: function(type, parentCardId, index) {
                var tile = document.querySelector('.palette-block-item[data-block-type="' + type + '"]');
                if (!tile) throw new Error('No palette tile for type: ' + type);
                var clone = tile.cloneNode(true);
                var listEl, parentId, cardSel;
                if (parentCardId) {
                    listEl = document.querySelector('.card-child-list[data-card-id="' + parentCardId + '"]');
                    parentId = parentCardId;
                    cardSel = ':scope > .child-block-card';
                } else {
                    listEl = document.getElementById('blockList');
                    parentId = null;
                    cardSel = ':scope > .block-card-wrapper';
                }
                if (!listEl) throw new Error('No drop list found');
                var cards = listEl.querySelectorAll(cardSel);
                if (typeof index === 'number' && index < cards.length) {
                    listEl.insertBefore(clone, cards[index]);
                } else {
                    listEl.appendChild(clone);
                }
                return handlePaletteDrop({ item: clone }, listEl, parentId);
            }
        };
        window.dispatchEvent(new CustomEvent('editor-test-ready'));
    }
});
</script>
@endsection
