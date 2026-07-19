@extends('admin.layouts.app')
@section('title', 'Blueprint diff')
@section('page-title', 'Blueprint diff, ' . $tpl->name)

@php
    $currentJson = json_encode($current['snapshot'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $latestJson  = json_encode($latest['snapshot'],  JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $blockTypes = function (array $snap): array {
        $out = [];
        foreach (($snap['blocks'] ?? []) as $b) {
            $out[] = $b['type'] ?? '?';
        }
        return $out;
    };
    $currentBlocks = $blockTypes($current['snapshot']);
    $latestBlocks  = $blockTypes($latest['snapshot']);
@endphp

@section('content')
<div class="max-w-6xl">
    <a href="{{ route('admin.templates.index', ['tab' => 'page']) }}" class="text-xs text-white/40 hover:text-white mb-4 inline-block">
        <i class="fas fa-arrow-left mr-1"></i>Back to templates
    </a>

    <div class="glass rounded-2xl border border-amber-500/30 bg-amber-500/5 p-4 mb-5">
        <div class="flex items-start gap-3">
            <i class="fas fa-triangle-exclamation text-amber-400 text-lg mt-0.5"></i>
            <div class="flex-1">
                <div class="text-sm font-semibold text-white mb-1">
                    Stored blueprint v{{ $storedVersion }} · current design v{{ $currentVersion }}
                </div>
                <p class="text-xs text-white/60">
                    This persona template was originally generated from an older blueprint.
                    Untouched seed rows auto-refresh on the next deploy, but this row was
                    customized in the admin panel so it stayed pinned to v{{ $storedVersion }}.
                    Compare the two below and either keep your edits or reset to the new design.
                </p>
            </div>
            <form action="{{ route('admin.templates.blueprint.reset', ['id' => $tpl->id]) }}"
                  method="POST"
                  class="shrink-0"
                  onsubmit="return window.themedConfirmSubmit(this, {title: 'Reset to current blueprint?', body: 'This overwrites the stored name, description, and snapshot with the v{{ $currentVersion }} design. Your customizations will be lost.', confirmText: 'Reset', confirmIcon: 'fa-rotate', iconClass: 'fa-triangle-exclamation'})">
                @csrf
                <button type="submit" class="px-3 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-semibold">
                    <i class="fas fa-rotate mr-1"></i>Reset to current design
                </button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5 text-xs">
        <div class="glass rounded-xl border border-white/10 p-4">
            <div class="text-white/40 uppercase tracking-wide text-[10px] mb-1">Slug</div>
            <div class="text-white font-mono break-all">{{ $tpl->slug }}</div>
        </div>
        <div class="glass rounded-xl border border-white/10 p-4">
            <div class="text-white/40 uppercase tracking-wide text-[10px] mb-1">Block count</div>
            <div class="text-white">
                <span class="{{ count($currentBlocks) !== count($latestBlocks) ? 'text-amber-300' : '' }}">
                    Stored: {{ count($currentBlocks) }}
                </span>
                <span class="text-white/30 mx-1">→</span>
                <span class="text-emerald-300">Current: {{ count($latestBlocks) }}</span>
            </div>
        </div>
        <div class="glass rounded-xl border border-white/10 p-4">
            <div class="text-white/40 uppercase tracking-wide text-[10px] mb-1">Persona</div>
            <div class="text-white">{{ $tpl->personaSeedSlug() }}</div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <div class="glass rounded-2xl border border-white/10 p-4">
            <div class="flex items-center gap-2 mb-2">
                <span class="text-[10px] uppercase tracking-wide text-white/40">Stored (v{{ $storedVersion }})</span>
            </div>
            <div class="space-y-2 text-xs">
                <div>
                    <div class="text-white/40 mb-0.5">Name</div>
                    <div class="text-white {{ $current['name'] !== $latest['name'] ? 'bg-amber-500/10 px-1 rounded' : '' }}">{{ $current['name'] }}</div>
                </div>
                <div>
                    <div class="text-white/40 mb-0.5">Description</div>
                    <div class="text-white/80 {{ $current['description'] !== $latest['description'] ? 'bg-amber-500/10 px-1 rounded' : '' }}">{{ $current['description'] ?: '—' }}</div>
                </div>
                <div>
                    <div class="text-white/40 mb-0.5">Block types</div>
                    <div class="text-white/70 font-mono text-[11px] break-words">{{ implode(', ', $currentBlocks) ?: '—' }}</div>
                </div>
            </div>
        </div>
        <div class="glass rounded-2xl border border-emerald-500/20 p-4">
            <div class="flex items-center gap-2 mb-2">
                <span class="text-[10px] uppercase tracking-wide text-emerald-300/70">Current blueprint (v{{ $currentVersion }})</span>
            </div>
            <div class="space-y-2 text-xs">
                <div>
                    <div class="text-white/40 mb-0.5">Name</div>
                    <div class="text-white {{ $current['name'] !== $latest['name'] ? 'bg-emerald-500/10 px-1 rounded' : '' }}">{{ $latest['name'] }}</div>
                </div>
                <div>
                    <div class="text-white/40 mb-0.5">Description</div>
                    <div class="text-white/80 {{ $current['description'] !== $latest['description'] ? 'bg-emerald-500/10 px-1 rounded' : '' }}">{{ $latest['description'] ?: '—' }}</div>
                </div>
                <div>
                    <div class="text-white/40 mb-0.5">Block types</div>
                    <div class="text-white/70 font-mono text-[11px] break-words">{{ implode(', ', $latestBlocks) ?: '—' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="glass rounded-2xl border border-white/10 p-4">
            <div class="text-[10px] uppercase tracking-wide text-white/40 mb-2">Stored snapshot JSON</div>
            <pre class="text-[11px] text-white/70 bg-black/40 rounded-lg p-3 overflow-auto max-h-[60vh] whitespace-pre-wrap break-words">{{ $currentJson }}</pre>
        </div>
        <div class="glass rounded-2xl border border-emerald-500/20 p-4">
            <div class="text-[10px] uppercase tracking-wide text-emerald-300/70 mb-2">Current blueprint JSON</div>
            <pre class="text-[11px] text-white/70 bg-black/40 rounded-lg p-3 overflow-auto max-h-[60vh] whitespace-pre-wrap break-words">{{ $latestJson }}</pre>
        </div>
    </div>

    <div class="mt-5 flex items-center justify-between">
        <a href="{{ route('admin.templates.edit', ['kind' => 'page', 'id' => $tpl->id]) }}"
           class="text-xs text-white/40 hover:text-white">
            <i class="fas fa-edit mr-1"></i>Edit this template manually instead
        </a>
        <form action="{{ route('admin.templates.blueprint.reset', ['id' => $tpl->id]) }}"
              method="POST"
              onsubmit="return window.themedConfirmSubmit(this, {title: 'Reset to current blueprint?', body: 'This overwrites the stored name, description, and snapshot with the v{{ $currentVersion }} design. Your customizations will be lost.', confirmText: 'Reset', confirmIcon: 'fa-rotate', iconClass: 'fa-triangle-exclamation'})">
            @csrf
            <button type="submit" class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-sm font-semibold">
                <i class="fas fa-rotate mr-1"></i>Reset to current design
            </button>
        </form>
    </div>
</div>
@endsection
