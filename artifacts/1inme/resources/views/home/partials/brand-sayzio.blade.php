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

    /* Faint typographic backdrop built from the brand words (desktop + mobile). */
    .bs-backing {
        position: absolute; inset: 0; z-index: 0; pointer-events: none; overflow: hidden;
        mask-image: radial-gradient(ellipse 80% 75% at 50% 45%, #000 5%, transparent 78%);
        -webkit-mask-image: radial-gradient(ellipse 80% 75% at 50% 45%, #000 5%, transparent 78%);
    }
    .bs-backing-word {
        position: absolute; white-space: nowrap; user-select: none;
        font-weight: 900; letter-spacing: -.03em; line-height: .82;
        font-size: clamp(4.5rem, 17vw, 15rem);
        color: rgba(255,255,255,.035);
        will-change: transform, opacity;
    }
    html.light-mode .bs-backing-word { color: rgba(15,23,42,.04); }
    .bs-backing-word--id  { top: 4%;  left: -3%;  animation: bsBackA 18s ease-in-out infinite; }
    .bs-backing-word--zio { bottom: 4%; right: -3%; animation: bsBackB 22s ease-in-out infinite; }
    @keyframes bsBackA {
        0%, 100% { transform: translate3d(0, 0, 0); opacity: 1; }
        50%      { transform: translate3d(2.5%, -1%, 0); opacity: .45; }
    }
    @keyframes bsBackB {
        0%, 100% { transform: translate3d(0, 0, 0); opacity: .45; }
        50%      { transform: translate3d(-2.5%, 1%, 0); opacity: 1; }
    }

    /* Brand cards */
    .bs-card {
        position: relative; overflow: hidden;
        border-radius: 1.75rem;
        border: 1px solid rgba(255,255,255,.14);
        background: linear-gradient(180deg, rgba(255,255,255,.07), rgba(255,255,255,.03));
        backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
        box-shadow:
            0 32px 80px -32px rgba(61,107,255,.55),
            0 10px 30px -18px rgba(0,0,0,.45),
            inset 0 1px 0 rgba(255,255,255,.12),
            inset 0 0 0 1px rgba(255,255,255,.02);
        transition: transform .4s cubic-bezier(.16,1,.3,1), box-shadow .4s, border-color .4s;
    }
    .bs-card::after {
        content: ""; position: absolute; inset: 0; border-radius: inherit; pointer-events: none;
        padding: 1px;
        background: linear-gradient(155deg, rgba(255,255,255,.28), rgba(255,255,255,0) 30%, rgba(255,255,255,0) 70%, rgba(255,255,255,.10));
        -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
        -webkit-mask-composite: xor; mask-composite: exclude;
    }
    .bs-card:hover {
        transform: translateY(-7px);
        border-color: rgba(255,255,255,.22);
        box-shadow:
            0 42px 100px -32px rgba(61,107,255,.7),
            0 14px 36px -18px rgba(0,0,0,.5),
            inset 0 1px 0 rgba(255,255,255,.16);
    }
    .bs-card::before {
        content: ""; position: absolute; width: 240px; height: 240px; border-radius: 9999px;
        filter: blur(60px); opacity: .35; pointer-events: none;
    }
    .bs-card--id::before    { top: -90px; left: -70px;  background: var(--c2); }
    .bs-card--zio::before   { bottom: -110px; right: -70px; background: #6e61ff; }
    html.light-mode .bs-card {
        background: linear-gradient(180deg, #ffffff, #fbfbfe);
        border-color: rgba(15,23,42,.09);
        box-shadow:
            0 26px 64px -32px rgba(61,107,255,.35),
            0 8px 22px -16px rgba(15,23,42,.10),
            inset 0 1px 0 rgba(255,255,255,.9);
    }
    html.light-mode .bs-card::after { background: linear-gradient(155deg, rgba(15,23,42,.06), rgba(15,23,42,0) 40%); }
    html.light-mode .bs-card:hover { box-shadow: 0 34px 84px -32px rgba(61,107,255,.42), 0 10px 26px -16px rgba(15,23,42,.14); }
    html.light-mode .bs-card::before { opacity: .22; }

    /* Solid brand wordmarks — no gradient. White for 1IN.ME, black for Sayzio.
       A mode-aware "plate" keeps each legible when the surrounding surface would
       otherwise swallow it (white text needs a dark plate in light mode; black
       text needs a light plate in dark mode). */
    .bs-word {
        display: inline-block; position: relative; line-height: 1;
        padding: .06em .22em; border-radius: .32em;
        transition: background-color .3s ease, box-shadow .3s ease, color .3s ease;
    }
    .bs-word--id { color: #fff; text-shadow: 0 2px 22px rgba(61,107,255,.4); }
    html.light-mode .bs-word--id {
        color: #fff; text-shadow: none;
        background: linear-gradient(150deg, #140a22, #241536);
        box-shadow: 0 10px 24px -12px rgba(20,10,34,.55), inset 0 1px 0 rgba(255,255,255,.10);
    }
    .bs-word--zio {
        color: #0a0a12;
        background: #ffffff;
        box-shadow: 0 10px 24px -12px rgba(0,0,0,.4), inset 0 1px 0 rgba(255,255,255,.7);
    }
    html.light-mode .bs-word--zio {
        color: #0a0a12; background: none; box-shadow: none; padding: 0;
    }

    /* Mode-aware emphasis text — white on dark, near-black on light */
    .bs-emph { color: #fff; }
    html.light-mode .bs-emph { color: #0f172a; }

    /* Mode-aware heading connector + body copy (legible white-on-dark / dark-on-light) */
    .bs-is-word { color: rgba(255,255,255,.55); }
    html.light-mode .bs-is-word { color: rgba(15,23,42,.55); }
    .bs-copy { color: rgba(255,255,255,.82); }
    html.light-mode .bs-copy { color: rgba(15,23,42,.78); }

    /* Logo holder tile — neutral / frosted so the colorful brand marks read cleanly */
    .bs-glyph {
        position: relative; flex-shrink: 0;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 1.1rem;
        border: 1px solid rgba(255,255,255,.10);
        background: rgba(255,255,255,.03);
        backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
        box-shadow: inset 0 1px 0 rgba(255,255,255,.10), 0 12px 28px -22px rgba(61,107,255,.30);
    }
    .bs-glyph::after {
        content: ""; position: absolute; inset: 0; border-radius: inherit; pointer-events: none;
        background: linear-gradient(135deg, rgba(61,107,255,.08), transparent 60%);
    }
    .bs-glyph-zio::after { background: linear-gradient(135deg, rgba(110,97,255,.10), transparent 60%); }
    .bs-glyph > img { position: relative; object-fit: contain; user-select: none; -webkit-user-select: none; }
    html.light-mode .bs-glyph {
        background: rgba(255,255,255,.55); border-color: rgba(15,23,42,.08);
        box-shadow: inset 0 1px 0 rgba(255,255,255,.5), 0 12px 28px -24px rgba(61,107,255,.18);
    }

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
        background: linear-gradient(135deg, rgba(61,107,255,.82), rgba(110,97,255,.82));
        color: #fff; font-weight: 800; letter-spacing: .04em;
        box-shadow: 0 10px 26px -14px rgba(61,107,255,.45);
    }
    .bs-is-pill::before {
        content: ""; position: absolute; inset: -5px; border-radius: inherit; z-index: -1;
        background: conic-gradient(from 0deg, #3d6bff, #6e61ff, #1bd4d9, #3d6bff);
        filter: blur(11px); opacity: .32; animation: spinSlow 7s linear infinite;
    }

    /* Pillar chips */
    .bs-pillar {
        display: flex; align-items: center; gap: .9rem;
        padding: 1.1rem 1.25rem; border-radius: 1.15rem;
        border: 1px solid rgba(255,255,255,.10);
        background: linear-gradient(180deg, rgba(255,255,255,.06), rgba(255,255,255,.02));
        box-shadow: inset 0 1px 0 rgba(255,255,255,.06), 0 14px 30px -26px rgba(0,0,0,.5);
        transition: transform .3s ease, box-shadow .3s ease, background-color .3s ease, border-color .3s ease;
    }
    .bs-pillar:hover {
        transform: translateY(-4px);
        background: linear-gradient(180deg, rgba(255,255,255,.10), rgba(255,255,255,.03));
        border-color: rgba(255,255,255,.18);
        box-shadow: 0 18px 44px -22px rgba(61,107,255,.55), inset 0 1px 0 rgba(255,255,255,.10);
    }
    html.light-mode .bs-pillar {
        background: linear-gradient(180deg, #ffffff, #fbfbfe);
        border-color: rgba(15,23,42,.09);
        box-shadow: 0 10px 22px -18px rgba(15,23,42,.12);
    }
    html.light-mode .bs-pillar:hover { background: #ffffff; border-color: rgba(15,23,42,.14); box-shadow: 0 18px 44px -24px rgba(61,107,255,.30); }
    .bs-pillar-ico {
        flex-shrink: 0; width: 42px; height: 42px; border-radius: .85rem;
        display: inline-flex; align-items: center; justify-content: center; font-size: 1rem;
        box-shadow: inset 0 1px 0 rgba(255,255,255,.15);
    }

    /* Mascot drift (gentler, section-local variant of the float helpers). */
    .bs-mascot { animation: bsDrift 7.5s ease-in-out infinite; will-change: transform; }
    @keyframes bsDrift { 0%,100% { transform: translateY(0) rotate(-1.5deg); } 50% { transform: translateY(-8px) rotate(1.5deg); } }
    /* Animated clip shows by default; the static still is the reduced-motion fallback. */
    .bs-mascot-video { display: block; object-fit: contain; aspect-ratio: 1 / 1; }
    .bs-mascot-fallback { display: none; }
    /* Animated transparent WebP fallback — revealed by the hero's alpha guard for
       browsers that decode the WebM but ignore its alpha (Safari/iOS). Static PNG
       fallback stays reserved for the reduced-motion path below. */
    .bs-mascot-anim { display: none; }

    @media (prefers-reduced-motion: reduce) {
        .bs-energy::after, .bs-is-pill::before, .bs-mascot,
        .bs-backing-word--id, .bs-backing-word--zio {
            animation: none !important;
        }
        .bs-energy::after { background: linear-gradient(90deg, var(--c1), #6e61ff, var(--c3)) !important; }
        .bs-backing-word--zio { opacity: .7; }
        .bs-mascot-video { display: none !important; }
        .bs-mascot-fallback { display: block !important; }
    }
</style>

<section class="bs-section relative py-20 lg:py-28 overflow-hidden" aria-labelledby="bs-h">
    <div class="bs-backing" aria-hidden="true">
        <span class="bs-backing-word bs-backing-word--id">1INME</span>
        <span class="bs-backing-word bs-backing-word--zio">SAYZIO</span>
    </div>

    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-10 xl:px-12">
        {{-- Eyebrow + heading --}}
        <div class="text-center max-w-3xl mx-auto mb-16 lg:mb-24">
            <div data-anim="fade-up" class="inline-flex items-center gap-2 px-4 py-1.5 glass rounded-full text-xs font-semibold mb-7">
                <span class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full rounded-full" style="background:var(--c2)"></span>
                    <span class="ring-pulse" style="inset:0;background:var(--c2);"></span>
                </span>
                <span class="grad-text">The platform behind your links</span>
            </div>
            <h2 id="bs-h" data-anim="fade-up" class="text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight leading-[1.12]">
                <span class="bs-word bs-word--id">1IN.ME</span>
                <span class="bs-is-word font-semibold italic px-1">is</span>
                <span class="bs-word bs-word--zio">Sayzio</span>
            </h2>
            <p data-anim="fade-up" class="bs-copy mt-6 text-lg leading-relaxed">
                <strong class="bs-emph">1IN.ME</strong> is your digital identity, unified — and it runs on
                <strong class="bs-emph">Sayzio</strong>, the smart, scalable, seamless platform powering every link, page and QR.
            </p>
        </div>

        {{-- Twin brand cards joined by an animated energy line + "is" pill --}}
        <div class="relative grid grid-cols-1 lg:grid-cols-[1fr_auto_1fr] gap-7 lg:gap-0 items-stretch">
            {{-- 1IN.ME card --}}
            <div data-anim="fade-right" class="bs-card bs-card--id p-7 sm:p-9 lg:mr-12">
                <div class="relative flex items-center gap-4 sm:gap-5">
                    <span class="bs-glyph float-a w-16 h-16 sm:w-20 sm:h-20">
                        <img src="{{ asset('branding/1inme-mark.png') }}" alt="1IN.ME logo" width="72" height="56"
                             class="w-11 h-11 sm:w-14 sm:h-14 drop-shadow-[0_8px_22px_rgba(61,107,255,.45)]" loading="lazy" decoding="async">
                    </span>
                    <div class="min-w-0">
                        <div class="text-3xl sm:text-4xl font-black tracking-tight"><span class="bs-word bs-word--id">1IN.ME</span></div>
                        <div class="mt-1.5 text-sm sm:text-base text-gray-300">Your Digital Identity, <span class="font-semibold" style="color:var(--c4)">Unified.</span></div>
                    </div>
                </div>
                <div class="relative mt-7 pt-7 border-t border-white/10">
                    <div class="text-xs font-bold uppercase tracking-[.18em] mb-1.5" style="color:var(--c2)">All-in-one platform</div>
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
            <div data-anim="fade-left" class="bs-card bs-card--zio p-7 sm:p-9 lg:ml-12">
                <div class="relative flex items-center gap-4 sm:gap-5">
                    <span class="bs-glyph bs-glyph-zio w-16 h-16 sm:w-20 sm:h-20">
                        {{-- Animated transparent mascot clip (matches the hero). Autoplays
                             muted + inline, no controls/audio, loops. The transparent still
                             PNG is the poster (shown while loading or if the video can't
                             play). Under prefers-reduced-motion the video is hidden and the
                             static .bs-mascot-fallback image is shown instead (see <style>). --}}
                        <video class="bs-mascot bs-mascot-video w-12 h-12 sm:w-14 sm:h-14 drop-shadow-[0_10px_26px_rgba(110,97,255,.55)]"
                               aria-label="Zio, the Sayzio mascot"
                               width="64" height="64"
                               autoplay loop muted playsinline disablepictureinpicture
                               preload="metadata"
                               poster="{{ asset('branding/sayzio-mascot-still.png') }}">
                            <source src="{{ asset('branding/sayzio-mascot.webm') }}" type="video/webm">
                        </video>
                        <img src="{{ asset('branding/sayzio-mascot-still.png') }}" alt="Zio, the Sayzio mascot" width="64" height="64"
                             class="bs-mascot bs-mascot-fallback w-12 h-12 sm:w-14 sm:h-14 drop-shadow-[0_10px_26px_rgba(110,97,255,.55)]" loading="lazy" decoding="async">
                        {{-- Animated transparent WebP, revealed by the hero alpha guard
                             for browsers that decode the WebM but ignore its alpha
                             (Safari/iOS). data-src keeps it from downloading otherwise. --}}
                        <img data-src="{{ asset('branding/sayzio-mascot.webp') }}" alt="Zio, the Sayzio mascot" width="64" height="64"
                             class="bs-mascot bs-mascot-anim w-12 h-12 sm:w-14 sm:h-14 drop-shadow-[0_10px_26px_rgba(110,97,255,.55)]" decoding="async">
                    </span>
                    <div class="min-w-0">
                        <div class="text-3xl sm:text-4xl font-black tracking-tight"><span class="bs-word bs-word--zio">Sayzio</span></div>
                        <div class="mt-1.5 text-sm sm:text-base text-gray-300">
                            <span class="font-semibold" style="color:var(--c2)">Smart.</span>
                            <span class="font-semibold" style="color:var(--c1)">Scalable.</span>
                            <span class="font-semibold" style="color:var(--c3)">Seamless.</span>
                        </div>
                    </div>
                </div>
                <div class="relative mt-7 pt-7 border-t border-white/10">
                    <div class="text-xs font-bold uppercase tracking-[.18em] mb-1.5" style="color:#6e61ff">The power behind every experience</div>
                    <p class="text-sm text-gray-400 leading-relaxed">The engine doing the heavy lifting — analytics, AI, automation and rock-solid delivery at any scale.</p>
                </div>
            </div>
        </div>

        {{-- "Powered by Sayzio" connector --}}
        <div data-anim="fade-up" class="mt-12 lg:mt-14 flex items-center justify-center">
            <div class="inline-flex items-center gap-3 px-5 py-2.5 glass rounded-full">
                <span class="text-xs font-bold uppercase tracking-[.22em] text-gray-400">Powered by</span>
                <span class="text-base font-extrabold"><span class="bs-word bs-word--zio">Sayzio</span></span>
            </div>
        </div>

        {{-- Four pillars --}}
        <div data-anim="fade-up" data-stagger class="mt-14 lg:mt-20 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
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
                        <div class="text-base font-extrabold bs-emph leading-tight">{{ $p[3] }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
