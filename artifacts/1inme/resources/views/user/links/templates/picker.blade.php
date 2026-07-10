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

    <div class="flex items-center gap-3 mb-2">
        <div class="flex-1">
            <input type="text" x-model="search" placeholder="Search templates…" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-white/20">
        </div>
    </div>

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

    <div class="flex items-center gap-1 mb-3 overflow-x-auto pb-2">
        @foreach($cats as $key => $label)
            @if($key === 'all' || in_array($key, $usedCats, true))
            <button @click="category = '{{ $key }}'" :class="category === '{{ $key }}' ? 'bg-blue-600 text-white' : 'text-white/50 hover:text-white bg-white/5'" class="px-3 py-1.5 text-xs font-semibold rounded-lg transition flex-shrink-0">
                {{ $label }}
            </button>
            @endif
        @endforeach
    </div>

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
                Page templates are ready-made layouts — like a "Creator profile" or "Product launch" — that drop a complete set of blocks onto your Link in Bio so you don't have to start from a blank page.
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

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($pageTemplates as $tpl)
                @php
                    $locked = $lockedFn($tpl->plan_tier);
                    $summary = $tpl->content_summary ?? [];
                    $topCount = count($summary);
                    $blockCount = $topCount;
                    foreach ($summary as $s) { $blockCount += count($s['children'] ?? []); }
                @endphp
                <div x-show="matches('{{ $tpl->category }}', {{ \Illuminate\Support\Js::from(strtolower($tpl->name . ' ' . $tpl->description . ' ' . ucfirst($tpl->category))) }})"
                     x-cloak
                     x-data="{ expanded: false }"
                     class="glass rounded-2xl border border-white/10 overflow-hidden hover:border-blue-500/40 transition group">
                    @php $previewRows = $tpl->preview_layout ?? []; @endphp
                    <div class="aspect-[4/3] flex items-center justify-center overflow-hidden relative" style="background: linear-gradient(135deg, rgba(61,107,255,0.12), rgba(92,131,255,0.04));">
                        @if($tpl->thumbnail_url)
                            <img src="{{ $tpl->thumbnail_url }}" alt="{{ $tpl->name }}" class="w-full h-full object-cover">
                        @elseif(!empty($previewRows))
                            {{-- Auto-generated mini blueprint of the page's top-level
                                 blocks. Shared with the guided wizard's starting-design
                                 step via the template-preview-blueprint partial. --}}
                            @include('user.links.partials.template-preview-blueprint', ['previewRows' => $previewRows])
                        @else
                            <img src="{{ asset('template-placeholders/page.svg') }}" alt="{{ $tpl->name }} preview" class="w-full h-full object-cover">
                        @endif
                        @if($locked)
                            <div class="absolute top-2 right-2 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/90 text-white"><i class="fas fa-lock mr-1"></i>{{ $tpl->plan_tier }}</div>
                        @endif
                    </div>
                    <div class="p-4">
                        {{-- Title row: name on the left, small subtle category pill on the
                             right so the most useful info (the title and the chip list below)
                             reads first. --}}
                        <div class="flex items-start justify-between gap-2 mb-1.5">
                            <h3 class="text-sm font-semibold text-white flex-1 min-w-0"
                                x-html="highlight({{ \Illuminate\Support\Js::from($tpl->name) }})">{{ $tpl->name }}</h3>
                            <span class="shrink-0 text-[9px] uppercase tracking-wide px-1.5 py-0.5 rounded-full whitespace-nowrap text-white/55"
                                  style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.10);"
                                  x-html="highlight({{ \Illuminate\Support\Js::from(ucfirst($tpl->category)) }})">{{ ucfirst($tpl->category) }}</span>
                        </div>
                        @if($tpl->description)
                            <p class="text-xs text-white/50 mb-2 line-clamp-2"
                               x-html="highlight({{ \Illuminate\Support\Js::from($tpl->description) }})">{{ $tpl->description }}</p>
                        @endif

                        @if($topCount)
                            {{-- Primary "what's inside" caption: small icon-tagged chips
                                 (icon + short label like '2 Cards', 'Heading'), grouped by
                                 type with counts. Full breakdown lives in the expand panel
                                 below so card heights stay consistent. --}}
                            @php
                                $chipGroups = [];
                                foreach ($summary as $entry) {
                                    $key = $entry['type'];
                                    if (!isset($chipGroups[$key])) {
                                        $chipGroups[$key] = ['icon' => $entry['icon'] ?: 'fa-cube', 'label' => $entry['label'], 'count' => 0];
                                    }
                                    $chipGroups[$key]['count'] += 1;
                                }
                                $chipGroups = array_values($chipGroups);
                                $shownChips = array_slice($chipGroups, 0, 3);
                                $extraChips = max(0, count($chipGroups) - 3);
                            @endphp
                            <div class="flex flex-wrap gap-1 mb-2">
                                @foreach($shownChips as $chip)
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-medium text-white/90"
                                          style="background: rgba(92,131,255,0.10); border: 1px solid rgba(92,131,255,0.18);">
                                        <i class="fas {{ $chip['icon'] }} text-blue-300" style="font-size: 9px;"></i>
                                        <span>{{ $chip['count'] > 1 ? $chip['count'] . ' ' . $chip['label'] . 's' : $chip['label'] }}</span>
                                    </span>
                                @endforeach
                                @if($extraChips > 0)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-semibold text-blue-300/90">
                                        +{{ $extraChips }} more
                                    </span>
                                @endif
                            </div>
                            <p class="text-[10px] text-white/35 mb-2">{{ $blockCount }} {{ \Illuminate\Support\Str::plural('block', $blockCount) }} total</p>
                            <button type="button"
                                    @click="expanded = !expanded"
                                    class="text-[11px] text-blue-400 hover:text-blue-300 mb-3 inline-flex items-center gap-1">
                                <i class="fas" :class="expanded ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                                <span x-text="expanded ? 'Hide what\'s inside' : 'See what\'s inside'"></span>
                            </button>
                            <div x-show="expanded" x-cloak class="mb-3 -mt-1 rounded-lg border border-white/10 bg-white/5 p-2.5 max-h-64 overflow-y-auto">
                                <div class="text-[10px] uppercase tracking-wide text-white/40 mb-1.5">What's inside</div>
                                <ul class="space-y-1.5">
                                    @foreach($summary as $entry)
                                        <li class="text-[11px] text-white/85">
                                            <div class="flex items-start gap-2">
                                                <i class="fas {{ $entry['icon'] ?: 'fa-cube' }} text-blue-400 mt-0.5 w-3 text-center"></i>
                                                <span class="flex-1 min-w-0">
                                                    <span class="font-semibold">{{ $entry['label'] }}</span>
                                                    @if(!empty($entry['preview']))
                                                        <span class="text-white/50"> — {{ $entry['preview'] }}</span>
                                                    @endif
                                                </span>
                                            </div>
                                            @if(!empty($entry['children']))
                                                <ul class="mt-1 ml-5 pl-2 border-l border-white/10 space-y-1">
                                                    @foreach($entry['children'] as $child)
                                                        <li class="flex items-start gap-2 text-[10.5px] text-white/70">
                                                            <i class="fas {{ $child['icon'] ?: 'fa-cube' }} text-blue-400/80 mt-0.5 w-3 text-center"></i>
                                                            <span class="flex-1 min-w-0">
                                                                <span class="font-medium">{{ $child['label'] }}</span>
                                                                @if(!empty($child['preview']))
                                                                    <span class="text-white/45"> — {{ $child['preview'] }}</span>
                                                                @endif
                                                            </span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        @if($locked)
                            <a href="{{ route('user.upgrade') }}" class="block text-center w-full py-2 text-xs font-semibold rounded-xl bg-amber-500/10 text-amber-400 border border-amber-500/20 hover:bg-amber-500/20 transition">
                                <i class="fas fa-lock mr-1"></i>Upgrade to "{{ $tpl->plan_tier }}" to use
                            </a>
                        @else
                            @php $hasBlocks = $link->biolinkBlocks()->exists(); @endphp
                            <div class="flex items-center gap-2">
                                <button type="button"
                                        @click="openPreview('{{ route('user.onboarding.template.preview', ['id' => $tpl->id]) }}', @js($tpl->name))"
                                        class="shrink-0 py-2 px-3 text-xs font-semibold rounded-xl bg-white/5 hover:bg-white/10 text-white border border-white/10 transition"
                                        title="Preview as a published page">
                                    <i class="fas fa-eye mr-1"></i>Preview
                                </button>
                                <form method="POST" action="{{ route('user.links.templates.apply-page', $link) }}" class="flex-1"
                                      @if($hasBlocks) onsubmit="return window.themedConfirmSubmit(this, {title: 'Replace existing blocks?', message: 'This will replace your existing blocks on this Link in Bio.', confirmText: 'Replace', confirmIcon: 'fa-arrows-rotate', iconClass: 'fa-triangle-exclamation'})" @endif>
                                    @csrf
                                    <input type="hidden" name="template_id" value="{{ $tpl->id }}">
                                    @if($hasBlocks)<input type="hidden" name="confirm_overwrite" value="1">@endif
                                    <button type="submit" class="w-full py-2 text-xs font-semibold rounded-xl bg-blue-600 hover:bg-blue-700 text-white transition">
                                        {{ $hasBlocks ? 'Replace with this template' : 'Use this template' }}
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
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
