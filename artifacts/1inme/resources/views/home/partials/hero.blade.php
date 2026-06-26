{{-- ============================ HERO (Zio orbital) ============================
     AI-first home hero. Zio (the AI mascot) sits at the centre of an orbiting
     ring of feature icons — every Sayzio tool revolves around the AI. Reuses the
     homepage design system only (glass, reveal, rd-*, grad-text, grad-bar,
     btn-bounce, btn-glow, confetti, --c1..--c5) so dark/light modes and
     reduced-motion carry over. The orbit animation is pure CSS and pauses under
     prefers-reduced-motion. CTA keeps the existing open-auth + trackMarketingEvent
     behaviour.
--}}
<section class="relative pt-12 pb-20 lg:pt-16 lg:pb-32 xl:pt-20 xl:pb-40 overflow-hidden" aria-labelledby="hero-h">
    {{-- Drifting confetti --}}
    <div class="confetti drift-a" style="left:10%; bottom:-22vh;"><div class="w-3 h-3 rounded-sm" style="background:var(--c1)"></div></div>
    <div class="confetti drift-b" style="left:86%; bottom:-28vh; animation-delay:-6s"><div class="w-2 h-6 rounded-full" style="background:var(--c2)"></div></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-10 xl:px-12">
        <div class="grid grid-cols-1 gap-y-14 lg:grid-cols-[1.05fr_1fr] lg:gap-x-12 xl:gap-x-16 lg:items-center">

            {{-- Copy column --}}
            <div class="text-center lg:text-left lg:max-w-[600px]">
                <div class="reveal inline-flex items-center gap-2 px-4 py-1.5 glass rounded-full text-xs font-semibold mb-8">
                    <i class="fas fa-wand-magic-sparkles text-[11px]" style="color:var(--c2)"></i>
                    <span class="grad-text">The AI-first marketing toolkit</span>
                </div>

                <h1 id="hero-h" class="reveal rd-1 text-5xl sm:text-6xl lg:text-7xl font-bold leading-[1.05] tracking-tight mb-7">
                    One AI runs your whole <span class="grad-text">universe</span>
                </h1>

                <p class="reveal rd-2 text-lg sm:text-xl text-gray-400 max-w-xl mx-auto lg:mx-0 mb-10 leading-relaxed">
                    Meet <strong class="text-white">Zio</strong>, the AI at the center of Sayzio. Every tool orbits around it — building your pages, coaching your growth, answering visitors and even picking up your calls. One link, an AI suite that markets you 24/7 — <strong class="text-white">free forever</strong>, no card required.
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
                        <span class="font-bold text-white">120,000+</span><span class="text-gray-500">creators served</span>
                    </span>
                    <span class="flex items-center gap-2 text-gray-400">
                        <span class="w-1.5 h-1.5 rounded-full" style="background:var(--c2)"></span>
                        <span class="font-bold text-white">AI built-in,</span><span class="text-gray-500">not bolted on</span>
                    </span>
                    <span class="flex items-center gap-2 text-gray-400">
                        <span class="w-1.5 h-1.5 rounded-full" style="background:var(--c1)"></span>
                        <span class="font-bold text-white">Free forever</span>
                    </span>
                </div>
            </div>

            {{-- Orbital Zio visual --}}
            <div class="reveal rd-2 zio-orbit-wrap" aria-hidden="true">
                <div class="zio-orbit">
                    <span class="zio-glow"></span>
                    <span class="zio-ring zio-ring--outer"></span>
                    <span class="zio-ring zio-ring--inner"></span>

                    @php
                        $zioNodes = [
                            ['a' => 0,   'i' => 'fa-wand-magic-sparkles', 'c' => 'var(--c2)'],
                            ['a' => 45,  'i' => 'fa-chart-column',        'c' => 'var(--c2)'],
                            ['a' => 90,  'i' => 'fa-gauge-high',          'c' => 'var(--c1)'],
                            ['a' => 135, 'i' => 'fa-store',               'c' => '#10b981'],
                            ['a' => 180, 'i' => 'fa-phone',               'c' => 'var(--c4)'],
                            ['a' => 225, 'i' => 'fa-link',                'c' => 'var(--c2)'],
                            ['a' => 270, 'i' => 'fa-qrcode',              'c' => 'var(--c3)'],
                            ['a' => 315, 'i' => 'fa-code',                'c' => 'var(--c2)'],
                        ];
                    @endphp
                    <div class="zio-rotor">
                        @foreach($zioNodes as $n)
                            <div class="zio-node" style="--a:{{ $n['a'] }}deg">
                                <span class="zio-node-ic"><i class="fas {{ $n['i'] }}" style="color:{{ $n['c'] }}"></i></span>
                            </div>
                        @endforeach
                    </div>

                    <div class="zio-core">
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
            --size: clamp(280px, 38vw, 440px);
            --node: 54px;
            --r: calc(var(--size) / 2 - 26px);
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

        .zio-node {
            position: absolute; top: 50%; left: 50%;
            width: var(--node); height: var(--node);
            margin: calc(var(--node) / -2);
            transform: rotate(var(--a)) translate(0, calc(-1 * var(--r))) rotate(calc(-1 * var(--a)));
        }
        .zio-node-ic {
            display: flex; align-items: center; justify-content: center;
            width: 100%; height: 100%;
            border-radius: 16px;
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.12);
            backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            box-shadow: 0 10px 26px -14px rgba(10,12,30,.85);
            font-size: 18px;
            animation: zioSpinRev 46s linear infinite;
        }
        @keyframes zioSpinRev { to { transform: rotate(-360deg); } }

        .zio-core {
            position: absolute; top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            z-index: 3;
            display: flex; flex-direction: column; align-items: center;
            pointer-events: none;
        }
        .zio-mascot {
            width: calc(var(--size) * 0.42);
            height: auto;
            filter: drop-shadow(0 18px 36px rgba(61,107,255,.45));
            animation: zioFloat 6s ease-in-out infinite;
        }
        @keyframes zioFloat { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
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
            .zio-orbit { --size: clamp(340px, 52vw, 440px); }
        }

        /* ---- Light mode ---- */
        html.light-mode .zio-glow {
            background: radial-gradient(circle at 50% 45%, rgba(61,107,255,.18), rgba(110,97,255,.08) 45%, transparent 70%);
        }
        html.light-mode .zio-ring { border-color: rgba(37,66,199,.26); }
        html.light-mode .zio-ring--inner { border-color: rgba(37,66,199,.15); }
        html.light-mode .zio-node-ic {
            background: #ffffff; border-color: #e2e8f0;
            box-shadow: 0 10px 24px -14px rgba(15,23,42,.35);
        }
        html.light-mode .zio-core-label {
            background: #ffffff; border-color: #e2e8f0; color: #0f172a;
            box-shadow: 0 6px 16px -10px rgba(15,23,42,.3);
        }
        html.light-mode .zio-cta-ghost {
            border-color: #cbd5e1; color: #0f172a; background: #ffffff;
        }
        html.light-mode .zio-cta-ghost:hover { background: #f1f5f9; border-color: #94a3b8; }

        /* ---- Reduced motion: freeze the orbit (icons stay placed + upright) ---- */
        @media (prefers-reduced-motion: reduce) {
            .zio-rotor, .zio-node-ic, .zio-mascot { animation: none !important; }
        }
    </style>
</section>
