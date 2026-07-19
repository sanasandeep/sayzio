@extends('admin.layouts.app')
@section('title', 'Block First-Paint Defaults')
@section('page-title', 'Block First-Paint Defaults')

@section('content')
<div class="max-w-5xl" x-data="{ copySource: null, copyTargets: [] }">

    @if(session('success'))
        <div class="mb-4 p-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-200 text-sm">
            <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="mb-4 p-3 rounded-xl border border-red-500/30 bg-red-500/10 text-red-200 text-sm">
            <i class="fas fa-exclamation-circle mr-1"></i> {{ $errors->first() }}
        </div>
    @endif

    {{-- Header --}}
    <div class="glass rounded-2xl border border-white/10 p-6 mb-5">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-white/90">Block First-Paint Defaults</h2>
                <p class="text-sm text-white/50 mt-1 max-w-2xl">
                    Configure the sample text, placeholder images/media URLs, and structural styling that
                    freshly-added biolink blocks start with. Overrides only affect <strong>new</strong> blocks
                   , existing blocks are never changed.
                </p>
            </div>
            @if($customized > 0)
                <div class="flex-shrink-0 px-3 py-1.5 rounded-xl text-sm font-semibold"
                     style="background: rgba(92,131,255,0.15); border: 1px solid rgba(92,131,255,0.3); color: var(--accent-light);">
                    {{ $customized }} {{ Str::plural('type', $customized) }} customised
                </div>
            @endif
        </div>
    </div>

    {{-- Block type groups --}}
    @foreach($groups as $groupName => $types)
        <div class="glass rounded-2xl border border-white/10 p-5 mb-4">
            <h3 class="text-xs font-bold uppercase tracking-widest mb-3" style="color: var(--text-faint);">
                {{ $groupName }}
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                @foreach($types as $type)
                    @php
                        $isCustomised = array_key_exists($type, $overrides);
                        $hasContent   = !empty($overrides[$type]['content'] ?? []);
                        $hasStyle     = !empty($overrides[$type]['style'] ?? []);
                    @endphp
                    <a href="{{ route('admin.block-defaults.edit', $type) }}"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-xl border transition-all hover:bg-white/[0.04]"
                       style="border-color: {{ $isCustomised ? 'rgba(92,131,255,0.4)' : 'var(--border-glass)' }}; background: {{ $isCustomised ? 'rgba(92,131,255,0.06)' : 'var(--bg-glass)' }};">
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-semibold truncate" style="color: var(--text-primary);">
                                {{ $type }}
                            </div>
                            @if($isCustomised)
                                <div class="flex items-center gap-1.5 mt-0.5">
                                    @if($hasContent)
                                        <span class="text-[10px] px-1.5 py-0.5 rounded" style="background: rgba(34,197,94,0.12); color: #4ade80; border: 1px solid rgba(34,197,94,0.2);">content</span>
                                    @endif
                                    @if($hasStyle)
                                        <span class="text-[10px] px-1.5 py-0.5 rounded" style="background: rgba(59,130,246,0.12); color: #93c5fd; border: 1px solid rgba(59,130,246,0.2);">style</span>
                                    @endif
                                </div>
                            @else
                                <div class="text-[11px] mt-0.5" style="color: var(--text-faint);">system default</div>
                            @endif
                        </div>
                        @if($isCustomised)
                            <button type="button"
                                    @click.prevent.stop="copySource = '{{ $type }}'; copyTargets = []"
                                    class="flex-shrink-0 w-7 h-7 rounded-lg flex items-center justify-center transition-colors hover:bg-white/10"
                                    style="color: var(--accent-light); border: 1px solid rgba(92,131,255,0.3);"
                                    title="Copy overrides from &quot;{{ $type }}&quot; to other types">
                                <i class="fas fa-copy text-xs"></i>
                            </button>
                        @endif
                        <i class="fas fa-chevron-right text-xs" style="color: var(--text-faint);"></i>
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach

    {{-- Copy-overrides modal --}}
    <div x-show="copySource" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         @keydown.escape.window="copySource = null">
        <div class="absolute inset-0 bg-black/60" @click="copySource = null"></div>
        <div class="relative w-full max-w-2xl max-h-[85vh] flex flex-col glass rounded-2xl border border-white/10 p-5"
             style="background: var(--bg-card, #101830);">
            <form method="POST" x-bind:action="'{{ route('admin.block-defaults.index') }}/' + copySource + '/copy-to'"
                  class="flex flex-col min-h-0">
                @csrf
                <div class="flex items-start justify-between gap-3 mb-1">
                    <h3 class="text-base font-semibold text-white/90">
                        Copy overrides from &laquo;<span x-text="copySource"></span>&raquo;
                    </h3>
                    <button type="button" @click="copySource = null"
                            class="w-7 h-7 rounded-lg flex items-center justify-center hover:bg-white/10"
                            style="color: var(--text-faint);">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <p class="text-xs mb-3" style="color: var(--text-faint);">
                    Select the block types to copy this type's content and style overrides into.
                    Existing overrides on the selected types will be replaced.
                </p>
                <div class="overflow-y-auto min-h-0 flex-1 pr-1 space-y-3">
                    @foreach($groups as $groupName => $types)
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <div class="text-[11px] font-bold uppercase tracking-widest" style="color: var(--text-faint);">
                                    {{ $groupName }}
                                </div>
                                <button type="button" class="text-[11px] font-semibold hover:underline"
                                        style="color: var(--accent-light);"
                                        @click="(() => { const g = {{ Js::from($types) }}.filter(t => t !== copySource); const all = g.every(t => copyTargets.includes(t)); copyTargets = all ? copyTargets.filter(t => !g.includes(t)) : [...new Set([...copyTargets, ...g])]; })()">
                                    Toggle all
                                </button>
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-1.5">
                                @foreach($types as $type)
                                    <label x-show="copySource !== '{{ $type }}'"
                                           class="flex items-center gap-2 px-2.5 py-1.5 rounded-lg border cursor-pointer transition-colors hover:bg-white/[0.05]"
                                           style="border-color: var(--border-glass); background: var(--bg-glass);">
                                        <input type="checkbox" name="targets[]" value="{{ $type }}"
                                               x-model="copyTargets"
                                               class="rounded border-white/20 bg-transparent"
                                               style="accent-color: var(--accent-light);">
                                        <span class="text-xs font-medium truncate" style="color: var(--text-primary);">{{ $type }}</span>
                                        @if(array_key_exists($type, $overrides))
                                            <span class="text-[9px] px-1 py-0.5 rounded flex-shrink-0" style="background: rgba(92,131,255,0.15); color: var(--accent-light);">customised</span>
                                        @endif
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="flex items-center justify-between gap-3 pt-4 mt-1 border-t border-white/10">
                    <div class="text-xs" style="color: var(--text-faint);">
                        <span x-text="copyTargets.length"></span> <span x-text="copyTargets.length === 1 ? 'type' : 'types'"></span> selected
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="copySource = null"
                                class="px-3.5 py-2 rounded-xl text-sm font-semibold border border-white/10 hover:bg-white/5"
                                style="color: var(--text-primary);">Cancel</button>
                        <button type="submit" :disabled="copyTargets.length === 0"
                                class="px-3.5 py-2 rounded-xl text-sm font-semibold disabled:opacity-40 disabled:cursor-not-allowed"
                                style="background: rgba(92,131,255,0.25); border: 1px solid rgba(92,131,255,0.4); color: var(--accent-light);">
                            Copy overrides
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
