<!DOCTYPE html>
<html lang="en" class="{{ (($_COOKIE['1inme_theme'] ?? null) === 'light') ? 'light-mode' : '' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Safari uses theme-color to tint the browser toolbar. Always dark so the
         tab bar matches the brand regardless of the site's light/dark mode. --}}
    <meta name="theme-color" content="#0a0a14">
    @php
        $__seo = \App\Modules\Common\Support\MarketingSeo::resolveForView(['seoKey' => 'home']);
    @endphp
    <title>{{ $__seo['title'] }} — {{ config('app.name', 'Sayzio') }}</title>
    <meta name="description" content="{{ $__seo['description'] }}">
    @if(($__seo['keywords'] ?? '') !== '')
        <meta name="keywords" content="{{ $__seo['keywords'] }}">
    @endif
    @php
        $__schema = \App\Modules\Common\Support\MarketingSchema::forView([
            'seoKey' => 'home',
            'title'  => $__seo['title'] ?? null,
            'url'    => \App\Modules\Common\Support\PlatformHosts::canonicalUrl(),
        ]);
    @endphp
    <script type="application/ld+json">{!! json_encode($__schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @include('public.partials.marketing-share-meta')
    @include('public.partials.marketing-tracking')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('common.partials.default-icons')
    @include('common.partials.fontawesome')
    <script defer src="{{ asset('js/vendor/alpine-collapse.min.js') }}"></script>
    <script defer src="{{ asset('js/vendor/alpine.min.js') }}"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/marketing-anim.css') }}?v=18">
    @vite(['resources/js/marketing-anim.js'])
    <script>
        // Fire-and-forget marketing-CTA tracking shared by every home-page
        // "Sign up free" button so we can see which placement converts.
        window.trackMarketingEvent = function (source, target) {
            try {
                var url = '{{ route('marketing-events.track') }}';
                var data = new FormData();
                data.append('source', source);
                data.append('target', target);
                if (navigator.sendBeacon) {
                    navigator.sendBeacon(url, data);
                } else {
                    fetch(url, { method: 'POST', body: data, keepalive: true, credentials: 'same-origin' });
                }
            } catch (e) { /* fire-and-forget */ }
        };
    </script>
    <script>
        // Sync with site-wide theme preference (also toggled via Cmd/Ctrl+I).
        (function(){
            var pref = null;
            try { pref = localStorage.getItem('1inme_theme'); } catch(e) {}
            if (!pref) {
                var m = document.cookie.match(/(?:^|; )1inme_theme=([^;]+)/);
                if (m) pref = decodeURIComponent(m[1]);
            }
            if (pref === 'light' || pref === 'dark') {
                if (pref === 'light') document.documentElement.classList.add('light-mode');
            } else {
                try {
                    if (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) {
                        document.documentElement.classList.add('light-mode');
                    }
                } catch(e) {}
            }
        })();
        window.inmeToggleTheme = function(){
            var html = document.documentElement;
            var light = !html.classList.contains('light-mode');
            html.classList.toggle('light-mode', light);
            var val = light ? 'light' : 'dark';
            try { localStorage.setItem('1inme_theme', val); } catch(e) {}
            try { document.cookie = '1inme_theme=' + val + '; path=/; max-age=31536000; SameSite=Lax'; } catch(e) {}
            window.dispatchEvent(new CustomEvent('inme-theme-changed', { detail: { light: light } }));
            return light;
        };
        function homeThemeToggle(){
            return {
                light: document.documentElement.classList.contains('light-mode'),
                init(){ window.addEventListener('inme-theme-changed', e => this.light = e.detail.light); },
                toggle(){ this.light = window.inmeToggleTheme(); }
            }
        }
    </script>
    <style>
        :root {
            /* ===== Logo gradient stops ===== */
            --c1: #1bd4d9; /* cyan/teal */
            --c2: #3d6bff; /* purple */
            --c3: #e94e8c; /* magenta */
            --c4: #ff8a3c; /* orange */
            --c5: #ffc845; /* yellow */
            --ink:    #14081f;  /* deep purple from tagline */
            --bg:     #0a0a14;
            --bg-2:   #14091f;
            --bg-3:   #1c0e2e;
        }
        html, body { background: var(--bg); }
        html { overflow-x: clip; }
        body { font-family: 'Space Grotesk', sans-serif; color: #fff; overflow-x: clip; }
        [x-cloak] { display: none !important; }

        /* ===== Light mode overrides for the home page ===== */
        html.light-mode {
            --bg:   #f8fafc;
            --bg-2: #ffffff;
            --bg-3: #f3f4f6;
            --ink:  #111827;
        }
        html.light-mode body { background: #f8fafc; color: #111827; }

        /* ============ Aurora background ============ */
        /* Blobs are pre-softened radial gradients instead of a live
           filter: blur() so low-end/mobile GPUs (and headless screenshot
           tooling) never composite a viewport-sized blur. */
        .aurora { position: fixed; inset: -10%; z-index: -1; pointer-events: none; opacity: .6; }
        .aurora b {
            position: absolute; border-radius: 50%; mix-blend-mode: screen;
            animation: aurora 22s ease-in-out infinite; will-change: transform;
        }
        .aurora b:nth-child(1) { top:-10%; left:-10%; width:60vw; height:60vw; background: radial-gradient(closest-side, var(--c2) 0%, transparent 72%); animation-delay: -2s; }
        .aurora b:nth-child(2) { bottom:-15%; right:-10%; width:55vw; height:55vw; background: radial-gradient(closest-side, #5c83ff 0%, rgba(92,131,255,.45) 45%, transparent 72%); animation-delay: -8s; }
        .aurora b:nth-child(3) { top:30%; left:40%; width:40vw; height:40vw; background: radial-gradient(closest-side, #22d3ee 0%, rgba(34,211,238,.45) 45%, transparent 72%); animation-delay: -14s; }
        .aurora b:nth-child(4) { top:60%; left:5%; width:35vw; height:35vw; background: radial-gradient(closest-side, #2342c7 0%, rgba(35,66,199,.45) 45%, transparent 72%); opacity:.7; animation-delay: -18s; }
        @keyframes aurora {
            0%,100% { transform: translate(0,0) scale(1); }
            33%     { transform: translate(6%,-4%) scale(1.15); }
            66%     { transform: translate(-5%,5%) scale(.95); }
        }

        /* ============ Reveal-on-scroll (bouncy) ============
           Reveals run as a pure CSS animation so the content always
           paints — even if the JS reveal observer is slow, fails, or
           never fires (which used to leave the hero blank on
           phones/tablets). The .js / .visible classes still gate the
           transition so reduced-motion users get the calm fallback. */
        .reveal {
            opacity: 1;
            transform: none;
            transition: opacity .7s cubic-bezier(.16,1,.3,1), transform .7s cubic-bezier(.34,1.56,.64,1);
        }
        @media (min-width: 1024px) {
            .reveal { animation: revealAuto .8s cubic-bezier(.34,1.56,.64,1) both; }
            @keyframes revealAuto {
                from { opacity: 0; transform: translateY(40px) scale(.94); }
                to   { opacity: 1; transform: none; }
            }
            .rd-1 { animation-delay: .08s }  .rd-2 { animation-delay: .18s }
            .rd-3 { animation-delay: .28s }  .rd-4 { animation-delay: .38s }
            .rd-5 { animation-delay: .48s }  .rd-6 { animation-delay: .58s }
        }

        /* ============ Hero grid safety net ============
           Below the lg breakpoint, force the hero grid into a single
           column with each item sized to its own content. This protects
           the hero from any quirk in Tailwind CDN parsing the arbitrary
           `lg:grid-cols-[5fr_7fr]` value, and it stops the right column
           (which has `min-height: 560px` from `.hero-phone-stage`) from
           dragging the left column with `align-items: center`, which was
           pushing the headline / paragraph / CTAs off the visible area
           on phones and tablets. */
        @media (max-width: 1023px) {
            .hero-grid {
                grid-template-columns: minmax(0, 1fr) !important;
                align-items: start !important;
            }
            .hero-grid > * { min-width: 0; }
        }

        /* ============ Floats ============ */
        .float-a { animation: floatA 6s ease-in-out infinite; }
        @keyframes floatA { 0%,100%{ transform: translateY(0) rotate(-2deg);} 50%{ transform: translateY(-16px) rotate(2deg);} }
        .float-b { animation: floatB 7s ease-in-out infinite; }
        @keyframes floatB { 0%,100%{ transform: translateY(0) rotate(2deg);} 50%{ transform: translateY(-14px) rotate(-2deg);} }
        .float-c { animation: floatC 9s ease-in-out infinite; }
        @keyframes floatC { 0%,100%{ transform: translateY(0) rotate(0);} 50%{ transform: translateY(-10px) rotate(3deg);} }

        /* ============ Wiggle / spin / shake / pop ============ */
        .wiggle { animation: wiggle 3.6s ease-in-out infinite; transform-origin: center; }
        @keyframes wiggle { 0%,100%{ transform: rotate(-4deg);} 50%{ transform: rotate(4deg);} }
        .spin-slow { animation: spinSlow 18s linear infinite; }
        @keyframes spinSlow { to { transform: rotate(360deg);} }
        .pop-in { animation: popIn .8s cubic-bezier(.34,1.56,.64,1) both; }
        @keyframes popIn { 0% { opacity:0; transform: scale(.4) rotate(-12deg);} 100% { opacity:1; transform: scale(1) rotate(0);} }

        /* ============ Buttons (magnetic + bounce + glow) ============ */
        .btn-bounce { transition: transform .22s cubic-bezier(.34,1.56,.64,1), box-shadow .25s, filter .25s; }
        .btn-bounce:hover { transform: translateY(-3px) scale(1.03); filter: brightness(1.06); }
        .btn-bounce:active { transform: translateY(-1px) scale(.98); }
        .btn-glow { position: relative; }
        .btn-glow::after {
            content:""; position: absolute; inset: -4px; border-radius: inherit; z-index: -1;
            background: conic-gradient(from 0deg, #3d6bff, #1bd4d9, #22d3ee, #3d6bff);
            opacity: 0; filter: blur(12px); transition: opacity .35s; animation: spinSlow 8s linear infinite;
        }
        .btn-glow:hover::after { opacity: .85; }

        /* ============ Tilt-on-hover ============ */
        .tilt { transition: transform .4s cubic-bezier(.16,1,.3,1), box-shadow .4s; }
        .tilt:hover { transform: perspective(800px) rotateX(4deg) rotateY(-6deg) translateY(-6px); }

        /* ============ Lift ============ */
        .lift { transition: transform .35s cubic-bezier(.16,1,.3,1), box-shadow .35s; }
        .lift:hover { transform: translateY(-8px); }

        /* ============ Marquee ============ */
        .marquee { animation: marquee 40s linear infinite; }
        @keyframes marquee { 0% { transform: translateX(0); } 100% { transform: translateX(-50%); } }
        .marquee-rev { animation: marqueeRev 50s linear infinite; }
        @keyframes marqueeRev { 0% { transform: translateX(-50%); } 100% { transform: translateX(0); } }

        /* ============ Equalizer ============ */
        .eq i { display:inline-block; width:3px; margin-right:2px; background: currentColor; border-radius:2px; animation: eq 1.1s ease-in-out infinite; }
        .eq i:nth-child(1){ height:35%; animation-delay:0s }
        .eq i:nth-child(2){ height:80%; animation-delay:.15s }
        .eq i:nth-child(3){ height:55%; animation-delay:.3s }
        .eq i:nth-child(4){ height:90%; animation-delay:.1s }
        @keyframes eq { 0%,100%{ transform: scaleY(.45);} 50%{ transform: scaleY(1);} }

        /* ============ Pulse ring + dot ============ */
        .pulse-dot { animation: pulseDot 2.2s ease-in-out infinite; }
        @keyframes pulseDot { 0%,100%{ transform: scale(1);} 50%{ transform: scale(1.3);} }
        .ring-pulse { position: absolute; border-radius: 50%; animation: ringPulse 2.4s cubic-bezier(0,0,.2,1) infinite; }
        @keyframes ringPulse { 0%{ transform: scale(.4); opacity:.9;} 80%,100%{ transform: scale(2.6); opacity:0;} }

        /* ============ Drawn-line keyframe (shared by .draw-line) ============ */
        @keyframes drawLine { to { stroke-dashoffset: 0; } }

        /* ============ Drawn underline ============ */
        .draw-line { stroke-dasharray: 220; stroke-dashoffset: 220; animation: drawLine 1.6s 1s ease-out forwards; }

        /* ============ Pricing card sparkles & shimmer ============ */
        .free-spark, .prem-spark {
            position: absolute; width: 10px; height: 10px; border-radius: 50%;
            background: radial-gradient(circle, #fff 0%, rgba(255,255,255,.6) 40%, transparent 70%);
            opacity: 0; pointer-events: none;
            animation: sparkPulse 3.4s ease-in-out infinite;
            filter: drop-shadow(0 0 6px rgba(255,255,255,.7));
        }
        .prem-spark { width: 8px; height: 8px; }
        @keyframes sparkPulse {
            0%, 100% { opacity: 0; transform: scale(.4); }
            50% { opacity: 1; transform: scale(1.2); }
        }

        /* Diagonal shimmer sweep across the premium card */
        .prem-shimmer::before {
            content: ""; position: absolute; inset: 0;
            background: linear-gradient(115deg, transparent 30%, rgba(255,255,255,.18) 48%, rgba(255,255,255,.30) 50%, rgba(255,255,255,.18) 52%, transparent 70%);
            transform: translateX(-100%);
            animation: premSweep 5.5s ease-in-out infinite;
        }
        @keyframes premSweep {
            0% { transform: translateX(-100%); }
            55%, 100% { transform: translateX(120%); }
        }

        /* Premium feature blocks subtle entrance + icon halo on hover */
        .prem-feat { opacity: 0; transform: translateY(6px); animation: premFeatIn .55s ease-out forwards; }
        @keyframes premFeatIn { to { opacity: 1; transform: translateY(0); } }
        .prem-feat:hover .prem-feat-ico { box-shadow: 0 0 0 2px rgba(255,255,255,.25), 0 8px 22px -6px rgba(255,255,255,.35); transform: rotate(-6deg) scale(1.08); }


        /* ============ Logo gradient text ============ */
        .grad-text {
            color: #90acff;
        }
        @keyframes gradShift { 0%,100%{ background-position: 0% 50%;} 50%{ background-position: 100% 50%;} }

        /* ============ Logo gradient bar ============ */
        .grad-bar {
            background: linear-gradient(95deg, #3d6bff, #1bd4d9, #22d3ee);
        }

        /* ============ Confetti shapes (drifting) ============ */
        .confetti { position: absolute; pointer-events: none; }
        .drift-a { animation: driftA 14s linear infinite; }
        @keyframes driftA { 0%{ transform: translateY(20vh) rotate(0);} 100%{ transform: translateY(-120vh) rotate(540deg);} }
        .drift-b { animation: driftB 18s linear infinite; }
        @keyframes driftB { 0%{ transform: translateY(20vh) rotate(0);} 100%{ transform: translateY(-120vh) rotate(-720deg);} }

        /* ============ Sticker shake on hover ============ */
        .shake-hover { transition: transform .25s; }
        .shake-hover:hover { animation: shake .5s; }
        @keyframes shake { 0%,100%{ transform: translateX(0);} 20%{ transform: translateX(-3px) rotate(-2deg);} 40%{ transform: translateX(3px) rotate(2deg);} 60%{ transform: translateX(-2px) rotate(-1deg);} 80%{ transform: translateX(2px) rotate(1deg);} }

        /* ============ Hero rotating role word ============ */
        .role-word { display: inline-block; will-change: transform, opacity, filter; }
        .role-word.word-in { animation: wordIn .55s cubic-bezier(.34,1.56,.64,1) both; }
        @keyframes wordIn {
            0%   { opacity: 0; transform: translateY(60%) rotateX(-70deg); filter: blur(8px); }
            55%  { opacity: 1; filter: blur(0); }
            100% { opacity: 1; transform: translateY(0) rotateX(0); filter: blur(0); }
        }
        .role-word.word-out { animation: wordOut .22s ease both; }
        @keyframes wordOut {
            0%   { opacity: 1; transform: translateY(0) rotateX(0); filter: blur(0); }
            100% { opacity: 0; transform: translateY(-50%) rotateX(60deg); filter: blur(6px); }
        }
        /* ============ 3D biolink stack (hero right) ============ */
        .stack-scene { position: relative; perspective: 1500px; perspective-origin: 55% 50%; }
        .stack-3d {
            position: absolute; inset: 0;
            display: flex; align-items: center; justify-content: center;
            transform-style: preserve-3d;
            animation: stackFloat 9s ease-in-out infinite;
        }
        @keyframes stackFloat {
            0%,100% { transform: rotateX(8deg) rotateY(-14deg) translateY(0); }
            50%     { transform: rotateX(5deg) rotateY(-10deg) translateY(-14px); }
        }
        .stack-inner {
            width: 320px; max-width: 86%;
            display: flex; flex-direction: column; gap: 12px;
            transform-style: preserve-3d;
        }
        .stack-card {
            position: relative;
            background: rgba(255,255,255,.07);
            border: 1px solid rgba(255,255,255,.14);
            border-radius: 20px;
            padding: 12px 14px;
            display: flex; align-items: center; gap: 12px;
            color: #fff;
            box-shadow: 0 22px 48px -22px rgba(0,0,0,.65), 0 0 0 1px rgba(255,255,255,.04), inset 0 1px 0 rgba(255,255,255,.15);
            opacity: 0;
            animation: cardIn .7s cubic-bezier(.34,1.56,.64,1) forwards;
            animation-delay: var(--d, 0ms);
        }
        @supports (backdrop-filter: blur(20px)) {
            .stack-card {
                background: linear-gradient(135deg, rgba(255,255,255,.1) 0%, rgba(255,255,255,.03) 100%);
                backdrop-filter: blur(24px) saturate(140%);
                -webkit-backdrop-filter: blur(24px) saturate(140%);
            }
        }
        .stack-card.is-profile {
            padding: 16px;
            background: linear-gradient(135deg, rgba(255,255,255,.14), rgba(255,255,255,.04));
            flex-direction: column; align-items: stretch;
        }
        .stack-card .card-icon {
            width: 38px; height: 38px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; flex-shrink: 0;
            background: rgba(255,255,255,.08);
        }
        .stack-card .card-thumb {
            width: 50px; height: 50px; border-radius: 12px;
            object-fit: cover; flex-shrink: 0;
            border: 1px solid rgba(255,255,255,.15);
        }
        .stack-card .card-body { min-width: 0; flex: 1; }
        .stack-card .card-title { font-size: 13px; font-weight: 700; line-height: 1.25; }
        .stack-card .card-sub   { font-size: 11px; color: rgba(255,255,255,.65); margin-top: 2px; }
        .stack-card .card-cta   { color: rgba(255,255,255,.55); font-size: 11px; }

        .profile-row { display: flex; gap: 12px; align-items: center; }
        .profile-avatar { width: 56px; height: 56px; border-radius: 50%; object-fit: cover; border: 2px solid rgba(255,255,255,.35); flex-shrink: 0; }
        .profile-handle { font-size: 15px; font-weight: 700; line-height: 1.1; }
        .profile-tag    { font-size: 11px; color: rgba(255,255,255,.7); margin-top: 3px; }
        .profile-socials { display: flex; gap: 6px; margin-top: 12px; }
        .profile-socials span { width: 26px; height: 26px; border-radius: 50%; background: rgba(255,255,255,.14); display: inline-flex; align-items: center; justify-content: center; font-size: 11px; }

        @keyframes cardIn {
            0%   { opacity: 0; transform: translateY(40px) rotateX(-25deg) scale(.88); }
            100% { opacity: 1; transform: translateY(0) rotateX(0) scale(1); }
        }
        .stack-out .stack-card { animation: cardOut .22s ease forwards; animation-delay: 0ms; }
        @keyframes cardOut {
            0%   { opacity: 1; transform: translateY(0) scale(1); filter: blur(0); }
            100% { opacity: 0; transform: translateY(-24px) scale(.92); filter: blur(4px); }
        }

        @media (max-width: 1023px) {
            .stack-3d { animation: none; transform: rotateX(0) rotateY(0); }
            .stack-inner { width: 280px; }
        }

        /* ============ Pillar preview subtle animations (only when card is in view) ============ */
        .pp-live-dot { display:inline-block; width:6px; height:6px; border-radius:9999px; will-change: transform, opacity; }
        .pp-in-view .pp-live-dot { animation: ppLiveDot 1.8s ease-in-out infinite; }
        @keyframes ppLiveDot { 0%,100% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.45); opacity: .55; } }

        .pp-qr-wrap { position: relative; overflow: hidden; }
        .pp-qr-wrap::after {
            content: ''; position: absolute; left: 6%; right: 6%; top: 2px; height: 2px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,.9), transparent);
            box-shadow: 0 0 6px rgba(255,255,255,.55);
            border-radius: 2px; pointer-events: none; opacity: 0; will-change: transform, opacity;
        }
        .pp-in-view .pp-qr-wrap::after { animation: ppQrScan 2.6s ease-in-out infinite; }
        @keyframes ppQrScan {
            0%   { transform: translateY(0);    opacity: 0; }
            15%  { opacity: 1; }
            50%  { transform: translateY(38px); opacity: 1; }
            85%  { opacity: 1; }
            100% { transform: translateY(0);    opacity: 0; }
        }
        .pp-nfc-pulse { will-change: transform; }
        .pp-in-view .pp-nfc-pulse { animation: ppNfcPulse 2.2s ease-in-out infinite; }
        @keyframes ppNfcPulse { 0%,100% { transform: scale(1); } 50% { transform: scale(1.12); } }

        .pp-tip-card { will-change: transform, opacity; }
        .pp-in-view .pp-tip-card { animation: ppTipSlide 6.5s ease-in-out infinite; }
        @keyframes ppTipSlide {
            0%, 6%    { transform: translateX(-14px); opacity: 0; }
            16%, 82%  { transform: translateX(0);     opacity: 1; }
            94%, 100% { transform: translateX(-6px);  opacity: .85; }
        }
        .pp-dm-bubble { transform-origin: left bottom; will-change: transform; }
        .pp-in-view .pp-dm-bubble { animation: ppDmBreath 3.6s ease-in-out infinite 1.2s; }
        @keyframes ppDmBreath { 0%,100% { transform: scale(1); } 50% { transform: scale(1.04); } }

        .pp-coach-arc { stroke-dashoffset: 97.39; }
        .pp-in-view .pp-coach-arc { animation: ppCoachFill 1.8s cubic-bezier(.34,1.56,.64,1) .15s both; }
        @keyframes ppCoachFill { from { stroke-dashoffset: 97.39; } to { stroke-dashoffset: 12.66; } }
        .pp-coach-num { will-change: transform; }
        .pp-in-view .pp-coach-num { animation: ppCoachPulse 3.4s ease-in-out infinite 1.6s; }
        @keyframes ppCoachPulse { 0%,100% { transform: scale(1); } 50% { transform: scale(1.08); } }
        .pp-coach-bar { transform-origin: bottom; transform: scaleY(0); will-change: transform; }
        .pp-in-view .pp-coach-bar { animation: ppBarRise 1.1s cubic-bezier(.34,1.56,.64,1) both; }
        @keyframes ppBarRise { from { transform: scaleY(0); } to { transform: scaleY(1); } }

        /* ============ Audience card subtle animations (only when card is in view) ============ */
        .aud-blob { will-change: transform; }
        .aud-in-view .aud-blob { animation: audBlobDrift 9s ease-in-out infinite; }
        @keyframes audBlobDrift {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50%      { transform: translate(-6%, 4%) scale(1.08); }
        }

        .aud-icon { position: relative; overflow: hidden; will-change: transform; }
        .aud-icon::after {
            content: ''; position: absolute; inset: 0;
            background: linear-gradient(120deg, transparent 30%, rgba(255,255,255,.55) 50%, transparent 70%);
            transform: translateX(-120%);
            pointer-events: none;
        }
        .aud-in-view .aud-icon::after { animation: audIconShimmer 3.6s ease-in-out infinite; }
        @keyframes audIconShimmer {
            0%, 60% { transform: translateX(-120%); }
            85%     { transform: translateX(120%); }
            100%    { transform: translateX(120%); }
        }
        .aud-in-view .aud-icon > i { animation: audIconBob 4.2s ease-in-out infinite; }
        @keyframes audIconBob {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50%      { transform: translateY(-2px) rotate(-4deg); }
        }

        .aud-arrow { display: inline-block; will-change: transform; }
        .aud-in-view .aud-arrow { animation: audArrowNudge 2.4s ease-in-out infinite; }
        @keyframes audArrowNudge {
            0%, 60%, 100% { transform: translateX(0); }
            75%           { transform: translateX(4px); }
            90%           { transform: translateX(0); }
        }

        /* ============ Reduced motion ============ */
        @media (prefers-reduced-motion: reduce) {
            .reveal, .aurora b, .float-a, .float-b, .float-c, .wiggle, .spin-slow,
            .marquee, .marquee-rev, .eq i, .pulse-dot, .ring-pulse,
            .draw-line, .grad-text, .drift-a, .drift-b, .pop-in, .btn-glow::after,
            .pp-live-dot, .pp-nfc-pulse, .pp-tip-card, .pp-dm-bubble, .pp-coach-num, .pp-coach-bar,
            .aud-blob, .aud-arrow {
                animation: none !important; transition: none !important; transform: none !important; opacity: 1 !important;
            }
            .aud-in-view .aud-icon::after { animation: none !important; transform: translateX(-120%) !important; }
            .aud-in-view .aud-icon > i { animation: none !important; transform: none !important; }
            .pp-in-view .pp-qr-wrap::after { animation: none !important; opacity: 0 !important; }
            .pp-coach-arc { stroke-dashoffset: 12.66 !important; animation: none !important; }
            .draw-line { stroke-dashoffset: 0 !important; }
        }

        /* Make <picture> transparent to layout so existing img selectors / flex / grid rules still apply. */
        picture { display: contents; }

        /* ============ Focus ============ */
        a:focus-visible, button:focus-visible { outline: 2px solid var(--c2); outline-offset: 3px; border-radius: 8px; }

        /* ============ Phone frame ============ */
        .phone {
            width: 280px; aspect-ratio: 9/19; border-radius: 38px;
            background: #08020f; padding: 9px;
            box-shadow: 0 28px 80px -20px rgba(61,107,255,.45), 0 0 0 1px rgba(255,255,255,.06);
        }
        .phone-screen { width:100%; height:100%; border-radius: 30px; overflow: hidden; position: relative; }
        .notch { position: absolute; top: 8px; left: 50%; transform: translateX(-50%); width: 78px; height: 18px; background: #08020f; border-radius: 12px; z-index: 10; }

        /* ============ Hero phone mockup ============ */
        .hero-phone-wrap {
            position: relative;
            width: 320px; max-width: 92%;
            margin: 0 auto;
            transform-style: preserve-3d;
            transition: transform .25s cubic-bezier(.16,1,.3,1);
            will-change: transform;
        }
        .hero-phone {
            position: relative;
            width: 100%; aspect-ratio: 10/20;
            border-radius: 46px;
            padding: 10px;
            background: linear-gradient(160deg,#1a1024 0%,#08020f 60%,#1a1024 100%);
            box-shadow:
                0 50px 100px -30px rgba(61,107,255,.55),
                0 0 0 1.5px rgba(255,255,255,.08),
                inset 0 1px 0 rgba(255,255,255,.10);
        }
        .hero-phone::before {
            /* Side power button */
            content:""; position: absolute; right: -2px; top: 28%;
            width: 3px; height: 56px; border-radius: 2px;
            background: linear-gradient(180deg,#2a1640,#0d0518);
        }
        .hero-phone::after {
            /* Side volume buttons (combined illusion) */
            content:""; position: absolute; left: -2px; top: 22%;
            width: 3px; height: 36px; border-radius: 2px;
            background: linear-gradient(180deg,#2a1640,#0d0518);
            box-shadow: 0 56px 0 0 #1a0c2c, 0 56px 0 0px #1a0c2c;
        }
        .hero-phone-screen {
            position: relative;
            width: 100%; height: 100%;
            border-radius: 36px; overflow: hidden;
            background: var(--phone-bg, linear-gradient(140deg,#3d6bff,#1bd4d9,#22d3ee));
            transition: background 1.2s ease;
        }
        .hero-phone-screen::before {
            /* Animated wallpaper sheen */
            content:""; position: absolute; inset: -30%;
            background: radial-gradient(closest-side, rgba(255,255,255,.22), transparent 70%);
            animation: wallSheen 12s ease-in-out infinite;
            pointer-events: none;
        }
        .hero-phone-screen::after {
            /* Soft tint overlay matching role */
            content:""; position: absolute; inset: 0;
            background: linear-gradient(180deg, transparent 50%, rgba(0,0,0,.35));
            pointer-events: none;
        }
        @keyframes wallSheen {
            0%,100% { transform: translate(-12%,-8%) scale(1); opacity:.9; }
            50%     { transform: translate(14%,12%) scale(1.15); opacity:.55; }
        }
        .hero-notch {
            position: absolute; top: 14px; left: 50%; transform: translateX(-50%);
            width: 92px; height: 22px; background: #050108;
            border-radius: 14px; z-index: 20;
            box-shadow: inset 0 -1px 0 rgba(255,255,255,.05);
        }
        .hero-notch::after {
            content:""; position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
            width: 7px; height: 7px; border-radius: 50%;
            background: #1a0a2a; box-shadow: inset 0 0 0 1px rgba(255,255,255,.18);
        }
        .hero-phone-content {
            position: absolute; inset: 0;
            padding: 44px 14px 18px;
            display: flex; flex-direction: column; gap: 10px;
            overflow: hidden;
            z-index: 5;
        }
        .hero-phone-content .stack-card {
            background: rgba(255,255,255,.18);
            border-color: rgba(255,255,255,.28);
            border-radius: 16px;
            padding: 9px 11px;
            gap: 10px;
        }
        @supports (backdrop-filter: blur(14px)) {
            .hero-phone-content .stack-card {
                background: linear-gradient(135deg, rgba(255,255,255,.25) 0%, rgba(255,255,255,.1) 100%);
                backdrop-filter: blur(20px) saturate(150%);
                -webkit-backdrop-filter: blur(20px) saturate(150%);
            }
        }
        .hero-phone-content .stack-card.is-profile { padding: 12px; gap: 8px; }
        .hero-phone-content .stack-card .card-title { font-size: 12px; }
        .hero-phone-content .stack-card .card-sub { font-size: 10px; }
        .hero-phone-content .stack-card .card-icon { width: 30px; height: 30px; font-size: 12px; border-radius: 9px; }
        .hero-phone-content .stack-card .card-thumb { width: 38px; height: 38px; border-radius: 9px; }
        .hero-phone-content .profile-avatar { width: 42px; height: 42px; border-width: 2px; }
        .hero-phone-content .profile-handle { font-size: 13px; }
        .hero-phone-content .profile-tag { font-size: 10px; }
        .hero-phone-content .profile-socials { margin-top: 8px; gap: 5px; }
        .hero-phone-content .profile-socials span { width: 22px; height: 22px; font-size: 10px; }

        /* On mobile and tablet, beef up the hero so it fills the viewport
           and never leaves a sparse gap below the phone, no matter which
           role is currently rotating. */
        @media (max-width: 1023px) {
            .hero-phone-wrap { width: 300px; max-width: 88vw; transform: none !important; }
        }
        @media (max-width: 640px) {
            .hero-phone-wrap { width: 320px; max-width: 86vw; }
        }

        /* Phone stage gets enough breathing room on desktop so the floating
           cards have somewhere to live without overlapping the phone or each
           other. */
        .hero-phone-stage { min-height: 560px; }
        @media (min-width: 1024px) {
            .hero-phone-stage { min-height: 700px; padding: 36px 0; gap: 32px; }
        }
        @media (min-width: 1280px) {
            .hero-phone-stage { gap: 44px; }
        }

        /* Phone "frame" — wraps the phone + its absolutely-positioned float
           cards so the cards anchor to the phone (not the wider stage). This
           leaves the right side of the stage clean for the vertical tile rail. */
        .hero-phone-frame {
            position: relative;
            display: flex; align-items: center; justify-content: center;
        }
        @media (min-width: 1024px) {
            .hero-phone-frame { width: 320px; flex: 0 0 320px; }
        }

        /* ============ Hero floating info cards ============ */
        .float-card {
            position: absolute;
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 16px;
            padding: 10px 12px;
            box-shadow: 0 18px 40px -16px rgba(0,0,0,.55), inset 0 1px 0 rgba(255,255,255,.15);
            z-index: 11;
            color: #fff;
        }
        @supports (backdrop-filter: blur(18px)) {
            .float-card {
                background: linear-gradient(135deg, rgba(255,255,255,.1) 0%, rgba(255,255,255,.03) 100%);
                backdrop-filter: blur(24px) saturate(150%);
                -webkit-backdrop-filter: blur(24px) saturate(150%);
            }
        }
        .float-card-label {
            display: block;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: #9ca3af;
            line-height: 1.1;
        }
        /* Desktop placement — all cards live on the left side of the phone so
           the right side stays clean for the vertical interactive tile rail.
           Three loose lanes (close / mid / far) keep them from crowding. */
        @media (min-width: 1024px) {
            /* Left lane (further-out cards) */
            .float-card--visitors  { top: 36px;    left: -158px; width: 158px; box-shadow: 0 22px 50px -18px rgba(27,212,217,.45); }
            .float-card--toplink   { top: 188px;   left: -168px; width: 180px; box-shadow: 0 22px 50px -18px rgba(255,0,51,.35); }
            .float-card--coach     { top: 360px;   left: -178px; width: 200px; box-shadow: 0 22px 50px -18px rgba(61,107,255,.5); }
            .float-card--revenue   { bottom: -8px; left: -158px; width: 168px; box-shadow: 0 22px 50px -18px rgba(255,138,60,.4); }
            /* Right lane (sit just outside the phone, only the inner edge brushes the bezel
               — never overlaps the screen content). Mirrors the left lane offsets. */
            .float-card--follower  { top: -16px;   right: -120px; left: auto; width: 188px; box-shadow: 0 22px 50px -18px rgba(236,72,153,.4); }
            .float-card--qr        { top: 240px;   right: -110px; left: auto; width: 178px; box-shadow: 0 22px 50px -18px rgba(61,107,255,.4); }
            .float-card--conv      { bottom: 92px; right: -110px; left: auto; width: 178px; box-shadow: 0 22px 50px -18px rgba(27,212,217,.35); }
        }

        /* ============ Mobile-only condensed stats row ============ */
        .hero-mobile-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0,1fr));
            gap: 8px;
        }
        .hero-mstat {
            display: flex; flex-direction: column; align-items: flex-start;
            gap: 2px;
            padding: 10px 10px;
            border-radius: 14px;
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.12);
        }
        @supports (backdrop-filter: blur(14px)) {
            .hero-mstat {
                background: linear-gradient(135deg, rgba(255,255,255,.1) 0%, rgba(255,255,255,.03) 100%);
                backdrop-filter: blur(20px) saturate(140%);
                -webkit-backdrop-filter: blur(20px) saturate(140%);
                box-shadow: inset 0 1px 0 rgba(255,255,255,.15);
            }
        }
        .hero-mstat .lbl {
            font-size: 9px; font-weight: 800; letter-spacing: .1em;
            text-transform: uppercase; color: #9ca3af;
            display: inline-flex; align-items: center; gap: 4px;
        }
        .hero-mstat .val { font-size: 15px; font-weight: 800; line-height: 1; color: #fff; }
        .hero-mstat .sub { font-size: 9px; color: #9ca3af; }
        @media (max-width: 380px) {
            .hero-mobile-stats { grid-template-columns: repeat(2, minmax(0,1fr)); }
        }

        /* ============ Per-theme phone layouts ============ */
        .hp-profile-mini {
            display: flex; align-items: center; gap: 9px;
            padding: 8px 10px; border-radius: 14px;
            background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.28);
        }
        @supports (backdrop-filter: blur(14px)) {
            .hp-profile-mini {
                background: linear-gradient(135deg, rgba(255,255,255,.2) 0%, rgba(255,255,255,.05) 100%);
                backdrop-filter: blur(20px) saturate(140%);
                -webkit-backdrop-filter: blur(20px) saturate(140%);
                box-shadow: inset 0 1px 0 rgba(255,255,255,.15);
            }
        }
        .hp-profile-mini img { width: 34px; height: 34px; border-radius: 50%; object-fit: cover; border: 2px solid rgba(255,255,255,.35); flex-shrink: 0; }
        .hp-profile-mini .hpm-h { font-size: 12px; font-weight: 700; line-height: 1.1; }
        .hp-profile-mini .hpm-t { font-size: 9px; opacity: .8; margin-top: 2px; }
        .hp-profile-mini .hpm-verified { color:#7dd3fc; font-size: 10px; margin-left: 2px; }

        /* ===== Per-theme unique profile blocks ===== */
        /* Shared base for all themed profile cards. */
        .hp-prof {
            position: relative; border-radius: 16px;
            padding: 10px 11px;
            background: rgba(255,255,255,.18);
            border: 1px solid rgba(255,255,255,.28);
            overflow: hidden;
        }
        @supports (backdrop-filter: blur(14px)) {
            .hp-prof {
                background: linear-gradient(135deg, rgba(255,255,255,.2) 0%, rgba(255,255,255,.05) 100%);
                backdrop-filter: blur(20px) saturate(140%);
                -webkit-backdrop-filter: blur(20px) saturate(140%);
                box-shadow: inset 0 1px 0 rgba(255,255,255,.15);
            }
        }
        .hp-prof .prow { display:flex; align-items:center; gap: 10px; }
        .hp-prof .pav { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; border: 2px solid rgba(255,255,255,.4); flex-shrink: 0; }
        .hp-prof .ph  { font-size: 13px; font-weight: 800; line-height: 1.1; display:flex; align-items:center; gap:4px; }
        .hp-prof .pt  { font-size: 10px; opacity: .85; margin-top: 2px; }
        .hp-prof .pvd { color:#7dd3fc; font-size: 10px; }
        /* Creator — neon ring + subscribers */
        .hp-prof.var-creator .pav {
            padding: 2px; background: conic-gradient(from 0deg,#ff0033,#ff8a3c,#ffc845,#ff0033);
            border: 0; width: 48px; height: 48px;
        }
        .hp-prof.var-creator .pstats { display:flex; gap:10px; margin-top:9px; font-size:10px; }
        .hp-prof.var-creator .pstats .ps { display:flex; flex-direction:column; }
        .hp-prof.var-creator .pstats .sv { font-weight:800; font-size:12px; line-height:1; }
        .hp-prof.var-creator .pstats .sl { opacity:.75; font-size:9px; }
        /* Artist — color palette swatches */
        .hp-prof.var-artist .pav { border-radius: 14px; width: 46px; height: 46px; }
        .hp-prof.var-artist .swatch { display:flex; gap:3px; margin-top:8px; }
        .hp-prof.var-artist .swatch i { width: 18px; height: 10px; border-radius: 3px; display:inline-block; }
        /* Musician — now playing tag */
        .hp-prof.var-music .pav { border: 2px solid #1ed760; }
        .hp-prof.var-music .npill {
            margin-top: 7px; display:inline-flex; align-items:center; gap:5px;
            font-size: 9px; font-weight: 800; padding: 3px 7px; border-radius: 999px;
            background: rgba(30,215,96,.22); color:#86efac; border:1px solid rgba(30,215,96,.35);
        }
        .hp-prof.var-music .npill i { animation: musicPulse 1.8s ease-in-out infinite; }
        /* Business — LinkedIn-style + availability dot */
        .hp-prof.var-business .pav { border-radius: 12px; }
        .hp-prof.var-business .bbadges { display:flex; gap:4px; margin-top:7px; flex-wrap:wrap; }
        .hp-prof.var-business .bbadge {
            font-size: 9px; font-weight: 800; padding: 3px 7px; border-radius: 6px;
            background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.3);
        }
        .hp-prof.var-business .online { width:10px; height:10px; border-radius:50%; background:#1ed760; border:2px solid rgba(255,255,255,.9); position:absolute; bottom:-1px; right:-1px; }
        .hp-prof.var-business .avwrap { position:relative; }
        /* Coach — stat chips inline */
        .hp-prof.var-coach .pav {
            padding: 2px; background: linear-gradient(135deg,#1bd4d9,#3d6bff);
            border: 0;
        }
        .hp-prof.var-coach .chips { display:flex; gap:5px; margin-top:7px; }
        .hp-prof.var-coach .chip {
            font-size: 9px; font-weight: 800; padding: 3px 7px; border-radius: 999px;
            background: rgba(0,0,0,.35); border: 1px solid rgba(255,255,255,.28);
            display:flex; align-items:center; gap:4px;
        }
        .hp-prof.var-coach .chip i { color:#ffc845; font-size:8px; }
        /* Photographer — location pin + camera gear row */
        .hp-prof.var-photo .pav { border-radius: 10px; width: 46px; height: 46px; }
        .hp-prof.var-photo .loc { display:flex; align-items:center; gap:4px; font-size:10px; margin-top:6px; }
        .hp-prof.var-photo .loc i { color:#22d3ee; }
        .hp-prof.var-photo .gear { display:flex; gap:4px; margin-top:6px; flex-wrap:wrap; }
        .hp-prof.var-photo .gr { font-size: 9px; padding: 2px 6px; border-radius: 5px; background: rgba(0,0,0,.35); border:1px solid rgba(255,255,255,.22); }
        /* Social — follower counts strip */
        .hp-prof.var-social .pav { padding: 2px; background: conic-gradient(from 0deg,#ffc845,#e94e8c,#3d6bff,#ffc845); border: 0; animation: spinSlow 14s linear infinite; }
        .hp-prof.var-social .fgrid { display:grid; grid-template-columns: repeat(3, 1fr); gap: 4px; margin-top: 8px; }
        .hp-prof.var-social .fg { text-align:center; padding: 4px 2px; border-radius: 8px; background: rgba(0,0,0,.32); }
        .hp-prof.var-social .fg .fv { font-size: 11px; font-weight: 800; line-height: 1; }
        .hp-prof.var-social .fg .fl { font-size: 8px; opacity: .8; margin-top: 2px; text-transform: uppercase; letter-spacing: .05em; }
        /* Podcaster — on-air indicator */
        .hp-prof.var-podcast .pav { border-radius: 12px; width: 46px; height: 46px; }
        .hp-prof.var-podcast .air {
            display:inline-flex; align-items:center; gap:5px;
            margin-top: 7px; font-size: 9px; font-weight: 800;
            padding: 3px 7px; border-radius: 999px;
            background: rgba(239,68,68,.22); color:#fca5a5; border:1px solid rgba(239,68,68,.4);
        }
        .hp-prof.var-podcast .air i { width:6px; height:6px; border-radius:50%; background:#ef4444; animation: pulseDot 1.1s ease-in-out infinite; }
        @keyframes pulseDot { 0%,100% { opacity:1; transform:scale(1); } 50% { opacity:.5; transform:scale(1.35); } }

        /* --- MUSIC theme (Musician) --- */
        .hp-music-card {
            border-radius: 18px; overflow: hidden; position: relative;
            background: rgba(0,0,0,.28); border: 1px solid rgba(255,255,255,.22);
        }
        .hp-music-cover { width: 100%; height: 110px; object-fit: cover; display: block; }
        .hp-music-meta { padding: 8px 10px; display: flex; align-items: center; gap: 8px; }
        .hp-music-meta .mt { flex: 1; min-width: 0; }
        .hp-music-meta .mt-t { font-size: 12px; font-weight: 800; line-height: 1.1; }
        .hp-music-meta .mt-s { font-size: 9px; opacity: .8; margin-top: 2px; }
        .hp-music-play {
            width: 30px; height: 30px; border-radius: 50%;
            background: #fff; color:#0a0a14; display:flex; align-items:center; justify-content:center;
            box-shadow: 0 6px 18px -4px rgba(0,0,0,.5);
            animation: musicPulse 2.4s ease-in-out infinite;
        }
        @keyframes musicPulse { 0%,100% { transform: scale(1); } 50% { transform: scale(1.07); } }
        .hp-music-eq { position: absolute; left: 8px; bottom: 8px; display:flex; align-items:flex-end; gap:2px; height:14px; color:#fff; }
        .hp-music-eq i { display:inline-block; width:3px; background: currentColor; border-radius:2px; animation: eq 1.1s ease-in-out infinite; }
        .hp-music-eq i:nth-child(1){ height:35%; }
        .hp-music-eq i:nth-child(2){ height:80%; animation-delay:.15s; }
        .hp-music-eq i:nth-child(3){ height:55%; animation-delay:.3s; }
        .hp-music-eq i:nth-child(4){ height:90%; animation-delay:.1s; }
        .hp-track { display:flex; align-items:center; gap:8px; padding: 6px 9px; border-radius: 12px;
            background: rgba(255,255,255,.16); border: 1px solid rgba(255,255,255,.22); font-size: 11px; }
        .hp-track .num { width: 16px; text-align: center; font-weight: 800; opacity: .8; font-size: 10px; }
        .hp-track .nm  { flex: 1; min-width: 0; font-weight: 700; }
        .hp-track .du  { font-size: 10px; opacity: .75; }
        .hp-pill-row { display: flex; gap: 6px; }
        .hp-pill { flex:1; padding: 7px 8px; border-radius: 12px; font-size: 10px; font-weight: 800; text-align: center;
            background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.28); display:flex; align-items:center; justify-content:center; gap:5px; }

        /* --- GALLERY theme (Artist) --- */
        .hp-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 5px; }
        .hp-grid-3 .gi { aspect-ratio: 1/1; border-radius: 10px; overflow: hidden; position: relative;
            border: 1px solid rgba(255,255,255,.2); }
        .hp-grid-3 .gi img { width: 100%; height: 100%; object-fit: cover; transition: transform .6s; }
        .hp-grid-3 .gi:hover img { transform: scale(1.1); }
        .hp-grid-3 .gi .badge { position: absolute; top: 4px; left: 4px; font-size: 7px; font-weight: 800;
            padding: 2px 4px; border-radius: 4px; background: rgba(0,0,0,.6); letter-spacing: .05em; text-transform: uppercase; }
        .hp-cta {
            display: flex; align-items: center; justify-content: center; gap: 6px;
            padding: 9px 12px; border-radius: 14px;
            background: #fff; color: #0a0a14;
            font-size: 12px; font-weight: 800;
            box-shadow: 0 8px 22px -8px rgba(0,0,0,.55);
        }
        .hp-cta.dark { background: rgba(0,0,0,.55); color: #fff; border: 1px solid rgba(255,255,255,.25); }

        /* --- BUSINESS theme --- */
        .hp-biz-cta {
            border-radius: 16px; padding: 11px 12px;
            background: linear-gradient(135deg, rgba(0,0,0,.55), rgba(0,0,0,.28));
            border: 1px solid rgba(255,255,255,.22);
            display:flex; align-items:center; gap:10px;
        }
        .hp-biz-cta .ic { width: 34px; height: 34px; border-radius: 12px; display:flex; align-items:center; justify-content:center; background: rgba(255,255,255,.92); color:#0a0a14; flex-shrink:0; }
        .hp-biz-cta .bd { flex:1; min-width:0; }
        .hp-biz-cta .bt { font-size: 12px; font-weight: 800; }
        .hp-biz-cta .bs { font-size: 10px; opacity: .85; margin-top: 2px; }
        .hp-svc-list { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
        .hp-svc { padding: 8px 9px; border-radius: 12px; background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.22); }
        .hp-svc .st { font-size: 10px; font-weight: 800; }
        .hp-svc .sp { font-size: 11px; font-weight: 800; margin-top: 2px; }
        .hp-stat-row { display:flex; gap: 6px; }
        .hp-stat { flex:1; text-align:center; padding: 7px 4px; border-radius: 11px; background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.22); }
        .hp-stat .sv { font-size: 13px; font-weight: 800; line-height:1; }
        .hp-stat .sl { font-size: 8px; opacity: .8; margin-top: 3px; text-transform: uppercase; letter-spacing: .07em; }

        /* --- COACH theme --- */
        .hp-quote {
            position: relative; padding: 11px 12px; padding-left: 26px;
            border-radius: 14px;
            background: rgba(255,255,255,.16); border: 1px solid rgba(255,255,255,.24);
            font-size: 11px; font-style: italic; line-height: 1.35;
        }
        .hp-quote::before { content:"\201C"; position:absolute; left: 8px; top: 0; font-size: 28px; line-height: 1; opacity:.7; font-style: normal; }
        .hp-quote .qa { display: flex; align-items: center; gap: 6px; margin-top: 7px; font-style: normal; font-size: 10px; opacity: .85; }
        .hp-quote .qa i { color:#ffc845; }

        /* --- PORTFOLIO theme (Photographer) --- */
        .hp-feature {
            position: relative; border-radius: 14px; overflow: hidden;
            border: 1px solid rgba(255,255,255,.2); aspect-ratio: 16/10;
        }
        .hp-feature img { width:100%; height:100%; object-fit: cover; }
        .hp-feature .lbl { position:absolute; left:8px; bottom: 8px; right: 8px; display:flex; justify-content:space-between; font-size: 10px; font-weight: 800; }
        .hp-feature .lbl span { background: rgba(0,0,0,.55); padding: 3px 6px; border-radius: 6px; }
        .hp-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 5px; }
        .hp-grid-2 .gi { aspect-ratio: 4/3; border-radius: 10px; overflow: hidden; border: 1px solid rgba(255,255,255,.2); }
        .hp-grid-2 .gi img { width:100%; height:100%; object-fit: cover; }

        /* --- PODCAST theme --- */
        .hp-pod-card {
            border-radius: 16px; padding: 9px;
            background: rgba(0,0,0,.32); border: 1px solid rgba(255,255,255,.22);
            display:flex; gap: 9px; align-items: center;
        }
        .hp-pod-card img { width: 56px; height: 56px; border-radius: 10px; object-fit: cover; flex-shrink: 0; }
        .hp-pod-card .pm { flex:1; min-width:0; }
        .hp-pod-card .pe { font-size: 9px; font-weight: 800; opacity: .8; letter-spacing: .07em; text-transform: uppercase; }
        .hp-pod-card .pt { font-size: 12px; font-weight: 800; line-height: 1.15; margin-top: 1px; }
        .hp-pod-card .pd { font-size: 10px; opacity: .8; margin-top: 3px; }
        .hp-pod-card .pp {
            width: 32px; height: 32px; border-radius: 50%;
            background: #fff; color:#0a0a14; display:flex; align-items:center; justify-content:center; flex-shrink:0;
            animation: musicPulse 2.4s ease-in-out infinite;
        }
        .hp-wave { display:flex; align-items:center; gap: 6px; padding: 5px 8px; border-radius: 10px;
            background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.22); font-size: 10px; }
        .hp-wave svg { flex:1; height: 14px; }

        /* --- SOCIAL theme (Influencer) --- */
        .hp-stories { display:flex; gap: 8px; overflow: hidden; padding: 2px 0; }
        .hp-story { flex-shrink: 0; }
        .hp-story .ring {
            width: 38px; height: 38px; border-radius: 50%; padding: 2px;
            background: conic-gradient(from 0deg, #ffc845, #e94e8c, #3d6bff, #ffc845);
            animation: spinSlow 14s linear infinite;
        }
        .hp-story .ring img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 2px solid rgba(0,0,0,.35); }
        .hp-story .nm { font-size: 8px; text-align: center; margin-top: 3px; opacity: .9; }
        .hp-reel {
            position: relative; border-radius: 14px; overflow: hidden;
            border: 1px solid rgba(255,255,255,.2); aspect-ratio: 16/9;
        }
        .hp-reel img { width:100%; height:100%; object-fit: cover; }
        .hp-reel .ov { position: absolute; inset: 0; background: linear-gradient(180deg, transparent 50%, rgba(0,0,0,.55)); }
        .hp-reel .play { position: absolute; left: 50%; top: 50%; transform: translate(-50%,-50%); width: 36px; height: 36px; border-radius: 50%; background: rgba(255,255,255,.85); color:#0a0a14; display:flex; align-items:center; justify-content:center; }
        .hp-reel .lb { position:absolute; left: 8px; bottom: 7px; right: 8px; display:flex; gap:6px; align-items:center; font-size: 10px; font-weight: 800; }
        .hp-reel .lb span { background: rgba(0,0,0,.45); padding: 2px 5px; border-radius: 5px; }

        /* Merch row (music theme) */
        .hp-merch { display:flex; align-items:center; gap: 9px; padding: 7px 9px; border-radius: 14px;
            background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.22); }
        .hp-merch img { width: 40px; height: 40px; border-radius: 10px; object-fit: cover; flex-shrink:0; }
        .hp-merch .mi { flex:1; min-width:0; }
        .hp-merch .mt { font-size: 11px; font-weight: 800; line-height:1.1; }
        .hp-merch .ms { font-size: 9px; opacity:.8; margin-top: 2px; }
        .hp-merch .mp { font-size: 12px; font-weight: 900; padding: 4px 8px; border-radius: 8px; background: rgba(0,0,0,.5); flex-shrink:0; }

        /* Episode row (podcast theme) */
        .hp-ep { display:flex; align-items:center; gap: 8px; padding: 6px 9px; border-radius: 11px;
            background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.22); font-size: 10px; }
        .hp-ep .epn { font-weight:800; opacity:.8; width: 24px; }
        .hp-ep .ept { flex:1; min-width:0; font-weight:700; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .hp-ep .epd { opacity:.75; font-size: 9px; }

        /* 4-up post grid (social theme) */
        .hp-grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 4px; }
        .hp-grid-4 .gi { aspect-ratio: 1/1; border-radius: 8px; overflow: hidden; position: relative; border: 1px solid rgba(255,255,255,.2); }
        .hp-grid-4 .gi img { width:100%; height:100%; object-fit: cover; }
        .hp-grid-4 .gi .hrt { position:absolute; left:3px; bottom:3px; font-size: 7px; font-weight: 800;
            padding: 2px 4px; border-radius: 4px; background: rgba(0,0,0,.6); display:inline-flex; align-items:center; gap:3px; }
        .hp-grid-4 .gi .hrt i { color:#ef4444; font-size: 6px; }

        /* Theme entrance */
        .theme-block { opacity: 0; animation: cardIn .55s cubic-bezier(.34,1.56,.64,1) both; animation-delay: var(--d, 0ms); }

        /* ============ Build-section — block list + phone ============ */
        .build-list {
            max-height: 520px; overflow-y: auto;
            padding-right: 4px;
            mask-image: linear-gradient(180deg, transparent 0, #000 14px, #000 calc(100% - 22px), transparent 100%);
            -webkit-mask-image: linear-gradient(180deg, transparent 0, #000 14px, #000 calc(100% - 22px), transparent 100%);
            scrollbar-width: thin; scrollbar-color: rgba(61,107,255,.5) transparent;
        }
        .build-list::-webkit-scrollbar { width: 6px; }
        .build-list::-webkit-scrollbar-thumb { background: rgba(61,107,255,.45); border-radius: 6px; }
        .build-list::-webkit-scrollbar-track { background: transparent; }
        .build-row {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 12px; border-radius: 14px;
            background: rgba(255,255,255,.05);
            border: 1px solid rgba(255,255,255,.10);
            transition: transform .25s cubic-bezier(.16,1,.3,1), background .25s, border-color .25s;
        }
        .build-row:hover { transform: translateX(3px); background: rgba(255,255,255,.08); }
        .build-row.is-selected {
            background: rgba(233,78,140,.12);
            border-color: rgba(233,78,140,.45);
            box-shadow: 0 0 0 1px rgba(233,78,140,.2), 0 10px 24px -12px rgba(233,78,140,.6);
        }
        .build-row.is-columns { align-items: stretch; }
        .bl-grip { color: #6b7280; font-size: 11px; flex-shrink: 0; cursor: grab; }
        .bl-ic { width: 36px; height: 36px; border-radius: 10px; display:flex; align-items:center; justify-content:center; font-size: 14px; flex-shrink: 0; }
        .bl-ic.sm { width: 28px; height: 28px; font-size: 12px; border-radius: 8px; }
        .bl-title { font-size: 12px; font-weight: 800; color: #fff; line-height: 1.15; }
        .bl-sub   { font-size: 10px; color: #9ca3af; margin-top: 2px; }
        .bl-thumb { width: 40px; height: 28px; border-radius: 7px; flex-shrink: 0;
            box-shadow: inset 0 0 0 1px rgba(255,255,255,.12); }
        .bl-thumb-dark { width: 40px; height: 28px; border-radius: 7px; flex-shrink: 0;
            background: linear-gradient(135deg,#1a0b2e,#3a0ca3); color:#fff;
            display:flex; align-items:center; justify-content:center; font-size: 9px;
            box-shadow: inset 0 0 0 1px rgba(255,255,255,.12); }
        .bl-chip { font-size: 9px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase;
            padding: 3px 7px; border-radius: 999px;
            background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.18); color:#d1d5db; flex-shrink:0; }
        .bl-live { display:inline-flex; align-items:center; gap:4px; font-size:9px; font-weight:800;
            letter-spacing:.06em; text-transform:uppercase; padding: 3px 7px; border-radius: 999px;
            background: rgba(233,78,140,.2); color: #fda4af; border:1px solid rgba(233,78,140,.45); flex-shrink:0; }
        .bl-live .dot { width:6px; height:6px; border-radius:50%; background:#ef4444; animation: pulseDot 1.1s ease-in-out infinite; }
        .bl-eq { display:inline-flex; align-items:flex-end; gap:2px; height:14px; color:#1ed760; flex-shrink:0; }
        .bl-eq i { width: 3px; background: currentColor; border-radius:2px; animation: eq 1.1s ease-in-out infinite; display:inline-block; }
        .bl-eq i:nth-child(1){ height:40%; }
        .bl-eq i:nth-child(2){ height:90%; animation-delay:.15s; }
        .bl-eq i:nth-child(3){ height:60%; animation-delay:.3s; }
        .bl-eq i:nth-child(4){ height:100%; animation-delay:.1s; }
        .bl-mini-grid { display:grid; grid-template-columns: 1fr 1fr; gap:2px; width:24px; height:24px; flex-shrink:0; }
        .bl-mini-grid i { display:block; background: linear-gradient(135deg,#e94e8c,#3d6bff); border-radius: 3px; }
        .bl-mini-grid i:nth-child(2){ background: linear-gradient(135deg,#1bd4d9,#3d6bff); }
        .bl-mini-grid i:nth-child(3){ background: linear-gradient(135deg,#ff8a3c,#ffc845); }
        .bl-mini-grid i:nth-child(4){ background: linear-gradient(135deg,#3d6bff,#e94e8c); }
        .bl-date { display:flex; flex-direction:column; align-items:center; background:#fff; color:#0a0a14; border-radius:8px; padding:2px 6px 3px; flex-shrink:0; line-height:1; }
        .bl-date .mo { font-size:7px; font-weight:900; letter-spacing:.08em; color:#ef4444; }
        .bl-date .da { font-size:12px; font-weight:900; }
        .bl-socials { display:flex; gap:4px; flex-shrink:0; font-size: 11px; }
        .bl-socials i { opacity:.9; }
        .bl-map { width:40px; height:28px; border-radius:7px; flex-shrink:0; position:relative; overflow:hidden;
            background:
                radial-gradient(circle at 35% 45%, rgba(255,255,255,.25) 0 2px, transparent 3px),
                linear-gradient(135deg,#0f3a2a,#22d3ee 60%,#0a2540);
            box-shadow: inset 0 0 0 1px rgba(255,255,255,.12); }
        .bl-map::after { content:""; position:absolute; left:35%; top:40%; width:6px; height:6px; border-radius:50%; background:#ef4444; box-shadow: 0 0 0 3px rgba(239,68,68,.3); }
        .bl-count { display:flex; gap:3px; flex-shrink:0; font-family: ui-monospace, monospace; font-weight: 900; font-size: 10px; color:#fbbf24; }
        .bl-count span { background: rgba(251,191,36,.12); border:1px solid rgba(251,191,36,.35); padding: 2px 4px; border-radius: 4px; }
        .bl-chev { font-size: 10px; color:#90acff; flex-shrink:0; }
        .bl-stars { font-size: 8px; color: #ffc845; letter-spacing: 1px; flex-shrink:0; }
        .bl-col { flex:1; display:flex; align-items:center; gap:6px; padding: 4px 8px; background: rgba(255,255,255,.05);
            border: 1px dashed rgba(255,255,255,.18); border-radius: 10px; }
        .bl-col-t { font-size: 11px; font-weight: 700; color:#e5e7eb; }

        /* Build-section phone preview (biolink) */
        .bb-phone {
            width: 218px; aspect-ratio: 9/19; border-radius: 34px;
            background: #08020f; padding: 8px;
            box-shadow: 0 28px 70px -20px rgba(61,107,255,.55), 0 0 0 1px rgba(255,255,255,.08);
        }
        .bb-screen { position: relative; width:100%; height:100%; border-radius: 28px; overflow: hidden;
            background:
                radial-gradient(ellipse at 25% 0%,   rgba(61,107,255,.55) 0%, transparent 55%),
                radial-gradient(ellipse at 85% 38%,  rgba(233,78,140,.5)  0%, transparent 52%),
                radial-gradient(ellipse at 50% 100%, rgba(255,138,60,.45) 0%, transparent 48%),
                #0d0820; }
        .bb-notch { position:absolute; top: 7px; left:50%; transform: translateX(-50%); width:64px; height:14px; background:#08020f; border-radius:10px; z-index:20; }
        .bb-scroll { position:absolute; inset:28px 10px 10px; overflow:hidden; display:flex; flex-direction:column;
            scrollbar-width:none; z-index:2; mask-image:linear-gradient(180deg,transparent 0,#000 12px,#000 88%,transparent 100%); -webkit-mask-image:linear-gradient(180deg,transparent 0,#000 12px,#000 88%,transparent 100%); }
        .bb-scroll::-webkit-scrollbar { display:none; }
        .bb-prof { text-align:center; color:#fff; padding: 4px 0; }
        .bb-prof .bb-av { width: 48px; height:48px; border-radius:50%;
            background: linear-gradient(135deg,#3d6bff 0%,#e94e8c 100%);
            margin: 0 auto; display:flex; align-items:center; justify-content:center; font-size: 13px; font-weight: 900; color:#fff;
            border: 2.5px solid rgba(255,255,255,.55);
            box-shadow: 0 0 0 4px rgba(61,107,255,.25), 0 8px 20px -8px rgba(61,107,255,.7); }
        .bb-prof .bb-h { font-size: 11px; font-weight: 900; margin-top: 4px; }
        .bb-prof .bb-t { font-size: 8px; opacity: .85; margin-top: 1px; }
        .bb-prof .bb-soc { display:flex; justify-content:center; gap:7px; margin-top:5px; font-size:9px; color:#fff; opacity:.9; }
        .bb-hero { height: 54px; border-radius: 10px;
            background: linear-gradient(135deg,#1bd4d9 0%,#3d6bff 60%,#e94e8c 100%);
            box-shadow: inset 0 0 0 1px rgba(255,255,255,.25); position:relative; overflow:hidden; }
        .bb-hero::after { content:""; position:absolute; inset:0;
            background: radial-gradient(circle at 70% 30%, rgba(255,255,255,.4), transparent 50%); }
        .bb-btn { background: rgba(255,255,255,.94); color:#0a0a14; text-align:center; font-size:10px; font-weight:900;
            padding: 7px 8px; border-radius: 10px; display:flex; align-items:center; justify-content:center; gap:5px; }
        .bb-btn.is-accent { background: linear-gradient(135deg,#ff8a3c,#e94e8c); color:#fff; position:relative; }
        .bb-btn .bb-live { position:absolute; right:5px; top:50%; transform:translateY(-50%); display:inline-flex; align-items:center; gap:3px;
            font-size:7px; padding:2px 5px; border-radius:999px; background: rgba(0,0,0,.35); color:#fff; }
        .bb-btn .bb-live .dot { width:5px; height:5px; border-radius:50%; background:#ef4444; animation: pulseDot 1.1s ease-in-out infinite; }
        .bb-video { border-radius:10px; background: rgba(0,0,0,.45); color:#fff;
            display:flex; align-items:center; gap:6px; padding: 10px 9px; font-size:9px; font-weight:800;
            border: 1px solid rgba(255,255,255,.18); position: relative; }
        .bb-video i { width: 22px; height:22px; border-radius:50%; background:#fff; color:#0a0a14; display:flex; align-items:center; justify-content:center; font-size:8px; }
        .bb-audio { display:flex; align-items:center; gap:7px; padding: 6px 8px; border-radius: 10px;
            background: rgba(0,0,0,.35); border: 1px solid rgba(255,255,255,.2); color:#fff; }
        .bb-audio .ico { width: 22px; height:22px; border-radius:50%; background: rgba(30,215,96,.25); color:#1ed760; display:flex; align-items:center; justify-content:center; font-size: 10px; }
        .bb-audio .meta { flex:1; min-width:0; }
        .bb-audio .tt { font-size: 9px; font-weight: 800; line-height:1; }
        .bb-audio .ss { font-size: 8px; opacity: .75; margin-top: 2px; }
        .bb-audio .eq { display:inline-flex; align-items:flex-end; gap:2px; height:12px; color:#1ed760; }
        .bb-audio .eq i { width: 2px; background: currentColor; border-radius:1px; animation: eq 1.1s ease-in-out infinite; display:inline-block; }
        .bb-audio .eq i:nth-child(1){ height:40%; }
        .bb-audio .eq i:nth-child(2){ height:90%; animation-delay:.15s; }
        .bb-audio .eq i:nth-child(3){ height:55%; animation-delay:.3s; }
        .bb-audio .eq i:nth-child(4){ height:100%; animation-delay:.1s; }
        .bb-gal { display:grid; grid-template-columns: repeat(3, 1fr); gap: 3px; }
        .bb-gal i { display:block; aspect-ratio: 1/1; border-radius: 6px;
            background: linear-gradient(135deg,#e94e8c,#3d6bff); }
        .bb-gal i:nth-child(2){ background: linear-gradient(135deg,#1bd4d9,#3d6bff); }
        .bb-gal i:nth-child(3){ background: linear-gradient(135deg,#ffc845,#ff8a3c); }
        .bb-form { display:flex; gap: 4px; }
        .bb-form .fi { flex: 1; background: rgba(255,255,255,.9); color:#9ca3af; padding: 7px 8px; border-radius: 9px; font-size: 9px; font-weight: 700; text-align:left; }
        .bb-form .fb { background:#0a0a14; color:#fff; padding: 7px 10px; border-radius: 9px; font-size: 9px; font-weight: 900; }
        .bb-cal { display:flex; align-items:center; gap:7px; padding: 6px 8px; border-radius: 10px;
            background: rgba(255,255,255,.92); color:#0a0a14; }
        .bb-cal .dt { display:flex; flex-direction:column; align-items:center; line-height:1; }
        .bb-cal .dt .mo { font-size: 7px; font-weight: 900; color:#ef4444; letter-spacing:.08em; }
        .bb-cal .dt .da { font-size: 14px; font-weight: 900; }
        .bb-cal .mt { flex:1; min-width:0; }
        .bb-cal .mt .tt { font-size: 9px; font-weight: 800; }
        .bb-cal .mt .ss { font-size: 8px; opacity: .7; margin-top: 1px; }
        .bb-tip { display:flex; justify-content: space-between; align-items:center;
            padding: 7px 9px; border-radius: 10px; font-size: 9px; font-weight: 800; color:#fff;
            background: linear-gradient(135deg, rgba(236,72,153,.35), rgba(61,107,255,.35));
            border: 1px solid rgba(255,255,255,.2); }
        .bb-tip .amts { font-weight: 900; font-size: 10px; display:inline-flex; gap:4px; align-items:center; }
        .bb-tip .amts i { opacity:.5; font-style: normal; }
        .bb-map { display:flex; align-items:center; gap:5px; padding: 6px 9px; border-radius: 10px;
            font-size: 9px; font-weight: 700; color:#fff;
            background: linear-gradient(110deg, rgba(34,211,238,.25), rgba(15,58,42,.6));
            border: 1px solid rgba(255,255,255,.2); }
        .bb-map i { color:#ef4444; }
        .bb-count { display:flex; align-items:center; justify-content:center; gap:2px;
            padding: 7px 9px; border-radius: 10px; color:#fbbf24; font-family: ui-monospace,monospace; font-weight:900; font-size: 13px;
            background: rgba(0,0,0,.5); border: 1px solid rgba(251,191,36,.35); }
        .bb-count span { background: rgba(251,191,36,.1); padding: 3px 6px; border-radius: 6px; }
        .bb-count i { opacity:.6; font-style: normal; }
        .bb-2col { display:grid; grid-template-columns: 1fr 1fr; gap: 4px; }
        .bb-2col > div { background: rgba(255,255,255,.9); color:#0a0a14; text-align:center; padding: 7px 4px; border-radius: 9px; font-size: 9px; font-weight:800;
            display:flex; align-items:center; justify-content:center; gap:4px; }
        .bb-faq { display:flex; justify-content: space-between; align-items:center;
            padding: 7px 9px; border-radius: 10px; font-size: 10px; font-weight: 800; color:#fff;
            background: rgba(0,0,0,.35); border: 1px solid rgba(255,255,255,.2); }
        .bb-faq i { font-size: 9px; opacity:.7; }
        .bb-quote { padding: 7px 9px; border-radius: 10px; font-size: 9px; font-style: italic; color:#fff;
            background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.22); line-height: 1.3; }
        .bb-quote div { margin-top: 3px; font-size: 8px; color:#ffc845; font-style: normal; letter-spacing: 1px; }
        .bb-socials { display:flex; justify-content:space-around; padding: 7px 4px; border-radius: 10px; color:#fff; font-size: 11px;
            background: rgba(0,0,0,.3); border: 1px solid rgba(255,255,255,.18); }
        .bb-foot { text-align:center; font-size: 8px; opacity:.7; color:#fff; padding: 4px 0 8px; }

        /* ─── Phone: subtle inner shine overlay ─── */
        .bb-screen::before {
            content:""; position:absolute; inset:0; z-index:1; pointer-events:none;
            background: radial-gradient(ellipse at 20% 8%, rgba(255,255,255,.13) 0%, transparent 50%);
        }
        /* ─── Phone auto-scroll pan ─── */
        @keyframes bbScroll {
            0%,8%   { transform: translateY(0); }
            32%,44% { transform: translateY(-35%); }
            70%,82% { transform: translateY(-66%); }
            96%,100%{ transform: translateY(0); }
        }
        .bb-scroll > * { flex-shrink: 0; }
        .bb-phone-inner { display:flex; flex-direction:column; gap:7px; animation: bbScroll 22s ease-in-out infinite; }

        /* ─── Builder drag-reorder states ─── */
        .bl-drop-indicator {
            height: 3px; border-radius: 999px; flex-shrink: 0;
            background: linear-gradient(90deg, var(--c2), var(--c3));
            box-shadow: 0 0 10px rgba(61,107,255,.7);
            margin: 1px 0;
            animation: blIndicatorPop .22s cubic-bezier(.34,1.56,.64,1) both;
        }
        @keyframes blIndicatorPop { from { opacity:0; transform:scaleX(.3); } to { opacity:1; transform:scaleX(1); } }
        .build-row.bl-dragging {
            background: rgba(61,107,255,.18) !important;
            border-color: rgba(61,107,255,.6) !important;
            box-shadow: 0 18px 42px -12px rgba(61,107,255,.55), 0 0 0 1.5px rgba(61,107,255,.4) !important;
            transform: translateY(-5px) scale(1.025) translateX(2px) !important;
            z-index: 10; position: relative; cursor: grabbing;
        }
        .build-row.bl-dragging .bl-grip { color: var(--c2) !important; }
        .build-row.bl-ghost {
            opacity: .25 !important;
            background: rgba(255,255,255,.025) !important;
            border-style: dashed !important;
            transform: scale(.98) !important;
        }
        .build-row.bl-dropping { animation: blDrop .4s cubic-bezier(.16,1,.3,1) both; }
        @keyframes blDrop { 0%{ transform:translateY(-5px) scale(1.025); } 55%{ transform:translateY(2px) scale(.99); } 100%{ transform:translateY(0) scale(1); } }

        /* ─── Theme swatch active ring ─── */
        .th-swatch { transition: transform .35s cubic-bezier(.16,1,.3,1), box-shadow .35s, outline-color .35s; }
        .th-swatch.is-active {
            transform: translateY(-4px) scale(1.16);
            outline: 2.5px solid rgba(255,255,255,.8);
            outline-offset: 2px;
            box-shadow: 0 0 0 5px rgba(255,255,255,.1), 0 10px 22px -8px rgba(0,0,0,.6) !important;
        }

        /* ─── Right-col card polish: swatch preview bar ─── */
        .th-preview-bar {
            height: 4px; border-radius: 999px; overflow:hidden; position:relative;
            background: rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.08);
        }
        .th-preview-bar-fill {
            height:100%; border-radius:999px; width:0%;
            transition: width .6s cubic-bezier(.16,1,.3,1), background .4s ease;
        }

        /* ─── Mobile-first stat bar animation ─── */
        @keyframes mfStatIn { from { transform:scaleX(0); } to { transform:scaleX(1); } }
        .mf-stat-bar { height:3px; border-radius:999px; transform-origin:left; animation: mfStatIn 1.2s cubic-bezier(.16,1,.3,1) both; }

        /* ─── Reduced motion: all new builder/phone/swatch animations ─── */
        @media (prefers-reduced-motion: reduce) {
            .build-row.bl-dragging, .build-row.bl-ghost, .build-row.bl-dropping,
            .bl-drop-indicator, .bb-phone-inner, .th-swatch, .th-swatch.is-active,
            .mf-stat-bar {
                animation: none !important;
                transform: none !important;
                transition: none !important;
            }
            .th-swatch.is-active { outline: 2.5px solid rgba(255,255,255,.7); }
        }

        /* ============ Hero category gallery ============ */
        .hero-gallery {
            display: grid;
            grid-template-columns: repeat(6, minmax(0,1fr));
            gap: 6px;
        }
        .hero-gallery-item {
            position: relative; aspect-ratio: 1/1;
            border-radius: 12px; overflow: hidden;
            background: rgba(255,255,255,.05);
            border: 1px solid rgba(255,255,255,.08);
            opacity: 0;
            animation: galleryIn .55s cubic-bezier(.34,1.56,.64,1) both;
            animation-delay: var(--gd, 0ms);
        }
        .hero-gallery-item img {
            width: 100%; height: 100%; object-fit: cover;
            transform: scale(1); transition: transform .6s cubic-bezier(.16,1,.3,1);
        }
        .hero-gallery-item:hover img { transform: scale(1.08); }
        .hero-gallery-item .gallery-cat {
            position: absolute; left: 4px; bottom: 4px;
            font-size: 8px; font-weight: 800; letter-spacing: .08em;
            text-transform: uppercase;
            padding: 2px 5px; border-radius: 4px;
            background: rgba(0,0,0,.55); color: #fff;
        }
        .hero-gallery-item.gallery-shimmer::after {
            content:""; position: absolute; inset: 0;
            background: linear-gradient(110deg, transparent 30%, rgba(255,255,255,.35) 50%, transparent 70%);
            animation: shimmer 1.1s ease-out;
        }
        @keyframes galleryIn {
            0%   { opacity: 0; transform: translateY(10px) scale(.92); }
            100% { opacity: 1; transform: translateY(0) scale(1); }
        }
        @keyframes shimmer {
            0%   { transform: translateX(-60%); opacity: 0; }
            30%  { opacity: 1; }
            100% { transform: translateX(60%); opacity: 0; }
        }
        @media (max-width: 1023px) {
            .hero-gallery {
                display: flex; gap: 10px; overflow-x: auto; scroll-snap-type: x mandatory;
                padding-bottom: 4px;
                scrollbar-width: none;
            }
            .hero-gallery::-webkit-scrollbar { display: none; }
            .hero-gallery-item { flex: 0 0 110px; scroll-snap-align: start; }
            .hero-gallery-item .gallery-cat { font-size: 9px; padding: 3px 6px; }
        }

        /* ============ Hero compact horizontal tile strip ============ */
        .hero-rail-label {
            line-height: 1.1;
            white-space: nowrap;
        }
        .hero-tile-rail {
            display: flex;
            flex-direction: row;
            gap: 8px;
            overflow-x: auto;
            overflow-y: hidden;
            overscroll-behavior-x: contain;
            -webkit-overflow-scrolling: touch;
            scroll-snap-type: x proximity;
            scrollbar-width: none;
            padding: 4px 2px 6px;
            -webkit-mask-image: linear-gradient(to right, transparent 0, #000 18px, #000 calc(100% - 18px), transparent 100%);
                    mask-image: linear-gradient(to right, transparent 0, #000 18px, #000 calc(100% - 18px), transparent 100%);
            z-index: 6;
        }
        .hero-tile-rail::-webkit-scrollbar { display: none; }
        .hero-tile {
            appearance: none; -webkit-appearance: none;
            background: rgba(255,255,255,.05);
            border: 1px solid rgba(255,255,255,.10);
            border-radius: 12px;
            padding: 5px 5px 6px;
            color: #fff;
            cursor: pointer;
            display: flex; flex-direction: column; align-items: stretch;
            gap: 5px;
            flex-shrink: 0;
            width: 78px;
            scroll-snap-align: start;
            transition: transform .18s ease, border-color .18s ease, background .18s ease, box-shadow .18s ease;
            text-align: center;
        }
        .hero-tile:hover {
            transform: translateY(-2px);
            border-color: rgba(255,255,255,.24);
            background: rgba(255,255,255,.09);
        }
        .hero-tile:focus-visible {
            outline: 2px solid #1bd4d9;
            outline-offset: 2px;
        }
        .hero-tile.is-active {
            border-color: rgba(61,107,255,.6);
            background: rgba(61,107,255,.14);
            box-shadow: 0 10px 24px -12px rgba(61,107,255,.7);
        }
        .hero-tile-thumb {
            position: relative;
            aspect-ratio: 4/3;
            border-radius: 9px;
            overflow: hidden;
            background: rgba(255,255,255,.04);
        }
        .hero-tile-thumb picture,
        .hero-tile-thumb img {
            display: block; width: 100%; height: 100%; object-fit: cover;
        }
        .hero-tile-fallback {
            display: flex; align-items: center; justify-content: center;
            width: 100%; height: 100%;
            color: #fff;
            position: relative;
        }
        .hero-tile-fallback i {
            font-size: 22px;
            opacity: .9;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,.35));
        }
        .hero-tile-fallback .ftl {
            position: absolute; left: 4px; right: 4px; bottom: 4px;
            font-size: 8px; font-weight: 800; text-transform: uppercase;
            letter-spacing: .06em;
            color: rgba(255,255,255,.95);
            text-shadow: 0 1px 2px rgba(0,0,0,.4);
        }
        .hero-tile-label {
            font-size: 10px; font-weight: 700; letter-spacing: .04em;
            color: #e5e7eb;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        @media (prefers-reduced-motion: reduce) {
            .hero-tile, .hero-tile:hover { transition: none; transform: none; }
        }

        /* ============ Hero block icons cluster ============ */
        .hero-blocks {
            display: flex; flex-wrap: wrap; gap: 8px;
            justify-content: center;
        }
        .hero-block-chip {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 7px 11px;
            border-radius: 999px;
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.12);
            font-size: 11px; font-weight: 700;
            color: #fff;
            animation: blockFloat var(--bdur, 5s) ease-in-out infinite;
            animation-delay: var(--bdel, 0s);
            transition: transform .25s, background .25s, border-color .25s;
            will-change: transform;
        }
        @supports (backdrop-filter: blur(14px)) {
            .hero-block-chip {
                background: linear-gradient(135deg, rgba(255,255,255,.1) 0%, rgba(255,255,255,.03) 100%);
                backdrop-filter: blur(20px) saturate(140%);
                -webkit-backdrop-filter: blur(20px) saturate(140%);
                box-shadow: inset 0 1px 0 rgba(255,255,255,.15);
            }
        }
        .hero-block-chip:hover {
            transform: translateY(-3px) scale(1.05);
            background: rgba(255,255,255,.12);
            border-color: rgba(255,255,255,.25);
        }
        .hero-block-chip i {
            width: 18px; height: 18px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 12px;
        }
        @keyframes blockFloat {
            0%,100% { transform: translateY(0) rotate(0); }
            50%     { transform: translateY(-6px) rotate(var(--brot, 0deg)); }
        }

        @media (max-width: 1023px) {
            .hero-block-chip { animation: none; }
        }

        /* ============ Reduced motion for new hero pieces ============ */
        @media (prefers-reduced-motion: reduce) {
            .hero-phone-screen, .hero-phone-screen::before,
            .hero-block-chip, .hero-gallery-item, .hero-gallery-item::after,
            .hero-phone-wrap {
                animation: none !important; transition: none !important;
            }
            .hero-phone-wrap { transform: none !important; }
            .hero-gallery-item { opacity: 1 !important; }
        }

        /* ============ Sticker ============ */
        .sticker { position: absolute; pointer-events: none; }

        /* ============ Card glass ============ */
        /* Liquid Glass (Dark Mode) */
        .glass, .glass-2 { 
            background: rgba(255, 255, 255, 0.04); 
            border: 1px solid transparent; 
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.06), inset 1.5px 2px 0 -1px rgba(255,255,255,0.4), inset -1.5px -1.5px 0 -1px rgba(255,255,255,0.2), inset -3px -8px 1px -6px rgba(255,255,255,0.15), inset 0 0 8px 1px rgba(0,0,0,0.2), 0 12px 32px rgba(0,0,0,0.4); 
        }
        @supports (backdrop-filter: blur(8px)) {
            .glass, .glass-2 { 
                background: linear-gradient(135deg, rgba(255, 255, 255, 0.06) 0%, rgba(255, 255, 255, 0.01) 100%); 
                backdrop-filter: blur(6px) saturate(180%) brightness(1.1); 
                -webkit-backdrop-filter: blur(6px) saturate(180%) brightness(1.1); 
            }
        }

        /* ============ FAQ ============ */
        .faq-item summary { list-style: none; cursor: pointer; }
        .faq-item summary::-webkit-details-marker { display: none; }
        .faq-item[open] .faq-icon { transform: rotate(45deg); }
        .faq-icon { transition: transform .3s; }

        /* ============ Brand logo dark/light mode ============ */
        .brand-logo--light { display: none; }
        .brand-logo--dark  { display: inline-block; }
        html.light-mode .brand-logo--light { display: inline-block; }
        html.light-mode .brand-logo--dark  { display: none; }
        /* Force the dark-bg logo variant regardless of page theme — used on
           always-dark surfaces like the auth-hero photo pane where the
           light-mode logo would wash out against the dark image. */
        .force-dark-logo .brand-logo--light { display: none !important; }
        .force-dark-logo .brand-logo--dark  { display: inline-block !important; }
        html.light-mode .force-dark-logo .brand-logo--light { display: none !important; }
        html.light-mode .force-dark-logo .brand-logo--dark  { display: inline-block !important; }

        /* ============ Workspace collab panel ============ */
        .ws-card { position: relative; }
        .ws-row {
            display: flex; align-items: center; gap: 12px;
            background: rgba(255,255,255,.04);
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 14px;
            padding: 10px 12px;
            position: relative;
            opacity: 0;
            transform: translateY(8px);
            animation: wsRowIn .55s cubic-bezier(.16,1,.3,1) forwards;
        }
        @keyframes wsRowIn { to { opacity: 1; transform: translateY(0);} }

        .ws-avatar {
            position: relative;
            width: 38px; height: 38px;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
            background: rgba(255,255,255,.06);
            border: 2px solid rgba(255,255,255,.12);
        }
        .ws-avatar img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .ws-avatar.is-online::after {
            content: ""; position: absolute; right: -1px; bottom: -1px;
            width: 11px; height: 11px; border-radius: 50%;
            background: #22c55e;
            border: 2px solid #0d0d12;
            box-shadow: 0 0 0 0 rgba(34,197,94,.6);
            animation: wsPing 2s infinite;
        }
        @keyframes wsPing {
            0% { box-shadow: 0 0 0 0 rgba(34,197,94,.55); }
            70% { box-shadow: 0 0 0 6px rgba(34,197,94,0); }
            100% { box-shadow: 0 0 0 0 rgba(34,197,94,0); }
        }

        .ws-meta { min-width: 0; flex: 1; }
        .ws-name { font-size: 12px; font-weight: 700; color: #fff; display:flex; align-items:center; gap:6px; }
        .ws-name .verified { color: var(--c1); font-size: 10px; }
        .ws-task {
            font-size: 11px; color: #9ca3af;
            display: flex; align-items: center; gap: 6px;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .ws-task i { font-size: 9px; opacity: .8; }

        .ws-badge {
            font-size: 9px; font-weight: 800;
            text-transform: uppercase; letter-spacing: .08em;
            padding: 4px 8px; border-radius: 999px;
            display: inline-flex; align-items: center; gap: 5px;
            white-space: nowrap; flex-shrink: 0;
        }
        .ws-badge .dot {
            width: 6px; height: 6px; border-radius: 50%;
            animation: wsDot 1.2s ease-in-out infinite;
        }
        @keyframes wsDot { 0%,100% { opacity: 1; transform: scale(1);} 50% { opacity: .4; transform: scale(.7);} }
        .ws-b-edit   { background: rgba(61,107,255,.18); color: #bccfff; }
        .ws-b-edit .dot { background: #90acff; }
        .ws-b-up     { background: rgba(27,212,217,.18); color: #67e8f9; }
        .ws-b-up .dot { background: #22d3ee; }
        .ws-b-comment{ background: rgba(233,78,140,.18); color: #f9a8d4; }
        .ws-b-comment .dot { background: #ec4899; }
        .ws-b-ok     { background: rgba(34,197,94,.18); color: #86efac; }
        .ws-b-ok .dot { background: #22c55e; animation: none; }
        .ws-b-view   { background: rgba(255,200,69,.18); color: #fde68a; }
        .ws-b-view .dot { background: #fbbf24; }

        /* typing dots inside task line */
        .ws-typing { display: inline-flex; gap: 3px; margin-left: 4px; }
        .ws-typing span {
            width: 4px; height: 4px; border-radius: 50%;
            background: #90acff;
            animation: wsType 1.1s infinite ease-in-out;
        }
        .ws-typing span:nth-child(2) { animation-delay: .15s; }
        .ws-typing span:nth-child(3) { animation-delay: .3s; }
        @keyframes wsType {
            0%,80%,100% { transform: scale(.6); opacity: .4; }
            40% { transform: scale(1); opacity: 1; }
        }

        /* progress bar for upload */
        .ws-prog {
            margin-top: 4px;
            height: 3px;
            background: rgba(255,255,255,.08);
            border-radius: 999px;
            overflow: hidden;
        }
        .ws-prog .bar {
            height: 100%;
            background: linear-gradient(90deg, var(--c1), var(--c2));
            border-radius: 999px;
            width: 0;
            animation: wsProg 4.5s ease-in-out infinite;
        }
        @keyframes wsProg {
            0% { width: 0; }
            70% { width: 92%; }
            100% { width: 92%; }
        }

        /* Online avatar stack */
        .ws-online-stack { display: flex; align-items: center; }
        .ws-online-stack .av {
            width: 26px; height: 26px; border-radius: 50%;
            border: 2px solid #0d0d12; overflow: hidden;
            margin-left: -8px; background: #1f2030;
            box-shadow: 0 0 0 0 rgba(27,212,217,.5);
            animation: wsBreath 3s ease-in-out infinite;
        }
        .ws-online-stack .av:first-child { margin-left: 0; }
        .ws-online-stack .av:nth-child(2) { animation-delay: .4s; }
        .ws-online-stack .av:nth-child(3) { animation-delay: .8s; }
        .ws-online-stack .av:nth-child(4) { animation-delay: 1.2s; }
        .ws-online-stack .av:nth-child(5) { animation-delay: 1.6s; }
        .ws-online-stack .av img { width:100%; height:100%; object-fit: cover; }
        @keyframes wsBreath {
            0%,100% { box-shadow: 0 0 0 0 rgba(27,212,217,.5); }
            50% { box-shadow: 0 0 0 4px rgba(27,212,217,0); }
        }

        /* Live cursor floating across panel */
        .ws-cursor {
            position: absolute;
            width: 14px; height: 14px;
            pointer-events: none;
            z-index: 5;
            opacity: 0;
            animation: wsCursor 9s ease-in-out infinite;
        }
        .ws-cursor svg { width: 100%; height: 100%; filter: drop-shadow(0 2px 4px rgba(0,0,0,.4)); }
        .ws-cursor .lbl {
            position: absolute; left: 14px; top: 12px;
            font-size: 9px; font-weight: 700; color: #fff;
            background: linear-gradient(135deg,#3d6bff,#e94e8c);
            padding: 2px 6px; border-radius: 6px;
            white-space: nowrap;
        }
        @keyframes wsCursor {
            0%   { left: 12%; top: 22%; opacity: 0; }
            8%   { opacity: 1; }
            25%  { left: 65%; top: 35%; }
            45%  { left: 30%; top: 58%; }
            65%  { left: 72%; top: 72%; }
            85%  { left: 18%; top: 80%; opacity: 1; }
            95%  { opacity: 0; }
            100% { left: 12%; top: 22%; opacity: 0; }
        }
        .ws-cursor.c2 { animation-delay: -4.5s; animation-duration: 11s; }
        .ws-cursor.c2 .lbl { background: linear-gradient(135deg,#1bd4d9,#3d6bff); }

        @media (prefers-reduced-motion: reduce) {
            .ws-row, .ws-avatar.is-online::after, .ws-badge .dot,
            .ws-typing span, .ws-prog .bar, .ws-online-stack .av,
            .ws-cursor { animation: none !important; }
            .ws-row { opacity: 1; transform: none; }
        }

        /* ============ Share section · animated cards ============ */
        .share-card { position: relative; overflow: hidden; }
        .share-card::before {
            content: ""; position: absolute; inset: 0;
            background: linear-gradient(115deg, transparent 30%, rgba(255,255,255,.06) 50%, transparent 70%);
            transform: translateX(-100%);
            pointer-events: none;
            transition: transform .9s ease;
        }
        .share-card:hover::before { transform: translateX(100%); }

        /* Branded short link · typing slug */
        .sl-pill {
            display: flex; align-items: center; gap: 8px;
            background: #0a0a14;
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 12px;
            padding: 9px 12px;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 12px;
            position: relative;
            overflow: hidden;
        }
        .sl-pill .host { color: #fff; opacity: .85; }
        .sl-pill .slug {
            color: var(--c1);
            display: inline-block;
            white-space: nowrap;
            overflow: hidden;
            border-right: 2px solid var(--c1);
            width: 0;
            animation: slType 8s steps(11) infinite, slCaret .8s steps(2) infinite;
            vertical-align: bottom;
        }
        @keyframes slType {
            0%   { width: 0; }
            22%  { width: 11ch; }
            55%  { width: 11ch; }
            60%  { width: 0; }
            100% { width: 0; }
        }
        @keyframes slCaret { 50% { border-color: transparent; } }
        .sl-counter {
            margin-top: 10px;
            display: flex; align-items: center; justify-content: space-between;
            font-size: 10px; color: #9ca3af;
        }
        .sl-counter .num {
            color: var(--c1);
            font-weight: 700; font-variant-numeric: tabular-nums;
        }
        .sl-spark {
            display: inline-flex; gap: 2px; align-items: end; height: 14px;
        }
        .sl-spark i {
            width: 3px; background: var(--c1); border-radius: 1px;
            opacity: .7;
            animation: slSpark 1.6s ease-in-out infinite;
        }
        .sl-spark i:nth-child(1) { height: 30%; animation-delay: 0s; }
        .sl-spark i:nth-child(2) { height: 60%; animation-delay: .15s; }
        .sl-spark i:nth-child(3) { height: 45%; animation-delay: .3s; }
        .sl-spark i:nth-child(4) { height: 80%; animation-delay: .45s; }
        .sl-spark i:nth-child(5) { height: 50%; animation-delay: .6s; }
        .sl-spark i:nth-child(6) { height: 95%; animation-delay: .75s; }
        @keyframes slSpark { 50% { transform: scaleY(.5); opacity: 1; } }

        /* Themes card · swatches & font pills */
        .th-swatch {
            width: 26px; height: 26px; border-radius: 8px;
            display: inline-block;
            box-shadow: 0 4px 10px -4px rgba(0,0,0,.5), inset 0 0 0 1.5px rgba(255,255,255,.12);
            transition: transform .25s ease;
            cursor: default;
        }
        .th-swatch:hover { transform: translateY(-2px) scale(1.06); }
        .th-pill {
            display: inline-flex; align-items: center; gap: 4px;
            font-size: 11px; font-weight: 700;
            color: #d1d5db;
            padding: 4px 10px; border-radius: 999px;
            background: rgba(255,255,255,.05);
            border: 1px solid rgba(255,255,255,.08);
        }
        .th-pill--accent { background: linear-gradient(135deg, rgba(27,212,217,.18), rgba(61,107,255,.18)); color: #a5f3fc; border-color: rgba(27,212,217,.35); }

        /* Mobile-first card · phone mock + stats */
        .mf-mock { display: grid; grid-template-columns: 92px 1fr; gap: 16px; align-items: center; }
        .mf-phone {
            position: relative;
            width: 92px; height: 158px;
            background: linear-gradient(160deg, #18181b, #0e0e10);
            border: 1.5px solid rgba(255,255,255,.08);
            border-radius: 18px;
            padding: 14px 10px 10px;
            box-shadow: 0 18px 36px -18px rgba(233,78,140,.55), inset 0 0 0 1px rgba(255,255,255,.03);
            overflow: hidden;
        }
        .mf-notch {
            position: absolute; top: 5px; left: 50%; transform: translateX(-50%);
            width: 28px; height: 4px; border-radius: 4px;
            background: rgba(255,255,255,.18);
        }
        .mf-avatar {
            width: 28px; height: 28px; border-radius: 50%;
            margin: 4px auto 6px;
            background: linear-gradient(135deg, var(--c3), var(--c2));
            box-shadow: 0 0 0 2px rgba(255,255,255,.06);
        }
        .mf-name { width: 60%; height: 6px; margin: 0 auto 3px; border-radius: 3px; background: rgba(255,255,255,.7); }
        .mf-handle { width: 38%; height: 4px; margin: 0 auto 9px; border-radius: 3px; background: rgba(255,255,255,.25); }
        .mf-btn {
            height: 14px; width: 100%;
            margin-bottom: 6px;
            border-radius: 7px;
            background: linear-gradient(90deg, rgba(61,107,255,.55), rgba(233,78,140,.5));
            box-shadow: inset 0 0 0 1px rgba(255,255,255,.08);
        }
        .mf-stats { display: grid; grid-template-columns: 1fr; gap: 8px; }
        .mf-stats > div {
            display: flex; flex-direction: column;
            padding: 8px 12px;
            background: rgba(255,255,255,.04);
            border: 1px solid rgba(255,255,255,.06);
            border-radius: 10px;
        }
        .mf-stats strong {
            font-size: 16px; font-weight: 800; line-height: 1;
            background: linear-gradient(90deg, var(--c1), var(--c3));
            -webkit-background-clip: text; background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .mf-stats span { font-size: 10px; color: #9ca3af; margin-top: 2px; text-transform: uppercase; letter-spacing: .06em; font-weight: 600; }

        /* ── AI builder prompt showcase (typewriter loop) ── */
        .ai-prompt-card {
            position: relative; overflow: hidden;
            padding: 18px 20px 20px;
            border-radius: 22px;
        }
        .ai-prompt-card::before {
            content: ""; position: absolute; inset: -1px; border-radius: inherit; pointer-events: none;
            background: radial-gradient(120% 140% at 12% -10%, rgba(27,212,217,.16), transparent 55%),
                        radial-gradient(120% 140% at 88% 120%, rgba(61,107,255,.18), transparent 55%);
            opacity: .9;
        }
        .ai-prompt-head { position: relative; display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
        .ai-badge {
            display: inline-flex; align-items: center; gap: 7px;
            font-size: 11px; font-weight: 800; letter-spacing: .04em;
            padding: 5px 11px; border-radius: 9999px;
            color: #bff3f5;
            background: rgba(27,212,217,.14);
            border: 1px solid rgba(27,212,217,.32);
        }
        .ai-badge i { font-size: 11px; color: var(--c1); }
        .ai-status {
            display: inline-flex; align-items: center; gap: 7px;
            font-size: 11px; font-weight: 700; color: #9ca3af;
            transition: color .3s ease;
        }
        .ai-status-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: #4ade80; box-shadow: 0 0 0 0 rgba(74,222,128,.6);
        }
        .ai-status.is-building { color: var(--c1); }
        .ai-status.is-building .ai-status-dot {
            background: var(--c1);
            animation: aiPulseDot 1s ease-in-out infinite;
        }
        @keyframes aiPulseDot {
            0%,100% { box-shadow: 0 0 0 0 color-mix(in srgb, var(--c1) 55%, transparent); }
            50%     { box-shadow: 0 0 0 6px rgba(27,212,217,0); }
        }
        .ai-prompt-input {
            position: relative; display: flex; align-items: center; gap: 12px;
            padding: 14px 14px 14px 16px;
            border-radius: 16px;
            background: rgba(10,10,20,.55);
            border: 1px solid rgba(255,255,255,.10);
        }
        .ai-prompt-input > .ai-spark { font-size: 15px; color: var(--c2); flex-shrink: 0; }
        .ai-typed {
            flex: 1; min-width: 0;
            font-size: 14px; line-height: 1.5; font-weight: 500; color: #e8ebf5;
            min-height: 21px;
            white-space: normal; word-break: break-word;
        }
        .ai-typed .ai-placeholder { color: #6b7280; }
        .ai-caret {
            display: inline-block; width: 2px; height: 15px;
            margin-left: 1px; vertical-align: -2px;
            background: var(--c1); border-radius: 1px;
            animation: aiCaret 1s step-end infinite;
        }
        @keyframes aiCaret { 0%,100% { opacity: 1; } 50% { opacity: 0; } }
        .ai-gen-btn {
            flex-shrink: 0; display: inline-flex; align-items: center; gap: 7px;
            font-size: 12px; font-weight: 800; color: #fff;
            padding: 9px 15px; border-radius: 11px;
            background: linear-gradient(90deg, var(--c1), var(--c2));
            box-shadow: 0 10px 24px -12px rgba(61,107,255,.8);
            transition: transform .3s cubic-bezier(.16,1,.3,1), box-shadow .3s ease, filter .3s ease;
        }
        .ai-gen-btn i { font-size: 10px; }
        .ai-prompt-card.is-building .ai-gen-btn {
            transform: scale(.96);
            filter: saturate(1.25) brightness(1.08);
            box-shadow: 0 12px 30px -10px rgba(27,212,217,.9);
        }
        .ai-build-chips {
            position: relative; display: flex; flex-wrap: wrap; gap: 8px;
            margin-top: 14px; min-height: 30px; align-items: center;
        }
        .ai-build-hint { font-size: 11px; font-weight: 600; color: #6b7280; }
        .ai-chip {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 11px; font-weight: 700; color: #cbd5e1;
            padding: 5px 10px; border-radius: 9999px;
            background: rgba(255,255,255,.05);
            border: 1px solid rgba(255,255,255,.09);
            opacity: 0; transform: translateY(6px) scale(.94);
            transition: opacity .32s ease, transform .32s cubic-bezier(.16,1,.3,1);
        }
        .ai-chip.is-in { opacity: 1; transform: translateY(0) scale(1); }
        .ai-chip i { font-size: 10px; }
        @media (prefers-reduced-motion: reduce) {
            .ai-caret { animation: none; }
            .ai-status.is-building .ai-status-dot { animation: none; }
            .ai-chip { opacity: 1; transform: none; transition: none; }
        }

        /* QR · scanning beam */
        .qr-stage {
            position: relative;
            width: 132px; height: 132px;
            border-radius: 14px;
            background: #fff;
            padding: 8px;
            box-shadow: 0 20px 50px -25px rgba(233,78,140,.6);
            animation: qrFloat 4s ease-in-out infinite;
        }
        @keyframes qrFloat { 0%,100% { transform: translateY(0) rotate(0); } 50% { transform: translateY(-6px) rotate(2deg); } }
        .qr-svg {
            width: 100%; height: 100%;
            display: block;
            border-radius: 4px;
            shape-rendering: geometricPrecision;
        }
        .qr-stage::after {
            content: ""; position: absolute;
            left: 8px; right: 8px; top: 8px;
            height: 14px;
            background: linear-gradient(180deg, rgba(233,78,140,.0) 0%, rgba(233,78,140,.55) 60%, rgba(255,255,255,.7) 100%);
            border-radius: 4px;
            box-shadow: 0 0 18px rgba(233,78,140,.7);
            animation: qrScan 2.4s ease-in-out infinite;
            pointer-events: none;
            mix-blend-mode: screen;
        }
        @keyframes qrScan {
            0%   { top: 8px; opacity: 0; }
            10%  { opacity: .9; }
            90%  { opacity: .9; }
            100% { top: calc(100% - 22px); opacity: 0; }
        }
        .qr-corner {
            position: absolute; width: 14px; height: 14px;
            border: 2px solid var(--c3);
        }
        .qr-corner.tl { top: -3px; left: -3px; border-right: none; border-bottom: none; border-radius: 4px 0 0 0; }
        .qr-corner.tr { top: -3px; right: -3px; border-left: none; border-bottom: none; border-radius: 0 4px 0 0; }
        .qr-corner.bl { bottom: -3px; left: -3px; border-right: none; border-top: none; border-radius: 0 0 0 4px; }
        .qr-corner.br { bottom: -3px; right: -3px; border-left: none; border-top: none; border-radius: 0 0 4px 0; }
        .qr-scans-pill {
            position: absolute; top: -10px; right: -10px;
            background: linear-gradient(135deg, var(--c3), var(--c4));
            color: #fff; font-size: 9px; font-weight: 800;
            padding: 4px 8px; border-radius: 999px;
            text-transform: uppercase; letter-spacing: .08em;
            box-shadow: 0 8px 20px -8px rgba(233,78,140,.7);
            animation: qrPulse 1.8s ease-in-out infinite;
        }
        @keyframes qrPulse { 50% { transform: scale(1.08); } }

        /* Channel-ready · orbit pulses */
        .ch-grid { display: flex; flex-wrap: wrap; gap: 8px; }
        .ch-icon {
            position: relative;
            width: 38px; height: 38px;
            border-radius: 12px;
            background: rgba(255,255,255,.05);
            border: 1px solid rgba(255,255,255,.1);
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 15px;
            transition: transform .25s ease, background .25s ease;
        }
        .ch-icon::after {
            content: ""; position: absolute; inset: 0;
            border-radius: 12px;
            border: 2px solid currentColor;
            opacity: 0;
            animation: chRing 2.4s ease-out infinite;
        }
        .ch-icon:nth-child(1)::after { animation-delay: 0s; }
        .ch-icon:nth-child(2)::after { animation-delay: .3s; }
        .ch-icon:nth-child(3)::after { animation-delay: .6s; }
        .ch-icon:nth-child(4)::after { animation-delay: .9s; }
        .ch-icon:nth-child(5)::after { animation-delay: 1.2s; }
        .ch-icon:nth-child(6)::after { animation-delay: 1.5s; }
        @keyframes chRing {
            0%   { transform: scale(1); opacity: .55; }
            70%  { transform: scale(1.5); opacity: 0; }
            100% { transform: scale(1.5); opacity: 0; }
        }
        .ch-icon:hover { transform: translateY(-3px) scale(1.08); background: rgba(255,255,255,.1); }
        .ch-tags {
            display:flex; flex-wrap:wrap; gap:5px; margin-top: 12px;
        }
        .ch-tags span {
            font-size: 9px; font-weight: 700; letter-spacing: .06em;
            padding: 3px 7px; border-radius: 999px;
            background: rgba(255,138,60,.15); color: #fed7aa;
            text-transform: uppercase;
        }

        /* Custom domain card */
        .cd-stage {
            background: #0a0a14;
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 14px;
            padding: 12px;
            position: relative;
            overflow: hidden;
        }
        .cd-bar {
            display: flex; align-items: center; gap: 8px;
            background: rgba(255,255,255,.04);
            border-radius: 8px;
            padding: 6px 9px;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 11px;
            position: relative;
            overflow: hidden;
        }
        .cd-bar .lock {
            color: #22c55e; font-size: 10px;
            display: inline-flex; align-items: center;
            animation: cdLock 3s ease-in-out infinite;
        }
        @keyframes cdLock {
            0%,80%,100% { transform: scale(1); color: #22c55e; }
            85% { transform: scale(1.2); color: #86efac; }
        }
        .cd-bar .sub { color: #9ca3af; }
        .cd-bar .brand { color: #fff; font-weight: 600; }
        .cd-bar .tld { color: #90acff; }
        .cd-bar .path { color: #67e8f9; }
        .cd-bar::after {
            content: ""; position: absolute; left: -30%; top: 0; bottom: 0; width: 30%;
            background: linear-gradient(90deg, transparent, rgba(61,107,255,.25), transparent);
            animation: cdSweep 4s ease-in-out infinite;
        }
        @keyframes cdSweep { 0% { left: -30%; } 100% { left: 130%; } }

        .cd-rows {
            margin-top: 10px;
            display: grid; gap: 5px;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 10px;
        }
        .cd-rec {
            display: grid;
            grid-template-columns: 36px 1fr auto;
            align-items: center;
            gap: 8px;
            color: #9ca3af;
            opacity: 0;
            animation: cdRecIn .5s ease forwards;
        }
        .cd-rec .ty {
            font-weight: 800; color: #90acff;
            background: rgba(61,107,255,.15);
            padding: 2px 5px;
            border-radius: 4px;
            text-align: center;
            font-size: 9px;
        }
        .cd-rec .val { color: #d1d5db; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .cd-rec .ok {
            color: #22c55e; font-size: 11px;
            opacity: 0;
            animation: cdOk .4s ease .1s forwards;
        }
        .cd-rec:nth-child(1) { animation-delay: .2s; }
        .cd-rec:nth-child(1) .ok { animation-delay: .9s; }
        .cd-rec:nth-child(2) { animation-delay: .9s; }
        .cd-rec:nth-child(2) .ok { animation-delay: 1.6s; }
        .cd-rec:nth-child(3) { animation-delay: 1.6s; }
        .cd-rec:nth-child(3) .ok { animation-delay: 2.3s; }
        @keyframes cdRecIn { from { opacity: 0; transform: translateX(-6px); } to { opacity: 1; transform: none; } }
        @keyframes cdOk { from { opacity: 0; transform: scale(.4); } to { opacity: 1; transform: scale(1); } }

        .cd-status {
            margin-top: 10px;
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 10px; font-weight: 700;
            padding: 4px 9px;
            border-radius: 999px;
            background: rgba(34,197,94,.15);
            color: #86efac;
            text-transform: uppercase; letter-spacing: .08em;
        }
        .cd-status .pulse {
            width: 6px; height: 6px; border-radius: 50%;
            background: #22c55e;
            box-shadow: 0 0 0 0 rgba(34,197,94,.6);
            animation: wsPing 2s infinite;
        }

        @media (prefers-reduced-motion: reduce) {
            .sl-pill .slug, .sl-spark i, .qr-stage, .qr-stage::after,
            .qr-scans-pill, .ch-icon::after, .cd-bar .lock, .cd-bar::after,
            .cd-rec, .cd-rec .ok, .cd-status .pulse,
            .share-card::before { animation: none !important; }
            .sl-pill .slug { width: 11ch; border-right: none; }
            .cd-rec, .cd-rec .ok { opacity: 1; transform: none; }
        }

        /* ============ Grow section · Live geo + Coach ============ */
        .geo-map {
            position: relative;
            aspect-ratio: 16/9;
            border-radius: 18px;
            background: radial-gradient(ellipse at center, #14142a 0%, #0a0a14 75%);
            overflow: hidden;
            border: 1px solid rgba(255,255,255,.06);
        }
        .geo-map .grid {
            position: absolute; inset: 0;
            background-image:
                linear-gradient(rgba(61,107,255,.08) 1px, transparent 1px),
                linear-gradient(90deg, rgba(61,107,255,.08) 1px, transparent 1px);
            background-size: 22px 22px;
            mask-image: radial-gradient(ellipse at center, #000 30%, transparent 80%);
            -webkit-mask-image: radial-gradient(ellipse at center, #000 30%, transparent 80%);
        }
        .geo-map .continents { position: absolute; inset: 0; }
        .geo-map .continents path {
            fill: rgba(61,107,255,.18);
            stroke: rgba(61,107,255,.3);
            stroke-width: .5;
        }
        .geo-map .meridian {
            position: absolute; top: 0; bottom: 0;
            width: 1px;
            background: linear-gradient(180deg, transparent, rgba(27,212,217,.45), transparent);
            box-shadow: 0 0 12px rgba(27,212,217,.5);
            animation: geoMeridian 6s linear infinite;
        }
        @keyframes geoMeridian {
            0%   { left: -2%; opacity: 0; }
            8%   { opacity: .9; }
            92%  { opacity: .9; }
            100% { left: 102%; opacity: 0; }
        }

        .geo-pin { position: absolute; width: 12px; height: 12px; }
        .geo-pin .core {
            position: absolute; inset: 3px;
            border-radius: 50%;
            background: var(--c, #1bd4d9);
            box-shadow: 0 0 14px var(--c, #1bd4d9);
        }
        .geo-pin .ring {
            position: absolute; inset: 0;
            border-radius: 50%;
            border: 2px solid var(--c, #1bd4d9);
            opacity: 0;
            animation: geoPulse 2.4s ease-out infinite;
        }
        .geo-pin .ring.r2 { animation-delay: .8s; }
        .geo-pin .ring.r3 { animation-delay: 1.6s; }
        @keyframes geoPulse {
            0%   { transform: scale(.6); opacity: .9; }
            80%  { transform: scale(3.6); opacity: 0; }
            100% { transform: scale(3.6); opacity: 0; }
        }

        .geo-stream {
            fill: none;
            stroke-width: 1.3;
            stroke-dasharray: 4 4;
            opacity: .55;
            animation: geoFlow 1.4s linear infinite;
        }
        @keyframes geoFlow { to { stroke-dashoffset: -16; } }

        .geo-ticker {
            position: absolute; top: 12px; left: 12px; right: 12px;
            background: rgba(10,10,20,.78);
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 10px;
            padding: 7px 10px;
            font-size: 10px;
            color: #e5e7eb;
            display: flex; align-items: center; gap: 8px;
            max-width: 320px;
        }
        @supports (backdrop-filter: blur(10px)) {
            .geo-ticker {
                background: linear-gradient(135deg, rgba(10,10,20,.6) 0%, rgba(10,10,20,.3) 100%);
                backdrop-filter: blur(20px) saturate(140%);
                -webkit-backdrop-filter: blur(20px) saturate(140%);
                box-shadow: inset 0 1px 0 rgba(255,255,255,.1);
            }
        }
        .geo-ticker .live {
            color: var(--c1); font-weight: 800; font-size: 9px;
            text-transform: uppercase; letter-spacing: .08em;
            display: inline-flex; align-items: center; gap: 4px;
            white-space: nowrap;
        }
        .geo-ticker .live::before {
            content: ""; width: 6px; height: 6px; border-radius: 50%;
            background: var(--c1); box-shadow: 0 0 8px var(--c1);
            animation: wsDot 1.2s ease-in-out infinite;
        }
        .geo-ticker .feed {
            position: relative; height: 14px; flex: 1; min-width: 0; overflow: hidden;
        }
        .geo-ticker .feed > div {
            position: absolute; left: 0; right: 0; top: 0;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            opacity: 0;
            animation: tickerCycle 12s ease-in-out infinite;
        }
        .geo-ticker .feed > div:nth-child(1) { animation-delay: 0s; }
        .geo-ticker .feed > div:nth-child(2) { animation-delay: 3s; }
        .geo-ticker .feed > div:nth-child(3) { animation-delay: 6s; }
        .geo-ticker .feed > div:nth-child(4) { animation-delay: 9s; }
        .geo-ticker .feed em { font-style: normal; color: var(--c1); font-weight: 700; }
        @keyframes tickerCycle {
            0%       { transform: translateY(100%); opacity: 0; }
            4%, 21%  { transform: translateY(0);    opacity: 1; }
            25%,100% { transform: translateY(-100%); opacity: 0; }
        }

        .geo-stat .num {
            font-size: 26px; font-weight: 800;
            background: linear-gradient(90deg, var(--c2), var(--c3));
            -webkit-background-clip: text; background-clip: text; color: transparent;
            font-variant-numeric: tabular-nums;
        }
        .geo-stat .bar {
            height: 3px; border-radius: 999px; margin-top: 6px;
            background: linear-gradient(90deg, var(--c1), var(--c3));
            width: 0;
            animation: statBarFill 1.8s cubic-bezier(.16,1,.3,1) forwards;
        }
        .geo-stat:nth-child(1) .bar { animation-delay: .2s; --to: 78%; }
        .geo-stat:nth-child(2) .bar { animation-delay: .4s; --to: 60%; }
        .geo-stat:nth-child(3) .bar { animation-delay: .6s; --to: 42%; }
        @keyframes statBarFill { to { width: var(--to, 60%); } }

        .geo-flags {
            margin-top: 12px;
            background: rgba(255,255,255,.03);
            border: 1px solid rgba(255,255,255,.06);
            border-radius: 12px;
            padding: 9px 0;
            overflow: hidden;
            position: relative;
        }
        .geo-flags::before, .geo-flags::after {
            content: ""; position: absolute; top: 0; bottom: 0; width: 32px; z-index: 2;
            pointer-events: none;
        }
        .geo-flags::before { left: 0; background: linear-gradient(90deg, #0d0d12, transparent); }
        .geo-flags::after  { right: 0; background: linear-gradient(-90deg, #0d0d12, transparent); }
        .geo-flags .marquee {
            display: flex; gap: 22px; width: max-content;
            animation: flagsScroll 28s linear infinite;
            padding-left: 22px;
        }
        .geo-flag {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 11px; color: #d1d5db; white-space: nowrap;
        }
        .geo-flag .em { font-size: 15px; line-height: 1; }
        .geo-flag .n  { color: var(--c1); font-weight: 700; font-variant-numeric: tabular-nums; }
        @keyframes flagsScroll { from { transform: translateX(0); } to { transform: translateX(-50%); } }

        /* Coach */
        .coach-ring { position: relative; width: 140px; height: 140px; margin: 0 auto 12px; }
        .coach-ring > svg { width: 100%; height: 100%; transform: rotate(-90deg); }
        .coach-ring .track { stroke: rgba(255,255,255,.18); }
        .coach-ring .fill {
            stroke: #fff; stroke-linecap: round;
            stroke-dasharray: 251.2;
            stroke-dashoffset: 251.2;
            filter: drop-shadow(0 0 8px rgba(255,255,255,.6));
            animation: coachFill 2.6s cubic-bezier(.16,1,.3,1) forwards;
        }
        @keyframes coachFill { to { stroke-dashoffset: 32.66; } } /* 87 / 100 */
        .coach-ring .num {
            position: absolute; inset: 0;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            color: #fff;
        }
        .coach-ring .num .big {
            font-size: 38px; font-weight: 800; line-height: 1;
            font-variant-numeric: tabular-nums;
        }
        .coach-ring .num .lbl {
            font-size: 9px; opacity: .85;
            text-transform: uppercase; letter-spacing: .12em;
            margin-top: 4px;
        }
        .coach-ring .glow {
            position: absolute; inset: -8px; border-radius: 50%;
            background: radial-gradient(circle, rgba(255,255,255,.25), transparent 60%);
            animation: coachGlow 3s ease-in-out infinite;
            pointer-events: none;
        }
        @keyframes coachGlow { 50% { transform: scale(1.06); opacity: .7; } }

        .coach-analyzing {
            display: inline-flex; align-items: center; gap: 7px;
            font-size: 10px; color: rgba(255,255,255,.9);
            margin-bottom: 10px;
            font-weight: 700;
            text-transform: uppercase; letter-spacing: .12em;
            background: rgba(255,255,255,.12);
            padding: 4px 9px; border-radius: 999px;
        }
        .coach-analyzing .dots { display: inline-flex; gap: 3px; }
        .coach-analyzing .dots span {
            width: 4px; height: 4px; border-radius: 50%; background: #fff;
            animation: wsType 1.1s infinite ease-in-out;
        }
        .coach-analyzing .dots span:nth-child(2) { animation-delay: .15s; }
        .coach-analyzing .dots span:nth-child(3) { animation-delay: .3s; }

        .coach-tip {
            display: grid; grid-template-columns: 32px 1fr auto;
            align-items: center; gap: 10px;
            background: rgba(255,255,255,.15);
            border: 1px solid rgba(255,255,255,.18);
            border-radius: 14px;
            padding: 10px 12px;
            position: relative;
            overflow: hidden;
            opacity: 0;
            transform: translateY(8px);
            animation: tipIn .55s cubic-bezier(.16,1,.3,1) forwards;
        }
        @keyframes tipIn { to { opacity: 1; transform: none; } }
        .coach-tip:nth-child(1) { animation-delay: .9s; }
        .coach-tip:nth-child(2) { animation-delay: 1.5s; }
        .coach-tip:nth-child(3) { animation-delay: 2.1s; }
        .coach-tip .ic {
            width: 32px; height: 32px; border-radius: 10px;
            display: inline-flex; align-items: center; justify-content: center;
            background: rgba(255,255,255,.2);
            color: #fff; font-size: 13px;
        }
        .coach-tip .body { font-size: 12px; color: #fff; line-height: 1.4; min-width: 0; }
        .coach-tip .body b { font-weight: 800; }
        .coach-tip .body small {
            display: flex; align-items: center; gap: 6px;
            font-size: 10px; opacity: .85;
            margin-top: 3px; font-variant-numeric: tabular-nums;
        }
        .coach-tip .cta {
            font-size: 9px; font-weight: 800; text-transform: uppercase; letter-spacing: .08em;
            background: rgba(255,255,255,.95); color: #3d6bff;
            padding: 6px 10px; border-radius: 999px;
            white-space: nowrap;
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .coach-tip .cta:hover { transform: translateY(-1px); box-shadow: 0 8px 20px -8px rgba(0,0,0,.4); }
        .coach-tip .spark { display: inline-flex; gap: 1.5px; align-items: end; height: 10px; }
        .coach-tip .spark i {
            width: 2px; background: rgba(255,255,255,.85); border-radius: 1px;
        }
        .coach-tip .spark.up i:nth-child(1){ height: 30%; }
        .coach-tip .spark.up i:nth-child(2){ height: 45%; }
        .coach-tip .spark.up i:nth-child(3){ height: 60%; }
        .coach-tip .spark.up i:nth-child(4){ height: 80%; }
        .coach-tip .spark.up i:nth-child(5){ height: 100%; }
        .coach-tip .spark.dn i:nth-child(1){ height: 100%; }
        .coach-tip .spark.dn i:nth-child(2){ height: 75%; }
        .coach-tip .spark.dn i:nth-child(3){ height: 55%; }
        .coach-tip .spark.dn i:nth-child(4){ height: 40%; }
        .coach-tip .spark.dn i:nth-child(5){ height: 25%; }

        @media (prefers-reduced-motion: reduce) {
            .geo-pin .ring, .geo-stream, .geo-ticker .feed > div,
            .geo-stat .bar, .geo-flags .marquee, .geo-map .meridian,
            .coach-ring .fill, .coach-ring .glow,
            .coach-tip, .coach-analyzing .dots span { animation: none !important; }
            .coach-tip { opacity: 1; transform: none; }
            .coach-ring .fill { stroke-dashoffset: 32.66; }
            .geo-stat .bar { width: 60%; }
        }

        /* ============ Buzz section · diverse event cards ============ */
        .buzz-feed { position: relative; display: flex; flex-direction: column; gap: 10px; }
        .buzz-card {
            position: relative;
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 18px;
            padding: 12px 14px;
            transition: transform .25s ease, box-shadow .25s ease;
            opacity: 0;
            transform: translateY(8px);
            animation: buzzIn .55s cubic-bezier(.16,1,.3,1) forwards;
        }
        @supports (backdrop-filter: blur(14px)) {
            .buzz-card {
                background: linear-gradient(135deg, rgba(255,255,255,.1) 0%, rgba(255,255,255,.03) 100%);
                backdrop-filter: blur(20px) saturate(140%);
                -webkit-backdrop-filter: blur(20px) saturate(140%);
                box-shadow: inset 0 1px 0 rgba(255,255,255,.15);
            }
        }
        @keyframes buzzIn { to { opacity: 1; transform: none; } }
        .buzz-card:hover { transform: translateY(-2px); box-shadow: 0 14px 30px -14px rgba(0,0,0,.5); }
        .buzz-card:nth-child(1){ animation-delay: .05s; }
        .buzz-card:nth-child(2){ animation-delay: .18s; }
        .buzz-card:nth-child(3){ animation-delay: .31s; }
        .buzz-card:nth-child(4){ animation-delay: .44s; }
        .buzz-card:nth-child(5){ animation-delay: .57s; }
        .buzz-card:nth-child(6){ animation-delay: .70s; }
        .buzz-card:nth-child(7){ animation-delay: .83s; }

        .buzz-card.fresh {
            border-color: rgba(27,212,217,.45);
            box-shadow: 0 0 0 1px rgba(27,212,217,.25), 0 14px 36px -14px rgba(27,212,217,.4);
            background: linear-gradient(135deg, rgba(27,212,217,.10), rgba(61,107,255,.06));
        }
        .buzz-card .fresh-tag {
            position: absolute; top: -10px; left: 14px;
            font-size: 9px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase;
            background: linear-gradient(90deg, var(--c2), var(--c3));
            color: #fff; padding: 3px 8px; border-radius: 999px;
            animation: buzzFresh 2.4s ease-in-out infinite;
        }
        @keyframes buzzFresh { 50% { transform: translateY(-2px); } }

        /* FOLLOW */
        .bz-follow { display: grid; grid-template-columns: 44px 1fr auto; gap: 12px; align-items: center; }
        .bz-avatar { position: relative; width: 44px; height: 44px; border-radius: 50%; overflow: hidden; border: 2px solid rgba(27,212,217,.5); }
        .bz-avatar img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .bz-avatar .on { position: absolute; right: -2px; bottom: -2px; width: 12px; height: 12px; border-radius: 50%; background: #1bd4d9; border: 2px solid #0d0d12; box-shadow: 0 0 0 0 rgba(27,212,217,.6); animation: wsPing 2s infinite; }
        .bz-follow .name { font-weight: 700; font-size: 13px; }
        .bz-follow .meta { font-size: 11px; color: #9ca3af; margin-top: 1px; }
        .bz-follow .btn { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; background: linear-gradient(90deg, var(--c1), var(--c2)); color: #fff; padding: 6px 11px; border-radius: 999px; white-space: nowrap; }

        /* PURCHASE */
        .bz-buy { display: grid; grid-template-columns: 56px 1fr auto; gap: 12px; align-items: center; }
        .bz-thumb { width: 56px; height: 56px; border-radius: 12px; overflow: hidden; position: relative; flex-shrink: 0; }
        .bz-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .bz-thumb .tag { position: absolute; top: 4px; left: 4px; background: rgba(0,0,0,.65); color: #fff; font-size: 8px; font-weight: 800; padding: 2px 5px; border-radius: 4px; letter-spacing: .04em; text-transform: uppercase; }
        .bz-buy .product { font-size: 12px; font-weight: 700; }
        .bz-buy .who { font-size: 11px; color: #9ca3af; margin-top: 2px; }
        .bz-buy .price { background: rgba(34,197,94,.15); color: #4ade80; font-weight: 800; font-size: 12px; padding: 7px 10px; border-radius: 10px; white-space: nowrap; display: inline-flex; align-items: center; gap: 5px; border: 1px solid rgba(74,222,128,.25); }
        .bz-buy .price .d { width: 5px; height: 5px; border-radius: 50%; background: #4ade80; box-shadow: 0 0 6px #4ade80; animation: wsDot 1.5s ease-in-out infinite; }

        /* LIVE VIEWS */
        .bz-views { display: grid; grid-template-columns: 40px 1fr; gap: 12px; align-items: center; }
        .bz-views .ic { width: 40px; height: 40px; border-radius: 12px; background: linear-gradient(135deg, #3d6bff, #90acff); display: flex; align-items: center; justify-content: center; color: #fff; position: relative; flex-shrink: 0; }
        .bz-views .ic::after { content: ""; position: absolute; inset: -4px; border-radius: 14px; border: 2px solid rgba(61,107,255,.5); animation: geoPulse 2.2s ease-out infinite; }
        .bz-views .row { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; font-size: 12px; }
        .bz-views .row b { font-weight: 700; }
        .bz-views .num { font-size: 15px; font-weight: 800; background: linear-gradient(90deg, var(--c1), var(--c2)); -webkit-background-clip: text; background-clip: text; color: transparent; font-variant-numeric: tabular-nums; }
        .bz-views .track { height: 4px; border-radius: 999px; background: rgba(255,255,255,.1); overflow: hidden; margin-top: 7px; }
        .bz-views .fill { height: 100%; width: 0; background: linear-gradient(90deg, var(--c1), var(--c2)); border-radius: 999px; animation: bzBar 2.5s cubic-bezier(.16,1,.3,1) forwards 1s; }
        @keyframes bzBar { to { width: 72%; } }

        /* FORM */
        .bz-form { display: grid; grid-template-columns: 40px 1fr auto; gap: 12px; align-items: center; }
        .bz-form .ic { width: 40px; height: 40px; border-radius: 12px; background: linear-gradient(135deg, #ff8a3c, #ffc845); display: flex; align-items: center; justify-content: center; color: #fff; flex-shrink: 0; }
        .bz-form .who { font-size: 12px; font-weight: 700; }
        .bz-form .subj { font-size: 11px; color: #9ca3af; margin-top: 2px; font-style: italic; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .bz-form .pri { font-size: 9px; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; padding: 5px 9px; border-radius: 999px; background: rgba(255,138,60,.18); color: #ff8a3c; border: 1px solid rgba(255,138,60,.3); white-space: nowrap; }

        /* TIP */
        .bz-tip { display: grid; grid-template-columns: 44px 1fr auto; gap: 12px; align-items: center; }
        .bz-coin {
            width: 44px; height: 44px; border-radius: 50%;
            background: radial-gradient(circle at 35% 30%, #fef08a, #f59e0b 70%, #b45309);
            display: flex; align-items: center; justify-content: center;
            color: #78350f; font-weight: 900; font-size: 18px; flex-shrink: 0;
            box-shadow: 0 8px 20px -6px rgba(245,158,11,.5), inset 0 -3px 6px rgba(120,53,15,.3);
            animation: coinFlip 4s ease-in-out infinite;
        }
        @keyframes coinFlip { 0%,75% { transform: rotateY(0); } 88% { transform: rotateY(180deg); } 100% { transform: rotateY(360deg); } }
        .bz-tip .who { font-size: 12px; }
        .bz-tip .who b { font-weight: 700; }
        .bz-tip .msg { font-size: 11px; color: #9ca3af; margin-top: 1px; font-style: italic; }
        .bz-tip .amt { font-size: 16px; font-weight: 900; color: #fde047; white-space: nowrap; text-shadow: 0 0 12px rgba(253,224,71,.4); }
        .bz-tip .amt small { font-size: 10px; opacity: .8; font-weight: 700; }

        /* QR scan */
        .bz-qr { display: grid; grid-template-columns: 40px 1fr auto; gap: 12px; align-items: center; }
        .bz-qr .ic { width: 40px; height: 40px; border-radius: 12px; background: linear-gradient(135deg, #1bd4d9, #06b6d4); display: flex; align-items: center; justify-content: center; color: #fff; flex-shrink: 0; }
        .bz-qr .label { font-size: 12px; font-weight: 700; }
        .bz-qr .meta { font-size: 11px; color: #9ca3af; margin-top: 1px; }
        .bz-qr .spark { display: inline-flex; gap: 2px; align-items: end; height: 22px; }
        .bz-qr .spark i { width: 3px; background: linear-gradient(180deg, var(--c1), var(--c2)); border-radius: 1.5px; animation: sparkPulse 1.4s ease-in-out infinite; transform-origin: bottom; }
        .bz-qr .spark i:nth-child(1){ height: 30%; }
        .bz-qr .spark i:nth-child(2){ height: 50%; animation-delay: .15s; }
        .bz-qr .spark i:nth-child(3){ height: 75%; animation-delay: .3s; }
        .bz-qr .spark i:nth-child(4){ height: 90%; animation-delay: .45s; }
        .bz-qr .spark i:nth-child(5){ height: 60%; animation-delay: .6s; }
        @keyframes sparkPulse { 50% { opacity: .45; transform: scaleY(.65); } }

        /* GOAL */
        .bz-goal { padding: 10px 14px 12px; }
        .bz-goal .top { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
        .bz-goal .trophy { width: 34px; height: 34px; border-radius: 10px; background: linear-gradient(135deg, #f59e0b, #ef4444); display: flex; align-items: center; justify-content: center; color: #fff; flex-shrink: 0; box-shadow: 0 8px 18px -8px rgba(239,68,68,.6); }
        .bz-goal .title { font-size: 12px; font-weight: 800; flex: 1; }
        .bz-goal .pct { font-size: 11px; font-weight: 800; color: #4ade80; font-variant-numeric: tabular-nums; }
        .bz-goal .track { height: 6px; background: rgba(255,255,255,.08); border-radius: 999px; overflow: hidden; position: relative; }
        .bz-goal .fill { height: 100%; width: 100%; background: linear-gradient(90deg, var(--c2), var(--c3), var(--c4), var(--c5)); border-radius: 999px; box-shadow: 0 0 12px rgba(61,107,255,.5); transform-origin: left; transform: scaleX(0); animation: goalFill 2.4s cubic-bezier(.16,1,.3,1) forwards .3s; }
        @keyframes goalFill { to { transform: scaleX(1); } }
        .bz-goal .conf { position: absolute; top: -2px; right: 0; font-size: 14px; animation: bzConfetti 2.6s ease-in-out infinite; pointer-events: none; }
        @keyframes bzConfetti { 0%,80% { transform: translate(0,0) rotate(0); opacity: 1; } 90% { transform: translate(2px,-6px) rotate(15deg); } 100% { transform: translate(0,0) rotate(0); opacity: 1; } }

        @media (prefers-reduced-motion: reduce) {
            .buzz-card, .buzz-card.fresh .fresh-tag, .bz-avatar .on,
            .bz-views .ic::after, .bz-views .fill, .bz-coin,
            .bz-buy .price .d, .bz-qr .spark i, .bz-goal .fill, .bz-goal .conf
            { animation: none !important; }
            .buzz-card { opacity: 1; transform: none; }
            .bz-views .fill { width: 72%; }
            .bz-goal .fill { transform: scaleX(1); }
        }

        /* ========================================================
           LIGHT MODE — overrides for the marketing homepage.
           Toggled by adding `light-mode` to <html> (Cmd/Ctrl+I or
           the sun/moon button in the nav). The page heavily uses
           Tailwind's dark utilities so we re-skin them here rather
           than rewriting 4000+ lines of markup.
           ======================================================== */
        html.light-mode {
            --ink: #0f172a;
            --bg:  #f8fafc;
            --bg-2:#eef2ff;
            --bg-3:#f5f3ff;
            color-scheme: light;
        }
        html.light-mode body { background: var(--bg); color: #0f172a; }

        /* Aurora strengthened for visible glass translucency */
        html.light-mode .aurora { opacity: 0.25; }
        html.light-mode .aurora b { mix-blend-mode: multiply; opacity: 0.15; }

        /* ---- Generic dark utilities → light equivalents ---- */
        html.light-mode .text-white                       { color: #0f172a; }
        html.light-mode .text-white\/80                   { color: rgba(15,23,42,.78); }
        html.light-mode .text-white\/70                   { color: rgba(15,23,42,.70); }
        html.light-mode .text-white\/60                   { color: rgba(15,23,42,.58); }

        /* ---- Keep white text white when sitting on the dark brand gradient ----
           .grad-bar stays a dark gradient even in light mode, so the global
           .text-white → dark rule above would render text dark-on-dark
           (marquee strip, "Sign up free" pill, Dashboard pill, CTA buttons,
           FAQ chevrons, gradient avatars, etc.). Restore white inside grad-bar. */
        html.light-mode .grad-bar,
        html.light-mode .grad-bar .text-white,
        html.light-mode .grad-bar i,
        html.light-mode .grad-bar span,
        html.light-mode .grad-bar a,
        html.light-mode .grad-bar button         { color: #ffffff !important; }
        html.light-mode .grad-bar .text-white\/80 { color: rgba(255,255,255,.85) !important; }
        html.light-mode .grad-bar .text-white\/70 { color: rgba(255,255,255,.78) !important; }
        html.light-mode .grad-bar .text-white\/60 { color: rgba(255,255,255,.70) !important; }
        html.live, /* sentinel — keeps the file parseable */
        html.light-mode .text-gray-200                    { color: #1f2937; }
        html.light-mode .text-gray-300                    { color: #334155; }
        html.light-mode .text-gray-400                    { color: #475569; }
        html.light-mode .text-gray-500                    { color: #64748b; }
        html.light-mode .text-gray-600                    { color: #475569; }

        html.light-mode .bg-white\/5                      { background-color: rgba(15,23,42,.04); }
        html.light-mode .bg-white\/10                     { background-color: rgba(15,23,42,.06); }
        html.light-mode .border-white\/5                  { border-color: rgba(15,23,42,.08); }
        html.light-mode .border-white\/10                 { border-color: rgba(15,23,42,.10); }
        html.light-mode .border-white\/20                 { border-color: rgba(15,23,42,.14); }
        html.light-mode .bg-white                         { background-color: #ffffff; }
        html.light-mode .bg-gray-100                      { background-color: #f1f5f9; }

        /* ---- Hero / nav surfaces with hardcoded hex ---- */
        html.light-mode .bg-\[\#0a0a14\]                  { background-color: #ffffff; }
        html.light-mode .bg-\[\#0a0a14\]\/80              { background-color: rgba(255,255,255,.85); }
        html.light-mode .bg-\[\#08020f\]                  { background-color: #f8fafc; }

        /* Hover variants on the nav links */
        html.light-mode .hover\:text-white:hover          { color: #0f172a; }
        html.light-mode .hover\:bg-white\/5:hover         { background-color: rgba(15,23,42,.05); }

        /* Anything still using a near-black inline background */
        html.light-mode [style*="background:#0a0a14"],
        html.light-mode [style*="background-color:#0a0a14"],
        html.light-mode [style*="background:#14091f"],
        html.light-mode [style*="background:#1c0e2e"] { background: #ffffff !important; color: #0f172a; }

        /* Headings that are gradient-clipped stay vibrant; plain ones flip */
        html.light-mode h1:not(.grad-text),
        html.light-mode h2:not(.grad-text),
        html.light-mode h3:not(.grad-text),
        html.light-mode h4:not(.grad-text) { color: #0f172a; }

        /* Cards built from translucent white surfaces — give a real liquid glass look */
        html.light-mode .glass-card,
        html.light-mode .feature-card,
        html.light-mode .pricing-card,
        html.light-mode .step-card {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid transparent;
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.4), inset 1.8px 3px 0 -2px rgba(255,255,255,0.9), inset -2px -2px 0 -2px rgba(255,255,255,0.8), inset -3px -8px 1px -6px rgba(255,255,255,0.6), inset -0.3px -1px 4px 0 rgba(0,0,0,0.05), inset 0 0 8px 1px rgba(0,0,0,0.02), 0 12px 32px rgba(0,0,0,0.08);
        }
        @supports (backdrop-filter: blur(8px)) {
            html.light-mode .glass-card,
            html.light-mode .feature-card,
            html.light-mode .pricing-card,
            html.light-mode .step-card {
                background: linear-gradient(135deg, rgba(255,255,255,0.25) 0%, rgba(255,255,255,0.1) 100%);
                backdrop-filter: blur(6px) saturate(180%) brightness(1.05);
                -webkit-backdrop-filter: blur(6px) saturate(180%) brightness(1.05);
            }
        }

        /* Inputs / placeholders */
        html.light-mode input::placeholder,
        html.light-mode textarea::placeholder { color: #94a3b8; }

        /* Theme toggle button */
        .theme-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 38px; height: 38px; border-radius: 999px;
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.10);
            color: #e2e8f0; cursor: pointer;
            transition: background .2s ease, color .2s ease, transform .2s ease;
        }
        .theme-btn:hover { background: rgba(255,255,255,.12); color: #fff; transform: rotate(15deg); }
        html.light-mode .theme-btn {
            background: #f1f5f9; border-color: #e2e8f0; color: #334155;
        }
        html.light-mode .theme-btn:hover { background: #e2e8f0; color: #0f172a; }

        /* ---- Soften brand gradient accents on white ---- */
        /* The default gradient stops are calibrated for a near-black bg and
           look glaring on a white page. Use deeper, slightly desaturated
           stops so the "Sign up free" button + headline underline read as
           premium accents instead of neon. White button text stays legible
           because the mid-stops are still dark enough for contrast. */
        html.light-mode .grad-bar {
            background: linear-gradient(95deg, #2342c7, #2b54eb);
        }
        html.light-mode .grad-text {
            background: none;
            -webkit-text-fill-color: initial;
            color: #2342c7;
        }
        html.light-mode .btn-glow::after {
            background: conic-gradient(from 0deg, #2342c7, #2b54eb, #2342c7);
            filter: blur(10px);
        }
        html.light-mode .btn-glow:hover::after { opacity: .35; }
        /* Tone down the violet drop-shadow halo on the Sign up free button */
        html.light-mode .shadow-\[\#3d6bff\]\/30 {
            --tw-shadow-color: rgba(61,107,255,.12);
            --tw-shadow: var(--tw-shadow-colored);
            box-shadow: 0 10px 15px -3px rgba(61,107,255,.12), 0 4px 6px -4px rgba(61,107,255,.10);
        }

        /* ====================================================================
           LIGHT MODE — section demo widgets (full-page legibility audit)
           The hero float cards/metric strip were handled above. These rules
           re-skin the remaining custom-class demo widgets that live on light
           .glass cards (Share / Workspace / Grow / Buzz) plus the hero
           showcase tile rail, so white-on-translucent text reads on the light
           surfaces. Widgets sitting on self-contained dark/colored surfaces
           (phone mockups .bb-*/.mf-phone, the dark geo-map, the purple coach
           card, vivid .bz-* icon chips) are intentionally left untouched.
           Dark mode is unaffected. */

        /* Glass card surfaces (.glass-card handled earlier; add bare .glass/.glass-2) */
        html.light-mode .glass,
        html.light-mode .glass-2 {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid transparent;
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.4), inset 1.8px 3px 0 -2px rgba(255,255,255,0.9), inset -2px -2px 0 -2px rgba(255,255,255,0.8), inset -3px -8px 1px -6px rgba(255,255,255,0.6), inset -0.3px -1px 4px 0 rgba(0,0,0,0.05), inset 0 0 8px 1px rgba(0,0,0,0.02), 0 12px 32px rgba(0,0,0,0.08);
        }
        @supports (backdrop-filter: blur(8px)) {
            html.light-mode .glass,
            html.light-mode .glass-2 {
                background: linear-gradient(135deg, rgba(255,255,255,0.25) 0%, rgba(255,255,255,0.1) 100%);
                backdrop-filter: blur(6px) saturate(180%) brightness(1.05);
                -webkit-backdrop-filter: blur(6px) saturate(180%) brightness(1.05);
            }
        }

        /* Ambient washes behind glass grids in light mode */
        html.light-mode .glass-ambient-wash {
            position: relative;
            isolation: isolate;
        }
        html.light-mode .glass-ambient-wash::before {
            content: "";
            position: absolute;
            inset: -20%;
            z-index: -1;
            pointer-events: none;
            background: radial-gradient(circle at 50% 50%, rgba(56, 189, 248, 0.35) 0%, rgba(129, 140, 248, 0.2) 40%, transparent 70%);
            filter: blur(60px);
        }

        /* In light mode the two #features product-preview panels render as
           light cards (matching the white "Themes & design controls" / hero
           tiles), so pin them explicitly white with a subtle border. This makes
           the surface unambiguous for the dark `.bl-*` labels below — never
           dark-on-dark. Dark mode keeps the original dark fill (via the base
           `bg-[#0a0a14]` utility on the element). */
        html.light-mode .feat-preview {
            background-color: #ffffff;
            border-color: rgba(15,23,42,.08);
        }

        /* ---- Editor-mock block labels (.bl-*) ----
           The "Reorder blocks" build-list renders on a light card in light
           mode, but its label colours are calibrated for a dark panel: titles
           are #fff (invisible on white), subtitles/column labels are near-white,
           and chip pills use translucent-white fills. Re-skin them so every
           block label reads clearly on the light card. Dark mode is unaffected. */
        html.light-mode .bl-title { color: #0f172a; }
        html.light-mode .bl-sub   { color: #64748b; }
        /* AI builder prompt — light-mode legibility */
        html.light-mode .ai-prompt-input { background: #f8fafc; border-color: #e2e8f0; }
        html.light-mode .ai-typed { color: #1e293b; }
        html.light-mode .ai-typed .ai-placeholder { color: #94a3b8; }
        html.light-mode .ai-status { color: #64748b; }
        html.light-mode .ai-chip { background: #f1f5f9; border-color: #e2e8f0; color: #475569; }
        html.light-mode .ai-build-hint { color: #94a3b8; }
        html.light-mode .bl-col-t { color: #334155; }
        html.light-mode .bl-chip  {
            background: #f1f5f9;
            border-color: #e2e8f0;
            color: #475569;
        }

        /* ---- Hero showcase tile rail ---- */
        html.light-mode .hero-tile {
            background: #ffffff;
            border-color: #e2e8f0;
            color: #0f172a;
        }
        html.light-mode .hero-tile:hover {
            border-color: rgba(61,107,255,.35);
            background: #f8fafc;
        }
        html.light-mode .hero-tile.is-active {
            border-color: rgba(61,107,255,.55);
            background: rgba(61,107,255,.08);
        }
        html.light-mode .hero-tile-thumb { background: #f1f5f9; }
        html.light-mode .hero-tile-fallback { color: #2342c7; }
        html.light-mode .hero-tile-fallback .ftl { color: #475569; text-shadow: none; }
        html.light-mode .hero-tile-label { color: #475569; }

        /* ---- Share section · branded short link ---- */
        html.light-mode .sl-pill {
            background: #f1f5f9;
            border-color: #e2e8f0;
        }
        html.light-mode .sl-pill .host { color: #475569; }
        html.light-mode .sl-counter { color: #64748b; }
        html.light-mode .th-pill {
            color: #475569;
            background: #f1f5f9;
            border-color: #e2e8f0;
        }

        /* ---- Share section · mobile-first stats (phone stays dark) ---- */
        html.light-mode .mf-stats > div {
            background: #f8fafc;
            border-color: #e2e8f0;
        }
        html.light-mode .mf-stats span { color: #64748b; }
        /* ---- New animation elements: theme preview bar + stat bar ---- */
        html.light-mode .th-preview-bar { background: #e2e8f0; border-color: #cbd5e1; }
        html.light-mode #th-active-label { background: rgba(61,107,255,.08) !important; border-color: rgba(61,107,255,.35) !important; }
        html.light-mode .build-row.bl-dragging { background: rgba(61,107,255,.1) !important; }
        html.light-mode .build-row.bl-ghost { background: rgba(15,23,42,.04) !important; }

        /* ---- Share section · channel chips ---- */
        html.light-mode .ch-icon {
            background: #f1f5f9;
            border-color: #e2e8f0;
        }

        /* ---- Share section · custom-domain DNS bar/records ---- */
        html.light-mode .cd-bar {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
        }
        html.light-mode .cd-bar .brand { color: #0f172a; }
        html.light-mode .cd-bar .sub { color: #64748b; }
        html.light-mode .cd-bar .tld { color: #3d6bff; }
        html.light-mode .cd-bar .path { color: #0891b2; }
        html.light-mode .cd-rec { color: #64748b; }
        html.light-mode .cd-rec .val { color: #334155; }
        html.light-mode .cd-rec .ty { color: #2342c7; }

        /* ---- Workspace section · live activity rows ---- */
        html.light-mode .ws-row {
            background: #f8fafc;
            border-color: #e2e8f0;
        }
        html.light-mode .ws-name { color: #0f172a; }
        html.light-mode .ws-task { color: #64748b; }
        html.light-mode .ws-prog { background: #e2e8f0; }
        html.light-mode .ws-b-edit    { color: #2342c7; }
        html.light-mode .ws-b-up      { color: #0e7490; }
        html.light-mode .ws-b-comment { color: #be185d; }
        html.light-mode .ws-b-ok      { color: #15803d; }
        html.light-mode .ws-b-view    { color: #b45309; }

        /* ---- Grow section · country flag marquee (map stays dark) ---- */
        html.light-mode .geo-flags {
            background: #f8fafc;
            border-color: #e2e8f0;
        }
        html.light-mode .geo-flags::before { background: linear-gradient(90deg, #ffffff, transparent); }
        html.light-mode .geo-flags::after  { background: linear-gradient(-90deg, #ffffff, transparent); }
        html.light-mode .geo-flag { color: #475569; }

        /* ---- Buzz section · live event cards ---- */
        html.light-mode .buzz-card {
            background: #ffffff;
            border-color: #e2e8f0;
        }
        html.light-mode .buzz-card.fresh {
            border-color: rgba(27,212,217,.5);
            background: linear-gradient(135deg, rgba(27,212,217,.10), rgba(61,107,255,.06));
        }
        html.light-mode .bz-follow .meta,
        html.light-mode .bz-buy .who,
        html.light-mode .bz-form .subj,
        html.light-mode .bz-tip .msg,
        html.light-mode .bz-qr .meta { color: #64748b; }
        html.light-mode .bz-views .track,
        html.light-mode .bz-goal .track { background: #e2e8f0; }
        html.light-mode .bz-goal .pct { color: #15803d; }
        html.light-mode .bz-buy .price { color: #15803d; }
        html.light-mode .bz-tip .amt { color: #ca8a04; text-shadow: none; }
    </style>
</head>
<body class="overflow-x-hidden">

@include('common.partials.announcement-banner', ['surface' => 'site', 'fixed' => true])

{{-- ============ Aurora background ============ --}}
<div class="aurora" aria-hidden="true"><b></b><b></b><b></b><b></b></div>

{{-- ============================ NAV ============================ --}}
@include('public.partials.header', ['useModal' => true, 'fixed' => true])

{{-- Relationship band — only on a non-primary global domain. Sits directly
     below the (fixed) menu, explaining how this sub-brand domain relates to
     the primary domain. Hidden entirely on the primary domain. --}}
@php $__rel = \App\Modules\Common\Support\DomainBranding::relationship(); @endphp
@if($__rel)
<div class="relative z-30 pt-16 border-b border-white/10 bg-white/[0.04] backdrop-blur-xl">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex flex-col sm:flex-row items-center justify-center gap-x-3 gap-y-1 text-center">
        <span class="text-sm text-gray-200">{{ $__rel['blurb'] }}</span>
        <a href="{{ $__rel['primary_url'] }}"
           class="inline-flex items-center gap-1 text-sm font-semibold text-blue-300 hover:text-blue-200 whitespace-nowrap">
            Visit {{ $__rel['primary_domain'] }} <i class="fas fa-arrow-right text-[10px]"></i>
        </a>
    </div>
</div>
@endif

{{-- Scroll-spy strip removed: it duplicated the Product menu in the header.
     The home page now matches all other marketing pages, which use a sticky
     sidebar TOC (features, faqs, policy) instead of a horizontal sub-nav. --}}

{{-- ==================== ZONE · HOOK & CREDIBILITY ==================== --}}
@include('home.partials.hero')

{{-- ==================== DEFERRED BELOW-THE-FOLD SECTIONS ====================
     Everything after the hero (brand section, showcase, AI zone, pricing,
     FAQ, final CTA…) is server-rendered by the /home/sections fragment and
     injected here right after first paint. This keeps the initial response
     to just header + hero + CTA (small HTML, no plan/link-type queries),
     while the full brand story still arrives moments later — triggered by
     window load, first interaction, or a short failsafe timer, whichever
     comes first. Injected <script> tags are re-created so they execute;
     Alpine's mutation observer initializes injected x-data trees, and
     window.homeEnhance() + marketingAnimScan() re-run the reveal/anim
     observers over the new content (with the usual visibility backstops). --}}
<div id="home-deferred" data-src="{{ route('home.sections') }}" aria-busy="true" style="min-height:70vh">
    <div id="home-deferred-loading" style="display:flex;align-items:center;justify-content:center;padding:6rem 1rem;">
        <span style="width:28px;height:28px;border-radius:50%;border:3px solid rgba(120,140,255,.25);border-top-color:#3d6bff;animation:spinSlow 0.8s linear infinite;display:inline-block" aria-hidden="true"></span>
        <span class="sr-only">Loading more…</span>
    </div>
    <noscript>
        {{-- No JS: the fragment can't be fetched. Keep the page useful with
             direct links to the same content on always-full pages. --}}
        <div style="max-width:42rem;margin:0 auto;padding:3rem 1.5rem;text-align:center">
            <p style="margin-bottom:1rem">Explore everything Sayzio offers:</p>
            <p><a href="{{ route('site.features') }}" style="text-decoration:underline">All features</a> ·
               <a href="{{ route('site.pricing') }}" style="text-decoration:underline">Pricing</a> ·
               <a href="{{ route('site.how-it-works') }}" style="text-decoration:underline">How it works</a></p>
        </div>
    </noscript>
</div>
<script>
    (function () {
        var box = document.getElementById('home-deferred');
        if (!box) return;
        var started = false;
        function execScripts(root) {
            // Scripts inserted via innerHTML never execute — recreate each
            // one in place so section runtimes (demos, Alpine helpers) run.
            var scripts = root.querySelectorAll('script');
            Array.prototype.forEach.call(scripts, function (old) {
                var s = document.createElement('script');
                Array.prototype.forEach.call(old.attributes, function (a) { s.setAttribute(a.name, a.value); });
                s.textContent = old.textContent;
                old.parentNode.replaceChild(s, old);
            });
        }
        function load() {
            if (started) return;
            started = true;
            fetch(box.getAttribute('data-src'), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            }).then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.text();
            }).then(function (html) {
                box.innerHTML = html;
                box.style.minHeight = '';
                box.removeAttribute('aria-busy');
                execScripts(box);
                if (window.homeEnhance) window.homeEnhance(box);
                if (window.marketingAnimScan) window.marketingAnimScan(box);
                window.dispatchEvent(new CustomEvent('home:sections-loaded'));
                // If the visitor arrived with an in-page anchor (e.g. /#features),
                // honor it now that the target exists.
                if (location.hash.length > 1) {
                    var t = null;
                    try { t = document.querySelector(location.hash); } catch (e) {}
                    if (t) t.scrollIntoView({ block: 'start' });
                }
            }).catch(function () {
                // Fetch failed — allow a retry on the next trigger.
                started = false;
            });
        }
        // Earliest of: window load (+tiny delay so it never competes with
        // above-the-fold work), first interaction, or a failsafe timer.
        ['scroll', 'pointerdown', 'keydown', 'touchstart'].forEach(function (ev) {
            window.addEventListener(ev, load, { once: true, passive: true });
        });
        if (document.readyState === 'complete') setTimeout(load, 50);
        else window.addEventListener('load', function () { setTimeout(load, 50); });
        setTimeout(load, 3000);
    })();
</script>

{{-- ============================ FOOTER ============================ --}}
@include('public.partials.footer')

@include('common.partials.global-shortcuts')

<script>
    document.documentElement.classList.add('js');
    // Idempotent enhancement pass over `root` (defaults to the whole
    // document). Runs once at DOMContentLoaded for the initial header/hero
    // markup and AGAIN over the injected deferred sections (see the
    // #home-deferred loader). Every element is stamped via dataset flags so
    // re-running never double-observes or double-binds.
    window.homeEnhance = function (root) {
        root = root || document;
        const pick = (sel) => Array.prototype.filter.call(
            root.querySelectorAll(sel), el => !el.dataset.homeEnhanced || !el.dataset.homeEnhanced.includes(sel)
        );
        const stamp = (el, sel) => { el.dataset.homeEnhanced = (el.dataset.homeEnhanced || '') + '|' + sel; };

        const reveals = pick('.reveal');
        reveals.forEach(el => stamp(el, '.reveal'));
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.05, rootMargin: '0px 0px -10px 0px' });
            reveals.forEach(el => observer.observe(el));
            // Safety net for elements the observer might miss — but NEVER
            // force showcase cards visible on load; they must stay gated on
            // real intersection so their entrance/alive motion only fires
            // once the grid scrolls into view.
            setTimeout(() => reveals.forEach(el => {
                if (!el.classList.contains('showcase-card')) el.classList.add('visible');
            }), 250);
        } else {
            reveals.forEach(el => el.classList.add('visible'));
        }

        // Toggle sc-alive on showcase cards so their continuous glow/blob
        // animations only run while the card is on screen and pause once it
        // scrolls out of view (saves GPU/battery on low-power devices). The
        // one-time entrance reveal stays gated on .visible above and is never
        // removed, so scrolling back in never re-triggers the entrance keyframe.
        // Skipped under prefers-reduced-motion (those animations are already
        // killed in CSS), keeping the page fully static.
        const showcaseCards = pick('.showcase-card');
        showcaseCards.forEach(el => stamp(el, '.showcase-card'));
        const scReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (showcaseCards.length && !scReducedMotion && 'IntersectionObserver' in window) {
            const scObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    entry.target.classList.toggle('sc-alive', entry.isIntersecting);
                });
            }, { threshold: 0.1 });
            showcaseCards.forEach(el => scObserver.observe(el));
        }

        // Toggle pp-in-view on pillar preview blocks so their subtle animations
        // only run while the card is on screen (and pause when scrolled away).
        const pillarPreviews = pick('.pillar-preview');
        pillarPreviews.forEach(el => stamp(el, '.pillar-preview'));
        if (pillarPreviews.length && 'IntersectionObserver' in window) {
            const ppObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    entry.target.classList.toggle('pp-in-view', entry.isIntersecting);
                });
            }, { threshold: 0.15 });
            pillarPreviews.forEach(el => ppObserver.observe(el));
        } else {
            pillarPreviews.forEach(el => el.classList.add('pp-in-view'));
        }

        // Toggle aud-in-view on audience cards so their subtle animations
        // only run while the card is on screen.
        const audCards = pick('.audience-card');
        audCards.forEach(el => stamp(el, '.audience-card'));
        if (audCards.length && 'IntersectionObserver' in window) {
            const audObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    entry.target.classList.toggle('aud-in-view', entry.isIntersecting);
                });
            }, { threshold: 0.2 });
            audCards.forEach(el => audObserver.observe(el));
        } else {
            audCards.forEach(el => el.classList.add('aud-in-view'));
        }

        pick('a[href^="#"]').forEach(anchor => {
            stamp(anchor, 'a[href^="#"]');
            anchor.addEventListener('click', function (e) {
                const href = this.getAttribute('href');
                if (href === '#' || href.length < 2) return;
                const target = document.querySelector(href);
                if (target) { e.preventDefault(); target.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
            });
        });

        // (Hero phone parallax is gated by IntersectionObserver in
        // resources/views/home/partials/hero.blade.php so it only runs while
        // the hero is on screen.)

        // Showcase grid ("what you can create"): cursor-following spotlight
        // per card, isolated to the hovered card. Skipped entirely under
        // prefers-reduced-motion (no cursor-driven motion) and on touch/coarse
        // pointer devices (no hover, so no listeners doing useless work).
        const showcaseFinePointer = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
        if (!scReducedMotion && showcaseFinePointer) {
            showcaseCards.forEach(card => {
                card.addEventListener('pointermove', (e) => {
                    const rect = card.getBoundingClientRect();
                    card.style.setProperty('--sc-x', ((e.clientX - rect.left) / rect.width * 100) + '%');
                    card.style.setProperty('--sc-y', ((e.clientY - rect.top) / rect.height * 100) + '%');
                });
            });
        }
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => window.homeEnhance());
    } else {
        window.homeEnhance();
    }
</script>
@include('common.partials.cookie-consent', ['surface' => 'site'])
@include('common.partials.site-assistant', ['surface' => 'marketing'])
</body>
</html>
