@extends('user.layouts.app')
@section('title', 'Layout - ' . ($link->title ?: $link->alias))
@section('breadcrumb_parent', 'Links')
@section('breadcrumb_parent_url', route('user.links.index'))

@section('content')
@php
    $bs = $link->settings['biolink'] ?? [];
    $layout = $bs['layout'] ?? [];
    $activeSettingsTab = 'layout';
@endphp

<div class="max-w-4xl mx-auto">
    @include('user.links.partials.settings-header', ['link' => $link, 'activeSettingsTab' => $activeSettingsTab])

    <form method="POST" action="{{ route('user.links.page-settings', $link) }}" enctype="multipart/form-data">
        @csrf

        <div class="card-premium p-6">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(34,211,238,0.1);"><i class="fas fa-ruler-combined text-cyan-400 text-xs"></i></div>
                <h3 class="text-sm font-bold" style="color: var(--text-primary);">Page Layout</h3>
            </div>
            <div class="space-y-6">
                <div>
                    <p class="text-xs font-semibold mb-3" style="color: var(--text-muted);">Content Max Width (px)</p>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="flex items-center gap-2 text-[11px] font-medium mb-1" style="color: var(--text-faint);"><i class="fas fa-mobile-alt text-[9px] text-purple-400"></i> Phone</label>
                            <input type="number" name="layout[max_width_phone]" value="{{ $layout['max_width_phone'] ?? '' }}" placeholder="448" min="280" max="600" class="theme-input w-full">
                        </div>
                        <div>
                            <label class="flex items-center gap-2 text-[11px] font-medium mb-1" style="color: var(--text-faint);"><i class="fas fa-tablet-alt text-[9px] text-pink-400"></i> Tablet</label>
                            <input type="number" name="layout[max_width_tablet]" value="{{ $layout['max_width_tablet'] ?? '' }}" placeholder="540" min="320" max="900" class="theme-input w-full">
                        </div>
                        <div>
                            <label class="flex items-center gap-2 text-[11px] font-medium mb-1" style="color: var(--text-faint);"><i class="fas fa-desktop text-[9px] text-cyan-400"></i> Desktop</label>
                            <input type="number" name="layout[max_width_desktop]" value="{{ $layout['max_width_desktop'] ?? '' }}" placeholder="680" min="400" max="1200" class="theme-input w-full">
                        </div>
                    </div>
                </div>

                <div style="border-top: 1px solid var(--border-subtle);" class="pt-5">
                    <p class="text-xs font-semibold mb-3" style="color: var(--text-muted);">Page Padding (px)</p>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-[11px] mb-1" style="color: var(--text-faint);">Top</label>
                            <input type="number" name="layout[page_padding_top]" value="{{ $layout['page_padding_top'] ?? '' }}" placeholder="32" min="0" max="200" class="theme-input w-full">
                        </div>
                        <div>
                            <label class="block text-[11px] mb-1" style="color: var(--text-faint);">Bottom</label>
                            <input type="number" name="layout[page_padding_bottom]" value="{{ $layout['page_padding_bottom'] ?? '' }}" placeholder="64" min="0" max="200" class="theme-input w-full">
                        </div>
                        <div>
                            <label class="block text-[11px] mb-1" style="color: var(--text-faint);">Sides</label>
                            <input type="number" name="layout[page_padding_x]" value="{{ $layout['page_padding_x'] ?? '' }}" placeholder="16" min="0" max="100" class="theme-input w-full">
                        </div>
                    </div>
                </div>

                <div style="border-top: 1px solid var(--border-subtle);" class="pt-5">
                    <p class="text-xs font-semibold mb-3" style="color: var(--text-muted);">Block Spacing (px)</p>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] mb-1" style="color: var(--text-faint);">Gap between blocks</label>
                            <input type="number" name="layout[block_gap]" value="{{ $layout['block_gap'] ?? '' }}" placeholder="12" min="0" max="100" class="theme-input w-full">
                        </div>
                        <div>
                            <label class="block text-[11px] mb-1" style="color: var(--text-faint);">Block inner padding</label>
                            <input type="number" name="layout[block_padding]" value="{{ $layout['block_padding'] ?? '' }}" placeholder="Auto" min="0" max="60" class="theme-input w-full">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @include('user.links.partials.settings-footer', ['link' => $link])
    </form>
</div>
@endsection
