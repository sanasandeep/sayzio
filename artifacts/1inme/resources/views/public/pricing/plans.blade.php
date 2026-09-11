@extends('public.layouts.site')

@include('partials.marketing-system')

@section('title', 'Pricing')

@push('head')
@php
    $__pricingProducts = \App\Modules\Common\Support\MarketingSchema::pricingProducts(
        $planModels ?? collect(),
        $currency ?? 'USD'
    );
@endphp
@if(!empty($__pricingProducts))
<script type="application/ld+json">{!! json_encode(\App\Modules\Common\Support\MarketingSchema::graph($__pricingProducts), JSON_UNESCAPED_UNICODE) !!}</script>
@endif
<style>
    .grad-bar { background: linear-gradient(135deg,#3d6bff 0%,#2b54eb 50%,#22d3ee 100%); }
    .grad-text {
        background: linear-gradient(120deg,#bccfff 0%,#3d6bff 38%,#2b54eb 66%,#1bd4d9 100%);
        -webkit-background-clip: text; background-clip: text;
        -webkit-text-fill-color: transparent; color: transparent;
    }
    .hero-title { text-shadow: 0 0 60px rgba(61,107,255,.25); }
    html.light-mode .hero-title { text-shadow: 0 0 50px rgba(61,107,255,.18); }
    .pulse-dot { animation: pulse 1.6s ease-in-out infinite; }
    @keyframes pulse { 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(1.4);opacity:.5} }
    .float-coin { animation: floaty 3s ease-in-out infinite; }
    @keyframes floaty { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-6px)} }

    /* Most-popular ribbon — gentle wiggle on hover */
    .pop-ribbon { animation: ribbonGlow 3s ease-in-out infinite; }
    @keyframes ribbonGlow {
        0%, 100% { box-shadow: 0 8px 24px -10px rgba(61,107,255,.55); }
        50%      { box-shadow: 0 12px 32px -8px rgba(144,172,255,.65); }
    }
    .grad-glow:hover .pop-ribbon { animation: ribbonWiggle .55s ease-in-out; }
    @keyframes ribbonWiggle {
        0%,100% { transform: translate(-50%, 0) rotate(0); }
        25%     { transform: translate(-50%, -1px) rotate(-3deg); }
        75%     { transform: translate(-50%, -1px) rotate(3deg); }
    }

    /* "Recommended for you" callout in the upgrade banner */
    .smart-banner {
        position: relative; overflow: hidden;
        background:
            radial-gradient(120% 140% at 0% 0%, rgba(61,107,255,.16), transparent 55%),
            radial-gradient(120% 140% at 100% 0%, rgba(236,72,153,.10), transparent 55%),
            rgba(255,255,255,.02);
        box-shadow: 0 24px 60px -36px rgba(61,107,255,.6);
    }
    /* Banner uses light-on-dark text in both modes, so keep a dark surface in light mode for contrast. */
    html.light-mode .smart-banner {
        background:
            radial-gradient(120% 140% at 0% 0%, rgba(61,107,255,.30), transparent 55%),
            radial-gradient(120% 140% at 100% 0%, rgba(236,72,153,.20), transparent 55%),
            #1e1b2e;
        border-color: rgba(255,255,255,.12);
        box-shadow: 0 24px 60px -36px rgba(61,107,255,.5);
    }
    .smart-pill {
        background: rgba(61,107,255,.22);
        border: 1px solid rgba(255,255,255,.18);
    }
    .smart-meter {
        height: 6px; background: rgba(255,255,255,.06); border-radius: 9999px; overflow: hidden;
    }
    .smart-meter > span {
        display: block; height: 100%; border-radius: 9999px;
        background: #3d6bff;
        transition: width .9s cubic-bezier(.2,.7,.2,1);
    }
    .smart-meter.warn > span { background: #f59e0b; }

    /* Compare-features matrix — one fixed-width column per plan.
       Columns use locked px widths so every plan lines up; the matrix
       lives in a self-contained scroll box (capped height) so BOTH the
       feature-name column (sticky left) and the plan header row
       (sticky top) stay anchored while you scroll horizontally and
       vertically through the long feature list. */
    .feat-matrix-scroll {
        overflow: auto; -webkit-overflow-scrolling: touch;
        max-height: min(78vh, 760px);
        scrollbar-width: thin; scrollbar-color: rgba(61,107,255,.55) transparent;
    }
    .feat-matrix-scroll::-webkit-scrollbar { width: 9px; height: 9px; }
    .feat-matrix-scroll::-webkit-scrollbar-track { background: rgba(255,255,255,.03); }
    .feat-matrix-scroll::-webkit-scrollbar-thumb { background: rgba(61,107,255,.5); border-radius: 9999px; }
    .feat-matrix-scroll::-webkit-scrollbar-thumb:hover { background: rgba(61,107,255,.75); }
    .feat-matrix { min-width: max-content; }
    .feat-cell {
        padding: .85rem 1rem; border-top: 1px solid rgba(255,255,255,.05);
        font-size: .82rem; color: #cbd5e1;
        display: flex; align-items: center;
        transition: background-color .18s ease;
    }
    /* Zebra striping: every other feature row gets a faint wash so the eye
       tracks across the wide grid. Group headers reset the rhythm. */
    .feat-cell.feat-stripe { background: rgba(255,255,255,.022); }
    .feat-cell.text-center { justify-content: center; text-align: center; }
    .feat-cell.feat-head {
        border-top: 0;
        background: rgba(17,16,28,.92); backdrop-filter: blur(8px);
        text-transform: uppercase; letter-spacing: .08em;
        font-size: .68rem; font-weight: 700; color: #94a3b8;
        padding-top: 1rem; padding-bottom: 1rem;
        position: sticky; top: 0; z-index: 3;
        box-shadow: inset 0 -1px 0 rgba(144,172,255,.22);
    }
    .feat-cell.feat-row-name {
        position: sticky; left: 0; background: #0b0a14;
        z-index: 2; color: #e5e7eb; font-weight: 500;
        box-shadow: inset -1px 0 0 rgba(255,255,255,.04);
    }
    .feat-cell.feat-row-name.feat-stripe { background: #0d0c17; }
    /* The top-left corner cell sits at both sticky axes → highest layer. */
    .feat-cell.feat-head.feat-row-name { z-index: 4; background: rgba(17,16,28,.96); }
    .feat-cell.feat-group {
        background: linear-gradient(90deg, rgba(61,107,255,.18), rgba(61,107,255,.08));
        color: #bccfff;
        text-transform: uppercase; letter-spacing: .08em;
        font-size: .66rem; font-weight: 700;
        padding: .6rem 1rem;
        position: sticky; left: 0; z-index: 1;
        box-shadow: inset 2px 0 0 rgba(144,172,255,.55);
    }
    .feat-cell.feat-popular-col {
        background: rgba(61,107,255,.07);
        box-shadow: inset 1px 0 0 rgba(144,172,255,.12), inset -1px 0 0 rgba(144,172,255,.12);
    }
    .feat-cell.feat-popular-col.feat-stripe { background: rgba(61,107,255,.10); }
    .feat-cell.feat-head.feat-popular-col {
        background: rgba(61,107,255,.20);
        box-shadow: inset 0 -1px 0 rgba(144,172,255,.45), inset 1px 0 0 rgba(144,172,255,.3), inset -1px 0 0 rgba(144,172,255,.3);
    }
    .feat-mark { display: inline-flex; align-items: center; justify-content: center;
                 width: 28px; height: 28px; border-radius: 9999px; }
    .feat-mark-yes {
        background: rgba(16,185,129,.18); color: #34d399;
        box-shadow: 0 0 0 1px rgba(52,211,153,.35), 0 4px 12px -6px rgba(16,185,129,.6);
    }
    .feat-mark-no  { background: rgba(148,163,184,.08); color: #5b647a; }

    /* Light-mode overrides for the comparison matrix (dark is the default).
       The global marketing-anim.css recolors utility classes, but these
       feat-* rules use hard-coded dark values, so they need explicit
       light-mode counterparts to stay legible. */
    html.light-mode .feat-cell {
        border-top-color: rgba(15,23,42,.08); color: #475569;
    }
    html.light-mode .feat-cell.feat-head {
        background: #f1f0f7; color: #64748b;
    }
    html.light-mode .feat-cell.feat-row-name {
        background: #ffffff; color: #1e293b;
    }
    html.light-mode .feat-cell.feat-head.feat-row-name { background: #f1f0f7; }
    html.light-mode .feat-cell.feat-popular-col {
        background: rgba(61,107,255,.06);
    }
    html.light-mode .feat-cell.feat-head.feat-popular-col { background: #e6e0fb; }
    html.light-mode .feat-mark-no { color: #94a3b8; }
    html.light-mode .feat-cell.feat-stripe { background: rgba(15,23,42,.028); }
    html.light-mode .feat-cell.feat-row-name.feat-stripe { background: #f7f7fb; }
    html.light-mode .feat-cell.feat-popular-col.feat-stripe { background: rgba(61,107,255,.09); }
    html.light-mode .feat-cell.feat-group {
        background: linear-gradient(90deg, #e7e2fb, #f1eefc); color: #2342c7;
    }
    html.light-mode .feat-mark-yes { color: #047857; box-shadow: 0 0 0 1px rgba(16,185,129,.3), 0 4px 12px -6px rgba(16,185,129,.4); }

    /* ── /pricing's extension of the marketing system ──
       Everything a card looks like lives in partials/marketing-system.
       What belongs here is only what is specific to this page: the plan
       rail's track sizing, and the coin card's warm marking. */

    /* A plan card in a scrolling rail needs a floor and a ceiling on its
       width; the system's grid cannot know them. */
    .plans-row > .sy-card { flex: 1 0 300px; min-width: 300px; max-width: 372px; scroll-snap-align: start; }
    .coin-rail { --sy-min: 280px; }

    /* The lead card sits a step forward. One card, one step -- lifting
       several is a wobbly row rather than a ranking. */
    .sy-card--lead { transform: translateY(-6px); }
    .sy-card--lead:hover { transform: translateY(-10px); }

    /* Rule 1 in the one place it is tempting to break: the coin cards.
       Amber marks them as the wallet, and it does that as a ribbon on the
       bonus packs and as the flag on those packs -- not as amber words and
       not as an amber ground. The numbers are ink, like every other number
       on the site. */
    .sy-flag--coin {
        color: #7A4A05; background: #FDF3D8; border-color: #F2D89A;
    }
    html:not(.light-mode) .sy-flag--coin {
        color: #FCD9A0; background: rgba(245,158,11,.16); border-color: rgba(245,158,11,.34);
    }

    /* The current plan is a state, so it is marked, not sold: a flag and a
       held button, with no ribbon and no lift. */
    .sy-flag--current { color: #0F766E; background: #DCFCE7; border-color: #A7E8C6; }
    html:not(.light-mode) .sy-flag--current { color: #6EE7B7; background: rgba(16,185,129,.16); border-color: rgba(16,185,129,.34); }

    /* The money on a coin card's first row carries a struck original when
       there is one; both are ink, the struck one just dimmer. */
    .sy-cell-price .was { text-decoration: line-through; color: var(--sy-ink-3); margin-right: 7px; }
    .sy-cell-price b, .sy-cell-price > span:last-child { color: var(--sy-ink); font-weight: 700; }

    /* An intro offer is a line of note text, not a green chip. */
    .sy-note .sy-intro { display: block; color: var(--sy-ink-2); font-weight: 650; }

    /* ── Single-row, horizontally-scrollable plan rail ──
       All plan cards sit on one row. Each keeps a readable min-width and
       grows to fill spare space; when the combined min-widths exceed the
       container the rail scrolls horizontally (styled scrollbar + edge
       fades + a hint act as the scroll affordance). */
    .plans-rail { position: relative; }
    .plans-rail::before, .plans-rail::after {
        content: ""; position: absolute; top: 0; bottom: .75rem; width: 2.5rem;
        pointer-events: none; z-index: 5; opacity: 0; transition: opacity .3s ease;
    }
    /* Wire the edge-fade to the site layout's real, theme-aware background
       token (`--bg`: #0a0a14 dark / #f8fafc light, declared in
       public.layouts.site). The old `--page-bg` was never defined, so its
       dark fallback literal always won — smearing dark over the light page. */
    .plans-rail::before { left: 0; background: linear-gradient(90deg, var(--bg, #0a0a14), transparent); }
    .plans-rail::after  { right: 0; background: linear-gradient(270deg, var(--bg, #0a0a14), transparent); }
    .plans-rail.can-scroll-left::before  { opacity: 1; }
    .plans-rail.can-scroll-right::after { opacity: 1; }
    .plans-scroll {
        overflow-x: auto; overflow-y: hidden; -webkit-overflow-scrolling: touch;
        scroll-snap-type: x proximity;
        /* Generous top room so the accent card (lifted -6px at rest, -10px on
           hover) and any card's hover lift + gradient glow are never clipped
           by this overflow container. */
        padding: 1.5rem .25rem 1rem;
        scrollbar-width: thin; scrollbar-color: rgba(61,107,255,.55) transparent;
    }
    .plans-scroll::-webkit-scrollbar { height: 9px; }
    .plans-scroll::-webkit-scrollbar-track { background: rgba(255,255,255,.04); border-radius: 9999px; }
    .plans-scroll::-webkit-scrollbar-thumb { background: rgba(61,107,255,.5); border-radius: 9999px; }
    .plans-scroll::-webkit-scrollbar-thumb:hover { background: rgba(61,107,255,.75); }
    html.light-mode .plans-scroll::-webkit-scrollbar-track { background: rgba(15,23,42,.06); }
    .plans-row { display: flex; gap: 1.25rem; align-items: stretch; }
    

    /* Keep the gradient CTA legible in light mode — the global
       override would otherwise darken `.text-white` sitting on it. */
    html.light-mode .grad-bar.text-white,
    html.light-mode .grad-bar .text-white { color: #fff !important; }

    /* The Monthly/Annual toggle used to live here: a blue-cyan gradient knob
       with a ring, a drop-shadow and a double text-shadow to keep white
       legible on its lighter cyan end -- three workarounds for having made
       the knob a gradient in the first place. It is `.sy-seg` now, with an
       ink knob, and none of that is needed. Measured after the move: 16.5:1
       in light, 14.4:1 in dark. */

    /* ── Arbitrary white-opacity utilities the global remap misses ──
       marketing-anim.css only remaps bg-white/{.03,.05,.07}; this page also
       leans on /[0.02] and /[0.04] soft fills plus /[0.08] /[0.10] hovers,
       so give them light-mode counterparts (otherwise the surface stays
       transparent and visually disappears on the white background). */
    html.light-mode .bg-white\/\[0\.02\],
    html.light-mode .bg-white\/\[0\.04\] { background-color: rgba(15,23,42,.035) !important; }
    html.light-mode .hover\:bg-white\/\[0\.08\]:hover,
    html.light-mode .hover\:bg-white\/\[0\.10\]:hover { background-color: rgba(15,23,42,.07) !important; }


    /* ── Smart upgrade banner keeps its dark surface in BOTH modes, so its
       text and accents must stay light too — the global light-mode rules
       would otherwise darken every child into dark-on-dark. ── */
    html.light-mode .smart-banner .text-white { color: #f8fafc !important; }
    html.light-mode .smart-banner .text-gray-200,
    html.light-mode .smart-banner .text-gray-300 { color: #e3e1f0 !important; }
    html.light-mode .smart-banner .text-gray-400 { color: #bdbace !important; }
    html.light-mode .smart-banner .text-gray-500 { color: #918da8 !important; }
    html.light-mode .smart-banner [class*="text-white/"] { color: rgba(248,250,252,.7) !important; }
    html.light-mode .smart-banner .text-blue-200,
    html.light-mode .smart-banner .text-blue-300 { color: #bccfff !important; }
    html.light-mode .smart-banner .text-cyan-300    { color: #22d3ee !important; }
    html.light-mode .smart-banner .text-amber-300   { color: #fcd34d !important; }
    html.light-mode .smart-banner .text-emerald-300 { color: #6ee7b7 !important; }
    html.light-mode .smart-banner .bg-white\/10     { background-color: rgba(255,255,255,.12) !important; }
    html.light-mode .smart-banner .border-white\/10 { border-color: rgba(255,255,255,.14) !important; }

    /* ── Referral teaser icon — gradient chip, white glyph in both modes ── */
    .ref-icon { background: linear-gradient(135deg,#3d6bff,#22d3ee); color: #fff; }
    html.light-mode .ref-icon { color: #fff !important; }

    /* The smart banner keeps a dark surface in both modes, so its emerald
       status text must stay light — the global accent darken in
       marketing-anim.css (which now covers the whole 100..400 range, opacity
       variants included) would otherwise bury it dark-on-dark. Higher
       specificity wins here. */
    html.light-mode .smart-banner [class*="text-emerald-100"],
    html.light-mode .smart-banner [class*="text-emerald-200"],
    html.light-mode .smart-banner [class*="text-emerald-400"] { color: rgba(167,243,208,.85) !important; }

    @media (prefers-reduced-motion: reduce) {
        .pulse-dot, .float-coin, .pop-ribbon { animation: none !important; }
        .price-pop { animation: none !important; }
        .smart-meter > span { transition: none !important; }
        .sy-card { transition: none !important; }
        .plans-rail::before, .plans-rail::after { transition: none !important; }
        .plans-scroll { scroll-snap-type: none !important; }
    }
</style>
@endpush

@section('content')
<section
    @inme-currency.window="currency = $event.detail.c"
    x-data="{
        cycle: '{{ $cycle }}',
        currency: '{{ $currency }}',
        priceKey: 0,
        cur(plan){ return plan[this.currency] || plan.USD || {}; },
        money(plan, c){
            const block = this.cur(plan);
            const r = c === 'annual' ? block.annual : block.monthly;
            return r && r.formatted ? r.formatted : '—';
        },
        intro(plan){
            /* First-term introductory discount for the active currency +
               cycle (null when none applies). Drives the struck-through
               normal price, the discounted headline and the savings badge. */
            const block = this.cur(plan);
            const r = this.cycle === 'annual' ? block.annual : block.monthly;
            return (r && r.intro) ? r.intro : null;
        },
        priceSize(plan){
            /* The headline price uses a fluid (clamped) base size, but very
               long currency strings — notably INR per-month amounts —
               still need to step down a notch (or two) so they never run
               past the card edge. On annual we measure the per-month string
               (that's what's shown as the headline); on monthly we measure
               the monthly price. Reactive on currency + cycle. */
            const s = (this.cycle === 'annual' && this.hasAnnual(plan)
                ? this.perMonth(plan)
                : (this.money(plan, this.cycle) || '')
            ).replace(/\s/g, '');
            const len = s.length;
            if (len >= 11) return 'price-xs';
            if (len >= 9)  return 'price-sm';
            if (len >= 7)  return 'price-md';
            return '';
        },
        hasAnnual(plan){
            const a = this.cur(plan).annual;
            return a && Number(a.amount_minor) > 0;
        },
        perMonth(plan){
            const a = this.cur(plan).annual;
            if (!a) return '';
            return (Number(a.amount_minor) / 12 / 100)
                .toLocaleString(undefined, { style: 'currency', currency: a.currency || this.currency });
        },
        introPerMonth(plan){
            /* Effective per-month of the discounted first-year intro price. */
            const i = this.intro(plan);
            if (!i) return '';
            return (Number(i.first_minor) / 12 / 100)
                .toLocaleString(undefined, { style: 'currency', currency: this.currency });
        },
        introNormalPerMonth(plan){
            /* Per-month of the normal (non-discounted) annual price shown as
               the struck-through 'Was' and the renewal rate on intro cards. */
            const i = this.intro(plan);
            if (!i) return '';
            return (Number(i.normal_minor) / 12 / 100)
                .toLocaleString(undefined, { style: 'currency', currency: this.currency });
        },
        billingNote(plan){
            /* Fineprint under the big price.
               Annual: headline is per-month, so fineprint shows the annual
               total (what's actually charged) and the higher month-to-month
               rate so the saving is obvious.
               Monthly: tease the cheaper annual per-month equivalent. */
            const monthly = this.money(plan, 'monthly');
            if (this.cycle === 'annual' && this.hasAnnual(plan)) {
                const annualTotal = this.money(plan, 'annual');
                return monthly && monthly !== '—'
                    ? 'Billed annually at ' + annualTotal + '/yr, or ' + monthly + '/mo month-to-month'
                    : 'Billed annually at ' + annualTotal + '/yr';
            }
            if (this.hasAnnual(plan)) {
                return 'Billed monthly, or ' + this.perMonth(plan) + '/mo billed annually';
            }
            return 'Billed monthly';
        },
        coinPrice(prices){
            const p = prices[this.currency] || prices.USD || {};
            return p.formatted || '—';
        },
        coinOriginal(prices){
            const p = prices[this.currency] || prices.USD || {};
            return p.original_formatted || '';
        },
        switchCurrency(c){
            if (this.currency === c) return;
            this.currency = c;
            this.priceKey++;
            /* Persist the choice (session + cookie + user preference) in the
               background — the UI has already re-rendered, so we never block on it. */
            const url = '{{ route('upgrade.public.switch-currency') }}';
            const token = document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '';
            const data = new FormData();
            data.append('currency', c);
            data.append('_token', token);
            try {
                fetch(url, {
                    method: 'POST',
                    body: data,
                    credentials: 'same-origin',
                    keepalive: true,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
            } catch (e) { /* swallow — UX must not depend on persistence */ }
        },
        rememberCycle(c){
            /* Persist the chosen cycle server-side so a refresh, menu
               navigation, or return visit lands on the same toggle.
               Best-effort — we never block the UI on the response. */
            const url = '{{ route('site.pricing.cycle') }}';
            const token = document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '';
            const data = new FormData();
            data.append('cycle', c);
            data.append('_token', token);
            try {
                fetch(url, {
                    method: 'POST',
                    body: data,
                    credentials: 'same-origin',
                    keepalive: true,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
            } catch (e) { /* swallow — UX must not depend on persistence */ }
        },
        trackCoinsView(){
            const url = '{{ route('marketing-events.track') }}';
            const data = new FormData();
            data.append('source', 'landing_pricing_teaser');
            data.append('target', 'coins');
            if (navigator.sendBeacon) {
                navigator.sendBeacon(url, data);
            } else {
                fetch(url, { method: 'POST', body: data, keepalive: true, credentials: 'same-origin' });
            }
        }
    }"
    x-init="
        $watch('cycle', (val) => { priceKey++; rememberCycle(val); });
        /* Coin packages no longer live behind a tab toggle; instead
           we fire the marketing event when the coins section either
           is the deep-linked target on load or scrolls into view. */
        const fireOnceCoins = (() => { let fired = false; return () => { if (!fired) { fired = true; trackCoinsView(); } }; })();
        if (window.location.hash === '#coins') { fireOnceCoins(); }
        const coinsEl = document.getElementById('coins');
        if (coinsEl && 'IntersectionObserver' in window) {
            const io = new IntersectionObserver((entries) => {
                entries.forEach(e => { if (e.isIntersecting) { fireOnceCoins(); io.disconnect(); } });
            }, { rootMargin: '0px 0px -25% 0px' });
            io.observe(coinsEl);
        }

        /* Level the description boxes to the tallest in each rail.
           These blocks sit above the price panel, so a card whose copy runs a
           line longer pushes its price and CTA below its neighbours'. The old
           answer was a fixed three-line box, which lined the cards up by
           cutting the longer descriptions mid-sentence.
           Measuring costs nothing and guesses nothing: whatever an admin
           writes, every card gets the same box and every sentence finishes.
           The CSS min-height stays as the floor, so with JS off the page is
           exactly what it was. */
        const levelDescriptions = () => {
            document.querySelectorAll('.plans-row, .coin-rail').forEach((rail) => {
                const boxes = rail.querySelectorAll('.plan-band-desc');
                if (boxes.length < 2) return;
                boxes.forEach((b) => { b.style.minHeight = ''; });
                let tallest = 0;
                boxes.forEach((b) => { tallest = Math.max(tallest, b.getBoundingClientRect().height); });
                boxes.forEach((b) => { b.style.minHeight = Math.ceil(tallest) + 'px'; });
            });
        };
        /* After the webfont lands, or the measurement is of the fallback. */
        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(levelDescriptions);
        } else {
            levelDescriptions();
        }
        let levelTimer;
        window.addEventListener('resize', () => {
            clearTimeout(levelTimer);
            levelTimer = setTimeout(levelDescriptions, 150);
        });
    "
    class="sy sec-first relative pt-20 pb-12 lg:pt-28 lg:pb-16">
    <div class="absolute inset-0 -z-10 overflow-hidden">
        <div class="absolute -top-32 left-1/2 -translate-x-1/2 w-[60rem] h-[60rem] rounded-full opacity-30 blur-[120px]"
             style="background: #3d6bff;"></div>
        <div class="absolute top-40 -right-32 w-[36rem] h-[36rem] rounded-full opacity-20 blur-[100px]"
             style="background: #5c83ff;"></div>
        <div class="absolute top-72 -left-32 w-[36rem] h-[36rem] rounded-full opacity-20 blur-[100px]"
             style="background: #2342c7;"></div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto space-y-5" data-anim="fade-up">
            <div class="sy-flag">
                <span class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75 animate-ping motion-reduce:hidden"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-blue-300"></span>
                </span>
                Pricing &amp; coins
            </div>
            <h1 class="hero-title text-4xl sm:text-5xl lg:text-[3.65rem] font-bold tracking-tight leading-[1.05]">
                {{-- Rule 1: the second half of this sentence was gradient-
                     clipped text. The gradient is a ribbon now, under the
                     line, where it marks without competing. --}}
                Pricing that scales with you.
            </h1>
            <p class="text-lg text-gray-300 opacity-90 max-w-xl mx-auto">
                Start free, upgrade only when you outgrow it. Powerful features, transparent pricing, cancel anytime.
            </p>
            <div class="flex flex-wrap items-center justify-center gap-x-2.5 gap-y-2 pt-1 pb-4 text-xs">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/[0.04] border border-white/10 text-gray-300"><i class="fas fa-circle-check sy-ico"></i> No card to start</span>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/[0.04] border border-white/10 text-gray-300"><i class="fas fa-circle-check sy-ico"></i> Cancel anytime</span>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/[0.04] border border-white/10 text-gray-300"><i class="fas fa-circle-check sy-ico"></i> Secure checkout</span>
            </div>
        </div>

        {{-- ───────────────── SMART UPGRADE BANNER (logged-in only) ───────────────── --}}
        @auth
            @php $rec = $recommendation; @endphp
            @if($rec)
                <div data-anim="fade-up" class="smart-banner mt-10 rounded-2xl border border-white/10 p-5 sm:p-6">
                    <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] gap-6">
                        {{-- Left: current plan + usage gauges --}}
                        <div>
                            <div class="sy-eyebrow" style="margin-bottom:4px;">
                                <i class="fas fa-user-circle"></i> You're signed in
                            </div>
                            <div class="text-white text-lg font-semibold">
                                You're on
                                <span class="smart-pill inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-sm align-middle">
                                    <i class="fas fa-bolt sy-ico text-xs"></i> {{ $rec['currentPlan']?->name ?? 'no plan' }}
                                </span>
                            </div>
                            @if(!empty($rec['usage']))
                                <div class="mt-4 space-y-2.5">
                                    @foreach($rec['usage'] as $u)
                                        <div>
                                            <div class="flex items-baseline justify-between text-xs">
                                                <span class="text-gray-300 capitalize">{{ $u['label'] }}</span>
                                                <span class="text-gray-400">
                                                    @if($u['unlimited'])
                                                        <span class="font-semibold">{{ number_format($u['used']) }}</span> · unlimited
                                                    @else
                                                        <span class="text-white font-semibold">{{ number_format($u['used']) }}</span> / {{ number_format($u['cap']) }}
                                                        <span class="text-gray-500">({{ $u['pct'] }}%)</span>
                                                    @endif
                                                </span>
                                            </div>
                                            @unless($u['unlimited'])
                                                <div class="smart-meter mt-1 {{ $u['pct'] >= 70 ? 'warn' : '' }}">
                                                    <span style="width: {{ max(2, $u['pct']) }}%"></span>
                                                </div>
                                            @endunless
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="mt-3 text-sm text-gray-400">Start adding links and Link in Bio pages to see how much room your plan has.</p>
                            @endif
                        </div>

                        {{-- Right: recommendation card --}}
                        @if($rec['recommendedPlan'])
                            @php $recPlan = $rec['recommendedPlan']; @endphp
                            <a href="{{ route('user.upgrade', ['cycle' => $cycle]) }}"
                               class="group block rounded-2xl border border-blue-400/40 p-5 bg-blue-600/15 hover:bg-blue-600/25 transition relative overflow-hidden">
                                <div class="absolute -right-10 -top-10 w-32 h-32 rounded-full bg-blue-500/20 blur-2xl pointer-events-none"></div>
                                <div class="sy-eyebrow" style="margin-bottom:4px;">
                                    <i class="fas fa-wand-magic-sparkles"></i> Recommended for you
                                </div>
                                <div class="flex items-baseline justify-between gap-3 mt-1">
                                    <div>
                                        <div class="text-2xl font-bold text-white">Upgrade to {{ $recPlan->name }}</div>
                                        <div class="text-sm text-gray-300 mt-1">{{ $rec['reason'] }}</div>
                                    </div>
                                    <i class="fas fa-arrow-right sy-ico group-hover:translate-x-1 transition"></i>
                                </div>
                                <div class="mt-4 inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/10 text-xs font-semibold text-white">
                                    See plans &amp; checkout <i class="fas fa-chevron-right text-[10px]"></i>
                                </div>
                            </a>
                        @else
                            {{-- Rules 1, 2 and 7: a green panel with a green
                                 circle and three shades of green text, for a
                                 message that is simply good news. --}}
                            <div class="sy-cells" style="margin-top:0;">
                                <div class="sy-cell">
                                    <i class="fas fa-circle-check" aria-hidden="true"></i>
                                    <span class="sy-cell-body">
                                        <b>You're on our top tier.</b>
                                        <span class="sy-cell-sub">Add coin packs below for one-off boosts.</span>
                                    </span>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        @endauth

        {{-- Cycle toggle — sliding segmented control that surfaces the
             annual savings. The knob is an absolutely-positioned layer that
             slides under the active label (transition disabled under
             reduced-motion via the motion-reduce utilities). --}}
        <div class="flex flex-col items-center justify-center gap-2.5 mt-10" data-anim="fade-up">
            <div class="sy-seg" role="tablist" aria-label="Billing cadence">
                <span class="sy-seg-knob" :style="cycle==='annual' ? 'transform: translateX(100%)' : 'transform: translateX(0)'" aria-hidden="true"></span>
                <button type="button" role="tab" @click="cycle='monthly'"
                    :aria-selected="cycle==='monthly'" :class="cycle==='monthly' ? 'is-on' : ''">Monthly</button>
                <button type="button" role="tab" @click="cycle='annual'"
                    :aria-selected="cycle==='annual'" :class="cycle==='annual' ? 'is-on' : ''">Annual</button>
            </div>
            {{-- Savings callout — emphasised on annual, teasing on monthly --}}
            <div class="h-5 text-xs" aria-live="polite">
                <span x-show="cycle==='annual'" x-cloak class="inline-flex items-center gap-1.5 font-semibold">
                    <i class="fas fa-circle-check"></i> You're saving 2 months, that's ~17% off every year
                </span>
                <span x-show="cycle==='monthly'" x-cloak class="inline-flex items-center gap-1.5 text-gray-400">
                    <i class="fas fa-piggy-bank sy-ico"></i> Switch to annual and save 2 months (~17%)
                </span>
            </div>
        </div>

        {{-- ───────────────── PLANS RAIL ─────────────────
             All plan cards live on one horizontal rail. They grow to
             fill the width when there's room and scroll horizontally
             (styled scrollbar + edge fades + hint) once their combined
             min-widths overflow the container — no responsive
             column-count logic to keep in sync with the plan count. --}}
        @php
            $planCount = count($plans);
            // All plan cards sit on a single horizontal rail (see the
            // `.plans-rail` / `.plans-scroll` styles). Each card keeps a
            // readable min-width and grows to fill spare space; when the
            // combined widths exceed the container the rail scrolls
            // horizontally, so there's no responsive column-count logic
            // to maintain regardless of how many tiers the seeder ships.
            $hasRec = isset($recommendation['recommendedPlan']) && $recommendation['recommendedPlan'];
            $recPlanId = $hasRec ? $recommendation['recommendedPlan']->id : null;
            $currentPlanId = $recommendation['currentPlan']->id ?? null;
            // True only when a *distinct* recommended card will be shown
            // (a recommendation exists and it isn't the user's current plan).
            // Drives single-highlight: when a recommended card carries the
            // accent, "Most popular" stays a badge-only secondary treatment.
            $showsRecommended = $hasRec && $recPlanId !== $currentPlanId;

            // Re-index so each card can reach the plan before it for the
            // Linktree-style "Everything in {previous}, plus:" delta list.
            $plansArr = $plans->values();

            // Does ANY plan carry a first-term intro discount (any currency ×
            // cycle)? When one does, every card reserves the intro badge +
            // "was/renews" slots so the headline price, billing note and the
            // CTA below stay on the same baseline whether or not a given card
            // shows an offer for the active currency/cycle. When none do, the
            // reservation is dropped so the price block stays tight.
            $anyIntro = $plansArr->contains(function ($r) {
                foreach (($r['prices'] ?? []) as $byCycle) {
                    foreach ((array) $byCycle as $p) {
                        if (!empty($p['intro'])) return true;
                    }
                }
                return false;
            });

            // ── Feature display catalogue ──────────────────────────────
            // Maps the plan `features` blob into Linktree-style grouped,
            // icon-led entries (bold name + one-line description). This is
            // presentation only — *which* features each plan has is still
            // driven entirely by the seeded features blob; we just choose
            // how to label and group what's already there.
            $featureCatalog = [
                ['key' => 'max_links',          'group' => 'Link in bio',      'icon' => 'fa-link',                       'type' => 'number',    'noun' => 'short links',        'desc' => 'Trackable short links with click analytics'],
                ['key' => 'max_biolinks',       'group' => 'Link in bio',      'icon' => 'fa-id-badge',                   'type' => 'number',    'noun' => 'Link in Bio pages',  'desc' => 'Mini-site bio pages for your audience'],
                ['key' => 'storage_limit_mb',   'group' => 'Link in bio',      'icon' => 'fa-database',                   'type' => 'storage',   'noun' => 'storage',            'desc' => 'Space for files, images & media'],
                ['key' => 'max_files',          'group' => 'Link in bio',      'icon' => 'fa-folder-open',                'type' => 'number',    'noun' => 'hosted files',       'desc' => 'Upload & share downloadable files'],
                ['key' => 'block_types_allowed','group' => 'Link in bio',      'icon' => 'fa-table-cells',                'type' => 'blocks',                                    'desc' => 'Content block types for your pages'],
                ['key' => 'custom_domains',     'group' => 'Link in bio',      'icon' => 'fa-globe',                      'type' => 'bool',      'name' => 'Custom domains',     'desc' => 'Use your own branded domain'],
                ['key' => 'white_label',        'group' => 'Link in bio',      'icon' => 'fa-tag',                        'type' => 'bool',      'name' => 'Remove Sayzio branding','desc' => 'White-label your public pages'],

                ['key' => 'analytics',          'group' => 'Analytics & SEO',  'icon' => 'fa-chart-line',                 'type' => 'analytics'],
                ['key' => 'pixels',             'group' => 'Analytics & SEO',  'icon' => 'fa-bullseye',                   'type' => 'bool',      'name' => 'Marketing pixels',   'desc' => 'FB, GA, TikTok, LinkedIn & more'],
                ['key' => 'utm_params',         'group' => 'Analytics & SEO',  'icon' => 'fa-compass',                    'type' => 'bool',      'name' => 'UTM campaign tracking','desc' => 'Tag links for clean attribution'],
                ['key' => 'seo_settings',       'group' => 'Analytics & SEO',  'icon' => 'fa-magnifying-glass',           'type' => 'bool',      'name' => 'SEO & social previews','desc' => 'Control titles, meta & OG cards'],

                ['key' => 'ecommerce',          'group' => 'Make money',       'icon' => 'fa-bag-shopping',               'type' => 'bool',      'name' => 'Sell from your bio', 'desc' => 'Native product checkout'],
                ['key' => 'leads',              'group' => 'Make money',       'icon' => 'fa-magnet',                     'type' => 'bool',      'name' => 'Lead capture & CRM', 'desc' => 'Collect and manage leads'],
                ['key' => 'custom_forms',       'group' => 'Make money',       'icon' => 'fa-clipboard-list',             'type' => 'bool',      'name' => 'Custom forms',       'desc' => '21 field types with notifications'],

                ['key' => 'contacts_max',       'group' => 'Growth tools',     'icon' => 'fa-address-book',               'type' => 'number',    'noun' => 'contacts',           'desc' => 'Address-book contacts & dialer'],
                ['key' => 'max_forms',          'group' => 'Growth tools',     'icon' => 'fa-rectangle-list',             'type' => 'number',    'noun' => 'forms',              'desc' => 'Publishable forms'],
                ['key' => 'max_projects',       'group' => 'Growth tools',     'icon' => 'fa-folder',                     'type' => 'number',    'noun' => 'projects',           'desc' => 'Separate project workspaces'],
                ['key' => 'teams',              'group' => 'Growth tools',     'icon' => 'fa-people-group',               'type' => 'bool',      'name' => 'Team workspaces',    'desc' => 'Invite teammates & seats'],
                ['key' => 'creator_profile_public','group' => 'Growth tools',  'icon' => 'fa-star',                       'type' => 'bool',      'name' => 'Public creator profile','desc' => 'A discoverable creator page'],
                ['key' => 'verification_eligible','group' => 'Growth tools',   'icon' => 'fa-circle-check',               'type' => 'bool',      'name' => 'Verified-creator eligible','desc' => 'Apply for a verified badge'],
                ['key' => 'calendar_sync',      'group' => 'Growth tools',     'icon' => 'fa-calendar-days',              'type' => 'bool',      'name' => 'Calendar sync',      'desc' => 'Sync events to your calendar'],

                ['key' => 'link_password',      'group' => 'Pro controls',     'icon' => 'fa-lock',                       'type' => 'bool',      'name' => 'Password-protected links','desc' => 'Lock links behind a password'],
                ['key' => 'link_expiry',        'group' => 'Pro controls',     'icon' => 'fa-hourglass-half',             'type' => 'bool',      'name' => 'Link expiry',        'desc' => 'Auto-expire links on a date'],
                ['key' => 'link_geo_targeting', 'group' => 'Pro controls',     'icon' => 'fa-map-location-dot',           'type' => 'bool',      'name' => 'Geo targeting',      'desc' => 'Redirect by visitor location'],
                ['key' => 'link_device_targeting','group' => 'Pro controls',   'icon' => 'fa-mobile-screen',              'type' => 'bool',      'name' => 'Device targeting',   'desc' => 'Redirect by device or OS'],
                ['key' => 'link_deep_link',     'group' => 'Pro controls',     'icon' => 'fa-arrow-up-right-from-square', 'type' => 'bool',      'name' => 'Deep links',         'desc' => 'Open links inside native apps'],
                ['key' => 'link_smart_rules',   'group' => 'Pro controls',     'icon' => 'fa-brain',                      'type' => 'bool',      'name' => 'Smart redirect rules','desc' => 'Rule-based link routing'],
                ['key' => 'link_active_window', 'group' => 'Pro controls',     'icon' => 'fa-clock',                      'type' => 'bool',      'name' => 'Active-window scheduling','desc' => 'Schedule when links are live'],
                ['key' => 'vaults',             'group' => 'Pro controls',     'icon' => 'fa-key',                        'type' => 'bool',      'name' => 'Credential vault',   'desc' => 'Store secrets securely'],

                ['key' => 'api_access',         'group' => 'Developer',        'icon' => 'fa-code',                       'type' => 'api'],

                // Included coin grants (features.included_coins_monthly / _yearly, 0 = hidden).
                ['key' => 'included_coins_monthly', 'group' => 'Included coins', 'icon' => 'fa-coins', 'type' => 'number', 'noun' => 'coins / month included', 'desc' => 'Credited to your wallet every monthly billing cycle'],
                ['key' => 'included_coins_yearly',  'group' => 'Included coins', 'icon' => 'fa-coins', 'type' => 'number', 'noun' => 'coins / year included',  'desc' => 'Credited to your wallet every yearly billing cycle'],
            ];

            // Biolink block-type slug → human-readable label resolution (and
            // canonicalization of friendly/legacy allowlist slugs to real
            // block types, so labels match what editor gating enforces) lives
            // in the shared PlanBlockLabels helper so this page and the in-app
            // /user/upgrade page stay in sync.

            // Evaluate a single catalogue entry against a features blob.
            // Returns null when the feature isn't present/enabled, or an
            // array with a numeric weight (for delta comparison), a bold
            // name, and a description line.
            $evalFeature = function (array $features, array $e) {
                $type = $e['type'];
                $key  = $e['key'];

                if ($type === 'number') {
                    if (!isset($features[$key])) return null;
                    $v = (int) $features[$key];
                    if ($v === 0) return null;
                    $name = $v === -1 ? 'Unlimited ' . $e['noun'] : number_format($v) . ' ' . $e['noun'];
                    return ['num' => $v === -1 ? PHP_INT_MAX : $v, 'name' => $name, 'desc' => $e['desc']];
                }
                if ($type === 'storage') {
                    if (!isset($features[$key])) return null;
                    $v = (int) $features[$key];
                    if ($v === 0) return null;
                    if ($v === -1) { $num = PHP_INT_MAX; $name = 'Unlimited storage'; }
                    elseif ($v >= 1024) { $num = $v; $name = rtrim(rtrim(number_format($v / 1024, 1), '0'), '.') . ' GB storage'; }
                    else { $num = $v; $name = number_format($v) . ' MB storage'; }
                    return ['num' => $num, 'name' => $name, 'desc' => $e['desc']];
                }
                if ($type === 'blocks') {
                    $val = $features[$key] ?? null;
                    if (\App\Modules\Common\Support\PlanBlockLabels::isAll($val)) {
                        return ['num' => PHP_INT_MAX, 'name' => 'All Link in Bio blocks', 'desc' => $e['desc'], 'blocks' => null];
                    }
                    $labels = \App\Modules\Common\Support\PlanBlockLabels::labelsFor($val);
                    if (!$labels) return null;
                    return ['num' => count($labels), 'name' => count($labels) . ' Link in Bio blocks', 'desc' => $e['desc'], 'blocks' => $labels];
                }
                if ($type === 'analytics') {
                    $val = $features[$key] ?? null;
                    if (!$val) return null;
                    $adv = strtolower((string) $val) === 'advanced';
                    return [
                        'num'  => $adv ? 2 : 1,
                        'name' => $adv ? 'Advanced analytics' : 'Click & view analytics',
                        'desc' => $adv ? 'Geo, device & referrer breakdowns' : 'Clicks and views over time',
                    ];
                }
                if ($type === 'api') {
                    if (empty($features[$key])) return null;
                    $calls = (int) ($features['api_calls_monthly'] ?? 0);
                    $callLabel = $calls === -1 ? 'Unlimited API calls / month' : number_format($calls) . ' API calls / month (coin top-up beyond)';
                    return ['num' => 1, 'name' => 'Developer API access', 'desc' => $callLabel];
                }
                // bool
                return !empty($features[$key])
                    ? ['num' => 1, 'name' => $e['name'], 'desc' => $e['desc']]
                    : null;
            };

            // Build the grouped feature list for a plan. When $prev is
            // supplied we only keep the *delta* (newly enabled, or a
            // numeric cap that went up) so each card reads as
            // "Everything in {previous}, plus:".
            $buildGroups = function ($plan, $prev) use ($featureCatalog, $evalFeature) {
                if (!$plan) return [];
                $cf = $plan->features ?? [];
                $pf = $prev->features ?? [];
                $groups = [];
                foreach ($featureCatalog as $e) {
                    $cur = $evalFeature($cf, $e);
                    if (!$cur) continue;
                    if ($prev) {
                        $old = $evalFeature($pf, $e);
                        if ($old && $cur['num'] <= $old['num']) continue;
                    }
                    $groups[$e['group']][] = $cur + ['icon' => $e['icon']];
                }
                return $groups;
            };
        @endphp
        {{-- The plan cards are the page, and nothing named them.

             Each card's tier is an <h3>, and the nearest heading above the
             rail was the page's own <h1>, so the outline read h1 then h3 --
             the one heading-level skip on /pricing, and the same one on
             /coins, which renders this view too.

             The heading is visually hidden rather than drawn because the rail
             needs no title on screen: the cards say what they are. It exists
             for the outline and for a screen reader, which can now jump to the
             plans as a group instead of landing on "Starter" with nothing
             saying what Starter is one of. --}}
        <h2 class="sr-only">Plans</h2>
        <div data-anim="fade-up" class="plans-rail mt-8"
             x-data="{
                 scrollable: false,
                 update() {
                     const s = this.$refs.scroll;
                     if (!s) return;
                     const max = s.scrollWidth - s.clientWidth;
                     this.scrollable = max > 4;
                     this.$el.classList.toggle('can-scroll-left', s.scrollLeft > 4);
                     this.$el.classList.toggle('can-scroll-right', s.scrollLeft < max - 4);
                 }
             }"
             x-init="$nextTick(() => update()); window.addEventListener('resize', () => update());">
            <div class="plans-scroll" x-ref="scroll" @scroll="update()">
                <div class="plans-row">
            @foreach($plansArr as $i => $row)
                @php
                    $plan = $row['model'];
                    $features = $plan->features ?? [];
                    $isPopular = $plan->is_popular;
                    $isCurrent = $currentPlanId === $plan->id;
                    $isRecommended = $recPlanId === $plan->id && !$isCurrent;
                    // Single-highlight: the recommended card takes the accent
                    // when one is shown; otherwise the "Most popular" card does.
                    // (Popular still keeps its badge either way.)
                    $isAccent = $isRecommended || ($isPopular && !$showsRecommended);
                    $cmpKind = auth()->check()
                        ? \App\Services\PlanRecommender::compare($recommendation['currentPlan'] ?? null, $plan)
                        : 'guest';
                    $planJs = $row['prices']; // { USD: {monthly, annual}, INR: {monthly, annual} }
                    $prevPlan = $i > 0 ? $plansArr[$i - 1]['model'] : null;
                    $groups = $buildGroups($plan, $prevPlan);
                    $ctaFilled = $isAccent;
                    $borderClasses = $isCurrent
                        ? 'border-emerald-400/50'
                        : ($isRecommended
                            ? 'border-cyan-400/50'
                            : ($isPopular
                                ? 'border-blue-500/50'
                                : 'border-white/10'));
                    // Header-band tint mirrors the card emphasis.
                    $bandClass = $isCurrent ? 'is-current' : ($isAccent ? 'is-accent' : '');
                    // Per-tier header chip icon — adds a visual accent to the
                    // (otherwise plain) name/description band, picked by tier
                    // emphasis so every card reads consistently.
                    $isFreeTier = !empty($row['is_free']);
                    $tierIcon = $isCurrent ? 'fa-circle-check'
                        : ($isRecommended ? 'fa-wand-magic-sparkles'
                        : ($isPopular ? 'fa-star'
                        : ($isFreeTier ? 'fa-gift' : 'fa-gem')));
                @endphp
                <div
                    x-data='{ plan: @json($planJs) }'
                    class="sy-card {{ $isAccent ? 'sy-card--lead sy-ribbon card-lit' : '' }} {{ $isCurrent ? 'sy-card--current' : '' }} plan-card">

                    <div class="sy-head">
                        <div class="sy-eyebrow">{{ $plan->name }}</div>
                        @if($isCurrent)
                            <span class="sy-flag sy-flag--current"><i class="fas fa-circle-check"></i> Your plan</span>
                        @elseif($isRecommended)
                            <span class="sy-flag">Recommended</span>
                        @elseif($isPopular)
                            <span class="sy-flag">Most popular</span>
                        @endif
                    </div>

                    @php
                        // SSR price, so the number is right before Alpine
                        // hydrates. On the annual cycle the headline is the
                        // per-month equivalent, matching what the card says.
                        if ($cycle === 'annual' && empty($row['is_free'])) {
                            $annualMinor = (int) ($row['prices'][$currency]['annual']['amount_minor'] ?? 0);
                            $initialFormatted = $annualMinor > 0
                                ? \App\Services\PricingResolver::money((int) round($annualMinor / 12), $currency)
                                : ($row['prices'][$currency]['monthly']['formatted'] ?? '—');
                        } else {
                            $initialFormatted = $row['prices'][$currency][$cycle]['formatted'] ?? '—';
                        }
                    @endphp

                    <div class="sy-figure">
                        <span x-text="intro(plan) ? (cycle==='annual' ? introPerMonth(plan) : intro(plan).first_formatted) : (cycle==='annual' && hasAnnual(plan) ? perMonth(plan) : money(plan, cycle))">{{ $initialFormatted }}</span>
                        @unless(!empty($row['is_free']))
                            <span class="unit">/ mo</span>
                        @endunless
                    </div>

                    {{-- Intro offer, billing cadence and tax all read as one
                         quiet block under the number rather than three boxes
                         with their own reserved heights. --}}
                    <div class="sy-note">
                        <template x-if="intro(plan)">
                            <span class="sy-intro">
                                <i class="fas fa-bolt"></i>
                                <span x-text="(intro(plan)?.label) || ('Save ' + intro(plan)?.percent_off + '% first ' + (cycle==='annual' ? 'year' : 'month'))"></span>
                            </span>
                        </template>
                        <template x-if="intro(plan)">
                            <span class="block">
                                <span class="was" x-text="cycle==='annual' ? introNormalPerMonth(plan) : (intro(plan)?.normal_formatted ?? '')"></span>
                                renews at <span x-text="cycle==='annual' ? introNormalPerMonth(plan) : (intro(plan)?.normal_formatted ?? '')"></span>/mo
                            </span>
                        </template>
                        @if(!empty($row['is_free']))
                            <span class="block">Free forever, no card required</span>
                        @else
                            <span class="block" x-text="billingNote(plan)"></span>
                        @endif
                        @foreach(['USD','INR'] as $cur)
                            @foreach(['monthly','annual'] as $c)
                                @php $taxBlock = $row['tax'][$cur][$c] ?? null; @endphp
                                @if(($row['prices'][$cur][$c]['amount_minor'] ?? 0) > 0)
                                    <span class="block" x-show="currency==='{{ $cur }}' && cycle==='{{ $c }}'" x-cloak>
                                        @if(!empty($taxBlock) && !empty($taxBlock['tax_breakdown']))
                                            @foreach($taxBlock['tax_breakdown'] as $line)
                                                + {{ $line['label'] }} {{ \App\Services\PricingResolver::money((int) $line['amount_minor'], $cur) }}<br>
                                            @endforeach
                                            Total {{ \App\Services\PricingResolver::money((int) $taxBlock['grand_total_minor'], $cur) }}
                                        @else
                                            + taxes as applicable, shown at checkout.
                                        @endif
                                    </span>
                                @endif
                            @endforeach
                        @endforeach
                    </div>

                    <p class="sy-blurb plan-band-desc">{{ $plan->description }}</p>

                    {{-- The CTA sits above the feature list, not below it.
                         A visitor who has decided at the price should not have
                         to scroll past forty features to act. --}}
                    <div class="sy-foot sy-foot--lead">
                        @switch($cmpKind)
                            @case('current')
                                <span class="sy-cta sy-cta--held"><i class="fas fa-check"></i> Current plan</span>
                                @break
                            @case('upgrade')
                                <a :href="'{{ route('user.upgrade') }}?cycle=' + cycle" href="{{ route('user.upgrade', ['cycle' => $cycle]) }}" class="surface-lit-keep sy-cta">
                                    Upgrade to {{ $plan->name }} <i class="fas fa-arrow-right"></i>
                                </a>
                                @break
                            @case('downgrade')
                                <a :href="'{{ route('user.upgrade') }}?cycle=' + cycle" href="{{ route('user.upgrade', ['cycle' => $cycle]) }}" class="surface-lit-keep sy-cta sy-cta--ghost">
                                    Downgrade to {{ $plan->name }}
                                </a>
                                @break
                            @case('choose')
                                <a :href="'{{ route('user.upgrade') }}?cycle=' + cycle" href="{{ route('user.upgrade', ['cycle' => $cycle]) }}" class="surface-lit-keep sy-cta {{ $ctaFilled ? '' : 'sy-cta--ghost' }}">
                                    Choose {{ $plan->name }}
                                </a>
                                @break
                            @default
                                <a href="{{ route('user.register') }}" class="surface-lit-keep sy-cta {{ $ctaFilled ? '' : 'sy-cta--ghost' }}">
                                    {{ !empty($row['is_free']) ? 'Get started free' : 'Start free trial' }} <i class="fas fa-arrow-right"></i>
                                </a>
                        @endswitch
                    </div>

                    {{-- Hairline rows, not forty bordered chips with their own
                         fills and their own ring colours. A rule between rows
                         says "separate item" and then gets out of the way. --}}
                    <div class="sy-cells">
                        <div class="sy-group sy-group--first">
                            {{ $prevPlan ? 'Everything in ' . $prevPlan->name . ', plus' : 'Key features' }}
                        </div>
                        @forelse($groups as $groupName => $items)
                            <div class="sy-group">{{ $groupName }}</div>
                            @foreach($items as $f)
                                <div class="sy-cell">
                                    <i class="fas {{ $f['icon'] }}" aria-hidden="true"></i>
                                    <span class="sy-cell-body">
                                        <b>{{ $f['name'] }}</b>
                                        @if(!empty($f['desc']))<span class="sy-cell-sub">{{ $f['desc'] }}</span>@endif
                                        @if(!empty($f['blocks']))
                                            @php $blockNames = $f['blocks']; $blockPreview = array_slice($blockNames, 0, 6); $blockExtra = count($blockNames) - count($blockPreview); @endphp
                                            <span x-data="{ open: false }" class="sy-cell-sub">
                                                <span x-show="!open">{{ implode(', ', $blockPreview) }}@if($blockExtra > 0) &amp; {{ $blockExtra }} more @endif</span>
                                                <span x-show="open" x-cloak>{{ implode(', ', $blockNames) }}</span>
                                                @if($blockExtra > 0)
                                                    <button type="button" @click="open = !open" class="sy-more">
                                                        <span x-show="!open">Show all {{ count($blockNames) }}</span>
                                                        <span x-show="open" x-cloak>Show fewer</span>
                                                    </button>
                                                @endif
                                            </span>
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                        @empty
                            <div class="sy-cell"><span class="sy-cell-body"><b>Everything you need to get started.</b></span></div>
                        @endforelse
                    </div>

                </div>
            @endforeach
                </div>
            </div>
            @if($planCount > 1)
                <div x-show="scrollable" x-cloak class="mt-3 flex items-center justify-center gap-2 text-[11px] text-gray-500" aria-hidden="true">
                    <i class="fas fa-arrows-left-right sy-ico"></i>
                    Scroll to compare every plan
                </div>
            @endif
        </div>

        {{-- Rule 3: sections are separated by a labelled rule, not by a
             guess at a margin. --}}
        <div class="sy-divider-label"><span>Compare every plan</span></div>

        {{-- ───────────────── COMPARE FEATURES AT A GLANCE ───────────────── --}}
        @php
            // Plan-vs-plan feature matrix. Reuses the same labels from
            // the per-card lists above so visitors can scan deltas without
            // hunting through cards.
            $matrixGroups = [
                'Limits' => [
                    ['max_links',         'Short links',          'number'],
                    ['max_biolinks',      'Link in Bio pages',    'number'],
                    ['max_projects',      'Projects',             'number'],
                    ['storage_limit_mb',  'Storage (MB)',         'number'],
                    ['contacts_max',      'Contacts',             'number'],
                    ['max_files',         'Files',                'number'],
                    ['max_forms',         'Forms',                'number'],
                ],
                'Content blocks' => [
                    ['block_types_allowed', 'Link in Bio block types',  'blocks'],
                ],
                'Link types' => [
                    ['max_conversational',   'Conversational pages',  'number'],
                    ['max_slides',           'Slides pages',          'number'],
                    ['max_ai_chat',          'AI Chatbot pages',      'number'],
                    ['max_restaurant_menu',  'Restaurant Menu pages', 'number'],
                    ['max_service_booking',  'Service Booking pages', 'number'],
                    ['max_reviews',          'Reviews pages',         'number'],
                    ['max_resume',           'Resume / portfolio pages','number'],
                ],
                'Growth & analytics' => [
                    ['analytics',                'Analytics depth',         'analytics'],
                    ['pixels',                   'Marketing pixels',        'bool'],
                    ['utm_params',               'UTM parameters',          'bool'],
                    ['max_custom_domains',       'Custom domains',          'number'],
                    ['seo_settings',             'SEO & social previews',   'bool'],
                ],
                'Per-link controls' => [
                    ['link_password',            'Password protection',     'bool'],
                    ['link_expiry',              'Link expiry',             'bool'],
                    ['link_geo_targeting',       'Geo targeting',           'bool'],
                    ['link_device_targeting',    'Device targeting',        'bool'],
                    ['link_deep_link',           'Deep links',              'bool'],
                    ['link_smart_rules',         'Smart redirect rules',    'bool'],
                ],
                'Monetization & teams' => [
                    ['ecommerce',                'Sell from your bio',      'bool'],
                    ['custom_forms',             'Custom forms',            'bool'],
                    ['paid_forms',               'Paid forms',              'bool'],
                    ['form_analytics_advanced',  'Advanced form analytics', 'bool'],
                    ['teams',                    'Team workspaces',         'bool'],
                    ['leads',                    'Leads capture',           'bool'],
                    ['vaults',                   'Credential vault',        'bool'],
                    ['creator_profile_public',   'Public creator profile',  'bool'],
                ],
                'AI features' => [
                    ['max_minds',                'AI Minds',      'number'],
                    ['max_personas',             'AI Agents',               'number'],
                    ['max_companions',           'Chat Widgets',            'number'],
                    ['ai_widget',                'Site Assistant widget',   'bool'],
                    ['ai_voice_assistant',       'AI Voice Assistant',      'bool'],
                    ['ask_coach',                'AI Coach',                'bool'],
                    ['card_scan',                'Card & Brochure Scanner', 'bool'],
                    ['ai_resume_tools',          'AI Resume Tools',         'bool'],
                    ['audience_type_estimation', 'Audience Insights',       'ai_access'],
                ],
                'Developer API' => [
                    ['api_access',               'API access',              'bool'],
                    ['api_calls_monthly',        'API calls / month',       'number'],
                    ['api_rate_per_min',         'API rate (calls / min)',  'number'],
                ],
                'Included coins' => [
                    ['included_coins_monthly',   'Coins / monthly cycle',   'number'],
                    ['included_coins_yearly',    'Coins / yearly cycle',    'number'],
                ],
            ];
            // Fixed (locked) column widths so every plan column lines up
            // uniformly; the matrix scroll box handles overflow when the
            // combined width exceeds the container.
            $colTpl = '220px repeat(' . count($plans) . ', 160px)';
            $blockLabel = fn (string $slug): string => \App\Modules\Common\Support\PlanBlockLabels::label($slug);
            $renderCell = function ($plan, $key, $kind) use ($blockLabel) {
                $features = $plan->features ?? [];
                if ($kind === 'ai_access') {
                    // AI features gated by AiPlanAccess::featureAllowed()
                    // rather than a seeded plan-form key (e.g. Audience
                    // Insights / audience_type_estimation). Mirror its
                    // resolution: an explicit per-plan key wins; otherwise
                    // the feature is unlocked on any paid (non-free) plan.
                    $on = array_key_exists($key, $features)
                        ? !empty($features[$key])
                        : ($plan->slug ?? null) !== 'free';
                    return $on
                        ? '<span class="feat-mark feat-mark-yes" aria-label="Included"><i class="fas fa-check text-[11px]"></i></span>'
                        : '<span class="feat-mark feat-mark-no" aria-label="Not included"><i class="fas fa-minus text-[10px]"></i></span>';
                }
                if (!array_key_exists($key, $features) && $kind !== 'analytics') {
                    return '<span class="feat-mark feat-mark-no" aria-label="Not included"><i class="fas fa-minus text-[10px]"></i></span>';
                }
                $val = $features[$key] ?? null;
                if ($kind === 'blocks') {
                    if ($val === '*') {
                        return '<span class="font-semibold">All blocks</span>';
                    }
                    if (is_array($val) && count($val) > 0) {
                        $labels = [];
                        foreach ($val as $slug) {
                            if (!is_string($slug) || $slug === '') continue;
                            $labels[$blockLabel($slug)] = true;
                        }
                        $labels = array_keys($labels);
                        sort($labels, SORT_NATURAL | SORT_FLAG_CASE);
                        $count = count($labels);
                        if ($count === 0) {
                            return '<span class="feat-mark feat-mark-no" aria-label="Not included"><i class="fas fa-minus text-[10px]"></i></span>';
                        }
                        return '<span x-data="{ open: false }" class="inline-flex flex-col items-center gap-1">'
                             . '<button type="button" @click="open = !open" class="inline-flex items-center gap-1 text-white font-semibold transition" '
                             . ':aria-expanded="open" aria-label="' . e($count . ' blocks') . '">'
                             . $count . ' blocks <i class="fas fa-chevron-down text-[8px]" :class="open ? \'rotate-180\' : \'\'"></i>'
                             . '</button>'
                             . '<span x-show="open" x-cloak class="block text-[11px] text-gray-400 leading-snug">' . e(implode(', ', $labels)) . '</span>'
                             . '</span>';
                    }
                    return '<span class="feat-mark feat-mark-no" aria-label="Not included"><i class="fas fa-minus text-[10px]"></i></span>';
                }
                if ($kind === 'number') {
                    if ((int) $val === -1) return '<span class="font-semibold">Unlimited</span>';
                    if ((int) $val === 0)  return '<span class="feat-mark feat-mark-no" aria-label="Not included"><i class="fas fa-minus text-[10px]"></i></span>';
                    return '<span class="text-white font-semibold">' . number_format((int) $val) . '</span>';
                }
                if ($kind === 'analytics') {
                    $label = is_string($val) ? ucfirst($val) : 'Basic';
                    $cls = strtolower((string) $val) === 'advanced' ? 'font-semibold' : 'text-gray-300';
                    return '<span class="' . $cls . '">' . e($label) . '</span>';
                }
                return $val
                    ? '<span class="feat-mark feat-mark-yes" aria-label="Included"><i class="fas fa-check text-[11px]"></i></span>'
                    : '<span class="feat-mark feat-mark-no" aria-label="Not included"><i class="fas fa-minus text-[10px]"></i></span>';
            };
        @endphp
        <div class="mt-16" data-anim="fade-up">
            <div class="text-center max-w-2xl mx-auto mb-6">
                <div class="sy-eyebrow">Side by side</div>
                <h2 class="text-3xl sm:text-4xl font-bold tracking-tight">Compare features at a glance</h2>
                <p class="text-gray-400 mt-2">Every plan, every important feature, laid out so you can spot the deltas in seconds.</p>
            </div>
            <div class="glass-panel rounded-3xl overflow-hidden">
                <div class="feat-matrix-scroll">
                    <div class="feat-matrix grid" style="grid-template-columns: {{ $colTpl }};">
                        {{-- Header row --}}
                        <div class="feat-cell feat-head feat-row-name">Feature</div>
                        @foreach($plans as $row)
                            @php $p = $row['model']; @endphp
                            <div class="feat-cell feat-head text-center {{ $p->is_popular ? 'feat-popular-col' : '' }}">
                                <span class="text-white text-sm font-semibold normal-case tracking-normal">
                                    @if($p->is_popular)<i class="fas fa-star sy-ico text-[10px]"></i>@endif
                                    {{ $p->name }}
                                </span>
                            </div>

                        @endforeach

                        {{-- Grouped rows --}}
                        @foreach($matrixGroups as $groupName => $rows)
                            <div class="feat-cell feat-group" style="grid-column: span {{ count($plans) + 1 }};">{{ $groupName }}</div>
                            @foreach($rows as $ri => [$fkey, $flabel, $fkind])
                                @php $stripe = $ri % 2 === 1 ? 'feat-stripe' : ''; @endphp
                                <div class="feat-cell feat-row-name {{ $stripe }}">{{ $flabel }}</div>
                                @foreach($plans as $prow)
                                    @php $p = $prow['model']; @endphp
                                    <div class="feat-cell text-center {{ $stripe }} {{ $p->is_popular ? 'feat-popular-col' : '' }}">
                                        {!! $renderCell($p, $fkey, $fkind) !!}
                                    </div>
                                @endforeach
                            @endforeach
                        @endforeach
                    </div>
                </div>
                <div class="md:hidden text-center text-[11px] text-gray-500 px-4 py-3 bg-white/[.02] border-t border-white/5">
                    <i class="fas fa-arrows-left-right"></i> Swipe to see all plans
                </div>
            </div>
        </div>

        {{-- ───────────────── COIN PACKAGES SECTION (always visible) ───────────────── --}}
        <div class="sy-divider-label"><span>Top up with coins</span></div>

        <div id="coins" data-anim="fade-up">
            <div class="text-center max-w-2xl mx-auto mb-8">
                {{-- Rule 1: the eyebrow was amber. The divider above already
                     says what this section is, in ink. --}}
                <h2 class="text-3xl sm:text-4xl font-bold tracking-tight">Out of API calls? Just grab some coins.</h2>
                <p class="text-gray-400 mt-2">
                    Coins are Sayzio's pay-as-you-go top-up currency, one flexible balance
                    you keep on hand and only spend when you need more than your plan includes.
                    No overage bills, no hard stops, no jumping to a bigger plan just for a busy week.
                </p>
                @php
                    $pbUser = auth()->user();
                    $pbOnPaid = $pbUser && ($pbUser->plan?->slug ?? 'free') !== 'free'
                        && (\App\Services\Billing\CoinPlanBonus::PLAN_BONUS_PCT[$pbUser->plan?->slug] ?? 0) > 0;
                    [$pbMin, $pbMax] = \App\Services\Billing\CoinPlanBonus::teaserRange();
                @endphp
                @unless($pbOnPaid)
                    {{-- Rule 1: was blue text on a blue tint. --}}
                    <div class="sy-flag" style="margin-top: 18px;">
                        <i class="fas fa-gift" aria-hidden="true"></i>
                        Paid plans earn {{ $pbMin }}&ndash;{{ $pbMax }}% extra bonus coins
                    </div>
                @endunless
            </div>

            {{-- ── "What are coins?" explainer — plain-language breakdown of the
                 three things coins are spent on, with everyday examples so a
                 first-time buyer can immediately picture the value. The AI
                 features card hands off to the detailed list in the partial
                 below (kept as the single source of truth), so we don't repeat
                 those feature names here. ── --}}
            {{-- Rules 1, 2, 5 and 7 together: system cards on the system
                 grid, one ink for every word, and the three coloured icon
                 tiles reduced to one quiet glyph each. --}}
            <div class="max-w-4xl mx-auto mb-10 sy-grid" style="--sy-min: 220px;">
                @foreach([
                    ['fa-gauge-high', 'API overage', 'Blow past your monthly API-call allowance and coins quietly cover the extra calls, nothing breaks.', 'A viral week that doubles your traffic.'],
                    ['fa-puzzle-piece', 'Paid add-ons', 'Activate one-off extras on demand, without committing to a higher plan.', 'A single ad campaign, or a batch of NFC tags.'],
                    ['fa-wand-magic-sparkles', 'AI features', 'Power OpenAI tools billed straight from your balance; pay only for what you use.', 'Generating a persona, or scanning business cards.'],
                ] as $use)
                    <div class="sy-card" style="padding: 24px 22px 22px;">
                        <div class="sy-head" style="min-height:0; margin-bottom:12px;">
                            <span class="sy-eyebrow" style="display:inline-flex; align-items:center; gap:9px;">
                                <i class="fas {{ $use[0] }}" aria-hidden="true" style="font-size:12px;"></i> {{ $use[1] }}
                            </span>
                        </div>
                        <p class="sy-blurb" style="margin-top:0;">{{ $use[2] }}</p>
                        <div class="sy-note" style="margin-top:auto; padding-top:14px;">e.g. {{ $use[3] }}</div>
                    </div>
                @endforeach
            </div>

            @include('public.pricing._ai_coin_uses')

            @if(!$wallet_enabled)
                <div class="sy-card max-w-2xl mx-auto text-center">
                    <i class="fas fa-coins text-2xl float-coin mb-2 block"></i>
                    Coin top-ups aren't enabled on this site yet. Check back soon.
                </div>
            @elseif($packages->isEmpty())
                <div class="max-w-2xl mx-auto glass-panel rounded-2xl p-6 text-gray-400 text-center">
                    No coin packages are available right now.
                </div>
            @else
                @php
                    /**
                     * "Best for…" caption catalogue. Picks the right tagline for
                     * each pack based on its size band so visitors immediately
                     * see what each pack is typically used for.
                     */
                    $bestForFor = function ($row) {
                        $total = (int) ($row['total_coins'] ?? 0);
                        if ($total <= 0) return 'Activating a single paid add-on without subscribing.';
                        if ($total < 1000) return 'One-off campaigns or activating a single add-on.';
                        if ($total < 5000) return 'A batch of NFC tag activations or a short ad burst.';
                        if ($total < 20000) return 'Coin top-ups for AI + a few add-on activations.';
                        return 'Power users who need months of AI generation, gifting coins, or running multiple add-ons in parallel.';
                    };
                    // Does ANY pack earn a plan bonus for this visitor? The pill
                    // slot is reserved across every card when one does, so the
                    // headline coin count keeps a single baseline down the row —
                    // the same trick the plan cards use for their intro badge.
                    // `auth()->user()` on this public page can hand back an
                    // Admin -- the admin guard shares the session -- and
                    // CoinPlanBonus is typed `?User`. So /pricing was a 500 for
                    // any signed-in admin who loaded it, which is also why
                    // PricingPageCacheFlushOnCoinPackageSaveTest has been red.
                    $coinUser = auth()->user() instanceof \App\Modules\User\Models\User
                        ? auth()->user()
                        : null;

                    $anyPlanBonus = $packages->contains(
                        fn ($r) => (\App\Services\Billing\CoinPlanBonus::breakdownFor($coinUser, $r['model'])['plan_bonus_pct'] ?? 0) > 0
                    );
                @endphp
                <div class="coin-rail sy-grid mt-10">
                    @foreach($packages as $row)
                        @php
                            $pkg = $row['model'];
                            $isFeat = $pkg->bonus_coins > 0;
                            $planBonus = \App\Services\Billing\CoinPlanBonus::breakdownFor($coinUser, $pkg);
                            // The coin count is this card's headline number, so it
                            // takes the plan cards' own size buckets. It doesn't
                            // change with currency, so unlike the plan price it
                            // needs no reactive sizing.
                            $coinTotal = number_format($row['total_coins']);
                            $coinLen = mb_strlen($coinTotal);
                            $coinSize = $coinLen >= 11 ? 'price-xs'
                                : ($coinLen >= 9 ? 'price-sm'
                                : ($coinLen >= 7 ? 'price-md' : ''));
                        @endphp
                        <div x-data='{ prices: @json($row['prices']) }'
                             class="sy-card {{ $isFeat ? 'sy-ribbon sy-ribbon--warm' : '' }}">

                            <div class="sy-head">
                                <div class="sy-eyebrow">{{ $pkg->name }}</div>
                                @if($isFeat)
                                    <span class="sy-flag sy-flag--coin">+{{ number_format($pkg->bonus_coins) }} bonus</span>
                                @endif
                            </div>

                            {{-- The coin count is the headline: it is what the
                                 pack IS, and what a buyer compares. The money
                                 is the first hairline row below, where the
                                 plans put their first value cell. --}}
                            <div class="sy-figure {{ strlen($coinTotal) >= 9 ? 'sy-figure--long' : '' }}">
                                <span>{{ $coinTotal }}</span>
                                <span class="unit">coins</span>
                            </div>

                            <div class="sy-note">
                                @if($pkg->bonus_coins > 0)
                                    {{ number_format($pkg->coin_amount) }} base
                                    + <span>{{ number_format($pkg->bonus_coins) }} bonus</span>
                                @else
                                    One flexible balance.
                                @endif
                            </div>

                            @php
                                // The card already says "205,000 coins" and
                                // "175,000 base + 30,000 bonus" above this line,
                                // and most of the admin copy opens by saying it
                                // a third time: "205,000 coins (incl. 30,000
                                // bonus) to keep a small team running on AI."
                                // Drop that opening clause and keep the half
                                // that says something new.
                                $coinBlurb = trim((string) ($pkg->description ?: ($pkg->best_for ?: $bestForFor($row))));
                                $coinBlurb = preg_replace(
                                    '/^[\d,]+\s*coins?\s*(\(incl\.[^)]*\))?\s*[:,-]?\s*/iu',
                                    '',
                                    $coinBlurb
                                );
                                $coinBlurb = $coinBlurb === '' ? ($pkg->best_for ?: $bestForFor($row)) : ucfirst($coinBlurb);
                            @endphp
                            <p class="sy-blurb plan-band-desc">{{ $coinBlurb }}</p>

                            <div class="sy-cells">
                                <div class="sy-cell">
                                    <i class="fas fa-tag" aria-hidden="true"></i>
                                    <span class="sy-cell-body"><b>One-time</b></span>
                                    <span class="sy-cell-note sy-cell-price">
                                        <span class="was" x-text="coinOriginal(prices)" x-show="coinOriginal(prices)">{{ $row['prices'][$currency]['original_formatted'] ?? '' }}</span>
                                        <span x-text="coinPrice(prices)">{{ $row['prices'][$currency]['formatted'] ?? '—' }}</span>
                                    </span>
                                </div>
                                <div class="sy-cell">
                                    <i class="fas fa-bullseye" aria-hidden="true"></i>
                                    <span class="sy-cell-body"><b>Best for</b><span class="sy-cell-sub">{{ $pkg->best_for ?: $bestForFor($row) }}</span></span>
                                </div>
                                @if($planBonus['plan_bonus_pct'] > 0)
                                    <div class="sy-cell">
                                        <i class="fas fa-gift" aria-hidden="true"></i>
                                        <span class="sy-cell-body">
                                            <b>+{{ $planBonus['plan_bonus_pct'] }}% {{ $planBonus['plan_name'] }} bonus</b>
                                            <span class="sy-cell-sub">{{ number_format($planBonus['total_with_plan_bonus']) }} coins in total on your plan</span>
                                        </span>
                                    </div>
                                @endif
                            </div>

                            <div class="sy-foot">
                                @auth
                                    <a href="{{ route('user.wallet.buy') }}" class="sy-cta">
                                        Buy {{ $pkg->name }} <i class="fas fa-arrow-right"></i>
                                    </a>
                                @else
                                    <a href="{{ route('user.register') }}" class="sy-cta">
                                        Sign up to buy <i class="fas fa-arrow-right"></i>
                                    </a>
                                @endauth
                                <div class="sy-foot-note">Coins never expire</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <hr class="sy-divider">

        {{-- ───────────────── FOOTER LINKS ───────────────── --}}
        <div class="mt-14 text-center" data-anim="fade-up">
            <p class="text-gray-400">Want the full feature breakdown?</p>
            <div class="mt-4 flex flex-wrap items-center justify-center gap-3">
                <a href="#coins" class="sy-cta sy-cta--ghost" style="width:auto; display:inline-flex;">
                    <i class="fas fa-coins"></i> Browse coin packages
                </a>
            </div>
        </div>

        {{-- Referral-program teaser --}}
        <div class="mt-10 max-w-3xl mx-auto" data-anim="fade-up">
            <div class="grad-border rounded-2xl p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center gap-4 sm:gap-6 bg-white/[0.02]">
                <div class="ref-icon w-12 h-12 rounded-xl flex items-center justify-center shrink-0">
                    <i class="fas fa-gift"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="sy-eyebrow" style="margin-bottom:4px;">Referral program</div>
                    <div class="text-white font-semibold leading-snug">Tell a friend, both get credit.</div>
                    <p class="text-sm text-gray-400 mt-1 leading-relaxed">
                        Share your personal <span class="font-mono font-semibold">/r/&lt;your-code&gt;</span> link. Every signup is tracked back to you, and your referrals land on the right plan with a thank-you discount applied automatically.
                    </p>
                </div>
                @auth
                    <a href="{{ \Illuminate\Support\Facades\Route::has('user.referrals.index') ? route('user.referrals.index') : route('user.dashboard') }}" class="sy-cta sy-cta--ghost" style="width:auto; display:inline-flex; white-space:nowrap; flex:none;">
                        Get my referral link <i class="fas fa-arrow-right text-[11px]"></i>
                    </a>
                @else
                    <a href="{{ route('register.page') }}" class="sy-cta sy-cta--ghost" style="width:auto; display:inline-flex; white-space:nowrap; flex:none;">
                        Sign up &amp; share <i class="fas fa-arrow-right text-[11px]"></i>
                    </a>
                @endauth
            </div>
        </div>
    </div>
</section>

{{-- Custom Plan Request form --}}
@include('public.partials._custom-plan-form')

{{-- ============================ FULL COMPETITOR BREAKDOWN ============================
     The pricing page hosts the complete competitor comparison: head-to-head
     rival selector PLUS the full multi-tool feature matrix (expanded by
     default in non-compact mode). The homepage shows only a teaser that links
     here via /pricing#compare. The "Compare features at a glance" matrix above
     covers plan tiers; this section covers Sayzio vs other tools. --}}
@include('public.partials._compare', ['compact' => false, 'anchorId' => 'compare'])

@include('public.partials.subscribe-block', [
    'heading' => 'Pricing changes, deals, and new plans.',
    'subtext' => 'Pick email, WhatsApp Channel, or DM to be first to know about coin packages and seasonal offers.',
    'source'  => 'pricing',
])
@endsection
