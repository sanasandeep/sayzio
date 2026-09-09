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
<section class="relative z-10 overflow-hidden pt-28 pb-16 sm:pt-32 lg:pt-24 lg:pb-24 lg:min-h-[100svh] lg:flex lg:items-center" aria-labelledby="hero-h">
    {{-- Square grid behind the hero, with a scatter of tiles that fade up and
         down. The positions are written out rather than randomised at request
         time: a fixed scatter looks the same as a random one to anyone
         reading the page once, and it keeps the markup identical between
         renders, which matters for caching and for spotting a real diff.
         Each tile carries its own cell coordinates, duration and negative
         delay, so they are already mid-cycle on the first paint and no two
         are ever in step. --}}
    @php
        // col, row, seconds, delay
        $zioTiles = [
            [2, 1, 7.5, -0.4], [5, 4, 9.0, -3.1], [3, 9, 8.0, -6.2], [8, 2, 10.5, -1.7],
            [7, 11, 7.0, -4.8], [11, 6, 9.5, -2.3], [14, 3, 8.5, -7.4], [12, 13, 11.0, -0.9],
            [17, 9, 7.5, -5.6], [19, 2, 10.0, -3.8], [21, 12, 8.0, -1.2], [23, 5, 9.5, -6.9],
            [16, 15, 7.0, -2.7], [9, 7, 12.0, -8.3], [24, 8, 8.5, -4.1], [1, 13, 9.0, -5.2],
        ];
    @endphp
    <div class="zio-grid" aria-hidden="true">
        @foreach($zioTiles as [$c, $r, $d, $delay])
            <span class="zio-tile" style="--c:{{ $c }}; --r:{{ $r }}; --d:{{ $d }}s; --delay:{{ $delay }}s"></span>
        @endforeach
    </div>

    {{-- Drifting confetti --}}
    <div class="confetti drift-a" style="left:10%; bottom:-22vh;"><div class="w-3 h-3 rounded-sm" style="background:var(--c1)"></div></div>
    <div class="confetti drift-b" style="left:86%; bottom:-28vh; animation-delay:-6s"><div class="w-2 h-6 rounded-full" style="background:var(--c2)"></div></div>

    <div class="relative w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-10 xl:px-12">
        <div class="zio-hero-grid grid grid-cols-1 gap-y-16 lg:gap-x-14 xl:gap-x-20 lg:items-center">

            {{-- Copy column (sits on the RIGHT at ≥lg via .zio-hero-copy order) --}}
            <div class="zio-hero-copy text-center lg:text-left lg:max-w-[600px]">
                <div class="reveal inline-flex items-center gap-2 px-4 py-1.5 glass rounded-full text-xs font-semibold mb-8">
                    <i class="fas fa-wand-magic-sparkles text-[11px]" style="color:var(--c2)"></i>
                    <span class="grad-text">One Platform. Endless Conversations.</span>
                </div>

                <h1 id="hero-h" class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold leading-[1.08] tracking-tight mb-6">
                    Your link, now it <span class="grad-text">talks back</span>.
                </h1>

                <p class="reveal rd-2 text-lg sm:text-xl text-gray-400 max-w-xl mx-auto lg:mx-0 mb-9 leading-relaxed">
                    Meet <strong class="text-white">Zio</strong>, the AI behind Sayzio. It builds your Link in Bio pages, short links and QR codes, answers your visitors and picks up your calls, <strong class="text-white">24/7, free forever</strong>, no card required.
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
                    {{-- The dashed orbit guides and the expanding pulse rings are
                         gone. The nodes still travel their three circles; the
                         circles are simply no longer drawn. --}}

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
                                ['a' => 90,  'img' => 'growth.png',    'c' => '#10b981',   't' => 'AI Link Optimizer',     'd' => "Zio reads your stats, flags what's working and hands you the next move to grow.", 'tag' => 'Weekly tips'],
                                ['a' => 180, 'img' => 'calls.png',     'c' => 'var(--c4)', 't' => 'AI Phone',         'd' => 'Zio answers your calls and turns every caller into a captured lead while you focus.', 'tag' => '24/7 answer'],
                                ['a' => 270, 'img' => 'analytics.png', 'c' => 'var(--c3)', 't' => 'Live Analytics',   'd' => 'Watch every click, scan and visit land in real time on a live world map.', 'tag' => 'Real-time'],
                            ]],
                            // Middle ring (6) — everyday building & growth tools.
                            ['cls' => 'r2', 'nodes' => [
                                ['a' => 30,  'img' => 'link.png',      'c' => 'var(--c1)', 't' => 'Smart Links',      'd' => 'Turn long URLs into branded short links you can track, tag and retarget.', 'tag' => 'Branded'],
                                ['a' => 90,  'img' => 'qr.png',        'c' => 'var(--c3)', 't' => 'QR Studio',        'd' => 'Design on-brand codes with custom eyes and frames that track every single scan.', 'tag' => '16 types'],
                                ['a' => 150, 'img' => 'store.png',     'c' => '#10b981',   't' => 'Built-in Store',   'd' => 'Sell products and take payments straight from your link. Keep every cent.', 'tag' => '0% fees'],
                                ['a' => 210, 'img' => 'forms.png',     'c' => 'var(--c4)', 't' => 'Forms',           'd' => 'Collect leads, bookings and payments with 21 customizable field types.', 'tag' => '21 fields'],
                                ['a' => 270, 'img' => 'audience.png',  'c' => 'var(--c5)', 't' => 'Subscribers',     'd' => 'Grow an email and WhatsApp audience you actually own, then message them anytime.', 'tag' => 'You own it'],
                                ['a' => 330, 'img' => 'social.png',    'c' => 'var(--c2)', 't' => 'Social Proof',     'd' => 'Live popups surface real activity that nudges new visitors to take action.', 'tag' => '7 widgets'],
                            ]],
                            // Outer ring (7) — the wider feature universe + a new add-on.
                            ['cls' => 'r3', 'nodes' => [
                                ['a' => 0,   'img' => 'code.png',      'c' => '#10b981',   't' => 'Developer API',    'd' => 'Build anything on Sayzio with a full, token-secured REST API.', 'tag' => 'REST API'],
                                ['a' => 51,  'img' => 'reviews.png',   'c' => 'var(--c5)', 't' => 'Reviews',         'd' => 'Collect native reviews and pull in Google & Trustpilot ratings to build instant trust.', 'tag' => 'Google + more'],
                                ['a' => 103, 'img' => 'menu.png',      'c' => 'var(--c4)', 't' => 'Restaurant Menu', 'd' => 'QR menus with live ordering that sends tickets straight to your kitchen staff.', 'tag' => 'Live orders'],
                                ['a' => 154, 'img' => 'resume.png',    'c' => 'var(--c3)', 't' => 'Resume',          'd' => 'Build a polished, shareable resume and portfolio with AI tailoring and PDF export.', 'tag' => 'AI-tailored'],
                                ['a' => 206, 'img' => 'calendar.png',  'c' => 'var(--c1)', 't' => 'Calendar',        'd' => 'Share events visitors can follow and book, synced to Google Calendar.', 'tag' => 'Auto-sync'],
                                ['a' => 257, 'img' => 'vcard.png',     'c' => 'var(--c2)', 't' => 'Digital Cards',    'd' => 'Share a tappable vCard that saves straight to any phone in one tap.', 'tag' => 'One tap'],
                                ['a' => 309, 'img' => 'domain.png',    'c' => 'var(--c5)', 't' => 'Custom Domain',    'd' => 'Put your whole universe on your own domain for a fully branded presence.', 'tag' => 'Your brand'],
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

                        {{-- Zio, talking.

                             Three images, not one. The artwork ships cut into a
                             body and two antennae (branding/zio-body.png,
                             zio-antenna-l.png, zio-antenna-r.png), all on the same
                             512x512 canvas so they stack at inset:0 with nothing to
                             line up by hand. The cut was made along the head's own
                             outline, so each antenna is whole and the body keeps
                             the little stubs where they join — which is what lets
                             them sway from their bases without coming loose.

                             Everything else — the lids, the mouth, the tongue — is
                             CSS positioned in PERCENTAGES of the artwork, so it
                             stays registered at every size. The numbers come from
                             the pixels of the 758px master: left iris x 170-292,
                             y 173-291; right iris x 409-542, y 201-323; the painted
                             smile x 303-378, y 313-334.

                             The mouth sits ON TOP of the painted smile rather than
                             replacing it: between lines it is hidden and the
                             original smile is Zio's resting face; while he is
                             speaking it opens over it and the tongue shows. --}}
                        <div class="zio-face">
                            <img src="{{ asset('branding/zio-body.png') }}"
                                 alt="Zio, the Sayzio AI mascot" class="zio-mascot"
                                 width="220" height="220" loading="eager" decoding="async">
                            <span class="zio-ant zio-ant--l"></span>
                            <span class="zio-ant zio-ant--r"></span>
                            <span class="zio-lid zio-lid--l"></span>
                            <span class="zio-lid zio-lid--r"></span>
                            <span class="zio-mouth-gate"><span class="zio-mouth"><i class="zio-tongue"></i></span></span>
                        </div>

                        {{-- One line at a time, on a loop. Every bubble sits in the
                             same place and takes its turn via an animation-delay, so
                             only one is ever on screen and they never reflow the
                             hero. Decorative: the same promise is written out in the
                             copy column beside it, and this whole block is
                             aria-hidden, so a screen reader hears it once. --}}
                        @php
                            $zioLines = [
                                "Hi, I'm Zio 👋",
                                'I build your link page, QR codes and short links.',
                                'Then I answer your visitors — and pick up your calls.',
                                'Free forever. Want to try me?',
                            ];
                        @endphp
                        <div class="zio-says">
                            @foreach($zioLines as $i => $line)
                                <span class="zio-bubble" style="--i:{{ $i }}">{{ $line }}</span>
                            @endforeach
                        </div>

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
        /* ---- Hero grid ----
           A square grid drawn in two hairline gradients, with a scatter of
           single cells that glow up and fade out. It is masked away toward
           the edges so it never meets the page border as a hard line, which
           is what keeps a texture from reading as a table. Both the grid and
           the tiles are drawn from tokens, so the whole thing inverts for
           dark mode by redefining two colours. */
        .zio-grid {
            position: absolute; inset: 0;
            z-index: -1;
            pointer-events: none;
            --cell: 58px;
            --line: rgba(15,23,42,.055);
            --tile: rgba(61,107,255,.10);
            background-image:
                linear-gradient(to right, var(--line) 1px, transparent 1px),
                linear-gradient(to bottom, var(--line) 1px, transparent 1px);
            background-size: var(--cell) var(--cell);
            -webkit-mask-image: radial-gradient(120% 85% at 42% 45%, #000 30%, transparent 78%);
                    mask-image: radial-gradient(120% 85% at 42% 45%, #000 30%, transparent 78%);
        }
        html:not(.light-mode) .zio-grid {
            --line: rgba(255,255,255,.05);
            --tile: rgba(120,150,255,.14);
        }
        .zio-tile {
            position: absolute;
            left: calc(var(--cell) * var(--c));
            top:  calc(var(--cell) * var(--r));
            width: var(--cell); height: var(--cell);
            background: radial-gradient(closest-side, var(--tile), transparent 92%);
            opacity: 0;
            animation: zioTile var(--d) ease-in-out infinite;
            animation-delay: var(--delay);
        }
        @keyframes zioTile {
            0%, 100% { opacity: 0; }
            18%      { opacity: 1; }
            46%      { opacity: .55; }
            70%      { opacity: 0; }
        }
        @media (max-width: 640px) { .zio-grid { --cell: 44px; } }

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
            background: color-mix(in srgb, var(--ac, var(--c2)) 10%, rgba(255,255,255,.06));
            border: 1px solid color-mix(in srgb, var(--ac, var(--c2)) 32%, rgba(255,255,255,.12));
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
            border-color: color-mix(in srgb, var(--ac, var(--c2)) 55%, rgba(255,255,255,.30));
            background: rgba(255,255,255,.10);
            box-shadow: 0 16px 38px -16px color-mix(in srgb, var(--ac, var(--c2)) 60%, transparent);
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
        /* The float lives on the face, not the artwork, so the eyelids and the
           mouth ride along with it instead of drifting off the eyes. */
        .zio-face {
            position: relative;
            /* Published as its own custom property so the few places that need
               a real length rather than a percentage — the lash line's
               thickness — can scale with the artwork. */
            --fw: calc(var(--size) * 0.42);
            width: var(--fw);
            aspect-ratio: 1 / 1;
            animation: zioFloat 6.5s ease-in-out infinite;
            transform-origin: 50% 80%;
        }
        .zio-mascot {
            display: block;
            width: 100%;
            height: auto;
            /* No glow. The old blue drop-shadow spread a haze roughly 80px
               wide around the mascot, which on a white page is not white:
               the pixels beside his head measured #D1DBFF. */
        }
        @keyframes zioFloat {
            0%,100% { transform: translateY(0) rotate(-1.5deg) scale(1); }
            50%     { transform: translateY(-12px) rotate(1.5deg) scale(1.02); }
        }

        /* ---- Antennae ----
           Each is its own layer on the same canvas, pinned at inset:0, so it
           needs no positioning of its own — only a pivot.

           They sit BEHIND the body, and that is the whole trick. In the
           artwork an antenna is drawn resting along the head's dome for its
           entire arc, so there is no single point where it "joins": cutting
           it out leaves a long edge that follows the dome exactly. In front
           of the body, the first degree of sway slides that edge off the
           dome and the antenna reads as a sticker peeling away. Behind it,
           the head's own silhouette is the boundary, and it never moves. The
           layers also carry 90px of head with them below the cut, so the
           edge is buried too deep for a few degrees to ever bring it out.

           Cutting them out took two goes. The first cut took, per column,
           everything above where the head's silhouette began — but where a
           stalk merges into the head there is no gap between them, so that
           column's silhouette starts partway UP the stalk. The stalk got
           split lengthwise, half to the antenna and half to the body, and
           the two halves came apart the moment it swayed. The cut now
           follows a running median of the silhouette, which ignores those
           narrow spikes and recovers the head's actual dome, so each antenna
           comes away whole.

           They sway on purposely mismatched durations, so the pair never
           falls into a mechanical lockstep. */
        .zio-ant {
            position: absolute; inset: 0;
            background-repeat: no-repeat;
            background-size: 100% 100%;
            pointer-events: none;
            z-index: 0;
        }
        .zio-mascot { position: relative; z-index: 1; }
        .zio-ant--l {
            background-image: url('{{ asset('branding/zio-antenna-l.png') }}');
            transform-origin: 33% 26%;
            animation: zioAntL 3.6s ease-in-out infinite;
        }
        .zio-ant--r {
            background-image: url('{{ asset('branding/zio-antenna-r.png') }}');
            transform-origin: 68% 22%;
            animation: zioAntR 4.3s ease-in-out infinite;
        }
        @keyframes zioAntL {
            0%,100% { transform: rotate(-2.6deg); }
            50%     { transform: rotate(2.2deg); }
        }
        @keyframes zioAntR {
            0%,100% { transform: rotate(2.4deg); }
            50%     { transform: rotate(-2.2deg); }
        }

        /* ---- Blink ----
           The lid is a patch of head colour that wipes down over the eye, and
           the whole trick is that it must not read AS a patch. Two things do
           that. It is masked with a radial gradient, so it has no edge at all
           — it is fully opaque over the iris and dissolves into the head well
           before its own boundary. And it is half again as wide as the iris,
           so the dissolve happens over skin rather than over the eye. What is
           left to see is the lash line, which is the part that says "shut". */
        /* Above the body, which is itself above the antennae (z-index 1). */
        .zio-lid, .zio-mouth-gate { z-index: 2; }
        .zio-lid {
            position: absolute;
            clip-path: inset(0 0 100% 0);
            animation: zioBlink 5.6s ease-in-out infinite;
            will-change: clip-path;
        }
        .zio-lid::before {
            content: ''; position: absolute; inset: 0;
            background: linear-gradient(var(--c-top), var(--c-bot));
            -webkit-mask-image: radial-gradient(closest-side, #000 72%, transparent 100%);
                    mask-image: radial-gradient(closest-side, #000 72%, transparent 100%);
        }
        /* Iris on the 758px master: left x 170-292 / y 173-291, right
           x 409-542 / y 201-323. Each lid is 1.5x that box, centred on it. */
        .zio-lid--l { left: 18.4%; top: 18.9%; width: 24.1%; height: 23.4%;
                      --c-top: #569CFF; --c-bot: #5C7AFF; }
        .zio-lid--r { left: 49.6%; top: 22.5%; width: 26.3%; height: 24.1%;
                      --c-top: #A3C4FF; --c-bot: #7C72FF;
                      animation-delay: .07s; }
        /* The lash: a circle showing only its lower border, which draws the
           closed eye as an arc rather than a straight line. */
        .zio-lid::after {
            content: ''; position: absolute;
            left: 20%; right: 20%; top: 30%; height: 42%;
            border: 0 solid #0E1A55;
            border-bottom-width: calc(var(--fw) * .016);
            border-radius: 50%;
        }
        @keyframes zioBlink {
            0%, 88%, 100% { clip-path: inset(0 0 100% 0); }
            91%, 94%      { clip-path: inset(0 0 0 0); }
            97%           { clip-path: inset(0 0 100% 0); }
        }

        /* ---- Mouth ----
           The gate hides the whole mouth between lines, which lets the painted
           smile underneath be Zio's resting face; while a line is up the mouth
           opens and closes over it. It opens by growing its HEIGHT rather than
           scaling, because a scale would stretch the tongue inside it with it.
           The painted smile stays visible along the top edge and reads as the
           upper lip. */
        .zio-mouth-gate {
            position: absolute;
            left: 39.9%; top: 41.2%;
            width: 9.9%; height: 3.0%;
            animation: zioMouthGate 4s linear infinite;
        }
        .zio-mouth {
            display: block; position: absolute; inset: 0 0 auto 0;
            height: 100%;
            background: #1B0E3C;
            border-radius: 42% 42% 50% 50% / 22% 22% 78% 78%;
            overflow: hidden;
            animation: zioChatter .36s ease-in-out infinite;
        }
        .zio-tongue {
            position: absolute; left: 20%; right: 20%; bottom: -14%;
            height: 62%;
            background: #FF6E9E;
            border-radius: 50% 50% 45% 45% / 65% 65% 35% 35%;
        }
        @keyframes zioChatter {
            0%, 100% { height: 100%; }
            50%      { height: 205%; }
        }
        /* Synced to the bubbles, not merely near them. A bubble's slot is 4s
           (16s / four lines): it finishes appearing at 2.5% of the 16s cycle
           and starts leaving at 21% — 0.4s and 3.36s inside its own slot. The
           gate runs on the same 4s and opens at 11%, shuts at 84%: Zio starts
           talking as the line lands and has finished the sentence by the time
           it fades. Move one of these and the other has to move with it. */
        @keyframes zioMouthGate {
            0%, 9%    { opacity: 0; }
            11%, 83%  { opacity: 1; }
            85%, 100% { opacity: 0; }
        }

        /* ---- Speech bubbles ----
           Four lines share one slot above Zio's head and take turns: each runs
           the same 16s animation offset by four seconds, so exactly one is
           visible and the hero never reflows. */
        .zio-says {
            position: absolute;
            left: 50%; bottom: calc(100% + 6px);
            transform: translateX(-50%);
            width: max-content; max-width: min(15rem, 62vw);
            z-index: 5;
        }
        .zio-bubble {
            position: absolute; left: 50%; bottom: 0;
            width: max-content; max-width: min(15rem, 62vw);
            padding: 9px 13px;
            border-radius: 12px;
            /* Opaque on purpose: the tail is drawn as two triangles and can
               only match a solid fill. Takes the same surface tokens as every
               other floating chip on the page. */
            background: var(--fs-chip, #17162A);
            border: 1px solid var(--fs-rule, rgba(255,255,255,.16));
            color: #fff;
            font-size: 12.5px; font-weight: 600; line-height: 1.35;
            text-align: left; text-wrap: balance;
            opacity: 0;
            transform: translate(-50%, 6px) scale(.96);
            animation: zioSay 16s ease-in-out infinite;
            animation-delay: calc(var(--i) * 4s);
        }
        /* The tail. Two stacked triangles: the outer one is the border colour
           and sits a pixel lower, so the tail keeps the bubble's hairline. */
        .zio-bubble::before, .zio-bubble::after {
            content: ''; position: absolute; left: 50%; margin-left: -7px;
            width: 0; height: 0; border-left: 7px solid transparent;
            border-right: 7px solid transparent;
        }
        .zio-bubble::before { top: 100%; border-top: 8px solid var(--fs-rule, rgba(255,255,255,.16)); }
        .zio-bubble::after  { top: calc(100% - 1px); border-top: 8px solid var(--fs-chip, #17162A); }
        html.light-mode .zio-bubble { color: #0F172A; }
        @keyframes zioSay {
            0%              { opacity: 0; transform: translate(-50%, 6px) scale(.96); }
            2.5%, 21%       { opacity: 1; transform: translate(-50%, 0) scale(1); }
            24%, 100%       { opacity: 0; transform: translate(-50%, -4px) scale(.98); }
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
            background: color-mix(in srgb, var(--ac, var(--c2)) 6%, #ffffff);
            border-color: color-mix(in srgb, var(--ac, var(--c2)) 26%, #e2e8f0);
            box-shadow: 0 10px 24px -14px color-mix(in srgb, var(--ac, var(--c2)) 45%, rgba(15,23,42,.35));
        }
        html.light-mode .zio-node-btn:hover {
            border-color: color-mix(in srgb, var(--ac, var(--c2)) 45%, #e2e8f0); background: #ffffff;
            box-shadow: 0 16px 34px -16px color-mix(in srgb, var(--ac, var(--c2)) 45%, transparent);
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
            .zio-rotor, .zio-node-ic, .zio-face, .zio-mascot, .zio-mascot-halo,
            .zio-glow, .zio-pulse, .zio-node, .zio-node-thumb,
            .zio-lid, .zio-mouth, .zio-mouth-gate, .zio-bubble, .zio-ant, .zio-tile {
                animation: none !important;
            }
            /* The grid stays; only its blinking stops, on a low steady value
               so the scatter still reads as texture rather than vanishing. */
            .zio-tile { opacity: .5 !important; }
            .zio-node, .zio-node-thumb { opacity: 1 !important; }
            .zio-node-thumb { transform: scale(1) !important; }
            .zio-pulse { opacity: 0 !important; }
            /* Zio holds still: eyes open, antennae level, resting smile, and
               the first line left on screen rather than a cycle nobody asked
               to watch. */
            .zio-lid { clip-path: inset(0 0 100% 0) !important; }
            .zio-ant { transform: none !important; }
            .zio-mouth-gate { opacity: 0 !important; }
            .zio-bubble { opacity: 0 !important; transform: translate(-50%, 0) !important; }
            .zio-bubble:first-child { opacity: 1 !important; }
        }
    </style>

    <script>

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
