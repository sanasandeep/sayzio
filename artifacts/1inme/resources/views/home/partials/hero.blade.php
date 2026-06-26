{{-- ============================ HERO (Zio orbital) ============================
     AI-first home hero. Zio (the AI mascot) sits at the centre of an orbiting
     ring of feature nodes — every Sayzio tool revolves around the AI. The ring
     icons are brand images; clicking a node opens a small glass popover with a
     title + one-line description and pauses the orbit so it stays readable
     (Alpine: only one open at a time, dismiss via re-click / outside / Esc).

     Reuses the homepage design system only (glass, reveal, rd-*, grad-text,
     grad-bar, btn-bounce, btn-glow, confetti, --c1..--c5) so dark/light modes
     and reduced-motion carry over. All motion is pure CSS and freezes under
     prefers-reduced-motion. CTAs keep the existing open-auth +
     trackMarketingEvent behaviour.
--}}
<section class="relative overflow-hidden pt-28 pb-16 sm:pt-32 lg:pt-24 lg:pb-24 lg:min-h-[100svh] lg:flex lg:items-center" aria-labelledby="hero-h">
    {{-- Drifting confetti --}}
    <div class="confetti drift-a" style="left:10%; bottom:-22vh;"><div class="w-3 h-3 rounded-sm" style="background:var(--c1)"></div></div>
    <div class="confetti drift-b" style="left:86%; bottom:-28vh; animation-delay:-6s"><div class="w-2 h-6 rounded-full" style="background:var(--c2)"></div></div>

    <div class="relative w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-10 xl:px-12">
        <div class="grid grid-cols-1 gap-y-16 lg:grid-cols-[1.05fr_1fr] lg:gap-x-14 xl:gap-x-20 lg:items-center">

            {{-- Copy column --}}
            <div class="text-center lg:text-left lg:max-w-[600px]">
                <div class="reveal inline-flex items-center gap-2 px-4 py-1.5 glass rounded-full text-xs font-semibold mb-8">
                    <i class="fas fa-wand-magic-sparkles text-[11px]" style="color:var(--c2)"></i>
                    <span class="grad-text">One AI. Every tool. Free forever.</span>
                </div>

                <h1 id="hero-h" class="reveal rd-1 text-5xl sm:text-6xl lg:text-7xl font-bold leading-[1.04] tracking-tight mb-6">
                    One AI runs your whole <span class="grad-text">universe</span>
                </h1>

                <p class="reveal rd-2 text-lg sm:text-xl text-gray-400 max-w-xl mx-auto lg:mx-0 mb-9 leading-relaxed">
                    <strong class="text-white">Zio</strong> is the AI at the heart of Sayzio. It builds your pages, coaches your growth, replies to visitors — even answers your calls. One smart link that markets you 24/7, <strong class="text-white">free forever</strong>, no card required.
                </p>

                <div class="reveal rd-3 flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4 justify-center lg:justify-start">
                    <button type="button" onclick="window.trackMarketingEvent && window.trackMarketingEvent('landing_home_cta','hero'); window.dispatchEvent(new CustomEvent('open-auth',{detail:{tab:'register'}}))" class="btn-bounce btn-glow inline-flex items-center justify-center gap-2 px-8 py-4 grad-bar text-white rounded-full text-base font-bold whitespace-nowrap shrink-0">
                        Start free with AI <i class="fas fa-arrow-right text-sm"></i>
                    </button>
                    <a href="#ai-suite" class="zio-cta-ghost inline-flex items-center justify-center gap-2 px-7 py-4 rounded-full text-base font-bold whitespace-nowrap">
                        Meet the AI suite
                    </a>
                </div>

                <div class="reveal rd-4 flex flex-wrap items-center gap-x-6 gap-y-3 mt-12 justify-center lg:justify-start text-sm">
                    <span class="flex items-center gap-2 text-gray-400">
                        <span class="w-1.5 h-1.5 rounded-full" style="background:#1ed760"></span>
                        <span class="font-bold text-white">120,000+</span><span class="text-gray-500">creators</span>
                    </span>
                    <span class="flex items-center gap-2 text-gray-400">
                        <span class="w-1.5 h-1.5 rounded-full" style="background:var(--c2)"></span>
                        <span class="font-bold text-white">8 tools,</span><span class="text-gray-500">one AI brain</span>
                    </span>
                    <span class="flex items-center gap-2 text-gray-400">
                        <span class="w-1.5 h-1.5 rounded-full" style="background:var(--c1)"></span>
                        <span class="font-bold text-white">Free forever</span><span class="text-gray-500">· no card</span>
                    </span>
                </div>
            </div>

            {{-- Orbital Zio visual --}}
            <div class="reveal rd-2 zio-orbit-wrap">
                <div class="zio-orbit" x-data="{ open: null }" @keydown.escape.window="open = null" @click.outside="open = null">
                    <span class="zio-glow" aria-hidden="true"></span>
                    <span class="zio-pulse zio-pulse--1" aria-hidden="true"></span>
                    <span class="zio-pulse zio-pulse--2" aria-hidden="true"></span>
                    <span class="zio-ring zio-ring--outer" aria-hidden="true"></span>
                    <span class="zio-ring zio-ring--inner" aria-hidden="true"></span>

                    @php
                        $zioNodes = [
                            ['a' => 0,   'img' => 'ai.png',        'c' => 'var(--c2)', 't' => 'AI Page Builder', 'd' => 'Describe it — Zio builds your page in seconds.'],
                            ['a' => 45,  'img' => 'analytics.png', 'c' => 'var(--c2)', 't' => 'Live Analytics',   'd' => 'Track every click, scan and visit in real time.'],
                            ['a' => 90,  'img' => 'growth.png',    'c' => 'var(--c1)', 't' => 'Growth Coach',     'd' => "Zio spots what's working and what to do next."],
                            ['a' => 135, 'img' => 'store.png',     'c' => '#10b981',   't' => 'Built-in Store',   'd' => 'Sell products and take payments from one link.'],
                            ['a' => 180, 'img' => 'calls.png',     'c' => 'var(--c4)', 't' => 'AI Phone',         'd' => 'Zio answers calls and turns them into leads.'],
                            ['a' => 225, 'img' => 'link.png',      'c' => 'var(--c2)', 't' => 'Smart Links',      'd' => 'Branded short links for everything you share.'],
                            ['a' => 270, 'img' => 'qr.png',        'c' => 'var(--c3)', 't' => 'QR Studio',        'd' => 'Design scannable codes that track every scan.'],
                            ['a' => 315, 'img' => 'code.png',      'c' => 'var(--c2)', 't' => 'Developer API',    'd' => 'Build on Sayzio with a full REST API.'],
                        ];
                    @endphp
                    <div class="zio-rotor" :class="{ 'zio-rotor--paused': open !== null }">
                        @foreach($zioNodes as $n)
                            @php $i = $loop->index; @endphp
                            <div class="zio-node"
                                 style="--a:{{ $n['a'] }}deg; --d:{{ 0.5 + $i * 0.09 }}s; --ac:{{ $n['c'] }}"
                                 :class="{ 'zio-node--on': open === {{ $i }} }">
                                <div class="zio-node-ic">
                                    <button type="button"
                                            class="zio-node-btn"
                                            @click="open = (open === {{ $i }} ? null : {{ $i }})"
                                            :aria-expanded="open === {{ $i }}"
                                            aria-label="{{ $n['t'] }}: {{ $n['d'] }}">
                                        <img class="zio-node-thumb" src="{{ asset('images/zio-nodes/' . $n['img']) }}" alt="" width="62" height="62" loading="lazy" decoding="async">
                                    </button>
                                    <div class="zio-pop" x-show="open === {{ $i }}" x-cloak x-transition.opacity.scale.95 @click.stop role="dialog" aria-label="{{ $n['t'] }}">
                                        <span class="zio-pop-title">{{ $n['t'] }}</span>
                                        <span class="zio-pop-desc">{{ $n['d'] }}</span>
                                        <button type="button" class="zio-pop-x" @click.stop="open = null" aria-label="Close">&times;</button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="zio-core" aria-hidden="true">
                        <span class="zio-mascot-halo"></span>
                        <img src="{{ asset('branding/sayzio-mascot.png') }}" alt="Zio, the Sayzio AI mascot" class="zio-mascot" width="220" height="220" loading="eager" decoding="async">
                        <span class="zio-core-label"><i class="fas fa-wand-magic-sparkles"></i> Zio runs it all</span>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <style>
        /* ============ Secondary (ghost) CTA ============ */
        .zio-cta-ghost {
            border: 1px solid rgba(255,255,255,.18);
            color: #fff;
            background: rgba(255,255,255,.02);
            transition: background .2s ease, border-color .2s ease, transform .22s cubic-bezier(.34,1.56,.64,1);
        }
        .zio-cta-ghost:hover {
            background: rgba(255,255,255,.07);
            border-color: rgba(255,255,255,.32);
            transform: translateY(-2px);
        }

        /* ============ Orbital Zio visual ============ */
        .zio-orbit-wrap { display: flex; align-items: center; justify-content: center; width: 100%; }
        .zio-orbit {
            --size: clamp(290px, 38vw, 460px);
            --node: 62px;
            --r: calc(var(--size) / 2 - 28px);
            position: relative;
            width: var(--size);
            height: var(--size);
        }

        .zio-glow {
            position: absolute; inset: -8%;
            border-radius: 50%;
            background: radial-gradient(circle at 50% 45%, rgba(61,107,255,.28), rgba(110,97,255,.12) 45%, transparent 70%);
            filter: blur(8px);
            z-index: 0;
            animation: zioGlowPulse 7s ease-in-out infinite;
        }
        @keyframes zioGlowPulse { 0%,100% { opacity: .85; transform: scale(1); } 50% { opacity: 1; transform: scale(1.06); } }

        /* ---- Ambient pulse rings (expand + fade outward) ---- */
        .zio-pulse {
            position: absolute; top: 50%; left: 50%;
            width: 46%; height: 46%;
            margin: -23% 0 0 -23%;
            border-radius: 50%;
            border: 1px solid rgba(120,150,255,.35);
            z-index: 0;
            opacity: 0;
            animation: zioPulse 5.5s ease-out infinite;
        }
        .zio-pulse--2 { animation-delay: 2.75s; }
        @keyframes zioPulse {
            0%   { transform: scale(.55); opacity: .55; }
            70%  { opacity: .12; }
            100% { transform: scale(2.1); opacity: 0; }
        }

        .zio-ring {
            position: absolute; border-radius: 50%;
            border: 1.5px dashed rgba(120,140,255,.30);
            z-index: 1;
        }
        .zio-ring--outer { inset: 0; }
        .zio-ring--inner { inset: 15%; border-color: rgba(120,140,255,.18); }

        .zio-rotor {
            position: absolute; inset: 0; z-index: 2;
            animation: zioSpin 46s linear infinite;
        }
        @keyframes zioSpin { to { transform: rotate(360deg); } }

        /* Lift the rotor above the central mascot (z-index:3) while a popover is
           open, so an active node's popover is never hidden behind Zio. */
        .zio-rotor--paused { z-index: 6; }

        /* Pause the orbit (and the counter-rotation) while a popover is open OR a
           node is hovered, so nodes are easy to click and popovers stay put. */
        .zio-rotor--paused,
        .zio-rotor--paused .zio-node-ic,
        .zio-rotor:has(.zio-node:hover),
        .zio-rotor:has(.zio-node:hover) .zio-node-ic,
        .zio-rotor:has(.zio-node-btn:focus-visible),
        .zio-rotor:has(.zio-node-btn:focus-visible) .zio-node-ic { animation-play-state: paused; }

        .zio-node {
            position: absolute; top: 50%; left: 50%;
            width: var(--node); height: var(--node);
            margin: calc(var(--node) / -2);
            transform: rotate(var(--a)) translate(0, calc(-1 * var(--r))) rotate(calc(-1 * var(--a)));
            animation: zioNodeFade .55s var(--d) ease backwards;
        }
        @keyframes zioNodeFade { from { opacity: 0; } to { opacity: 1; } }
        .zio-node--on { z-index: 16; }

        /* Counter-rotation wrapper — cancels the rotor spin so the tile + popover
           stay upright at all times (and frozen-upright while paused). */
        .zio-node-ic {
            position: relative;
            width: 100%; height: 100%;
            animation: zioSpinRev 46s linear infinite;
        }
        @keyframes zioSpinRev { to { transform: rotate(-360deg); } }

        .zio-node-btn {
            display: flex; align-items: center; justify-content: center;
            width: 100%; height: 100%;
            padding: 0; margin: 0;
            border-radius: 17px;
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.12);
            backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            box-shadow: 0 10px 26px -14px rgba(10,12,30,.85);
            cursor: pointer; pointer-events: auto;
            transition: box-shadow .25s ease, border-color .25s ease, background .25s ease;
        }
        .zio-node-btn:focus-visible { outline: 2px solid var(--c2); outline-offset: 3px; }

        .zio-node-thumb {
            width: 80%; height: 80%; object-fit: contain;
            transform: scale(1);
            pointer-events: none;
            transition: transform .28s cubic-bezier(.34,1.56,.64,1);
            animation: zioThumbPop .6s var(--d) cubic-bezier(.34,1.56,.64,1) backwards;
            filter: drop-shadow(0 4px 8px rgba(10,12,30,.35));
        }
        @keyframes zioThumbPop { from { opacity: 0; transform: scale(.35); } to { opacity: 1; transform: scale(1); } }

        /* Hover lift + active state (shadow/scale, not transform on the rotated node) */
        .zio-node-btn:hover .zio-node-thumb { transform: scale(1.14); }
        .zio-node-btn:hover {
            border-color: rgba(255,255,255,.30);
            background: rgba(255,255,255,.10);
            box-shadow: 0 16px 38px -16px rgba(61,107,255,.6);
        }
        .zio-node--on .zio-node-btn {
            border-color: color-mix(in srgb, var(--ac) 70%, white 10%);
            background: rgba(255,255,255,.12);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--ac) 28%, transparent), 0 20px 44px -16px color-mix(in srgb, var(--ac) 65%, transparent);
        }
        .zio-node--on .zio-node-thumb { transform: scale(1.12); }

        /* ---- Popover card (lives inside the upright counter-rotated tile) ---- */
        .zio-pop {
            position: absolute; bottom: calc(100% + 13px); left: 50%;
            transform: translateX(-50%);
            width: max-content; max-width: 210px;
            padding: 11px 30px 12px 13px;
            text-align: left;
            border-radius: 15px;
            background: rgba(15,19,38,.94);
            border: 1px solid rgba(255,255,255,.14);
            backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
            box-shadow: 0 22px 48px -20px rgba(4,6,22,.95);
            z-index: 30;
            cursor: default;
        }
        .zio-pop::after {
            content: ''; position: absolute; top: 100%; left: 50%;
            transform: translateX(-50%);
            border: 7px solid transparent; border-top-color: rgba(15,19,38,.94);
        }
        .zio-pop-title { display: block; font-size: 13px; font-weight: 800; color: #fff; line-height: 1.25; }
        .zio-pop-desc  { display: block; margin-top: 3px; font-size: 11.5px; font-weight: 500; color: rgba(214,222,255,.82); line-height: 1.4; }
        .zio-pop-x {
            position: absolute; top: 7px; right: 7px;
            width: 18px; height: 18px; line-height: 1;
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; color: rgba(255,255,255,.55);
            background: rgba(255,255,255,.06); border: 0; border-radius: 6px;
            cursor: pointer; transition: color .15s ease, background .15s ease;
        }
        .zio-pop-x:hover { color: #fff; background: rgba(255,255,255,.14); }

        .zio-core {
            position: absolute; top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            z-index: 3;
            display: flex; flex-direction: column; align-items: center;
            pointer-events: none;
        }
        .zio-mascot-halo {
            position: absolute; top: 38%; left: 50%;
            width: calc(var(--size) * 0.5); height: calc(var(--size) * 0.5);
            transform: translate(-50%, -50%);
            border-radius: 50%;
            background: radial-gradient(circle, rgba(61,107,255,.45), transparent 68%);
            filter: blur(10px);
            z-index: -1;
            animation: zioHalo 5s ease-in-out infinite;
        }
        @keyframes zioHalo { 0%,100% { opacity: .55; transform: translate(-50%,-50%) scale(1); } 50% { opacity: .9; transform: translate(-50%,-50%) scale(1.12); } }
        .zio-mascot {
            width: calc(var(--size) * 0.42);
            height: auto;
            filter: drop-shadow(0 18px 36px rgba(61,107,255,.45));
            animation: zioFloat 6.5s ease-in-out infinite;
            transform-origin: 50% 80%;
        }
        @keyframes zioFloat {
            0%,100% { transform: translateY(0) rotate(-1.5deg) scale(1); }
            50%     { transform: translateY(-12px) rotate(1.5deg) scale(1.02); }
        }
        .zio-core-label {
            margin-top: 6px;
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 12px;
            border-radius: 999px;
            font-size: 11px; font-weight: 700;
            background: rgba(255,255,255,.08);
            border: 1px solid rgba(255,255,255,.14);
            backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            color: #fff; white-space: nowrap;
        }
        .zio-core-label i { color: var(--c2); font-size: 10px; }

        /* ---- Tablet (single-column, sm→below-lg): the orbit is stacked under the
               copy in a wide column, so the vw-based size leaves it looking small and
               lost. Bump --size here so it fills the column with more presence. Every
               sub-element (mascot, node radius) derives from --size, so they scale in
               lockstep. Does NOT touch the ≥lg two-column desktop layout. ---- */
        @media (min-width: 640px) and (max-width: 1023.98px) {
            .zio-orbit { --size: clamp(360px, 54vw, 460px); }
        }

        /* ---- Light mode ---- */
        html.light-mode .zio-glow {
            background: radial-gradient(circle at 50% 45%, rgba(61,107,255,.18), rgba(110,97,255,.08) 45%, transparent 70%);
        }
        html.light-mode .zio-pulse { border-color: rgba(37,66,199,.28); }
        html.light-mode .zio-ring { border-color: rgba(37,66,199,.26); }
        html.light-mode .zio-ring--inner { border-color: rgba(37,66,199,.15); }
        html.light-mode .zio-node-btn {
            background: #ffffff; border-color: #e2e8f0;
            box-shadow: 0 10px 24px -14px rgba(15,23,42,.35);
        }
        html.light-mode .zio-node-btn:hover {
            border-color: #c7d2fe; background: #ffffff;
            box-shadow: 0 16px 34px -16px rgba(61,107,255,.45);
        }
        html.light-mode .zio-node--on .zio-node-btn { background: #ffffff; }
        html.light-mode .zio-pop {
            background: rgba(255,255,255,.97); border-color: #e2e8f0;
            box-shadow: 0 22px 48px -20px rgba(15,23,42,.35);
        }
        html.light-mode .zio-pop::after { border-top-color: rgba(255,255,255,.97); }
        html.light-mode .zio-pop-title { color: #0f172a; }
        html.light-mode .zio-pop-desc { color: #475569; }
        html.light-mode .zio-pop-x { color: #64748b; background: #f1f5f9; }
        html.light-mode .zio-pop-x:hover { color: #0f172a; background: #e2e8f0; }
        html.light-mode .zio-mascot-halo { background: radial-gradient(circle, rgba(61,107,255,.28), transparent 68%); }
        html.light-mode .zio-core-label {
            background: #ffffff; border-color: #e2e8f0; color: #0f172a;
            box-shadow: 0 6px 16px -10px rgba(15,23,42,.3);
        }
        html.light-mode .zio-cta-ghost {
            border-color: #cbd5e1; color: #0f172a; background: #ffffff;
        }
        html.light-mode .zio-cta-ghost:hover { background: #f1f5f9; border-color: #94a3b8; }

        /* ---- Reduced motion: freeze the orbit + ambient layers (nodes stay
               placed + upright, everything visible, popovers still work) ---- */
        @media (prefers-reduced-motion: reduce) {
            .zio-rotor, .zio-node-ic, .zio-mascot, .zio-mascot-halo,
            .zio-glow, .zio-pulse, .zio-node, .zio-node-thumb {
                animation: none !important;
            }
            .zio-node, .zio-node-thumb { opacity: 1 !important; }
            .zio-node-thumb { transform: scale(1) !important; }
            .zio-pulse { opacity: 0 !important; }
        }
    </style>
</section>
