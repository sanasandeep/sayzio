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

    {{-- TOP TIER: two ways to start — the recommended guided wizard (loud) and
         the AI builder (calmer secondary), side-by-side on desktop, stacked on
         mobile. Only the recommended card carries the gradient/glow treatment. --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-8">

        {{-- RECOMMENDED: guided wizard. Carries the typed Custom URL (alias)
             through so a user who fills it in keeps it; blank → auto-generated. --}}
        <a href="{{ route('user.links.wizard') }}"
           data-wizard-base="{{ route('user.links.wizard') }}"
           onclick="(function(a){var v=(document.getElementById('create-link-alias')||{}).value;v=(v||'').trim();a.href=a.getAttribute('data-wizard-base')+(v?('?alias='+encodeURIComponent(v)):'');})(this)"
           class="group h-full flex flex-col relative overflow-hidden glass rounded-3xl p-6 border border-blue-500/30 bg-gradient-to-br from-blue-500/15 via-indigo-500/10 to-fuchsia-500/10 hover:from-blue-500/20 hover:via-indigo-500/15 hover:to-fuchsia-500/15 transition-all hover:shadow-2xl hover:shadow-blue-500/20">
            <div class="absolute -top-20 -right-12 w-48 h-48 rounded-full bg-fuchsia-500/20 blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-20 -left-12 w-48 h-48 rounded-full bg-blue-500/20 blur-3xl pointer-events-none"></div>
            <div class="relative flex items-start justify-between gap-3 mb-4">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-blue-500 to-fuchsia-500 text-white flex items-center justify-center shadow-lg shadow-blue-500/30">
                    <i class="fas fa-magic text-xl"></i>
                </div>
                <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-blue-200 bg-blue-500/20 border border-blue-400/30 rounded-full px-2.5 py-0.5">
                    <i class="fas fa-star text-[9px]"></i> Recommended
                </span>
            </div>
            <div class="relative flex-1">
                <div class="text-lg font-bold text-white">Guided wizard</div>
                <div class="text-sm text-white/60 mt-1">Answer a few questions and we'll build your page for you.</div>
            </div>
            <div class="relative mt-4 flex items-center gap-2 text-blue-200 font-medium text-sm">
                Start building
                <span class="w-8 h-8 rounded-full bg-blue-500/20 border border-blue-400/30 flex items-center justify-center group-hover:translate-x-0.5 transition-all">
                    <i class="fas fa-arrow-right text-xs"></i>
                </span>
            </div>
        </a>

        {{-- SECONDARY: AI builder — neutral surface, fuchsia accent, no glow. --}}
        @if(!empty($aiBuilderEnabled))
        <form method="POST" action="{{ route('user.links.store') }}" class="h-full"
              onsubmit="this.querySelector('input[name=alias]').value = (document.getElementById('create-link-alias')?.value || '').trim();">
            @csrf
            <input type="hidden" name="type" value="biolink">
            <input type="hidden" name="start_mode" value="ai">
            <input type="hidden" name="alias" value="">
            <button type="submit"
                    class="group h-full w-full text-left flex flex-col relative overflow-hidden glass rounded-3xl p-6 border border-white/10 bg-white/[0.03] hover:border-fuchsia-500/30 hover:bg-fuchsia-500/[0.06] transition-all">
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div class="w-12 h-12 rounded-2xl bg-fuchsia-500/15 border border-fuchsia-400/20 text-fuchsia-300 flex items-center justify-center">
                        <i class="fas fa-wand-magic-sparkles text-xl"></i>
                    </div>
                    <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-fuchsia-200 bg-fuchsia-500/15 border border-fuchsia-400/20 rounded-full px-2.5 py-0.5">
                        <i class="fas fa-bolt text-[9px]"></i> AI Powered
                    </span>
                </div>
                <div class="flex-1">
                    <div class="text-lg font-bold text-white">Build with AI</div>
                    <div class="text-sm text-white/60 mt-1">Describe your page and AI assembles it. Uses AI credits.</div>
                </div>
                <div class="mt-4 flex items-center gap-2 text-fuchsia-200 font-medium text-sm">
                    Describe it
                    <span class="w-8 h-8 rounded-full bg-fuchsia-500/15 border border-fuchsia-400/20 flex items-center justify-center group-hover:translate-x-0.5 transition-all">
                        <i class="fas fa-arrow-right text-xs"></i>
                    </span>
                </div>
            </button>
        </form>
        @else
        {{-- AI BUILDER teaser: engine off / unavailable — kept visible so users
             discover it and get a path to enable (admins) or upgrade (everyone). --}}
        @php
            $aiTeaserHref = !empty($aiBuilderAdminCanEnable)
                ? route('admin.ai-engine.edit')
                : route('user.upgrade');
            $aiTeaserCta = !empty($aiBuilderAdminCanEnable) ? 'Enable AI' : 'Upgrade';
        @endphp
        <a href="{{ $aiTeaserHref }}"
           class="group h-full flex flex-col relative overflow-hidden glass rounded-3xl p-6 border border-white/10 bg-white/[0.03] hover:border-fuchsia-500/30 hover:bg-fuchsia-500/[0.06] transition-all">
            <div class="flex items-start justify-between gap-3 mb-4">
                <div class="w-12 h-12 rounded-2xl bg-white/5 border border-white/10 text-white/40 flex items-center justify-center">
                    <i class="fas fa-wand-magic-sparkles text-xl"></i>
                </div>
                <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-white/50 bg-white/5 border border-white/10 rounded-full px-2.5 py-0.5">
                    <i class="fas fa-lock text-[9px]"></i> {{ !empty($aiBuilderAdminCanEnable) ? 'Currently off' : 'Locked' }}
                </span>
            </div>
            <div class="flex-1">
                <div class="text-lg font-bold text-white/70">Build with AI</div>
                <div class="text-sm text-white/40 mt-1">Describe your page and AI assembles it for you.</div>
            </div>
            <div class="mt-4 flex items-center gap-2 text-fuchsia-200 font-medium text-sm">
                {{ $aiTeaserCta }}
                <span class="w-8 h-8 rounded-full bg-fuchsia-500/15 border border-fuchsia-400/20 flex items-center justify-center group-hover:translate-x-0.5 transition-all">
                    <i class="fas fa-arrow-right text-xs"></i>
                </span>
            </div>
        </a>
        @endif
    </div>

    @php
        $linkCategories = \App\Modules\User\Support\LinkTypeCategories::categories();
        $cardIndex = 0;
        $linkFilterCats = [];
        $linkTypeMeta   = [];
        foreach ($linkCategories as $catIdx => $cat) {
            $linkFilterCats['cat-' . $catIdx] = array_map(
                static fn (array $t): array => ['label' => $t['label'], 'desc' => $t['desc']],
                $cat['types']
            );
            foreach ($cat['types'] as $t) {
                $linkTypeMeta[$t['value']] = [
                    'label' => $t['label'],
                    'icon'  => $t['icon'],
                    'badge' => $t['badge'],
                ];
            }
        }
    @endphp

    <form method="POST" action="{{ route('user.links.choose-type') }}"
          x-data="linkTypePicker({ type: '{{ old('type', $lastType ?? '') }}', cats: {{ \Illuminate\Support\Js::from($linkFilterCats) }}, typeMeta: {{ \Illuminate\Support\Js::from($linkTypeMeta) }} })"
          x-init="window.__voiceSurface = { name: 'create_link' }"
          @alias-verdict="aliasBlocked = $event.detail.blocked"
          @submit="guardAliasSubmit($event)"
          @voice-action.window="
              if ($event.detail && $event.detail.type === 'select_link_type' && $event.detail.link_type) {
                  type = $event.detail.link_type;
                  // requestSubmit() (not submit()) so the alias guard and native
                  // validation still run on the voice-driven path.
                  $nextTick(() => ($el.requestSubmit ? $el.requestSubmit() : $el.submit()));
              }
          ">
        @csrf

        {{-- MANUAL FALLBACK: quieter neutral surface to pick a link type. --}}
        <div class="glass rounded-2xl p-6 mb-6">

            {{-- SHARED LINK ADDRESS: applies to every link type, so it sits at
                 the top of the picker as one compact input. Optional — blank
                 auto-generates one. Registers the aliasChecker component once,
                 then mounts it on the field (live availability + error + prefill
                 all preserved). --}}
            @include('user.links.partials.alias-checker')
            <div class="mb-6" x-data="aliasChecker('{{ route('user.links.check-alias') }}')" x-init="init()">
                <label for="create-link-alias" class="block text-sm font-medium text-white mb-1.5">
                    Your link address <span class="font-normal text-white/40">— optional</span>
                </label>
                <div class="flex items-stretch rounded-xl bg-white/5 border overflow-hidden transition-colors"
                     :class="state === 'available' ? 'border-emerald-500/40 focus-within:ring-2 focus-within:ring-emerald-500/40'
                         : (isError ? 'border-red-500/40 focus-within:ring-2 focus-within:ring-red-500/40'
                         : 'border-white/15 focus-within:ring-2 focus-within:ring-blue-500/40')">
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
                           class="flex-1 bg-transparent px-3 py-2.5 text-sm text-white placeholder-white/25 outline-none min-w-0">
                    <span class="flex items-center px-3" x-show="state && state !== 'empty'" x-cloak>
                        <i x-show="state === 'checking'" class="fas fa-spinner fa-spin text-white/40 text-sm"></i>
                        <i x-show="state === 'available'" class="fas fa-circle-check text-emerald-400 text-sm"></i>
                        <i x-show="isError" class="fas fa-circle-xmark text-red-400 text-sm"></i>
                    </span>
                </div>
                @error('alias') <p class="text-red-400 text-sm mt-1.5">{{ $message }}</p> @enderror
                <p id="create-link-alias-status" aria-live="polite"
                   x-show="message && state && state !== 'empty'" x-cloak
                   class="text-sm mt-1.5"
                   :class="state === 'available' ? 'text-emerald-400' : (isError ? 'text-red-400' : 'text-white/40')"
                   x-text="message"></p>
                <p class="text-xs text-white/40 mt-1.5">Works for any link type. Letters, numbers, dashes &amp; underscores only.@if(!empty($aliasUpgradeHint)) <a href="{{ route('user.plans.index') }}" class="text-blue-400 hover:underline">Upgrade for more.</a>@endif</p>
            </div>

            <h2 class="text-base font-semibold text-white mb-4">Or pick a link type</h2>

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
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
                            @foreach($category['types'] as $opt)
                                <label id="lt-card-{{ $opt['value'] }}" class="relative cursor-pointer block group h-full lt-card-reveal"
                                       x-show="matches({{ \Illuminate\Support\Js::from($opt['label']) }}, {{ \Illuminate\Support\Js::from($opt['desc']) }}, 'cat-{{ $catIdx }}')"
                                       style="animation-delay: {{ min($cardIndex++ * 35, 420) }}ms">
                                    <input type="radio" name="type" value="{{ $opt['value'] }}" x-model="type" class="sr-only peer">
                                    <div class="h-full border rounded-xl p-3 flex items-start gap-3 transition-all duration-200 motion-safe:group-hover:-translate-y-0.5"
                                         :class="type === '{{ $opt['value'] }}'
                                            ? 'border-blue-500 bg-blue-500/10 ring-2 ring-blue-500/30 shadow-lg shadow-blue-500/10'
                                            : 'border-white/10 hover:border-white/20 hover:bg-white/[0.04]'">
                                        <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0 {{ $opt['badge'] }}">
                                            <i class="fas {{ $opt['icon'] }} text-sm"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center justify-between gap-2">
                                                <div class="text-sm font-semibold text-white truncate">{{ $opt['label'] }}</div>
                                                <span class="w-4 h-4 rounded-full border flex items-center justify-center flex-shrink-0 transition-all"
                                                      :class="type === '{{ $opt['value'] }}'
                                                        ? 'border-blue-400 bg-blue-500'
                                                        : 'border-white/20'">
                                                    <i class="fas fa-check text-[8px] text-white transition-opacity"
                                                       :class="type === '{{ $opt['value'] }}' ? 'opacity-100' : 'opacity-0'"></i>
                                                </span>
                                            </div>
                                            <div class="text-xs text-white/50 leading-snug mt-0.5 line-clamp-1">{{ $opt['desc'] }}</div>
                                        </div>
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

            {{-- Sticky action bar: surfaces the current selection and keeps the
                 Continue action in view while the user browses the list.
                 Continue is disabled (real `disabled`, so it can't submit and is
                 announced as such) until a link type is selected; the alias guard
                 and server-side `type` validation remain as additional gates. --}}
            <div class="sticky bottom-0 z-20 -mx-6 -mb-6 mt-6 px-6 py-4 rounded-b-2xl border-t border-white/10"
                 style="background: var(--bg-body);">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0 flex items-center gap-2.5">
                        <template x-if="type">
                            <span class="flex items-center gap-2.5 min-w-0">
                                <span class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0" :class="selectedBadge()">
                                    <i class="fas text-sm" :class="selectedIcon()"></i>
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-[10px] uppercase tracking-wide text-white/40 leading-none">Selected</span>
                                    <span class="block text-sm font-semibold text-white truncate" x-text="selectedLabel()"></span>
                                </span>
                            </span>
                        </template>
                        <template x-if="!type">
                            <span class="text-sm text-white/40">Pick a link type to continue</span>
                        </template>
                    </div>
                    <div class="flex items-center gap-3 flex-shrink-0">
                        <a href="{{ route('user.links.index') }}" class="px-4 py-2.5 text-sm text-white/40 hover:text-white hover:bg-white/5 rounded-xl transition-all">Cancel</a>
                        <button type="submit" :disabled="!type"
                                :class="type
                                    ? 'bg-blue-600 hover:bg-blue-700 text-white hover:shadow-lg hover:shadow-blue-500/20 cursor-pointer'
                                    : 'bg-white/5 text-white/30 cursor-not-allowed'"
                                class="px-6 py-2.5 rounded-xl text-sm font-medium transition-all">
                            Continue <i class="fas fa-arrow-right ml-1.5 text-xs"></i>
                        </button>
                    </div>
                </div>
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

            // Manual card filter (search box + category tabs over the grid).
            search: '',
            activeCategory: 'all',
            cats: config.cats || {},

            // value => { label, icon, badge } for the chosen type, used to mirror
            // the current selection in the sticky action bar.
            typeMeta: config.typeMeta || {},

            selectedLabel: function () { return (this.typeMeta[this.type] || {}).label || ''; },
            selectedIcon: function () { return (this.typeMeta[this.type] || {}).icon || ''; },
            selectedBadge: function () { return (this.typeMeta[this.type] || {}).badge || ''; },

            // Mirrors the nested aliasChecker verdict (via the bubbling
            // `alias-verdict` event) so Continue can be blocked client-side when
            // the typed Custom URL is known taken/invalid/banned.
            aliasBlocked: false,

            // Block submit when the alias is in a known-error state, surfacing
            // the inline message by focusing/scrolling to the field. Format and
            // length are also caught natively by the input's pattern/min/max, so
            // this primarily guards taken/banned aliases the browser can't see.
            guardAliasSubmit: function (e) {
                if (!this.aliasBlocked) { return; }
                if (e && typeof e.preventDefault === 'function') { e.preventDefault(); }
                var el = document.getElementById('create-link-alias');
                if (!el) { return; }
                try { el.focus(); } catch (err) {}
                var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                el.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'center' });
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
        };
    });
});
</script>
@endpush
