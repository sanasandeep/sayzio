{{-- ============================ WHAT YOU CAN CREATE (LINK TYPES) ============================ --}}
@php
    // Admin-editable from the `home` SitePage (extra.link_types). Falls back
    // to the shared SitePagesContent defaults when the controller didn't pass
    // a list (e.g. the partial is rendered in isolation).
    $__linkTypes = (!empty($linkTypes) && is_array($linkTypes))
        ? $linkTypes
        : \App\Modules\Common\Support\SitePagesContent::homeLinkTypesDefault();

@endphp
<section id="create" class="py-24 lg:py-32 relative overflow-hidden" aria-labelledby="create-h">
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-14 max-w-3xl mx-auto">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c1)">What you can create</div>
            <h2 id="create-h" class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mb-5">
                {{ count($__linkTypes) }} kinds of link.<br><span class="grad-text">One simple dashboard.</span>
            </h2>
            <p class="reveal rd-2 text-lg text-gray-400">
                A short link is just the start. Spin up a chat page, a slide story, a digital menu, a review wall and more — your AI helps draft each one, and every one is tracked and shareable from a single URL.
            </p>
        </div>

        {{-- ════ The link-type wall ════
             A bento of all the link types: large cells for the featured ones,
             small for the rest, and every cell opens a modal for that type.
             There is no rotation and no selected state; the section shows
             everything at once and clicking does exactly one thing.
             Data source: $__linkTypes (admin-editable via SitePage
             extra.link_types, falling back to homeLinkTypesDefault()). ════ --}}
        @php
            /*
             * Bento order. The grid is four columns with dense packing; a
             * featured type takes a 2x2 cell and the rest take 1x1, so source
             * order decides the rhythm. Left alone, the admin list puts every
             * featured type first, which packs into a block of large cells
             * followed by a block of small ones: a grid, not a bento.
             *
             * Featured comes from the admin-editable list, so changing which
             * types are large is an admin edit, not a deploy.
             */
            $__ltAll   = array_values($__linkTypes);
            $__ltBig   = array_values(array_filter($__ltAll, fn ($t) => !empty($t['featured'])));
            $__ltSmall = array_values(array_filter($__ltAll, fn ($t) => empty($t['featured'])));

            /*
             * Bands, not a running interleave. In a four-column grid a large
             * cell is 4 units and a small one is 1, and only three groupings
             * fill whole rows with no gap:
             *
             *   A  one large + four small   (8 units, 2 rows)
             *   B  two large                (8 units, 2 rows)
             *   C  four small               (4 units, 1 row)
             *
             * Emitting whole bands means the wall closes flush at the bottom
             * instead of ending on a hole, which is what a running interleave
             * leaves behind. With the current six featured and twelve others
             * it solves to A B A B C: two large cells stacked beside four
             * small, twice, then a closing row of four.
             *
             * a is capped so the large cells that are left over are an even
             * number (they pair into B bands); anything that will not divide
             * cleanly falls through to a simple alternation and lets `dense`
             * tidy up, so an admin toggling `featured` can never break it.
             */
            $__nBig   = count($__ltBig);
            $__nSmall = count($__ltSmall);
            $__ltForEach = [];

            $__a = min($__nBig, intdiv($__nSmall, 4));
            if ((($__nBig - $__a) % 2) !== 0 && $__a > 0) { $__a--; }
            $__c = intdiv($__nSmall, 4) - $__a;
            $__tiles = ($__nSmall % 4 === 0) && (($__nBig - $__a) % 2 === 0) && $__c >= 0;

            if ($__tiles) {
                $__b = intdiv($__nBig - $__a, 2);
                $__bi = 0; $__si = 0;
                $__bands = [];
                for ($k = 0; $k < max($__a, $__b); $k++) {
                    if ($k < $__a) { $__bands[] = 'A'; }
                    if ($k < $__b) { $__bands[] = 'B'; }
                }
                for ($k = 0; $k < $__c; $k++) { $__bands[] = 'C'; }

                foreach ($__bands as $__band) {
                    if ($__band === 'A') {
                        $__ltForEach[] = $__ltBig[$__bi++];
                        for ($k = 0; $k < 4; $k++) { $__ltForEach[] = $__ltSmall[$__si++]; }
                    } elseif ($__band === 'B') {
                        $__ltForEach[] = $__ltBig[$__bi++];
                        $__ltForEach[] = $__ltBig[$__bi++];
                    } else {
                        for ($k = 0; $k < 4; $k++) { $__ltForEach[] = $__ltSmall[$__si++]; }
                    }
                }
            } else {
                $__si = 0;
                for ($__bi = 0; $__bi < $__nBig; $__bi++) {
                    $__ltForEach[] = $__ltBig[$__bi];
                    $__upto = (int) round((($__bi + 1) * $__nSmall) / max(1, $__nBig));
                    while ($__si < $__upto && $__si < $__nSmall) { $__ltForEach[] = $__ltSmall[$__si++]; }
                }
                while ($__si < $__nSmall) { $__ltForEach[] = $__ltSmall[$__si++]; }
            }

        @endphp
        <style>
            /* ═══ The link-type wall ═══ */
            .lt-chip{display:inline-flex;align-items:center;gap:6px;padding:6px 13px 6px 8px;border-radius:9999px;white-space:nowrap;font-size:13px;font-weight:600;cursor:pointer;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.04);color:rgba(255,255,255,.6);transition:color .2s,border-color .2s,background .2s;flex-shrink:0;line-height:1}
            html.light-mode .lt-chip{color:rgba(0,0,0,.55);border-color:rgba(0,0,0,.1);background:rgba(0,0,0,.03)}
            .lt-chip:hover{color:rgba(255,255,255,.9);border-color:rgba(255,255,255,.2);background:rgba(255,255,255,.08)}
            html.light-mode .lt-chip:hover{color:rgba(0,0,0,.8);border-color:rgba(0,0,0,.18);background:rgba(0,0,0,.06)}
            .lt-chip-on{color:#fff!important}
            html.light-mode .lt-chip-on{color:rgba(0,0,0,.85)!important}
            .lt-chip-ico{width:22px;height:22px;border-radius:7px;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;font-size:10px}
            .lt-chip-new{font-size:7px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;padding:1px 5px;border-radius:9999px;background:rgba(255,255,255,.1);border:1px solid;margin-left:2px;line-height:1.5}
            html.light-mode .lt-chip-new{background:rgba(0,0,0,.07)}

        
            /* ---------- all eighteen at once ----------
               The rail was a horizontal scroller: 2858px of chips inside
               1216px of space, so fewer than half the link types were ever
               visible and the rest were behind a scroll nobody makes. They
               wrap now, centred, three tidy rows, nothing hidden. */
            .lt-rail{
                flex-wrap:wrap;overflow:visible;justify-content:center;
                gap:7px;padding:0 0 18px;
                -webkit-mask-image:none;mask-image:none;animation:none
            }

            /* The autoplay is 4.2s per type and was invisible. The active
               chip now fills a rule underneath itself, so the switch is
               expected rather than startling, and hovering pauses both. */
            .lt-chip{position:relative;overflow:hidden}
            .lt-chip-on::after{
                content:"";position:absolute;left:0;bottom:0;height:2px;width:100%;
                transform-origin:left;background:currentColor;opacity:.55;
                animation:lt-progress 4.2s linear forwards
            }
            .lt-rail:hover .lt-chip-on::after{animation-play-state:paused}
            @keyframes lt-progress{from{transform:scaleX(0)}to{transform:scaleX(1)}}

            /* Three rows of six. Left to wrap at the full column width the
               chips fell 8/8/2, and the orphan pair in the middle of an empty
               third row was the ragged part. Capping the rail balances them. */
            .lt-rail{max-width:1040px;margin-left:auto;margin-right:auto}

            /* The panel was 300px of text beside 914px of preview holding a
               194px drawing: a small picture adrift in a large empty box.
               A shorter zone, a wider text column and a drawing scaled up to
               meet it. */
            /* The reduced-motion carve-out for the chip progress rule, which
               the card layout hides anyway. */
            @media(prefers-reduced-motion:reduce){
                .lt-chip-on::after{animation:none;transform:scaleX(1)}
            }

            /* ---------- the rail becomes a card grid ----------
               Eighteen pills said the names and nothing else, and the one
               description on the page belonged to whichever pill happened to
               be selected. As cards they each carry their own line, so the
               section answers "what are the eighteen" without anyone having
               to click through them one at a time. */
            /* ── Bento. Four columns of equal width; a featured type takes a
               2x2 cell, everything else a 1x1. `dense` lets a small cell
               backfill a gap a large one leaves, so the wall closes up
               instead of leaving holes. Row height is a floor, not a fixed
               value: a long description makes its row taller and the large
               cells beside it follow, so nothing is ever clipped. ── */
            .lt-rail{
                display:grid;gap:14px;max-width:1180px;margin:0 auto 26px;
                grid-template-columns:repeat(4,1fr);
                grid-auto-rows:minmax(132px,auto);
                grid-auto-flow:row dense;
                overflow:visible;padding:0
            }
            @media (max-width:980px){
                .lt-rail{grid-template-columns:repeat(2,1fr);grid-auto-rows:minmax(124px,auto)}
            }
            @media (max-width:560px){
                .lt-rail{grid-template-columns:1fr;grid-auto-rows:auto}
                .lt-chip-big{grid-column:auto !important;grid-row:auto !important}
            }
            /* A ruled outline on the page's own ground, the way the reference
               does it: no fill, no tint, no second surface. Every card is the
               same neutral, so the only colour in the wall is the icon and the
               corner control, and nothing looks selected when it is not. */
            .lt-chip{
                position:relative;display:flex;flex-direction:column;align-items:stretch;
                gap:0;white-space:normal;text-align:left;
                padding:15px 15px 0;border-radius:14px;line-height:1.35;
                height:100%;overflow:hidden;
                background:transparent;
                border:1px solid rgba(255,255,255,.11);
                transition:border-color .18s ease
            }
            html.light-mode .lt-chip{background:transparent;border-color:rgba(0,0,0,.11)}
            .lt-chip:hover{border-color:rgba(255,255,255,.24);background:transparent}
            html.light-mode .lt-chip:hover{border-color:rgba(0,0,0,.22);background:transparent}
            .lt-chip-big{grid-column:span 2;grid-row:span 2;padding:22px 22px 0}

            .lt-chip-head{display:flex;align-items:center;gap:9px;width:100%;margin-bottom:9px;padding-right:34px}
            .lt-chip-name{font-size:15px;font-weight:700;letter-spacing:-.01em}

            /* ── The large cell. Its own composition rather than the small one
               scaled up: the name leads at display size, the description gets
               room to be read, and the type's icon returns as a large, faint
               graphic bleeding off the corner so the cell has a subject rather
               than empty space. When the real page previews land they take
               that lower area; the glyph is what holds it until then. ── */
            .lt-chip-big .lt-chip-head{margin-bottom:14px;padding-right:44px}
            .lt-chip-big .lt-chip-ico{width:32px;height:32px;border-radius:10px}
            .lt-chip-big .lt-chip-ico i{font-size:14px !important}
            .lt-chip-big .lt-chip-name{font-size:22px;letter-spacing:-.02em;line-height:1.2}
            .lt-chip-big .lt-chip-desc{font-size:14.5px;line-height:1.6;opacity:.78;max-width:34ch}
            /* The usage line, on the large cells only. It is the one piece of
               copy that says what the type is FOR rather than what it is, and
               a large cell has the room the small ones do not. */
            .lt-chip-usage{
                margin-top:auto;padding:14px 0 18px;
                border-top:1px solid rgba(255,255,255,.09);
                font-size:13.5px;line-height:1.6;opacity:.66;max-width:44ch;
                font-weight:400
            }
            html.light-mode .lt-chip-usage{border-top-color:rgba(0,0,0,.09)}
            .lt-chip-usage-label{
                display:block;font-size:10px;font-weight:700;letter-spacing:.13em;
                text-transform:uppercase;opacity:.7;margin-bottom:6px;
                color:var(--lt-accent,#3E3AE0)
            }
            html.light-mode .lt-chip .lt-chip-usage-label{color:var(--lt-accent,#3E3AE0) !important}
            .lt-chip-glyph{
                position:absolute;right:-16px;bottom:-26px;pointer-events:none;
                font-size:132px;line-height:1;color:var(--lt-accent,#3E3AE0);
                opacity:.13;transition:opacity .25s ease,transform .25s ease;
                transform:rotate(-8deg)
            }
            .lt-chip-big:hover .lt-chip-glyph{opacity:.2;transform:rotate(-8deg) scale(1.04)}
            html.light-mode .lt-chip-glyph{opacity:.11}
            @media (prefers-reduced-motion:reduce){
                .lt-chip-glyph,.lt-chip-big:hover .lt-chip-glyph{transition:none;transform:rotate(-8deg)}
            }
            /* No clamp. The grid equalises the row, so a longer line makes the
               row taller rather than cutting a sentence off mid-word — which is
               what the two-line clamp was doing to most of the eighteen. */
            .lt-chip-desc{font-size:12.9px;font-weight:400;line-height:1.55;opacity:.72}

            /* The progress rule belongs to the old pill; on a card it would
               underline the description. */
            .lt-chip-on::after{display:none}

            /* The expand control. A span rather than a button, because the card
               is already a button and a button inside a button is not valid
               markup. It sits in flow at the foot of the card and is ALWAYS
               visible: as a hover-only icon at opacity 0 it was undiscoverable,
               so most visitors never learned the cards opened at all. */
            .lt-chip-open{
                position:absolute;top:13px;right:13px;
                width:28px;height:28px;border-radius:9px;
                display:inline-flex;align-items:center;justify-content:center;
                background:color-mix(in srgb, var(--lt-accent,#3E3AE0) 16%, transparent);
                color:var(--lt-accent,#3E3AE0);
                transition:background .16s ease,color .16s ease,transform .16s ease
            }
            .lt-chip-big .lt-chip-open{top:18px;right:18px;width:34px;height:34px;border-radius:11px}
            /* Filled on hover, exactly as the reference does it: the control
               is legible at rest and unmistakable once you are on the card. */
            .lt-chip:hover .lt-chip-open,.lt-chip-open:focus-visible{
                background:var(--lt-accent,#3E3AE0);color:#fff;transform:scale(1.06)
            }
            /* public/partials/surfaces.blade.php forces every span inside a chip to the
               body ink in light mode so labels can never vanish; this control
               is the deliberate exception, and outranks it on specificity. */
            html.light-mode .lt-chip .lt-chip-open{color:var(--lt-accent,#3E3AE0) !important}
            html.light-mode .lt-chip:hover .lt-chip-open{color:#fff !important}
            .lt-chip-open svg{
                width:13px;height:13px;fill:none;stroke:currentColor;
                stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round;
                flex-shrink:0
            }
            .lt-chip-big .lt-chip-open svg{width:15px;height:15px}
            /* The label is for screen readers and for browsers without
               color-mix; it never takes layout space. */
            .lt-chip-open span{
                position:absolute;width:1px;height:1px;overflow:hidden;
                clip:rect(0 0 0 0);clip-path:inset(50%);white-space:nowrap
            }
            @media (prefers-reduced-motion:reduce){
                .lt-chip-open,.lt-chip:hover .lt-chip-open{transition:none;transform:none}
            }

</style>

        {{-- No Alpine here any more. There was a rotating "active" index that
             drove a panel below the grid: clicking a card filled that panel and
             a timer cycled it every 4.2 seconds. The panel is gone, so the
             selected state, the auto-rotation, the pause-on-hover and the dot
             navigation went with it. A card now does one thing: it opens. --}}
        <div class="lt-spotlight reveal rd-1">

            {{-- ── The wall ── --}}
            <div class="lt-rail" aria-label="Link types">
                @foreach($__ltForEach as $i => $lt)
                <button type="button" class="lt-chip {{ empty($lt['featured']) ? '' : 'lt-chip-big' }}"
                        style="--lt-accent:{{ $lt['color'] }}"
                        data-lt-open="{{ $i }}"
                        data-lt-slug="{{ \Illuminate\Support\Str::slug($lt['name']) }}"
                        aria-label="Preview {{ $lt['name'] }}">
                    <span class="lt-chip-head">
                        <span class="lt-chip-ico" style="background:{{ $lt['color'] }}"><i class="fas {{ $lt['icon'] }}" style="color:#fff;font-size:10px"></i></span>
                        <span class="lt-chip-name">{{ $lt['name'] }}</span>
                        @if($lt['new'])<span class="lt-chip-new" style="color:{{ $lt['color'] }};border-color:{{ $lt['color'] }}55">New</span>@endif
                    </span>
                    <span class="lt-chip-desc">{{ $lt['desc'] }}</span>
                    @if(!empty($lt['featured']))
                        @php
                            $__cardUsage = \App\Modules\Common\Support\LinkTypeUsage::forName($lt['name']);
                        @endphp
                        @if($__cardUsage !== '')
                            <span class="lt-chip-usage">
                                <span class="lt-chip-usage-label">How you would use it</span>
                                {{ $__cardUsage }}
                            </span>
                        @endif
                    @endif
                    {{-- The corner control is a marker now, not a target. The
                         whole card carries data-lt-open, so anywhere on it
                         opens the modal and there is no small square to hit. --}}
                    <span class="lt-chip-open" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M14 4h6v6M20 4l-7.5 7.5M10 20H4v-6M4 20l7.5-7.5"/></svg>
                    </span>
                    @if(!empty($lt['featured']))
                        <i class="fas {{ $lt['icon'] }} lt-chip-glyph" aria-hidden="true"></i>
                    @endif
                </button>
                @endforeach
            </div>
        </div>
        <div class="reveal rd-3 mt-12 text-center">
            <a href="{{ route('site.features') }}#cat-link-types" class="inline-flex items-center gap-2 px-6 py-3 glass rounded-full text-sm font-bold lift border border-white/10 hover:border-white/20 transition">
                See every link type
                <i class="fas fa-arrow-right text-xs"></i>
            </a>
        </div>
    </div>
</section>
