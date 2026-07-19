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
        <div class="zio-hero-grid grid grid-cols-1 gap-y-16 lg:gap-x-14 xl:gap-x-20 lg:items-center">

            {{-- Copy column (sits on the RIGHT at ≥lg via .zio-hero-copy order) --}}
            <div class="zio-hero-copy text-center lg:text-left lg:max-w-[600px]">
                <div class="reveal inline-flex items-center gap-2 px-4 py-1.5 glass rounded-full text-xs font-semibold mb-8">
                    <i class="fas fa-wand-magic-sparkles text-[11px]" style="color:var(--c2)"></i>
                    <span class="grad-text">Short links · Bio pages · QR codes</span>
                </div>

                <h1 id="hero-h" class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold leading-[1.08] tracking-tight mb-6">
                    Every link, page and QR code you need — <span class="grad-text">one place</span>
                </h1>

                <p class="reveal rd-2 text-lg sm:text-xl text-gray-400 max-w-xl mx-auto lg:mx-0 mb-9 leading-relaxed">
                    Create short links, bio pages and QR codes in seconds — then let <strong class="text-white">Zio</strong>, your built-in AI, build, grow and run them <strong class="text-white">24/7. Free forever</strong>, no card required.
                </p>

                @guest
                    @php
                        // Canonical brand host for the "claim your link" prefix — read
                        // from the platform's primary domain rather than hardcoded so a
                        // rebrand carries through automatically.
                        $claimHost = \App\Modules\Common\Support\PlatformHosts::PLATFORM_DOMAINS[0] ?? 'sayzio.app';
                    @endphp
                    {{-- Claim-your-link control: a higher-intent entry point than the
                         generic CTA. The handle the visitor types is carried into the
                         register modal (via the open-auth event) and reserved as their
                         @handle right after sign-up. Empty submit just opens register.
                         Signup-oriented, so guests only. --}}
                    <form class="zio-claim-form reveal rd-3" onsubmit="return window.zioClaimSubmit(event)" aria-label="Claim your link"
                          data-handle-check-url="{{ route('site.handle.available') }}">
                        <label for="zio-claim-input" class="zio-claim-label">Claim your link and pick your handle</label>
                        <div class="zio-claim" id="zio-claim-box">
                            <span class="zio-claim-prefix" aria-hidden="true">{{ $claimHost }}/</span>
                            <input id="zio-claim-input" name="desired_handle" type="text"
                                   autocomplete="off" autocapitalize="none" autocorrect="off" spellcheck="false"
                                   maxlength="30" placeholder="yourname" class="zio-claim-input"
                                   aria-describedby="zio-claim-status">
                            <span class="zio-claim-mark" id="zio-claim-mark" aria-hidden="true"></span>
                            <button type="submit" class="zio-claim-btn btn-bounce btn-cta">
                                Claim your link <i class="fas fa-arrow-right text-xs"></i>
                            </button>
                        </div>
                        {{-- Live verdict + suggestions. role=status keeps it announced
                             to screen readers; the message text is driven by the
                             public site.handle.available endpoint (mirrors submit-time
                             handle rules). --}}
                        <p id="zio-claim-status" class="zio-claim-status" role="status" aria-live="polite" data-state=""></p>
                        <div id="zio-claim-suggest" class="zio-claim-suggest" hidden>
                            <span class="zio-claim-suggest-label">Try one of these:</span>
                            <span id="zio-claim-suggest-list" class="zio-claim-suggest-list"></span>
                        </div>
                    </form>

                    <div class="reveal rd-3 flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4 justify-center lg:justify-start">
                        <a href="#ai-zone" class="zio-cta-ghost inline-flex items-center justify-center gap-2 px-7 py-4 rounded-full text-base font-bold whitespace-nowrap">
                            Meet Zio, your AI
                        </a>
                    </div>
                @else
                    {{-- Already signed in: no signup CTAs. Send them straight to
                         their dashboard instead of asking them to claim a handle /
                         "start free" again. --}}
                    <div class="reveal rd-3 flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4 justify-center lg:justify-start">
                        <a href="{{ route('user.dashboard') }}" class="btn-bounce btn-glow inline-flex items-center justify-center gap-2 px-8 py-4 grad-bar text-white rounded-full text-base font-bold whitespace-nowrap shrink-0">
                            Go to your dashboard <i class="fas fa-arrow-right text-sm"></i>
                        </a>
                        <a href="#ai-zone" class="zio-cta-ghost inline-flex items-center justify-center gap-2 px-7 py-4 rounded-full text-base font-bold whitespace-nowrap">
                            Meet Zio, your AI
                        </a>
                    </div>
                @endguest

                <div class="reveal rd-4 flex flex-wrap items-center gap-x-6 gap-y-3 mt-12 justify-center lg:justify-start text-sm">
                    <span class="flex items-center gap-2 text-gray-400">
                        <span class="w-1.5 h-1.5 rounded-full" style="background:#1ed760"></span>
                        <span class="font-bold text-white">375,000+</span><span class="text-gray-500">creators</span>
                    </span>
                    <span class="flex items-center gap-2 text-gray-400">
                        <span class="w-1.5 h-1.5 rounded-full pulse-dot" style="background:var(--c2)"></span>
                        <span class="font-bold text-white">Links, pages</span><span class="text-gray-500">&amp; QR codes</span>
                    </span>
                    <span class="flex items-center gap-2 text-gray-400">
                        <span class="w-1.5 h-1.5 rounded-full" style="background:var(--c1)"></span>
                        <span class="font-bold text-white">Free forever</span><span class="text-gray-500">· no card</span>
                    </span>
                </div>
            </div>

            {{-- Orbital Zio visual (sits on the LEFT at ≥lg via .zio-hero-visual order) --}}
            <div class="reveal rd-2 zio-orbit-wrap zio-hero-visual">
                <div class="zio-orbit" x-data="{ open: null }" @keydown.escape.window="open = null" @click.outside="open = null" :class="{ 'zio-paused': open !== null }">
                    <span class="zio-glow" aria-hidden="true"></span>
                    <span class="zio-pulse zio-pulse--1" aria-hidden="true"></span>
                    <span class="zio-pulse zio-pulse--2" aria-hidden="true"></span>
                    <span class="zio-ring zio-ring--r1" aria-hidden="true"></span>
                    <span class="zio-ring zio-ring--r2" aria-hidden="true"></span>
                    <span class="zio-ring zio-ring--r3" aria-hidden="true"></span>

                    @php
                        // Feature nodes split across three concentric rings. Zio's direct AI
                        // powers sit on the inner ring; the wider feature universe fans out
                        // across the middle and outer rings. Each ring rotates independently
                        // (its own radius, speed and direction — see CSS). Angles are evenly
                        // spaced within each ring so tiles never crowd. `img` files live in
                        // public/images/zio-nodes/.
                        // Each node carries a punchy title (t), an engaging one-line
                        // detail (d) and a small stat/benefit chip (tag) shown in the
                        // popover + the <noscript> fallback.
                        $zioRings = [
                            // Inner ring (4) — Zio's core AI brain.
                            ['cls' => 'r1', 'nodes' => [
                                ['a' => 0,   'img' => 'ai.png',        'c' => 'var(--c2)', 't' => 'AI Page Builder', 'd' => 'Describe your idea in a sentence and Zio assembles a complete, on-brand page for you.', 'tag' => 'Live in ~30s'],
                                ['a' => 90,  'img' => 'growth.png',    'c' => 'var(--c1)', 't' => 'AI Growth Coach',     'd' => "Zio reads your stats, flags what's working and hands you the next move to grow.", 'tag' => 'Weekly tips'],
                                ['a' => 180, 'img' => 'calls.png',     'c' => 'var(--c4)', 't' => 'AI Phone',         'd' => 'Zio answers your calls and turns every caller into a captured lead while you focus.', 'tag' => '24/7 answer'],
                                ['a' => 270, 'img' => 'analytics.png', 'c' => 'var(--c2)', 't' => 'Live Analytics',   'd' => 'Watch every click, scan and visit land in real time on a live world map.', 'tag' => 'Real-time'],
                            ]],
                            // Middle ring (6) — everyday building & growth tools.
                            ['cls' => 'r2', 'nodes' => [
                                ['a' => 30,  'img' => 'link.png',      'c' => 'var(--c2)', 't' => 'Smart Links',      'd' => 'Turn long URLs into branded short links you can track, tag and retarget.', 'tag' => 'Branded'],
                                ['a' => 90,  'img' => 'qr.png',        'c' => 'var(--c3)', 't' => 'QR Studio',        'd' => 'Design on-brand codes with custom eyes and frames that track every single scan.', 'tag' => '16 types'],
                                ['a' => 150, 'img' => 'store.png',     'c' => '#10b981',   't' => 'Built-in Store',   'd' => 'Sell products and take payments straight from your link. Keep every cent.', 'tag' => '0% fees'],
                                ['a' => 210, 'img' => 'forms.png',     'c' => 'var(--c2)', 't' => 'Forms',           'd' => 'Collect leads, bookings and payments with 21 customizable field types.', 'tag' => '21 fields'],
                                ['a' => 270, 'img' => 'audience.png',  'c' => 'var(--c1)', 't' => 'Subscribers',     'd' => 'Grow an email and WhatsApp audience you actually own, then message them anytime.', 'tag' => 'You own it'],
                                ['a' => 330, 'img' => 'social.png',    'c' => 'var(--c3)', 't' => 'Social Proof',     'd' => 'Live popups surface real activity that nudges new visitors to take action.', 'tag' => '7 widgets'],
                            ]],
                            // Outer ring (7) — the wider feature universe + a new add-on.
                            ['cls' => 'r3', 'nodes' => [
                                ['a' => 0,   'img' => 'code.png',      'c' => 'var(--c2)', 't' => 'Developer API',    'd' => 'Build anything on Sayzio with a full, token-secured REST API.', 'tag' => 'REST API'],
                                ['a' => 51,  'img' => 'reviews.png',   'c' => 'var(--c3)', 't' => 'Reviews',         'd' => 'Collect native reviews and pull in Google & Trustpilot ratings to build instant trust.', 'tag' => 'Google + more'],
                                ['a' => 103, 'img' => 'menu.png',      'c' => '#10b981',   't' => 'Restaurant Menu', 'd' => 'QR menus with live ordering that sends tickets straight to your kitchen staff.', 'tag' => 'Live orders'],
                                ['a' => 154, 'img' => 'resume.png',    'c' => 'var(--c2)', 't' => 'Resume',          'd' => 'Build a polished, shareable resume and portfolio with AI tailoring and PDF export.', 'tag' => 'AI-tailored'],
                                ['a' => 206, 'img' => 'calendar.png',  'c' => 'var(--c1)', 't' => 'Calendar',        'd' => 'Share events visitors can follow and book, synced to Google Calendar.', 'tag' => 'Auto-sync'],
                                ['a' => 257, 'img' => 'vcard.png',     'c' => 'var(--c2)', 't' => 'Digital Cards',    'd' => 'Share a tappable vCard that saves straight to any phone in one tap.', 'tag' => 'One tap'],
                                ['a' => 309, 'img' => 'domain.png',    'c' => 'var(--c4)', 't' => 'Custom Domain',    'd' => 'Put your whole universe on your own domain for a fully branded presence.', 'tag' => 'Your brand'],
                            ]],
                        ];
                        // Flat list (in ring order) for the <noscript> fallback below.
                        $zioNodes = array_merge(...array_map(fn ($r) => $r['nodes'], $zioRings));
                        $zioIdx = 0;
                    @endphp

                    @foreach($zioRings as $ring)
                        <div class="zio-rotor zio-rotor--{{ $ring['cls'] }}">
                            @foreach($ring['nodes'] as $n)
                                @php $i = $zioIdx++; @endphp
                                <div class="zio-node"
                                     style="--a:{{ $n['a'] }}deg; --d:{{ 0.5 + $i * 0.06 }}s; --ac:{{ $n['c'] }}"
                                     :class="{ 'zio-node--on': open === {{ $i }} }">
                                    <div class="zio-node-ic">
                                        <button type="button"
                                                class="zio-node-btn"
                                                @click="open = (open === {{ $i }} ? null : {{ $i }})"
                                                :aria-expanded="open === {{ $i }}"
                                                aria-label="{{ $n['t'] }}: {{ $n['d'] }}">
                                            <img class="zio-node-thumb" src="{{ asset('images/zio-nodes/' . $n['img']) }}" alt="" width="58" height="58" loading="lazy" decoding="async">
                                        </button>
                                        <div class="zio-pop" x-show="open === {{ $i }}" x-cloak x-transition.opacity.scale.95 @click.stop role="dialog" aria-label="{{ $n['t'] }}">
                                            <span class="zio-pop-title">{{ $n['t'] }}</span>
                                            <span class="zio-pop-desc">{{ $n['d'] }}</span>
                                            <span class="zio-pop-tag"><i class="fas fa-bolt"></i>{{ $n['tag'] }}</span>
                                            <button type="button" class="zio-pop-x" @click.stop="open = null" aria-label="Close">&times;</button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach

                    <div class="zio-core" aria-hidden="true">
                        <span class="zio-mascot-halo"></span>
                        {{-- Animated mascot: a transparent (VP9-alpha) looping clip keyed
                             out of its original off-white box. Autoplays muted + inline,
                             no controls/audio. The transparent still PNG is the poster
                             (shown while loading or if the video can't play). Under
                             prefers-reduced-motion the video is hidden and the static
                             .zio-mascot-fallback image is shown instead (see <style>). --}}
                        <video class="zio-mascot zio-mascot-video"
                               aria-label="Zio, the Sayzio AI mascot"
                               width="220" height="220"
                               autoplay loop muted playsinline disablepictureinpicture
                               preload="metadata"
                               poster="{{ asset('branding/sayzio-mascot-still.png') }}">
                            <source src="{{ asset('branding/sayzio-mascot.webm') }}" type="video/webm">
                        </video>
                        <img src="{{ asset('branding/sayzio-mascot-still.png') }}" alt="Zio, the Sayzio AI mascot" class="zio-mascot zio-mascot-fallback" width="220" height="220" loading="eager" decoding="async">
                        {{-- Animated transparent WebP, revealed by the alpha guard for
                             browsers that decode the WebM but ignore its alpha (Safari/iOS).
                             data-src keeps it from downloading on browsers that honor video
                             alpha or under reduced motion — the guard sets src only on demand. --}}
                        <img data-src="{{ asset('branding/sayzio-mascot.webp') }}" alt="Zio, the Sayzio AI mascot" class="zio-mascot zio-mascot-anim" width="220" height="220" decoding="async">
                        <span class="zio-core-label"><i class="fas fa-wand-magic-sparkles"></i> Zio runs it all</span>
                    </div>
                </div>

                {{-- No-JS fallback: the popover title/description live inside Alpine
                     x-cloak panels, so they're unreachable if Alpine fails to load
                     or JS is disabled. This <noscript> list keeps every tool's name
                     + description readable. Hidden whenever JS is available. --}}
                <noscript>
                    <ul class="zio-noscript">
                        @foreach($zioNodes as $n)
                            <li class="zio-noscript-item">
                                <img class="zio-noscript-ic" src="{{ asset('images/zio-nodes/' . $n['img']) }}" alt="" width="34" height="34" loading="lazy" decoding="async">
                                <span class="zio-noscript-text">
                                    <strong class="zio-noscript-title" style="--ac:{{ $n['c'] }}">{{ $n['t'] }}</strong>
                                    <span class="zio-noscript-desc">{{ $n['d'] }}</span>
                                    <span class="zio-noscript-tag" style="--ac:{{ $n['c'] }}">{{ $n['tag'] }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </noscript>
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

        /* ============ Claim-your-link control ============
           Glass pill matching the hero design system. Scoped CSS only (no new
           Tailwind utilities) so it renders even when the build/watch isn't
           running in an isolated env. */
        .zio-claim-form { margin-bottom: 1rem; }
        .zio-claim-label {
            display: block;
            font-size: .7rem; font-weight: 600; letter-spacing: .08em; text-transform: uppercase;
            color: #94a3b8; margin-bottom: .5rem;
        }
        .zio-claim {
            display: flex; align-items: stretch; gap: .25rem;
            max-width: 30rem; margin-inline: auto;
            padding: .3rem;
            background: rgba(255,255,255,.04);
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 9999px;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            transition: border-color .2s ease, background .2s ease;
        }
        .zio-claim:focus-within {
            border-color: rgba(96,165,250,.55);
            background: rgba(255,255,255,.06);
        }
        .zio-claim-prefix {
            display: flex; align-items: center;
            padding-inline: .6rem 0; padding-left: .9rem;
            font-size: .9rem; color: #94a3b8; white-space: nowrap; user-select: none;
        }
        .zio-claim-input {
            flex: 1 1 auto; min-width: 0;
            background: transparent; border: 0; outline: none;
            color: #fff; font-size: .95rem; padding: .55rem .25rem;
        }
        .zio-claim-input::placeholder { color: #64748b; }
        .zio-claim-btn {
            flex: 0 0 auto;
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .55rem 1.15rem;
            border: 0; border-radius: 9999px;
            color: #fff; font-size: .9rem; font-weight: 700; white-space: nowrap; cursor: pointer;
        }
        /* Inline status mark (spinner / check / cross) sitting before the button. */
        .zio-claim-mark {
            flex: 0 0 auto;
            display: none; align-items: center; justify-content: center;
            width: 1.1rem; height: 1.1rem; margin-inline: .15rem .35rem;
            align-self: center;
            font-size: .85rem; line-height: 1;
        }
        .zio-claim[data-state="checking"] .zio-claim-mark,
        .zio-claim[data-state="available"] .zio-claim-mark,
        .zio-claim[data-state="error"] .zio-claim-mark { display: inline-flex; }
        .zio-claim[data-state="available"] .zio-claim-mark::before { content: '\2713'; color: #1ed760; font-weight: 800; }
        .zio-claim[data-state="error"] .zio-claim-mark::before { content: '\2715'; color: #fb7185; font-weight: 800; }
        .zio-claim[data-state="checking"] .zio-claim-mark::before {
            content: ''; width: .9rem; height: .9rem; border-radius: 50%;
            border: 2px solid rgba(148,163,184,.35); border-top-color: #60a5fa;
            animation: zioClaimSpin .6s linear infinite;
        }
        @keyframes zioClaimSpin { to { transform: rotate(360deg); } }
        .zio-claim[data-state="available"] { border-color: rgba(30,215,96,.5); }
        .zio-claim[data-state="error"] { border-color: rgba(251,113,133,.5); }

        /* Verdict line + suggestion chips. */
        .zio-claim-status {
            min-height: 1.1rem;
            margin: .55rem .25rem 0;
            font-size: .82rem; font-weight: 600; line-height: 1.3;
            color: #94a3b8;
        }
        .zio-claim-status[data-state="available"] { color: #34d399; }
        .zio-claim-status[data-state="error"] { color: #fb7185; }
        .zio-claim-status[data-state="checking"] { color: #94a3b8; }
        .zio-claim-suggest {
            display: flex; flex-wrap: wrap; align-items: center; gap: .4rem;
            margin: .5rem .25rem 0;
        }
        .zio-claim-suggest-label { font-size: .78rem; color: #94a3b8; }
        .zio-claim-suggest-list { display: inline-flex; flex-wrap: wrap; gap: .35rem; }
        .zio-claim-suggest-btn {
            display: inline-flex; align-items: center;
            padding: .25rem .6rem;
            font-size: .8rem; font-weight: 600; color: #c7d2fe;
            background: rgba(96,165,250,.1);
            border: 1px solid rgba(96,165,250,.3);
            border-radius: 9999px; cursor: pointer;
            transition: background .18s ease, border-color .18s ease, transform .18s cubic-bezier(.34,1.56,.64,1);
        }
        .zio-claim-suggest-btn:hover {
            background: rgba(96,165,250,.18);
            border-color: rgba(96,165,250,.5);
            transform: translateY(-1px);
        }
        @media (prefers-reduced-motion: reduce) {
            .zio-claim[data-state="checking"] .zio-claim-mark::before { animation: none; }
            .zio-claim-suggest-btn { transition: none; }
            .zio-claim-suggest-btn:hover { transform: none; }
            .pulse-dot { animation: none; }
        }
        .pulse-dot { animation: zioStatPulse 2.2s ease-in-out infinite; }
        @keyframes zioStatPulse {
            0%, 100% { opacity: 1; box-shadow: 0 0 0 0 rgba(61,107,255,.55); }
            50% { opacity: .75; box-shadow: 0 0 0 4px rgba(61,107,255,0); }
        }
        @media (max-width: 1023.98px) {
            .zio-claim-status, .zio-claim-suggest { text-align: center; justify-content: center; }
        }
        html.light-mode .zio-claim-status { color: #64748b; }
        html.light-mode .zio-claim-status[data-state="available"] { color: #059669; }
        html.light-mode .zio-claim-status[data-state="error"] { color: #e11d48; }
        html.light-mode .zio-claim-suggest-label { color: #64748b; }
        html.light-mode .zio-claim-suggest-btn {
            color: #4338ca; background: rgba(99,102,241,.08); border-color: rgba(99,102,241,.28);
        }
        html.light-mode .zio-claim-suggest-btn:hover { background: rgba(99,102,241,.15); border-color: rgba(99,102,241,.45); }
        @media (max-width: 380px) {
            .zio-claim { flex-wrap: wrap; border-radius: 1.1rem; }
            .zio-claim-input { flex-basis: 100%; }
            .zio-claim-btn { flex: 1 1 100%; justify-content: center; }
        }
        @media (min-width: 1024px) {
            .zio-claim { margin-inline: 0; }
            .zio-claim-label { text-align: left; }
        }
        html.light-mode .zio-claim {
            background: #ffffff; border-color: #e2e8f0;
        }
        html.light-mode .zio-claim:focus-within { border-color: #60a5fa; }
        html.light-mode .zio-claim-prefix { color: #64748b; }
        html.light-mode .zio-claim-input { color: #0f172a; }
        html.light-mode .zio-claim-input::placeholder { color: #94a3b8; }
        html.light-mode .zio-claim-label { color: #64748b; }

        /* ============ Hero column order ============
           At ≥lg the Zio universe visual sits on the LEFT and the copy on the
           RIGHT (copy keeps the wider 1.05fr share for the headline). Below lg
           the grid is a single column in DOM order (copy first) so the headline
           still leads on mobile. Done in scoped CSS so no new Tailwind utilities
           are needed (no rebuild). */
        @media (min-width: 1024px) {
            .zio-hero-grid { grid-template-columns: 1fr 1.05fr; }
            .zio-hero-visual { order: 1; }
            .zio-hero-copy { order: 2; }
        }

        /* ============ Orbital Zio visual ============ */
        .zio-orbit-wrap { display: flex; align-items: center; justify-content: center; width: 100%; }
        .zio-orbit {
            --size: clamp(300px, 40vw, 500px);
            /* Node tiles scale WITH the orbit (proportional, not a fixed px) so the
               radial clearance between the three rings holds at every breakpoint. */
            --node: clamp(38px, calc(var(--size) * 0.092), 50px);
            /* Three concentric node-orbit radii (fractions of --size). Each icon's
               CENTER sits precisely on its dashed ring. The ~0.125 gap between rings
               exceeds the node fraction (~0.092), so tiles never collide. */
            --r1: calc(var(--size) * 0.300);
            --r2: calc(var(--size) * 0.425);
            --r3: calc(var(--size) * 0.550);
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

        /* Dashed orbit guides — one per node ring, each sitting on its ring radius.
           inset = (0.5 − radiusFraction) of the box, so the dashed circle lines up
           with the icon centers on that ring (r3 pokes a touch past the box). */
        .zio-ring {
            position: absolute; border-radius: 50%;
            border: 1.5px dashed rgba(120,140,255,.30);
            z-index: 1;
        }
        .zio-ring--r1 { inset: 20%;   border-color: rgba(120,140,255,.16); }
        .zio-ring--r2 { inset: 7.5%;  border-color: rgba(120,140,255,.22); }
        .zio-ring--r3 { inset: -5%;   border-color: rgba(120,140,255,.30); }

        /* Three independent rotors. Each spins at its own speed; the middle ring
           runs in REVERSE so adjacent rings counter-rotate. Per-ring --r feeds the
           node placement below. One shared keyframe (0→360); animation-direction
           gives clockwise vs counter-clockwise. */
        .zio-rotor {
            position: absolute; inset: 0; z-index: 2;
            /* Each rotor is a full-size (inset:0) layer; with three stacked, the
               topmost (outer) one would otherwise swallow clicks aimed at the
               inner rings' nodes. Make the rotor layers click-through and re-enable
               pointer events only on the nodes themselves (below). */
            pointer-events: none;
            animation-name: zioSpin;
            animation-timing-function: linear;
            animation-iteration-count: infinite;
        }
        .zio-rotor--r1 { --r: var(--r1); animation-duration: 54s; animation-direction: normal;  }
        .zio-rotor--r2 { --r: var(--r2); animation-duration: 64s; animation-direction: reverse; }
        .zio-rotor--r3 { --r: var(--r3); animation-duration: 80s; animation-direction: normal;  }
        @keyframes zioSpin { to { transform: rotate(360deg); } }

        /* Lift the rotors above the central mascot (z-index:3) while a popover is
           open, so an active node's popover is never hidden behind Zio. */
        .zio-paused .zio-rotor { z-index: 6; }
        /* Each rotor is its own stacking context, so lifting only the active NODE
           (z-index:16) can't raise it above a sibling rotor that comes later in the
           DOM — those rings' icons would paint over the open card. Lift the whole
           rotor that contains the active node above every other ring instead. */
        .zio-paused .zio-rotor:has(.zio-node--on) { z-index: 20; }

        /* Pause every ring (and its counter-rotation) while a popover is open OR a
           node is hovered/focused, so nodes are easy to click and popovers stay put. */
        .zio-paused .zio-rotor,
        .zio-paused .zio-node-ic,
        .zio-orbit:has(.zio-node:hover) .zio-rotor,
        .zio-orbit:has(.zio-node:hover) .zio-node-ic,
        .zio-orbit:has(.zio-node-btn:focus-visible) .zio-rotor,
        .zio-orbit:has(.zio-node-btn:focus-visible) .zio-node-ic { animation-play-state: paused; }

        .zio-node {
            position: absolute; top: 50%; left: 50%;
            width: var(--node); height: var(--node);
            margin: calc(var(--node) / -2);
            /* Re-enable pointer events the parent rotor turned off, so the node's
               button and its popover (close button / @click.stop) stay clickable. */
            pointer-events: auto;
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
            animation-name: zioSpin;
            animation-timing-function: linear;
            animation-iteration-count: infinite;
        }
        /* Each tile counter-rotates with its OWN ring's duration but the OPPOSITE
           direction, so the rotor spin is exactly cancelled and tiles stay upright. */
        .zio-rotor--r1 .zio-node-ic { animation-duration: 54s; animation-direction: reverse; }
        .zio-rotor--r2 .zio-node-ic { animation-duration: 64s; animation-direction: normal;  }
        .zio-rotor--r3 .zio-node-ic { animation-duration: 80s; animation-direction: reverse; }

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
            width: max-content; max-width: 232px;
            padding: 11px 30px 13px 13px;
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
        .zio-pop-desc  { display: block; margin-top: 4px; font-size: 11.5px; font-weight: 500; color: rgba(214,222,255,.82); line-height: 1.45; }
        .zio-pop-tag {
            display: inline-flex; align-items: center; gap: 4px;
            margin-top: 9px;
            padding: 3px 9px;
            border-radius: 999px;
            font-size: 10px; font-weight: 800; letter-spacing: .02em;
            color: color-mix(in srgb, var(--ac) 75%, white 25%);
            background: color-mix(in srgb, var(--ac) 16%, transparent);
            border: 1px solid color-mix(in srgb, var(--ac) 38%, transparent);
        }
        .zio-pop-tag i { font-size: 8px; opacity: .9; }
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
        /* The animated clip is square (640x640); keep it block-level so it sits
           flush like the old <img> and let its aspect ratio drive the height. */
        .zio-mascot-video { display: block; aspect-ratio: 1 / 1; }
        /* Default (motion ok): show the video, hide both fallback images. The
           animated WebP fallback is only revealed by the alpha guard for
           browsers that don't honor video alpha (Safari/iOS); the static PNG
           fallback is reserved for the reduced-motion path. */
        .zio-mascot-fallback { display: none; }
        .zio-mascot-anim { display: none; }
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

        /* ---- No-JS fallback list (only rendered inside <noscript>) ---- */
        .zio-noscript {
            list-style: none;
            margin: 22px auto 0;
            padding: 0;
            display: grid;
            gap: 8px;
            width: 100%;
            max-width: 460px;
        }
        .zio-noscript-item {
            display: flex; align-items: flex-start; gap: 11px;
            padding: 11px 13px;
            border-radius: 14px;
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.12);
            backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
        }
        .zio-noscript-ic { width: 34px; height: 34px; object-fit: contain; flex: 0 0 auto; }
        .zio-noscript-text { display: flex; flex-direction: column; text-align: left; }
        .zio-noscript-title { font-size: 13px; font-weight: 800; color: #fff; line-height: 1.25; }
        .zio-noscript-title::before {
            content: ''; display: inline-block;
            width: 7px; height: 7px; margin-right: 7px;
            border-radius: 50%; background: var(--ac, var(--c2));
            vertical-align: middle;
        }
        .zio-noscript-desc { margin-top: 2px; font-size: 11.5px; font-weight: 500; color: rgba(214,222,255,.82); line-height: 1.4; }
        .zio-noscript-tag {
            align-self: flex-start;
            margin-top: 6px;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 9.5px; font-weight: 800; letter-spacing: .02em;
            color: color-mix(in srgb, var(--ac, var(--c2)) 75%, white 25%);
            background: color-mix(in srgb, var(--ac, var(--c2)) 16%, transparent);
            border: 1px solid color-mix(in srgb, var(--ac, var(--c2)) 38%, transparent);
        }

        html.light-mode .zio-noscript-item {
            background: #ffffff; border-color: #e2e8f0;
            box-shadow: 0 10px 24px -14px rgba(15,23,42,.35);
        }
        html.light-mode .zio-noscript-title { color: #0f172a; }
        html.light-mode .zio-noscript-desc { color: #475569; }
        html.light-mode .zio-noscript-tag {
            color: color-mix(in srgb, var(--ac, var(--c2)) 60%, black 40%);
            background: color-mix(in srgb, var(--ac, var(--c2)) 12%, white 88%);
            border-color: color-mix(in srgb, var(--ac, var(--c2)) 30%, white 70%);
        }

        /* ---- Tablet (single-column, sm→below-lg): the orbit is stacked under the
               copy in a wide column, so the vw-based size leaves it looking small and
               lost. Bump --size here so it fills the column with more presence. Every
               sub-element (mascot, node radius) derives from --size, so they scale in
               lockstep. Does NOT touch the ≥lg two-column desktop layout. ---- */
        @media (min-width: 640px) and (max-width: 1023.98px) {
            .zio-orbit { --size: clamp(360px, 54vw, 460px); }
        }

        /* ---- Narrow single-column phones (anything below the ≥640px tablet block).
               Across this whole range `--size` sits pinned at its 300px floor
               (40vw < 300px until ~750px), but the DEFAULT `--node` clamps up to its
               38px MIN — i.e. ~0.127 of --size, which EXCEEDS the ~0.125 inter-ring
               gap, so the inner/middle rings kiss at the shared 3- and 9-o'clock
               nodes (and the outer ring pokes past the box on ~320px screens). Pull
               all three radii in and shrink the tiles so every icon stays fully
               visible, clears the central mascot, and never collides. Bounded at
               639.98px so it dovetails exactly with the ≥640px tablet block (which
               grows --size enough that the default radii breathe again). The
               ≥640px tablet / ≥lg desktop sizing is untouched. ---- */
        @media (max-width: 639.98px) {
            .zio-orbit {
                --node: clamp(32px, calc(var(--size) * 0.088), 42px);
                --r1: calc(var(--size) * 0.275);
                --r2: calc(var(--size) * 0.390);
                --r3: calc(var(--size) * 0.500);
            }
        }

        /* ---- Light mode ---- */
        html.light-mode .zio-glow {
            background: radial-gradient(circle at 50% 45%, rgba(61,107,255,.18), rgba(110,97,255,.08) 45%, transparent 70%);
        }
        html.light-mode .zio-pulse { border-color: rgba(37,66,199,.28); }
        html.light-mode .zio-ring { border-color: rgba(37,66,199,.26); }
        html.light-mode .zio-ring--r1 { border-color: rgba(37,66,199,.14); }
        html.light-mode .zio-ring--r2 { border-color: rgba(37,66,199,.20); }
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
        html.light-mode .zio-pop-tag {
            color: color-mix(in srgb, var(--ac) 60%, black 40%);
            background: color-mix(in srgb, var(--ac) 12%, white 88%);
            border-color: color-mix(in srgb, var(--ac) 30%, white 70%);
        }
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
            /* No autoplaying clip when motion is reduced: hide the video and
               show the static transparent mascot still in its place. */
            .zio-mascot-video { display: none !important; }
            .zio-mascot-fallback { display: block !important; }
        }
    </style>

    <script>
        // Transparent-mascot guard. The animated mascot is a VP9-alpha WebM,
        // but Safari / iOS WebKit decode VP9 yet IGNORE its alpha channel, so
        // the keyed-out off-white background renders as an opaque box. There is
        // no reliable feature flag for "VP9 alpha honored", so we detect it
        // directly: once a frame is available, draw a corner of the video (the
        // keyed-out background region) to a small canvas and read its alpha.
        // The clip is same-origin, so the canvas is not tainted. If the corner
        // is opaque, the browser is not honoring alpha — hide the video and
        // show the transparent still PNG instead (mascot still visible, no box).
        // Covers both home mascot clips: the hero (.zio-mascot-video) and the
        // "1IN.ME is Sayzio" section (.bs-mascot-video), each paired with its
        // own transparent still (*-fallback) sibling. Runs after DOMContentLoaded
        // because brand-sayzio is included further down the page than this hero,
        // so its <video> doesn't exist yet at parse time.
        (function () {
            function initMascotAlphaGuard() {
            var videos = document.querySelectorAll('.zio-mascot-video, .bs-mascot-video');
            if (!videos.length) { return; }
            var reduceMotion = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
            Array.prototype.forEach.call(videos, function (video) {
                // Both fallbacks are matching siblings sharing the video's class
                // prefix: -video -> -fallback (static still PNG, reduced-motion
                // path) and -video -> -anim (animated transparent WebP, shown to
                // browsers that decode VP9 but ignore its alpha, e.g. Safari/iOS).
                var prefix = (video.className.split(/\s+/).filter(function (c) {
                    return /-mascot-video$/.test(c);
                })[0] || '');
                var still = prefix ? video.parentNode.querySelector('.' + prefix.replace(/-video$/, '-fallback')) : null;
                var anim = prefix ? video.parentNode.querySelector('.' + prefix.replace(/-video$/, '-anim')) : null;
                if (!still) { return; }
                var done = false;
                function showStill() {
                    done = true;
                    video.style.display = 'none';
                    // Motion allowed: show the animated transparent WebP (mascot
                    // still moves, no opaque box). Reduced motion: keep the static
                    // still. Set src only now so the WebP never downloads for
                    // browsers that honor video alpha or under reduced motion.
                    if (!reduceMotion && anim) {
                        if (!anim.getAttribute('src') && anim.getAttribute('data-src')) {
                            anim.setAttribute('src', anim.getAttribute('data-src'));
                        }
                        anim.style.display = 'block';
                    } else {
                        still.style.display = 'block';
                    }
                    try { video.pause(); } catch (e) { /* noop */ }
                }
                function checkAlpha() {
                    if (done) { return; }
                    if (video.readyState < 2 || !video.videoWidth) { return; }
                    try {
                        var c = document.createElement('canvas');
                        c.width = 4; c.height = 4;
                        var ctx = c.getContext('2d', { willReadFrequently: true });
                        if (!ctx) { return; }
                        ctx.clearRect(0, 0, 4, 4);
                        // Sample the top-left corner of the frame (background region).
                        ctx.drawImage(video, 0, 0, video.videoWidth * 0.05, video.videoHeight * 0.05, 0, 0, 4, 4);
                        var a = ctx.getImageData(0, 0, 1, 1).data[3];
                        done = true; // decided; if the corner is opaque, alpha isn't honored.
                        if (a > 24) { showStill(); }
                    } catch (e) {
                        // Tainted/unsupported canvas: be safe, keep the still image.
                        showStill();
                    }
                }
                video.addEventListener('loadeddata', checkAlpha);
                video.addEventListener('playing', checkAlpha);
                // Belt-and-suspenders: re-check shortly after load in case the
                // first probed frame was decoded before alpha was applied.
                setTimeout(function () { done = false; checkAlpha(); }, 600);
                if (video.readyState >= 2) { checkAlpha(); }
            });
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initMascotAlphaGuard);
            } else {
                initMascotAlphaGuard();
            }
        })();

        // Hero "claim your link" handler. Carries the typed handle into the
        // existing register flow via the same open-auth event the other hero
        // CTAs use. An empty handle still opens registration normally.
        window.zioClaimSubmit = function (e) {
            e.preventDefault();
            var input = document.getElementById('zio-claim-input');
            var handle = input ? input.value.trim().toLowerCase().replace(/^@+/, '') : '';
            if (window.trackMarketingEvent) {
                window.trackMarketingEvent('landing_home_cta', 'hero_claim');
            }
            window.dispatchEvent(new CustomEvent('open-auth', {
                detail: { tab: 'register', handle: handle }
            }));
            return false;
        };

        // Live "is this handle free?" feedback. Debounces input and hits the
        // public, rate-limited site.handle.available endpoint, which mirrors the
        // exact handle rules enforced at sign-up. Pure vanilla JS so it works
        // even if Alpine fails to load; fails quietly on network errors (the
        // submit-time validation still guards).
        (function () {
            var form = document.querySelector('.zio-claim-form[data-handle-check-url]');
            if (!form) { return; }
            var url     = form.getAttribute('data-handle-check-url');
            var input   = document.getElementById('zio-claim-input');
            var box     = document.getElementById('zio-claim-box');
            var status  = document.getElementById('zio-claim-status');
            var suggest = document.getElementById('zio-claim-suggest');
            var sugList = document.getElementById('zio-claim-suggest-list');
            if (!input || !box || !status) { return; }

            var timer = null, controller = null, reqToken = 0;
            // 'available' → green, '' / 'empty' / 'checking' → neutral, anything
            // else (taken/banned/invalid/too_*) → error styling.
            function visualState(s) {
                if (s === 'available') { return 'available'; }
                if (s === 'checking' || s === 'empty' || s === '') { return s === 'checking' ? 'checking' : ''; }
                return 'error';
            }
            function paint(s, message, suggestions) {
                var vs = visualState(s);
                box.setAttribute('data-state', vs);
                status.setAttribute('data-state', vs);
                status.textContent = message || '';
                if (suggestions && suggestions.length) {
                    sugList.textContent = '';
                    suggestions.forEach(function (h) {
                        var b = document.createElement('button');
                        b.type = 'button';
                        b.className = 'zio-claim-suggest-btn';
                        b.textContent = '@' + h;
                        b.addEventListener('click', function () {
                            input.value = h;
                            input.focus();
                            run(h);
                        });
                        sugList.appendChild(b);
                    });
                    suggest.hidden = false;
                } else {
                    suggest.hidden = true;
                    sugList.textContent = '';
                }
            }
            function clear() {
                box.setAttribute('data-state', '');
                status.setAttribute('data-state', '');
                status.textContent = '';
                suggest.hidden = true;
                sugList.textContent = '';
            }

            function run(raw) {
                var value = (raw || '').trim().toLowerCase().replace(/^@+/, '');
                reqToken++;
                var token = reqToken;
                if (controller) { try { controller.abort(); } catch (e) {} }

                if (value === '') { clear(); return; }

                paint('checking', 'Checking availability…');

                controller = (typeof AbortController !== 'undefined') ? new AbortController() : null;
                fetch(url + '?handle=' + encodeURIComponent(value), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    signal: controller ? controller.signal : undefined
                })
                .then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
                .then(function (data) {
                    if (token !== reqToken) { return; }
                    paint(data.status || '', data.message || '', data.suggestions || []);
                })
                .catch(function (err) {
                    if (err && err.name === 'AbortError') { return; }
                    if (token !== reqToken) { return; }
                    clear();
                });
            }

            input.addEventListener('input', function () {
                if (timer) { clearTimeout(timer); }
                var v = input.value;
                if (v.trim() === '') { reqToken++; clear(); return; }
                timer = setTimeout(function () { run(v); }, 400);
            });
            // Check a pre-filled value (e.g. browser autofill) on load.
            if (input.value.trim() !== '') { run(input.value); }
        })();
    </script>
</section>
