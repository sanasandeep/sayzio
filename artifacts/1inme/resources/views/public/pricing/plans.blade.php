@extends('public.layouts.site')

@section('title', 'Pricing')

@push('head')
@php
    $__pricingProducts = \App\Modules\Common\Support\MarketingSchema::pricingProducts(
        $planModels ?? collect(),
        $currency ?? 'USD'
    );
@endphp
@if(!empty($__pricingProducts))
<script type="application/ld+json">{!! json_encode(\App\Modules\Common\Support\MarketingSchema::graph($__pricingProducts), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endif
<style>
    .grad-bar { background: linear-gradient(135deg,#7c3aed 0%,#9333ea 50%,#c026d3 100%); }
    .grad-text {
        background: linear-gradient(120deg,#c4b5fd 0%,#a855f7 38%,#7c3aed 66%,#c026d3 100%);
        -webkit-background-clip: text; background-clip: text;
        -webkit-text-fill-color: transparent; color: transparent;
    }
    .hero-title { text-shadow: 0 0 60px rgba(124,58,237,.25); }
    html.light-mode .hero-title { text-shadow: 0 0 50px rgba(124,58,237,.18); }
    .grad-glow { position: relative; }
    .grad-glow::before {
        content: ""; position: absolute; inset: -1px; border-radius: 1.1rem; padding: 1px;
        background: linear-gradient(135deg,#a855f7 0%,#7c3aed 50%,#c026d3 100%);
        -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
        -webkit-mask-composite: xor; mask-composite: exclude;
        opacity: 0; transition: opacity .35s ease;
        pointer-events: none;
    }
    .grad-glow:hover::before, .grad-glow.is-popular::before { opacity: 1; }
    .grad-glow:hover { transform: translateY(-4px); }
    /* The accent plan card already sits lifted (-6px); keep it leading on hover. */
    .grad-glow.is-accent:hover { transform: translateY(-10px); }
    .grad-glow { transition: transform .35s cubic-bezier(.2,.7,.2,1); }
    .grad-glow.is-current::before {
        opacity: 1;
        background: #34d399;
    }
    .price-num { font-variant-numeric: tabular-nums; }
    /* Headline plan price — fluid base size with content-aware step-downs
       (driven by priceSize()) so long INR annual amounts like ₹40,670.00
       never overflow the card. Scoped to .plan-price so the coin-package
       prices (which use their own Tailwind sizes) are left alone. */
    .plan-price .price-num {
        font-size: clamp(2rem, 1.4rem + 2.6vw, 3rem);
        line-height: 1.04; word-break: keep-all;
    }
    .plan-price .price-num.price-md { font-size: clamp(1.75rem, 1.25rem + 2.1vw, 2.5rem); }
    .plan-price .price-num.price-sm { font-size: clamp(1.5rem, 1.1rem + 1.7vw, 2.1rem); }
    .plan-price .price-num.price-xs { font-size: clamp(1.25rem, .95rem + 1.3vw, 1.75rem); }
    .price-pop { animation: pricePop .35s cubic-bezier(.2,.7,.2,1); }
    @keyframes pricePop {
        0%   { transform: translateY(6px); opacity: 0; }
        100% { transform: translateY(0); opacity: 1; }
    }
    .coin-bg {
        background: rgba(124,58,237,.06);
    }
    .pulse-dot { animation: pulse 1.6s ease-in-out infinite; }
    @keyframes pulse { 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(1.4);opacity:.5} }
    .float-coin { animation: floaty 3s ease-in-out infinite; }
    @keyframes floaty { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-6px)} }
    .seg { transition: background-color .25s ease, color .25s ease, box-shadow .25s ease; }
    .seg-active { background-color: #7c3aed; color: #fff; box-shadow: 0 8px 24px -10px rgba(124,58,237,.55); }

    /* Most-popular ribbon — gentle wiggle on hover */
    .pop-ribbon { animation: ribbonGlow 3s ease-in-out infinite; }
    @keyframes ribbonGlow {
        0%, 100% { box-shadow: 0 8px 24px -10px rgba(124,58,237,.55); }
        50%      { box-shadow: 0 12px 32px -8px rgba(167,139,250,.65); }
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
            radial-gradient(120% 140% at 0% 0%, rgba(124,58,237,.16), transparent 55%),
            radial-gradient(120% 140% at 100% 0%, rgba(236,72,153,.10), transparent 55%),
            rgba(255,255,255,.02);
        box-shadow: 0 24px 60px -36px rgba(124,58,237,.6);
    }
    /* Banner uses light-on-dark text in both modes, so keep a dark surface in light mode for contrast. */
    html.light-mode .smart-banner {
        background:
            radial-gradient(120% 140% at 0% 0%, rgba(124,58,237,.30), transparent 55%),
            radial-gradient(120% 140% at 100% 0%, rgba(236,72,153,.20), transparent 55%),
            #1e1b2e;
        border-color: rgba(255,255,255,.12);
        box-shadow: 0 24px 60px -36px rgba(124,58,237,.5);
    }
    .smart-pill {
        background: rgba(124,58,237,.22);
        border: 1px solid rgba(255,255,255,.18);
    }
    .smart-meter {
        height: 6px; background: rgba(255,255,255,.06); border-radius: 9999px; overflow: hidden;
    }
    .smart-meter > span {
        display: block; height: 100%; border-radius: 9999px;
        background: #7c3aed;
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
        scrollbar-width: thin; scrollbar-color: rgba(124,58,237,.55) transparent;
    }
    .feat-matrix-scroll::-webkit-scrollbar { width: 9px; height: 9px; }
    .feat-matrix-scroll::-webkit-scrollbar-track { background: rgba(255,255,255,.03); }
    .feat-matrix-scroll::-webkit-scrollbar-thumb { background: rgba(124,58,237,.5); border-radius: 9999px; }
    .feat-matrix-scroll::-webkit-scrollbar-thumb:hover { background: rgba(124,58,237,.75); }
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
        box-shadow: inset 0 -1px 0 rgba(167,139,250,.22);
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
        background: linear-gradient(90deg, rgba(124,58,237,.18), rgba(124,58,237,.08));
        color: #c4b5fd;
        text-transform: uppercase; letter-spacing: .08em;
        font-size: .66rem; font-weight: 700;
        padding: .6rem 1rem;
        position: sticky; left: 0; z-index: 1;
        box-shadow: inset 2px 0 0 rgba(167,139,250,.55);
    }
    .feat-cell.feat-popular-col {
        background: rgba(124,58,237,.07);
        box-shadow: inset 1px 0 0 rgba(167,139,250,.12), inset -1px 0 0 rgba(167,139,250,.12);
    }
    .feat-cell.feat-popular-col.feat-stripe { background: rgba(124,58,237,.10); }
    .feat-cell.feat-head.feat-popular-col {
        background: rgba(124,58,237,.20);
        box-shadow: inset 0 -1px 0 rgba(167,139,250,.45), inset 1px 0 0 rgba(167,139,250,.3), inset -1px 0 0 rgba(167,139,250,.3);
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
        background: rgba(124,58,237,.06);
    }
    html.light-mode .feat-cell.feat-head.feat-popular-col { background: #e6e0fb; }
    html.light-mode .feat-mark-no { color: #94a3b8; }
    html.light-mode .feat-cell.feat-stripe { background: rgba(15,23,42,.028); }
    html.light-mode .feat-cell.feat-row-name.feat-stripe { background: #f7f7fb; }
    html.light-mode .feat-cell.feat-popular-col.feat-stripe { background: rgba(124,58,237,.09); }
    html.light-mode .feat-cell.feat-group {
        background: linear-gradient(90deg, #e7e2fb, #f1eefc); color: #6d28d9;
    }
    html.light-mode .feat-mark-yes { box-shadow: 0 0 0 1px rgba(16,185,129,.3), 0 4px 12px -6px rgba(16,185,129,.4); }

    /* Linktree-style plan card: a tinted header band sits flush at the
       top, with the body (price, CTA, grouped feature list) below. The
       card uses overflow-hidden so the band's rounded top is clean, which
       is why badges live inline in the band rather than as floating
       ribbons. */
    .plan-card {
        overflow: hidden;
        background:
            radial-gradient(140% 80% at 50% -10%, rgba(255,255,255,.05), transparent 60%),
            rgba(255,255,255,.02);
        box-shadow: 0 18px 40px -30px rgba(0,0,0,.8);
        transition: transform .35s cubic-bezier(.2,.7,.2,1), box-shadow .35s ease;
    }
    .plan-card:hover { box-shadow: 0 34px 80px -34px rgba(124,58,237,.6); }
    /* Accent (recommended / most-popular) tier — lifted, glowier, and a
       half-step larger so the hero card clearly leads the rail. */
    .plan-card.is-accent {
        box-shadow: 0 40px 90px -34px rgba(124,58,237,.6), 0 0 0 1px rgba(167,139,250,.18) inset;
        transform: translateY(-6px);
    }
    .plan-card.is-accent:hover { box-shadow: 0 50px 110px -34px rgba(124,58,237,.7), 0 0 0 1px rgba(167,139,250,.28) inset; }
    .plan-card.is-current {
        box-shadow: 0 34px 80px -34px rgba(16,185,129,.5), 0 0 0 1px rgba(52,211,153,.18) inset;
    }

    /* Header band: a subtle tinted strip; accent/current get a richer
       diagonal wash so the highlighted tiers read as premium. A thin
       gradient top bar crowns the highlighted tiers. */
    .plan-band {
        position: relative;
        background: linear-gradient(180deg, rgba(255,255,255,.045), rgba(255,255,255,.015));
        border-bottom: 1px solid rgba(255,255,255,.06);
    }
    .plan-band.is-accent  { background: linear-gradient(135deg, rgba(124,58,237,.30), rgba(168,85,247,.10)); border-bottom-color: rgba(167,139,250,.34); }
    .plan-band.is-current { background: linear-gradient(135deg, rgba(16,185,129,.24), rgba(52,211,153,.08)); border-bottom-color: rgba(52,211,153,.32); }
    .plan-band.is-accent::before,
    .plan-band.is-current::before {
        content: ""; position: absolute; left: 0; right: 0; top: 0; height: 3px;
    }
    .plan-band.is-accent::before  { background: linear-gradient(90deg,#7c3aed,#a855f7,#ec4899); }
    .plan-band.is-current::before { background: linear-gradient(90deg,#10b981,#34d399); }
    .plan-card.is-accent  { background: radial-gradient(140% 80% at 50% -10%, rgba(124,58,237,.14), transparent 60%), rgba(124,58,237,.06); }
    .plan-card.is-current { background: radial-gradient(140% 80% at 50% -10%, rgba(16,185,129,.12), transparent 60%), rgba(16,185,129,.05); }

    /* Price block — a quiet inset panel so the headline number anchors
       the card body. A faint top sheen adds depth without distraction. */
    .plan-price {
        position: relative; overflow: hidden;
        background: linear-gradient(180deg, rgba(255,255,255,.05), rgba(255,255,255,.02));
        border: 1px solid rgba(255,255,255,.07);
        border-radius: .9rem;
        box-shadow: inset 0 1px 0 rgba(255,255,255,.05);
    }
    .plan-card.is-accent .plan-price { background: linear-gradient(180deg, rgba(124,58,237,.16), rgba(124,58,237,.06)); border-color: rgba(167,139,250,.28); }
    .plan-card.is-current .plan-price { background: linear-gradient(180deg, rgba(16,185,129,.14), rgba(16,185,129,.05)); border-color: rgba(52,211,153,.24); }
    .price-suffix { opacity: .85; }

    /* Light-mode counterparts (dark is the default; the global
       marketing-anim.css doesn't know these custom classes). */
    html.light-mode .plan-card { background: #ffffff; box-shadow: 0 18px 40px -30px rgba(15,23,42,.35); }
    html.light-mode .plan-card:hover { box-shadow: 0 30px 70px -34px rgba(124,58,237,.4); }
    html.light-mode .plan-band { background: linear-gradient(180deg, rgba(15,23,42,.05), rgba(15,23,42,.01)); border-bottom-color: rgba(15,23,42,.08); }
    html.light-mode .plan-band.is-accent  { background: linear-gradient(135deg, rgba(124,58,237,.16), rgba(168,85,247,.05)); border-bottom-color: rgba(124,58,237,.25); }
    html.light-mode .plan-band.is-current { background: linear-gradient(135deg, rgba(16,185,129,.16), rgba(52,211,153,.05)); border-bottom-color: rgba(16,185,129,.25); }
    html.light-mode .plan-card.is-accent  { background: #faf8ff; }
    html.light-mode .plan-card.is-current { background: #f3fcf8; }
    html.light-mode .plan-price { background: rgba(15,23,42,.03); border-color: rgba(15,23,42,.08); }
    html.light-mode .plan-card.is-accent .plan-price { background: rgba(124,58,237,.07); border-color: rgba(124,58,237,.2); }
    html.light-mode .plan-card.is-current .plan-price { background: rgba(16,185,129,.07); border-color: rgba(16,185,129,.2); }

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
    .plans-rail::before { left: 0; background: linear-gradient(90deg, var(--page-bg, #0b0a14), transparent); }
    .plans-rail::after  { right: 0; background: linear-gradient(270deg, var(--page-bg, #0b0a14), transparent); }
    .plans-rail.can-scroll-left::before  { opacity: 1; }
    .plans-rail.can-scroll-right::after { opacity: 1; }
    html.light-mode .plans-rail::before { background: linear-gradient(90deg, #f7f7fb, transparent); }
    html.light-mode .plans-rail::after  { background: linear-gradient(270deg, #f7f7fb, transparent); }
    .plans-scroll {
        overflow-x: auto; overflow-y: hidden; -webkit-overflow-scrolling: touch;
        scroll-snap-type: x proximity; padding: .25rem .25rem 1rem;
        scrollbar-width: thin; scrollbar-color: rgba(124,58,237,.55) transparent;
    }
    .plans-scroll::-webkit-scrollbar { height: 9px; }
    .plans-scroll::-webkit-scrollbar-track { background: rgba(255,255,255,.04); border-radius: 9999px; }
    .plans-scroll::-webkit-scrollbar-thumb { background: rgba(124,58,237,.5); border-radius: 9999px; }
    .plans-scroll::-webkit-scrollbar-thumb:hover { background: rgba(124,58,237,.75); }
    html.light-mode .plans-scroll::-webkit-scrollbar-track { background: rgba(15,23,42,.06); }
    .plans-row { display: flex; gap: 1.25rem; align-items: stretch; }
    .plans-row > .plan-card { flex: 1 0 290px; min-width: 290px; max-width: 360px; scroll-snap-align: start; }

    /* Keep the violet gradient CTA legible in light mode — the global
       override would otherwise darken `.text-white` sitting on it. */
    html.light-mode .grad-bar.text-white,
    html.light-mode .grad-bar .text-white { color: #fff !important; }

    /* ── Arbitrary white-opacity utilities the global remap misses ──
       marketing-anim.css only remaps bg-white/{.03,.05,.07}; this page also
       leans on /[0.02] and /[0.04] soft fills plus /[0.08] /[0.10] hovers,
       so give them light-mode counterparts (otherwise the surface stays
       transparent and visually disappears on the white background). */
    html.light-mode .bg-white\/\[0\.02\],
    html.light-mode .bg-white\/\[0\.04\] { background-color: rgba(15,23,42,.035) !important; }
    html.light-mode .hover\:bg-white\/\[0\.08\]:hover,
    html.light-mode .hover\:bg-white\/\[0\.10\]:hover { background-color: rgba(15,23,42,.07) !important; }

    /* Coin-package tint reads cleaner over the lighter surface. */
    html.light-mode .coin-bg { background: rgba(124,58,237,.045); }

    /* ── Smart upgrade banner keeps its dark surface in BOTH modes, so its
       text and accents must stay light too — the global light-mode rules
       would otherwise darken every child into dark-on-dark. ── */
    html.light-mode .smart-banner .text-white { color: #f8fafc !important; }
    html.light-mode .smart-banner .text-gray-200,
    html.light-mode .smart-banner .text-gray-300 { color: #e3e1f0 !important; }
    html.light-mode .smart-banner .text-gray-400 { color: #bdbace !important; }
    html.light-mode .smart-banner .text-gray-500 { color: #918da8 !important; }
    html.light-mode .smart-banner [class*="text-white/"] { color: rgba(248,250,252,.7) !important; }
    html.light-mode .smart-banner .text-violet-200,
    html.light-mode .smart-banner .text-violet-300 { color: #c4b5fd !important; }
    html.light-mode .smart-banner .text-pink-300    { color: #f9a8d4 !important; }
    html.light-mode .smart-banner .text-amber-300   { color: #fcd34d !important; }
    html.light-mode .smart-banner .text-emerald-300 { color: #6ee7b7 !important; }
    html.light-mode .smart-banner .bg-white\/10     { background-color: rgba(255,255,255,.12) !important; }
    html.light-mode .smart-banner .border-white\/10 { border-color: rgba(255,255,255,.14) !important; }

    /* ── Referral teaser icon — gradient chip, white glyph in both modes ── */
    .ref-icon { background: linear-gradient(135deg,#7c3aed,#c026d3); color: #fff; }
    html.light-mode .ref-icon { color: #fff !important; }

    @media (prefers-reduced-motion: reduce) {
        .pulse-dot, .float-coin, .pop-ribbon, .grad-glow { animation: none !important; }
        .grad-glow:hover { transform: none !important; }
        .price-pop { animation: none !important; }
        .seg { transition: none !important; }
        .smart-meter > span { transition: none !important; }
        .plan-card { transition: none !important; }
        .plans-rail::before, .plans-rail::after { transition: none !important; }
        .plans-scroll { scroll-snap-type: none !important; }
    }
</style>
@endpush

@section('content')
<section
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
            // First-term introductory discount for the active currency +
            // cycle (null when none applies). Drives the struck-through
            // normal price, the discounted headline and the savings badge.
            const block = this.cur(plan);
            const r = this.cycle === 'annual' ? block.annual : block.monthly;
            return (r && r.intro) ? r.intro : null;
        },
        priceSize(plan){
            // The headline price uses a fluid (clamped) base size, but very
            // long currency strings — notably INR annual amounts like
            // ₹40,670.00 — still need to step down a notch (or two) so they
            // never run past the card edge. We measure the visible glyph
            // count (digits + symbol + separators, spaces ignored) and pick
            // a size bucket. Reactive on currency + cycle, so it re-fits the
            // instant the visitor flips either toggle.
            const s = (this.money(plan, this.cycle) || '').replace(/\s/g, '');
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
        billingNote(plan){
            // Linktree-style fineprint under the big price. When viewing
            // annual we surface the equivalent monthly rack rate; when
            // viewing monthly we tease the annual per-month equivalent.
            const monthly = this.money(plan, 'monthly');
            if (this.cycle === 'annual' && this.hasAnnual(plan)) {
                return monthly && monthly !== '—'
                    ? 'Billed annually, or ' + monthly + ' monthly'
                    : 'Billed annually';
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
        switchCurrency(c){
            if (this.currency === c) return;
            this.currency = c;
            this.priceKey++;
            // Persist the choice (session + cookie + user preference) in the
            // background — the UI has already re-rendered, so we never block on it.
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
            // Persist the chosen cycle server-side so a refresh, menu
            // navigation, or return visit lands on the same toggle.
            // Best-effort — we never block the UI on the response.
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
        // Coin packages no longer live behind a tab toggle; instead
        // we fire the marketing event when the coins section either
        // is the deep-linked target on load or scrolls into view.
        const fireOnceCoins = (() => { let fired = false; return () => { if (!fired) { fired = true; trackCoinsView(); } }; })();
        if (window.location.hash === '#coins') { fireOnceCoins(); }
        const coinsEl = document.getElementById('coins');
        if (coinsEl && 'IntersectionObserver' in window) {
            const io = new IntersectionObserver((entries) => {
                entries.forEach(e => { if (e.isIntersecting) { fireOnceCoins(); io.disconnect(); } });
            }, { rootMargin: '0px 0px -25% 0px' });
            io.observe(coinsEl);
        }
    "
    class="relative pt-20 pb-12 lg:pt-28 lg:pb-16">
    <div class="absolute inset-0 -z-10 overflow-hidden">
        <div class="absolute -top-32 left-1/2 -translate-x-1/2 w-[60rem] h-[60rem] rounded-full opacity-30 blur-[120px]"
             style="background: #7c3aed;"></div>
        <div class="absolute top-40 -right-32 w-[36rem] h-[36rem] rounded-full opacity-20 blur-[100px]"
             style="background: #8b5cf6;"></div>
        <div class="absolute top-72 -left-32 w-[36rem] h-[36rem] rounded-full opacity-20 blur-[100px]"
             style="background: #6d28d9;"></div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto space-y-5" data-anim="fade-up">
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-gradient-to-r from-violet-500/15 via-fuchsia-500/10 to-violet-500/15 border border-violet-400/25 backdrop-blur-sm text-xs font-bold uppercase tracking-[.2em] text-violet-100 shadow-lg shadow-violet-900/25">
                <span class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full rounded-full bg-violet-400 opacity-75 animate-ping motion-reduce:hidden"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-violet-300"></span>
                </span>
                Pricing &amp; coins
            </div>
            <h1 class="hero-title text-4xl sm:text-5xl lg:text-[3.65rem] font-bold tracking-tight leading-[1.05]">
                Pricing that <span class="grad-text">scales with you.</span>
            </h1>
            <p class="text-lg text-gray-300/90 max-w-xl mx-auto">
                Start free, upgrade only when you outgrow it. Powerful features, transparent pricing — cancel anytime.
            </p>
            <div class="flex flex-wrap items-center justify-center gap-x-2.5 gap-y-2 pt-1 text-xs">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/[0.04] border border-white/10 text-gray-300"><i class="fas fa-circle-check text-emerald-400"></i> No card to start</span>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/[0.04] border border-white/10 text-gray-300"><i class="fas fa-circle-check text-emerald-400"></i> Cancel anytime</span>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/[0.04] border border-white/10 text-gray-300"><i class="fas fa-circle-check text-emerald-400"></i> Secure checkout</span>
            </div>
            {{-- Currency switch — flips USD/INR instantly client-side (prices for
                 both currencies are embedded in each card's Alpine payload), with a
                 background ping to persist the choice in session + cookie + profile. --}}
            @php
                $curIsCountry = $currencySource === \App\Services\PricingResolver::SOURCE_USER_COUNTRY;
                $curIsAuto    = $currencySource === \App\Services\PricingResolver::SOURCE_GEO;
            @endphp
            <div class="inline-flex flex-wrap items-center justify-center gap-2.5 mt-4" role="group" aria-label="Display currency">
                @if($curIsCountry)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border border-emerald-400/20 bg-emerald-400/[0.06] text-xs">
                        <i class="fas fa-globe text-emerald-300" aria-hidden="true"></i>
                        <span class="text-gray-400">Your country:</span>
                        <span class="font-semibold text-white">{{ $currency === 'INR' ? '₹ INR' : '$ USD' }}</span>
                    </span>
                    <span class="text-[11px] text-gray-500">
                        Set from your billing country (<span class="uppercase">{{ $user->country }}</span>) —
                        <a href="{{ route('user.profile.edit') }}" class="text-violet-400 hover:underline">change</a>
                    </span>
                @else
                    <span class="text-[11px] uppercase tracking-wider font-semibold text-gray-500">Currency</span>
                    <div class="inline-flex rounded-full border border-white/10 bg-white/[0.03] p-1" role="tablist" aria-label="Choose display currency">
                        <button type="button" role="tab" @click="switchCurrency('USD')"
                                :aria-selected="currency === 'USD'"
                                :class="currency === 'USD' ? 'seg-active' : 'text-gray-300 hover:text-white'"
                                class="seg px-3.5 py-1.5 text-xs font-bold rounded-full">$ USD</button>
                        <button type="button" role="tab" @click="switchCurrency('INR')"
                                :aria-selected="currency === 'INR'"
                                :class="currency === 'INR' ? 'seg-active' : 'text-gray-300 hover:text-white'"
                                class="seg px-3.5 py-1.5 text-xs font-bold rounded-full">₹ INR</button>
                    </div>
                    @if($curIsAuto)
                        <span class="text-[11px] text-gray-500">auto-detected — switch anytime</span>
                    @endif
                @endif
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
                            <div class="text-[11px] font-bold uppercase tracking-[.18em] text-violet-300 mb-1">
                                <i class="fas fa-user-circle"></i> You're signed in
                            </div>
                            <div class="text-white text-lg font-semibold">
                                You're on
                                <span class="smart-pill inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-sm align-middle">
                                    <i class="fas fa-bolt text-amber-300 text-xs"></i> {{ $rec['currentPlan']?->name ?? 'no plan' }}
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
                                                        <span class="text-emerald-300 font-semibold">{{ number_format($u['used']) }}</span> · unlimited
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
                                <p class="mt-3 text-sm text-gray-400">Start adding links and bio pages to see how much room your plan has.</p>
                            @endif
                        </div>

                        {{-- Right: recommendation card --}}
                        @if($rec['recommendedPlan'])
                            @php $recPlan = $rec['recommendedPlan']; @endphp
                            <a href="{{ route('user.upgrade', ['cycle' => $cycle]) }}"
                               class="group block rounded-2xl border border-violet-400/40 p-5 bg-violet-600/15 hover:bg-violet-600/25 transition relative overflow-hidden">
                                <div class="absolute -right-10 -top-10 w-32 h-32 rounded-full bg-violet-500/20 blur-2xl pointer-events-none"></div>
                                <div class="text-[11px] font-bold uppercase tracking-[.18em] text-pink-300 mb-1">
                                    <i class="fas fa-wand-magic-sparkles"></i> Recommended for you
                                </div>
                                <div class="flex items-baseline justify-between gap-3 mt-1">
                                    <div>
                                        <div class="text-2xl font-bold text-white">Upgrade to {{ $recPlan->name }}</div>
                                        <div class="text-sm text-gray-300 mt-1">{{ $rec['reason'] }}</div>
                                    </div>
                                    <i class="fas fa-arrow-right text-pink-300 group-hover:translate-x-1 transition"></i>
                                </div>
                                <div class="mt-4 inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/10 text-xs font-semibold text-white">
                                    See plans &amp; checkout <i class="fas fa-chevron-right text-[10px]"></i>
                                </div>
                            </a>
                        @else
                            <div class="rounded-2xl border border-emerald-400/30 p-5 bg-emerald-400/5 flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-emerald-400/15 flex items-center justify-center text-emerald-300">
                                    <i class="fas fa-check"></i>
                                </div>
                                <div class="text-emerald-100">
                                    <div class="font-semibold">You're on our top tier.</div>
                                    <div class="text-sm text-emerald-200/80">Add coin packs below for one-off boosts.</div>
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
            <div class="relative inline-flex items-center rounded-full border border-white/10 bg-white/[0.03] p-1 shadow-inner shadow-black/20">
                {{-- Sliding knob --}}
                <span class="absolute top-1 bottom-1 left-1 w-[calc(50%-0.25rem)] rounded-full grad-bar shadow-lg shadow-violet-900/30 transition-transform duration-300 ease-out motion-reduce:transition-none"
                      :class="cycle==='annual' ? 'translate-x-full' : 'translate-x-0'" aria-hidden="true"></span>
                <button type="button" @click="cycle='monthly'"
                    :class="cycle==='monthly' ? 'text-white' : 'text-gray-400 hover:text-white'"
                    class="relative z-10 w-28 px-5 py-2 text-sm font-semibold rounded-full transition-colors">Monthly</button>
                <button type="button" @click="cycle='annual'"
                    :class="cycle==='annual' ? 'text-white' : 'text-gray-400 hover:text-white'"
                    class="relative z-10 w-28 px-5 py-2 text-sm font-semibold rounded-full transition-colors inline-flex items-center justify-center gap-1.5">
                    Annual
                </button>
            </div>
            {{-- Savings callout — emphasised on annual, teasing on monthly --}}
            <div class="h-5 text-xs" aria-live="polite">
                <span x-show="cycle==='annual'" x-cloak class="inline-flex items-center gap-1.5 font-semibold text-emerald-300">
                    <i class="fas fa-circle-check"></i> You're saving 2 months — that's ~17% off every year
                </span>
                <span x-show="cycle==='monthly'" x-cloak class="inline-flex items-center gap-1.5 text-gray-400">
                    <i class="fas fa-piggy-bank text-emerald-400/80"></i> Switch to annual and save 2 months (~17%)
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
                ['key' => 'white_label',        'group' => 'Link in bio',      'icon' => 'fa-tag',                        'type' => 'bool',      'name' => 'Remove 1INME branding','desc' => 'White-label your public pages'],

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
            ];

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
                    if ($val === '*') return ['num' => PHP_INT_MAX, 'name' => 'All biolink blocks', 'desc' => $e['desc']];
                    if (is_array($val) && count($val) > 0) return ['num' => count($val), 'name' => count($val) . ' biolink blocks', 'desc' => $e['desc']];
                    return null;
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
                            ? 'border-pink-400/50'
                            : ($isPopular
                                ? 'border-violet-500/50'
                                : 'border-white/10'));
                    // Header-band tint mirrors the card emphasis.
                    $bandClass = $isCurrent ? 'is-current' : ($isAccent ? 'is-accent' : '');
                @endphp
                <div
                    x-data='{ plan: @json($planJs) }'
                    class="plan-card grad-glow {{ $isAccent ? 'is-accent' : '' }} {{ $isPopular || $isRecommended ? 'is-popular' : '' }} {{ $isCurrent ? 'is-current' : '' }} group relative rounded-2xl border {{ $borderClasses }} flex flex-col">

                    {{-- ── Tinted header band: name + short description + badge ── --}}
                    <div class="plan-band {{ $bandClass }} px-5 sm:px-6 pt-5 pb-4">
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="text-xl font-bold text-white leading-tight">{{ $plan->name }}</h3>
                            @if($isCurrent)
                                <span class="shrink-0 inline-flex items-center gap-1 whitespace-nowrap px-2.5 py-1 bg-emerald-500 text-white text-[10px] font-bold rounded-full uppercase tracking-wider">
                                    <i class="fas fa-circle-check"></i> Your plan
                                </span>
                            @elseif($isRecommended)
                                <span class="shrink-0 inline-flex items-center gap-1 whitespace-nowrap px-2.5 py-1 grad-bar text-white text-[10px] font-bold rounded-full uppercase tracking-wider">
                                    <i class="fas fa-wand-magic-sparkles"></i> Recommended
                                </span>
                            @elseif($isPopular)
                                <span class="shrink-0 inline-flex items-center gap-1 whitespace-nowrap px-2.5 py-1 grad-bar text-white text-[10px] font-bold rounded-full uppercase tracking-wider">
                                    <i class="fas fa-star"></i> Most popular
                                </span>
                            @endif
                        </div>
                        <p class="text-sm text-gray-400 mt-1.5 leading-snug min-h-[2.5rem]">{{ $plan->description }}</p>
                    </div>

                    {{-- ── Body: price → billing note → CTA → feature list ── --}}
                    <div class="px-5 sm:px-6 pt-5 pb-6 flex flex-col flex-grow">
                        @php
                            // Initial (server-rendered) price-size bucket so the
                            // number fits before Alpine hydrates and takes over
                            // the reactive sizing. Mirrors the priceSize() JS.
                            $initialFormatted = $row['prices'][$currency][$cycle]['formatted'] ?? '—';
                            $initialLen = mb_strlen((string) preg_replace('/\s/', '', $initialFormatted));
                            $initialPriceSize = $initialLen >= 11 ? 'price-xs'
                                : ($initialLen >= 9 ? 'price-sm'
                                : ($initialLen >= 7 ? 'price-md' : ''));
                        @endphp
                        <div class="plan-price px-4 py-4">
                            {{-- First-term intro badge: only when a discount applies for
                                 the active currency × cycle. --}}
                            <template x-if="intro(plan)">
                                <div class="mb-2 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-400/15 border border-emerald-300/30 text-emerald-300 text-[10px] font-bold uppercase tracking-wider">
                                    <i class="fas fa-bolt"></i>
                                    <span x-text="(intro(plan)?.label) || ('Save ' + intro(plan)?.percent_off + '% first ' + (cycle==='annual' ? 'year' : 'month'))"></span>
                                </div>
                            </template>
                            <div class="flex items-baseline gap-1.5 min-w-0 flex-wrap">
                                {{-- Struck-through normal price when intro is active --}}
                                <template x-if="intro(plan)">
                                    <span class="text-base font-semibold text-gray-500 line-through decoration-2" x-text="intro(plan)?.normal_formatted"></span>
                                </template>
                                <span class="price-num font-bold text-white tracking-tight price-pop {{ $initialPriceSize }}"
                                      :class="{ 'price-md': priceSize(plan) === 'price-md', 'price-sm': priceSize(plan) === 'price-sm', 'price-xs': priceSize(plan) === 'price-xs' }"
                                      :key="cycle + '-' + currency + '-' + priceKey + '-{{ $plan->id }}'"
                                      x-text="intro(plan) ? intro(plan).first_formatted : money(plan, cycle)">{{ $initialFormatted }}</span>
                                @unless(!empty($row['is_free']))
                                    <span class="price-suffix shrink-0 text-sm font-medium text-gray-500">/ <span x-text="cycle==='annual' ? 'yr' : 'mo'">{{ $cycle === 'annual' ? 'yr' : 'mo' }}</span></span>
                                @endunless
                            </div>

                            {{-- Intro fineprint: makes the revert-to-normal explicit --}}
                            <template x-if="intro(plan)">
                                <div class="text-[11px] text-emerald-300/90 mt-1.5">
                                    First <span x-text="cycle==='annual' ? 'year' : 'month'"></span> only — renews at <span x-text="intro(plan)?.normal_formatted"></span>/<span x-text="cycle==='annual' ? 'yr' : 'mo'"></span>
                                </div>
                            </template>

                            {{-- Billing note (Linktree fineprint under the price) --}}
                            @if(!empty($row['is_free']))
                                <div class="text-xs text-gray-400 mt-1.5">Free forever — no card required</div>
                            @else
                                <div class="text-xs text-gray-400 mt-1.5 min-h-[1rem]" x-text="billingNote(plan)"></div>
                            @endif

                            {{-- Tax / fineprint blocks per currency × cycle, toggled by Alpine.
                                 Both currencies are pre-rendered so the instant switcher stays
                                 accurate for signed-in buyers with a billing address. --}}
                            @foreach(['USD','INR'] as $cur)
                                @foreach(['monthly','annual'] as $c)
                                    @php $taxBlock = $row['tax'][$cur][$c] ?? null; @endphp
                                    @if(($row['prices'][$cur][$c]['amount_minor'] ?? 0) > 0)
                                        <div x-show="currency==='{{ $cur }}' && cycle==='{{ $c }}'" x-cloak>
                                            @if(!empty($taxBlock) && !empty($taxBlock['tax_breakdown']))
                                                <div class="mt-2 text-[11px] text-gray-400 space-y-0.5 border-t border-white/5 pt-2">
                                                    @foreach($taxBlock['tax_breakdown'] as $line)
                                                        <div class="flex justify-between"><span>+ {{ $line['label'] }}</span><span>{{ \App\Services\PricingResolver::money((int) $line['amount_minor'], $cur) }}</span></div>
                                                    @endforeach
                                                    <div class="flex justify-between font-medium text-white pt-1"><span>Total</span><span>{{ \App\Services\PricingResolver::money((int) $taxBlock['grand_total_minor'], $cur) }}</span></div>
                                                </div>
                                            @else
                                                <div class="mt-2 text-[11px] text-gray-500">+ taxes as applicable (shown at checkout)</div>
                                            @endif
                                        </div>
                                    @endif
                                @endforeach
                            @endforeach
                        </div>

                        {{-- Full-width CTA --}}
                        <div class="mt-5">
                            @switch($cmpKind)
                                @case('current')
                                    <span class="block text-center w-full px-4 py-2.5 rounded-full font-semibold bg-emerald-500/15 text-emerald-200 border border-emerald-400/30">
                                        <i class="fas fa-check mr-1"></i> Current plan
                                    </span>
                                    @break
                                @case('upgrade')
                                    <a :href="'{{ route('user.upgrade') }}?cycle=' + cycle"
                                       href="{{ route('user.upgrade', ['cycle' => $cycle]) }}"
                                       class="block text-center w-full px-4 py-2.5 rounded-full font-semibold grad-bar text-white hover:opacity-95 transition">
                                        Upgrade to {{ $plan->name }} <i class="fas fa-arrow-up ml-1 text-xs"></i>
                                    </a>
                                    @break
                                @case('downgrade')
                                    <a :href="'{{ route('user.upgrade') }}?cycle=' + cycle"
                                       href="{{ route('user.upgrade', ['cycle' => $cycle]) }}"
                                       class="block text-center w-full px-4 py-2.5 rounded-full font-semibold border border-white/15 bg-white/[0.04] text-white hover:bg-white/[0.10] transition">
                                        Downgrade to {{ $plan->name }}
                                    </a>
                                    @break
                                @case('choose')
                                    <a :href="'{{ route('user.upgrade') }}?cycle=' + cycle"
                                       href="{{ route('user.upgrade', ['cycle' => $cycle]) }}"
                                       class="block text-center w-full px-4 py-2.5 rounded-full font-semibold {{ $ctaFilled ? 'grad-bar text-white hover:opacity-95' : 'border border-white/15 bg-white/[0.04] text-white hover:bg-white/[0.10]' }} transition">
                                        Choose {{ $plan->name }}
                                    </a>
                                    @break
                                @default
                                    <a href="{{ route('user.register') }}"
                                       class="block text-center w-full px-4 py-2.5 rounded-full font-semibold {{ $ctaFilled ? 'grad-bar text-white hover:opacity-95' : 'border border-white/15 bg-white/[0.04] text-white hover:bg-white/[0.10]' }} transition">
                                        {{ !empty($row['is_free']) ? 'Get started free' : 'Start free trial' }}
                                    </a>
                            @endswitch
                        </div>

                        {{-- Grouped, icon-led feature list (delta vs previous tier) --}}
                        <div class="mt-6 flex-grow">
                            <div class="text-sm font-semibold text-white mb-3">
                                @if($prevPlan)
                                    Everything in {{ $prevPlan->name }}, plus:
                                @else
                                    Key features
                                @endif
                            </div>
                            <div class="space-y-4">
                                @forelse($groups as $groupName => $items)
                                    <div>
                                        <div class="text-[11px] font-bold uppercase tracking-wider text-violet-300 mb-2">{{ $groupName }}</div>
                                        <ul class="space-y-2.5">
                                            @foreach($items as $f)
                                                <li class="flex items-start gap-2.5">
                                                    <span class="mt-0.5 w-5 h-5 shrink-0 rounded-md bg-violet-500/15 ring-1 ring-violet-400/25 flex items-center justify-center">
                                                        <i class="fas {{ $f['icon'] }} text-violet-300 text-[10px]"></i>
                                                    </span>
                                                    <span class="min-w-0">
                                                        <span class="block text-sm font-semibold text-white leading-tight">{{ $f['name'] }}</span>
                                                        @if(!empty($f['desc']))
                                                            <span class="block text-xs text-gray-400 leading-snug">{{ $f['desc'] }}</span>
                                                        @endif
                                                    </span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @empty
                                    <div class="text-sm text-gray-400">Everything you need to get started.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
                </div>
            </div>
            @if($planCount > 1)
                <div x-show="scrollable" x-cloak class="mt-3 flex items-center justify-center gap-2 text-[11px] text-gray-500" aria-hidden="true">
                    <i class="fas fa-arrows-left-right text-violet-400/70"></i>
                    Scroll to compare every plan
                </div>
            @endif
        </div>

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
                'Link types' => [
                    ['max_conversational',   'Conversational pages',  'number'],
                    ['max_slides',           'Slides pages',          'number'],
                    ['max_ai_chat',          'AI Chatbot pages',      'number'],
                    ['max_restaurant_menu',  'Restaurant Menu pages', 'number'],
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
                    ['teams',                    'Team workspaces',         'bool'],
                    ['leads',                    'Leads capture',           'bool'],
                    ['vaults',                   'Credential vault',        'bool'],
                    ['creator_profile_public',   'Public creator profile',  'bool'],
                ],
                'AI features' => [
                    ['max_minds',                'AI Minds',                'number'],
                    ['max_personas',             'AI Personas',             'number'],
                    ['max_companions',           'AI Companions',           'number'],
                    ['ai_widget',                'Site Assistant widget',   'bool'],
                    ['ai_voice_assistant',       'Voice Assistant',         'bool'],
                    ['ask_coach',                'Ask Coach',               'bool'],
                    ['card_scan',                'Card & Brochure Scanner', 'bool'],
                    ['ai_resume_tools',          'AI Resume Tools',         'bool'],
                ],
                'Developer API' => [
                    ['api_access',               'API access',              'bool'],
                    ['api_calls_monthly',        'API calls / month',       'number'],
                    ['api_rate_per_min',         'API rate (calls / min)',  'number'],
                ],
            ];
            // Fixed (locked) column widths so every plan column lines up
            // uniformly; the matrix scroll box handles overflow when the
            // combined width exceeds the container.
            $colTpl = '220px repeat(' . count($plans) . ', 160px)';
            $renderCell = function ($plan, $key, $kind) {
                $features = $plan->features ?? [];
                if (!array_key_exists($key, $features) && $kind !== 'analytics') {
                    return '<span class="feat-mark feat-mark-no" aria-label="Not included"><i class="fas fa-minus text-[10px]"></i></span>';
                }
                $val = $features[$key] ?? null;
                if ($kind === 'number') {
                    if ((int) $val === -1) return '<span class="text-emerald-300 font-semibold">Unlimited</span>';
                    if ((int) $val === 0)  return '<span class="feat-mark feat-mark-no" aria-label="Not included"><i class="fas fa-minus text-[10px]"></i></span>';
                    return '<span class="text-white font-semibold">' . number_format((int) $val) . '</span>';
                }
                if ($kind === 'analytics') {
                    $label = is_string($val) ? ucfirst($val) : 'Basic';
                    $cls = strtolower((string) $val) === 'advanced' ? 'text-emerald-300 font-semibold' : 'text-gray-300';
                    return '<span class="' . $cls . '">' . e($label) . '</span>';
                }
                return $val
                    ? '<span class="feat-mark feat-mark-yes" aria-label="Included"><i class="fas fa-check text-[11px]"></i></span>'
                    : '<span class="feat-mark feat-mark-no" aria-label="Not included"><i class="fas fa-minus text-[10px]"></i></span>';
            };
        @endphp
        <div class="mt-16" data-anim="fade-up">
            <div class="text-center max-w-2xl mx-auto mb-6">
                <div class="text-xs font-bold uppercase tracking-[.2em] text-violet-400 mb-2">Side by side</div>
                <h2 class="text-3xl sm:text-4xl font-bold tracking-tight">Compare features at a glance</h2>
                <p class="text-gray-400 mt-2">Every plan, every important feature — laid out so you can spot the deltas in seconds.</p>
            </div>
            <div class="rounded-3xl border border-white/10 bg-white/[0.02] overflow-hidden">
                <div class="feat-matrix-scroll">
                    <div class="feat-matrix grid" style="grid-template-columns: {{ $colTpl }};">
                        {{-- Header row --}}
                        <div class="feat-cell feat-head feat-row-name">Feature</div>
                        @foreach($plans as $row)
                            @php $p = $row['model']; @endphp
                            <div class="feat-cell feat-head text-center {{ $p->is_popular ? 'feat-popular-col' : '' }}">
                                <span class="text-white text-sm font-semibold normal-case tracking-normal">
                                    @if($p->is_popular)<i class="fas fa-star text-pink-400 text-[10px]"></i>@endif
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
        <div id="coins" class="mt-20" data-anim="fade-up">
            <div class="text-center max-w-2xl mx-auto mb-8">
                <div class="text-xs font-bold uppercase tracking-[.2em] text-amber-300 mb-2">
                    <i class="fas fa-coins"></i> Top up with coins
                </div>
                <h2 class="text-3xl sm:text-4xl font-bold tracking-tight">Out of API calls? Just grab some coins.</h2>
                <p class="text-gray-400 mt-2">
                    Every plan includes a monthly API-call allowance. When you go over it,
                    coins automatically top up the extra calls — no overage bill, no hard stop.
                    Coins also activate paid add-ons on demand — one-off campaigns,
                    NFC tag batches or AI credit top-ups — without committing to a higher plan.
                </p>
            </div>

            @include('public.pricing._ai_coin_uses')

            @if(!$wallet_enabled)
                <div class="max-w-2xl mx-auto rounded-2xl border border-amber-400/20 bg-amber-400/5 p-6 text-amber-200 text-center">
                    <i class="fas fa-coins text-2xl float-coin mb-2 block"></i>
                    Coin top-ups aren't enabled on this site yet — check back soon.
                </div>
            @elseif($packages->isEmpty())
                <div class="max-w-2xl mx-auto rounded-2xl border border-white/10 bg-white/[0.02] p-6 text-gray-400 text-center">
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
                        if ($total < 20000) return 'AI credit top-ups + a few add-on activations.';
                        return 'Power-users — months of AI generation, gifting credits, or running multiple add-ons in parallel.';
                    };
                @endphp
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    @foreach($packages as $row)
                        @php $pkg = $row['model']; $isFeat = $pkg->bonus_coins > 0; @endphp
                        <div x-data='{ prices: @json($row['prices']) }'
                             class="grad-glow {{ $isFeat ? 'is-popular' : '' }} relative rounded-2xl border {{ $isFeat ? 'border-amber-400/40' : 'border-white/10' }} bg-white/[0.02] coin-bg p-6 flex flex-col overflow-hidden">
                            @if($isFeat)
                                <div class="absolute -top-3 right-6 px-3 py-1 bg-amber-400 text-[#1e2330] text-[10px] font-bold rounded-full uppercase tracking-wider shadow-lg shadow-amber-500/20">
                                    +{{ number_format($pkg->bonus_coins) }} bonus
                                </div>
                            @endif
                            <div class="absolute -right-6 -top-6 w-28 h-28 rounded-full bg-amber-400/10 blur-2xl pointer-events-none"></div>

                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-amber-400/15 ring-1 ring-amber-400/30 flex items-center justify-center float-coin">
                                    <i class="fas fa-coins text-amber-300"></i>
                                </div>
                                <div class="text-xs uppercase tracking-wider text-gray-400">{{ $pkg->name }}</div>
                            </div>

                            <div class="mt-3 flex items-baseline gap-2">
                                <span class="price-num text-4xl font-semibold text-white">{{ number_format($row['total_coins']) }}</span>
                                <span class="text-amber-300 text-sm">coins</span>
                            </div>
                            @if($pkg->bonus_coins > 0)
                                <div class="text-[11px] text-gray-500 mt-1">{{ number_format($pkg->coin_amount) }} base + <span class="text-amber-300">{{ number_format($pkg->bonus_coins) }} bonus</span></div>
                            @endif

                            <div class="mt-3 rounded-lg bg-white/[0.03] border border-white/5 px-3 py-2">
                                <div class="text-[10px] uppercase tracking-wider text-amber-300/80 font-bold mb-0.5">
                                    <i class="fas fa-bullseye"></i> Best for
                                </div>
                                <div class="text-xs text-gray-300 leading-snug">{{ $bestForFor($row) }}</div>
                            </div>

                            @if($pkg->description)
                                <p class="text-sm text-gray-400 mt-3 flex-grow">{{ $pkg->description }}</p>
                            @else
                                <div class="flex-grow"></div>
                            @endif

                            <div class="mt-5 pt-4 border-t border-white/5 flex items-center justify-between">
                                <span class="text-2xl font-bold text-white price-num" x-text="coinPrice(prices)">{{ $row['prices'][$currency]['formatted'] ?? '—' }}</span>
                                @auth
                                    <a href="{{ route('user.wallet.buy') }}" class="px-4 py-2 bg-amber-400 text-[#1e2330] rounded-xl text-sm font-bold hover:bg-amber-300 transition shadow-lg shadow-amber-500/20">Buy now</a>
                                @else
                                    <a href="{{ route('user.register') }}" class="px-4 py-2 bg-amber-400 text-[#1e2330] rounded-xl text-sm font-bold hover:bg-amber-300 transition shadow-lg shadow-amber-500/20">Sign up to buy</a>
                                @endauth
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ───────────────── FOOTER LINKS ───────────────── --}}
        <div class="mt-14 text-center" data-anim="fade-up">
            <p class="text-gray-400">Want the full feature breakdown?</p>
            <div class="mt-4 flex flex-wrap items-center justify-center gap-3">
                <a href="{{ route('site.premium-features') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full border border-white/10 bg-white/[0.04] text-white hover:bg-white/[0.08] text-sm font-medium transition">
                    <i class="fas fa-star text-violet-400"></i> See premium features
                </a>
                <a href="#coins" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full border border-white/10 bg-white/[0.04] text-white hover:bg-white/[0.08] text-sm font-medium transition">
                    <i class="fas fa-coins text-amber-400"></i> Browse coin packages
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
                    <div class="text-[11px] font-bold uppercase tracking-[.2em] text-violet-300 mb-1">Referral program</div>
                    <div class="text-white font-semibold leading-snug">Tell a friend, both get credit.</div>
                    <p class="text-sm text-gray-400 mt-1 leading-relaxed">
                        Share your personal <span class="font-mono text-violet-300">/r/&lt;your-code&gt;</span> link — every signup is tracked back to you, and your referrals land on the right plan with a thank-you discount applied automatically.
                    </p>
                </div>
                @auth
                    <a href="{{ \Illuminate\Support\Facades\Route::has('user.referrals.index') ? route('user.referrals.index') : route('user.dashboard') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full border border-violet-400/30 bg-violet-500/10 text-violet-200 hover:bg-violet-500/20 text-sm font-bold whitespace-nowrap shrink-0">
                        Get my referral link <i class="fas fa-arrow-right text-[11px]"></i>
                    </a>
                @else
                    <a href="{{ route('register.page') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full border border-violet-400/30 bg-violet-500/10 text-violet-200 hover:bg-violet-500/20 text-sm font-bold whitespace-nowrap shrink-0">
                        Sign up &amp; share <i class="fas fa-arrow-right text-[11px]"></i>
                    </a>
                @endauth
            </div>
        </div>
    </div>
</section>

{{-- ============================ HEAD-TO-HEAD ONLY (compact) ============================
     The 24-feature matrix is intentionally hidden on the pricing page (the
     "Compare features at a glance" matrix above already serves that purpose
     for plans). We force the compact path so visitors only see the more
     creative 1-on-1 rival selector. --}}
@include('public.partials._compare', ['compact' => true, 'anchorId' => 'compare'])

@include('public.partials.subscribe-block', [
    'heading' => 'Pricing changes, deals, and new plans.',
    'subtext' => 'Be the first to know about coin packages and seasonal offers — pick email, WhatsApp Channel, or DM.',
    'source'  => 'pricing',
])
@endsection
