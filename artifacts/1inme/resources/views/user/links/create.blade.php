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
        [x-cloak] { display: none !important; }
    </style>

    {{-- HERO: guided wizard — the recommended, primary path. Carries the typed
         "Custom URL" (alias) through so a user who fills it in and clicks here
         keeps it; blank → wizard auto-generates as before. --}}
    <a href="{{ route('user.links.wizard') }}"
       data-wizard-base="{{ route('user.links.wizard') }}"
       onclick="(function(a){var v=(document.getElementById('create-link-alias')||{}).value;v=(v||'').trim();a.href=a.getAttribute('data-wizard-base')+(v?('?alias='+encodeURIComponent(v)):'');})(this)"
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

    @php
        $linkCategories = \App\Modules\User\Support\LinkTypeCategories::categories();
        $linkIntents    = \App\Modules\User\Support\LinkTypeCategories::intents();
        $cardIndex = 0;
        $linkFilterCats = [];
        foreach ($linkCategories as $catIdx => $cat) {
            $linkFilterCats['cat-' . $catIdx] = array_map(
                static fn (array $t): array => ['label' => $t['label'], 'desc' => $t['desc']],
                $cat['types']
            );
        }

        // Goal => guided-wizard URL for the goals the wizard can build,
        // pre-seeding the matching persona group — and, for goals that map to
        // exactly one persona, the persona too (so the wizard skips its second
        // question). Goals absent here have no wizard path and keep the manual
        // flow. The typed Custom URL alias is layered on at click time (JS).
        $wizardIntentUrls = [];
        foreach (\App\Modules\User\Support\LinkTypeCategories::wizardGroups() as $intentType => $wizardCfg) {
            $params = [];
            if (!empty($wizardCfg['group'])) {
                $params['group'] = $wizardCfg['group'];
                if (!empty($wizardCfg['persona'])) {
                    $params['persona'] = $wizardCfg['persona'];
                }
            }
            $wizardIntentUrls[$intentType] = route('user.links.wizard', $params);
        }
    @endphp

    <form method="POST" action="{{ route('user.links.choose-type') }}"
          x-data="linkTypePicker({ type: '{{ old('type', $lastType ?? '') }}', searchIndex: {{ Illuminate\Support\Js::from(\App\Modules\User\Support\LinkTypeCategories::searchIndex()) }}, cats: {{ \Illuminate\Support\Js::from($linkFilterCats) }}, wizardPaths: {{ \Illuminate\Support\Js::from($wizardIntentUrls) }} })"
          x-init="window.__voiceSurface = { name: 'create_link' }"
          @voice-action.window="
              if ($event.detail && $event.detail.type === 'select_link_type' && $event.detail.link_type) {
                  type = $event.detail.link_type;
                  $nextTick(() => $el.submit());
              }
          ">
        @csrf

        {{-- SECONDARY: pick a link type manually --}}
        <div class="glass rounded-2xl p-6 mb-6">
            <h2 class="text-base font-semibold text-white mb-1">…or pick a link type manually</h2>
            <p class="text-xs text-white/40 mb-6">Pick one to continue — we'll only ask for the fields that matter for that type.</p>

            {{-- INTENT PROMPT: map a plain-language goal to the matching link type --}}
            <div class="mb-6 rounded-2xl border border-white/10 bg-white/[0.03] p-4 sm:p-5">
                <div class="flex items-center gap-2 mb-1">
                    <span class="w-7 h-7 rounded-lg bg-blue-500/15 text-blue-300 flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-wand-magic-sparkles text-xs"></i>
                    </span>
                    <h3 class="text-sm font-semibold text-white">What are you trying to do?</h3>
                </div>
                <p class="text-xs text-white/40 mb-3.5">Tap a goal and we'll pick the matching type for you — or just choose one below.</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($linkIntents as $intent)
                        <button type="button"
                                @click="select('{{ $intent['type'] }}')"
                                class="inline-flex items-center gap-1.5 text-xs font-medium rounded-full px-3 py-1.5 border transition-all"
                                :class="type === '{{ $intent['type'] }}'
                                    ? 'border-blue-400 bg-blue-500/20 text-blue-100 shadow-lg shadow-blue-500/10'
                                    : 'border-white/10 bg-white/5 text-white/60 hover:border-white/20 hover:bg-white/10 hover:text-white'">
                            <i class="fas {{ $intent['icon'] }} text-[10px]"></i>
                            {{ $intent['label'] }}
                        </button>
                    @endforeach
                </div>

                {{-- FREE-TEXT SEARCH: type a goal in your own words --}}
                <div class="mt-3.5 pt-3.5 border-t border-white/10">
                    <label for="intent-search" class="block text-xs text-white/40 mb-2">…or describe it in your own words</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-white/30 pointer-events-none">
                            <i class="fas fa-magnifying-glass text-xs"></i>
                        </span>
                        <input type="text" id="intent-search" autocomplete="off" spellcheck="false"
                               x-model="query"
                               @input.debounce.250ms="runSearch()"
                               @keydown.enter.prevent="runSearch()"
                               placeholder="e.g. take orders for my cafe, sell my photos, save my number…"
                               aria-describedby="intent-search-status"
                               class="w-full rounded-xl bg-white/5 border border-white/10 pl-9 pr-9 py-2.5 text-sm text-white placeholder-white/25 outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-400/40 transition-all">
                        <button type="button" x-show="query.length > 0" x-cloak
                                @click="clearSearch()"
                                aria-label="Clear search"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 w-6 h-6 rounded-md flex items-center justify-center text-white/40 hover:text-white hover:bg-white/10 transition-all">
                            <i class="fas fa-xmark text-xs"></i>
                        </button>
                    </div>
                    <p id="intent-search-status" class="mt-2 text-xs min-h-[1rem]" aria-live="polite"
                       x-show="matchLabel || noMatch" x-cloak
                       :class="noMatch ? 'text-amber-300/80' : 'text-blue-300/90'">
                        <template x-if="matchLabel">
                            <span><i class="fas fa-circle-check text-[10px] mr-1"></i>Matched <span class="font-semibold" x-text="matchLabel"></span> — selected below.</span>
                        </template>
                        <template x-if="noMatch">
                            <span><i class="fas fa-circle-info text-[10px] mr-1"></i>No matching type yet — try different words or pick one below.</span>
                        </template>
                    </p>
                </div>

                {{-- One-tap guided-wizard path. Appears only when the chosen
                     goal maps to a wizard-supported type; the wizard lands with
                     the matching category pre-seeded so the user skips its first
                     question. Goals without a wizard path keep manual select. --}}
                <a x-cloak x-show="!!wizardPaths[type]" :href="wizardPaths[type] || '#'"
                   @click="$el.href = wizardHref(type)"
                   x-transition:enter="transition ease-out duration-200"
                   x-transition:enter-start="opacity-0 -translate-y-1"
                   x-transition:enter-end="opacity-100 translate-y-0"
                   class="mt-3.5 flex items-center gap-3 rounded-xl border border-blue-500/30 bg-gradient-to-br from-blue-500/15 to-fuchsia-500/10 hover:from-blue-500/20 hover:to-fuchsia-500/15 px-4 py-3 transition-all group">
                    <span class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-500 to-fuchsia-500 text-white flex items-center justify-center flex-shrink-0 shadow-lg shadow-blue-500/30">
                        <i class="fas fa-magic text-xs"></i>
                    </span>
                    <span class="flex-1 min-w-0">
                        <span class="block text-sm font-semibold text-white">Build this with the guided wizard</span>
                        <span class="block text-xs text-white/50">We'll start you in the right place — answer a few questions and we'll generate the page.</span>
                    </span>
                    <span class="flex items-center gap-1.5 text-blue-200 text-sm font-medium flex-shrink-0">
                        <span class="hidden sm:inline">Start</span>
                        <i class="fas fa-arrow-right text-xs group-hover:translate-x-0.5 transition-transform"></i>
                    </span>
                </a>
            </div>

            @include('user.links.partials.alias-checker')
            <div class="mb-6" x-data="aliasChecker('{{ route('user.links.check-alias') }}')" x-init="init()">
                <label class="block text-sm font-medium text-white/60 mb-1.5">
                    Custom URL <span class="text-white/30 text-xs">(optional)</span>
                </label>
                <div class="flex items-stretch rounded-xl bg-white/5 border overflow-hidden transition-colors"
                     :class="state === 'available' ? 'border-emerald-500/40 focus-within:ring-2 focus-within:ring-emerald-500/40'
                         : (isError ? 'border-red-500/40 focus-within:ring-2 focus-within:ring-red-500/40'
                         : 'border-white/10 focus-within:ring-2 focus-within:ring-blue-500/40')">
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
                           @input.debounce.400ms="check($event.target.value)"
                           aria-describedby="create-link-alias-status"
                           class="flex-1 bg-transparent px-3 py-2.5 text-sm text-white placeholder-white/20 outline-none">
                    <span class="flex items-center px-3" x-show="state && state !== 'empty'" x-cloak>
                        <i x-show="state === 'checking'" class="fas fa-spinner fa-spin text-white/40 text-sm"></i>
                        <i x-show="state === 'available'" class="fas fa-circle-check text-emerald-400 text-sm"></i>
                        <i x-show="isError" class="fas fa-circle-xmark text-red-400 text-sm"></i>
                    </span>
                </div>
                @error('alias') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
                <p id="create-link-alias-status" aria-live="polite"
                   x-show="message && state && state !== 'empty'" x-cloak
                   class="text-sm mt-1.5"
                   :class="state === 'available' ? 'text-emerald-400' : (isError ? 'text-red-400' : 'text-white/40')"
                   x-text="message"></p>
                <p class="text-xs text-white/30 mt-1.5">
                    Leave blank and we'll generate one for you. Letters, numbers, dashes &amp; underscores only.
                    Length: {{ $aliasLimits['min'] }}–{{ $aliasLimits['max'] }} characters
                    @if(!empty($aliasUpgradeHint))
                        · <a href="{{ route('user.plans.index') }}" class="text-blue-400 hover:underline">upgrade for more</a>
                    @endif.
                </p>
            </div>

            {{-- Search + category filters for the manual picker --}}
            <div class="mb-6">
                <label for="link-type-search" class="sr-only">Search link types</label>
                <div class="relative">
                    <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-white/30 text-sm pointer-events-none"></i>
                    <input type="text" id="link-type-search" x-model="search"
                           placeholder="Search link types…"
                           autocomplete="off" spellcheck="false"
                           @keydown.escape="resetFilters()"
                           class="w-full rounded-xl bg-white/5 border border-white/10 focus:ring-2 focus:ring-blue-500/40 pl-10 pr-10 py-2.5 text-sm text-white placeholder-white/30 outline-none transition-all">
                    <button type="button" x-show="search" x-cloak @click="search = ''"
                            aria-label="Clear search"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-white/30 hover:text-white transition-colors">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>
                <div class="flex flex-wrap gap-2 mt-3" role="group" aria-label="Filter by category">
                    <button type="button" @click="activeCategory = 'all'"
                            class="px-3 py-1.5 rounded-full text-xs font-medium border transition-all"
                            :class="activeCategory === 'all'
                                ? 'border-blue-500 bg-blue-500/15 text-blue-200'
                                : 'border-white/10 text-white/50 hover:border-white/20 hover:text-white/80'">
                        All
                    </button>
                    @foreach($linkCategories as $catIdx => $category)
                        <button type="button" @click="activeCategory = 'cat-{{ $catIdx }}'"
                                class="px-3 py-1.5 rounded-full text-xs font-medium border transition-all"
                                :class="activeCategory === 'cat-{{ $catIdx }}'
                                    ? 'border-blue-500 bg-blue-500/15 text-blue-200'
                                    : 'border-white/10 text-white/50 hover:border-white/20 hover:text-white/80'">
                            {{ $category['label'] }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="space-y-8">
                @foreach($linkCategories as $catIdx => $category)
                    <section x-show="categoryHasMatch('cat-{{ $catIdx }}')">
                        <div class="mb-3">
                            <h3 class="text-sm font-semibold text-white/90">{{ $category['label'] }}</h3>
                            <p class="text-xs text-white/40 mt-0.5">{{ $category['desc'] }}</p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($category['types'] as $opt)
                                <label id="lt-card-{{ $opt['value'] }}" class="relative cursor-pointer block group h-full lt-card-reveal"
                                       x-show="matches({{ \Illuminate\Support\Js::from($opt['label']) }}, {{ \Illuminate\Support\Js::from($opt['desc']) }}, 'cat-{{ $catIdx }}')"
                                       style="animation-delay: {{ min($cardIndex++ * 45, 540) }}ms">
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

                {{-- Empty state: no link type matches the current search/filter --}}
                <div x-show="!anyMatch()" x-cloak class="text-center py-12">
                    <div class="w-12 h-12 mx-auto rounded-full bg-white/5 flex items-center justify-center text-white/30 mb-3">
                        <i class="fas fa-search text-lg"></i>
                    </div>
                    <p class="text-sm text-white/60">No link types match<template x-if="search.trim()"> “<span class="text-white font-medium" x-text="search.trim()"></span>”</template>.</p>
                    <button type="button" @click="resetFilters()" class="mt-3 text-sm text-blue-400 hover:underline">Clear search</button>
                </div>
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

@push('scripts')
<script>
document.addEventListener('alpine:init', function () {
    window.Alpine.data('linkTypePicker', function (config) {
        return {
            type: config.type || '',
            searchIndex: Array.isArray(config.searchIndex) ? config.searchIndex : [],
            query: '',
            matchLabel: '',
            noMatch: false,

            // Manual card filter (search box + category tabs over the grid).
            search: '',
            activeCategory: 'all',
            cats: config.cats || {},

            // Goal => guided-wizard URL map for the one-tap guided path.
            wizardPaths: config.wizardPaths || {},

            // Build the guided-wizard href for a goal, layering on the typed
            // Custom URL alias so a user who fills it in keeps it through the
            // wizard (mirrors the hero card). Computed at click time because the
            // alias lives in a plain (non-Alpine) input. Appends with the right
            // separator so an existing ?group=&persona= path stays intact.
            wizardHref: function (type) {
                var base = this.wizardPaths[type];
                if (!base) { return '#'; }
                var alias = (document.getElementById('create-link-alias') || {}).value;
                alias = (alias || '').trim();
                if (!alias) { return base; }
                return base + (base.indexOf('?') !== -1 ? '&' : '?') + 'alias=' + encodeURIComponent(alias);
            },

            matches: function (label, desc, key) {
                if (this.activeCategory !== 'all' && this.activeCategory !== key) { return false; }
                var q = this.search.trim().toLowerCase();
                if (!q) { return true; }
                return (label + ' ' + desc).toLowerCase().indexOf(q) !== -1;
            },

            categoryHasMatch: function (key) {
                if (this.activeCategory !== 'all' && this.activeCategory !== key) { return false; }
                var q = this.search.trim().toLowerCase();
                if (!q) { return true; }
                return (this.cats[key] || []).some(function (t) {
                    return (t.label + ' ' + t.desc).toLowerCase().indexOf(q) !== -1;
                });
            },

            anyMatch: function () {
                var self = this;
                return Object.keys(this.cats).some(function (k) { return self.categoryHasMatch(k); });
            },

            resetFilters: function () {
                this.search = '';
                this.activeCategory = 'all';
            },

            scrollToCard: function (value) {
                this.$nextTick(function () {
                    var el = document.getElementById('lt-card-' + value);
                    if (!el) { return; }
                    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                    el.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'center' });
                });
            },

            select: function (value) {
                this.type = value;
                this.scrollToCard(value);
            },

            clearSearch: function () {
                this.query = '';
                this.matchLabel = '';
                this.noMatch = false;
            },

            // Score one search-index entry against the normalized query. Higher
            // is better; 0 means no useful overlap.
            scoreEntry: function (query, entry) {
                var terms = [String(entry.label || '').toLowerCase()];
                (entry.keywords || []).forEach(function (k) { terms.push(String(k).toLowerCase()); });

                var queryTokens = query.split(/\s+/).filter(function (t) { return t.length >= 2; });
                var best = 0;

                for (var i = 0; i < terms.length; i++) {
                    var term = terms[i];
                    if (!term) { continue; }
                    var score = 0;

                    if (term === query) {
                        score = 100;
                    } else if (term.indexOf(query) === 0) {
                        score = 82;
                    } else if (term.indexOf(query) !== -1) {
                        score = 66;
                    } else if (query.length >= 3 && query.indexOf(term) !== -1 && term.length >= 3) {
                        score = 60;
                    } else if (queryTokens.length) {
                        var termTokens = term.split(/\s+/);
                        var overlap = 0;
                        queryTokens.forEach(function (qt) {
                            var hit = termTokens.some(function (tt) {
                                return tt === qt || (qt.length >= 3 && (tt.indexOf(qt) !== -1 || qt.indexOf(tt) !== -1));
                            });
                            if (hit) { overlap++; }
                        });
                        if (overlap) {
                            score = 28 + (overlap * 8);
                        }
                    }

                    if (score > best) { best = score; }
                }

                return best;
            },

            runSearch: function () {
                var query = (this.query || '').trim().toLowerCase();
                if (query.length < 2) {
                    this.matchLabel = '';
                    this.noMatch = false;
                    return;
                }

                var bestEntry = null;
                var bestScore = 0;
                for (var i = 0; i < this.searchIndex.length; i++) {
                    var s = this.scoreEntry(query, this.searchIndex[i]);
                    if (s > bestScore) { bestScore = s; bestEntry = this.searchIndex[i]; }
                }

                if (bestEntry && bestScore >= 28) {
                    this.matchLabel = bestEntry.label;
                    this.noMatch = false;
                    this.select(bestEntry.type);
                } else {
                    this.matchLabel = '';
                    this.noMatch = true;
                }
            },
        };
    });
});
</script>
@endpush
