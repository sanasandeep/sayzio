@extends('user.layouts.app')
@section('title', 'Choose a Template')

@section('content')
{{-- Inline style for the search-match highlight mark. Kept inline (rather
     than a Tailwind class string) so the highlight() helper above can emit
     a single-class <mark> tag without needing quoted attributes inside the
     double-quoted x-data expression. Mirrors the violet accent used for
     focused/selected states elsewhere on the picker. --}}
<style>
    mark.tpl-mark {
        background-color: rgba(92,131,255, 0.30);
        color: #fff;
        border-radius: 2px;
        padding: 0 2px;
    }
</style>
{{-- The mini-preview blueprint typography + shimmer CSS now lives in the
     shared template-preview-blueprint partial (emitted once per response). --}}
@php
    // Lightweight JS mirror of the templates so Alpine can compute how many
    // pass the active category + search filter without having to query the
    // DOM. Keeps the per-card x-show logic the source of truth — this list
    // just mirrors the same fields used in those expressions.
    $filterIndex = $pageTemplates->map(fn($t) => [
        'category' => $t->category,
        'text' => strtolower(($t->name ?? '') . ' ' . ($t->description ?? '') . ' ' . ucfirst($t->category ?? '')),
    ])->values();
@endphp
<div class="max-w-6xl mx-auto" x-data="{
        category: 'all',
        search: '',
        preview: { open: false, url: '', name: '' },
        previewDevice: 'phone',
        previewWidths: { phone: 420, tablet: 768, desktop: 1100 },
        openPreview(url, name) { this.previewDevice = 'phone'; this.preview = { open: true, url: url, name: name }; },
        closePreview() { this.preview = { open: false, url: '', name: '' }; },
        templates: {{ \Illuminate\Support\Js::from($filterIndex) }},
        /* Background chunk loader: the server renders only the first
           {{ $chunkSize }} cards; the rest of the library streams in via the
           chunk endpoint and is appended to the grid. Alpine auto-initializes
           the injected cards (they sit inside this x-data tree), so their
           x-show filter wiring works exactly like the server-rendered ones. */
        nextOffset: {{ $pageTemplates->count() > $initialTemplates->count() ? $initialTemplates->count() : 'null' }},
        allLoaded: {{ $pageTemplates->count() > $initialTemplates->count() ? 'false' : 'true' }},
        chunkUrl: {{ \Illuminate\Support\Js::from(route('user.links.templates.chunk', $link) . ($persona ? ('?persona=' . urlencode($persona)) : '')) }},
        async loadRest() {
            while (this.nextOffset !== null) {
                const sep = this.chunkUrl.includes('?') ? '&' : '?';
                let data;
                try {
                    const res = await fetch(this.chunkUrl + sep + 'offset=' + this.nextOffset, { headers: { 'Accept': 'application/json' } });
                    if (!res.ok) break;
                    data = await res.json();
                } catch (e) { break; }
                if (data.html) this.$refs.grid.insertAdjacentHTML('beforeend', data.html);
                this.nextOffset = data.next_offset;
            }
            this.allLoaded = true;
        },
        init() { if (this.nextOffset !== null) this.loadRest(); },
        /* Single source of truth for whether a template passes the active
           category + search filter. Used by both the per-card x-show and the
           visibleCount accessor below so the two can never drift apart.
           `text` should already be lowercased by the server-side index. */
        matches(category, text) {
            return (this.category === 'all' || this.category === category) &&
                (this.search === '' || text.includes(this.search.toLowerCase()));
        },
        get visibleCount() {
            return this.templates.filter(t => this.matches(t.category, t.text)).length;
        },
        /* Wrap occurrences of the active search term inside `text` in a
           <mark> tag so the picker shows *why* a card matched. HTML-escapes
           surrounding text and the matched slice to keep admin-authored
           names/descriptions safe from injection. Falls back to the plain
           (escaped) string when the search box is empty. The wrapping mark
           uses a single class (.tpl-mark, defined in the <style> block
           below) so we don't need quoted attributes inside this
           double-quoted x-data expression. */
        highlight(text) {
            const raw = text == null ? '' : String(text);
            const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            const q = this.search.trim();
            if (q === '') return esc(raw);
            const pattern = q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const re = new RegExp(pattern, 'gi');
            let out = '';
            let last = 0;
            let m;
            while ((m = re.exec(raw)) !== null) {
                out += esc(raw.slice(last, m.index));
                out += '<mark class=tpl-mark>' + esc(m[0]) + '</mark>';
                last = m.index + m[0].length;
                if (m[0].length === 0) re.lastIndex++;
            }
            out += esc(raw.slice(last));
            return out;
        }
    }">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-white">Choose a starting template</h1>
            <p class="text-sm text-white/40 mt-1">Pick a curated preset to skip the blank page, or start from scratch.</p>
            @if(!empty($hasRecommended))
                <p class="text-xs text-blue-300 mt-2"><i class="fas fa-sparkles mr-1"></i>Recommended for {{ $personaLabel }} appear first.</p>
            @endif
        </div>
        <a href="{{ route('user.links.blocks.editor', $link) }}" class="px-4 py-2 text-sm text-white/60 hover:text-white border border-white/10 rounded-xl hover:bg-white/5 transition">
            Skip, start from scratch
        </a>
    </div>

    @if(session('error'))
        <div class="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm">{{ session('error') }}</div>
    @endif

    @php
        $cats = ['all' => 'All'] + \App\Modules\Admin\Models\PageTemplate::categories();
        $usedCats = $pageTemplates->pluck('category')->unique()->all();
        // Computed ONCE for the whole page: with ~400 template cards, running
        // this ->exists() inside the card loop meant 400 identical round-trips
        // to the (distant) database and a page render measured in minutes.
        $hasBlocks = $link->biolinkBlocks()->exists();
    @endphp

    @if(!$pageTemplates->isEmpty())
    <div class="flex items-center gap-3 mb-2">
        <div class="flex-1">
            <input type="text" x-model="search" placeholder="Search templates…" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-white/20">
        </div>
    </div>
    @endif

    {{-- Live "Showing X of Y" counter sitting right under the search input.
         Shown whenever a filter is active (non-empty search or a category
         other than 'all') so the default view stays clean but users always
         see a hard count once they're filtering — including the "0 of N"
         state, which complements (rather than duplicates) the
         filter-empty panel's reset CTA below. Only renders when there's
         at least one seeded template. --}}
    @if(!$pageTemplates->isEmpty())
        <p x-show="search !== '' || category !== 'all'" x-cloak class="text-xs text-white/40 mb-3 px-1">
            Showing <span class="text-white/70 font-medium" x-text="visibleCount"></span>
            of {{ $pageTemplates->count() }} {{ \Illuminate\Support\Str::plural('template', $pageTemplates->count()) }}
            <button type="button"
                    @click="search = ''; category = 'all'"
                    class="ml-2 text-white/40 hover:text-white/80 underline-offset-2 hover:underline transition">
                Clear
            </button>
        </p>
    @endif

    {{-- Category filter chips: hidden entirely when there are no templates
         so the empty state isn't preceded by a lonely "All" chip. --}}
    @if(!$pageTemplates->isEmpty())
    <div class="flex items-center gap-1 mb-3 overflow-x-auto pb-2">
        @foreach($cats as $key => $label)
            @if($key === 'all' || in_array($key, $usedCats, true))
            <button @click="category = '{{ $key }}'" :class="category === '{{ $key }}' ? 'bg-blue-600 text-white' : 'text-white/50 hover:text-white bg-white/5'" class="px-3 py-1.5 text-xs font-semibold rounded-lg transition flex-shrink-0">
                {{ $label }}
            </button>
            @endif
        @endforeach
    </div>
    @endif

    @if($pageTemplates->isEmpty())
        <div class="glass rounded-2xl border border-white/10 p-10 sm:p-14 text-center max-w-xl mx-auto">
            <div class="relative w-20 h-20 mx-auto mb-5">
                <div class="absolute inset-0 rounded-2xl blur-xl opacity-60" style="background: linear-gradient(135deg, rgba(61,107,255,0.55), rgba(236,72,153,0.35));"></div>
                <div class="relative w-20 h-20 rounded-2xl flex items-center justify-center border border-white/10"
                     style="background: linear-gradient(135deg, rgba(61,107,255,0.22), rgba(92,131,255,0.10));">
                    <i class="fas fa-layer-group text-3xl text-blue-300"></i>
                </div>
            </div>
            <h3 class="text-lg font-semibold text-white mb-2">No templates yet</h3>
            <p class="text-sm text-white/55 mb-6 leading-relaxed">
                Page templates are ready-made layouts (like a "Creator profile" or "Product launch") that drop a complete set of blocks onto your Link in Bio so you don't have to start from a blank page.
                <br class="hidden sm:block">
                Once some are added you'll see them here. For now, jump into the editor and design yours from scratch.
            </p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-2">
                <a href="{{ route('user.links.blocks.editor', $link) }}"
                   class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold text-white transition shadow-lg shadow-blue-900/30"
                   style="background: linear-gradient(135deg, #3d6bff, #5c83ff);">
                    <i class="fas fa-pen-to-square"></i>
                    Open the editor
                </a>
            </div>
        </div>
    @else
        {{-- Filter-empty state: shown when the active search/category combo
             matches zero of the rendered cards. Mirrors the styling of the
             seed-empty state above (glass card, violet accent) but trimmed
             down since this is an inline filter hint, not a first-run
             explainer. --}}
        <div x-show="visibleCount === 0" x-cloak
             class="glass rounded-2xl border border-white/10 p-8 sm:p-10 text-center max-w-lg mx-auto">
            <div class="relative w-14 h-14 mx-auto mb-4">
                <div class="absolute inset-0 rounded-2xl blur-xl opacity-60" style="background: linear-gradient(135deg, rgba(61,107,255,0.55), rgba(236,72,153,0.35));"></div>
                <div class="relative w-14 h-14 rounded-2xl flex items-center justify-center border border-white/10"
                     style="background: linear-gradient(135deg, rgba(61,107,255,0.22), rgba(92,131,255,0.10));">
                    <i class="fas fa-magnifying-glass text-lg text-blue-300"></i>
                </div>
            </div>
            <h3 class="text-base font-semibold text-white mb-1.5">No templates match your filters</h3>
            <p class="text-sm text-white/55 mb-5 leading-relaxed">
                <template x-if="search !== ''">
                    <span>Nothing matches “<span class="text-white/80 font-medium" x-text="search"></span>”<template x-if="category !== 'all'"><span> in this category</span></template>.</span>
                </template>
                <template x-if="search === ''">
                    <span>No templates in this category yet.</span>
                </template>
                <br class="hidden sm:block">
                Try a different search term or clear the filters to see everything.
            </p>
            <button type="button"
                    @click="search = ''; category = 'all'"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold text-white transition shadow-lg shadow-blue-900/30"
                    style="background: linear-gradient(135deg, #3d6bff, #5c83ff);">
                <i class="fas fa-rotate-left"></i>
                Clear filters
            </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4"
             x-ref="grid"
             :data-all-loaded="allLoaded ? '1' : null">
            @foreach($initialTemplates as $tpl)
                @include('user.links.templates._card', [
                    'tpl' => $tpl,
                    'link' => $link,
                    'locked' => $lockedFn($tpl->plan_tier),
                    'hasBlocks' => $hasBlocks,
                ])
            @endforeach
        </div>
        {{-- Subtle loading hint while the rest of the library streams in. --}}
        <div x-show="!allLoaded" x-cloak class="flex items-center justify-center gap-2 py-6 text-xs text-white/40">
            <i class="fas fa-circle-notch fa-spin text-blue-400"></i>
            Loading more templates…
        </div>
    @endif


    {{-- Full public-style preview modal: renders the template's snapshot
         through the real biolink view inside a phone-style frame so the user
         can see exactly how it looks before applying (and overwriting). --}}
    <div x-show="preview.open" x-cloak
         class="fixed inset-0 z-[120] flex items-center justify-center p-4"
         @keydown.escape.window="closePreview()"
         @click.self="closePreview()">
        <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="closePreview()"></div>
        <div class="relative w-full flex flex-col transition-[max-width] duration-300 ease-out"
             :style="{ maxWidth: previewWidths[previewDevice] + 'px', maxHeight: 'calc(100vh - 2rem)' }">
            <div class="flex items-center justify-between mb-3 gap-2">
                <div class="flex items-center gap-2 min-w-0">
                    <i class="fas fa-mobile-screen-button text-blue-400"></i>
                    <h3 class="text-sm font-semibold text-white truncate" x-text="preview.name"></h3>
                    <span class="text-[10px] uppercase tracking-wide text-white/40 shrink-0">Preview</span>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <div class="flex items-center gap-0.5 rounded-lg bg-white/5 p-0.5">
                        <button type="button" @click="previewDevice = 'phone'"
                                :class="previewDevice === 'phone' ? 'bg-blue-500/30 text-white' : 'text-white/45 hover:text-white'"
                                class="px-2 py-1 rounded-md transition" title="Phone width">
                            <i class="fas fa-mobile-screen-button text-xs"></i>
                        </button>
                        <button type="button" @click="previewDevice = 'tablet'"
                                :class="previewDevice === 'tablet' ? 'bg-blue-500/30 text-white' : 'text-white/45 hover:text-white'"
                                class="px-2 py-1 rounded-md transition" title="Tablet width">
                            <i class="fas fa-tablet-screen-button text-xs"></i>
                        </button>
                        <button type="button" @click="previewDevice = 'desktop'"
                                :class="previewDevice === 'desktop' ? 'bg-blue-500/30 text-white' : 'text-white/45 hover:text-white'"
                                class="px-2 py-1 rounded-md transition" title="Desktop width">
                            <i class="fas fa-desktop text-xs"></i>
                        </button>
                    </div>
                    <a :href="preview.url" target="_blank" rel="noopener"
                       class="text-white/50 hover:text-white p-1.5" title="Open in a new tab">
                        <i class="fas fa-up-right-from-square text-xs"></i>
                    </a>
                    <button type="button" @click="closePreview()" class="text-white/50 hover:text-white p-1.5" title="Close">
                        <i class="fas fa-xmark text-base"></i>
                    </button>
                </div>
            </div>
            <div class="flex-1 min-h-0 rounded-3xl border-4 border-black/60 bg-black overflow-hidden shadow-2xl">
                <template x-if="preview.open">
                    <iframe :src="preview.url" :title="preview.name + ' preview'"
                            class="w-full h-full bg-white" style="min-height: 60vh;"></iframe>
                </template>
            </div>
        </div>
    </div>
</div>
@endsection
