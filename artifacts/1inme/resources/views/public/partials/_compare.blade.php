{{--
    Shared "How we compare" section.
    Data source of truth: App\Modules\Common\Support\ComparisonContent.
    Props:
      $compact (bool, default false) — when true, hides the wide matrix and
                                       shows only the head-to-head selector
                                       plus a CTA linking to /pricing#compare.
      $anchorId (string, default 'compare') — id used on the <section>.
      $sectionClass (string, default '') - extra classes for the <section>.
                                       The homepage passes 'sec-rule' so this
                                       band gets the same top hairline as the
                                       bands around it; other pages pass
                                       nothing and are unaffected.
      $only (string|null, default null) — when set to a rival key (e.g.
                                       'linktree'), locks the head-to-head to
                                       that rival, hides the rival selector and
                                       the full-matrix toggle. Used by the
                                       dedicated /compare/{competitor} pages.
      $hideHeading (bool, default false) — hide the built-in section heading
                                       (the comparison pages supply their own).
      $teaser (bool, default false) — when true, render only the heading and a
                                       single CTA button linking to the full
                                       comparison on /pricing#compare. Skips the
                                       rival selector, VS cards, win counter and
                                       all matrices. Used by the homepage.
--}}
@php
    use App\Modules\Common\Support\ComparisonContent;

    $compact     = $compact  ?? false;
    $anchorId    = $anchorId ?? 'compare';
    $only        = $only ?? null;
    $hideHeading = $hideHeading ?? false;
    $teaser      = $teaser ?? false;

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

<section id="{{ $anchorId }}" class="sec-rule {{ $sectionClass ?? '' }} py-20 lg:py-28 relative overflow-hidden">
    <div class="mesh-bg" aria-hidden="true"></div>
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- Heading --}}
        @unless($hideHeading)
        <div class="text-center mb-10 max-w-2xl mx-auto">
            <div data-anim="fade-up" class="text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c4)">{{ $eyebrowOverride ?? 'How we compare' }}</div>
            <h2 data-anim="fade-up" class="text-4xl sm:text-5xl font-bold tracking-tight mb-4">
                {{-- Rule 1: "Better deal." was gradient-clipped text. --}}
                More features. Better deal.
            </h2>
            <p data-anim="fade-up" class="text-gray-400">
                Pick any tool you already use: see exactly what Sayzio adds on top, including built-in AI, across
                <span class="text-white font-semibold">{{ $__cmpTotal }} features</span> and
                <span class="text-white font-semibold">{{ count($__cmpCompetitors) - 1 }} competitors</span>.
            </p>
        </div>
        @endunless

        @if($teaser)
        {{-- ========================================================
             TEASER — the homepage's version of this section.

             It used to be the heading and a single button. A band of white
             space with "See the full comparison" in the middle of it asks a
             visitor to click through on trust: it makes a claim ("more
             features, better deal") and then offers no evidence for it, so
             there is no reason to go and look.

             It is a panel now, and everything in it is DERIVED from
             ComparisonContent rather than written here -- the scores, the
             leading rival, and the features nothing else on the list has.
             That matters twice over: the numbers cannot drift out of step
             with the full matrix the button leads to, and nobody has to
             remember to update marketing copy when a competitor ships
             something.
             ======================================================== --}}
        @php
            // Rivals by score, so the panel can name the closest one rather
            // than an arbitrary first entry.
            $__tzRivals = [];
            foreach ($__cmpRivals as $__r) {
                $__tzRivals[] = $__r + ['score' => (int) ($__cmpScores[$__r['key']] ?? 0)];
            }
            usort($__tzRivals, fn ($a, $b) => $b['score'] <=> $a['score']);
            $__tzBest = $__tzRivals[0] ?? null;

            // Features Sayzio has and NO rival on the list does. This is the
            // most persuasive thing in the data, and it was not on the page.
            $__tzOnly = [];
            foreach ($__cmpFeaturesFlat as [$__name, $__matrix]) {
                if (empty($__matrix['ours'])) { continue; }
                $__rivalHits = array_filter(array_diff_key($__matrix, ['ours' => 1]));
                if (count($__rivalHits) === 0) { $__tzOnly[] = $__name; }
            }
        @endphp
        <div data-anim="fade-up" class="cmpt">
            <div class="cmpt-main">
                <p class="cmpt-eyebrow">Feature for feature</p>
                <p class="cmpt-lead">
                    <strong>{{ $__cmpScores['ours'] ?? $__cmpTotal }} of {{ $__cmpTotal }}.</strong>
                    @if($__tzBest)
                        The closest tool on the list, {{ $__tzBest['name'] }}, does {{ $__tzBest['score'] }}.
                    @endif
                </p>

                @if(!empty($__tzOnly))
                    <p class="cmpt-h">{{ count($__tzOnly) }} of them, nobody else has at all</p>
                    <ul class="cmpt-only">
                        @foreach($__tzOnly as $__f)
                            <li><i class="fas fa-check" aria-hidden="true"></i>{{ $__f }}</li>
                        @endforeach
                    </ul>
                @endif

                <a href="{{ url('/pricing') }}#compare" class="cmp-cta cmpt-cta">
                    <i class="fas fa-table-cells-large"></i>
                    See all {{ $__cmpTotal }} side by side
                    <i class="fas fa-arrow-right text-xs"></i>
                </a>
            </div>

            {{-- The scoreboard. Bars rather than numbers alone: the gap is the
                 argument, and a reader takes a length in faster than a pair of
                 integers. Widths are percentages of the same total the button
                 leads to. --}}
            <ul class="cmpt-board" aria-label="Features covered, out of {{ $__cmpTotal }}">
                <li class="cmpt-row cmpt-row--ours">
                    <span class="cmpt-name">Sayzio</span>
                    <span class="cmpt-bar"><span style="width:{{ round((($__cmpScores['ours'] ?? $__cmpTotal) / max($__cmpTotal, 1)) * 100) }}%"></span></span>
                    <span class="cmpt-num">{{ $__cmpScores['ours'] ?? $__cmpTotal }}</span>
                </li>
                @foreach($__tzRivals as $__r)
                    <li class="cmpt-row">
                        <span class="cmpt-name">{{ $__r['name'] }}</span>
                        <span class="cmpt-bar"><span style="width:{{ round(($__r['score'] / max($__cmpTotal, 1)) * 100) }}%"></span></span>
                        <span class="cmpt-num">{{ $__r['score'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
        @else

        {{-- ========================================================
             HEAD-TO-HEAD (Alpine tabs)
             ======================================================== --}}
        <div
            data-anim="fade-up"
            class="cmp-h2h"
            x-data="{
                rival: '{{ $__cmpInitialRival }}',
                /* One table, not two. `showAll` used to decide whether a
                   SECOND table appeared below the first, so /pricing showed
                   the same 24 rows twice -- once as Sayzio vs the selected
                   tool, and again as Sayzio vs all six. It now decides how
                   many columns the one table has. */
                showAll: false,
                /* Most of these rows are ticked by everyone, and a row every
                   tool has is not a comparison -- it is padding. This hides
                   them, so what is left is only where the tools actually
                   disagree. */
                diffOnly: false,
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
                /* The table's own column count. Collapsed it is us and the
                   selected tool; expanded, every tool. */
                cols(){ return this.showAll ? {{ count($__cmpCompetitors) }} : 2; },
                gridStyle(){ return 'grid-template-columns: minmax(200px, 1.6fr) repeat(' + this.cols() + ', minmax(94px, 1fr))'; },
                spanStyle(){ return 'grid-column: span ' + (this.cols() + 1); },
                colShown(key){ return key === 'ours' || this.showAll || key === this.rival; },
                /* A row is interesting when somebody disagrees about it --
                   measured against the tools currently on screen, so the
                   filter means the same thing collapsed as expanded. */
                rowDiffers(sup){
                    const ours = !!sup.ours;
                    if (this.showAll) {
                        return this.rivals.some(r => !!sup[r.key] !== ours);
                    }
                    return !!sup[this.rival] !== ours;
                },
                rowShown(sup){ return !this.diffOnly || this.rowDiffers(sup); },
                groupShown(sups){ return !this.diffOnly || sups.some(s => this.rowDiffers(s)); },
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
                <span class="text-xs font-bold uppercase tracking-wider text-gray-400 mr-1">Compare Sayzio vs</span>
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
                    {{-- No icon. The name carries itself, and a bolt in front
                         of it was doing nothing the word was not. --}}
                    <div class="cmp-vs-name">Sayzio</div>
                    <div class="cmp-vs-tagline">The whole growth stack</div>
                    <div class="cmp-vs-meta">
                        <span class="cmp-vs-score"><span x-text="ourAnim">{{ $__cmpScores['ours'] }}</span><span class="cmp-vs-score-total">/{{ $__cmpTotal }}</span></span>
                        <span class="cmp-vs-bar"><span class="cmp-vs-bar-fill cmp-vs-bar-ours" :style="`width:${(ourAnim/{{ $__cmpTotal }})*100}%`"></span></span>
                    </div>
                </div>

                {{-- Center VS badge --}}
                <div class="cmp-vs-center">
                    <div class="cmp-vs-badge" aria-hidden="true">
                        <span>VS</span>
                    </div>
                    <div class="cmp-vs-wins" x-show="wins() > 0" x-cloak>
                        <span class="cmp-vs-wins-num" x-text="winsAnim">0</span>
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
                            <span class="font-extrabold text-lg" x-text="winsAnim">0</span>
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

            {{-- ════════════════════════════════════════════════════════
                 ONE TABLE.

                 There were two, stacked, showing the same 24 rows: a
                 Sayzio-vs-selected-tool table, and directly under it the
                 same rows again across all six. Sana: "both of them are
                 same... need only 1".

                 So the toggle no longer reveals a second table -- it widens
                 this one. Collapsed it is two columns, us and the tool you
                 picked; expanded it is all of them. Same rows, same marks,
                 same header, one object on the page.

                 Two things the old pair could not do, and the reason this is
                 worth more than a deletion:

                   - "Only where they differ" hides every row all the tools
                     tick. Fourteen of twenty-four rows are unanimous; they
                     are not a comparison, they are padding around one.
                   - A row only Sayzio has is MARKED as one. That is the most
                     persuasive fact in the data and the table never said it
                     -- you had to read twenty-four rows and notice.
                 ════════════════════════════════════════════════════════ --}}
            @php
                // Per row: does anyone but us have it? Computed once here
                // rather than re-derived in the browser.
                $__rowMeta = [];
                foreach ($__cmpGroups as $__g => $__rows) {
                    foreach ($__rows as [$__label, $__support]) {
                        $__rivalsWith = array_filter(array_diff_key($__support, ['ours' => 1]));
                        $__rowMeta[$__g][$__label] = [
                            'onlyOurs' => ! empty($__support['ours']) && count($__rivalsWith) === 0,
                        ];
                    }
                }
                $__onlyOursCount = 0;
                foreach ($__rowMeta as $__g => $__rs) {
                    foreach ($__rs as $__m) { if ($__m['onlyOurs']) { $__onlyOursCount++; } }
                }
            @endphp

            {{-- The table's controls sit above the table, because that is
                 what they control. They used to sit between the two tables,
                 where the button read as a footer to one and a header to the
                 other. --}}
            <div class="cmp-controls">
                <button type="button" class="cmp-switch" :class="diffOnly ? 'is-on' : ''"
                        @click="diffOnly = !diffOnly" :aria-pressed="diffOnly">
                    <span class="cmp-switch-track" aria-hidden="true"><span class="cmp-switch-dot"></span></span>
                    Only where they differ
                </button>

                @if($__cmpShowMatrix)
                    <button type="button" class="cmp-switch" :class="showAll ? 'is-on' : ''"
                            @click="showAll = !showAll" :aria-pressed="showAll">
                        <span class="cmp-switch-track" aria-hidden="true"><span class="cmp-switch-dot"></span></span>
                        <span x-text="showAll ? 'All {{ count($__cmpCompetitors) - 1 }} tools' : 'All {{ count($__cmpCompetitors) - 1 }} tools'">All tools</span>
                    </button>
                @endif

                <span class="cmp-controls-spacer" aria-hidden="true"></span>

                @if($__cmpOnlyKey)
                    <a href="{{ url('/compare') }}" class="cmp-cta">
                        Compare against every tool <i class="fas fa-arrow-right text-xs"></i>
                    </a>
                @else
                    @foreach(array_slice($__cmpCompetitors, 1) as $c)
                        <template x-if="rival === '{{ $c['key'] }}'">
                            <a href="{{ url('/compare/' . $c['key']) }}" class="cmp-cta">
                                Full Sayzio vs {{ $c['name'] }} page <i class="fas fa-arrow-right text-xs"></i>
                            </a>
                        </template>
                    @endforeach
                @endif
            </div>

            <div class="cmp-wrap">
                <div class="cmp-matrix-scroll">
                    <div class="cmp-matrix" :style="gridStyle()"
                         style="grid-template-columns: minmax(200px, 1.6fr) repeat(2, minmax(94px, 1fr));">

                        {{-- The band behind our column. Not an animated glow
                             any more -- a quiet tint with the brand ribbon
                             along its top edge, which is rule 6. --}}
                        <div class="cmp-ours-band cmp-ours-band-grid" aria-hidden="true"></div>

                        {{-- Header --}}
                        <div class="cmp-cell cmp-head">Feature</div>
                        @foreach($__cmpCompetitors as $c)
                            <div class="cmp-cell cmp-head text-center {{ $c['isOurs'] ? 'cmp-head-ours' : '' }}"
                                 @unless($c['isOurs']) x-show="colShown('{{ $c['key'] }}')" @endunless>
                                @if($c['isOurs'])
                                    <span class="cmp-brand-ours">{{ $c['name'] }}</span>
                                @else
                                    <span class="cmp-head-rival">{{ $c['name'] }}</span>
                                @endif
                            </div>
                        @endforeach

                        {{-- Rows, grouped --}}
                        @foreach($__cmpGroups as $groupName => $rows)
                            @php $__groupSupports = array_map(fn ($r) => $r[1], $rows); @endphp
                            <div class="cmp-cell cmp-group-head"
                                 x-show="groupShown(@js($__groupSupports))"
                                 :style="spanStyle()"
                                 style="grid-column: span 3;">{{ $groupName }}</div>

                            @foreach($rows as [$label, $support])
                                @php $__only = $__rowMeta[$groupName][$label]['onlyOurs'] ?? false; @endphp
                                <div class="cmp-cell cmp-row-cell {{ $__only ? 'is-only' : '' }}"
                                     x-show="rowShown(@js($support))">
                                    <span class="cmp-feature">
                                        {{ $label }}
                                        @if($__only)
                                            <span class="cmp-only" title="No other tool on this list has it">only on Sayzio</span>
                                        @endif
                                    </span>
                                </div>
                                @foreach($__cmpCompetitors as $c)
                                    <div class="cmp-cell cmp-row-cell text-center {{ $__only ? 'is-only' : '' }}"
                                         x-show="rowShown(@js($support)){{ $c['isOurs'] ? '' : " && colShown('" . $c['key'] . "')" }}">
                                        @if(!empty($support[$c['key']]))
                                            <span class="cmp-mark {{ $c['isOurs'] ? 'cmp-mark-yes-ours' : 'cmp-mark-yes' }}" aria-label="Included">
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path d="M5 12.5l4.5 4.5L19 7"/>
                                                </svg>
                                            </span>
                                        @else
                                            <span class="cmp-mark cmp-mark-no" aria-label="Not included">
                                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true">
                                                    <path d="M6 12h12"/>
                                                </svg>
                                            </span>
                                        @endif
                                    </div>
                                @endforeach
                            @endforeach
                        @endforeach

                        {{-- Totals --}}
                        <div class="cmp-cell cmp-row-cell cmp-total-label">Total features</div>
                        @foreach($__cmpCompetitors as $c)
                            <div class="cmp-cell cmp-row-cell text-center"
                                 @unless($c['isOurs']) x-show="colShown('{{ $c['key'] }}')" @endunless>
                                <span class="cmp-badge {{ $c['isOurs'] ? 'cmp-badge-ours' : '' }}">
                                    {{ $__cmpScores[$c['key']] }}/{{ $__cmpTotal }}
                                </span>
                            </div>
                        @endforeach

                        <div class="cmp-cell cmp-row-cell cmp-total-label">The bottom line</div>
                        @foreach($__cmpCompetitors as $c)
                            <div class="cmp-cell cmp-row-cell text-center"
                                 @unless($c['isOurs']) x-show="colShown('{{ $c['key'] }}')" @endunless>
                                <span class="cmp-badge {{ $c['isOurs'] ? 'cmp-badge-ours' : '' }}">{{ $c['badge'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="cmp-foot">
                    @if($__onlyOursCount > 0)
                        <span><span class="cmp-only">only on Sayzio</span> marks the {{ $__onlyOursCount }} {{ \Illuminate\Support\Str::plural('feature', $__onlyOursCount) }} no other tool on this list has.</span>
                    @endif
                    <span class="cmp-foot-swipe"><i class="fas fa-arrows-left-right" aria-hidden="true"></i> Swipe to see every column</span>
                </div>
            </div>
        </div>
        @endif

        @unless($teaser)
        <p data-anim="fade-up" class="text-center text-xs text-gray-400 mt-6">
            Comparison reflects publicly listed feature sets at time of writing. We never quote a competitor's price.
        </p>
        @endunless
    </div>
</section>
