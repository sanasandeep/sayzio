@extends('user.layouts.app')
@section('title', 'Welcome to ' . config('app.name'))

@section('content')
@php
    $grouped = \App\Modules\User\Services\PersonaCatalog::grouped();
@endphp
<div class="max-w-7xl mx-auto" x-data="{ picked: '{{ $current ?? '' }}', q: '' }">
    <div class="flex items-start justify-between gap-4 mb-6">
        <div class="flex-1 text-center">
            <p class="text-xs font-semibold uppercase tracking-wider text-violet-400 mb-2">Step 1 of 2</p>
            <h1 class="text-3xl font-bold text-white mb-2">Welcome{{ auth()->user()->name ? ', ' . explode(' ', auth()->user()->name)[0] : '' }} 👋</h1>
            <p class="text-sm text-white/50">Tell us what you do — we'll line up the templates that fit best.</p>
        </div>
        <form method="POST" action="{{ route('user.onboarding.go-to-dashboard') }}" class="shrink-0">
            @csrf
            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-white/5 hover:bg-white/10 border border-white/10 hover:border-white/20 text-white/80 hover:text-white rounded-xl text-xs font-semibold transition">
                <i class="fas fa-th-large text-xs"></i> Go to dashboard
            </button>
        </form>
    </div>

    @if(session('error'))
        <div class="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm text-center">{{ session('error') }}</div>
    @endif

    <div class="mb-5 max-w-md mx-auto relative">
        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs text-white/30"></i>
        <input type="text" x-model="q" placeholder="Search personas (e.g. coach, photographer, restaurant)…"
               class="w-full bg-white/5 border border-white/10 rounded-xl pl-9 pr-3 py-2 text-sm text-white placeholder:text-white/30">
    </div>

    <form method="POST" action="{{ route('user.onboarding.persona.save') }}">
        @csrf
        <input type="hidden" name="persona" :value="picked">

        @foreach($grouped as $groupName => $items)
            <div x-show="$el.querySelectorAll('[data-persona-card]:not([style*=&quot;display: none&quot;])').length > 0">
                <h2 class="text-[11px] font-bold uppercase tracking-wider text-white/40 mb-3 mt-6 first:mt-0">{{ $groupName }}</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 lg:grid-cols-6 gap-2.5 mb-4">
                    @foreach($items as $p)
                        <button type="button"
                                data-persona-card
                                x-show="q === '' || '{{ strtolower(addslashes($p['label'] . ' ' . ($p['blurb'] ?? '') . ' ' . $p['slug'])) }}'.includes(q.toLowerCase())"
                                @click="picked = '{{ $p['slug'] }}'"
                                :class="picked === '{{ $p['slug'] }}' ? 'ring-2 ring-violet-500 border-violet-500/60 -translate-y-0.5' : 'border-white/10 hover:border-white/30 hover:-translate-y-0.5'"
                                class="group relative overflow-hidden rounded-xl border bg-white/5 text-left transition flex flex-col">
                            <div class="relative w-full h-20 sm:h-24 overflow-hidden bg-white/5">
                                @if(!empty($p['image']))
                                    <img src="{{ $p['image'] }}" alt="{{ $p['label'] }}" loading="lazy"
                                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"
                                         class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition duration-500">
                                @endif
                                <div class="absolute inset-0 items-center justify-center bg-gradient-to-br from-violet-600/40 to-fuchsia-600/30" style="display: {{ empty($p['image']) ? 'flex' : 'none' }};">
                                    <i class="fas {{ $p['icon'] }} text-2xl text-white/70"></i>
                                </div>
                                <div class="absolute top-1.5 right-1.5" x-show="picked === '{{ $p['slug'] }}'" x-cloak>
                                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-violet-500 text-white text-[9px] shadow-lg"><i class="fas fa-check"></i></span>
                                </div>
                            </div>
                            <div class="px-2.5 py-2 bg-white/[0.04] border-t border-white/5 flex-1">
                                <p class="text-[12px] font-semibold text-white leading-tight truncate">{{ $p['label'] }}</p>
                                <p class="text-[10.5px] text-white/55 leading-snug line-clamp-2 mt-0.5">{{ $p['blurb'] }}</p>
                            </div>
                        </button>
                    @endforeach
                </div>
            </div>
        @endforeach

        <div x-show="q !== ''" x-cloak class="text-center text-xs text-white/30 py-2"
             :class="$root.querySelectorAll('[data-persona-card]:not([style*=&quot;display: none&quot;])').length === 0 ? '' : 'hidden'">
            <p>No personas match "<span x-text="q"></span>". Try a broader keyword.</p>
        </div>

        <div class="mt-6 flex items-center justify-between border-t border-white/5 pt-5">
            <span class="text-xs text-white/30">Don't see your fit? Pick "Something else" — you can always change it later.</span>
            <button type="submit"
                    :disabled="!picked"
                    :class="picked ? 'bg-violet-600 hover:bg-violet-700 text-white' : 'bg-white/5 text-white/30 cursor-not-allowed'"
                    class="px-6 py-2.5 rounded-xl text-sm font-semibold transition">
                Continue <i class="fas fa-arrow-right text-xs ml-1"></i>
            </button>
        </div>
    </form>
</div>
@endsection
