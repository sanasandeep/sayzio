{{-- ============================ WHAT YOU CAN CREATE (LINK TYPES) ============================ --}}
@php
    // Admin-editable from the `home` SitePage (extra.link_types). Falls back
    // to the shared SitePagesContent defaults when the controller didn't pass
    // a list (e.g. the partial is rendered in isolation).
    $__linkTypes = (!empty($linkTypes) && is_array($linkTypes))
        ? $linkTypes
        : \App\Modules\Common\Support\SitePagesContent::homeLinkTypesDefault();

    // Map each card to its seeded /demos explainer page (alias
    // `demo-type-{slug(name)}`) when one is live, falling back to the
    // Features page link-types anchor otherwise. Reuses the same cached
    // gallery data as /demos (5-min TTL, plain arrays) so the home render
    // stays query-free on the warm path; any failure degrades to the
    // Features fallback for every card.
    $__demoAliasSet = [];
    try {
        $__demoData = \Illuminate\Support\Facades\Cache::remember(
            \App\Modules\Common\Controllers\SitePageController::DEMOS_CACHE_KEY,
            300,
            fn () => \App\Modules\Common\Controllers\SitePageController::buildDemosData()
        );
        $__demoAliasSet = array_fill_keys(array_keys((array) ($__demoData['links'] ?? [])), true);
    } catch (\Throwable $e) {
        $__demoAliasSet = [];
    }
    $__ltCardLink = function (array $lt) use ($__demoAliasSet): array {
        $name = trim((string) ($lt['name'] ?? ''));
        $alias = 'demo-type-' . \Illuminate\Support\Str::slug($name);
        if ($name !== '' && isset($__demoAliasSet[$alias])) {
            // `track` = the link-type slug recorded as a marketing event
            // (source "home_showcase_demo") so admins can see which
            // showcase cards actually drive demo visits.
            return [
                'href'  => url('/' . $alias),
                'label' => 'See demo',
                'aria'  => 'See the live ' . $name . ' demo',
                'track' => \Illuminate\Support\Str::slug($name),
            ];
        }
        return [
            'href'  => route('site.features') . '#cat-link-types',
            'label' => 'Learn more',
            'aria'  => ($name !== '' ? $name . ' — l' : 'L') . 'earn more on the Features page',
            'track' => null,
        ];
    };
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

        {{-- ════ Interactive spotlight — replaces the static two-tier card grid ════
             All 18 link types live in one rotating stage. The chip rail lets
             visitors pick any type instantly; auto-rotation cycles every 4.2 s
             and pauses on hover/interaction. Respects prefers-reduced-motion.
             Data source is unchanged: $__linkTypes (admin-editable via SitePage
             extra.link_types, falling back to homeLinkTypesDefault() above).       ════ --}}
        @php
            /*
             * Bento order. The grid is four columns with dense packing; a
             * featured type takes a 2x2 cell and the rest take 1x1, so source
             * order decides the rhythm. Left alone, the admin list puts every
             * featured type first, which packs into a block of large cells
             * followed by a block of small ones: a grid, not a bento. So the
             * two groups are interleaved to a fixed pattern instead.
             *
             * Featured comes from the admin-editable list, so changing which
             * types are large is an admin edit, not a deploy. The pattern
             * degrades cleanly at any count: whichever group runs out first,
             * the remainder is appended in its own order.
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

            $__ltCount   = count($__ltForEach);
            $__ltColors  = json_encode(array_column($__ltForEach, 'color'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        @endphp
        <style>
            /* ═══ Link-type spotlight chip rail + stage ═══ */
            .lt-rail{display:flex;gap:8px;overflow-x:auto;padding:0 2px 14px;scrollbar-width:thin;scrollbar-color:rgba(255,255,255,.1) transparent;-webkit-overflow-scrolling:touch}
            html.light-mode .lt-rail{scrollbar-color:rgba(0,0,0,.1) transparent}
            .lt-rail::-webkit-scrollbar{height:3px}
            .lt-rail::-webkit-scrollbar-thumb{background:rgba(255,255,255,.15);border-radius:3px}
            html.light-mode .lt-rail::-webkit-scrollbar-thumb{background:rgba(0,0,0,.12)}
            /* Mobile: right-edge fade cue signalling more chips off-screen. Pure CSS —
               static mask fallback everywhere; scroll-driven animation (where supported)
               shrinks the fade to nothing as the rail reaches its end. Mask only, no
               layout impact; desktop untouched. */
            @property --lt-fade{syntax:'<length>';inherits:false;initial-value:36px}
            @media(max-width:767px){
                .lt-rail{
                    --lt-fade:36px;
                    -webkit-mask-image:linear-gradient(to right,#000 calc(100% - var(--lt-fade)),transparent 100%);
                    mask-image:linear-gradient(to right,#000 calc(100% - var(--lt-fade)),transparent 100%);
                }
                @supports (animation-timeline: scroll(self x)){
                    .lt-rail{animation:lt-rail-fade linear both;animation-timeline:scroll(self x)}
                }
            }
            @keyframes lt-rail-fade{0%,85%{--lt-fade:36px}100%{--lt-fade:0px}}
            .lt-chip{display:inline-flex;align-items:center;gap:6px;padding:6px 13px 6px 8px;border-radius:9999px;white-space:nowrap;font-size:13px;font-weight:600;cursor:pointer;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.04);color:rgba(255,255,255,.6);transition:color .2s,border-color .2s,background .2s;flex-shrink:0;line-height:1}
            html.light-mode .lt-chip{color:rgba(0,0,0,.55);border-color:rgba(0,0,0,.1);background:rgba(0,0,0,.03)}
            .lt-chip:hover{color:rgba(255,255,255,.9);border-color:rgba(255,255,255,.2);background:rgba(255,255,255,.08)}
            html.light-mode .lt-chip:hover{color:rgba(0,0,0,.8);border-color:rgba(0,0,0,.18);background:rgba(0,0,0,.06)}
            .lt-chip-on{color:#fff!important}
            html.light-mode .lt-chip-on{color:rgba(0,0,0,.85)!important}
            .lt-chip-ico{width:22px;height:22px;border-radius:7px;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;font-size:10px}
            .lt-chip-new{font-size:7px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;padding:1px 5px;border-radius:9999px;background:rgba(255,255,255,.1);border:1px solid;margin-left:2px;line-height:1.5}
            html.light-mode .lt-chip-new{background:rgba(0,0,0,.07)}

            .lt-stage{display:flex;align-items:stretch;min-height:340px;border:1px solid rgba(255,255,255,.08);position:relative;overflow:hidden;border-radius:20px}
            html.light-mode .lt-stage{border-color:rgba(0,0,0,.08)}
            @media(max-width:767px){.lt-stage{flex-direction:column;min-height:auto}}
            .lt-blob{position:absolute;width:560px;height:560px;border-radius:9999px;filter:blur(130px);opacity:.14;pointer-events:none;top:-150px;right:-100px;z-index:0;transition:background .7s ease}
            html.light-mode .lt-blob{opacity:.07}
            @media(prefers-reduced-motion:reduce){.lt-blob{transition:none!important}}

            .lt-info-zone{flex:0 0 300px;max-width:300px;position:relative;border-right:1px solid rgba(255,255,255,.06);z-index:1}
            html.light-mode .lt-info-zone{border-right-color:rgba(0,0,0,.06)}
            @media(max-width:767px){.lt-info-zone{flex:none;max-width:none;width:100%;border-right:none;border-top:1px solid rgba(255,255,255,.06);order:2;min-height:248px}}
            @media(max-width:767px){html.light-mode .lt-info-zone{border-top-color:rgba(0,0,0,.06)}}
            html.light-mode .lt-info-zone .lt-pane{color:inherit}

            /* Sequential out-then-in: transition lives ONLY on .lt-pane-on (the active state).
               CSS picks the NEW state's transition on a class change, so:
               — activating (add lt-pane-on): new state has "opacity .32s ease" → smooth fade-in.
               — deactivating (remove lt-pane-on): new state has "transition:none" → instant hide.
               Result: outgoing pane disappears instantly; incoming pane fades in. Zero overlap. */
            .lt-pane{position:absolute;inset:0;padding:28px 26px;display:flex;flex-direction:column;justify-content:center;gap:11px;opacity:0;visibility:hidden;transition:none;pointer-events:none;z-index:1}
            .lt-pane-on{opacity:1;visibility:visible;transition:opacity .32s ease;pointer-events:auto}
            @media(prefers-reduced-motion:reduce){.lt-pane{transition:none;opacity:0;visibility:hidden}.lt-pane-on{opacity:1;visibility:visible;transition:none}}
            .lt-pane-icon{width:52px;height:52px;border-radius:16px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:20px;color:#fff}
            .lt-pane-badge{display:inline-flex;align-items:center;font-size:9px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;padding:2px 7px;border-radius:9999px;background:rgba(255,255,255,.07);border:1px solid;width:fit-content;margin-top:-2px}
            html.light-mode .lt-pane-badge{background:rgba(0,0,0,.06)}
            .lt-pane-name{font-size:21px;font-weight:800;line-height:1.2;color:#fff;margin:0}
            html.light-mode .lt-pane-name{color:#111827}
            .lt-pane-desc{font-size:13px;line-height:1.65;color:rgba(255,255,255,.6);flex:1}
            html.light-mode .lt-pane-desc{color:rgba(0,0,0,.58)}
            .lt-pane-cta{display:inline-flex;align-items:center;gap:5px;padding:9px 16px;border-radius:9999px;font-size:12.5px;font-weight:700;color:#fff!important;transition:filter .2s;width:fit-content;white-space:nowrap;margin-top:4px;border:none;cursor:pointer}
            html.light-mode .lt-pane-cta{color:#fff!important}
            .lt-pane-cta:hover{filter:brightness(1.15)}
            @media(prefers-reduced-motion:reduce){.lt-pane-cta{transition:none}}

            .lt-mock-zone{flex:1;min-width:0;position:relative;overflow:hidden;background:rgba(255,255,255,.015)}
            /* The mock visuals are inline-styled with white text on translucent-white cards
               (an always-dark "device preview" aesthetic, like the .lt-phone frames). In light
               mode the zone therefore stays a dark island so every mock remains legible. */
            html.light-mode .lt-mock-zone{background:linear-gradient(150deg,#171126 0%,#0d0918 70%)}
            @media(max-width:767px){.lt-mock-zone{flex:none;height:220px;order:1}}
            /* Same sequential out-then-in as .lt-pane: transition only on .lt-mock-on so deactivating
               is instant (no overlap) and activating fades in cleanly. */
            .lt-mock{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;opacity:0;visibility:hidden;transform:scale(.97) translateY(8px);transition:none;pointer-events:none}
            .lt-mock-on{opacity:1;visibility:visible;transform:none;transition:opacity .35s ease,transform .35s ease;pointer-events:auto}
            @media(prefers-reduced-motion:reduce){.lt-mock{transition:none;transform:none;opacity:0;visibility:hidden}.lt-mock-on{opacity:1;transform:none;visibility:visible;transition:none}}

            /* Phone frame for mock visuals — always-dark island, no light-mode flip needed */
            .lt-phone{width:156px;aspect-ratio:9/18;border-radius:28px;background:linear-gradient(150deg,#1c0d30 0%,#0d0516 70%);border:1.5px solid rgba(255,255,255,.13);box-shadow:0 24px 56px -18px rgba(0,0,0,.8),0 0 0 1px rgba(255,255,255,.05);overflow:hidden;position:relative;display:flex;flex-direction:column;padding:20px 10px 12px}
            .lt-phone::before{content:'';position:absolute;top:9px;left:50%;transform:translateX(-50%);width:38px;height:5px;border-radius:3px;background:rgba(255,255,255,.2);z-index:2}

            /* Dot nav */
            .lt-dots{position:absolute;bottom:12px;right:14px;display:flex;gap:4px;z-index:2;align-items:center}
            .lt-dot{width:5px;height:5px;border-radius:9999px;background:rgba(255,255,255,.22);border:none;padding:0;cursor:pointer;transition:width .25s ease,background .25s ease;flex-shrink:0}
            html.light-mode .lt-dot{background:rgba(0,0,0,.18)}
            .lt-dot-on{width:14px;background:rgba(255,255,255,.7)}
            html.light-mode .lt-dot-on{background:rgba(0,0,0,.45)}
            @media(prefers-reduced-motion:reduce){.lt-dot,.lt-dot-on{transition:none}}

            /* Typing animation for AI chatbot mock (always-dark element) */
            @keyframes lt-pulse{0%,100%{opacity:.4;transform:scale(.8)}50%{opacity:1;transform:scale(1.1)}}
        
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
            @media(min-width:768px){
                /* The drawing is 237px wide. Given a 777px zone it sat alone
                   in 540px of nothing, which is what still read as empty
                   after the first pass. The zone is now sized to the drawing
                   rather than to whatever space was left over, and the stage
                   itself is capped so the text column does not stretch to
                   fill the difference. */
                .lt-stage{max-width:1000px;margin-left:auto;margin-right:auto}
                .lt-info-zone{
                    flex:1 1 auto;max-width:none;
                    justify-content:center;padding-top:26px;padding-bottom:26px
                }
                .lt-mock-zone{flex:0 0 clamp(320px,34%,430px);height:284px}
                .lt-mock > *{transform:scale(1.22);transform-origin:center}
            }
            .lt-pane p{max-width:52ch}
            .lt-pane{padding-right:34px}
            /* An inner ring so the preview reads as a surface of its own
               rather than a hole in the card. */
            .lt-mock-zone{box-shadow:inset 0 0 0 1px rgba(255,255,255,.06)}
            html.light-mode .lt-mock-zone{box-shadow:inset 0 0 0 1px #EDEFF7}

            /* The call to action sat 135px below the sentence it belongs to,
               because the pane stretched to the panel's full height. It sits
               with its own text now. */
            .lt-pane{justify-content:center}
            .lt-pane-cta{margin-top:18px;align-self:flex-start}

            /* Panes rise as they fade, which reads as a change rather than
               a flicker. */
            .lt-pane-on{animation:lt-pane-in .38s cubic-bezier(.2,.7,.3,1) both}
            @keyframes lt-pane-in{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}

            @media(prefers-reduced-motion:reduce){
                .lt-chip-on::after{animation:none;transform:scaleX(1)}
                .lt-pane-on{animation:none}
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
            .lt-chip{
                position:relative;display:flex;flex-direction:column;align-items:stretch;
                gap:0;white-space:normal;text-align:left;
                padding:15px 15px 0;border-radius:14px;line-height:1.35;
                height:100%;overflow:hidden;
            }
            .lt-chip-big{grid-column:span 2;grid-row:span 2;padding:22px 22px 0}
            /* Each type's own colour, as a rail down the card's edge. The one
               piece of identity a flat card can carry without a gradient or a
               second surface; it also gives the selected card somewhere to
               show that it is selected. */
            .lt-chip::before{
                content:'';position:absolute;left:0;top:0;bottom:0;width:3px;
                background:var(--lt-accent,#3E3AE0);opacity:.45;
                transition:opacity .18s ease
            }
            .lt-chip:hover::before,.lt-chip-on::before{opacity:1}

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
            /* flat-surfaces.blade.php forces every span inside a chip to the
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

            /* The usage line only ever appears in the expanded card. */
            .lt-pane-usage{display:none;font-size:14px;line-height:1.55;margin-top:14px}
            .lt-pane-usage span{
                display:block;font-size:11px;font-weight:700;letter-spacing:.12em;
                text-transform:uppercase;opacity:.55;margin-bottom:5px
            }
</style>

        <div class="lt-spotlight reveal rd-1"
             x-data="{
                 active:0,
                 _n:{{ $__ltCount }},
                 _rm:false,_pa:false,_ti:null,
                 init(){
                     this._rm=window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                     if(!this._rm&&this._n>1)this._ti=setInterval(()=>{if(!this._pa)this.active=(this.active+1)%this._n;},4200);
                 },
                 destroy(){clearInterval(this._ti);},
                 pick(i){this.active=i;this._pa=true;setTimeout(()=>this._pa=false,9000);},
                 pause(){this._pa=true;},
                 resume(){this._pa=false;},
             }"
             @mouseenter="pause()" @mouseleave="resume()">

            {{-- ── Chip rail ── --}}
            <div class="lt-rail" role="tablist" aria-label="Link type">
                @foreach($__ltForEach as $i => $lt)
                <button type="button" class="lt-chip {{ empty($lt['featured']) ? '' : 'lt-chip-big' }} {{ $i === 0 ? 'lt-chip-on' : '' }}" role="tab"
                        style="--lt-accent:{{ $lt['color'] }}"
                        :class="{'lt-chip-on':active==={{ $i }}}"
                        :style="active==={{ $i }}?'border-color:{{ $lt['color'] }}55;background:{{ $lt['color'] }}18':''"
                        @click="pick({{ $i }})"
                        :aria-selected="active==={{ $i }}">
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
                    <span class="lt-chip-open" data-lt-open="{{ $i }}"
                          data-lt-slug="{{ \Illuminate\Support\Str::slug($lt['name']) }}"
                          role="button" tabindex="0"
                          aria-label="Preview {{ $lt['name'] }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 4h6v6M20 4l-7.5 7.5M10 20H4v-6M4 20l7.5-7.5"/></svg>
                        <span>Preview</span>
                    </span>
                    @if(!empty($lt['featured']))
                        <i class="fas {{ $lt['icon'] }} lt-chip-glyph" aria-hidden="true"></i>
                    @endif
                </button>
                @endforeach
            </div>

            {{-- ── Stage ── --}}
            <div class="lt-stage">
                {{-- Ambient blob transitions to each type's accent colour --}}
                <div class="lt-blob" aria-hidden="true"
                     :style="'background:'+{{ $__ltColors }}[active]"></div>

                {{-- Info panel (left on desktop, bottom on mobile) --}}
                <div class="lt-info-zone">
                    @foreach($__ltForEach as $i => $lt)
                    {{-- object :class syntax is required: Alpine's string syntax never removes a
                         class present in the static class attribute, so the server-rendered
                         lt-pane-on on slide 0 would stay stuck forever (permanent overlap). --}}
                    <div class="lt-pane {{ $i === 0 ? 'lt-pane-on' : '' }}"
                         :class="{'lt-pane-on':active==={{ $i }}}">
                        <div class="lt-pane-icon" style="background:{{ $lt['color'] }};box-shadow:0 10px 28px -10px {{ $lt['color'] }}bb">
                            <i class="fas {{ $lt['icon'] }}" style="color:#fff"></i>
                        </div>
                        @if($lt['new'])<span class="lt-pane-badge" style="color:{{ $lt['color'] }};border-color:{{ $lt['color'] }}55">New</span>@endif
                        <h3 class="lt-pane-name">{{ $lt['name'] }}</h3>
                        <p class="lt-pane-desc">{{ $lt['desc'] }}</p>
                        {{-- Block form is required here. Blade extracts raw
                             php…endphp blocks BEFORE it compiles directives, so
                             the one-line parenthesised php directive, in a file
                             that also has a later endphp, pairs with THAT and
                             swallows everything between as raw text: the
                             foreach and if below stop compiling and $__lts is
                             never assigned, which is a 500 on this section.
                             scripts/src/check-blade-php.ts guards it. --}}
                        @php
                            $__ltUsageLine = \App\Modules\Common\Support\LinkTypeUsage::forName($lt['name']);
                        @endphp
                        @if($__ltUsageLine !== '')
                            <p class="lt-pane-usage" data-expand-more>
                                <span>How you would use it</span>
                                {{ $__ltUsageLine }}
                            </p>
                        @endif
                        <button type="button" class="lt-pane-cta" style="background:{{ $lt['color'] }}"
                                onclick="window.trackMarketingEvent&&window.trackMarketingEvent('landing_home_spotlight','{{ addslashes($lt['name']) }}');window.dispatchEvent(new CustomEvent('open-auth',{detail:{tab:'register'}}))">
                            Get started free <i class="fas fa-arrow-right" style="font-size:10px"></i>
                        </button>
                    </div>
                    @endforeach
                </div>

                {{-- Mock visual zone (right on desktop, top on mobile) --}}
                <div class="lt-mock-zone" aria-hidden="true">
                    @foreach($__ltForEach as $i => $lt)
                    @php $__lts = \Illuminate\Support\Str::slug($lt['name']); @endphp
                    <div class="lt-mock {{ $i === 0 ? 'lt-mock-on' : '' }}"
                         :class="{'lt-mock-on':active==={{ $i }}}">

                        @switch($__lts)

                        @case('short-link')
                        {{-- URL card + click analytics mini chart --}}
                        <div style="width:200px;display:flex;flex-direction:column;gap:10px">
                            <div style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:14px;padding:14px 16px">
                                <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
                                    <div style="width:20px;height:20px;border-radius:6px;background:{{ $lt['color'] }};display:flex;align-items:center;justify-content:center"><i class="fas fa-link" style="color:#fff;font-size:8px"></i></div>
                                    <div style="flex:1;min-width:0">
                                        <div style="font-size:11px;font-weight:700;color:#fff">syz.io/<span style="color:{{ $lt['color'] }}">hello</span></div>
                                        <div style="font-size:9px;color:rgba(255,255,255,.4);margin-top:1px">→ example.com/long/path</div>
                                    </div>
                                </div>
                                <div style="display:flex;gap:6px">
                                    <div style="flex:1;background:{{ $lt['color'] }}22;border-radius:8px;padding:7px 10px;text-align:center">
                                        <div style="font-size:16px;font-weight:800;color:{{ $lt['color'] }}">2.8k</div>
                                        <div style="font-size:8.5px;color:rgba(255,255,255,.45);margin-top:1px">clicks</div>
                                    </div>
                                    <div style="flex:1;background:rgba(255,255,255,.04);border-radius:8px;padding:7px 10px;text-align:center">
                                        <div style="font-size:16px;font-weight:800;color:#fff">38</div>
                                        <div style="font-size:8.5px;color:rgba(255,255,255,.45);margin-top:1px">countries</div>
                                    </div>
                                </div>
                            </div>
                            <div style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07);border-radius:12px;padding:12px 14px">
                                <div style="font-size:9px;color:rgba(255,255,255,.35);margin-bottom:8px;text-transform:uppercase;letter-spacing:.08em">This week</div>
                                <div style="display:flex;align-items:flex-end;gap:4px;height:36px">
                                    @foreach([30,55,40,72,58,88,65] as $pct)
                                    <div style="flex:1;background:{{ $lt['color'] }};border-radius:3px 3px 0 0;opacity:.75;height:{{ $pct }}%"></div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        @break

                        @case('link-in-bio')
                        {{-- Phone frame: avatar + stacked link buttons --}}
                        <div class="lt-phone">
                            <div style="display:flex;flex-direction:column;align-items:center;gap:7px;padding-top:4px">
                                <div style="width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,{{ $lt['color'] }},{{ $lt['color'] }}88);border:2px solid rgba(255,255,255,.25);flex-shrink:0"></div>
                                <div style="font-size:11px;font-weight:800;color:#fff;text-align:center">@yourname</div>
                                <div style="font-size:9px;color:rgba(255,255,255,.45);text-align:center;max-width:120px;line-height:1.4">Creator · Speaker · Builder</div>
                            </div>
                            <div style="display:flex;flex-direction:column;gap:6px;margin-top:10px">
                                @foreach(['My Latest Drop','Book a Call','Shop My Store'] as $j => $lbl)
                                <div style="background:{{ $j===0 ? $lt['color'] : 'rgba(255,255,255,.08)' }};border:1px solid {{ $j===0 ? 'transparent' : 'rgba(255,255,255,.12)' }};border-radius:9px;padding:8px 12px;text-align:center;font-size:10px;font-weight:700;color:#fff">{{ $lbl }}</div>
                                @endforeach
                            </div>
                            <div style="display:flex;justify-content:center;gap:8px;margin-top:10px">
                                @foreach(['fa-instagram','fa-twitter','fa-youtube'] as $ico)
                                <div style="width:22px;height:22px;border-radius:50%;background:rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center"><i class="fab {{ $ico }}" style="color:rgba(255,255,255,.7);font-size:9px"></i></div>
                                @endforeach
                            </div>
                        </div>
                        @break

                        @case('conversational')
                        {{-- Phone frame: chat bubbles + option chips --}}
                        <div class="lt-phone">
                            <div style="display:flex;flex-direction:column;gap:8px;padding-top:6px">
                                <div style="display:flex;gap:6px;align-items:flex-end">
                                    <div style="width:22px;height:22px;border-radius:50%;background:{{ $lt['color'] }};flex-shrink:0;display:flex;align-items:center;justify-content:center"><i class="fas fa-robot" style="color:#fff;font-size:9px"></i></div>
                                    <div style="background:rgba(255,255,255,.1);border-radius:12px 12px 12px 3px;padding:8px 11px;max-width:108px"><div style="font-size:9.5px;color:#fff;line-height:1.45">Hi! I'm here to guide you. What are you looking for?</div></div>
                                </div>
                                <div style="display:flex;justify-content:flex-end">
                                    <div style="background:{{ $lt['color'] }};border-radius:12px 12px 3px 12px;padding:8px 11px;max-width:100px"><div style="font-size:9.5px;color:#fff;line-height:1.45">I'd like to see your links</div></div>
                                </div>
                                <div style="display:flex;gap:6px;align-items:flex-end">
                                    <div style="width:22px;height:22px;border-radius:50%;background:{{ $lt['color'] }};flex-shrink:0;display:flex;align-items:center;justify-content:center"><i class="fas fa-robot" style="color:#fff;font-size:9px"></i></div>
                                    <div style="background:rgba(255,255,255,.1);border-radius:12px 12px 12px 3px;padding:8px 11px;max-width:108px"><div style="font-size:9.5px;color:#fff;line-height:1.45">Great! Here are 3 options for you 👇</div></div>
                                </div>
                                <div style="display:flex;flex-direction:column;gap:5px;margin-left:28px">
                                    @foreach(['Visit my store','Read my blog','Book a call'] as $opt)
                                    <div style="border:1px solid {{ $lt['color'] }}88;border-radius:8px;padding:6px 10px;font-size:9px;font-weight:600;color:{{ $lt['color'] }}">{{ $opt }}</div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        @break

                        @case('slides')
                        {{-- Slide frame with progress bar + arrows --}}
                        <div style="width:200px;height:250px;border-radius:16px;background:linear-gradient(150deg,#1c0d30,#0d0516);border:1.5px solid rgba(255,255,255,.12);box-shadow:0 20px 48px -16px rgba(0,0,0,.75);overflow:hidden;position:relative">
                            <div style="position:absolute;inset:0;background:linear-gradient(135deg,{{ $lt['color'] }}33,transparent 60%)"></div>
                            <div style="position:relative;padding:22px 20px;height:100%;display:flex;flex-direction:column;justify-content:space-between">
                                <div>
                                    <div style="font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:{{ $lt['color'] }};margin-bottom:8px">Slide 1 of 5</div>
                                    <div style="font-size:18px;font-weight:800;color:#fff;line-height:1.2;margin-bottom:8px">My Story<br>Begins Here</div>
                                    <div style="font-size:9.5px;color:rgba(255,255,255,.5);line-height:1.5">Swipe to explore →</div>
                                </div>
                                <div>
                                    <div style="display:flex;gap:4px;margin-bottom:10px">
                                        @foreach(range(1,5) as $d)
                                        <div style="height:3px;border-radius:3px;flex:1;background:{{ $d===1 ? $lt['color'] : 'rgba(255,255,255,.2)' }}"></div>
                                        @endforeach
                                    </div>
                                    <div style="display:flex;justify-content:space-between;align-items:center">
                                        <div style="width:28px;height:28px;border-radius:50%;background:rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center"><i class="fas fa-chevron-left" style="color:rgba(255,255,255,.5);font-size:9px"></i></div>
                                        <div style="width:28px;height:28px;border-radius:50%;background:{{ $lt['color'] }};display:flex;align-items:center;justify-content:center"><i class="fas fa-chevron-right" style="color:#fff;font-size:9px"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @break

                        @case('ai-chatbot')
                        {{-- Phone: AI chat with typing dots --}}
                        <div class="lt-phone">
                            <div style="display:flex;align-items:center;gap:7px;padding-bottom:8px;border-bottom:1px solid rgba(255,255,255,.08);margin-bottom:8px">
                                <div style="width:24px;height:24px;border-radius:50%;background:{{ $lt['color'] }};display:flex;align-items:center;justify-content:center"><i class="fas fa-robot" style="color:#fff;font-size:10px"></i></div>
                                <div>
                                    <div style="font-size:10px;font-weight:700;color:#fff">AI Assistant</div>
                                    <div style="font-size:8px;color:{{ $lt['color'] }}">● Online</div>
                                </div>
                            </div>
                            <div style="display:flex;flex-direction:column;gap:7px">
                                <div style="display:flex;gap:5px;align-items:flex-end">
                                    <div style="width:18px;height:18px;border-radius:50%;background:{{ $lt['color'] }};flex-shrink:0"></div>
                                    <div style="background:rgba(255,255,255,.1);border-radius:10px 10px 10px 3px;padding:7px 10px"><div style="font-size:9px;color:#fff;line-height:1.45">Hello! Ask me anything.</div></div>
                                </div>
                                <div style="display:flex;justify-content:flex-end">
                                    <div style="background:{{ $lt['color'] }};border-radius:10px 10px 3px 10px;padding:7px 10px"><div style="font-size:9px;color:#fff">What are your prices?</div></div>
                                </div>
                                <div style="display:flex;gap:5px;align-items:flex-end">
                                    <div style="width:18px;height:18px;border-radius:50%;background:{{ $lt['color'] }};flex-shrink:0"></div>
                                    <div style="background:rgba(255,255,255,.1);border-radius:10px 10px 10px 3px;padding:8px 12px">
                                        <div style="display:flex;gap:4px;align-items:center">
                                            <div style="width:5px;height:5px;border-radius:50%;background:{{ $lt['color'] }};animation:lt-pulse 1s ease-in-out infinite"></div>
                                            <div style="width:5px;height:5px;border-radius:50%;background:{{ $lt['color'] }};animation:lt-pulse 1s ease-in-out .22s infinite"></div>
                                            <div style="width:5px;height:5px;border-radius:50%;background:{{ $lt['color'] }};animation:lt-pulse 1s ease-in-out .44s infinite"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div style="position:absolute;bottom:12px;left:10px;right:10px;background:rgba(255,255,255,.07);border-radius:9px;padding:7px 10px;display:flex;align-items:center;gap:6px">
                                <div style="flex:1;font-size:9px;color:rgba(255,255,255,.3)">Ask me anything…</div>
                                <div style="width:18px;height:18px;border-radius:6px;background:{{ $lt['color'] }};display:flex;align-items:center;justify-content:center"><i class="fas fa-paper-plane" style="color:#fff;font-size:7px"></i></div>
                            </div>
                        </div>
                        @break

                        @case('restaurant-menu')
                        {{-- Menu card with items + order button --}}
                        <div style="width:196px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:16px;overflow:hidden">
                            <div style="background:{{ $lt['color'] }};padding:12px 14px">
                                <div style="font-size:11px;font-weight:800;color:#fff">🍽 La Bella Table</div>
                                <div style="font-size:9px;color:rgba(255,255,255,.75);margin-top:2px">Table 4 · Scan to order</div>
                            </div>
                            <div style="padding:10px 12px;display:flex;flex-direction:column;gap:8px">
                                <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:rgba(255,255,255,.4)">Mains</div>
                                @foreach([['Grilled Salmon','$18.90'],['Truffle Pasta','$14.50'],['Wagyu Burger','$22.00']] as [$rname,$rprice])
                                <div style="display:flex;justify-content:space-between;align-items:center">
                                    <div style="font-size:10px;color:#fff">{{ $rname }}</div>
                                    <div style="font-size:10px;font-weight:700;color:{{ $lt['color'] }}">{{ $rprice }}</div>
                                </div>
                                @endforeach
                                <div style="background:{{ $lt['color'] }};border-radius:8px;padding:7px;text-align:center;font-size:10px;font-weight:700;color:#fff;margin-top:2px">Add to order →</div>
                            </div>
                        </div>
                        @break

                        @case('store-menu')
                        {{-- 2×2 product grid --}}
                        <div style="width:200px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:14px">
                            <div style="display:flex;align-items:center;gap:6px;margin-bottom:10px">
                                <div style="width:18px;height:18px;border-radius:6px;background:{{ $lt['color'] }};display:flex;align-items:center;justify-content:center"><i class="fas fa-store" style="color:#fff;font-size:8px"></i></div>
                                <div style="font-size:10px;font-weight:700;color:#fff">My Shop</div>
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px">
                                @foreach([['Hoodie','$35','fa-tshirt'],['Mug','$12','fa-mug-hot'],['Sticker Pack','$8','fa-star'],['Tote Bag','$22','fa-shopping-bag']] as [$pname,$pprice,$pico])
                                <div style="background:rgba(255,255,255,.06);border-radius:10px;padding:10px 8px;display:flex;flex-direction:column;gap:5px">
                                    <div style="width:28px;height:28px;border-radius:8px;background:{{ $lt['color'] }}22;display:flex;align-items:center;justify-content:center"><i class="fas {{ $pico }}" style="color:{{ $lt['color'] }};font-size:11px"></i></div>
                                    <div style="font-size:9px;font-weight:600;color:#fff;line-height:1.2">{{ $pname }}</div>
                                    <div style="font-size:10px;font-weight:800;color:{{ $lt['color'] }}">{{ $pprice }}</div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @break

                        @case('file-share')
                        {{-- File download card --}}
                        <div style="width:196px;display:flex;flex-direction:column;gap:10px">
                            <div style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:16px;padding:20px;display:flex;flex-direction:column;align-items:center;gap:12px">
                                <div style="width:52px;height:52px;border-radius:14px;background:{{ $lt['color'] }};display:flex;align-items:center;justify-content:center;box-shadow:0 10px 24px -8px {{ $lt['color'] }}"><i class="fas fa-file-pdf" style="color:#fff;font-size:22px"></i></div>
                                <div style="text-align:center">
                                    <div style="font-size:11px;font-weight:700;color:#fff">2024-Brand-Guide.pdf</div>
                                    <div style="font-size:9px;color:rgba(255,255,255,.4);margin-top:3px">4.2 MB · PDF</div>
                                </div>
                                <div style="background:{{ $lt['color'] }};border-radius:9px;padding:9px 0;font-size:11px;font-weight:700;color:#fff;display:flex;align-items:center;gap:5px;width:100%;justify-content:center">
                                    <i class="fas fa-download" style="font-size:10px"></i> Download
                                </div>
                            </div>
                            <div style="background:rgba(255,255,255,.04);border-radius:12px;padding:10px 14px;display:flex;justify-content:space-between">
                                <div style="font-size:9px;color:rgba(255,255,255,.4)">Total downloads</div>
                                <div style="font-size:9px;font-weight:700;color:#fff">1,847</div>
                            </div>
                        </div>
                        @break

                        @case('event')
                        {{-- Calendar event card --}}
                        <div style="width:186px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:16px;overflow:hidden">
                            <div style="background:{{ $lt['color'] }};padding:12px 16px">
                                <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.75)">December 2024</div>
                                <div style="font-size:32px;font-weight:900;color:#fff;line-height:1.1">14</div>
                                <div style="font-size:10px;color:rgba(255,255,255,.85);font-weight:600">Saturday</div>
                            </div>
                            <div style="padding:12px 16px;display:flex;flex-direction:column;gap:8px">
                                <div style="font-size:12px;font-weight:800;color:#fff">Product Launch Event</div>
                                <div style="display:flex;align-items:center;gap:6px"><i class="fas fa-clock" style="color:{{ $lt['color'] }};font-size:9px;width:12px"></i><div style="font-size:9.5px;color:rgba(255,255,255,.6)">6:00 PM — 9:00 PM</div></div>
                                <div style="display:flex;align-items:center;gap:6px"><i class="fas fa-map-marker-alt" style="color:{{ $lt['color'] }};font-size:9px;width:12px"></i><div style="font-size:9.5px;color:rgba(255,255,255,.6)">Online · Zoom</div></div>
                                <div style="background:{{ $lt['color'] }};border-radius:8px;padding:7px;text-align:center;font-size:10px;font-weight:700;color:#fff">Add to Calendar</div>
                            </div>
                        </div>
                        @break

                        @case('calendar')
                        {{-- Followable calendar grid with event dots --}}
                        <div style="width:196px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:16px;padding:14px">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                                <div style="font-size:11px;font-weight:700;color:#fff">December 2024</div>
                                <div style="display:flex;gap:4px">
                                    <div style="width:18px;height:18px;border-radius:5px;background:rgba(255,255,255,.08);display:flex;align-items:center;justify-content:center"><i class="fas fa-chevron-left" style="color:rgba(255,255,255,.5);font-size:7px"></i></div>
                                    <div style="width:18px;height:18px;border-radius:5px;background:rgba(255,255,255,.08);display:flex;align-items:center;justify-content:center"><i class="fas fa-chevron-right" style="color:rgba(255,255,255,.5);font-size:7px"></i></div>
                                </div>
                            </div>
                            <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:2px;margin-bottom:5px">
                                @foreach(['M','T','W','T','F','S','S'] as $d)
                                <div style="font-size:7.5px;text-align:center;color:rgba(255,255,255,.3);font-weight:600">{{ $d }}</div>
                                @endforeach
                            </div>
                            <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:2px">
                                @php $__evDays=[5,12,14,19,21]; @endphp
                                @foreach(range(1,28) as $d)
                                <div style="aspect-ratio:1;border-radius:5px;display:flex;align-items:center;justify-content:center;font-size:8px;font-weight:600;{{ in_array($d,$__evDays)?'background:'.$lt['color'].';color:#fff':'color:rgba(255,255,255,.6)' }}">{{ $d }}</div>
                                @endforeach
                            </div>
                            <div style="margin-top:10px;display:flex;flex-direction:column;gap:4px">
                                <div style="display:flex;align-items:center;gap:6px"><div style="width:6px;height:6px;border-radius:50%;background:{{ $lt['color'] }};flex-shrink:0"></div><div style="font-size:9px;color:rgba(255,255,255,.65)">Webinar · Dec 5</div></div>
                                <div style="display:flex;align-items:center;gap:6px"><div style="width:6px;height:6px;border-radius:50%;background:{{ $lt['color'] }};flex-shrink:0;opacity:.6"></div><div style="font-size:9px;color:rgba(255,255,255,.65)">Workshop · Dec 12</div></div>
                            </div>
                        </div>
                        @break

                        @case('contact-card')
                        {{-- vCard preview --}}
                        <div style="width:196px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:16px;overflow:hidden">
                            <div style="background:linear-gradient(135deg,{{ $lt['color'] }},{{ $lt['color'] }}88);padding:16px;display:flex;align-items:center;gap:10px">
                                <div style="width:44px;height:44px;border-radius:50%;background:rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:800;color:#fff;flex-shrink:0">JD</div>
                                <div>
                                    <div style="font-size:12px;font-weight:800;color:#fff">Jane Doe</div>
                                    <div style="font-size:9.5px;color:rgba(255,255,255,.8)">Product Designer</div>
                                </div>
                            </div>
                            <div style="padding:12px 14px;display:flex;flex-direction:column;gap:8px">
                                @foreach([['fa-phone','+1 (555) 000-0000'],['fa-envelope','jane@example.com'],['fa-globe','janedoe.com']] as [$ico,$val])
                                <div style="display:flex;align-items:center;gap:8px">
                                    <div style="width:22px;height:22px;border-radius:7px;background:{{ $lt['color'] }}22;display:flex;align-items:center;justify-content:center"><i class="fas {{ $ico }}" style="color:{{ $lt['color'] }};font-size:9px"></i></div>
                                    <div style="font-size:9.5px;color:rgba(255,255,255,.75)">{{ $val }}</div>
                                </div>
                                @endforeach
                                <div style="background:{{ $lt['color'] }};border-radius:8px;padding:7px;text-align:center;font-size:10px;font-weight:700;color:#fff;margin-top:2px">Save Contact</div>
                            </div>
                        </div>
                        @break

                        @case('resume-portfolio')
                        {{-- Resume document (white paper, always light inside) --}}
                        <div style="width:170px;height:238px;background:#fff;border-radius:10px;box-shadow:0 20px 48px -16px rgba(0,0,0,.6);overflow:hidden">
                            <div style="background:{{ $lt['color'] }};height:50px;padding:12px 14px;display:flex;flex-direction:column;justify-content:center">
                                <div style="font-size:12px;font-weight:800;color:#fff">Jane Doe</div>
                                <div style="font-size:9px;color:rgba(255,255,255,.8)">UX Designer · Portfolio</div>
                            </div>
                            <div style="padding:10px 12px;display:flex;flex-direction:column;gap:7px">
                                @foreach([['fa-briefcase','Experience'],['fa-graduation-cap','Education'],['fa-star','Skills']] as [$ico,$sec])
                                <div>
                                    <div style="display:flex;align-items:center;gap:4px;margin-bottom:3px">
                                        <i class="fas {{ $ico }}" style="color:{{ $lt['color'] }};font-size:8px"></i>
                                        <div style="font-size:8.5px;font-weight:800;color:#111;text-transform:uppercase;letter-spacing:.06em">{{ $sec }}</div>
                                    </div>
                                    <div style="height:1px;background:#eee;margin-bottom:4px"></div>
                                    <div style="height:5px;background:#eee;border-radius:3px;margin-bottom:2px;width:85%"></div>
                                    <div style="height:5px;background:#eee;border-radius:3px;width:65%"></div>
                                </div>
                                @endforeach
                                <div style="background:{{ $lt['color'] }};border-radius:6px;padding:5px 8px;text-align:center;font-size:8.5px;font-weight:700;color:#fff;margin-top:2px">Download PDF</div>
                            </div>
                        </div>
                        @break

                        @case('bizs-profile')
                        {{-- Creator profile card + post grid --}}
                        <div style="width:200px;display:flex;flex-direction:column;gap:8px">
                            <div style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:16px;padding:16px;display:flex;flex-direction:column;gap:8px">
                                <div style="display:flex;align-items:center;gap:10px">
                                    <div style="width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,{{ $lt['color'] }},{{ $lt['color'] }}88);flex-shrink:0"></div>
                                    <div>
                                        <div style="font-size:12px;font-weight:800;color:#fff">Creator Name</div>
                                        <div style="font-size:9px;color:rgba(255,255,255,.5)">@handle · ✓ Verified</div>
                                    </div>
                                </div>
                                <div style="display:flex;gap:8px">
                                    @foreach([['12.4k','Followers'],['248','Posts'],['⭐ 4.9','Rating']] as [$val,$lab])
                                    <div style="flex:1;background:rgba(255,255,255,.04);border-radius:9px;padding:7px 6px;text-align:center">
                                        <div style="font-size:11px;font-weight:800;color:#fff">{{ $val }}</div>
                                        <div style="font-size:8px;color:rgba(255,255,255,.4);margin-top:1px">{{ $lab }}</div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:4px">
                                @foreach([['fa-video','#3d6bff'],['fa-pen-nib','#34d399'],['fa-image','#fbbf24'],['fa-microphone','#fb7185'],['fa-star','#a855f7'],['fa-rss','#10b981']] as [$ico,$c])
                                <div style="aspect-ratio:1;background:{{ $c }}22;border-radius:10px;display:flex;align-items:center;justify-content:center"><i class="fas {{ $ico }}" style="color:{{ $c }};font-size:13px"></i></div>
                                @endforeach
                            </div>
                        </div>
                        @break

                        @case('reviews-page')
                        {{-- Star rating + review snippets --}}
                        <div style="width:200px;display:flex;flex-direction:column;gap:8px">
                            <div style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:14px;padding:16px;text-align:center">
                                <div style="font-size:36px;font-weight:900;color:#fff">4.9</div>
                                <div style="display:flex;justify-content:center;gap:3px;margin:4px 0">
                                    @foreach(range(1,5) as $_)<i class="fas fa-star" style="color:{{ $lt['color'] }};font-size:12px"></i>@endforeach
                                </div>
                                <div style="font-size:9px;color:rgba(255,255,255,.4)">Based on 384 reviews</div>
                            </div>
                            @foreach([['Sarah M.','Absolutely love it! Changed how I share my work.'],['James T.','Super simple to set up and looks incredible.']] as [$rname,$rev])
                            <div style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.07);border-radius:12px;padding:10px 12px">
                                <div style="display:flex;gap:2px;margin-bottom:4px">@foreach(range(1,5) as $_)<i class="fas fa-star" style="color:{{ $lt['color'] }};font-size:8px"></i>@endforeach</div>
                                <div style="font-size:9.5px;color:rgba(255,255,255,.75);line-height:1.4;margin-bottom:3px">{{ $rev }}</div>
                                <div style="font-size:8.5px;color:rgba(255,255,255,.4)">— {{ $rname }}</div>
                            </div>
                            @endforeach
                        </div>
                        @break

                        @case('brand-press-kit')
                        {{-- Colour palette + logo + font --}}
                        <div style="width:200px;display:flex;flex-direction:column;gap:8px">
                            <div style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:14px;padding:14px">
                                <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.4);margin-bottom:8px">Colour Palette</div>
                                <div style="display:flex;gap:5px">
                                    @foreach([$lt['color'],'#1bd4d9','#34d399','#fbbf24','#f43f5e'] as $col)
                                    <div style="flex:1;height:32px;border-radius:8px;background:{{ $col }}"></div>
                                    @endforeach
                                </div>
                            </div>
                            <div style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:14px;padding:14px;display:flex;align-items:center;gap:12px">
                                <div style="width:44px;height:44px;border-radius:12px;background:{{ $lt['color'] }};display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:900;color:#fff;flex-shrink:0">B</div>
                                <div>
                                    <div style="font-size:11px;font-weight:700;color:#fff">Brand Logo</div>
                                    <div style="font-size:9px;color:rgba(255,255,255,.4)">SVG · PNG · 2 MB</div>
                                    <div style="font-size:9px;color:{{ $lt['color'] }};margin-top:2px;font-weight:600">Download kit ↓</div>
                                </div>
                            </div>
                            <div style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07);border-radius:12px;padding:10px 12px">
                                <div style="font-size:9px;font-weight:700;color:rgba(255,255,255,.4);margin-bottom:4px;text-transform:uppercase;letter-spacing:.05em">Primary Font</div>
                                <div style="font-size:14px;font-weight:800;color:#fff">Space Grotesk</div>
                            </div>
                        </div>
                        @break

                        @case('paid-page')
                        {{-- Lock + unlock CTA --}}
                        <div style="width:186px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:16px;overflow:hidden">
                            <div style="padding:22px;display:flex;flex-direction:column;align-items:center;gap:10px">
                                <div style="width:52px;height:52px;border-radius:50%;background:{{ $lt['color'] }}22;border:2px solid {{ $lt['color'] }}55;display:flex;align-items:center;justify-content:center"><i class="fas fa-lock" style="color:{{ $lt['color'] }};font-size:20px"></i></div>
                                <div style="text-align:center">
                                    <div style="font-size:12px;font-weight:800;color:#fff">Premium Content</div>
                                    <div style="font-size:9.5px;color:rgba(255,255,255,.5);margin-top:3px;line-height:1.4">Get instant access to the exclusive course materials.</div>
                                </div>
                                <div style="background:{{ $lt['color'] }};border-radius:9px;padding:10px 16px;font-size:11px;font-weight:700;color:#fff;display:flex;align-items:center;gap:5px">
                                    <i class="fas fa-unlock-alt" style="font-size:10px"></i> Unlock for $9
                                </div>
                                <div style="font-size:9px;color:rgba(255,255,255,.3)">One-time · Instant access</div>
                            </div>
                        </div>
                        @break

                        @case('qr-code')
                        {{-- QR code pixel tile (static SVG-style CSS grid) --}}
                        @php
                            $__qrRows = [
                                [1,1,1,1,1,1,1,0,1,0,1,0,1,1,1,1,1,1,1],
                                [1,0,0,0,0,0,1,0,0,1,0,1,1,0,0,0,0,0,1],
                                [1,0,1,1,1,0,1,0,1,0,1,0,1,0,1,1,1,0,1],
                                [1,0,1,1,1,0,1,0,0,1,0,0,1,0,1,1,1,0,1],
                                [1,0,1,1,1,0,1,0,1,0,1,0,1,0,1,1,1,0,1],
                                [1,0,0,0,0,0,1,0,0,0,0,1,1,0,0,0,0,0,1],
                                [1,1,1,1,1,1,1,0,1,0,1,0,1,1,1,1,1,1,1],
                                [0,0,0,0,0,0,0,0,0,1,0,1,0,0,0,0,0,0,0],
                                [1,0,1,1,0,1,1,0,1,0,1,0,1,1,0,1,0,1,0],
                                [0,1,0,0,1,0,0,0,0,1,0,0,0,1,0,0,1,0,1],
                                [1,0,1,0,1,1,0,0,1,0,1,0,0,0,1,0,1,0,1],
                                [0,0,0,0,0,0,0,0,0,0,0,1,0,1,0,1,0,0,0],
                                [1,1,1,1,1,1,1,0,1,1,0,0,1,0,1,0,1,1,0],
                                [1,0,0,0,0,0,1,0,0,0,1,0,0,1,0,0,0,1,1],
                                [1,0,1,1,1,0,1,0,1,0,0,1,1,0,0,1,1,0,0],
                                [1,0,1,1,1,0,1,0,0,1,0,0,0,1,0,0,0,1,0],
                                [1,0,1,1,1,0,1,0,1,0,1,0,0,0,1,1,0,0,1],
                                [1,0,0,0,0,0,1,0,0,1,0,1,0,0,0,0,1,0,0],
                                [1,1,1,1,1,1,1,0,1,0,1,0,1,0,1,0,0,1,0],
                            ];
                        @endphp
                        <div style="display:flex;flex-direction:column;align-items:center;gap:10px">
                            <div style="background:#fff;border-radius:14px;padding:12px;box-shadow:0 16px 40px -12px rgba(0,0,0,.6)">
                                <div style="display:grid;grid-template-columns:repeat(19,6px);grid-template-rows:repeat(19,6px);gap:1px">
                                    @foreach($__qrRows as $row)
                                        @foreach($row as $cell)
                                        <div style="width:6px;height:6px;background:{{ $cell ? '#0d0516' : '#fff' }};border-radius:1px"></div>
                                        @endforeach
                                    @endforeach
                                </div>
                            </div>
                            <div style="display:flex;gap:6px;align-items:center">
                                <div style="width:24px;height:24px;border-radius:7px;background:{{ $lt['color'] }};display:flex;align-items:center;justify-content:center"><i class="fas fa-link" style="color:#fff;font-size:9px"></i></div>
                                <div style="font-size:10px;font-weight:600;color:rgba(255,255,255,.7)">Dynamic · Repointable</div>
                            </div>
                        </div>
                        @break

                        @case('forms')
                        {{-- Form with labelled input fields --}}
                        <div style="width:200px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:16px;padding:16px;display:flex;flex-direction:column;gap:10px">
                            <div style="font-size:11px;font-weight:800;color:#fff">Contact Us</div>
                            @foreach([['Your name','fa-user'],['Email address','fa-envelope'],['Your message','fa-comment-alt']] as [$ph,$ico])
                            <div>
                                <div style="font-size:9px;text-transform:uppercase;letter-spacing:.06em;color:rgba(255,255,255,.35);margin-bottom:4px">{{ $ph }}</div>
                                <div style="background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:{{ $ico==='fa-comment-alt'?'8':'7' }}px 10px;display:flex;align-items:{{ $ico==='fa-comment-alt'?'flex-start':'center' }};gap:7px">
                                    <i class="fas {{ $ico }}" style="color:rgba(255,255,255,.25);font-size:9px;{{ $ico==='fa-comment-alt'?'margin-top:1px':'' }}"></i>
                                    <div style="font-size:9.5px;color:rgba(255,255,255,.2)">{{ $ph }}…</div>
                                </div>
                            </div>
                            @endforeach
                            <div style="background:{{ $lt['color'] }};border-radius:8px;padding:8px;text-align:center;font-size:10px;font-weight:700;color:#fff">Send Message</div>
                        </div>
                        @break

                        @default
                        {{-- Generic fallback: large accent icon --}}
                        <div style="display:flex;flex-direction:column;align-items:center;gap:16px">
                            <div style="width:80px;height:80px;border-radius:24px;background:{{ $lt['color'] }};display:flex;align-items:center;justify-content:center;box-shadow:0 16px 40px -12px {{ $lt['color'] }}cc">
                                <i class="fas {{ $lt['icon'] }}" style="color:#fff;font-size:30px"></i>
                            </div>
                            <div style="text-align:center">
                                <div style="font-size:13px;font-weight:700;color:#fff">{{ $lt['name'] }}</div>
                                <div style="font-size:11px;color:rgba(255,255,255,.4);margin-top:4px">Shareable link · Track &amp; customise</div>
                            </div>
                        </div>
                        @break

                        @endswitch

                    </div>
                    @endforeach
                </div>

                {{-- Dot navigation (auto-rotate position indicator) --}}
                <div class="lt-dots" aria-hidden="true">
                    @foreach($__ltForEach as $i => $lt)
                    <button type="button"
                            class="lt-dot {{ $i === 0 ? 'lt-dot-on' : '' }}"
                            :class="{'lt-dot-on': active==={{ $i }}}"
                            @click.stop="pick({{ $i }})" tabindex="-1"></button>
                    @endforeach
                </div>
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
