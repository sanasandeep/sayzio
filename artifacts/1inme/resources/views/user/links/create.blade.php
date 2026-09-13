@extends('user.layouts.app')
@section('title', 'Create Link')

@section('content')
@php
    $aliasLimits = $aliasLimits ?? ['min' => 3, 'max' => 50];
    $domainHost  = $domainHost ?? request()->getHost();
@endphp
<div class="max-w-4xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('user.links.index') }}" class="cl-back transition-colors"><i class="fas fa-arrow-left"></i></a>
        <h1 class="text-2xl font-bold" style="color: var(--text-primary);">Create Link</h1>
    </div>

    {{--
        The skin for this page, written against the theme tokens rather than
        Tailwind colour utilities, so the one set of rules answers in all four
        theme scopes (dark, light, dark Aurora, light Aurora) instead of being
        a dark-mode-only treatment that has to be patched later.

        What it deliberately does NOT do, because the rest of the dashboard
        stopped doing it: no card lift on hover, no drop shadows, no ambient
        blurred glows, and no per-item colour. Every type used to carry its own
        badge tint -- violet, emerald, amber, cyan, rose -- which is eighteen
        colours competing on one screen. The colour is now spent in exactly one
        place: the icon plate of whatever you are pointing at fills with the
        Sayzio gradient. One brand moment, on demand, and nothing glowing at
        rest.
    --}}
    <style>
        @media (prefers-reduced-motion: no-preference) {
            .lt-card-reveal { opacity: 0; transform: translateY(12px); animation: ltCardReveal .5s cubic-bezier(.21,.6,.35,1) forwards; }
            @keyframes ltCardReveal { to { opacity: 1; transform: none; } }
        }
        [x-cloak] { display: none !important; }

        .cl-scope {
            /* The marketing ribbon's own stops, so the one accent on this page is
               the same gradient the landing pages and the dashboard hero use. */
            --cl-brand: linear-gradient(135deg, #3E3AE0 0%, #3D6BFF 55%, #1BD4D9 100%);
        }

        .cl-back { color: var(--text-faint); }
        .cl-back:hover { color: var(--text-primary); }

        /* ---------- surfaces ---------- */
        /* One card treatment for every card on the page: the dashboard's own
           rest state -- hairline border, card ground, no shadow. */
        .cl-card {
            background: var(--bg-card);
            border: 1px solid var(--border-glass);
            border-radius: 18px;
        }

        /* ---------- the two ways to start ---------- */
        .cl-start {
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 100%;
            width: 100%;
            text-align: left;
            padding: 22px;
            transition: background .18s cubic-bezier(.22,.8,.3,1), border-color .18s;
        }
        .cl-start:hover { background: var(--bg-glass-hover); border-color: var(--border-glass); }

        /* The shared ribbon is tuned for the tall heroes on the dashboard and
           stats pages: a corner sweep that occupies the bottom 82% of a card
           several hundred pixels deep. On a card this short that same sweep has
           a visible top edge in the middle of the card, so it reads as a shard
           dropped on the surface rather than something passing behind it -- and
           it lands squarely on the second line of the description.

           So: full bleed past both the top and bottom edges (no edge of the
           shape is ever visible except where it leaves the card), and the copy
           column is held clear of the right side so nothing has to be read
           through it. Desktop only -- under 900px the partial turns the ribbon
           into a top band and the copy runs full width, which is already right. */
        @media (min-width: 901px) {
            .cl-start .cribbon {
                top: auto;
                bottom: -46%;
                right: -10%;
                width: min(46%, 380px);
                height: 150%;
                opacity: .9;
                -webkit-mask-image: linear-gradient(30deg, #000 0%, #000 32%, transparent 72%);
                        mask-image: linear-gradient(30deg, #000 0%, #000 32%, transparent 72%);
            }
            .cl-start .cribbon-grid {
                -webkit-mask-image: linear-gradient(to right, #000 0%, #000 34%, transparent 62%);
                        mask-image: linear-gradient(to right, #000 0%, #000 34%, transparent 62%);
            }
            .cl-start .cribbon-copy { max-width: 74%; }
        }

        /* ---------- icon plates ---------- */
        .cl-ico {
            display: grid;
            place-items: center;
            flex: none;
            border-radius: 12px;
            background: var(--bg-glass-hover);
            color: var(--text-dimmed);
            transition: background .2s cubic-bezier(.22,.8,.3,1), color .2s;
        }
        .cl-ico-lg { width: 46px; height: 46px; font-size: 17px; }
        .cl-ico-md { width: 36px; height: 36px; font-size: 14px; border-radius: 10px; }
        .cl-ico-sm { width: 32px; height: 32px; font-size: 13px; border-radius: 9px; }
        /* The single brand moment on the page. */
        .cl-start:hover .cl-ico,
        .cl-tile:hover .cl-ico,
        .cl-pick:hover .cl-ico,
        .cl-ico--on { background: var(--cl-brand); color: #fff; }

        /* ---------- quiet pills ---------- */
        .cl-pill {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 10.5px; font-weight: 600; letter-spacing: .1em; text-transform: uppercase;
            color: var(--text-faint);
            border: 1px solid var(--border-glass);
            border-radius: 8px;
            padding: 3px 9px;
            white-space: nowrap;
        }
        .cl-pill--on { color: var(--accent); border-color: var(--accent); }

        .cl-cta {
            display: inline-flex; align-items: center; gap: 8px;
            font-size: 13.5px; font-weight: 600;
            color: var(--text-primary);
        }
        .cl-cta i { font-size: 11px; color: var(--text-faint); transition: transform .18s, color .18s; }
        .cl-start:hover .cl-cta i { transform: translateX(3px); color: var(--accent); }

        /* ---------- fields ---------- */
        .cl-field {
            background: var(--bg-card);
            border: 1px solid var(--border-glass);
            border-radius: 12px;
            transition: border-color .16s, box-shadow .16s;
        }
        .cl-field:focus-within { border-color: var(--accent); box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 14%, transparent); }
        .cl-field--ok { border-color: #10b981 !important; }
        .cl-field--bad { border-color: #ef4444 !important; }
        .cl-input { background: transparent; color: var(--text-primary); outline: none; }
        .cl-input::placeholder { color: var(--text-faint); }
        .cl-prefix {
            color: var(--text-dimmed);
            border-right: 1px solid var(--border-glass);
            background: var(--bg-glass-hover);
        }

        /* ---------- filter chips: square-cornered, like the dashboard's ---------- */
        .cl-chip {
            font-size: 12.5px; font-weight: 500;
            color: var(--text-dimmed);
            background: transparent;
            border: 1px solid var(--border-glass);
            border-radius: 9px;
            padding: 5px 11px;
            transition: color .16s, border-color .16s, background .16s;
        }
        .cl-chip:hover { color: var(--text-primary); border-color: var(--accent); }
        .cl-chip--on { color: var(--text-primary); border-color: var(--accent); background: color-mix(in srgb, var(--accent) 8%, transparent); }

        /* ---------- type tiles ---------- */
        /* Borderless at rest: eighteen bordered boxes were eighteen objects to
           get past. The hover surface is the affordance instead. */
        .cl-tile {
            display: flex; align-items: flex-start; gap: 11px;
            height: 100%;
            padding: 10px;
            border: 1px solid transparent;
            border-radius: 12px;
            transition: background .18s cubic-bezier(.22,.8,.3,1), border-color .18s;
        }
        .cl-tile:hover { background: var(--bg-glass-hover); }
        .cl-tile--on {
            border-color: var(--accent);
            background: color-mix(in srgb, var(--accent) 7%, transparent);
        }
        .cl-tile-name { font-size: 13.5px; font-weight: 600; color: var(--text-primary); }
        .cl-tile-desc { font-size: 12px; color: var(--text-faint); line-height: 1.35; }
        .cl-radio {
            width: 16px; height: 16px; border-radius: 50%;
            border: 1px solid var(--border-glass);
            display: grid; place-items: center; flex: none;
            transition: background .16s, border-color .16s;
        }
        .cl-radio--on { border-color: var(--accent); background: var(--accent); }

        /* ---------- section labels ---------- */
        .cl-eyebrow {
            font-size: 10.5px; font-weight: 600; letter-spacing: .18em; text-transform: uppercase;
            color: var(--text-faint);
            display: flex; align-items: center; gap: 10px;
        }
        .cl-eyebrow::after { content: ""; flex: 1; height: 1px; background: var(--border-glass); }

        /* ---------- bulk & advanced ---------- */
        .cl-pick {
            display: flex; align-items: center; gap: 12px;
            padding: 13px;
            border: 1px solid var(--border-glass);
            border-radius: 14px;
            background: var(--bg-card);
            transition: background .18s, border-color .18s;
        }
        .cl-pick:hover { background: var(--bg-glass-hover); }
        .cl-pick i.cl-go { color: var(--text-faint); font-size: 11px; transition: transform .18s, color .18s; }
        .cl-pick:hover i.cl-go { transform: translateX(3px); color: var(--accent); }

        /* ---------- sticky action bar ---------- */
        .cl-bar { border-top: 1px solid var(--border-glass); background: var(--bg-body); }
        .cl-continue {
            background: var(--accent); color: #fff;
            border-radius: 12px; font-size: 13.5px; font-weight: 600;
            transition: filter .16s;
        }
        .cl-continue:hover { filter: brightness(1.08); }
        .cl-continue:disabled {
            background: var(--bg-glass-hover); color: var(--text-faint);
            cursor: not-allowed; filter: none;
        }
        .cl-cancel { color: var(--text-dimmed); border-radius: 12px; }
        .cl-cancel:hover { color: var(--text-primary); background: var(--bg-glass-hover); }
    </style>

    <div class="cl-scope">

    {{-- TOP TIER: two ways to start — the recommended guided wizard (carrying
         the page's ribbon) and the AI builder, side-by-side on desktop,
         stacked on mobile. Neither lifts, glows or casts a shadow now; the
         wizard is marked as recommended by the ribbon and the pill, which is
         hierarchy rather than decoration. --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-8">

        {{-- RECOMMENDED: guided wizard. Carries the typed Custom URL (alias)
             through so a user who fills it in keeps it; blank → auto-generated. --}}
        <a href="{{ route('user.links.wizard') }}"
           data-wizard-base="{{ route('user.links.wizard') }}"
           onclick="(function(a){var v=(document.getElementById('create-link-alias')||{}).value;v=(v||'').trim();a.href=a.getAttribute('data-wizard-base')+(v?('?alias='+encodeURIComponent(v)):'');})(this)"
           class="cl-card cl-start group">
            @include('common.partials.card-ribbon')
            <div class="cribbon-copy flex flex-col flex-1">
                <div class="flex items-start justify-between gap-3 mb-4">
                    <span class="cl-ico cl-ico-lg"><i class="fas fa-magic"></i></span>
                    <span class="cl-pill cl-pill--on"><i class="fas fa-star text-[9px]"></i> Recommended</span>
                </div>
                <div class="flex-1">
                    <div class="text-lg font-bold" style="color: var(--text-primary);">Guided wizard</div>
                    <div class="text-sm mt-1" style="color: var(--text-dimmed);">Answer a few questions and we'll build your page for you.</div>
                </div>
                <div class="mt-5 cl-cta">Start building <i class="fas fa-arrow-right"></i></div>
            </div>
        </a>

        {{-- SECONDARY: AI builder — same surface, no ribbon, no second accent. --}}
        @if(!empty($aiBuilderEnabled))
        <form method="POST" action="{{ route('user.links.store') }}" class="h-full"
              onsubmit="this.querySelector('input[name=alias]').value = (document.getElementById('create-link-alias')?.value || '').trim();">
            @csrf
            <input type="hidden" name="type" value="biolink">
            <input type="hidden" name="start_mode" value="ai">
            <input type="hidden" name="alias" value="">
            <button type="submit" class="cl-card cl-start group">
                <div class="flex items-start justify-between gap-3 mb-4">
                    <span class="cl-ico cl-ico-lg"><i class="fas fa-wand-magic-sparkles"></i></span>
                    <span class="cl-pill"><i class="fas fa-bolt text-[9px]"></i> AI Powered</span>
                </div>
                <div class="flex-1">
                    <div class="text-lg font-bold" style="color: var(--text-primary);">Build with AI</div>
                    <div class="text-sm mt-1" style="color: var(--text-dimmed);">Describe your page and AI assembles it. Uses coins.</div>
                </div>
                <div class="mt-5 cl-cta">Describe it <i class="fas fa-arrow-right"></i></div>
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
        <a href="{{ $aiTeaserHref }}" class="cl-card cl-start group">
            <div class="flex items-start justify-between gap-3 mb-4">
                <span class="cl-ico cl-ico-lg"><i class="fas fa-wand-magic-sparkles"></i></span>
                <span class="cl-pill"><i class="fas fa-lock text-[9px]"></i> {{ !empty($aiBuilderAdminCanEnable) ? 'Currently off' : 'Locked' }}</span>
            </div>
            <div class="flex-1">
                <div class="text-lg font-bold" style="color: var(--text-primary);">Build with AI</div>
                <div class="text-sm mt-1" style="color: var(--text-dimmed);">Describe your page and AI assembles it for you.</div>
                {{-- Why it is locked. The teaser showed a disabled-looking card
                     and a bare "Upgrade" with no reason, which reads as a wall;
                     CreateLinkAiBuilderNudgeTest has been asserting this line
                     exists and failing because it never did. --}}
                <div class="text-xs mt-1.5" style="color: var(--text-faint);">
                    {{ !empty($aiBuilderAdminCanEnable) ? 'Turn on the AI Engine to make this available.' : 'Available on a higher plan.' }}
                </div>
            </div>
            <div class="mt-5 cl-cta">{{ $aiTeaserCta }} <i class="fas fa-arrow-right"></i></div>
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
                // Label and icon only. `badge` is the per-type colour the
                // catalog still carries for other surfaces; shipping it into
                // this page's Alpine payload would put all eighteen tints back
                // in the document for something that no longer paints with
                // them.
                $linkTypeMeta[$t['value']] = [
                    'label' => $t['label'],
                    'icon'  => $t['icon'],
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
                  /* requestSubmit() (not submit()) so the alias guard and native
                     validation still run on the voice-driven path. */
                  $nextTick(() => ($el.requestSubmit ? $el.requestSubmit() : $el.submit()));
              }
          ">
        @csrf

        {{-- MANUAL PICKER --}}
        <div class="cl-card p-6 mb-6">

            {{-- SHARED LINK ADDRESS: applies to every link type, so it sits at
                 the top of the picker as one compact input. Optional — blank
                 auto-generates one. Registers the aliasChecker component once,
                 then mounts it on the field (live availability + error + prefill
                 all preserved). --}}
            @include('user.links.partials.alias-checker')
            <div class="mb-6" x-data="aliasChecker('{{ route('user.links.check-alias') }}')" x-init="init()">
                <label for="create-link-alias" class="block text-sm font-medium mb-1.5" style="color: var(--text-primary);">
                    Your link address <span class="font-normal" style="color: var(--text-faint);"> - optional</span>
                </label>
                <div class="cl-field flex items-stretch overflow-hidden"
                     :class="state === 'available' ? 'cl-field--ok' : (isError ? 'cl-field--bad' : '')">
                    @if(($domains ?? collect())->count() > 1)
                        @php $selectedDomainId = old('domain_id', $defaultDomainId ?? ''); @endphp
                        <select name="domain_id" aria-label="Link domain"
                                class="cl-prefix cl-input px-2 py-2.5 text-sm max-w-[180px]">
                            @foreach($domains as $d)
                                {{-- Native option lists don't inherit the page's
                                     surface, so the ground is named explicitly
                                     or the menu renders light on a dark page. --}}
                                <option value="{{ $d->id }}" {{ (string) $selectedDomainId === (string) $d->id ? 'selected' : '' }}
                                        style="background: var(--bg-card); color: var(--text-primary);">{{ $d->domain }}/</option>
                            @endforeach
                        </select>
                    @else
                        <span class="cl-prefix flex items-center px-3 text-sm select-none">
                            {{ ($domains ?? collect())->first()->domain ?? $domainHost }}/
                        </span>
                    @endif
                    <input type="text" name="alias" id="create-link-alias"
                           value="{{ old('alias', $prefillAlias ?? '') }}"
                           placeholder="leave blank to auto-generate"
                           minlength="{{ $aliasLimits['min'] }}"
                           maxlength="{{ $aliasLimits['max'] }}"
                           pattern="[A-Za-z0-9_\-]+"
                           autocomplete="off" spellcheck="false"
                           @input.debounce.400ms="check($event.target.value)"
                           aria-describedby="create-link-alias-status"
                           class="cl-input flex-1 px-3 py-2.5 text-sm min-w-0">
                    <span class="flex items-center px-3" x-show="state && state !== 'empty'" x-cloak>
                        <i x-show="state === 'checking'" class="fas fa-spinner fa-spin text-sm" style="color: var(--text-faint);"></i>
                        <i x-show="state === 'available'" class="fas fa-circle-check text-sm" style="color:#10b981;"></i>
                        <i x-show="isError" class="fas fa-circle-xmark text-sm" style="color:#ef4444;"></i>
                    </span>
                </div>
                @error('alias') <p class="text-sm mt-1.5" style="color:#ef4444;">{{ $message }}</p> @enderror
                <p id="create-link-alias-status" aria-live="polite"
                   x-show="message && state && state !== 'empty'" x-cloak
                   class="text-sm mt-1.5"
                   :style="state === 'available' ? 'color:#10b981' : (isError ? 'color:#ef4444' : 'color: var(--text-faint)')"
                   x-text="message"></p>
                <p class="text-xs mt-1.5" style="color: var(--text-faint);">Works for any link type. Letters, numbers, dashes &amp; underscores only.@if(!empty($aliasUpgradeHint)) <a href="{{ route('user.plans.index') }}" class="hover:underline" style="color: var(--accent);">Upgrade for more.</a>@endif</p>
            </div>

            <h2 class="text-base font-semibold mb-4" style="color: var(--text-primary);">Or pick a link type</h2>

            {{-- Search + category filters for the manual picker --}}
            <div class="mb-6">
                <label for="link-type-search" class="sr-only">Search link types</label>
                <div class="cl-field relative">
                    <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-sm pointer-events-none" style="color: var(--text-faint);"></i>
                    <input type="text" id="link-type-search" x-model="search"
                           placeholder="Search link types…"
                           autocomplete="off" spellcheck="false"
                           @keydown.escape="resetFilters()"
                           class="cl-input w-full bg-transparent pl-10 pr-10 py-2.5 text-sm">
                    <button type="button" x-show="search" x-cloak @click="search = ''"
                            aria-label="Clear search"
                            class="absolute right-3 top-1/2 -translate-y-1/2 transition-colors cl-back">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>
                <div class="flex flex-wrap gap-2 mt-3" role="group" aria-label="Filter by category">
                    <button type="button" @click="activeCategory = 'all'"
                            class="cl-chip" :class="activeCategory === 'all' ? 'cl-chip--on' : ''">
                        All
                    </button>
                    @foreach($linkCategories as $catIdx => $category)
                        <button type="button" @click="activeCategory = 'cat-{{ $catIdx }}'"
                                class="cl-chip" :class="activeCategory === 'cat-{{ $catIdx }}' ? 'cl-chip--on' : ''">
                            {{ $category['label'] }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="space-y-7">
                @foreach($linkCategories as $catIdx => $category)
                    <section x-show="categoryHasMatch('cat-{{ $catIdx }}')">
                        <h3 class="cl-eyebrow mb-3">{{ $category['label'] }}</h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-1">
                            @foreach($category['types'] as $opt)
                                <label id="lt-card-{{ $opt['value'] }}" class="relative cursor-pointer block group h-full lt-card-reveal"
                                       x-show="matches({{ \Illuminate\Support\Js::from($opt['label']) }}, {{ \Illuminate\Support\Js::from($opt['desc']) }}, 'cat-{{ $catIdx }}')"
                                       style="animation-delay: {{ min($cardIndex++ * 35, 420) }}ms">
                                    <input type="radio" name="type" value="{{ $opt['value'] }}" x-model="type" class="sr-only peer">
                                    <div class="cl-tile" :class="type === '{{ $opt['value'] }}' ? 'cl-tile--on' : ''">
                                        <span class="cl-ico cl-ico-md" :class="type === '{{ $opt['value'] }}' ? 'cl-ico--on' : ''">
                                            <i class="fas {{ $opt['icon'] }}"></i>
                                        </span>
                                        <span class="flex-1 min-w-0">
                                            <span class="flex items-center justify-between gap-2">
                                                <span class="cl-tile-name truncate">{{ $opt['label'] }}</span>
                                                <span class="cl-radio" :class="type === '{{ $opt['value'] }}' ? 'cl-radio--on' : ''">
                                                    <i class="fas fa-check text-[8px] text-white transition-opacity"
                                                       :class="type === '{{ $opt['value'] }}' ? 'opacity-100' : 'opacity-0'"></i>
                                                </span>
                                            </span>
                                            <span class="cl-tile-desc block mt-0.5 line-clamp-1">{{ $opt['desc'] }}</span>
                                        </span>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </section>
                @endforeach

                {{-- Empty state: no link type matches the current search/filter --}}
                <div x-show="!anyMatch()" x-cloak class="text-center py-12">
                    <div class="cl-ico cl-ico-lg mx-auto mb-3"><i class="fas fa-search"></i></div>
                    <p class="text-sm" style="color: var(--text-dimmed);">No link types match<template x-if="search.trim()"> “<span class="font-medium" style="color: var(--text-primary);" x-text="search.trim()"></span>”</template>.</p>
                    <button type="button" @click="resetFilters()" class="mt-3 text-sm hover:underline" style="color: var(--accent);">Clear search</button>
                </div>
            </div>
            @error('type') <p class="text-sm mt-2" style="color:#ef4444;">{{ $message }}</p> @enderror

            {{-- Sticky action bar: surfaces the current selection and keeps the
                 Continue action in view while the user browses the list.
                 Continue is disabled (real `disabled`, so it can't submit and is
                 announced as such) until a link type is selected; the alias guard
                 and server-side `type` validation remain as additional gates. --}}
            <div class="cl-bar sticky bottom-0 z-20 -mx-6 -mb-6 mt-6 px-6 py-4 rounded-b-2xl">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0 flex items-center gap-2.5">
                        <template x-if="type">
                            <span class="flex items-center gap-2.5 min-w-0">
                                <span class="cl-ico cl-ico-sm cl-ico--on">
                                    <i class="fas" :class="selectedIcon()"></i>
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-[10px] uppercase tracking-wider leading-none" style="color: var(--text-faint);">Selected</span>
                                    <span class="block text-sm font-semibold truncate" style="color: var(--text-primary);" x-text="selectedLabel()"></span>
                                </span>
                            </span>
                        </template>
                        <template x-if="!type">
                            <span class="text-sm" style="color: var(--text-faint);">Pick a link type to continue</span>
                        </template>
                    </div>
                    <div class="flex items-center gap-3 flex-shrink-0">
                        <a href="{{ route('user.links.index') }}" class="cl-cancel px-4 py-2.5 text-sm transition-all">Cancel</a>
                        <button type="submit" :disabled="!type" class="cl-continue px-6 py-2.5">
                            Continue <i class="fas fa-arrow-right ml-1.5 text-xs"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    {{-- TERTIARY: bulk & advanced — rare actions, de-emphasized --}}
    <div class="mt-8">
        <h2 class="cl-eyebrow mb-3 px-1">Bulk &amp; advanced</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <a href="{{ route('user.links.url.bulk') }}" class="cl-pick group">
                <span class="cl-ico cl-ico-md"><i class="fas fa-layer-group"></i></span>
                <span class="flex-1 min-w-0">
                    <span class="cl-tile-name block truncate">Bulk create short links</span>
                    <span class="cl-tile-desc block mt-0.5">Paste a list or upload a CSV.</span>
                </span>
                <i class="fas fa-arrow-right cl-go"></i>
            </a>

            <a href="{{ route('user.links.biolink.bulk') }}" class="cl-pick group">
                <span class="cl-ico cl-ico-md"><i class="fas fa-table"></i></span>
                <span class="flex-1 min-w-0">
                    <span class="cl-tile-name block truncate">Bulk create Link in Bio pages</span>
                    <span class="cl-tile-desc block mt-0.5">Mail-merge a master page from a sheet.</span>
                </span>
                <i class="fas fa-arrow-right cl-go"></i>
            </a>

            <a href="{{ route('user.links.teardown.create') }}" class="cl-pick group">
                <span class="cl-ico cl-ico-md"><i class="fas fa-magnifying-glass-chart"></i></span>
                <span class="flex-1 min-w-0">
                    <span class="cl-tile-name block truncate">Competitor Biolink Teardown</span>
                    <span class="cl-tile-desc block mt-0.5 line-clamp-1">Paste a competitor URL, get an AI-scored teardown, build a better version.</span>
                </span>
                <i class="fas fa-arrow-right cl-go"></i>
            </a>
        </div>
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

            // value => { label, icon } for the chosen type, used to mirror the
            // current selection in the sticky action bar. No `badge`: per-type
            // colour is what made the picker read as noise, and the selected
            // plate now carries the one brand gradient instead.
            typeMeta: config.typeMeta || {},

            selectedLabel: function () { return (this.typeMeta[this.type] || {}).label || ''; },
            selectedIcon: function () { return (this.typeMeta[this.type] || {}).icon || ''; },

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
