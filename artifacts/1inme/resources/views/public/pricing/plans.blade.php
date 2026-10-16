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
<script src="{{ asset('js/pricing-curator.js') }}?v={{ substr(hash_file('sha256', public_path('js/pricing-curator.js')), 0, 12) }}"></script>
<link rel="stylesheet" href="{{ asset('css/pricing-curator.css') }}?v={{ substr(hash_file('sha256', public_path('css/pricing-curator.css')), 0, 12) }}">
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

    html:not(.light-mode) .sy-seg button.is-on{color:#15172c!important}
    html.light-mode .sy-seg button.is-on{color:#fff!important}
    /* Compact decision cards; the matrix below carries the full specification. */
    .plan-band-heading{display:flex;align-items:center;gap:14px;margin:32px 0 8px;flex-wrap:wrap}
    .plan-band-heading h2{color:var(--sy-ink);font-size:14px;letter-spacing:.07em}
    .plan-band-heading p{font-size:13px;color:var(--sy-ink-3)}
    .plans-row>.compact-plan{flex:1 0 258px;min-width:258px;max-width:360px;padding:22px;border-radius:22px;background:linear-gradient(150deg,rgba(105,94,255,.065),transparent 45%),var(--sy-card);border:1px solid var(--sy-rule);box-shadow:0 10px 30px -24px rgba(41,31,112,.35);transition:border-color .2s,box-shadow .2s}
    .plans-row>.compact-plan:hover{transform:none;border-color:#9b90ed;box-shadow:0 16px 36px -26px rgba(85,61,190,.55)}
    .plans-row>.compact-plan.sy-card--lead{transform:none;border-color:#9b90ed;background:linear-gradient(145deg,rgba(122,92,245,.13),transparent 65%),var(--sy-card)}
    .compact-plan .sy-head{margin-bottom:0;min-height:24px;gap:8px;flex-wrap:wrap}
    .compact-plan .sy-eyebrow{font-size:12px;letter-spacing:.09em}
    .compact-plan .sy-flag{font-size:10px;padding:4px 8px}
    .compact-plan .sy-figure{font-size:34px;line-height:1.15;margin-top:10px;letter-spacing:-.045em}
    .compact-plan .sy-note{font-size:11px;line-height:1.5;min-height:50px;margin-top:8px}
    .compact-plan .sy-blurb{font-size:12px;line-height:1.6;margin:12px 0 0;min-height:39px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
    .compact-plan .sy-foot--lead{margin-top:16px;padding:0;border:0}
    .compact-plan .sy-cta{min-height:42px;padding:10px 12px;font-size:12px;border-radius:12px}
    .compact-plan.sy-card--lead .sy-cta{background:#6246db!important;color:#fff!important;border-color:#6246db!important}
    .plan-highlights{list-style:none;margin:18px 0 0;padding:16px 0 0;border-top:1px solid var(--sy-rule);display:grid;gap:11px}
    .plan-highlights li{display:flex;align-items:center;gap:10px;font-size:12px;line-height:1.4;color:var(--sy-ink-2)}
    .plan-highlights i{width:15px;text-align:center;color:#8b79e8;font-size:12px}
    .plan-compare-link{display:flex;justify-content:space-between;align-items:center;margin-top:18px;padding-top:14px;border-top:1px solid var(--sy-rule);font-size:11px;font-weight:650;color:var(--sy-ink-2)}
    .plan-compare-link:hover{color:#8b79e8}
    #compare-plans{scroll-margin-top:100px}
    @media(max-width:640px){.plans-row>.compact-plan{flex-basis:276px;min-width:276px;padding:20px}.plan-band-heading{gap:6px}.plan-band-heading p{width:100%}.compact-plan .sy-figure{font-size:32px}}
    @media(prefers-reduced-motion:reduce){.plans-row>.compact-plan{transition:none}}


    .sy.pricing-page{padding-top:104px!important;padding-bottom:40px}
    .pricing-page>.absolute{display:none}
    .pricing-page .plan-band-heading{margin:20px 0 0}
    .pricing-page .plans-rail{margin-top:14px}
    .pricing-page .sy-divider-label{margin:28px 0 0}
    .pricing-page .pricing-comparison{margin-top:22px}
    .pricing-page .feat-matrix{width:100%;min-width:0}
    .pricing-page .feat-cell{padding:11px 14px;font-size:12px}
    .pricing-page .pricing-account-panel{margin-top:24px;padding:18px 22px;background:var(--sy-card);border:1px solid var(--sy-rule);box-shadow:none;color:var(--sy-ink)}
    .pricing-page .pricing-account-panel :is(.text-white,.text-gray-200,.text-gray-300,.text-gray-400,.text-gray-500,.text-emerald-400,.text-emerald-100,.text-emerald-200){color:var(--sy-ink-2)!important}
    .pricing-page .pricing-account-panel .border-white\/10{border-color:var(--sy-rule)!important}
    .pricing-page .pricing-account-panel .bg-white\/10{background:var(--sy-ground)!important}
    .pricing-page .smart-pill{color:var(--sy-ink);background:var(--sy-ground);border-color:var(--sy-rule)}
    .pricing-page .pricing-account-panel .sy-eyebrow{color:var(--sy-ink-3)}
    .compact-plan.is-match{outline:2px solid #8b79e8;outline-offset:2px}
    @media(min-width:1150px){.pricing-page [data-pricing-default] .plans-row>.compact-plan{flex:1 0 0;min-width:0;max-width:none}}
    @media(max-width:640px){.sy.pricing-page{padding-top:96px!important}.pricing-page .pricing-account-panel{padding:16px}}

</style>
@endpush

@section('content')
<section
    @inme-currency.window="currency = $event.detail.c"
    x-data="{
        ...window.sayzioPricingCurator(@js($curationCatalogue), {{ $showAllGroups ? 'true' : 'false' }}),
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
        restoreCuration();
        $watch('cycle', (val) => { priceKey++; rememberCycle(val); if (matches.length) matches = matchCatalogue(); });
        $watch('currency', () => { if (matches.length) matches = matchCatalogue(); });
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
    class="sy pricing-page sec-first relative">
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
                <div data-anim="fade-up" class="pricing-account-panel mt-10 rounded-2xl border border-white/10 p-5 sm:p-6">
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

        @include('public.pricing.partials.curator')
        <div x-show="!expanded" data-pricing-default>
            @include('public.pricing.partials.plan-selection', ['pricingScope' => 'simple'])
        </div>
        <div x-show="expanded" x-cloak data-pricing-expanded>
            <div class="curator-expanded-head"><div><h2>All plans, one clear comparison</h2><p>Explore every option. Your selection stays in this tab session.</p></div><button type="button" @click="resetCuration()">Back to four simple plans</button></div>
            @include('public.pricing.partials.plan-selection', ['pricingScope' => 'full', 'plans' => $catalogueRows, 'planBands' => $allPlanBands, 'showAllGroups' => true])
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
