{{--
    "1IN.ME is Sayzio" brand section — sits as section 2, directly after the hero
    and before the marquee strip. Communicates the brand relationship: 1IN.ME
    (your unified digital identity) is powered by Sayzio (the platform engine).

    Reuses the homepage's existing design system (glass, reveal, rd-*, grad-bar,
    float-a/b/c, btn-bounce/btn-glow, --c1..--c5) so dark/light modes, the bouncy
    reveal and reduced-motion handling all carry over. Section-specific motion
    (flowing energy line, pulsing connector, drifting mascot) is scoped with the
    `bs-` prefix and gated behind prefers-reduced-motion below.
--}}
<style>
    /* ===== "1IN.ME is Sayzio" section (bs- = brand-sayzio) ===== */
    .bs-section { position: relative; }

    /* Faint reference visual washed into the backdrop (desktop only). */
    .bs-backing {
        position: absolute; inset: 0; z-index: 0; pointer-events: none;
        background-size: cover; background-position: center;
        opacity: .05; filter: saturate(1.1);
        mask-image: radial-gradient(ellipse 70% 70% at 50% 45%, #000 10%, transparent 75%);
        -webkit-mask-image: radial-gradient(ellipse 70% 70% at 50% 45%, #000 10%, transparent 75%);
    }
    html.light-mode .bs-backing { opacity: .04; }

    /* Brand cards */
    .bs-card {
        position: relative; overflow: hidden;
        border-radius: 1.5rem;
        border: 1px solid rgba(255,255,255,.12);
        background: rgba(255,255,255,.05);
        backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px);
        box-shadow: 0 28px 70px -34px rgba(61,107,255,.55), inset 0 1px 0 rgba(255,255,255,.08);
        transition: transform .4s cubic-bezier(.16,1,.3,1), box-shadow .4s;
    }
    .bs-card:hover { transform: translateY(-6px); box-shadow: 0 36px 90px -34px rgba(61,107,255,.7), inset 0 1px 0 rgba(255,255,255,.10); }
    .bs-card::before {
        content: ""; position: absolute; width: 240px; height: 240px; border-radius: 9999px;
        filter: blur(60px); opacity: .35; pointer-events: none;
    }
    .bs-card--id::before    { top: -90px; left: -70px;  background: var(--c2); }
    .bs-card--zio::before   { bottom: -110px; right: -70px; background: #6e61ff; }
    html.light-mode .bs-card { background: #ffffff; border-color: rgba(15,23,42,.10); box-shadow: 0 24px 60px -34px rgba(61,107,255,.40); }
    html.light-mode .bs-card::before { opacity: .22; }

    /* Gradient wordmarks */
    .bs-wordmark {
        background: linear-gradient(95deg, #3d6bff, #6e61ff 55%, #1bd4d9);
        -webkit-background-clip: text; background-clip: text; color: transparent;
        background-size: 200% 100%; animation: bsGrad 6s ease-in-out infinite;
    }
    .bs-wordmark--zio { background: linear-gradient(95deg, #6e61ff, #e94e8c 60%, #ff8a3c); -webkit-background-clip: text; background-clip: text; }
    @keyframes bsGrad { 0%,100% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } }

    /* Logo glyph tile */
    .bs-glyph {
        position: relative; flex-shrink: 0;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 1.1rem;
        background: linear-gradient(135deg, #3d6bff, #6e61ff);
        box-shadow: 0 16px 38px -14px rgba(61,107,255,.7), inset 0 1px 0 rgba(255,255,255,.25);
    }
    .bs-glyph-zio { background: linear-gradient(135deg, #6e61ff, #e94e8c); box-shadow: 0 16px 38px -14px rgba(110,97,255,.7), inset 0 1px 0 rgba(255,255,255,.25); }

    /* Animated energy line between the two cards (desktop horizontal). */
    .bs-energy { position: relative; height: 4px; border-radius: 9999px; overflow: hidden; background: rgba(255,255,255,.08); }
    html.light-mode .bs-energy { background: rgba(15,23,42,.08); }
    .bs-energy::after {
        content: ""; position: absolute; inset: 0;
        background: linear-gradient(90deg, transparent, var(--c1), #6e61ff, var(--c3), transparent);
        background-size: 220% 100%;
        animation: bsFlow 2.6s linear infinite;
    }
    @keyframes bsFlow { 0% { background-position: 120% 0; } 100% { background-position: -120% 0; } }

    /* "is" connector pill */
    .bs-is-pill {
        position: relative; z-index: 2;
        display: inline-flex; align-items: center; justify-content: center;
        width: 64px; height: 64px; border-radius: 9999px;
        background: linear-gradient(135deg, #3d6bff, #6e61ff);
        color: #fff; font-weight: 800; letter-spacing: .04em;
        box-shadow: 0 14px 40px -10px rgba(61,107,255,.75);
    }
    .bs-is-pill::before {
        content: ""; position: absolute; inset: -6px; border-radius: inherit; z-index: -1;
        background: conic-gradient(from 0deg, #3d6bff, #6e61ff, #1bd4d9, #3d6bff);
        filter: blur(10px); opacity: .8; animation: spinSlow 7s linear infinite;
    }

    /* "Powered by" connector lightning halo */
    .bs-bolt {
        position: relative;
        display: inline-flex; align-items: center; justify-content: center;
        width: 54px; height: 54px; border-radius: 9999px;
        background: linear-gradient(135deg, #6e61ff, #3d6bff);
        color: #fff; box-shadow: 0 12px 34px -10px rgba(110,97,255,.8);
    }
    .bs-bolt::after {
        content: ""; position: absolute; inset: 0; border-radius: inherit;
        box-shadow: 0 0 0 0 rgba(110,97,255,.55);
        animation: bsBolt 2.4s ease-out infinite;
    }
    @keyframes bsBolt {
        0%   { box-shadow: 0 0 0 0 rgba(110,97,255,.55); }
        70%  { box-shadow: 0 0 0 18px rgba(110,97,255,0); }
        100% { box-shadow: 0 0 0 0 rgba(110,97,255,0); }
    }

    /* Pillar chips */
    .bs-pillar {
        display: flex; align-items: center; gap: .75rem;
        padding: .85rem 1rem; border-radius: 1rem;
        border: 1px solid rgba(255,255,255,.10);
        background: rgba(255,255,255,.04);
        transition: transform .3s ease, box-shadow .3s ease, background-color .3s ease;
    }
    .bs-pillar:hover { transform: translateY(-4px); background: rgba(255,255,255,.07); box-shadow: 0 16px 40px -22px rgba(61,107,255,.55); }
    html.light-mode .bs-pillar { background: #ffffff; border-color: rgba(15,23,42,.10); }
    html.light-mode .bs-pillar:hover { background: #f9fafb; box-shadow: 0 16px 40px -24px rgba(61,107,255,.30); }
    .bs-pillar-ico {
        flex-shrink: 0; width: 40px; height: 40px; border-radius: .8rem;
        display: inline-flex; align-items: center; justify-content: center; font-size: 1rem;
    }

    /* Mascot drift (gentler, section-local variant of the float helpers). */
    .bs-mascot { animation: bsDrift 7.5s ease-in-out infinite; will-change: transform; }
    @keyframes bsDrift { 0%,100% { transform: translateY(0) rotate(-1.5deg); } 50% { transform: translateY(-12px) rotate(1.5deg); } }

    @media (prefers-reduced-motion: reduce) {
        .bs-wordmark, .bs-energy::after, .bs-is-pill::before, .bs-bolt::after, .bs-mascot {
            animation: none !important;
        }
        .bs-wordmark { background-position: 0 50% !important; }
        .bs-energy::after { background: linear-gradient(90deg, var(--c1), #6e61ff, var(--c3)) !important; }
    }
</style>

<section class="bs-section relative py-20 lg:py-28 overflow-hidden" aria-labelledby="bs-h">
    <div class="bs-backing" style="background-image:url('{{ asset('images/marketing/1inme-is-sayzio.png') }}')" aria-hidden="true"></div>

    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-10 xl:px-12">
        {{-- Eyebrow + heading --}}
        <div class="text-center max-w-3xl mx-auto mb-14 lg:mb-20">
            <div data-anim="fade-up" class="inline-flex items-center gap-2 px-4 py-1.5 glass rounded-full text-xs font-semibold mb-6">
                <span class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full rounded-full" style="background:var(--c2)"></span>
                    <span class="ring-pulse" style="inset:0;background:var(--c2);"></span>
                </span>
                <span class="grad-text">The platform behind your links</span>
            </div>
            <h2 id="bs-h" data-anim="fade-up" class="text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight leading-[1.08]">
                <span class="bs-wordmark">1IN.ME</span>
                <span class="text-gray-400 font-semibold italic px-1">is</span>
                <span class="bs-wordmark bs-wordmark--zio">Sayzio</span>
            </h2>
            <p data-anim="fade-up" class="mt-5 text-lg text-gray-400 leading-relaxed">
                <strong class="text-white">1IN.ME</strong> is your digital identity, unified — and it runs on
                <strong class="text-white">Sayzio</strong>, the smart, scalable, seamless platform powering every link, page and QR.
            </p>
        </div>

        {{-- Twin brand cards joined by an animated energy line + "is" pill --}}
        <div class="relative grid grid-cols-1 lg:grid-cols-[1fr_auto_1fr] gap-6 lg:gap-0 items-stretch">
            {{-- 1IN.ME card --}}
            <div data-anim="fade-right" class="bs-card bs-card--id p-6 sm:p-8 lg:mr-12">
                <div class="relative flex items-center gap-4 sm:gap-5">
                    <span class="bs-glyph float-a w-16 h-16 sm:w-20 sm:h-20 text-3xl sm:text-4xl font-black text-white">
                        <i class="fas fa-fingerprint"></i>
                    </span>
                    <div class="min-w-0">
                        <div class="text-3xl sm:text-4xl font-black tracking-tight"><span class="bs-wordmark">1IN.ME</span></div>
                        <div class="mt-1 text-sm sm:text-base text-gray-300">Your Digital Identity, <span class="font-semibold" style="color:var(--c4)">Unified.</span></div>
                    </div>
                </div>
                <div class="relative mt-6 pt-6 border-t border-white/10">
                    <div class="text-xs font-bold uppercase tracking-[.18em] mb-1" style="color:var(--c2)">All-in-one platform</div>
                    <p class="text-sm text-gray-400 leading-relaxed">One link for everything — bio pages, short links, QR codes, forms and more, all under your handle.</p>
                </div>
            </div>

            {{-- Center connector (energy line + "is" pill) --}}
            <div class="relative flex lg:flex-col items-center justify-center gap-0 lg:px-2" aria-hidden="true">
                <div class="bs-energy hidden lg:block absolute top-1/2 -translate-y-1/2 left-0 right-0"></div>
                <div class="bs-energy lg:hidden h-1 w-24 rotate-0"></div>
                <span class="bs-is-pill float-c text-lg">is</span>
            </div>

            {{-- Sayzio card --}}
            <div data-anim="fade-left" class="bs-card bs-card--zio p-6 sm:p-8 lg:ml-12">
                <div class="relative flex items-center gap-4 sm:gap-5">
                    <img src="{{ asset('branding/zio-bot.png') }}" alt="Zio, the Sayzio mascot" width="80" height="80"
                         class="bs-mascot w-16 h-16 sm:w-20 sm:h-20 object-contain drop-shadow-[0_14px_30px_rgba(110,97,255,.6)]" loading="lazy" decoding="async">
                    <div class="min-w-0">
                        <div class="text-3xl sm:text-4xl font-black tracking-tight"><span class="bs-wordmark bs-wordmark--zio">Sayzio</span></div>
                        <div class="mt-1 text-sm sm:text-base text-gray-300">
                            <span class="font-semibold" style="color:var(--c2)">Smart.</span>
                            <span class="font-semibold" style="color:var(--c1)">Scalable.</span>
                            <span class="font-semibold" style="color:var(--c3)">Seamless.</span>
                        </div>
                    </div>
                </div>
                <div class="relative mt-6 pt-6 border-t border-white/10">
                    <div class="text-xs font-bold uppercase tracking-[.18em] mb-1" style="color:#6e61ff">The power behind every experience</div>
                    <p class="text-sm text-gray-400 leading-relaxed">The engine doing the heavy lifting — analytics, AI, automation and rock-solid delivery at any scale.</p>
                </div>
            </div>
        </div>

        {{-- "Powered by Sayzio" connector --}}
        <div data-anim="fade-up" class="mt-10 lg:mt-12 flex items-center justify-center">
            <div class="inline-flex items-center gap-3 px-5 py-2.5 glass rounded-full">
                <span class="bs-bolt w-9 h-9 text-sm"><i class="fas fa-bolt"></i></span>
                <span class="text-xs font-bold uppercase tracking-[.22em] text-gray-400">Powered by</span>
                <span class="text-base font-extrabold"><span class="bs-wordmark bs-wordmark--zio">Sayzio</span></span>
            </div>
        </div>

        {{-- Four pillars --}}
        <div data-anim="fade-up" data-stagger class="mt-12 lg:mt-16 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach([
                ['fa-rocket',        '#3d6bff', 'Built for',      'Performance'],
                ['fa-shield-halved', '#1bd4d9', 'Engineered for', 'Reliability'],
                ['fa-cubes',         '#e94e8c', 'Designed for',   'Scalability'],
                ['fa-lightbulb',     '#ff8a3c', 'Driven by',      'Innovation'],
            ] as $p)
                <div class="bs-pillar">
                    <span class="bs-pillar-ico" style="background:{{ $p[1] }}1f;color:{{ $p[1] }}"><i class="fas {{ $p[0] }}"></i></span>
                    <div class="min-w-0">
                        <div class="text-[11px] font-bold uppercase tracking-wider text-gray-400">{{ $p[2] }}</div>
                        <div class="text-base font-extrabold text-white leading-tight">{{ $p[3] }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
