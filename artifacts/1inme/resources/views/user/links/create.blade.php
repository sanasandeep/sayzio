@extends('user.layouts.app')
@section('title', 'Create Link')

@section('content')
@php
    $aliasLimits = $aliasLimits ?? ['min' => 3, 'max' => 50];
    $domainHost  = $domainHost ?? request()->getHost();
@endphp
<div class="max-w-3xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('user.links.index') }}" class="cl-back transition-colors"><i class="fas fa-arrow-left"></i></a>
        <div><p class="cl-step">Step 1 of 2</p><h1 class="text-2xl font-bold" style="color: var(--text-primary);">What would you like to create?</h1><p class="cl-subtitle">Choose a type. You’ll add the details next.</p></div>
    </div>

    <style>
[x-cloak]{display:none!important}.cl-scope{--cl-brand:linear-gradient(135deg,#3E3AE0,#3D6BFF)}.cl-back,.cl-step{color:var(--text-faint)}.cl-step{font-size:12px;margin-bottom:6px}.cl-subtitle{font-size:14px;color:var(--text-dimmed);margin-top:8px}.cl-card{background:var(--bg-card);border:1px solid var(--border-glass);border-radius:20px}.cl-primary-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.cl-primary{display:flex;flex-direction:column;align-items:flex-start;text-align:left;padding:20px;border:1px solid var(--border-glass);border-radius:14px;gap:8px;color:var(--text-primary);background:transparent}.cl-primary strong{font-size:16px;margin-top:8px}.cl-primary>span:last-child{font-size:13px;line-height:1.5;color:var(--text-dimmed)}.cl-primary:hover,.cl-tile:hover{background:var(--bg-glass-hover)}.cl-primary:focus-visible,.cl-tile:focus-visible,a:focus-visible,summary:focus-visible{outline:2px solid var(--accent);outline-offset:3px}.cl-ico{display:grid;place-items:center;flex:none;background:var(--bg-glass-hover);color:var(--text-dimmed);border-radius:10px}.cl-ico-lg{width:40px;height:40px}.cl-ico-md{width:34px;height:34px}.cl-ico-sm{width:30px;height:30px}.cl-ico--on,.cl-tile:hover .cl-ico{background:var(--cl-brand);color:white}.cl-tile--on{border-color:var(--accent)!important;background:color-mix(in srgb,var(--accent) 7%,transparent)}.cl-options{margin-top:24px;padding-top:24px;border-top:1px solid var(--border-glass)}.cl-options-title{font-size:16px;font-weight:650;color:var(--text-primary);margin-bottom:16px}.cl-field{border:1px solid var(--border-glass);border-radius:10px}.cl-input{background:transparent;color:var(--text-primary);outline:none}.cl-input::placeholder{color:var(--text-faint)}.cl-field:focus-within{border-color:var(--accent)}.cl-tile{display:flex;gap:10px;padding:12px;border:1px solid transparent;border-radius:12px;min-height:76px}.cl-tile-name{font-size:14px;font-weight:600;color:var(--text-primary)}.cl-tile-desc{font-size:12px;color:var(--text-dimmed);line-height:1.5}.cl-radio{width:16px;height:16px;border:1px solid var(--border-glass);border-radius:50%;display:grid;place-items:center;flex:none}.cl-radio--on{background:var(--accent);border-color:var(--accent)}.cl-eyebrow{font-size:11px;color:var(--text-faint);letter-spacing:.08em;text-transform:uppercase}.cl-bar{border-top:1px solid var(--border-glass)}.cl-continue{background:var(--accent);color:white;border-radius:10px;font-size:14px;font-weight:600}.cl-continue:disabled{background:var(--bg-glass-hover);color:var(--text-faint);cursor:not-allowed}.cl-cancel{color:var(--text-dimmed)}.cl-help{text-align:center;font-size:13px;color:var(--text-dimmed);margin-top:20px}.cl-help a{color:var(--accent);font-weight:600}.cl-bulk summary{cursor:pointer;font-size:13px;color:var(--text-dimmed);margin-bottom:16px}.cl-pick{display:flex;gap:12px;align-items:center;padding:12px;border:1px solid var(--border-glass);border-radius:12px}.cl-go{color:var(--text-faint)}@media(max-width:480px){.cl-primary{padding:14px}.cl-primary strong{font-size:14px}.cl-primary>span:last-child{font-size:12px}.cl-bar .cl-cancel{display:none}}
    </style>

    <div class="cl-scope">

    @php
        $linkCategories = \App\Modules\User\Support\LinkTypeCategories::categories();
        $linkFilterCats = [];
        $linkTypeMeta   = [];
        foreach ($linkCategories as $catIdx => $cat) {
            $linkFilterCats['cat-' . $catIdx] = array_map(
                static fn (array $t): array => ['value' => $t['value'], 'label' => $t['label'], 'desc' => $t['desc']],
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
                    'business' => in_array($t['value'], ['restaurant_menu','store_menu','service_booking','salon_spa','contact_directory','real_estate','education'], true),
                ];
            }
        }
    @endphp

    <form method="POST" action="{{ route('user.links.choose-type') }}"
          x-data="linkTypePicker({ type: {{ \Illuminate\Support\Js::from(old('type', $lastType ?? '')) }}, cats: {{ \Illuminate\Support\Js::from($linkFilterCats) }}, typeMeta: {{ \Illuminate\Support\Js::from($linkTypeMeta) }} })"
          x-init="window.__voiceSurface = { name: 'create_link' }"
          @alias-verdict="aliasBlocked = $event.detail.blocked"
          @submit="guardAliasSubmit($event)"
          @voice-action.window="
              if ($event.detail && $event.detail.type === 'select_link_type' && $event.detail.link_type) {
                  type = $event.detail.link_type; group = (typeMeta[type] || {}).business ? 'business' : 'more';
                  /* requestSubmit() (not submit()) so the alias guard and native
                     validation still run on the voice-driven path. */
                  $nextTick(() => ($el.requestSubmit ? $el.requestSubmit() : $el.submit()));
              }
          ">
        @csrf
        <input type="hidden" name="type" :value="type" value="{{ old('type', $lastType ?? '') }}">

        {{-- MANUAL PICKER --}}
        <div class="cl-card p-6 mb-6">

            <input type="hidden" name="alias" id="create-link-alias" value="{{ old('alias', $prefillAlias ?? '') }}">
            <input type="hidden" name="domain_id" value="{{ old('domain_id', $defaultDomainId ?? '') }}">
            @error('alias') <p role="alert" style="color:#ef4444;">{{ $message }} <a href="{{ route('user.links.create') }}">Clear custom address</a></p> @enderror
            <div class="cl-primary-grid" role="group" aria-label="What would you like to create?">
                <button type="button" class="cl-primary" :class="type === 'url' ? 'cl-tile--on' : ''" @click="pickPrimary('url')"><span class="cl-ico cl-ico-lg"><i class="fas fa-link"></i></span><strong>Short link</strong><span>Shorten an existing URL.</span></button>
                <button type="button" class="cl-primary" :class="type === 'biolink' ? 'cl-tile--on' : ''" @click="pickPrimary('biolink')"><span class="cl-ico cl-ico-lg"><i class="fas fa-id-card"></i></span><strong>Link in Bio</strong><span>Your links and content on one page.</span></button>
                <button type="button" class="cl-primary" :class="group === 'business' ? 'cl-tile--on' : ''" @click="openGroup('business')"><span class="cl-ico cl-ico-lg"><i class="fas fa-store"></i></span><strong>Business page</strong><span>Menus, services, contacts and catalogs.</span></button>
                <button type="button" class="cl-primary" :class="group === 'more' ? 'cl-tile--on' : ''" @click="openGroup('more')"><span class="cl-ico cl-ico-lg"><i class="fas fa-shapes"></i></span><strong>More options</strong><span>Files, events and other link types.</span></button>
            </div>
            <div x-show="group" x-cloak class="cl-options">
                <h2 class="cl-options-title" x-text="group === 'business' ? 'What does your business need?' : 'Explore more link types'"></h2>
                <div x-show="group === 'more'" class="cl-field mb-4">
                    <label for="link-type-search" class="sr-only">Search link types</label>
                    <input id="link-type-search" x-model="search" placeholder="Search more options…" class="cl-input w-full px-4 py-3" @keydown.escape="search = ''">
                </div>
            <div class="space-y-7">
                @foreach($linkCategories as $catIdx => $category)
                    <section x-show="categoryHasMatch('cat-{{ $catIdx }}')">
                        <h3 class="cl-eyebrow mb-3">{{ $category['label'] }}</h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-1">
                            @foreach($category['types'] as $opt)
                                <label id="lt-card-{{ $opt['value'] }}" class="relative cursor-pointer block group h-full" @click="type = '{{ $opt['value'] }}'" @keydown.enter.prevent="type = '{{ $opt['value'] }}'" @keydown.space.prevent="type = '{{ $opt['value'] }}'" tabindex="0" role="radio" :aria-checked="type === '{{ $opt['value'] }}'"
                                       x-show="matches({{ \Illuminate\Support\Js::from($opt['label']) }}, {{ \Illuminate\Support\Js::from($opt['desc']) }}, 'cat-{{ $catIdx }}', '{{ $opt['value'] }}')"
                                       >
                                    <input type="radio" name="type" value="{{ $opt['value'] }}" x-model="type" :disabled="type !== '{{ $opt['value'] }}'" class="sr-only peer">
                                    <div class="cl-tile" :class="type === '{{ $opt['value'] }}' ? 'cl-tile--on' : ''">
                                        <span class="cl-ico cl-ico-md" :class="type === '{{ $opt['value'] }}' ? 'cl-ico--on' : ''">
                                            <i class="fas {{ $opt['icon'] }}"></i>
                                        </span>
                                        <span class="flex-1 min-w-0">
                                            <span class="flex items-center justify-between gap-2">
                                                <span class="cl-tile-name ">{{ $opt['label'] }}</span>
                                                <span class="cl-radio" :class="type === '{{ $opt['value'] }}' ? 'cl-radio--on' : ''">
                                                    <i class="fas fa-check text-[8px] text-white transition-opacity"
                                                       :class="type === '{{ $opt['value'] }}' ? 'opacity-100' : 'opacity-0'"></i>
                                                </span>
                                            </span>
                                            <span class="cl-tile-desc block mt-0.5 ">{{ $opt['desc'] }}</span>
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
            </div>
            @error('type') <p class="text-sm mt-2" style="color:#ef4444;">{{ $message }}</p> @enderror

            {{-- Sticky action bar: surfaces the current selection and keeps the
                 Continue action in view while the user browses the list.
                 Continue is disabled (real `disabled`, so it can't submit and is
                 announced as such) until a link type is selected; the alias guard
                 and server-side `type` validation remain as additional gates. --}}
            <div class="cl-bar -mx-6 -mb-6 mt-6 px-6 py-4 rounded-b-2xl">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0 flex items-center gap-2.5" aria-live="polite">
                        <template x-if="type">
                            <span class="flex items-center gap-2.5 min-w-0">
                                <span class="cl-ico cl-ico-sm cl-ico--on">
                                    <i class="fas" :class="selectedIcon()"></i>
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-[10px] uppercase tracking-wider leading-none" style="color: var(--text-faint);">Selected</span>
                                    <span class="block text-sm font-semibold " style="color: var(--text-primary);" x-text="selectedLabel()"></span>
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

    <p class="cl-help">Not sure where to start? <a href="{{ route('user.links.wizard') }}">Help me choose</a></p>
    <details class="mt-8 cl-bulk"><summary>Bulk tools &amp; advanced</summary>
        <h2 class="cl-eyebrow mb-3 px-1">Bulk &amp; advanced</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <a href="{{ route('user.links.url.bulk') }}" class="cl-pick group">
                <span class="cl-ico cl-ico-md"><i class="fas fa-layer-group"></i></span>
                <span class="flex-1 min-w-0">
                    <span class="cl-tile-name block ">Bulk create short links</span>
                    <span class="cl-tile-desc block mt-0.5">Paste a list or upload a CSV.</span>
                </span>
                <i class="fas fa-arrow-right cl-go"></i>
            </a>

            <a href="{{ route('user.links.biolink.bulk') }}" class="cl-pick group">
                <span class="cl-ico cl-ico-md"><i class="fas fa-table"></i></span>
                <span class="flex-1 min-w-0">
                    <span class="cl-tile-name block ">Bulk create Link in Bio pages</span>
                    <span class="cl-tile-desc block mt-0.5">Mail-merge a master page from a sheet.</span>
                </span>
                <i class="fas fa-arrow-right cl-go"></i>
            </a>

            <a href="{{ route('user.links.teardown.create') }}" class="cl-pick group">
                <span class="cl-ico cl-ico-md"><i class="fas fa-magnifying-glass-chart"></i></span>
                <span class="flex-1 min-w-0">
                    <span class="cl-tile-name block ">Competitor Biolink Teardown</span>
                    <span class="cl-tile-desc block mt-0.5 ">Paste a competitor URL, get an AI-scored teardown, build a better version.</span>
                </span>
                <i class="fas fa-arrow-right cl-go"></i>
            </a>
        </div>
    </details>

    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('alpine:init', function () {
    window.Alpine.data('linkTypePicker', function (config) {
        return {
            type: config.type || '',

            // Search appears only within the secondary type group.
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

            group: '',
            init: function () {
                if (this.type && this.type !== 'url' && this.type !== 'biolink') {
                    this.group = (this.typeMeta[this.type] || {}).business ? 'business' : 'more';
                }
            },
            pickPrimary: function (value) { this.type = value; this.group = ''; this.search = ''; },
            openGroup: function (value) {
                this.group = value; this.search = '';
                if (!this.allowed(this.type)) { this.type = ''; }
            },
            allowed: function (value) {
                if (value === 'url' || value === 'biolink') { return false; }
                var business = !!(this.typeMeta[value] || {}).business;
                return this.group === 'business' ? business : this.group === 'more' && !business;
            },
            matches: function (label, desc, key, value) {
                if (!this.allowed(value)) { return false; }
                return (label + ' ' + desc).toLowerCase().indexOf(this.search.trim().toLowerCase()) !== -1;
            },
            categoryHasMatch: function (key) {
                var self = this;
                return (this.cats[key] || []).some(function (t) { return self.matches(t.label, t.desc, key, t.value); });
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
