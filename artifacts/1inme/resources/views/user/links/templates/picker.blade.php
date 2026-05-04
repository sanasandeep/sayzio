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
                @php
                    $locked = $lockedFn($tpl->plan_tier);
                    $summary = $tpl->content_summary ?? [];
                    $topCount = count($summary);
                    $blockCount = $topCount;
                    foreach ($summary as $s) { $blockCount += count($s['children'] ?? []); }
                @endphp
                <div x-show="(category === 'all' || category === '{{ $tpl->category }}') && (search === '' || '{{ strtolower($tpl->name . ' ' . $tpl->description) }}'.includes(search.toLowerCase()))"
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
                                 proportional to their grid_span, with type-specific
                                 background/height/icon hints so the layout is
                                 recognisable at thumbnail size. --}}
                            <div class="w-full h-full px-2 py-1.5 flex flex-col gap-1 justify-center">
                                @foreach($previewRows as $row)
                                    <div class="flex gap-1 w-full items-center">
                                        @foreach($row as $cell)
                                            <div class="rounded-[3px] flex items-center justify-center text-white/70"
                                                 style="flex: {{ $cell['span'] }} 0 0; min-height: {{ $cell['h'] }}px; background: {{ $cell['bg'] }};">
                                                @if(!empty($cell['icon']))
                                                    <i class="fas {{ $cell['icon'] }}" style="font-size: 7px;"></i>
                                                @endif
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
                        <h3 class="text-sm font-semibold text-white mb-1">{{ $tpl->name }}</h3>
                        <p class="text-xs text-white/40 mb-1">{{ ucfirst($tpl->category) }} · {{ $blockCount }} {{ \Illuminate\Support\Str::plural('block', $blockCount) }}</p>
                        @if($tpl->description)
                            <p class="text-xs text-white/50 mb-3 line-clamp-2">{{ $tpl->description }}</p>
                        @endif

                        @if($topCount)
                            {{-- Compact "what's inside" peek: first ~3 top-level cards/blocks
                                 with friendly type labels. Full breakdown lives in the
                                 expand panel below so card heights stay consistent. --}}
                            <div class="text-[11px] leading-snug text-white/55 mb-2">
                                @php $peek = array_slice($summary, 0, 3); @endphp
                                <span>{{ implode(' · ', array_map(fn($s) => $s['label'], $peek)) }}</span>
                                @if($topCount > 3)
                                    <span class="text-violet-300/80"> +{{ $topCount - 3 }} more</span>
                                @endif
                            </div>
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
