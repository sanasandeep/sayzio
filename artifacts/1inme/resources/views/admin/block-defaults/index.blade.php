@extends('admin.layouts.app')
@section('title', 'Block First-Paint Defaults')
@section('page-title', 'Block First-Paint Defaults')

@section('content')
<div class="max-w-5xl">

    @if(session('success'))
        <div class="mb-4 p-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-200 text-sm">
            <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
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
                    — existing blocks are never changed.
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
                        <i class="fas fa-chevron-right text-xs" style="color: var(--text-faint);"></i>
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach

</div>
@endsection
