@extends('user.layouts.app')
@section('title', 'Create Link')

@section('content')
@php
    $aliasLimits = $aliasLimits ?? ['min' => 3, 'max' => 50];
    $domainHost  = $domainHost ?? request()->getHost();
@endphp
<div class="max-w-4xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('user.links.index') }}" class="text-white/30 hover:text-white transition-colors"><i class="fas fa-arrow-left"></i></a>
        <h1 class="text-2xl font-bold text-white">Create Link</h1>
    </div>

    <style>
        @media (prefers-reduced-motion: no-preference) {
            .lt-card-reveal { opacity: 0; transform: translateY(12px); animation: ltCardReveal .5s cubic-bezier(.21,.6,.35,1) forwards; }
            @keyframes ltCardReveal { to { opacity: 1; transform: none; } }
        }
    </style>

    {{-- HERO: guided wizard — the recommended, primary path --}}
    <a href="{{ route('user.links.wizard') }}"
       class="block relative overflow-hidden glass rounded-3xl p-6 sm:p-8 mb-8 border border-blue-500/30 bg-gradient-to-br from-blue-500/15 via-indigo-500/10 to-fuchsia-500/10 hover:from-blue-500/20 hover:via-indigo-500/15 hover:to-fuchsia-500/15 transition-all group hover:shadow-2xl hover:shadow-blue-500/20">
        <div class="absolute -top-24 -right-16 w-64 h-64 rounded-full bg-fuchsia-500/20 blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-24 -left-16 w-64 h-64 rounded-full bg-blue-500/20 blur-3xl pointer-events-none"></div>
        <div class="relative flex flex-col sm:flex-row sm:items-center gap-5 sm:gap-6">
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-blue-500 to-fuchsia-500 text-white flex items-center justify-center flex-shrink-0 shadow-lg shadow-blue-500/30">
                <i class="fas fa-magic text-2xl"></i>
            </div>
            <div class="flex-1 min-w-0">
                <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-blue-200 bg-blue-500/20 border border-blue-400/30 rounded-full px-2.5 py-0.5 mb-2">
                    <i class="fas fa-star text-[9px]"></i> Recommended
                </span>
                <div class="text-xl sm:text-2xl font-bold text-white">Build a Link in Bio with the guided wizard</div>
                <div class="text-sm text-white/60 mt-1.5 max-w-xl">Answer a few quick questions and we'll generate your whole page for you — blocks, layout and styling, all done. The fastest way to get a page live.</div>
            </div>
            <div class="flex items-center gap-2 text-blue-200 font-medium flex-shrink-0">
                <span class="text-sm hidden sm:inline">Start building</span>
                <span class="w-10 h-10 rounded-full bg-blue-500/20 border border-blue-400/30 flex items-center justify-center group-hover:bg-blue-500/30 group-hover:translate-x-0.5 transition-all">
                    <i class="fas fa-arrow-right"></i>
                </span>
            </div>
        </div>
    </a>

    {{-- AI BUILDER: describe it and AI assembles the page — second prominent path --}}
    @if(!empty($aiBuilderEnabled))
    <form method="POST" action="{{ route('user.links.store') }}" class="mb-8"
          onsubmit="this.querySelector('input[name=alias]').value = (document.getElementById('create-link-alias')?.value || '').trim();">
        @csrf
        <input type="hidden" name="type" value="biolink">
        <input type="hidden" name="start_mode" value="ai">
        <input type="hidden" name="alias" value="">
        <button type="submit"
                class="w-full text-left block relative overflow-hidden glass rounded-3xl p-6 sm:p-8 border border-fuchsia-500/30 bg-gradient-to-br from-fuchsia-500/15 via-indigo-500/10 to-blue-500/10 hover:from-fuchsia-500/20 hover:via-indigo-500/15 hover:to-blue-500/15 transition-all group hover:shadow-2xl hover:shadow-fuchsia-500/20">
            <div class="absolute -top-24 -left-16 w-64 h-64 rounded-full bg-fuchsia-500/20 blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-24 -right-16 w-64 h-64 rounded-full bg-blue-500/20 blur-3xl pointer-events-none"></div>
            <div class="relative flex flex-col sm:flex-row sm:items-center gap-5 sm:gap-6">
                <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-fuchsia-500 to-blue-500 text-white flex items-center justify-center flex-shrink-0 shadow-lg shadow-fuchsia-500/30">
                    <i class="fas fa-wand-magic-sparkles text-2xl"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-fuchsia-200 bg-fuchsia-500/20 border border-fuchsia-400/30 rounded-full px-2.5 py-0.5 mb-2">
                        <i class="fas fa-bolt text-[9px]"></i> AI Powered
                    </span>
                    <div class="text-xl sm:text-2xl font-bold text-white">Describe it and AI builds your page</div>
                    <div class="text-sm text-white/60 mt-1.5 max-w-xl">Skip the blank page — describe your page, paste your links, and add photos. AI assembles a complete Link in Bio for you to refine in the editor. Uses AI credits.</div>
                </div>
                <div class="flex items-center gap-2 text-fuchsia-200 font-medium flex-shrink-0">
                    <span class="text-sm hidden sm:inline">Build with AI</span>
                    <span class="w-10 h-10 rounded-full bg-fuchsia-500/20 border border-fuchsia-400/30 flex items-center justify-center group-hover:bg-fuchsia-500/30 group-hover:translate-x-0.5 transition-all">
                        <i class="fas fa-arrow-right"></i>
                    </span>
                </div>
            </div>
        </button>
    </form>
    @else
    {{-- AI BUILDER teaser: the engine is off / unavailable, but we keep the
         card visible so users discover the capability and get a clear path
         to turn it on (admins) or upgrade (everyone else). --}}
    @php
        $aiTeaserHref = !empty($aiBuilderAdminCanEnable)
            ? route('admin.ai-engine.edit')
            : route('user.upgrade');
        $aiTeaserCta = !empty($aiBuilderAdminCanEnable) ? 'Enable AI' : 'Upgrade';
    @endphp
    <a href="{{ $aiTeaserHref }}"
       class="block relative overflow-hidden glass rounded-3xl p-6 sm:p-8 mb-8 border border-white/10 bg-white/[0.03] hover:border-fuchsia-500/30 hover:bg-fuchsia-500/[0.06] transition-all group">
        <div class="relative flex flex-col sm:flex-row sm:items-center gap-5 sm:gap-6">
            <div class="w-16 h-16 rounded-2xl bg-white/5 border border-white/10 text-white/40 flex items-center justify-center flex-shrink-0">
                <i class="fas fa-wand-magic-sparkles text-2xl"></i>
            </div>
            <div class="flex-1 min-w-0">
                <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-white/50 bg-white/5 border border-white/10 rounded-full px-2.5 py-0.5 mb-2">
                    <i class="fas fa-lock text-[9px]"></i> {{ !empty($aiBuilderAdminCanEnable) ? 'Currently off' : 'Locked' }}
                </span>
                <div class="text-xl sm:text-2xl font-bold text-white/70">Describe it and AI builds your page</div>
                <div class="text-sm text-white/40 mt-1.5 max-w-xl">Skip the blank page — describe your page, paste your links, and add photos. AI assembles a complete Link in Bio for you to refine in the editor.
                    @if(!empty($aiBuilderAdminCanEnable))
                        Turn on the AI Engine to make this available.
                    @else
                        Available on a higher plan.
                    @endif
                </div>
            </div>
            <div class="flex items-center gap-2 text-fuchsia-200 font-medium flex-shrink-0">
                <span class="text-sm hidden sm:inline">{{ $aiTeaserCta }}</span>
                <span class="w-10 h-10 rounded-full bg-fuchsia-500/15 border border-fuchsia-400/20 flex items-center justify-center group-hover:bg-fuchsia-500/25 group-hover:translate-x-0.5 transition-all">
                    <i class="fas fa-arrow-right"></i>
                </span>
            </div>
        </div>
    </a>
    @endif

    <form method="POST" action="{{ route('user.links.choose-type') }}"
          x-data="{ type: '{{ old('type', $lastType ?? '') }}' }"
          x-init="window.__voiceSurface = { name: 'create_link' }"
          @voice-action.window="
              if ($event.detail && $event.detail.type === 'select_link_type' && $event.detail.link_type) {
                  type = $event.detail.link_type;
                  $nextTick(() => $el.submit());
              }
          ">
        @csrf

        @php
            $linkCategories = \App\Modules\User\Support\LinkTypeCategories::categories();
            $cardIndex = 0;
        @endphp

        {{-- SECONDARY: pick a link type manually --}}
        <div class="glass rounded-2xl p-6 mb-6">
            <h2 class="text-base font-semibold text-white mb-1">…or pick a link type manually</h2>
            <p class="text-xs text-white/40 mb-6">Pick one to continue — we'll only ask for the fields that matter for that type.</p>

            <div class="mb-6">
                <label class="block text-sm font-medium text-white/60 mb-1.5">
                    Custom URL <span class="text-white/30 text-xs">(optional)</span>
                </label>
                <div class="flex items-stretch rounded-xl bg-white/5 border border-white/10 focus-within:ring-2 focus-within:ring-blue-500/40 overflow-hidden">
                    <span class="flex items-center px-3 text-sm text-white/40 bg-white/[0.03] border-r border-white/10 select-none">
                        {{ $domainHost }}/
                    </span>
                    <input type="text" name="alias" id="create-link-alias"
                           value="{{ old('alias', $prefillAlias ?? '') }}"
                           placeholder="leave blank to auto-generate"
                           minlength="{{ $aliasLimits['min'] }}"
                           maxlength="{{ $aliasLimits['max'] }}"
                           pattern="[A-Za-z0-9_\-]+"
                           autocomplete="off" spellcheck="false"
                           class="flex-1 bg-transparent px-3 py-2.5 text-sm text-white placeholder-white/20 outline-none">
                </div>
                @error('alias') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
                <p class="text-xs text-white/30 mt-1.5">
                    Leave blank and we'll generate one for you. Letters, numbers, dashes &amp; underscores only.
                    Length: {{ $aliasLimits['min'] }}–{{ $aliasLimits['max'] }} characters
                    @if(!empty($aliasUpgradeHint))
                        · <a href="{{ route('user.plans.index') }}" class="text-blue-400 hover:underline">upgrade for more</a>
                    @endif.
                </p>
            </div>

            <div class="space-y-8">
                @foreach($linkCategories as $category)
                    <section>
                        <div class="mb-3">
                            <h3 class="text-sm font-semibold text-white/90">{{ $category['label'] }}</h3>
                            <p class="text-xs text-white/40 mt-0.5">{{ $category['desc'] }}</p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($category['types'] as $opt)
                                <label class="relative cursor-pointer block group h-full lt-card-reveal" style="animation-delay: {{ min($cardIndex++ * 45, 540) }}ms">
                                    <input type="radio" name="type" value="{{ $opt['value'] }}" x-model="type" class="sr-only peer">
                                    <div class="h-full border rounded-2xl p-4 flex flex-col gap-3 transition-all duration-200 motion-safe:group-hover:-translate-y-1"
                                         :class="type === '{{ $opt['value'] }}'
                                            ? 'border-blue-500 bg-blue-500/10 ring-2 ring-blue-500/30 shadow-lg shadow-blue-500/10'
                                            : 'border-white/10 hover:border-white/20 hover:bg-white/[0.04] hover:shadow-lg hover:shadow-black/20'">
                                        <div class="relative rounded-xl overflow-hidden border border-white/5 bg-white/[0.02] aspect-[5/3]">
                                            <img src="{{ asset('img/link-types/' . $opt['value'] . '.svg') }}"
                                                 alt="{{ $opt['label'] }} preview" loading="lazy"
                                                 class="absolute inset-0 w-full h-full object-cover transition-transform duration-300 motion-safe:group-hover:scale-[1.06]"
                                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div class="absolute inset-0 hidden items-center justify-center {{ $opt['badge'] }}">
                                                <i class="fas {{ $opt['icon'] }} text-3xl"></i>
                                            </div>
                                        </div>
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-2.5 min-w-0">
                                                <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0 {{ $opt['badge'] }}">
                                                    <i class="fas {{ $opt['icon'] }}"></i>
                                                </div>
                                                <div class="text-sm font-semibold text-white truncate">{{ $opt['label'] }}</div>
                                            </div>
                                            <span class="w-5 h-5 rounded-full border flex items-center justify-center flex-shrink-0 transition-all"
                                                  :class="type === '{{ $opt['value'] }}'
                                                    ? 'border-blue-400 bg-blue-500'
                                                    : 'border-white/20'">
                                                <i class="fas fa-check text-[10px] text-white transition-opacity"
                                                   :class="type === '{{ $opt['value'] }}' ? 'opacity-100' : 'opacity-0'"></i>
                                            </span>
                                        </div>
                                        <div class="text-xs text-white/50 leading-relaxed">{{ $opt['desc'] }}</div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
            @error('type') <p class="text-red-400 text-sm mt-2">{{ $message }}</p> @enderror

            <div class="flex items-center justify-end gap-3 mt-6">
                <a href="{{ route('user.links.index') }}" class="px-5 py-2.5 text-sm text-white/40 hover:text-white hover:bg-white/5 rounded-xl transition-all">Cancel</a>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-xl text-sm font-medium transition-all hover:shadow-lg hover:shadow-blue-500/20">
                    Continue <i class="fas fa-arrow-right ml-1.5 text-xs"></i>
                </button>
            </div>
        </div>
    </form>

    {{-- TERTIARY: bulk & advanced — rare actions, de-emphasized --}}
    <div class="mt-8">
        <h2 class="text-xs font-semibold uppercase tracking-wide text-white/40 mb-3 px-1">Bulk &amp; advanced</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <a href="{{ route('user.links.url.bulk') }}"
               class="block glass rounded-xl p-4 border border-white/10 hover:border-emerald-500/30 hover:bg-emerald-500/[0.06] transition-all group">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-emerald-500/15 text-emerald-300 flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm text-white font-medium truncate">Bulk create short links</div>
                        <div class="text-xs text-white/40 mt-0.5">Paste a list or upload a CSV.</div>
                    </div>
                    <i class="fas fa-arrow-right text-white/20 group-hover:text-emerald-300 transition-colors text-xs"></i>
                </div>
            </a>

            <a href="{{ route('user.links.biolink.bulk') }}"
               class="block glass rounded-xl p-4 border border-white/10 hover:border-sky-500/30 hover:bg-sky-500/[0.06] transition-all group">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-sky-500/15 text-sky-300 flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-table"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm text-white font-medium truncate">Bulk create Link in Bio pages</div>
                        <div class="text-xs text-white/40 mt-0.5">Mail-merge a master page from a sheet.</div>
                    </div>
                    <i class="fas fa-arrow-right text-white/20 group-hover:text-sky-300 transition-colors text-xs"></i>
                </div>
            </a>
        </div>
    </div>
</div>
@endsection
