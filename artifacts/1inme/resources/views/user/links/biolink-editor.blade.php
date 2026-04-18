@extends('user.layouts.app')
@section('title', 'Blocks - ' . ($link->title ?: $link->alias))
@section('breadcrumb_parent', 'Links')
@section('breadcrumb_parent_url', route('user.links.index'))

@section('content')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>

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
        border-color: rgba(124,58,237,0.15);
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
        background: rgba(124,58,237,0.15);
        color: #a78bfa;
        border-color: rgba(124,58,237,0.3);
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
        border-color: rgba(124,58,237,0.2);
        background: linear-gradient(135deg, var(--bg-card), rgba(124,58,237,0.03));
    }
    .card-container-block:hover {
        border-color: rgba(124,58,237,0.3);
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
        background: rgba(124,58,237,0.15);
        color: #a78bfa;
        border-color: rgba(124,58,237,0.3);
    }
    .child-block-card.sortable-ghost,
    .block-card.sortable-ghost {
        opacity: 0.4;
        border: 1px dashed rgba(124,58,237,0.4) !important;
    }
    .child-block-card.sortable-chosen,
    .block-card.sortable-chosen {
        box-shadow: 0 4px 16px rgba(0,0,0,0.3);
        z-index: 10;
    }
    .card-child-list.sortable-drag-over {
        background: rgba(124,58,237,0.04);
        border: 1px dashed rgba(124,58,237,0.3) !important;
        border-radius: 8px;
    }

    .block-card-wrapper.sortable-ghost,
    .block-card.sortable-ghost {
        opacity: 0.4;
        border: 2px dashed rgba(124,58,237,0.4);
        background: rgba(124,58,237,0.04);
        transform: scale(0.97);
    }
    .block-card-wrapper.sortable-chosen,
    .block-card.sortable-chosen {
        box-shadow: 0 16px 48px rgba(0,0,0,0.4), 0 0 30px rgba(124,58,237,0.12);
        border-color: rgba(124,58,237,0.3);
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
    .block-action-btn.edit-btn:hover { color: #8b5cf6; background: rgba(124,58,237,0.1); }
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

    .gallery-modal {
        position: fixed; inset: 0; z-index: 60;
        display: flex; align-items: center; justify-content: center;
        background: rgba(0,0,0,0.6);
        backdrop-filter: blur(8px);
    }
    .gallery-inner {
        width: 90vw; max-width: 900px;
        max-height: 85vh;
        border-radius: 1.5rem;
        background: var(--bg-sidebar);
        backdrop-filter: blur(40px) saturate(1.4);
        border: 1px solid var(--border-subtle);
        box-shadow: 0 24px 80px rgba(0,0,0,0.5);
        display: flex; flex-direction: column;
        overflow: hidden;
    }
    .gallery-tabs {
        display: flex;
        gap: 2px;
        overflow-x: auto;
        padding: 0 20px;
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
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        box-shadow: 0 2px 12px rgba(124,58,237,0.3);
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
        border-color: rgba(124,58,237,0.3);
        background: rgba(124,58,237,0.04);
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    }

    .empty-state-icon {
        width: 80px; height: 80px;
        border-radius: 24px;
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 20px;
        background: linear-gradient(135deg, rgba(124,58,237,0.1), rgba(139,92,246,0.05));
        border: 1px solid rgba(124,58,237,0.12);
        position: relative;
    }
    .empty-state-icon::after {
        content: '';
        position: absolute;
        inset: -8px;
        border-radius: 28px;
        border: 1px dashed rgba(124,58,237,0.15);
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
    'basic' => '#8b5cf6', 'media' => '#ec4899', 'social' => '#3b82f6',
    'music' => '#10b981', 'video_platforms' => '#ef4444', 'contact' => '#f59e0b',
    'interactive' => '#06b6d4', 'business' => '#f97316', 'utility' => '#6366f1',
    'layout' => '#8b5cf6', 'integrations' => '#14b8a6', 'files' => '#64748b',
    'maps' => '#22c55e', 'identity' => '#8b5cf6',
];
@endphp
<div x-data="biolinkEditor()" class="max-w-7xl mx-auto">
    @include('user.links.partials.editor-header', ['link' => $link, 'activeMainTab' => 'blocks', 'hideEditorTabs' => true])

    <div class="flex items-center justify-between mb-4 gap-3 flex-wrap">
        <div class="flex items-center gap-3 flex-wrap">
            <div class="editor-tabs inline-flex items-center gap-1 p-1 rounded-full">
                <a href="{{ route('user.links.blocks.editor', $link) }}" class="editor-tab no-underline is-active">
                    <i class="fas fa-th-large text-[10px]"></i><span>Blocks</span>
                </a>
                <a href="{{ route('user.links.settings.appearance', $link) }}" class="editor-tab no-underline">
                    <i class="fas fa-cog text-[10px]"></i><span>Settings</span>
                </a>
            </div>
            <button @click="_insertAfterId = null; _cardGalleryParentId = null; showGallery = true" class="add-block-btn">
                <span class="add-block-icon"><i class="fas fa-plus text-[11px]"></i></span>
                <span>Add block</span>
            </button>
        </div>
        @if($blocks->count())
        <span class="block-count-chip">
            <i class="fas fa-layer-group text-[10px] opacity-70"></i>
            <span><strong>{{ $blocks->count() }}</strong> blocks</span>
        </span>
        @endif
    </div>
    <style>
        .add-block-btn {
            display: inline-flex; align-items: center; gap: 10px;
            padding: 9px 18px 9px 10px;
            font-size: 13px; font-weight: 600; color: #fff;
            border-radius: 9999px; border: 1px solid rgba(167,139,250,0.45);
            background: linear-gradient(135deg, #a78bfa 0%, #7c3aed 55%, #67e8f9 130%);
            box-shadow: 0 10px 30px -10px rgba(124,58,237,0.55), 0 4px 14px -4px rgba(103,232,249,0.35), inset 0 1px 0 rgba(255,255,255,0.25);
            transition: transform .18s ease, box-shadow .25s ease, filter .25s ease;
        }
        .add-block-btn:hover {
            transform: translateY(-1px);
            filter: brightness(1.08);
            box-shadow: 0 14px 36px -10px rgba(124,58,237,0.7), 0 6px 18px -4px rgba(103,232,249,0.45), inset 0 1px 0 rgba(255,255,255,0.3);
        }
        .add-block-icon {
            width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center;
            background: rgba(255,255,255,0.18); border-radius: 9999px;
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.25);
        }
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
        html.light-mode .add-block-btn {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            border-color: rgba(124,58,237,0.35);
            box-shadow: 0 6px 18px -6px rgba(124,58,237,0.45);
        }
        /* Sticky behavior now lives inside the device-preview partial so
           it works on every page that includes it (editor, appearance, etc.). */
    </style>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        <div class="lg:col-span-7 xl:col-span-7">

            {{-- BLOCKS --}}
                @if($blocks->count())
                <div class="flex items-center mb-3">
                    <p class="text-xs" style="color: var(--text-faint);"><i class="fas fa-grip-vertical mr-1"></i> Drag to reorder blocks</p>
                </div>
                @endif

                <div id="blockList" class="grid gap-2" style="grid-template-columns: repeat(12, 1fr); padding-right: 16px;">
                    @forelse($blocks as $block)
                    @php
                        $s = $block->settings ?? [];
                        $typeInfo = $blockTypes[$block->type] ?? ['label' => ucfirst($block->type), 'icon' => 'fa-cube'];
                        $catColor = $catColors[$typeInfo['category'] ?? 'basic'] ?? '#8b5cf6';
                    @endphp
                    @php $curSpan = intval($s['_style']['grid_span'] ?? 12) ?: 12; @endphp
                    <div class="block-card-wrapper" data-block-id="{{ $block->id }}" style="grid-column: span {{ $curSpan }}">
                    <button type="button" class="insert-block-btn" onclick="openInsertGallery({{ $block->id }})" title="Insert block after this">
                        <i class="fas fa-plus"></i>
                    </button>
                    <div class="block-card {{ $block->type === 'card' ? 'card-container-block' : '' }}" data-block-id="{{ $block->id }}" data-grid-span="{{ $curSpan }}">
                        <div class="flex items-center gap-2 p-3">
                            <div class="drag-handle handle">
                                <div class="flex gap-[3px]"><span class="dot"></span><span class="dot"></span></div>
                                <div class="flex gap-[3px]"><span class="dot"></span><span class="dot"></span></div>
                                <div class="flex gap-[3px]"><span class="dot"></span><span class="dot"></span></div>
                            </div>

                            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0" style="background: {{ $catColor }}12; border: 1px solid {{ $catColor }}25;">
                                <i class="fas {{ $typeInfo['icon'] }} text-sm" style="color: {{ $catColor }};"></i>
                            </div>

                            <div class="flex-1 min-w-0 ml-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-semibold" style="color: var(--text-primary);">{{ $typeInfo['label'] }}</span>
                                    @if(!$block->is_active)
                                    <span class="text-[9px] px-2 py-0.5 rounded-full font-bold" style="background: linear-gradient(135deg, rgba(239,68,68,0.3), rgba(220,38,38,0.25)); color: #ffffff; border: 1px solid rgba(239,68,68,0.5); text-shadow: 0 1px 2px rgba(0,0,0,0.3);">HIDDEN</span>
                                    @endif
                                    <span class="grid-span-badge text-[10px] px-2 py-0.5 rounded-md font-bold" style="background: linear-gradient(135deg, rgba(124,58,237,0.25), rgba(139,92,246,0.2)); color: #ffffff; border: 1px solid rgba(124,58,237,0.45); text-shadow: 0 1px 2px rgba(0,0,0,0.3); box-shadow: 0 2px 6px rgba(124,58,237,0.25); {{ $curSpan >= 12 ? 'display:none;' : '' }}" data-span-badge="{{ $block->id }}">{{ $curSpan }}/12</span>
                                </div>
                                <div class="block-preview-content mt-0.5">
                                    @if($block->type === 'card')
                                        <i class="fas fa-layer-group text-[9px] mr-1" style="color: var(--text-faint);"></i>{{ $block->children->count() }} block(s) inside{{ !empty($s['title']) ? ' — ' . $s['title'] : '' }}
                                    @elseif(in_array($block->type, ['link', 'link_big']))
                                        <i class="fas fa-globe text-[9px] mr-1" style="color: var(--text-faint);"></i>{{ $s['text'] ?? $s['url'] ?? 'No URL set' }}
                                    @elseif(in_array($block->type, ['heading', 'heading_gradient', 'heading_logo', 'heading_morph']))
                                        <i class="fas fa-font text-[9px] mr-1" style="color: var(--text-faint);"></i>{{ $s['text'] ?? 'No text' }}
                                    @elseif($block->type === 'paragraph' || $block->type === 'paragraph_rich')
                                        {{ \Illuminate\Support\Str::limit($s['text'] ?? $s['html'] ?? 'No content', 60) }}
                                    @elseif($block->type === 'socials' || $block->type === 'socials_multi' || $block->type === 'socials_custom')
                                        <i class="fas fa-users text-[9px] mr-1" style="color: var(--text-faint);"></i>{{ count($s['platforms'] ?? []) }} platforms connected
                                    @elseif(in_array($block->type, ['faq', 'faq_v2']))
                                        <i class="fas fa-list text-[9px] mr-1" style="color: var(--text-faint);"></i>{{ count($s['items'] ?? []) }} questions
                                    @elseif($block->type === 'image')
                                        <i class="fas fa-image text-[9px] mr-1" style="color: var(--text-faint);"></i>{{ $s['alt'] ?? ($s['url'] ? 'Image' : 'No image set') }}
                                    @elseif(in_array($block->type, ['video', 'header_video', 'youtube']))
                                        <i class="fas fa-play text-[9px] mr-1" style="color: var(--text-faint);"></i>{{ $s['url'] ?? $s['video_id'] ?? 'No video' }}
                                    @elseif($block->type === 'cta_button')
                                        <i class="fas fa-hand-pointer text-[9px] mr-1" style="color: var(--text-faint);"></i>{{ $s['text'] ?? 'Button' }}
                                    @elseif($block->type === 'spacer')
                                        <i class="fas fa-arrows-alt-v text-[9px] mr-1" style="color: var(--text-faint);"></i>{{ $s['height'] ?? 20 }}px
                                    @elseif($block->type === 'divider')
                                        <i class="fas fa-minus text-[9px] mr-1" style="color: var(--text-faint);"></i>{{ ucfirst($s['style'] ?? 'solid') }} line
                                    @else
                                        {{ ucfirst(str_replace('_', ' ', $block->type)) }} block
                                    @endif
                                </div>
                            </div>

                            <div class="flex items-center gap-1 flex-shrink-0">
                                <button class="block-action-btn edit-btn" title="Edit" onclick="openEditDrawer({{ $block->id }})">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button class="block-action-btn toggle-btn" title="{{ $block->is_active ? 'Hide' : 'Show' }}" onclick="ajaxToggleBlock(this, '{{ route('user.links.blocks.toggle', [$link, $block]) }}', {{ $block->id }})">
                                    <i class="fas {{ $block->is_active ? 'fa-eye' : 'fa-eye-slash' }}"></i>
                                </button>
                                <button class="block-action-btn delete-btn" title="Delete" onclick="ajaxDeleteBlock(this, '{{ route('user.links.blocks.destroy', [$link, $block]) }}', {{ $block->id }})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>

                        @if($block->type === 'card')
                        <div class="card-children-area px-3 pb-3" x-data="{ cardExpanded: true }">
                            <div class="rounded-xl overflow-hidden" style="border: 1px dashed var(--border-glass); background: rgba(124,58,237,0.02);">
                                <button type="button" @click="cardExpanded = !cardExpanded" class="w-full flex items-center justify-between px-3 py-1.5 text-[10px] font-semibold transition-colors hover:bg-white/[0.02]" style="color: var(--text-faint); background: rgba(124,58,237,0.04);">
                                    <span><i class="fas fa-cubes mr-1"></i> Child Blocks ({{ $block->children->count() }})</span>
                                    <i class="fas fa-chevron-down transition-transform text-[8px]" :class="cardExpanded ? 'rotate-180' : ''"></i>
                                </button>
                                <div x-show="cardExpanded" x-collapse>
                                    <div class="card-child-list p-2 space-y-1" data-card-id="{{ $block->id }}" style="min-height: 32px;">
                                        @forelse($block->children as $child)
                                        @php
                                            $cs = $child->settings ?? [];
                                            $cTypeInfo = $blockTypes[$child->type] ?? ['label' => ucfirst($child->type), 'icon' => 'fa-cube'];
                                            $cCatColor = $catColors[$cTypeInfo['category'] ?? 'basic'] ?? '#8b5cf6';
                                            $childSpan = intval($cs['_style']['grid_span'] ?? 12) ?: 12;
                                        @endphp
                                        <div class="child-block-card rounded-lg transition-all hover:bg-white/[0.03]" data-block-id="{{ $child->id }}" style="border: 1px solid var(--border-glass);">
                                            <div class="flex items-center gap-2 p-2">
                                                <div class="child-handle cursor-grab" style="color: var(--text-faint);">
                                                    <i class="fas fa-grip-vertical text-[9px]"></i>
                                                </div>
                                                <div class="w-6 h-6 rounded-md flex items-center justify-center flex-shrink-0" style="background: {{ $cCatColor }}10;">
                                                    <i class="fas {{ $cTypeInfo['icon'] }} text-[9px]" style="color: {{ $cCatColor }};"></i>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <span class="text-[11px] font-semibold" style="color: var(--text-primary);">{{ $cTypeInfo['label'] }}</span>
                                                    @if(!$child->is_active)<span class="text-[9px] px-1.5 py-0.5 rounded-full font-bold ml-1" style="background: linear-gradient(135deg, rgba(239,68,68,0.3), rgba(220,38,38,0.25)); color: #ffffff; border: 1px solid rgba(239,68,68,0.5); text-shadow: 0 1px 2px rgba(0,0,0,0.3);">HIDDEN</span>@endif
                                                    <span class="text-[9px] px-1.5 py-0.5 rounded-md font-bold ml-1" style="background: linear-gradient(135deg, rgba(124,58,237,0.25), rgba(139,92,246,0.2)); color: #ffffff; border: 1px solid rgba(124,58,237,0.45); text-shadow: 0 1px 2px rgba(0,0,0,0.3); box-shadow: 0 2px 6px rgba(124,58,237,0.25); {{ $childSpan >= 12 ? 'display:none;' : '' }}" data-child-span-badge="{{ $child->id }}">{{ $childSpan }}/12</span>
                                                </div>
                                                <div class="flex items-center gap-0.5 flex-shrink-0">
                                                    <button class="block-action-btn edit-btn" style="width:22px;height:22px;" title="Edit" onclick="openEditDrawer({{ $child->id }})"><i class="fas fa-pen" style="font-size:8px;"></i></button>
                                                    <button class="block-action-btn toggle-btn" style="width:22px;height:22px;" title="{{ $child->is_active ? 'Hide' : 'Show' }}" onclick="ajaxToggleBlock(this, '{{ route('user.links.blocks.toggle', [$link, $child]) }}', {{ $child->id }})"><i class="fas {{ $child->is_active ? 'fa-eye' : 'fa-eye-slash' }}" style="font-size:8px;"></i></button>
                                                    <button class="block-action-btn delete-btn" style="width:22px;height:22px;" title="Delete" onclick="ajaxDeleteBlock(this, '{{ route('user.links.blocks.destroy', [$link, $child]) }}', {{ $child->id }})"><i class="fas fa-trash" style="font-size:8px;"></i></button>
                                                </div>
                                            </div>
                                            <div class="child-span-row px-2 pb-1.5">
                                                <div class="flex items-center gap-1">
                                                    <span class="text-[8px] font-semibold flex-shrink-0" style="color: var(--text-faint);"><i class="fas fa-columns mr-0.5"></i>Width</span>
                                                    <div class="flex gap-[2px] flex-1">
                                                        @foreach([3 => '¼', 4 => '⅓', 6 => '½', 8 => '⅔', 9 => '¾', 12 => 'Full'] as $spanVal => $spanLabel)
                                                        <button type="button" class="child-span-btn text-[8px] font-bold px-1 py-0.5 rounded transition-all {{ $childSpan == $spanVal ? 'active' : '' }}"
                                                                onclick="setChildGridSpan({{ $child->id }}, {{ $spanVal }}, this)"
                                                                title="{{ $spanLabel }} width">{{ $spanLabel }}</button>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        @empty
                                        <div class="card-empty-hint text-center py-3">
                                            <p class="text-[10px]" style="color: var(--text-dimmed);">Drag blocks here or click + below</p>
                                        </div>
                                        @endforelse
                                    </div>
                                    <div class="px-2 pb-2">
                                        <button type="button" class="w-full py-1.5 rounded-lg text-[10px] font-semibold flex items-center justify-center gap-1 transition-all hover:bg-violet-500/10" style="border: 1px dashed rgba(124,58,237,0.3); color: #a78bfa;" onclick="openCardGallery({{ $block->id }})">
                                            <i class="fas fa-plus text-[8px]"></i> Add block to card
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        <div class="grid-span-row px-3 pb-2" data-span-row="{{ $block->id }}">
                            <div class="flex items-center gap-1.5">
                                <span class="text-[9px] font-semibold flex-shrink-0" style="color: var(--text-faint);"><i class="fas fa-columns mr-1"></i>Width</span>
                                <div class="flex gap-[3px] flex-1">
                                    @foreach([3 => '¼', 4 => '⅓', 6 => '½', 8 => '⅔', 9 => '¾', 12 => 'Full'] as $spanVal => $spanLabel)
                                    <button type="button" class="span-btn text-[9px] font-bold px-1.5 py-0.5 rounded transition-all {{ $curSpan == $spanVal ? 'active' : '' }}"
                                            onclick="setGridSpan({{ $block->id }}, {{ $spanVal }}, this)"
                                            title="{{ $spanLabel }} width ({{ $spanVal }}/12 columns)">{{ $spanLabel }}</button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                    </div>
                    @empty
                    <div class="py-16 text-center">
                        <div class="empty-state-icon">
                            <i class="fas fa-layer-group text-3xl text-violet-400"></i>
                        </div>
                        <h3 class="text-lg font-bold mb-2" style="color: var(--text-primary);">No blocks yet</h3>
                        <p class="text-sm mb-6 max-w-xs mx-auto" style="color: var(--text-muted);">Start from a curated template, or add blocks one at a time.</p>
                        <div class="flex items-center justify-center gap-2 flex-wrap">
                            <a href="{{ route('user.links.templates.picker', $link) }}" class="btn-primary text-sm py-2.5 px-6" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);">
                                <i class="fas fa-layer-group text-xs"></i> Browse templates
                            </a>
                            <button @click="showGallery = true" class="btn-primary text-sm py-2.5 px-6" style="background: linear-gradient(135deg, #10b981, #059669);">
                                <i class="fas fa-plus text-xs"></i> Add a block
                            </button>
                        </div>
                    </div>
                    @endforelse
                </div>
        </div>

        {{-- DEVICE PREVIEW --}}
        <div class="lg:col-span-5 xl:col-span-5 hidden lg:block lg:self-stretch lg:h-full">
            <div class="device-preview-sticky">
                @include('user.links.partials.device-preview', ['link' => $link])
            </div>
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
                    <button type="button" onclick="manualSaveFromModal()" class="text-[10px] font-medium px-3 py-1.5 rounded-lg transition-all" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white;">
                        <i class="fas fa-save mr-1"></i>Save & Close
                    </button>
                    <button @click="closeEditDrawer()" class="block-action-btn" style="color: var(--text-faint);" title="Close"><i class="fas fa-times"></i></button>
                </div>
            </div>
            <div class="edit-modal-body">
                <div class="edit-modal-preview" id="editModalPreview">
                    <div class="relative" style="width: 300px;">
                        <div class="absolute -inset-1 rounded-[2.8rem]" style="background: linear-gradient(180deg, rgba(124,58,237,0.12), rgba(255,255,255,0.03), rgba(124,58,237,0.08)); filter: blur(1px);"></div>
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

    {{-- BLOCK GALLERY --}}
    <template x-if="showGallery">
        <div class="gallery-modal" x-transition.opacity @click.self="showGallery = false">
            <div class="gallery-inner" @click.stop>
                <div class="p-5 pb-0 flex-shrink-0">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="text-lg font-bold gradient-text">Block Gallery</h2>
                            <p class="text-[10px] mt-0.5" style="color: var(--text-faint);" x-show="_cardGalleryParentId" x-cloak><i class="fas fa-layer-group mr-1"></i>Adding to card container</p>
                            <p class="text-[10px] mt-0.5" style="color: var(--text-faint);" x-show="_insertAfterId" x-cloak><i class="fas fa-arrow-down mr-1"></i>Inserting after selected block</p>
                        </div>
                        <button @click="showGallery = false" class="block-action-btn" style="color: var(--text-faint);"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="flex items-center gap-1 mb-3 p-1 rounded-xl bg-white/5 border border-white/5 w-max">
                        <button @click="galleryMode = 'blocks'" :class="galleryMode === 'blocks' ? 'bg-violet-600 text-white' : 'text-white/50 hover:text-white'" class="px-3 py-1 text-[11px] font-semibold rounded-lg transition">Blocks</button>
                        <button @click="galleryMode = 'templates'; loadCardTemplates();" :class="galleryMode === 'templates' ? 'bg-violet-600 text-white' : 'text-white/50 hover:text-white'" class="px-3 py-1 text-[11px] font-semibold rounded-lg transition">Card Templates</button>
                    </div>
                    <div class="relative mb-4">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs" style="color: var(--text-faint);"></i>
                        <input type="text" x-model="gallerySearch" placeholder="Search…" class="theme-input w-full pl-9">
                    </div>
                    <div class="gallery-tabs pb-3" x-show="galleryMode === 'blocks'">
                        <button class="gallery-tab" :class="galleryCategory === 'all' ? 'active' : ''" @click="galleryCategory = 'all'">All</button>
                        @foreach($blockCategories as $catKey => $catLabel)
                        <button class="gallery-tab" :class="galleryCategory === '{{ $catKey }}' ? 'active' : ''" @click="galleryCategory = '{{ $catKey }}'">{{ $catLabel }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto p-5 pt-2">
                    {{-- BLOCKS GRID --}}
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2" x-show="galleryMode === 'blocks'">
                        @foreach($blockTypes as $typeKey => $typeInfo)
                        @php $catColor = $catColors[$typeInfo['category']] ?? '#8b5cf6'; @endphp
                        <div x-show="(galleryCategory === 'all' || galleryCategory === '{{ $typeInfo['category'] }}') && (gallerySearch === '' || '{{ strtolower($typeInfo['label']) }}'.includes(gallerySearch.toLowerCase())) && !(_cardGalleryParentId && '{{ $typeKey }}' === 'card') && '{{ $typeInfo['category'] }}' !== 'verified'"
                             x-cloak>
                            <button type="button" class="gallery-block-card" onclick="ajaxAddBlock('{{ $typeKey }}', '{{ route('user.links.blocks.store', $link) }}', _cardGalleryParentId)">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" style="background: {{ $catColor }}15; border: 1px solid {{ $catColor }}25;">
                                        <i class="fas {{ $typeInfo['icon'] }} text-sm" style="color: {{ $catColor }};"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-xs font-semibold truncate" style="color: var(--text-primary);">{{ $typeInfo['label'] }}</div>
                                        <div class="text-[10px] truncate" style="color: var(--text-faint);">{{ ucfirst(str_replace('_', ' ', $typeInfo['category'])) }}</div>
                                    </div>
                                </div>
                            </button>
                        </div>
                        @endforeach
                    </div>

                    {{-- CARD TEMPLATES --}}
                    <div x-show="galleryMode === 'templates'" x-cloak>
                        <div x-show="cardTemplatesLoading" class="text-center py-10" style="color: var(--text-faint);">
                            <i class="fas fa-spinner fa-spin text-xl"></i>
                        </div>
                        <div x-show="!cardTemplatesLoading && cardTemplates.length === 0" class="text-center py-10">
                            <i class="fas fa-layer-group text-2xl mb-2" style="color: var(--text-faint);"></i>
                            <p class="text-sm" style="color: var(--text-muted);">No card templates available yet.</p>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" x-show="!cardTemplatesLoading">
                            <template x-for="t in cardTemplates" :key="t.id">
                                <div x-show="gallerySearch === '' || (t.name + ' ' + (t.description||'')).toLowerCase().includes(gallerySearch.toLowerCase())"
                                     class="rounded-xl border overflow-hidden transition cursor-pointer" style="border-color: var(--border-glass); background: rgba(124,58,237,0.02);"
                                     @click="t.locked ? (window.location.href = '{{ route('user.upgrade') }}') : applyCardTemplate(t.id)"
                                     :class="t.locked ? 'opacity-70 hover:border-amber-500/50' : 'hover:border-violet-500/50'"
                                     :title="t.locked ? 'Upgrade to ' + t.plan_tier + ' to use this template' : t.name">
                                    <div class="aspect-[4/2] flex items-center justify-center relative" style="background: linear-gradient(135deg, rgba(124,58,237,0.12), rgba(139,92,246,0.04));">
                                        <template x-if="t.thumbnail_url">
                                            <img :src="t.thumbnail_url" :alt="t.name" class="w-full h-full object-cover">
                                        </template>
                                        <template x-if="!t.thumbnail_url">
                                            <i class="fas fa-square-poll-vertical text-2xl text-violet-300/60"></i>
                                        </template>
                                        <div x-show="t.locked" class="absolute top-2 right-2 px-2 py-0.5 rounded-full text-[9px] font-bold bg-amber-500/90 text-white"><i class="fas fa-lock mr-1"></i><span x-text="t.plan_tier"></span></div>
                                    </div>
                                    <div class="p-3">
                                        <div class="text-xs font-semibold mb-0.5" style="color: var(--text-primary);" x-text="t.name"></div>
                                        <div class="text-[10px]" style="color: var(--text-faint);"><span x-text="t.children_count"></span> blocks · <span x-text="t.category"></span></div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <div class="reorder-toast" id="reorderToast"><i class="fas fa-check-circle mr-2"></i>Order saved</div>
</div>

{{-- Hidden edit forms for each block (including children) --}}
@php
    $allEditBlocks = collect();
    foreach($blocks as $block) {
        $allEditBlocks->push($block);
        if ($block->type === 'card' && $block->children) {
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
                <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(124,58,237,0.1); border: 1px solid rgba(124,58,237,0.15);">
                    <i class="fas {{ $blockTypes[$block->type]['icon'] ?? 'fa-cube' }} text-violet-400 text-sm"></i>
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
        showGallery: false,
        gallerySearch: '',
        galleryCategory: 'all',
        galleryMode: 'blocks',
        cardTemplates: [],
        cardTemplatesLoading: false,
        cardTemplatesLoaded: false,
        editingBlockId: null,
        loadCardTemplates() {
            if (this.cardTemplatesLoaded || this.cardTemplatesLoading) return;
            this.cardTemplatesLoading = true;
            fetch('{{ route('user.links.templates.cards', $link) }}', { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(d => { this.cardTemplates = d.items || []; this.cardTemplatesLoaded = true; })
                .catch(() => { showToast('Failed to load templates', 'error'); })
                .finally(() => { this.cardTemplatesLoading = false; });
        },
        applyCardTemplate(id) {
            var fd = new FormData();
            fd.append('_token', _csrfToken());
            fd.append('template_id', id);
            if (_insertAfterId) fd.append('insert_after', _insertAfterId);
            fetch('{{ route('user.links.templates.apply-card', $link) }}', {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: fd
            }).then(r => r.json()).then(d => {
                if (d.success) {
                    showToast('Card template added', 'success');
                    setTimeout(function() { location.reload(); }, 400);
                } else {
                    showToast(d.error || 'Failed to apply template', 'error');
                }
            }).catch(() => showToast('Failed to apply template', 'error'));
        },
        init() {
            var self = this;
            window.addEventListener('open-edit-drawer', function(e) {
                self.editingBlockId = e.detail.id;
            });
            window.addEventListener('close-edit-drawer', function() {
                self.editingBlockId = null;
                var c = document.getElementById('editDrawerContent');
                Alpine.destroyTree(c);
                c.innerHTML = '';
                _hideEditPreview();
            });
            window.addEventListener('open-card-gallery', function() {
                self.showGallery = true;
            });
            self.$watch('showGallery', function(val) {
                if (!val) { _cardGalleryParentId = null; _insertAfterId = null; }
            });
        },
        closeEditDrawer() {
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

var _csrfToken = function() { return document.querySelector('meta[name="csrf-token"]').content; };

function showToast(msg, type) {
    var colors = { success: 'linear-gradient(135deg, #10b981, #059669)', error: 'linear-gradient(135deg, #ef4444, #dc2626)', info: 'linear-gradient(135deg, #8b5cf6, #7c3aed)' };
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
    if (!confirm('Delete this block?')) return;
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

var _cardGalleryParentId = null;
var _insertAfterId = null;

function ajaxAddBlock(type, url, parentId) {
    var fd = new FormData();
    fd.append('type', type);
    fd.append('_token', _csrfToken());
    if (parentId) fd.append('parent_id', parentId);
    if (_insertAfterId) fd.append('insert_after', _insertAfterId);
    fetch(url, {
        method: 'POST',
        headers: { 'Accept': 'application/json' },
        body: fd
    }).then(function(r) { return r.json(); }).then(function(data) {
        if (data.success) {
            showToast('Block added', 'success');
            setTimeout(function() { location.reload(); }, 400);
        } else {
            showToast(data.error || 'Failed to add block', 'error');
        }
    }).catch(function() { showToast('Failed to add block', 'error'); });
}

function openCardGallery(cardId) {
    _cardGalleryParentId = cardId;
    _insertAfterId = null;
    var el = document.querySelector('[x-data="biolinkEditor()"]');
    if (el && el.__x) {
        el.__x.$data.showGallery = true;
    } else {
        window.dispatchEvent(new CustomEvent('open-card-gallery'));
    }
}

function openInsertGallery(afterBlockId) {
    _insertAfterId = afterBlockId;
    _cardGalleryParentId = null;
    var el = document.querySelector('[x-data="biolinkEditor()"]');
    if (el && el.__x) {
        el.__x.$data.showGallery = true;
    } else {
        window.dispatchEvent(new CustomEvent('open-card-gallery'));
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
        new Sortable(el, {
            handle: '.handle, .child-handle',
            animation: 250,
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
                var blockId = parseInt(evt.item.dataset.blockId);
                doMoveBlock(blockId, null).then(function(data) {
                    if (data.success) {
                        showToast('Block moved out of card', 'success');
                        reorderList(el, ':scope > .block-card-wrapper, :scope > .child-block-card').then(function() {
                            location.reload();
                        });
                    } else {
                        showToast(data.error || 'Move failed', 'error');
                        location.reload();
                    }
                });
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
        new Sortable(childList, {
            handle: '.child-handle, .handle',
            animation: 200,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            easing: 'cubic-bezier(0.4, 0, 0.2, 1)',
            group: { name: 'blocks', pull: true, put: true },
            draggable: '.child-block-card, .block-card, .block-card-wrapper',
            onAdd: function(evt) {
                var blockId = parseInt(evt.item.dataset.blockId);
                var hasCard = evt.item.querySelector && evt.item.querySelector('.card-container-block');
                if (evt.item.classList.contains('card-container-block') || hasCard) {
                    showToast('Cannot put a card inside another card', 'error');
                    location.reload();
                    return;
                }
                doMoveBlock(blockId, cardId).then(function(data) {
                    if (data.success) {
                        showToast('Block moved into card', 'success');
                        reorderList(childList, '.child-block-card, .block-card, .block-card-wrapper').then(function() {
                            location.reload();
                        });
                    } else {
                        showToast(data.error || 'Move failed', 'error');
                        location.reload();
                    }
                });
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
});
</script>
@endsection
