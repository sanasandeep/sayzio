@extends('user.layouts.app')
@section('title', 'Choose a Template')

@section('content')
<div class="max-w-6xl mx-auto" x-data="{ category: 'all', search: '' }">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-white">Choose a starting template</h1>
            <p class="text-sm text-white/40 mt-1">Pick a curated preset to skip the blank page, or start from scratch.</p>
            @if(!empty($hasRecommended))
                <p class="text-xs text-violet-300 mt-2"><i class="fas fa-sparkles mr-1"></i>Recommended for {{ $personaLabel }} appear first.</p>
            @endif
        </div>
        <a href="{{ route('user.links.blocks.editor', $link) }}" class="px-4 py-2 text-sm text-white/60 hover:text-white border border-white/10 rounded-xl hover:bg-white/5 transition">
            Skip — start from scratch
        </a>
    </div>

    @if(session('error'))
        <div class="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm">{{ session('error') }}</div>
    @endif

    @php
        $cats = ['all' => 'All'] + \App\Modules\Admin\Models\PageTemplate::categories();
        $usedCats = $pageTemplates->pluck('category')->unique()->all();
    @endphp

    <div class="flex items-center gap-3 mb-5">
        <div class="flex-1">
            <input type="text" x-model="search" placeholder="Search templates…" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-white/20">
        </div>
    </div>

    <div class="flex items-center gap-1 mb-6 overflow-x-auto pb-2">
        @foreach($cats as $key => $label)
            @if($key === 'all' || in_array($key, $usedCats, true))
            <button @click="category = '{{ $key }}'" :class="category === '{{ $key }}' ? 'bg-violet-600 text-white' : 'text-white/50 hover:text-white bg-white/5'" class="px-3 py-1.5 text-xs font-semibold rounded-lg transition flex-shrink-0">
                {{ $label }}
            </button>
            @endif
        @endforeach
    </div>

    @if($pageTemplates->isEmpty())
        <div class="glass rounded-2xl border border-white/10 p-12 text-center">
            <i class="fas fa-layer-group text-3xl text-violet-400 mb-3"></i>
            <h3 class="text-base font-semibold text-white mb-1">No templates available yet</h3>
            <p class="text-sm text-white/40 mb-4">Start from scratch and build your Link in Bio your way.</p>
            <a href="{{ route('user.links.blocks.editor', $link) }}" class="inline-block px-4 py-2 bg-violet-600 text-white rounded-xl text-sm font-medium hover:bg-violet-700">Open editor</a>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($pageTemplates as $tpl)
                @php $locked = $lockedFn($tpl->plan_tier); @endphp
                <div x-show="(category === 'all' || category === '{{ $tpl->category }}') && (search === '' || '{{ strtolower($tpl->name . ' ' . $tpl->description) }}'.includes(search.toLowerCase()))"
                     x-cloak
                     class="glass rounded-2xl border border-white/10 overflow-hidden hover:border-violet-500/40 transition group">
                    <div class="aspect-[4/3] flex items-center justify-center overflow-hidden relative" style="background: linear-gradient(135deg, rgba(124,58,237,0.12), rgba(139,92,246,0.04));">
                        @if($tpl->thumbnail_url)
                            <img src="{{ $tpl->thumbnail_url }}" alt="{{ $tpl->name }}" class="w-full h-full object-cover">
                        @else
                            <img src="{{ asset('template-placeholders/page.svg') }}" alt="{{ $tpl->name }} preview" class="w-full h-full object-cover">
                        @endif
                        @if($locked)
                            <div class="absolute top-2 right-2 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/90 text-white"><i class="fas fa-lock mr-1"></i>{{ $tpl->plan_tier }}</div>
                        @endif
                    </div>
                    <div class="p-4">
                        <h3 class="text-sm font-semibold text-white mb-1">{{ $tpl->name }}</h3>
                        <p class="text-xs text-white/40 mb-1">{{ ucfirst($tpl->category) }} · {{ count($tpl->snapshot['blocks'] ?? []) }} blocks</p>
                        @if($tpl->description)
                            <p class="text-xs text-white/50 mb-3 line-clamp-2">{{ $tpl->description }}</p>
                        @endif
                        @if($locked)
                            <a href="{{ route('user.upgrade') }}" class="block text-center w-full py-2 text-xs font-semibold rounded-xl bg-amber-500/10 text-amber-400 border border-amber-500/20 hover:bg-amber-500/20 transition">
                                <i class="fas fa-lock mr-1"></i>Upgrade to "{{ $tpl->plan_tier }}" to use
                            </a>
                        @else
                            @php $hasBlocks = $link->biolinkBlocks()->exists(); @endphp
                            <form method="POST" action="{{ route('user.links.templates.apply-page', $link) }}"
                                  @if($hasBlocks) onsubmit="return window.themedConfirmSubmit(this, {title: 'Replace existing blocks?', message: 'This will replace your existing blocks on this Link in Bio.', confirmText: 'Replace', confirmIcon: 'fa-arrows-rotate', iconClass: 'fa-triangle-exclamation'})" @endif>
                                @csrf
                                <input type="hidden" name="template_id" value="{{ $tpl->id }}">
                                @if($hasBlocks)<input type="hidden" name="confirm_overwrite" value="1">@endif
                                <button type="submit" class="w-full py-2 text-xs font-semibold rounded-xl bg-violet-600 hover:bg-violet-700 text-white transition">
                                    {{ $hasBlocks ? 'Replace with this template' : 'Use this template' }}
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
