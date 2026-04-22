{{--
    Shared "How we compare" section.
    Props:
      $compact (bool, default false) — when true, hides the wide matrix and
                                       shows only the head-to-head selector
                                       plus a CTA linking to /pricing#compare.
      $anchorId (string, default 'compare') — id used on the <section>.
--}}
@php
    $compact  = $compact  ?? false;
    $anchorId = $anchorId ?? 'compare';

    // 7 competitors. "ours" must be first.
    $__cmpCompetitors = [
        ['key' => 'ours',     'name' => '1INME',    'tagline' => 'The whole growth stack', 'badge' => 'All-in-one',            'isOurs' => true],
        ['key' => 'linktree', 'name' => 'Linktree', 'tagline' => 'Bio link page',          'badge' => 'Half the cost',         'isOurs' => false],
        ['key' => 'bitly',    'name' => 'Bitly',    'tagline' => 'Short links & QR',       'badge' => 'More features',         'isOurs' => false],
        ['key' => 'beacons',  'name' => 'Beacons',  'tagline' => 'Creator bio',            'badge' => 'Lower price',           'isOurs' => false],
        ['key' => 'carrd',    'name' => 'Carrd',    'tagline' => 'One-page sites',         'badge' => 'Way more inside',       'isOurs' => false],
        ['key' => 'taplink',  'name' => 'Taplink',  'tagline' => 'Insta micro-landing',    'badge' => 'Bigger toolkit',        'isOurs' => false],
        ['key' => 'stan',     'name' => 'Stan',     'tagline' => 'Creator store',          'badge' => 'Free forever plan',     'isOurs' => false],
    ];

    // 24 features grouped into 7 categories.
    $__cmpGroups = [
        'Bio link page' => [
            ['Drag-and-drop biolink builder',     ['ours'=>true,'linktree'=>true,  'bitly'=>false,'beacons'=>true, 'carrd'=>true, 'taplink'=>true, 'stan'=>true ]],
            ['Multiple bio pages per account',    ['ours'=>true,'linktree'=>true,  'bitly'=>false,'beacons'=>false,'carrd'=>true, 'taplink'=>false,'stan'=>true ]],
            ['Embed video, music & forms',        ['ours'=>true,'linktree'=>true,  'bitly'=>false,'beacons'=>true, 'carrd'=>true, 'taplink'=>true, 'stan'=>true ]],
            ['Custom themes & fonts',             ['ours'=>true,'linktree'=>true,  'bitly'=>false,'beacons'=>true, 'carrd'=>true, 'taplink'=>true, 'stan'=>true ]],
            ['Custom domains',                    ['ours'=>true,'linktree'=>true,  'bitly'=>true, 'beacons'=>true, 'carrd'=>true, 'taplink'=>true, 'stan'=>true ]],
        ],
        'Links & QR' => [
            ['Branded short links',               ['ours'=>true,'linktree'=>false, 'bitly'=>true, 'beacons'=>false,'carrd'=>false,'taplink'=>false,'stan'=>false]],
            ['Dynamic QR codes',                  ['ours'=>true,'linktree'=>true,  'bitly'=>true, 'beacons'=>true, 'carrd'=>false,'taplink'=>true, 'stan'=>false]],
            ['QR styling, logos & colors',        ['ours'=>true,'linktree'=>false, 'bitly'=>true, 'beacons'=>false,'carrd'=>false,'taplink'=>false,'stan'=>false]],
            ['Bulk link import',                  ['ours'=>true,'linktree'=>false, 'bitly'=>true, 'beacons'=>false,'carrd'=>false,'taplink'=>false,'stan'=>false]],
        ],
        'Analytics' => [
            ['Built-in click analytics',          ['ours'=>true,'linktree'=>true,  'bitly'=>true, 'beacons'=>true, 'carrd'=>true, 'taplink'=>true, 'stan'=>true ]],
            ['Live visitor map',                  ['ours'=>true,'linktree'=>false, 'bitly'=>false,'beacons'=>false,'carrd'=>false,'taplink'=>false,'stan'=>false]],
            ['Click heatmap',                     ['ours'=>true,'linktree'=>false, 'bitly'=>false,'beacons'=>false,'carrd'=>false,'taplink'=>false,'stan'=>false]],
            ['UTM builder',                       ['ours'=>true,'linktree'=>false, 'bitly'=>true, 'beacons'=>false,'carrd'=>false,'taplink'=>false,'stan'=>false]],
        ],
        'Growth & AI' => [
            ['AI Performance coach',              ['ours'=>true,'linktree'=>false, 'bitly'=>false,'beacons'=>false,'carrd'=>false,'taplink'=>false,'stan'=>false]],
            ['Scheduled posts',                   ['ours'=>true,'linktree'=>false, 'bitly'=>false,'beacons'=>true, 'carrd'=>false,'taplink'=>false,'stan'=>true ]],
            ['A/B testing',                       ['ours'=>true,'linktree'=>true,  'bitly'=>true, 'beacons'=>false,'carrd'=>false,'taplink'=>false,'stan'=>false]],
        ],
        'Monetization' => [
            ['Tip jar / donations',               ['ours'=>true,'linktree'=>true,  'bitly'=>false,'beacons'=>true, 'carrd'=>false,'taplink'=>true, 'stan'=>true ]],
            ['Sell digital products',             ['ours'=>true,'linktree'=>true,  'bitly'=>false,'beacons'=>true, 'carrd'=>false,'taplink'=>true, 'stan'=>true ]],
            ['Coin / wallet rewards',             ['ours'=>true,'linktree'=>false, 'bitly'=>false,'beacons'=>false,'carrd'=>false,'taplink'=>false,'stan'=>false]],
        ],
        'Team & workflow' => [
            ['Team workspaces',                   ['ours'=>true,'linktree'=>true,  'bitly'=>true, 'beacons'=>false,'carrd'=>false,'taplink'=>false,'stan'=>false]],
            ['Direct messaging',                  ['ours'=>true,'linktree'=>false, 'bitly'=>false,'beacons'=>false,'carrd'=>false,'taplink'=>false,'stan'=>false]],
            ['Roles & permissions',               ['ours'=>true,'linktree'=>true,  'bitly'=>true, 'beacons'=>false,'carrd'=>false,'taplink'=>false,'stan'=>false]],
        ],
        'Plans & access' => [
            ['Free forever (no credit card)',     ['ours'=>true,'linktree'=>true,  'bitly'=>true, 'beacons'=>true, 'carrd'=>true, 'taplink'=>true, 'stan'=>false]],
            ['Native mobile app',                 ['ours'=>true,'linktree'=>true,  'bitly'=>true, 'beacons'=>true, 'carrd'=>false,'taplink'=>true, 'stan'=>true ]],
        ],
    ];

    // Flat list for the wide matrix + counts.
    $__cmpFeaturesFlat = [];
    foreach ($__cmpGroups as $g => $rows) {
        foreach ($rows as $r) { $__cmpFeaturesFlat[] = $r; }
    }
    $__cmpTotal = count($__cmpFeaturesFlat); // 24

    // Pre-compute per-competitor totals so the Alpine tabs can show win deltas
    // without doing the math client-side.
    $__cmpScores = [];
    foreach ($__cmpCompetitors as $c) {
        $n = 0;
        foreach ($__cmpFeaturesFlat as [$label, $support]) {
            if (!empty($support[$c['key']])) { $n++; }
        }
        $__cmpScores[$c['key']] = $n;
    }
@endphp

<section id="{{ $anchorId }}" class="py-20 lg:py-28 relative overflow-hidden">
    <div class="mesh-bg" aria-hidden="true"></div>
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- Heading --}}
        <div class="text-center mb-10 max-w-2xl mx-auto">
            <div data-anim="fade-up" class="text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c4)">How we compare</div>
            <h2 data-anim="fade-up" class="text-4xl sm:text-5xl font-bold tracking-tight mb-4">
                More features. <span class="grad-text">Better deal.</span>
            </h2>
            <p data-anim="fade-up" class="text-gray-400">
                Pick any tool you already use — see exactly what 1INME adds on top, across
                <span class="text-white font-semibold">{{ $__cmpTotal }} features</span> and
                <span class="text-white font-semibold">{{ count($__cmpCompetitors) - 1 }} competitors</span>.
            </p>
        </div>

        {{-- ========================================================
             HEAD-TO-HEAD (Alpine tabs)
             ======================================================== --}}
        <div
            data-anim="fade-up"
            class="cmp-h2h"
            x-data="{
                rival: 'linktree',
                showAll: {{ $compact ? 'false' : 'true' }},
                rivals: @js(array_slice($__cmpCompetitors, 1)),
                scores: @js($__cmpScores),
                rivalName(){ return (this.rivals.find(r => r.key === this.rival) || {}).name || ''; },
                rivalTagline(){ return (this.rivals.find(r => r.key === this.rival) || {}).tagline || ''; },
                rivalBadge(){ return (this.rivals.find(r => r.key === this.rival) || {}).badge || ''; },
                ourScore(){ return this.scores.ours || 0; },
                rivalScore(){ return this.scores[this.rival] || 0; },
                wins(){ return Math.max(0, this.ourScore() - this.rivalScore()); }
            }"
        >
            {{-- Rival selector chips --}}
            <div class="cmp-tabs flex flex-wrap items-center justify-center gap-2 mb-6">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500 mr-1">Compare 1INME vs</span>
                @foreach(array_slice($__cmpCompetitors, 1) as $c)
                    <button
                        type="button"
                        @click="rival = '{{ $c['key'] }}'"
                        :class="rival === '{{ $c['key'] }}' ? 'cmp-tab cmp-tab-active' : 'cmp-tab'"
                        class="cmp-tab"
                    >
                        {{ $c['name'] }}
                    </button>
                @endforeach
            </div>

            {{-- Animated win counter --}}
            <div class="cmp-counter grad-border rounded-2xl px-5 py-4 mb-6 flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <span class="cmp-pulse" aria-hidden="true"></span>
                    <div class="text-sm">
                        <div class="text-gray-400 text-xs uppercase tracking-wider font-bold">Head-to-head</div>
                        <div class="text-white font-semibold">
                            1INME wins
                            <span class="grad-text font-extrabold text-lg" x-text="wins()">0</span>
                            more features than
                            <span class="text-white" x-text="rivalName()"></span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    <span class="cmp-badge cmp-badge-ours"><i class="fas fa-bolt"></i> 1INME · <span x-text="ourScore()">{{ $__cmpScores['ours'] }}</span>/{{ $__cmpTotal }}</span>
                    <span class="cmp-badge"><span x-text="rivalName()"></span> · <span x-text="rivalScore()">{{ $__cmpScores['linktree'] }}</span>/{{ $__cmpTotal }}</span>
                </div>
            </div>

            {{-- Two-column head-to-head matrix --}}
            <div class="grad-border rounded-3xl overflow-hidden cmp-h2h-card">
                {{-- Header --}}
                <div class="grid items-center px-4 sm:px-6 py-5 bg-white/[.03] text-xs font-bold uppercase tracking-wider text-gray-400"
                     style="grid-template-columns: minmax(0,1fr) 110px 110px;">
                    <div>Feature</div>
                    <div class="text-center">
                        <span class="cmp-brand-ours text-[11px]"><i class="fas fa-bolt"></i> 1INME</span>
                    </div>
                    <div class="text-center text-gray-300 normal-case tracking-normal text-sm font-semibold" x-text="rivalName()">Linktree</div>
                </div>

                {{-- Grouped rows --}}
                @foreach($__cmpGroups as $groupName => $rows)
                    <div class="px-4 sm:px-6 py-2.5 bg-white/[.015] border-t border-white/5 text-[11px] font-bold uppercase tracking-wider text-gray-500 cmp-group-head">
                        {{ $groupName }}
                    </div>
                    <div class="cmp-stagger" data-anim="fade">
                        @foreach($rows as [$label, $support])
                            <div class="cmp-row grid items-center px-4 sm:px-6 py-3 border-t border-white/5 text-sm"
                                 style="grid-template-columns: minmax(0,1fr) 110px 110px;">
                                <div class="text-gray-200">{{ $label }}</div>
                                <div class="text-center">
                                    <span class="cmp-mark cmp-mark-yes-ours" aria-label="Included">
                                        <svg class="cmp-draw" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M5 12.5l4.5 4.5L19 7"/>
                                        </svg>
                                    </span>
                                </div>
                                <div class="text-center">
                                    {{-- One cell per rival, only the active one is shown --}}
                                    @foreach(array_slice($__cmpCompetitors, 1) as $c)
                                        <template x-if="rival === '{{ $c['key'] }}'">
                                            <span>
                                                @if(!empty($support[$c['key']]))
                                                    <span class="cmp-mark cmp-mark-yes" aria-label="Included">
                                                        <svg class="cmp-draw" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                            <path d="M5 12.5l4.5 4.5L19 7"/>
                                                        </svg>
                                                    </span>
                                                @else
                                                    <span class="cmp-mark cmp-mark-no" aria-label="Not included">
                                                        <svg class="cmp-draw" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true">
                                                            <path d="M6 12h12"/>
                                                        </svg>
                                                    </span>
                                                @endif
                                            </span>
                                        </template>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>

            {{-- Toggle / CTA row --}}
            <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                @if($compact)
                    <a href="{{ url('/pricing') }}#compare" class="cmp-cta">
                        <i class="fas fa-table-cells-large"></i>
                        See full feature breakdown across all {{ count($__cmpCompetitors) - 1 }} tools
                        <i class="fas fa-arrow-right text-xs"></i>
                    </a>
                @else
                    <button type="button" @click="showAll = !showAll" class="cmp-cta">
                        <i class="fas fa-table-cells-large"></i>
                        <span x-text="showAll ? 'Hide full {{ count($__cmpCompetitors) - 1 }}-tool matrix' : 'Show full {{ count($__cmpCompetitors) - 1 }}-tool matrix'">Show full matrix</span>
                        <i class="fas fa-chevron-down text-xs" :class="showAll ? 'rotate-180' : ''" style="transition:transform .25s ease"></i>
                    </button>
                @endif
            </div>

            {{-- ========================================================
                 FULL N-COMPETITOR MATRIX (only on /pricing or when toggled)
                 ======================================================== --}}
            @unless($compact)
                <div x-show="showAll" x-transition.duration.400ms x-cloak class="mt-8">
                    <div class="cmp-wrap grad-border rounded-3xl overflow-hidden relative">
                        <div class="cmp-matrix-scroll">
                            <div class="cmp-matrix" style="grid-template-columns: minmax(220px, 1.6fr) repeat({{ count($__cmpCompetitors) }}, minmax(96px, 1fr));">
                                {{-- Highlighted column band over 1INME (col index 1) --}}
                                <div class="cmp-ours-band cmp-ours-band-grid" aria-hidden="true"></div>

                                {{-- Header --}}
                                <div class="cmp-cell cmp-head">Feature</div>
                                @foreach($__cmpCompetitors as $c)
                                    <div class="cmp-cell cmp-head text-center">
                                        @if($c['isOurs'])
                                            <span class="cmp-brand-ours text-[11px]"><i class="fas fa-bolt"></i> {{ $c['name'] }}</span>
                                        @else
                                            <span class="text-gray-200 text-sm font-semibold normal-case tracking-normal">{{ $c['name'] }}</span>
                                        @endif
                                    </div>
                                @endforeach

                                {{-- Rows, grouped --}}
                                @foreach($__cmpGroups as $groupName => $rows)
                                    <div class="cmp-cell cmp-group-head" style="grid-column: span {{ count($__cmpCompetitors) + 1 }};">{{ $groupName }}</div>
                                    @foreach($rows as [$label, $support])
                                        <div class="cmp-cell cmp-row-cell text-gray-200">{{ $label }}</div>
                                        @foreach($__cmpCompetitors as $c)
                                            <div class="cmp-cell cmp-row-cell text-center">
                                                @if(!empty($support[$c['key']]))
                                                    <span class="cmp-mark {{ $c['isOurs'] ? 'cmp-mark-yes-ours' : 'cmp-mark-yes' }}" style="width:26px;height:26px;">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                            <path d="M5 12.5l4.5 4.5L19 7"/>
                                                        </svg>
                                                    </span>
                                                @else
                                                    <span class="cmp-mark cmp-mark-no" style="width:26px;height:26px;">
                                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true">
                                                            <path d="M6 12h12"/>
                                                        </svg>
                                                    </span>
                                                @endif
                                            </div>
                                        @endforeach
                                    @endforeach
                                @endforeach

                                {{-- Bottom totals --}}
                                <div class="cmp-cell cmp-row-cell text-xs font-bold uppercase tracking-wider text-gray-400">Total features</div>
                                @foreach($__cmpCompetitors as $c)
                                    <div class="cmp-cell cmp-row-cell text-center">
                                        <span class="cmp-badge {{ $c['isOurs'] ? 'cmp-badge-ours' : '' }}">
                                            {{ $__cmpScores[$c['key']] }}/{{ $__cmpTotal }}
                                        </span>
                                    </div>
                                @endforeach

                                <div class="cmp-cell cmp-row-cell text-xs font-bold uppercase tracking-wider text-gray-400">The bottom line</div>
                                @foreach($__cmpCompetitors as $c)
                                    <div class="cmp-cell cmp-row-cell text-center">
                                        <span class="cmp-badge {{ $c['isOurs'] ? 'cmp-badge-ours' : '' }}">
                                            @if($c['isOurs'])<i class="fas fa-star text-[10px]"></i>@endif
                                            {{ $c['badge'] }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="md:hidden text-center text-[11px] text-gray-500 px-4 py-3 bg-white/[.02] border-t border-white/5">
                            <i class="fas fa-arrows-left-right"></i> Swipe to see all tools
                        </div>
                    </div>
                </div>
            @endunless
        </div>

        <p data-anim="fade-up" class="text-center text-xs text-gray-500 mt-6">
            Comparison reflects publicly listed feature sets at time of writing. We never quote a competitor's price.
        </p>
    </div>
</section>
