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
        background-color: rgba(139, 92, 246, 0.30);
        color: #fff;
        border-radius: 2px;
        padding: 0 2px;
    }
    /* Mini page-preview placeholder typography — mirrors the card-templates
       gallery (editor-special-panel) so both previews read the same. White on
       the dark theme; dark ink under html.light-mode where the pale thumbnail
       background would wash white text out. Pill/button labels stay white
       because they sit on a coloured fill. */
    .tpl-prev-heading { font-size: 8px; font-weight: 700; line-height: 1.1; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .tpl-prev-name    { font-size: 7.5px; font-weight: 700; line-height: 1.1; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .tpl-prev-sub     { font-size: 6px; line-height: 1.15; color: rgba(255,255,255,0.6); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .tpl-prev-text    { font-size: 6px; line-height: 1.3; color: rgba(255,255,255,0.6); display: -webkit-box; -webkit-box-orient: vertical; overflow: hidden; }
    .tpl-prev-list    { font-size: 6px; line-height: 1.1; color: rgba(255,255,255,0.65); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .tpl-prev-pill    { font-size: 6px; font-weight: 700; line-height: 1; }
    html.light-mode .tpl-prev-heading,
    html.light-mode .tpl-prev-name { color: rgba(7,20,55,0.88); }
    html.light-mode .tpl-prev-sub,
    html.light-mode .tpl-prev-text,
    html.light-mode .tpl-prev-list { color: rgba(7,20,55,0.55); }
</style>
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
        templates: {{ \Illuminate\Support\Js::from($filterIndex) }},
        // Single source of truth for whether a template passes the active
        // category + search filter. Used by both the per-card x-show and the
        // visibleCount accessor below so the two can never drift apart.
        // `text` should already be lowercased by the server-side index.
        matches(category, text) {
            return (this.category === 'all' || this.category === category) &&
                (this.search === '' || text.includes(this.search.toLowerCase()));
        },
        get visibleCount() {
            return this.templates.filter(t => this.matches(t.category, t.text)).length;
        },
        // Wrap occurrences of the active search term inside `text` in a
        // <mark> tag so the picker shows *why* a card matched. HTML-escapes
        // surrounding text and the matched slice to keep admin-authored
        // names/descriptions safe from injection. Falls back to the plain
        // (escaped) string when the search box is empty. The wrapping mark
        // uses a single class (.tpl-mark, defined in the <style> block
        // below) so we don't need quoted attributes inside this
        // double-quoted x-data expression.
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
            <button @click="category = '{{ $key }}'" :class="category === '{{ $key }}' ? 'bg-violet-600 text-white' : 'text-white/50 hover:text-white bg-white/5'" class="px-3 py-1.5 text-xs font-semibold rounded-lg transition flex-shrink-0">
                {{ $label }}
            </button>
            @endif
        @endforeach
    </div>

    @if($pageTemplates->isEmpty())
        <div class="glass rounded-2xl border border-white/10 p-10 sm:p-14 text-center max-w-xl mx-auto">
            <div class="relative w-20 h-20 mx-auto mb-5">
                <div class="absolute inset-0 rounded-2xl blur-xl opacity-60" style="background: linear-gradient(135deg, rgba(124,58,237,0.55), rgba(236,72,153,0.35));"></div>
                <div class="relative w-20 h-20 rounded-2xl flex items-center justify-center border border-white/10"
                     style="background: linear-gradient(135deg, rgba(124,58,237,0.22), rgba(139,92,246,0.10));">
                    <i class="fas fa-layer-group text-3xl text-violet-300"></i>
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
                   class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold text-white transition shadow-lg shadow-violet-900/30"
                   style="background: linear-gradient(135deg, #7c3aed, #8b5cf6);">
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
                <div class="absolute inset-0 rounded-2xl blur-xl opacity-60" style="background: linear-gradient(135deg, rgba(124,58,237,0.55), rgba(236,72,153,0.35));"></div>
                <div class="relative w-14 h-14 rounded-2xl flex items-center justify-center border border-white/10"
                     style="background: linear-gradient(135deg, rgba(124,58,237,0.22), rgba(139,92,246,0.10));">
                    <i class="fas fa-magnifying-glass text-lg text-violet-300"></i>
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
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold text-white transition shadow-lg shadow-violet-900/30"
                    style="background: linear-gradient(135deg, #7c3aed, #8b5cf6);">
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
                     class="glass rounded-2xl border border-white/10 overflow-hidden hover:border-violet-500/40 transition group">
                    @php $previewRows = $tpl->preview_layout ?? []; @endphp
                    <div class="aspect-[4/3] flex items-center justify-center overflow-hidden relative" style="background: linear-gradient(135deg, rgba(124,58,237,0.12), rgba(139,92,246,0.04));">
                        @if($tpl->thumbnail_url)
                            <img src="{{ $tpl->thumbnail_url }}" alt="{{ $tpl->name }}" class="w-full h-full object-cover">
                        @elseif(!empty($previewRows))
                            {{-- Auto-generated mini blueprint of the page's top-level
                                 blocks. Mirrors the card-templates gallery preview:
                                 each row is a flex row whose children flex-grow
                                 proportional to their grid_span, with shape-aware
                                 cell rendering (avatar circles, pill buttons, stacked
                                 input lines, social dot rows, etc.) so the tile shows
                                 the card's actual content at thumbnail size. --}}
                            <div class="w-full h-full px-2 py-1.5 flex flex-col gap-1 justify-center">
                                @foreach($previewRows as $row)
                                    <div class="flex gap-1 w-full items-center">
                                        @foreach($row as $cell)
                                            @php
                                                $shape = $cell['shape'] ?? 'tile';
                                                $bg    = $cell['bg']    ?? 'rgba(255,255,255,0.10)';
                                                $h     = (int) ($cell['h'] ?? 12);
                                                $icon  = $cell['icon']  ?? '';
                                                $lines = (int) ($cell['lines'] ?? 2);
                                                $dots  = (int) ($cell['dots']  ?? 5);
                                                $sub   = !empty($cell['sub']);
                                                $btnBg = $cell['btn_bg'] ?? 'rgba(139,92,246,0.85)';
                                                $text  = $cell['text'] ?? '';
                                                $subText = $cell['sub_text'] ?? '';
                                                $imgUrl = $cell['img'] ?? '';
                                                $items = is_array($cell['items'] ?? null) ? $cell['items'] : [];
                                                $play  = !empty($cell['play']);
                                            @endphp
                                            <div class="flex items-center justify-center" style="flex: {{ $cell['span'] }} 0 0;">
                                                @switch($shape)
                                                    @case('heading')
                                                        <div class="w-full flex flex-col gap-[1px] items-center text-center">
                                                            @if($text !== '')
                                                                <div class="tpl-prev-heading w-full">{{ $text }}</div>
                                                            @else
                                                                <div class="rounded-[2px] w-full" style="background: {{ $bg }}; height: {{ $h }}px;"></div>
                                                            @endif
                                                            @if($sub && $subText !== '')
                                                                <div class="tpl-prev-sub w-full">{{ $subText }}</div>
                                                            @elseif($sub)
                                                                <div class="rounded-[2px]" style="background: {{ $bg }}; height: {{ max($h - 6, 4) }}px; width: 55%;"></div>
                                                            @endif
                                                        </div>
                                                        @break
                                                    @case('text_lines')
                                                        <div class="w-full flex flex-col gap-[2px] justify-center" style="min-height: {{ $h }}px;">
                                                            @if($text !== '')
                                                                <div class="tpl-prev-text" style="-webkit-line-clamp: {{ max($lines, 1) }};">{{ $text }}</div>
                                                            @else
                                                                @for($i = 1; $i <= max($lines, 1); $i++)
                                                                    <div class="rounded-[2px]" style="background: {{ $bg }}; height: 3px; width: {{ $i === max($lines, 1) ? '60%' : '100%' }};"></div>
                                                                @endfor
                                                            @endif
                                                        </div>
                                                        @break
                                                    @case('pill')
                                                        <div class="w-full rounded-full flex items-center justify-center gap-1 px-1.5 text-white/95 tpl-prev-pill"
                                                             style="background: {{ $bg }}; min-height: {{ $h }}px;">
                                                            @if($text !== '')<span class="truncate">{{ $text }}</span>@endif
                                                            @if($icon)<i class="fas {{ $icon }}" style="font-size: 6px;"></i>@endif
                                                        </div>
                                                        @break
                                                    @case('avatar')
                                                        <div class="w-full flex items-center gap-1.5" style="min-height: {{ $h }}px;">
                                                            @if($imgUrl !== '')
                                                                <img src="{{ $imgUrl }}" alt="" loading="lazy" class="rounded-full object-cover shrink-0"
                                                                     style="width: {{ max($h - 8, 14) }}px; height: {{ max($h - 8, 14) }}px;">
                                                            @else
                                                                <div class="rounded-full flex items-center justify-center text-white/90 shrink-0"
                                                                     style="background: {{ $bg }}; width: {{ max($h - 8, 14) }}px; height: {{ max($h - 8, 14) }}px;">
                                                                    @if($icon)<i class="fas {{ $icon }}" style="font-size: 7px;"></i>@endif
                                                                </div>
                                                            @endif
                                                            <div class="flex-1 flex flex-col gap-[1px] min-w-0">
                                                                @if($text !== '')
                                                                    <div class="tpl-prev-name">{{ $text }}</div>
                                                                @else
                                                                    <div class="rounded-[2px]" style="background: rgba(255,255,255,0.55); height: 4px; width: 70%;"></div>
                                                                @endif
                                                                @if($subText !== '')
                                                                    <div class="tpl-prev-sub">{{ $subText }}</div>
                                                                @else
                                                                    <div class="rounded-[2px]" style="background: rgba(255,255,255,0.30); height: 3px; width: 50%;"></div>
                                                                @endif
                                                            </div>
                                                        </div>
                                                        @break
                                                    @case('media')
                                                        <div class="w-full rounded-[3px] relative overflow-hidden flex items-center justify-center text-white/85"
                                                             style="background: {{ $bg }}; min-height: {{ $h }}px; height: {{ $h }}px;">
                                                            @if($imgUrl !== '')
                                                                <img src="{{ $imgUrl }}" alt="" loading="lazy" class="absolute inset-0 w-full h-full object-cover">
                                                            @endif
                                                            @if($play || $imgUrl === '')
                                                                <i class="fas {{ $play ? 'fa-play' : $icon }} relative" style="font-size: 11px;{{ $imgUrl !== '' ? ' text-shadow: 0 1px 3px rgba(0,0,0,0.6);' : '' }}"></i>
                                                            @endif
                                                        </div>
                                                        @break
                                                    @case('dot_row')
                                                        <div class="w-full flex items-center justify-center gap-1" style="min-height: {{ $h }}px;">
                                                            @for($i = 1; $i <= max($dots, 1); $i++)
                                                                <div class="rounded-full" style="background: {{ $bg }}; width: 5px; height: 5px;"></div>
                                                            @endfor
                                                        </div>
                                                        @break
                                                    @case('form')
                                                        <div class="w-full flex flex-col gap-1 justify-center" style="min-height: {{ $h }}px;">
                                                            @for($i = 1; $i <= max($lines, 1); $i++)
                                                                <div class="rounded-[2px] w-full" style="background: {{ $bg }}; height: 5px;"></div>
                                                            @endfor
                                                            <div class="rounded-full mx-auto flex items-center justify-center text-white/95 tpl-prev-pill px-1.5" style="background: {{ $btnBg }}; min-height: 7px; width: 70%;">
                                                                @if($text !== '')<span class="truncate">{{ $text }}</span>@endif
                                                            </div>
                                                        </div>
                                                        @break
                                                    @case('list_rows')
                                                        @php $listRows = !empty($items) ? array_slice($items, 0, max($lines, 1)) : array_fill(0, max($lines, 1), null); @endphp
                                                        <div class="w-full flex flex-col gap-1 justify-center" style="min-height: {{ $h }}px;">
                                                            @foreach($listRows as $item)
                                                                <div class="flex items-center gap-1 w-full">
                                                                    <div class="rounded-full shrink-0" style="background: {{ $bg }}; width: 3px; height: 3px;"></div>
                                                                    @if($item)
                                                                        <div class="tpl-prev-list flex-1">{{ $item }}</div>
                                                                    @else
                                                                        <div class="rounded-[2px] flex-1" style="background: {{ $bg }}; height: 3px;"></div>
                                                                    @endif
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                        @break
                                                    @case('hairline')
                                                        <div class="w-full rounded-[2px]" style="background: {{ $bg }}; height: {{ $h }}px;"></div>
                                                        @break
                                                    @case('spacer')
                                                        <div class="w-full" style="min-height: {{ $h }}px;"></div>
                                                        @break
                                                    @case('badge')
                                                        <div class="rounded-full mx-auto" style="background: {{ $bg }}; height: {{ $h }}px; width: 50%;"></div>
                                                        @break
                                                    @default
                                                        <div class="w-full rounded-[3px] flex items-center justify-center text-white/70"
                                                             style="background: {{ $bg }}; min-height: {{ $h }}px;">
                                                            @if($icon)<i class="fas {{ $icon }}" style="font-size: 8px;"></i>@endif
                                                        </div>
                                                @endswitch
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
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
                                          style="background: rgba(139,92,246,0.10); border: 1px solid rgba(139,92,246,0.18);">
                                        <i class="fas {{ $chip['icon'] }} text-violet-300" style="font-size: 9px;"></i>
                                        <span>{{ $chip['count'] > 1 ? $chip['count'] . ' ' . $chip['label'] . 's' : $chip['label'] }}</span>
                                    </span>
                                @endforeach
                                @if($extraChips > 0)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-semibold text-violet-300/90">
                                        +{{ $extraChips }} more
                                    </span>
                                @endif
                            </div>
                            <p class="text-[10px] text-white/35 mb-2">{{ $blockCount }} {{ \Illuminate\Support\Str::plural('block', $blockCount) }} total</p>
                            <button type="button"
                                    @click="expanded = !expanded"
                                    class="text-[11px] text-violet-400 hover:text-violet-300 mb-3 inline-flex items-center gap-1">
                                <i class="fas" :class="expanded ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                                <span x-text="expanded ? 'Hide what\'s inside' : 'See what\'s inside'"></span>
                            </button>
                            <div x-show="expanded" x-cloak class="mb-3 -mt-1 rounded-lg border border-white/10 bg-white/5 p-2.5 max-h-64 overflow-y-auto">
                                <div class="text-[10px] uppercase tracking-wide text-white/40 mb-1.5">What's inside</div>
                                <ul class="space-y-1.5">
                                    @foreach($summary as $entry)
                                        <li class="text-[11px] text-white/85">
                                            <div class="flex items-start gap-2">
                                                <i class="fas {{ $entry['icon'] ?: 'fa-cube' }} text-violet-400 mt-0.5 w-3 text-center"></i>
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
                                                            <i class="fas {{ $child['icon'] ?: 'fa-cube' }} text-violet-400/80 mt-0.5 w-3 text-center"></i>
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
