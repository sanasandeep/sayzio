{{--
    Shared "How we compare" section.
    Data source of truth: App\Modules\Common\Support\ComparisonContent.
    Props:
      $compact (bool, default false) — when true, hides the wide matrix and
                                       shows only the head-to-head selector
                                       plus a CTA linking to /pricing#compare.
      $anchorId (string, default 'compare') — id used on the <section>.
      $only (string|null, default null) — when set to a rival key (e.g.
                                       'linktree'), locks the head-to-head to
                                       that rival, hides the rival selector and
                                       the full-matrix toggle. Used by the
                                       dedicated /compare/{competitor} pages.
      $hideHeading (bool, default false) — hide the built-in section heading
                                       (the comparison pages supply their own).
--}}
@php
    use App\Modules\Common\Support\ComparisonContent;

    $compact     = $compact  ?? false;
    $anchorId    = $anchorId ?? 'compare';
    $only        = $only ?? null;
    $hideHeading = $hideHeading ?? false;

    $__cmpCompetitors = ComparisonContent::competitors();
    $__cmpGroups      = ComparisonContent::groups();
    $__cmpFeaturesFlat = ComparisonContent::featuresFlat();
    $__cmpTotal       = ComparisonContent::totalFeatures();
    $__cmpScores      = ComparisonContent::scores();

    // When locked to a single rival, validate the key and fall back to the
    // first rival if it's unknown.
    $__cmpRivals = array_slice($__cmpCompetitors, 1);
    $__cmpOnlyKey = null;
    if ($only) {
        foreach ($__cmpRivals as $r) {
            if ($r['key'] === $only) { $__cmpOnlyKey = $only; break; }
        }
        if ($__cmpOnlyKey === null && !empty($__cmpRivals)) {
            $__cmpOnlyKey = $__cmpRivals[0]['key'];
        }
    }
    $__cmpInitialRival = $__cmpOnlyKey ?? ($__cmpRivals[0]['key'] ?? 'linktree');
    // Full N-tool matrix only appears on the non-compact homepage/pricing use
    // (never on a single-rival comparison page).
    $__cmpShowMatrix = !$compact && !$__cmpOnlyKey;
@endphp

<section id="{{ $anchorId }}" class="py-20 lg:py-28 relative overflow-hidden">
    <div class="mesh-bg" aria-hidden="true"></div>
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- Heading --}}
        @unless($hideHeading)
        <div class="text-center mb-10 max-w-2xl mx-auto">
            <div data-anim="fade-up" class="text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c4)">{{ $eyebrowOverride ?? 'How we compare' }}</div>
            <h2 data-anim="fade-up" class="text-4xl sm:text-5xl font-bold tracking-tight mb-4">
                More features. <span class="grad-text">Better deal.</span>
            </h2>
            <p data-anim="fade-up" class="text-gray-400">
                Pick any tool you already use — see exactly what Sayzio adds on top, across
                <span class="text-white font-semibold">{{ $__cmpTotal }} features</span> and
                <span class="text-white font-semibold">{{ count($__cmpCompetitors) - 1 }} competitors</span>.
            </p>
        </div>
        @endunless

        {{-- ========================================================
             HEAD-TO-HEAD (Alpine tabs)
             ======================================================== --}}
        <div
            data-anim="fade-up"
            class="cmp-h2h"
            x-data="{
                rival: '{{ $__cmpInitialRival }}',
                showAll: {{ $compact ? 'false' : 'true' }},
                rivals: @js(array_slice($__cmpCompetitors, 1)),
                scores: @js($__cmpScores),
                winsAnim: 0,
                ourAnim: 0,
                rivalAnim: 0,
                _raf: null,
                rivalName(){ return (this.rivals.find(r => r.key === this.rival) || {}).name || ''; },
                rivalTagline(){ return (this.rivals.find(r => r.key === this.rival) || {}).tagline || ''; },
                rivalBadge(){ return (this.rivals.find(r => r.key === this.rival) || {}).badge || ''; },
                ourScore(){ return this.scores.ours || 0; },
                rivalScore(){ return this.scores[this.rival] || 0; },
                wins(){ return Math.max(0, this.ourScore() - this.rivalScore()); },
                animateTo(targets){
                    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                        this.ourAnim = targets.ours; this.rivalAnim = targets.rival; this.winsAnim = targets.wins; return;
                    }
                    if (this._raf) cancelAnimationFrame(this._raf);
                    const start = performance.now(); const dur = 700;
                    const from = { ours: this.ourAnim, rival: this.rivalAnim, wins: this.winsAnim };
                    const tick = (t) => {
                        const k = Math.min(1, (t - start) / dur);
                        const e = 1 - Math.pow(1 - k, 3);
                        this.ourAnim   = Math.round(from.ours  + (targets.ours  - from.ours)  * e);
                        this.rivalAnim = Math.round(from.rival + (targets.rival - from.rival) * e);
                        this.winsAnim  = Math.round(from.wins  + (targets.wins  - from.wins)  * e);
                        if (k < 1) this._raf = requestAnimationFrame(tick);
                    };
                    this._raf = requestAnimationFrame(tick);
                }
            }"
            x-init="$nextTick(() => animateTo({ ours: ourScore(), rival: rivalScore(), wins: wins() }))"
            x-effect="animateTo({ ours: ourScore(), rival: rivalScore(), wins: wins() })"
        >
            {{-- Rival selector chips (hidden when locked to a single rival) --}}
            @unless($__cmpOnlyKey)
            <div class="cmp-tabs flex flex-wrap items-center justify-center gap-2 mb-6">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500 mr-1">Compare Sayzio vs</span>
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
            @endunless

            {{-- ────────── VS hero — side-by-side brand cards ────────── --}}
            <div class="cmp-vs grid items-stretch gap-3 mb-6"
                 style="grid-template-columns: 1fr auto 1fr;">
                {{-- Our card --}}
                <div class="cmp-vs-card cmp-vs-ours rounded-2xl p-4 sm:p-5">
                    <div class="cmp-vs-name">
                        <i class="fas fa-bolt"></i> Sayzio
                    </div>
                    <div class="cmp-vs-tagline">The whole growth stack</div>
                    <div class="cmp-vs-meta">
                        <span class="cmp-vs-score grad-text"><span x-text="ourAnim">{{ $__cmpScores['ours'] }}</span><span class="cmp-vs-score-total">/{{ $__cmpTotal }}</span></span>
                        <span class="cmp-vs-bar"><span class="cmp-vs-bar-fill cmp-vs-bar-ours" :style="`width:${(ourAnim/{{ $__cmpTotal }})*100}%`"></span></span>
                    </div>
                </div>

                {{-- Center VS badge --}}
                <div class="cmp-vs-center">
                    <div class="cmp-vs-badge" aria-hidden="true">
                        <span>VS</span>
                    </div>
                    <div class="cmp-vs-wins" x-show="wins() > 0" x-cloak>
                        <span class="cmp-vs-wins-num grad-text" x-text="winsAnim">0</span>
                        <span class="cmp-vs-wins-label">feature lead</span>
                    </div>
                </div>

                {{-- Rival card --}}
                <div class="cmp-vs-card cmp-vs-rival rounded-2xl p-4 sm:p-5"
                     :key="rival"
                     x-transition:enter="cmp-vs-fade"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100">
                    <div class="cmp-vs-name text-gray-200" x-text="rivalName()">Linktree</div>
                    <div class="cmp-vs-tagline" x-text="rivalTagline()">Link in Bio page</div>
                    <div class="cmp-vs-meta">
                        <span class="cmp-vs-score text-gray-200"><span x-text="rivalAnim">{{ $__cmpScores['linktree'] }}</span><span class="cmp-vs-score-total">/{{ $__cmpTotal }}</span></span>
                        <span class="cmp-vs-bar"><span class="cmp-vs-bar-fill cmp-vs-bar-rival" :style="`width:${(rivalAnim/{{ $__cmpTotal }})*100}%`"></span></span>
                    </div>
                </div>
            </div>

            {{-- Animated win counter --}}
            <div class="cmp-counter grad-border rounded-2xl px-5 py-4 mb-6 flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <span class="cmp-pulse" aria-hidden="true"></span>
                    <div class="text-sm">
                        <div class="text-gray-400 text-xs uppercase tracking-wider font-bold">Head-to-head</div>
                        <div class="text-white font-semibold">
                            Sayzio wins
                            <span class="grad-text font-extrabold text-lg" x-text="winsAnim">0</span>
                            more features than
                            <span class="text-white" x-text="rivalName()"></span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    <span class="cmp-badge cmp-badge-ours"><i class="fas fa-bolt"></i> Sayzio · <span x-text="ourAnim">{{ $__cmpScores['ours'] }}</span>/{{ $__cmpTotal }}</span>
                    <span class="cmp-badge"><span x-text="rivalName()"></span> · <span x-text="rivalAnim">{{ $__cmpScores['linktree'] }}</span>/{{ $__cmpTotal }}</span>
                </div>
            </div>

            {{-- Two-column head-to-head matrix --}}
            <div class="grad-border rounded-3xl overflow-hidden cmp-h2h-card">
                {{-- Header --}}
                <div class="grid items-center px-4 sm:px-6 py-5 bg-white/[.03] text-xs font-bold uppercase tracking-wider text-gray-400"
                     style="grid-template-columns: minmax(0,1fr) 110px 110px;">
                    <div>Feature</div>
                    <div class="text-center">
                        <span class="cmp-brand-ours text-[11px]"><i class="fas fa-bolt"></i> Sayzio</span>
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
                @if($__cmpOnlyKey)
                    {{-- Locked single-rival page: link out to the full index. --}}
                    <a href="{{ url('/compare') }}" class="cmp-cta">
                        <i class="fas fa-table-cells-large"></i>
                        Compare Sayzio against every other tool
                        <i class="fas fa-arrow-right text-xs"></i>
                    </a>
                @elseif($compact)
                    <a href="{{ url('/pricing') }}#compare" class="cmp-cta">
                        <i class="fas fa-table-cells-large"></i>
                        See full feature breakdown across all {{ count($__cmpCompetitors) - 1 }} tools
                        <i class="fas fa-arrow-right text-xs"></i>
                    </a>
                    {{-- Deep-link straight to the active rival's dedicated page. --}}
                    @foreach(array_slice($__cmpCompetitors, 1) as $c)
                        <template x-if="rival === '{{ $c['key'] }}'">
                            <a href="{{ url('/compare/' . $c['key']) }}" class="cmp-cta">
                                <i class="fas fa-arrow-up-right-from-square text-xs"></i>
                                Read the full Sayzio vs {{ $c['name'] }} page
                            </a>
                        </template>
                    @endforeach
                @else
                    <button type="button" @click="showAll = !showAll" class="cmp-cta">
                        <i class="fas fa-table-cells-large"></i>
                        <span x-text="showAll ? 'Hide full {{ count($__cmpCompetitors) - 1 }}-tool matrix' : 'Show full {{ count($__cmpCompetitors) - 1 }}-tool matrix'">Show full matrix</span>
                        <i class="fas fa-chevron-down text-xs" :class="showAll ? 'rotate-180' : ''" style="transition:transform .25s ease"></i>
                    </button>
                    @foreach(array_slice($__cmpCompetitors, 1) as $c)
                        <template x-if="rival === '{{ $c['key'] }}'">
                            <a href="{{ url('/compare/' . $c['key']) }}" class="cmp-cta">
                                <i class="fas fa-arrow-up-right-from-square text-xs"></i>
                                Full Sayzio vs {{ $c['name'] }} page
                            </a>
                        </template>
                    @endforeach
                @endif
            </div>

            {{-- ========================================================
                 FULL N-COMPETITOR MATRIX (only on /pricing or when toggled)
                 ======================================================== --}}
            @if($__cmpShowMatrix)
                <div x-show="showAll" x-transition.duration.400ms x-cloak class="mt-8">
                    <div class="cmp-wrap grad-border rounded-3xl overflow-hidden relative">
                        <div class="cmp-matrix-scroll">
                            <div class="cmp-matrix" style="grid-template-columns: minmax(220px, 1.6fr) repeat({{ count($__cmpCompetitors) }}, minmax(96px, 1fr));">
                                {{-- Highlighted column band over Sayzio (col index 1) --}}
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
            @endif
        </div>

        <p data-anim="fade-up" class="text-center text-xs text-gray-500 mt-6">
            Comparison reflects publicly listed feature sets at time of writing. We never quote a competitor's price.
        </p>
    </div>
</section>
