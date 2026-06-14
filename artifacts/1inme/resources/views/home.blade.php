<!DOCTYPE html>
<html lang="en" class="{{ (($_COOKIE['1inme_theme'] ?? null) === 'light') ? 'light-mode' : '' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>1INME — One link to everything.</title>
    <meta name="description" content="1INME is the all-in-one link platform: drag-and-drop biolinks, short links, dynamic QR codes, live geographic analytics, a Performance Coach, follower system, forms, social proof and more.">
    @include('public.partials.marketing-share-meta')
    @include('public.partials.marketing-tracking')
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script defer src="{{ asset('js/vendor/alpine.min.js') }}"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/marketing-anim.css') }}?v=4">
    <script src="{{ asset('js/marketing-anim.js') }}?v=1" defer></script>
    <script>
        try {
            tailwind.config = {
                theme: { extend: { fontFamily: { sans: ['Space Grotesk', 'sans-serif'] } } }
            }
        } catch(e) {}
    </script>
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
            --c2: #7c3aed; /* purple */
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
        html.light-mode .aurora { opacity: 0.18; }
        html.light-mode .stack-card {
            background: rgba(15,23,42,0.85);
            border-color: rgba(15,23,42,0.30);
            color: #fff;
        }

        /* ============ Aurora background ============ */
        .aurora { position: fixed; inset: -10%; z-index: -1; pointer-events: none; opacity: .6; filter: blur(80px); }
        .aurora b {
            position: absolute; border-radius: 50%; mix-blend-mode: screen;
            animation: aurora 22s ease-in-out infinite;
        }
        .aurora b:nth-child(1) { top:-10%; left:-10%; width:60vw; height:60vw; background: var(--c2); animation-delay: -2s; }
        .aurora b:nth-child(2) { bottom:-15%; right:-10%; width:55vw; height:55vw; background: #8b5cf6; animation-delay: -8s; }
        .aurora b:nth-child(3) { top:30%; left:40%; width:40vw; height:40vw; background: #a855f7; animation-delay: -14s; }
        .aurora b:nth-child(4) { top:60%; left:5%; width:35vw; height:35vw; background: #6d28d9; opacity:.7; animation-delay: -18s; }
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
            background: conic-gradient(from 0deg, #7c3aed, #a855f7, #7c3aed);
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

        /* ============ Sparkline draw ============ */
        .spark-line { stroke-dasharray: 600; stroke-dashoffset: 600; animation: drawLine 2.4s ease-out forwards; }
        @keyframes drawLine { to { stroke-dashoffset: 0; } }

        /* ============ Health gauge ============ */
        .gauge-arc { stroke-dasharray: 251; stroke-dashoffset: 251; animation: gaugeFill 1.8s ease-out forwards; }
        @keyframes gaugeFill { to { stroke-dashoffset: 75; } }

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
            color: #a78bfa;
        }
        @keyframes gradShift { 0%,100%{ background-position: 0% 50%;} 50%{ background-position: 100% 50%;} }

        /* ============ Logo gradient bar ============ */
        .grad-bar {
            background: linear-gradient(95deg, #7c3aed, #a855f7);
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
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 12px 14px;
            display: flex; align-items: center; gap: 12px;
            color: #fff;
            box-shadow: 0 22px 48px -22px rgba(0,0,0,.65), 0 0 0 1px rgba(255,255,255,.04), inset 0 1px 0 rgba(255,255,255,.08);
            opacity: 0;
            animation: cardIn .7s cubic-bezier(.34,1.56,.64,1) forwards;
            animation-delay: var(--d, 0ms);
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
            .marquee, .marquee-rev, .eq i, .pulse-dot, .ring-pulse, .spark-line,
            .gauge-arc, .draw-line, .grad-text, .drift-a, .drift-b, .pop-in, .btn-glow::after,
            .stack-3d, .stack-card, .role-word,
            .pp-live-dot, .pp-nfc-pulse, .pp-tip-card, .pp-dm-bubble, .pp-coach-num, .pp-coach-bar,
            .aud-blob, .aud-arrow {
                animation: none !important; transition: none !important; transform: none !important; opacity: 1 !important;
            }
            .aud-in-view .aud-icon::after { animation: none !important; transform: translateX(-120%) !important; }
            .aud-in-view .aud-icon > i { animation: none !important; transform: none !important; }
            .pp-in-view .pp-qr-wrap::after { animation: none !important; opacity: 0 !important; }
            .pp-coach-arc { stroke-dashoffset: 12.66 !important; animation: none !important; }
            .spark-line, .draw-line { stroke-dashoffset: 0 !important; }
            .gauge-arc { stroke-dashoffset: 75 !important; }
            /* Simple crossfade fallback for the rotating hero */
            .role-word { transition: opacity .35s ease !important; }
            #hero-stack { transition: opacity .35s ease !important; }
            .role-word.rm-out, #hero-stack.rm-out { opacity: 0 !important; }
        }

        /* Make <picture> transparent to layout so existing img selectors / flex / grid rules still apply. */
        picture { display: contents; }

        /* ============ Focus ============ */
        a:focus-visible, button:focus-visible { outline: 2px solid var(--c2); outline-offset: 3px; border-radius: 8px; }

        /* ============ Phone frame ============ */
        .phone {
            width: 280px; aspect-ratio: 9/19; border-radius: 38px;
            background: #08020f; padding: 9px;
            box-shadow: 0 28px 80px -20px rgba(124,58,237,.45), 0 0 0 1px rgba(255,255,255,.06);
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
                0 50px 100px -30px rgba(124,58,237,.55),
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
            background: var(--phone-bg, linear-gradient(140deg,#7c3aed,#a855f7));
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
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            background: rgba(255,255,255,.18);
            border-color: rgba(255,255,255,.28);
            border-radius: 16px;
            padding: 9px 11px;
            gap: 10px;
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
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            border-radius: 16px;
            padding: 10px 12px;
            box-shadow: 0 18px 40px -16px rgba(0,0,0,.55);
            z-index: 11;
            color: #fff;
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
            .float-card--coach     { top: 360px;   left: -178px; width: 200px; box-shadow: 0 22px 50px -18px rgba(124,58,237,.5); }
            .float-card--revenue   { bottom: -8px; left: -158px; width: 168px; box-shadow: 0 22px 50px -18px rgba(255,138,60,.4); }
            /* Right lane (sit just outside the phone, only the inner edge brushes the bezel
               — never overlaps the screen content). Mirrors the left lane offsets. */
            .float-card--follower  { top: -16px;   right: -120px; left: auto; width: 188px; box-shadow: 0 22px 50px -18px rgba(236,72,153,.4); }
            .float-card--qr        { top: 240px;   right: -110px; left: auto; width: 178px; box-shadow: 0 22px 50px -18px rgba(124,58,237,.4); }
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
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
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
            backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
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
            backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
            overflow: hidden;
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
            padding: 2px; background: linear-gradient(135deg,#1bd4d9,#7c3aed);
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
        .hp-prof.var-social .pav { padding: 2px; background: conic-gradient(from 0deg,#ffc845,#e94e8c,#7c3aed,#ffc845); border: 0; animation: spinSlow 14s linear infinite; }
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
            background: conic-gradient(from 0deg, #ffc845, #e94e8c, #7c3aed, #ffc845);
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
            scrollbar-width: thin; scrollbar-color: rgba(124,58,237,.5) transparent;
        }
        .build-list::-webkit-scrollbar { width: 6px; }
        .build-list::-webkit-scrollbar-thumb { background: rgba(124,58,237,.45); border-radius: 6px; }
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
        .bl-mini-grid i { display:block; background: linear-gradient(135deg,#e94e8c,#7c3aed); border-radius: 3px; }
        .bl-mini-grid i:nth-child(2){ background: linear-gradient(135deg,#1bd4d9,#7c3aed); }
        .bl-mini-grid i:nth-child(3){ background: linear-gradient(135deg,#ff8a3c,#ffc845); }
        .bl-mini-grid i:nth-child(4){ background: linear-gradient(135deg,#7c3aed,#e94e8c); }
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
        .bl-chev { font-size: 10px; color:#a78bfa; flex-shrink:0; }
        .bl-stars { font-size: 8px; color: #ffc845; letter-spacing: 1px; flex-shrink:0; }
        .bl-col { flex:1; display:flex; align-items:center; gap:6px; padding: 4px 8px; background: rgba(255,255,255,.05);
            border: 1px dashed rgba(255,255,255,.18); border-radius: 10px; }
        .bl-col-t { font-size: 11px; font-weight: 700; color:#e5e7eb; }

        /* Build-section phone preview (biolink) */
        .bb-phone {
            width: 218px; aspect-ratio: 9/19; border-radius: 34px;
            background: #08020f; padding: 8px;
            box-shadow: 0 28px 70px -20px rgba(124,58,237,.55), 0 0 0 1px rgba(255,255,255,.08);
        }
        .bb-screen { position: relative; width:100%; height:100%; border-radius: 28px; overflow: hidden;
            background: linear-gradient(180deg,#7c3aed 0%,#e94e8c 55%,#ff8a3c 100%); }
        .bb-notch { position:absolute; top: 7px; left:50%; transform: translateX(-50%); width:64px; height:14px; background:#08020f; border-radius:10px; z-index:20; }
        .bb-scroll { position:absolute; inset: 28px 10px 10px; overflow-y: auto; display:flex; flex-direction:column; gap:7px;
            scrollbar-width: none; }
        .bb-scroll::-webkit-scrollbar { display:none; }
        .bb-prof { text-align:center; color:#fff; padding: 4px 0; }
        .bb-prof .bb-av { width: 44px; height:44px; border-radius:50%; background: rgba(255,255,255,.25);
            margin: 0 auto; display:flex; align-items:center; justify-content:center; font-size: 12px; font-weight: 900;
            border: 2px solid rgba(255,255,255,.4); }
        .bb-prof .bb-h { font-size: 11px; font-weight: 900; margin-top: 4px; }
        .bb-prof .bb-t { font-size: 8px; opacity: .85; margin-top: 1px; }
        .bb-prof .bb-soc { display:flex; justify-content:center; gap:7px; margin-top:5px; font-size:9px; color:#fff; opacity:.9; }
        .bb-hero { height: 54px; border-radius: 10px;
            background: linear-gradient(135deg,#1bd4d9 0%,#7c3aed 60%,#e94e8c 100%);
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
            background: linear-gradient(135deg,#e94e8c,#7c3aed); }
        .bb-gal i:nth-child(2){ background: linear-gradient(135deg,#1bd4d9,#7c3aed); }
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
            background: linear-gradient(135deg, rgba(236,72,153,.35), rgba(124,58,237,.35));
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
            border-color: rgba(124,58,237,.6);
            background: rgba(124,58,237,.14);
            box-shadow: 0 10px 24px -12px rgba(124,58,237,.7);
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
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            font-size: 11px; font-weight: 700;
            color: #fff;
            animation: blockFloat var(--bdur, 5s) ease-in-out infinite;
            animation-delay: var(--bdel, 0s);
            transition: transform .25s, background .25s, border-color .25s;
            will-change: transform;
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
        .glass { background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.08); backdrop-filter: blur(18px); }
        .glass-2 { background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.12); backdrop-filter: blur(18px); }

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
        .ws-b-edit   { background: rgba(124,58,237,.18); color: #c4b5fd; }
        .ws-b-edit .dot { background: #a78bfa; }
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
            background: #a78bfa;
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
            background: linear-gradient(135deg,#7c3aed,#e94e8c);
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
        .ws-cursor.c2 .lbl { background: linear-gradient(135deg,#1bd4d9,#7c3aed); }

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
        .th-pill--accent { background: linear-gradient(135deg, rgba(27,212,217,.18), rgba(124,58,237,.18)); color: #a5f3fc; border-color: rgba(27,212,217,.35); }

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
            background: linear-gradient(90deg, rgba(124,58,237,.55), rgba(233,78,140,.5));
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
        .cd-bar .tld { color: #a78bfa; }
        .cd-bar .path { color: #67e8f9; }
        .cd-bar::after {
            content: ""; position: absolute; left: -30%; top: 0; bottom: 0; width: 30%;
            background: linear-gradient(90deg, transparent, rgba(124,58,237,.25), transparent);
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
            font-weight: 800; color: #a78bfa;
            background: rgba(124,58,237,.15);
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
                linear-gradient(rgba(124,58,237,.08) 1px, transparent 1px),
                linear-gradient(90deg, rgba(124,58,237,.08) 1px, transparent 1px);
            background-size: 22px 22px;
            mask-image: radial-gradient(ellipse at center, #000 30%, transparent 80%);
            -webkit-mask-image: radial-gradient(ellipse at center, #000 30%, transparent 80%);
        }
        .geo-map .continents { position: absolute; inset: 0; }
        .geo-map .continents path {
            fill: rgba(124,58,237,.18);
            stroke: rgba(124,58,237,.3);
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
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 10px;
            padding: 7px 10px;
            font-size: 10px;
            color: #e5e7eb;
            display: flex; align-items: center; gap: 8px;
            max-width: 320px;
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
            background: rgba(255,255,255,.95); color: #7c3aed;
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
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            transition: transform .25s ease, box-shadow .25s ease;
            opacity: 0;
            transform: translateY(8px);
            animation: buzzIn .55s cubic-bezier(.16,1,.3,1) forwards;
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
            background: linear-gradient(135deg, rgba(27,212,217,.10), rgba(124,58,237,.06));
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
        .bz-views .ic { width: 40px; height: 40px; border-radius: 12px; background: linear-gradient(135deg, #7c3aed, #a78bfa); display: flex; align-items: center; justify-content: center; color: #fff; position: relative; flex-shrink: 0; }
        .bz-views .ic::after { content: ""; position: absolute; inset: -4px; border-radius: 14px; border: 2px solid rgba(124,58,237,.5); animation: geoPulse 2.2s ease-out infinite; }
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
        .bz-goal .fill { height: 100%; width: 100%; background: linear-gradient(90deg, var(--c2), var(--c3), var(--c4), var(--c5)); border-radius: 999px; box-shadow: 0 0 12px rgba(124,58,237,.5); transform-origin: left; transform: scaleX(0); animation: goalFill 2.4s cubic-bezier(.16,1,.3,1) forwards .3s; }
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

        /* Aurora softened to a faint pastel wash */
        html.light-mode .aurora { opacity: .22; filter: blur(110px); }

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

        /* Cards built from translucent white surfaces — give a real card look */
        html.light-mode .glass-card,
        html.light-mode .feature-card,
        html.light-mode .pricing-card,
        html.light-mode .step-card {
            background: #ffffff;
            border-color: #e2e8f0;
            box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 4px 14px -8px rgba(15,23,42,.10);
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
            background: linear-gradient(95deg, #6d28d9, #9333ea);
        }
        html.light-mode .grad-text {
            background: none;
            -webkit-text-fill-color: initial;
            color: #6d28d9;
        }
        html.light-mode .btn-glow::after {
            background: conic-gradient(from 0deg, #6d28d9, #9333ea, #6d28d9);
            filter: blur(10px);
        }
        html.light-mode .btn-glow:hover::after { opacity: .35; }
        /* Tone down the violet drop-shadow halo on the Sign up free button */
        html.light-mode .shadow-\[\#7c3aed\]\/30 {
            --tw-shadow-color: rgba(124,58,237,.12);
            --tw-shadow: var(--tw-shadow-colored);
            box-shadow: 0 10px 15px -3px rgba(124,58,237,.12), 0 4px 6px -4px rgba(124,58,237,.10);
        }
    </style>
</head>
<body class="overflow-x-hidden">

{{-- ============ Aurora background ============ --}}
<div class="aurora" aria-hidden="true"><b></b><b></b><b></b><b></b></div>

{{-- ============================ NAV ============================ --}}
@include('public.partials.header', ['useModal' => true, 'fixed' => true])

{{-- Scroll-spy strip removed: it duplicated the Product menu in the header.
     The home page now matches all other marketing pages, which use a sticky
     sidebar TOC (features, faqs, policy) instead of a horizontal sub-nav. --}}

@include('home.partials.hero')
{{-- ============================ MARQUEE STRIP ============================ --}}
@php $__skipMarquee = false; @endphp
<div class="grad-bar py-4 overflow-hidden border-y border-white/10" aria-hidden="true">
    <div class="flex whitespace-nowrap marquee">
        @for($i = 0; $i < 2; $i++)
        <span class="inline-flex items-center gap-8 mx-4">
            @foreach([
                ['fa-grip-vertical','Drag &amp; Drop Editor'],
                ['fa-globe','Live Geo Heatmap'],
                ['fa-bolt','Performance Coach'],
                ['fa-link','Short Links'],
                ['fa-qrcode','Dynamic QR Codes'],
                ['fa-users','Follower System'],
                ['fa-wpforms','Form Builder'],
                ['fa-bullhorn','Social Proof'],
                ['fa-address-book','Contacts Sync'],
                ['fa-phone','Built-in Dialer'],
            ] as $item)
                <span class="text-sm font-bold uppercase tracking-wider flex items-center gap-2 text-white"><i class="fas {{ $item[0] }}"></i>{!! $item[1] !!}</span>
                <span class="text-xl text-white/70">★</span>
            @endforeach
        </span>
        @endfor
    </div>
</div>

{{-- ============================ CREDIBILITY BAND (near-hero trust numbers) ============================ --}}
@include('public.partials.marketing-trust-band')

{{-- ============================ AUDIENCE (CREATORS / BUSINESSES / NETWORKING) ============================ --}}
@php
    $__audiences = [
        [
            'eyebrow' => 'Creators',
            'title'   => 'Turn followers into fans &mdash; and income.',
            'desc'    => 'One link for every drop, with tips, products, DMs, scheduled posts and an AI coach to keep you growing.',
            'icon'    => 'fa-microphone-lines',
            'color'   => '#e94e8c',
            'cta'     => 'Build my creator page',
        ],
        [
            'eyebrow' => 'Businesses',
            'title'   => 'A landing page, storefront &amp; CRM in one.',
            'desc'    => 'Branded short links, QR codes for packaging &amp; print, custom domains, forms and team workspaces.',
            'icon'    => 'fa-store',
            'color'   => '#1bd4d9',
            'cta'     => 'Start my business page',
        ],
        [
            'eyebrow' => 'Networking pros',
            'title'   => 'Your digital business card &mdash; and then some.',
            'desc'    => 'Tap-to-share NFC tags, dynamic QR codes, instant DMs and a live visitor map of who&rsquo;s engaging.',
            'icon'    => 'fa-id-badge',
            'color'   => '#ff8a3c',
            'cta'     => 'Make my smart card',
        ],
    ];
@endphp
<section id="audience" class="py-20 lg:py-28 relative overflow-hidden" aria-labelledby="audience-h">
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12 max-w-2xl mx-auto">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c3)">Built for you</div>
            <h2 id="audience-h" class="reveal rd-1 text-3xl sm:text-4xl lg:text-5xl font-bold tracking-tight mb-4">
                Built for <span class="grad-text">creators, brands &amp; networking pros.</span>
            </h2>
            <p class="reveal rd-2 text-gray-400">Pick the one that fits you &mdash; the same all-in-one toolkit powers all three.</p>
        </div>

        <div class="grid md:grid-cols-3 gap-5">
            @foreach($__audiences as $i => $a)
                <article class="audience-card reveal rd-{{ $i + 1 }} glass rounded-3xl p-7 tilt relative overflow-hidden flex flex-col">
                    <div class="aud-blob absolute -top-16 -right-16 w-48 h-48 rounded-full opacity-25" style="background:{{ $a['color'] }};animation-delay:{{ $i * 1.2 }}s;"></div>
                    <div class="aud-icon relative w-14 h-14 rounded-2xl flex items-center justify-center mb-5" style="background: {{ $a['color'] }}; box-shadow: 0 12px 30px -10px {{ $a['color'] }};animation-delay:{{ $i * 0.4 }}s;">
                        <i class="fas {{ $a['icon'] }} text-xl text-white" style="animation-delay:{{ $i * 0.5 }}s;"></i>
                    </div>
                    <div class="relative text-[11px] font-bold uppercase tracking-wider mb-2" style="color: {{ $a['color'] }};">{{ $a['eyebrow'] }}</div>
                    <h3 class="relative text-xl font-bold mb-3 leading-snug">{!! $a['title'] !!}</h3>
                    <p class="relative text-sm text-gray-400 leading-relaxed mb-6 flex-1">{!! $a['desc'] !!}</p>
                    <button type="button" onclick="window.trackMarketingEvent && window.trackMarketingEvent('landing_home_cta','audience'); window.dispatchEvent(new CustomEvent('open-auth',{detail:{tab:'register'}}))" class="relative btn-bounce inline-flex items-center justify-center gap-2 px-5 py-2.5 grad-bar text-white rounded-full text-sm font-bold self-start">
                        {{ $a['cta'] }} <i class="aud-arrow fas fa-arrow-right text-[10px]" style="animation-delay:{{ $i * 0.3 }}s;"></i>
                    </button>
                </article>
            @endforeach
        </div>
    </div>
</section>

{{-- ============================ HOW IT WORKS (upgraded) ============================ --}}
<style>
    .hiw-track { position: relative; }
    @media (min-width: 1024px) {
        .hiw-track::before {
            content: ""; position: absolute; left: 8%; right: 8%; top: 56px; height: 2px;
            background: rgba(124,58,237,.45);
            opacity: .55; pointer-events: none;
        }
    }
    .hiw-step { position: relative; transition: transform .35s ease, box-shadow .35s ease; }
    .hiw-step:hover { transform: translateY(-6px); box-shadow: 0 30px 60px -30px rgba(124,58,237,.55); }
    .hiw-icon-wrap { position: relative; width: 64px; height: 64px; border-radius: 22px; display:flex; align-items:center; justify-content:center; margin: 0 auto 1rem; box-shadow: 0 14px 30px -12px var(--hiw-color, #7c3aed); }
    .hiw-icon-wrap::after {
        content: ""; position: absolute; inset: -6px; border-radius: 26px;
        border: 2px solid color-mix(in srgb, var(--hiw-color, #7c3aed) 50%, transparent);
        opacity: .35; animation: hiwPulse 2.4s ease-in-out infinite;
    }
    .hiw-step:hover .hiw-icon-wrap::after { opacity: .8; }
    @keyframes hiwPulse { 0%,100% { transform: scale(1); opacity: .25; } 50% { transform: scale(1.08); opacity: .65; } }
    .hiw-num { position: absolute; top: 14px; right: 18px; font-size: 3.25rem; font-weight: 800; line-height: 1;
        color: var(--hiw-color, #7c3aed); opacity: .14;
    }
    .hiw-time {
        display:inline-flex; align-items:center; gap:6px;
        padding: 4px 10px; border-radius: 9999px; font-size: 11px; font-weight: 700;
        background: rgba(34,197,94,.12); color: #4ade80; border: 1px solid rgba(34,197,94,.25);
        margin-bottom: 10px; letter-spacing: .04em;
    }
    .hiw-time i { font-size: 9px; }
    .hiw-cta-wrap {
        position: relative; padding: 1.75rem; border-radius: 1.75rem; overflow: hidden;
        background: rgba(124,58,237,.12);
        border: 1px solid rgba(255,255,255,.08);
    }
    .hiw-cta-wrap::before {
        content:""; position:absolute; inset:-1px; border-radius:inherit; pointer-events:none;
        background: #7c3aed;
        opacity:.18; filter: blur(20px);
    }
</style>
<section id="how-it-works" class="py-20 lg:py-28 relative overflow-hidden">
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-14 max-w-3xl mx-auto">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c2)">How it works</div>
            <h2 class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mb-4">
                Live in <span class="grad-text">under 2 minutes.</span>
            </h2>
            <p class="reveal rd-2 text-lg text-gray-400">Four tiny steps from "I have an idea" to "share my link". No card, no setup call, no fuss.</p>
        </div>

        <div class="hiw-track grid sm:grid-cols-2 lg:grid-cols-4 gap-5 max-w-6xl mx-auto">
            @foreach([
                ['01','0:15','Sign up free','Email or one-tap Google. Pick your handle and you\'re in.','fa-user-plus','#1bd4d9'],
                ['02','0:45','Build your page','Drag-and-drop blocks for socials, music, shop, video, forms.','fa-grip-vertical','#7c3aed'],
                ['03','1:30','Share it everywhere','One link, branded short links and a dynamic QR for offline.','fa-share-nodes','#e94e8c'],
                ['04','2:00','Watch it grow','Live analytics + an AI Coach that turns numbers into actions.','fa-chart-line','#ff8a3c'],
            ] as $i => $s)
                <div class="reveal rd-{{ ($i % 4)+1 }} hiw-step glass rounded-3xl p-6 text-center" style="--hiw-color: {{ $s[5] }}">
                    <span class="hiw-num">{{ $s[0] }}</span>
                    <div class="hiw-icon-wrap" style="background: {{ $s[5] }};"><i class="fas {{ $s[4] }} text-xl text-white"></i></div>
                    <span class="hiw-time"><i class="fas fa-stopwatch"></i>{{ $s[1] }}</span>
                    <h3 class="text-lg font-bold mb-1.5">{!! $s[2] !!}</h3>
                    <p class="text-sm text-gray-400 leading-relaxed">{!! $s[3] !!}</p>
                </div>
            @endforeach
        </div>

        <div class="reveal rd-4 mt-12 max-w-3xl mx-auto">
            <div class="hiw-cta-wrap relative flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="relative text-center sm:text-left">
                    <div class="text-[11px] font-bold uppercase tracking-[.2em] mb-1" style="color:var(--c1)">Ready when you are</div>
                    <div class="text-lg sm:text-xl font-bold text-white">Start free — no card needed.</div>
                    <div class="text-xs text-gray-400 mt-0.5">Free Forever plan · Upgrade only when you outgrow it.</div>
                </div>
                <div class="relative flex flex-wrap items-center gap-3 shrink-0">
                    <button type="button" onclick="window.trackMarketingEvent && window.trackMarketingEvent('landing_home_cta','how_it_works'); window.dispatchEvent(new CustomEvent('open-auth',{detail:{tab:'register'}}))" class="btn-bounce btn-glow inline-flex items-center gap-2 px-7 py-3.5 grad-bar text-white rounded-full text-sm font-bold">
                        Start free — no card <i class="fas fa-arrow-right text-xs"></i>
                    </button>
                    <a href="{{ route('site.how-it-works') }}" class="inline-flex items-center gap-2 px-5 py-3 rounded-full glass text-white hover:bg-white/10 text-xs font-semibold transition-colors">
                        Walk me through it <i class="fas fa-route text-[10px]"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ============================ PRICING TEASER ============================ --}}
@if(!empty($plans))
@php
    $__teaserFree    = collect($plans)->first(fn($p) => !empty($p['is_free']));
    $__teaserPaid    = collect($plans)->reject(fn($p) => !empty($p['is_free']))
        ->sortBy(fn($p) => (int) ($p['monthly']['amount_minor'] ?? PHP_INT_MAX))->first();
@endphp
<section id="pricing-teaser" class="py-14 lg:py-20 relative overflow-hidden" aria-label="Pricing at a glance">
    <div class="relative max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-10 max-w-2xl mx-auto">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c1)">Pricing at a glance</div>
            <h2 class="reveal rd-1 text-3xl sm:text-4xl lg:text-5xl font-bold tracking-tight mb-3">Free forever. <span class="grad-text">Upgrade when you grow.</span></h2>
            <p class="reveal rd-2 text-gray-400">Two plans, zero surprises. See the full breakdown below.</p>
        </div>
        <div class="grid sm:grid-cols-2 gap-5 max-w-3xl mx-auto">
            @if($__teaserFree)
                <a href="#pricing" class="reveal rd-1 group glass rounded-2xl p-6 lift block border border-white/10 hover:border-white/20 transition">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-2">{{ $__teaserFree['name'] ?? 'Free' }}</div>
                    <div class="flex items-baseline gap-2 mb-3">
                        <span class="text-3xl font-extrabold grad-text">FREE</span>
                        <span class="text-xs text-gray-500">forever</span>
                    </div>
                    <ul class="space-y-1.5 text-sm text-gray-300 mb-4">
                        <li><i class="fas fa-check text-[10px] mr-2" style="color:var(--c1)"></i>Unlimited links &amp; biolink page</li>
                        <li><i class="fas fa-check text-[10px] mr-2" style="color:var(--c1)"></i>Built-in DMs &amp; AI Coach</li>
                        <li><i class="fas fa-check text-[10px] mr-2" style="color:var(--c1)"></i>Native mobile app</li>
                    </ul>
                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-violet-300 group-hover:text-violet-200">See full plan <i class="fas fa-arrow-right text-[10px]"></i></span>
                </a>
            @endif
            @if($__teaserPaid)
                @php
                    $__paidAmount = $__teaserPaid['monthly']['amount_display'] ?? null;
                @endphp
                <a href="#pricing" class="reveal rd-2 group rounded-2xl p-6 lift block relative overflow-hidden grad-border">
                    <div class="absolute inset-0 -z-10 opacity-20" style="background:var(--c2);"></div>
                    <div class="text-[11px] font-bold uppercase tracking-wider mb-2" style="color:var(--c2)">{{ $__teaserPaid['name'] ?? 'Pro' }} · most popular</div>
                    <div class="flex items-baseline gap-2 mb-3">
                        <span class="text-3xl font-extrabold text-white">{{ $__paidAmount ?: 'Pro' }}</span>
                        <span class="text-xs text-gray-400">/ mo</span>
                    </div>
                    <ul class="space-y-1.5 text-sm text-gray-300 mb-4">
                        <li><i class="fas fa-check text-[10px] mr-2" style="color:var(--c2)"></i>Custom domains &amp; A/B tests</li>
                        <li><i class="fas fa-check text-[10px] mr-2" style="color:var(--c2)"></i>Team seats &amp; roles</li>
                        <li><i class="fas fa-check text-[10px] mr-2" style="color:var(--c2)"></i>Priority support &amp; advanced AI</li>
                    </ul>
                    <span class="inline-flex items-center gap-1.5 text-xs font-bold text-white">Compare all plans <i class="fas fa-arrow-right text-[10px]"></i></span>
                </a>
            @endif
        </div>
    </div>
</section>
@endif

{{-- ============================ 1 · BUILD ============================ --}}
<section id="features" class="py-24 lg:py-32 relative overflow-hidden">
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16 max-w-3xl mx-auto">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c1)">Build</div>
            <h2 class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mb-5">
                A whole website,<br><span class="grad-text">drag-and-drop simple.</span>
            </h2>
            <p class="reveal rd-2 text-lg text-gray-400">Stack blocks for text, images, video, audio, files, embeds and forms. Arrange in multi-column layouts. Pick a theme. Go live.</p>
        </div>

        <div class="grid lg:grid-cols-12 gap-6">
            <div class="reveal rd-1 lg:col-span-7 glass rounded-3xl p-7 tilt">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-wider mb-1" style="color:var(--c2)">Drag-and-drop biolink editor</div>
                        <h3 class="text-xl font-bold">Reorder blocks. Build columns. Ship.</h3>
                    </div>
                    <span class="hidden sm:inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold text-white" style="background:rgba(124,58,237,.25);color:#c4b5fd"><i class="fas fa-grip-vertical"></i> Drag</span>
                </div>
                <div class="grid grid-cols-12 gap-3">
                    <div class="col-span-12 sm:col-span-7 bg-[#0a0a14] rounded-2xl p-3 border border-white/5">
                        <div class="build-list space-y-2">
                            {{-- 1. Hero image --}}
                            <div class="build-row lift" data-bl-style="image">
                                <i class="fas fa-grip-vertical bl-grip"></i>
                                <div class="bl-ic" style="background:rgba(27,212,217,.2)"><i class="fas fa-image" style="color:var(--c1)"></i></div>
                                <div class="flex-1 min-w-0"><div class="bl-title">Hero image</div><div class="bl-sub">1200×630 · WEBP</div></div>
                                <div class="bl-thumb" style="background:linear-gradient(135deg,#1bd4d9,#7c3aed)"></div>
                            </div>
                            {{-- 2. Free Templates link --}}
                            <div class="build-row lift" data-bl-style="link">
                                <i class="fas fa-grip-vertical bl-grip"></i>
                                <div class="bl-ic" style="background:rgba(124,58,237,.25)"><i class="fas fa-link" style="color:var(--c2)"></i></div>
                                <div class="flex-1 min-w-0"><div class="bl-title">Free Templates</div><div class="bl-sub font-mono">jane.co/templates</div></div>
                                <span class="bl-chip">Link</span>
                            </div>
                            {{-- 3. Shop Merch (selected/live) --}}
                            <div class="build-row is-selected lift" data-bl-style="shop">
                                <i class="fas fa-grip-vertical bl-grip" style="color:var(--c3)"></i>
                                <div class="bl-ic" style="background:rgba(255,138,60,.25)"><i class="fas fa-store" style="color:var(--c4)"></i></div>
                                <div class="flex-1 min-w-0"><div class="bl-title">Shop Merch</div><div class="bl-sub">22 products · From $ 19</div></div>
                                <span class="bl-live"><span class="dot"></span>Live</span>
                            </div>
                            {{-- 4. YouTube embed --}}
                            <div class="build-row lift" data-bl-style="video">
                                <i class="fas fa-grip-vertical bl-grip"></i>
                                <div class="bl-ic" style="background:rgba(255,0,51,.2)"><i class="fab fa-youtube" style="color:#ff0033"></i></div>
                                <div class="flex-1 min-w-0"><div class="bl-title">Latest video</div><div class="bl-sub">Embed · 4:20</div></div>
                                <div class="bl-thumb-dark"><i class="fas fa-play"></i></div>
                            </div>
                            {{-- 5. Spotify audio --}}
                            <div class="build-row lift" data-bl-style="audio">
                                <i class="fas fa-grip-vertical bl-grip"></i>
                                <div class="bl-ic" style="background:rgba(30,215,96,.2)"><i class="fab fa-spotify" style="color:#1ed760"></i></div>
                                <div class="flex-1 min-w-0"><div class="bl-title">Latest track</div><div class="bl-sub">Saltwater · 3:42</div></div>
                                <div class="bl-eq" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
                            </div>
                            {{-- 6. Gallery --}}
                            <div class="build-row lift" data-bl-style="gallery">
                                <i class="fas fa-grip-vertical bl-grip"></i>
                                <div class="bl-ic" style="background:rgba(233,78,140,.2)"><i class="fas fa-images" style="color:var(--c3)"></i></div>
                                <div class="flex-1 min-w-0"><div class="bl-title">Photo gallery</div><div class="bl-sub">12 photos · Masonry</div></div>
                                <div class="bl-mini-grid"><i></i><i></i><i></i><i></i></div>
                            </div>
                            {{-- 7. Newsletter form --}}
                            <div class="build-row lift" data-bl-style="form">
                                <i class="fas fa-grip-vertical bl-grip"></i>
                                <div class="bl-ic" style="background:rgba(255,200,69,.25)"><i class="fas fa-wpforms" style="color:var(--c5)"></i></div>
                                <div class="flex-1 min-w-0"><div class="bl-title">Newsletter form</div><div class="bl-sub">Email + name · 1.2k subs</div></div>
                                <span class="bl-chip">Form</span>
                            </div>
                            {{-- 8. Calendar / booking --}}
                            <div class="build-row lift" data-bl-style="calendar">
                                <i class="fas fa-grip-vertical bl-grip"></i>
                                <div class="bl-ic" style="background:rgba(14,165,233,.2)"><i class="fas fa-calendar-check" style="color:#0ea5e9"></i></div>
                                <div class="flex-1 min-w-0"><div class="bl-title">Book a call</div><div class="bl-sub">30 min · Calendly</div></div>
                                <div class="bl-date"><span class="mo">JUN</span><span class="da">14</span></div>
                            </div>
                            {{-- 9. Tip jar / payments --}}
                            <div class="build-row lift" data-bl-style="tip">
                                <i class="fas fa-grip-vertical bl-grip"></i>
                                <div class="bl-ic" style="background:rgba(236,72,153,.2)"><i class="fas fa-hand-holding-heart" style="color:#ec4899"></i></div>
                                <div class="flex-1 min-w-0"><div class="bl-title">Tip jar</div><div class="bl-sub">$ 3 / $ 5 / Custom</div></div>
                                <span class="bl-chip">Pay</span>
                            </div>
                            {{-- 10. Socials row --}}
                            <div class="build-row lift" data-bl-style="socials">
                                <i class="fas fa-grip-vertical bl-grip"></i>
                                <div class="bl-ic" style="background:rgba(124,58,237,.2)"><i class="fas fa-share-nodes" style="color:#c4b5fd"></i></div>
                                <div class="flex-1 min-w-0"><div class="bl-title">Socials row</div><div class="bl-sub">IG · TikTok · YT · X</div></div>
                                <div class="bl-socials">
                                    <i class="fab fa-instagram" style="color:#e94e8c"></i>
                                    <i class="fab fa-tiktok"></i>
                                    <i class="fab fa-youtube" style="color:#ff0033"></i>
                                </div>
                            </div>
                            {{-- 11. Map / location --}}
                            <div class="build-row lift" data-bl-style="map">
                                <i class="fas fa-grip-vertical bl-grip"></i>
                                <div class="bl-ic" style="background:rgba(34,211,238,.2)"><i class="fas fa-location-dot" style="color:#22d3ee"></i></div>
                                <div class="flex-1 min-w-0"><div class="bl-title">Studio location</div><div class="bl-sub">Berlin · 12 Mitte St.</div></div>
                                <div class="bl-map" aria-hidden="true"></div>
                            </div>
                            {{-- 12. Countdown --}}
                            <div class="build-row lift" data-bl-style="countdown">
                                <i class="fas fa-grip-vertical bl-grip"></i>
                                <div class="bl-ic" style="background:rgba(251,191,36,.2)"><i class="fas fa-hourglass-half" style="color:#fbbf24"></i></div>
                                <div class="flex-1 min-w-0"><div class="bl-title">Drop countdown</div><div class="bl-sub">Spring capsule · 3d 14h</div></div>
                                <div class="bl-count"><span>03</span>:<span>14</span>:<span>22</span></div>
                            </div>
                            {{-- 13. FAQ --}}
                            <div class="build-row lift" data-bl-style="faq">
                                <i class="fas fa-grip-vertical bl-grip"></i>
                                <div class="bl-ic" style="background:rgba(139,92,246,.2)"><i class="fas fa-circle-question" style="color:#a78bfa"></i></div>
                                <div class="flex-1 min-w-0"><div class="bl-title">FAQ</div><div class="bl-sub">6 questions · Accordion</div></div>
                                <i class="fas fa-chevron-down bl-chev"></i>
                            </div>
                            {{-- 14. File download --}}
                            <div class="build-row lift" data-bl-style="file">
                                <i class="fas fa-grip-vertical bl-grip"></i>
                                <div class="bl-ic" style="background:rgba(59,130,246,.2)"><i class="fas fa-file-lines" style="color:#60a5fa"></i></div>
                                <div class="flex-1 min-w-0"><div class="bl-title">Media kit · PDF</div><div class="bl-sub">2.4 MB · 12 pages</div></div>
                                <span class="bl-chip">PDF</span>
                            </div>
                            {{-- 15. Testimonials --}}
                            <div class="build-row lift" data-bl-style="quote">
                                <i class="fas fa-grip-vertical bl-grip"></i>
                                <div class="bl-ic" style="background:rgba(16,185,129,.2)"><i class="fas fa-quote-right" style="color:#10b981"></i></div>
                                <div class="flex-1 min-w-0"><div class="bl-title">Testimonials</div><div class="bl-sub">4.9★ · 140 reviews</div></div>
                                <div class="bl-stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
                            </div>
                            {{-- 16. Two-column row --}}
                            <div class="build-row is-columns lift" data-bl-style="cols">
                                <i class="fas fa-grip-vertical bl-grip"></i>
                                <div class="bl-col"><div class="bl-ic sm" style="background:rgba(27,212,217,.2)"><i class="fas fa-download" style="color:var(--c1)"></i></div><div class="bl-col-t">Download</div></div>
                                <div class="bl-col"><div class="bl-ic sm" style="background:rgba(255,200,69,.25)"><i class="fas fa-envelope" style="color:var(--c5)"></i></div><div class="bl-col-t">Contact</div></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-span-12 sm:col-span-5 bg-[#0a0a14] rounded-2xl p-3 border border-white/5 flex items-center justify-center">
                        <div class="bb-phone">
                            <div class="bb-screen">
                                <div class="bb-notch" aria-hidden="true"></div>
                                <div class="bb-scroll">
                                    {{-- Profile header --}}
                                    <div class="bb-prof">
                                        <div class="bb-av">JD</div>
                                        <div class="bb-h">@jane</div>
                                        <div class="bb-t">Creator · Berlin</div>
                                        <div class="bb-soc"><i class="fab fa-instagram"></i><i class="fab fa-tiktok"></i><i class="fab fa-youtube"></i><i class="fab fa-x-twitter"></i></div>
                                    </div>
                                    {{-- Hero image --}}
                                    <div class="bb-hero" aria-hidden="true"></div>
                                    {{-- Link --}}
                                    <div class="bb-btn">Templates</div>
                                    {{-- Shop (selected highlight) --}}
                                    <div class="bb-btn is-accent"><i class="fas fa-store"></i>Shop Merch<span class="bb-live"><span class="dot"></span>Live</span></div>
                                    {{-- Video embed --}}
                                    <div class="bb-video"><i class="fas fa-play"></i><span>Latest video · 4:20</span></div>
                                    {{-- Audio --}}
                                    <div class="bb-audio">
                                        <div class="ico"><i class="fab fa-spotify"></i></div>
                                        <div class="meta"><div class="tt">Saltwater</div><div class="ss">3:42</div></div>
                                        <div class="eq"><i></i><i></i><i></i><i></i></div>
                                    </div>
                                    {{-- Gallery 3-up --}}
                                    <div class="bb-gal"><i></i><i></i><i></i></div>
                                    {{-- Newsletter form --}}
                                    <div class="bb-form">
                                        <div class="fi">Email</div>
                                        <div class="fb">Subscribe</div>
                                    </div>
                                    {{-- Calendar --}}
                                    <div class="bb-cal">
                                        <div class="dt"><span class="mo">JUN</span><span class="da">14</span></div>
                                        <div class="mt"><div class="tt">Book a 30-min call</div><div class="ss">Calendly · Free intro</div></div>
                                    </div>
                                    {{-- Tip jar --}}
                                    <div class="bb-tip"><span>Tip jar</span><span class="amts">$3<i>·</i>$5<i>·</i>$10</span></div>
                                    {{-- Map --}}
                                    <div class="bb-map"><i class="fas fa-location-dot"></i>Berlin · 12 Mitte St.</div>
                                    {{-- Countdown --}}
                                    <div class="bb-count"><span>03</span><i>:</i><span>14</span><i>:</i><span>22</span></div>
                                    {{-- Two-column --}}
                                    <div class="bb-2col"><div><i class="fas fa-download"></i>Press kit</div><div><i class="fas fa-envelope"></i>Contact</div></div>
                                    {{-- FAQ --}}
                                    <div class="bb-faq"><span>FAQ</span><i class="fas fa-chevron-down"></i></div>
                                    {{-- Testimonials --}}
                                    <div class="bb-quote">"Best tool I've used all year" <div><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div></div>
                                    {{-- Socials --}}
                                    <div class="bb-socials"><i class="fab fa-instagram"></i><i class="fab fa-tiktok"></i><i class="fab fa-youtube"></i><i class="fab fa-x-twitter"></i><i class="fab fa-spotify"></i></div>
                                    <div class="bb-foot">1inme.co/@jane</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="reveal rd-2 lg:col-span-5 grid grid-cols-1 gap-6 auto-rows-fr">
                {{-- Themes & design controls --}}
                <div class="glass rounded-3xl p-6 lift relative overflow-hidden flex flex-col">
                    <div class="absolute -top-8 -right-8 w-32 h-32 rounded-full opacity-25" style="background:var(--c1)"></div>
                    <div class="relative flex flex-col flex-1">
                        <div class="w-12 h-12 rounded-2xl flex items-center justify-center mb-4" style="background:rgba(27,212,217,.2)"><i class="fas fa-palette text-xl" style="color:var(--c1)"></i></div>
                        <h3 class="text-lg font-bold mb-2">Themes &amp; design controls</h3>
                        <p class="text-sm text-gray-400 mb-5">Pick from beautiful presets, then fine-tune fonts, colours and layouts to match your vibe.</p>

                        <div class="mt-auto space-y-3">
                            {{-- Theme preset swatches --}}
                            <div class="flex items-center gap-2">
                                <span class="th-swatch" style="background:linear-gradient(135deg,#1bd4d9,#7c3aed)" title="Aurora"></span>
                                <span class="th-swatch" style="background:linear-gradient(135deg,#e94e8c,#ff8a3c)" title="Sunset"></span>
                                <span class="th-swatch" style="background:linear-gradient(135deg,#0e0e10,#3f3f46);border:1px solid rgba(255,255,255,.15)" title="Noir"></span>
                                <span class="th-swatch" style="background:linear-gradient(135deg,#fef3c7,#f59e0b)" title="Sand"></span>
                                <span class="th-swatch" style="background:linear-gradient(135deg,#a7f3d0,#10b981)" title="Mint"></span>
                                <span class="th-swatch" style="background:linear-gradient(135deg,#dbeafe,#3b82f6)" title="Sky"></span>
                                <span class="text-[11px] font-bold text-gray-500 ml-1">+24</span>
                            </div>
                            {{-- Font + radius row --}}
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="th-pill" style="font-family:'Inter',sans-serif">Aa Inter</span>
                                <span class="th-pill" style="font-family:Georgia,serif">Aa Serif</span>
                                <span class="th-pill" style="font-family:'Courier New',monospace">Aa Mono</span>
                                <span class="th-pill th-pill--accent"><i class="fas fa-circle-half-stroke text-[9px]"></i> Round</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Mobile-first by default --}}
                <div class="glass rounded-3xl p-6 lift relative overflow-hidden flex flex-col">
                    <div class="absolute -top-8 -right-8 w-32 h-32 rounded-full opacity-25" style="background:var(--c3)"></div>
                    <div class="relative flex flex-col flex-1">
                        <div class="w-12 h-12 rounded-2xl flex items-center justify-center mb-4" style="background:rgba(233,78,140,.2)"><i class="fas fa-mobile-screen text-xl" style="color:var(--c3)"></i></div>
                        <h3 class="text-lg font-bold mb-2">Mobile-first by default</h3>
                        <p class="text-sm text-gray-400 mb-5">Every theme looks razor-sharp on small screens — that’s where your audience actually is.</p>

                        <div class="mt-auto mf-mock" aria-hidden="true">
                            {{-- Tiny phone mockup --}}
                            <div class="mf-phone">
                                <div class="mf-notch"></div>
                                <div class="mf-avatar"></div>
                                <div class="mf-name"></div>
                                <div class="mf-handle"></div>
                                <div class="mf-btn"></div>
                                <div class="mf-btn" style="width:78%"></div>
                                <div class="mf-btn" style="width:65%"></div>
                            </div>
                            {{-- Stats --}}
                            <div class="mf-stats">
                                <div><strong>92%</strong><span>mobile traffic</span></div>
                                <div><strong>&lt;1.2s</strong><span>load time</span></div>
                                <div><strong>100</strong><span>Lighthouse</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ============================ 2 · SHARE ============================ --}}
<section id="share" class="py-24 lg:py-32 relative overflow-hidden">
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16 max-w-3xl mx-auto">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c3)">Share</div>
            <h2 class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mb-5">
                Share your 1INME<br><span class="grad-text">anywhere you like.</span>
            </h2>
            <p class="reveal rd-2 text-lg text-gray-400">Branded short links and dynamic QR codes you can repoint at any time. Add your link to bios, posters, business cards, packaging — anywhere.</p>
        </div>

        <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
            {{-- 1 · Branded short links --}}
            <div class="reveal rd-1 glass rounded-3xl p-7 tilt share-card">
                <div class="absolute -top-10 -right-10 w-40 h-40 rounded-full opacity-30" style="background:var(--c1)"></div>
                <div class="relative">
                    <div class="w-12 h-12 rounded-2xl flex items-center justify-center mb-4" style="background:rgba(27,212,217,.2)"><i class="fas fa-link text-xl" style="color:var(--c1)"></i></div>
                    <h3 class="text-xl font-bold mb-2">Branded short links</h3>
                    <p class="text-sm text-gray-400 mb-5">Custom slugs, UTM-ready, click tracking. Looks like you, not a random shortener.</p>
                    <div class="sl-pill">
                        <i class="fas fa-link text-[10px]" style="color:var(--c1)"></i>
                        <span class="host">1inme.co/</span><span class="slug">spring-drop</span>
                    </div>
                    <div class="sl-counter">
                        <span><span class="num">1,284</span> clicks today</span>
                        <span class="sl-spark" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i></span>
                    </div>
                </div>
            </div>

            {{-- 2 · Custom domain (NEW) --}}
            <div class="reveal rd-2 glass rounded-3xl p-7 tilt share-card">
                <div class="absolute -top-10 -right-10 w-40 h-40 rounded-full opacity-30" style="background:var(--c2)"></div>
                <div class="relative">
                    <div class="w-12 h-12 rounded-2xl flex items-center justify-center mb-4" style="background:rgba(124,58,237,.22)"><i class="fas fa-globe text-xl" style="color:var(--c2)"></i></div>
                    <h3 class="text-xl font-bold mb-2">Custom domain</h3>
                    <p class="text-sm text-gray-400 mb-5">Bring your own domain like <span class="text-white">links.yourbrand.com</span> — auto-SSL, zero DNS headaches.</p>
                    <div class="cd-stage">
                        <div class="cd-bar">
                            <span class="lock"><i class="fas fa-lock"></i></span>
                            <span class="sub">https://</span><span class="brand">links.</span><span class="brand">yourbrand</span><span class="tld">.com</span><span class="path">/launch</span>
                        </div>
                        <div class="cd-rows" aria-hidden="true">
                            <div class="cd-rec">
                                <span class="ty">CNAME</span>
                                <span class="val">links → cname.1inme.co</span>
                                <span class="ok"><i class="fas fa-circle-check"></i></span>
                            </div>
                            <div class="cd-rec">
                                <span class="ty">TXT</span>
                                <span class="val">_1inme-verify=ok-91a2</span>
                                <span class="ok"><i class="fas fa-circle-check"></i></span>
                            </div>
                            <div class="cd-rec">
                                <span class="ty">SSL</span>
                                <span class="val">Let's Encrypt · auto-renew</span>
                                <span class="ok"><i class="fas fa-circle-check"></i></span>
                            </div>
                        </div>
                        <span class="cd-status"><span class="pulse"></span>Live · secured</span>
                    </div>
                </div>
            </div>

            {{-- 3 · Dynamic QR codes --}}
            <div class="reveal rd-3 glass rounded-3xl p-7 tilt share-card">
                <div class="absolute -top-10 -right-10 w-40 h-40 rounded-full opacity-30" style="background:var(--c3)"></div>
                <div class="relative">
                    <div class="w-12 h-12 rounded-2xl flex items-center justify-center mb-4" style="background:rgba(233,78,140,.2)"><i class="fas fa-qrcode text-xl" style="color:var(--c3)"></i></div>
                    <h3 class="text-xl font-bold mb-2">Dynamic QR codes</h3>
                    <p class="text-sm text-gray-400 mb-5">Print once, redirect forever. Change the destination without reprinting.</p>
                    <div class="qr-stage qr-stage--left" aria-hidden="true">
                        <span class="qr-corner tl"></span>
                        <span class="qr-corner tr"></span>
                        <span class="qr-corner bl"></span>
                        <span class="qr-corner br"></span>
                        <span class="qr-scans-pill">+128 scans · today</span>
                        @php
                            $qrSize = 29;
                            $qrGrid = array_fill(0, $qrSize, array_fill(0, $qrSize, 0));
                            $qrFinder = function (&$g, $ox, $oy) {
                                for ($i = 0; $i < 7; $i++) {
                                    for ($j = 0; $j < 7; $j++) {
                                        $on = ($i === 0 || $i === 6 || $j === 0 || $j === 6)
                                            || ($i >= 2 && $i <= 4 && $j >= 2 && $j <= 4);
                                        $g[$oy + $i][$ox + $j] = $on ? 1 : 0;
                                    }
                                }
                            };
                            $qrFinder($qrGrid, 0, 0);
                            $qrFinder($qrGrid, 22, 0);
                            $qrFinder($qrGrid, 0, 22);
                            for ($i = 0; $i < 5; $i++) {
                                for ($j = 0; $j < 5; $j++) {
                                    $on = ($i === 0 || $i === 4 || $j === 0 || $j === 4) || ($i === 2 && $j === 2);
                                    $qrGrid[20 + $i][20 + $j] = $on ? 1 : 0;
                                }
                            }
                            for ($i = 8; $i <= 20; $i++) {
                                $qrGrid[6][$i] = ($i % 2 === 0) ? 1 : 0;
                                $qrGrid[$i][6] = ($i % 2 === 0) ? 1 : 0;
                            }
                            $qrReserved = function ($x, $y) {
                                if ($x < 8 && $y < 8) return true;
                                if ($x >= 22 && $y < 8) return true;
                                if ($x < 8 && $y >= 22) return true;
                                if ($x >= 20 && $x < 25 && $y >= 20 && $y < 25) return true;
                                if ($x === 6 || $y === 6) return true;
                                return false;
                            };
                            mt_srand(20251128);
                            for ($y = 0; $y < $qrSize; $y++) {
                                for ($x = 0; $x < $qrSize; $x++) {
                                    if (!$qrReserved($x, $y)) {
                                        $qrGrid[$y][$x] = (mt_rand(0, 100) < 47) ? 1 : 0;
                                    }
                                }
                            }
                            for ($y = 12; $y <= 16; $y++) {
                                for ($x = 12; $x <= 16; $x++) {
                                    $qrGrid[$y][$x] = 0;
                                }
                            }
                        @endphp
                        <svg class="qr-svg" viewBox="0 0 29 29" preserveAspectRatio="xMidYMid meet" aria-hidden="true">
                            <defs>
                                <linearGradient id="qrLogoGrad" x1="0" y1="0" x2="1" y2="1">
                                    <stop offset="0" stop-color="#e94e8c"/>
                                    <stop offset="1" stop-color="#7c3aed"/>
                                </linearGradient>
                            </defs>
                            @for ($y = 0; $y < $qrSize; $y++)
                                @for ($x = 0; $x < $qrSize; $x++)
                                    @if ($qrGrid[$y][$x])
                                        <rect x="{{ $x }}" y="{{ $y }}" width="1.04" height="1.04" rx="0.18" ry="0.18" fill="#0e0e10"/>
                                    @endif
                                @endfor
                            @endfor
                            <rect x="11.4" y="11.4" width="6.2" height="6.2" rx="1.3" ry="1.3" fill="#fff"/>
                            <rect x="12.1" y="12.1" width="4.8" height="4.8" rx="1" ry="1" fill="url(#qrLogoGrad)"/>
                            <text x="14.5" y="15.95" text-anchor="middle" font-family="Inter,system-ui,-apple-system,sans-serif" font-weight="900" font-size="3.2" fill="#fff">1</text>
                        </svg>
                    </div>
                </div>
            </div>

            {{-- 4 · Channel-ready --}}
            <div class="reveal rd-4 glass rounded-3xl p-7 tilt share-card">
                <div class="absolute -top-10 -right-10 w-40 h-40 rounded-full opacity-30" style="background:var(--c4)"></div>
                <div class="relative">
                    <div class="w-12 h-12 rounded-2xl flex items-center justify-center mb-4" style="background:rgba(255,138,60,.2)"><i class="fas fa-share-nodes text-xl" style="color:var(--c4)"></i></div>
                    <h3 class="text-xl font-bold mb-2">Channel-ready</h3>
                    <p class="text-sm text-gray-400 mb-5">Pre-made share cards for every channel. Pixels, UTM and OG ready out of the box.</p>
                    <div class="ch-grid">
                        @foreach(['fa-instagram'=>'#e94e8c','fa-tiktok'=>'#1bd4d9','fa-youtube'=>'#e94e8c','fa-x-twitter'=>'#7c3aed','fa-linkedin'=>'#1bd4d9','fa-facebook'=>'#7c3aed'] as $ic => $col)
                            <span class="ch-icon" style="color:{{ $col }}"><i class="fab {{ $ic }}"></i></span>
                        @endforeach
                    </div>
                    <div class="ch-tags" aria-hidden="true">
                        <span>OG</span><span>Pixels</span><span>UTM</span><span>UTM-A/B</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ============================ 3 · GROW ============================ --}}
<section class="py-24 lg:py-32 relative overflow-hidden">
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16 max-w-3xl mx-auto">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c5)">Grow</div>
            <h2 class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mb-5">
                Live analytics with<br><span class="grad-text">a built-in coach.</span>
            </h2>
            <p class="reveal rd-2 text-lg text-gray-400">See visitors land on a world map, watch click trends per block, and let the Performance Coach suggest one-click fixes.</p>
        </div>

        <div class="grid lg:grid-cols-12 gap-6">
            {{-- Live geo card --}}
            <div class="reveal rd-1 lg:col-span-7 glass rounded-3xl p-7 tilt">
                <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-wider mb-1" style="color:var(--c1)">Live geo heatmap</div>
                        <h3 class="text-xl font-bold">247 visitors right now in 14 countries</h3>
                    </div>
                    <span class="flex items-center gap-1.5 text-xs font-bold px-3 py-1 rounded-full" style="background:rgba(27,212,217,.15);color:var(--c1)"><span class="w-1.5 h-1.5 rounded-full pulse-dot" style="background:var(--c1)"></span>LIVE</span>
                </div>

                <div class="geo-map">
                    <div class="grid"></div>
                    {{-- Continents (simplified silhouettes) --}}
                    <svg class="continents" viewBox="0 0 320 180" preserveAspectRatio="none" aria-hidden="true">
                        {{-- North America --}}
                        <path d="M22,42 L48,32 L72,38 L80,52 L72,70 L86,82 L78,98 L60,108 L42,102 L30,86 L22,68 Z"/>
                        {{-- South America --}}
                        <path d="M82,108 L96,108 L102,124 L96,148 L86,160 L78,148 L76,128 Z"/>
                        {{-- Europe --}}
                        <path d="M138,38 L160,34 L172,42 L168,56 L156,62 L142,58 L134,48 Z"/>
                        {{-- Africa --}}
                        <path d="M150,68 L172,66 L184,82 L186,108 L172,132 L158,134 L144,118 L142,92 Z"/>
                        {{-- Asia --}}
                        <path d="M178,30 L228,26 L264,38 L274,56 L256,72 L228,72 L196,68 L182,52 Z"/>
                        {{-- SE Asia --}}
                        <path d="M242,78 L260,74 L266,86 L256,98 L246,94 Z"/>
                        {{-- Australia --}}
                        <path d="M256,118 L284,116 L292,128 L282,140 L262,138 L256,128 Z"/>
                    </svg>

                    {{-- Animated streams between visitor pins --}}
                    <svg class="absolute inset-0 w-full h-full" viewBox="0 0 320 180" preserveAspectRatio="none" aria-hidden="true">
                        <path class="geo-stream" stroke="#1bd4d9" d="M58,68 Q120,40 156,52"/>
                        <path class="geo-stream" stroke="#e94e8c" d="M156,52 Q210,30 252,52" style="animation-delay:-.5s"/>
                        <path class="geo-stream" stroke="#ffc845" d="M168,98 Q220,108 274,124" style="animation-delay:-1s"/>
                        <path class="geo-stream" stroke="#7c3aed" d="M58,68 Q70,90 88,112" style="animation-delay:-.7s"/>
                    </svg>

                    {{-- Sweeping meridian line --}}
                    <span class="meridian" aria-hidden="true"></span>

                    {{-- Live ticker overlay --}}
                    <div class="geo-ticker" aria-hidden="true">
                        <span class="live">Live</span>
                        <div class="feed">
                            <div>👤 <em>Sara</em> · Tokyo · clicked <em>/spring-drop</em></div>
                            <div>👤 <em>Liam</em> · London · scanned <em>QR · merch</em></div>
                            <div>👤 <em>Amara</em> · Lagos · followed <em>@jamie</em></div>
                            <div>👤 <em>Diego</em> · Mexico City · viewed <em>biolink</em></div>
                        </div>
                    </div>

                    {{-- Pulsing visitor pins --}}
                    @foreach([
                        ['18%','38%','#1bd4d9'],
                        ['48%','29%','#e94e8c'],
                        ['76%','29%','#ffc845'],
                        ['28%','62%','#ff8a3c'],
                        ['83%','72%','#7c3aed'],
                        ['52%','58%','#1bd4d9'],
                        ['58%','78%','#e94e8c'],
                    ] as $i => $p)
                        <span class="geo-pin" style="left:{{ $p[0] }};top:{{ $p[1] }};--c:{{ $p[2] }}; animation-delay:{{ -$i*0.4 }}s">
                            <span class="ring r1" style="animation-delay:{{ -$i*0.4 }}s"></span>
                            <span class="ring r2"></span>
                            <span class="ring r3"></span>
                            <span class="core"></span>
                        </span>
                    @endforeach
                </div>

                {{-- Stat trio with animated bars --}}
                <div class="grid grid-cols-3 gap-4 mt-5">
                    <div class="geo-stat">
                        <div class="num">38.4k</div>
                        <div class="text-[10px] uppercase tracking-wider text-gray-500 mt-0.5">7-day visits</div>
                        <div class="bar" style="--to:78%"></div>
                    </div>
                    <div class="geo-stat">
                        <div class="num">9.1k</div>
                        <div class="text-[10px] uppercase tracking-wider text-gray-500 mt-0.5">QR scans</div>
                        <div class="bar" style="--to:60%"></div>
                    </div>
                    <div class="geo-stat">
                        <div class="num">2.4k</div>
                        <div class="text-[10px] uppercase tracking-wider text-gray-500 mt-0.5">New followers</div>
                        <div class="bar" style="--to:42%"></div>
                    </div>
                </div>

                {{-- Country flags marquee --}}
                <div class="geo-flags" aria-hidden="true">
                    <div class="marquee">
                        @foreach([
                            ['🇺🇸','US','58'],['🇬🇧','UK','41'],['🇯🇵','JP','37'],['🇩🇪','DE','29'],
                            ['🇫🇷','FR','24'],['🇧🇷','BR','22'],['🇮🇳','IN','19'],['🇨🇦','CA','14'],
                            ['🇲🇽','MX','11'],['🇦🇺','AU','9'],['🇳🇬','NG','7'],['🇰🇷','KR','6'],
                            ['🇪🇸','ES','5'],['🇮🇹','IT','4'],
                        ] as $f)
                            <span class="geo-flag"><span class="em">{{ $f[0] }}</span>{{ $f[1] }}<span class="n">{{ $f[2] }}</span></span>
                        @endforeach
                        @foreach([
                            ['🇺🇸','US','58'],['🇬🇧','UK','41'],['🇯🇵','JP','37'],['🇩🇪','DE','29'],
                            ['🇫🇷','FR','24'],['🇧🇷','BR','22'],['🇮🇳','IN','19'],['🇨🇦','CA','14'],
                            ['🇲🇽','MX','11'],['🇦🇺','AU','9'],['🇳🇬','NG','7'],['🇰🇷','KR','6'],
                            ['🇪🇸','ES','5'],['🇮🇹','IT','4'],
                        ] as $f)
                            <span class="geo-flag"><span class="em">{{ $f[0] }}</span>{{ $f[1] }}<span class="n">{{ $f[2] }}</span></span>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Coach card --}}
            <div class="reveal rd-2 lg:col-span-5 rounded-3xl p-7 tilt relative overflow-hidden text-white" style="background: #7c3aed;">
                <div class="absolute -top-12 -right-12 w-48 h-48 rounded-full bg-white/10"></div>
                <div class="absolute -bottom-16 -left-16 w-56 h-56 rounded-full bg-white/5"></div>
                <div class="relative">
                    <div class="text-xs font-bold uppercase tracking-wider mb-1 text-white/80">Performance Coach</div>
                    <h3 class="text-2xl font-bold mb-4">Health score <span class="font-extrabold">87</span> <span class="text-white/70 text-base font-normal">/ 100</span></h3>

                    <div class="coach-ring">
                        <span class="glow" aria-hidden="true"></span>
                        <svg viewBox="0 0 100 100">
                            <circle class="track" cx="50" cy="50" r="40" fill="none" stroke-width="9"/>
                            <circle class="fill"  cx="50" cy="50" r="40" fill="none" stroke-width="9"/>
                        </svg>
                        <div class="num">
                            <span class="big">87</span>
                            <span class="lbl">Health</span>
                        </div>
                    </div>

                    <div class="coach-analyzing" aria-hidden="true">
                        <i class="fas fa-wand-magic-sparkles"></i>
                        <span>Coach is analyzing</span>
                        <span class="dots"><span></span><span></span><span></span></span>
                    </div>

                    <div class="space-y-2.5">
                        <div class="coach-tip">
                            <span class="ic"><i class="fas fa-arrows-rotate"></i></span>
                            <div class="body">
                                <b>Swap your top block.</b> “Free Templates” CTR
                                <small>
                                    <span class="spark dn" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></span>
                                    <span>−12% · last 7d</span>
                                </small>
                            </div>
                            <a href="#" class="cta">Try fix</a>
                        </div>
                        <div class="coach-tip">
                            <span class="ic"><i class="fas fa-star"></i></span>
                            <div class="body">
                                <b>Add social proof.</b> Pages with reviews convert
                                <small>
                                    <span class="spark up" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></span>
                                    <span>1.7× higher</span>
                                </small>
                            </div>
                            <a href="#" class="cta">Add now</a>
                        </div>
                        <div class="coach-tip">
                            <span class="ic"><i class="fas fa-flask"></i></span>
                            <div class="body">
                                <b>A/B test the hero.</b> 2 variants, auto-pick winner
                                <small>
                                    <span class="spark up" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></span>
                                    <span>+8% projected lift</span>
                                </small>
                            </div>
                            <a href="#" class="cta">Run test</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ============================ EVERYTHING YOU GET (4 PILLARS) ============================ --}}
@php
    $__pillars = [
        [
            'eyebrow' => 'Bio-link & branding',
            'title'   => 'A whole site behind one link',
            'icon'    => 'fa-grip-vertical',
            'color'   => '#1bd4d9',
            'items'   => [
                ['fa-grip-vertical', 'Drag-and-drop builder'],
                ['fa-palette',       'Custom themes &amp; fonts'],
                ['fa-globe',         'Custom domains'],
                ['fa-photo-film',    'Video, music &amp; form embeds'],
                ['fa-comments',      'Live social-proof widgets'],
            ],
        ],
        [
            'eyebrow' => 'Links, QR & NFC',
            'title'   => 'Share anywhere — online &amp; off',
            'icon'    => 'fa-qrcode',
            'color'   => '#7c3aed',
            'items'   => [
                ['fa-link',     'Branded short links with click analytics'],
                ['fa-qrcode',   'Dynamic styled QR codes, editable destinations'],
                ['fa-wifi',     'NFC tag writing from the mobile app'],
                ['fa-bullseye', 'UTM builder &amp; campaign tracking'],
            ],
        ],
        [
            'eyebrow' => 'Monetization & engagement',
            'title'   => 'Get paid &amp; talk to fans',
            'icon'    => 'fa-store',
            'color'   => '#e94e8c',
            'items'   => [
                ['fa-store',    'Sell digital products'],
                ['fa-hand-holding-dollar', 'Tips &amp; donations'],
                ['fa-message',  'Built-in DMs from biolink visitors'],
                ['fa-stream',   'Creator feed &amp; followers'],
                ['fa-clipboard-list', 'Forms &amp; RSVPs'],
            ],
        ],
        [
            'eyebrow' => 'Growth & intelligence',
            'title'   => 'See what works, fix what doesn&rsquo;t',
            'icon'    => 'fa-bolt',
            'color'   => '#ff8a3c',
            'items'   => [
                ['fa-bolt',     'AI Performance Coach'],
                ['fa-fire',     'Click heatmaps'],
                ['fa-map-location-dot', 'Live visitor map'],
                ['fa-chart-line', 'UTM builder &amp; deep analytics'],
                ['fa-coins',    'Coin / Wallet add-ons'],
            ],
        ],
        [
            'eyebrow' => 'Audience & followers',
            'title'   => 'Build an audience you actually own',
            'icon'    => 'fa-users',
            'color'   => '#f472b6',
            'href'    => route('site.audience'),
            'items'   => [
                ['fa-user-plus',          'Lightweight viewer accounts'],
                ['fa-bolt',               'Live follower counts everywhere'],
                ['fa-compass',            'Public creators directory'],
                ['fa-envelope-open-text', 'Daily digest emails'],
            ],
        ],
        [
            'eyebrow' => 'Social integrations',
            'title'   => 'Every network · one-click connect',
            'icon'    => 'fa-plug',
            'color'   => '#a78bfa',
            'href'    => route('site.integrations'),
            'items'   => [
                ['fa-plug',           'Instagram, TikTok, Facebook, X, LinkedIn, Pinterest'],
                ['fa-arrows-rotate',  'Auto-retry when tokens expire'],
                ['fa-circle-check',   'Live "healthy / needs reconnect" status'],
                ['fa-bell',           'Notifications when something breaks'],
            ],
        ],
        [
            'eyebrow' => 'Scheduling',
            'title'   => 'Launch on the date and time you want',
            'icon'    => 'fa-clock',
            'color'   => '#34d399',
            'href'    => route('site.features') . '#cat-scheduling',
            'items'   => [
                ['fa-clock',         'Schedule blocks to publish &amp; expire'],
                ['fa-calendar-week', 'Page-level publish scheduling'],
                ['fa-globe',         'Visitor-timezone-aware drops'],
                ['fa-paper-plane',   'Test send for digest emails'],
            ],
        ],
        [
            'eyebrow' => 'Events & RSVPs',
            'title'   => 'Run launches, drops &amp; meetups in-line',
            'icon'    => 'fa-calendar-day',
            'color'   => '#fbbf24',
            'href'    => route('site.features') . '#cat-events',
            'items'   => [
                ['fa-calendar-day',  'Event blocks with live countdown'],
                ['fa-clipboard-list','RSVPs with reminder emails'],
                ['fa-calendar-plus', 'Add to calendar (.ics)'],
                ['fa-people-group',  'Capacity caps &amp; waitlists'],
            ],
        ],
        [
            'eyebrow' => 'Referrals & templates',
            'title'   => 'Reward your fans. Start in seconds.',
            'icon'    => 'fa-gift',
            'color'   => '#fb7185',
            'href'    => route('site.features') . '#cat-referrals',
            'items'   => [
                ['fa-gift',           'Custom referral codes &amp; tracking'],
                ['fa-link',           'Personal /r/&lt;code&gt; share links'],
                ['fa-rectangle-list', 'Pre-built launch templates'],
                ['fa-floppy-disk',    'Save your own pages as templates'],
            ],
        ],
    ];
@endphp
<section id="everything" class="py-24 lg:py-32 relative overflow-hidden" aria-labelledby="everything-h">
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-14 max-w-3xl mx-auto">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c5)">Everything you get</div>
            <h2 id="everything-h" class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mb-5">
                One platform.<br><span class="grad-text">The whole growth stack.</span>
            </h2>
            <p class="reveal rd-2 text-lg text-gray-400">
                Four pillars, one login, free forever. No more stitching together five different tools to launch, share, sell and grow.
            </p>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
            @foreach($__pillars as $i => $p)
                <article class="reveal rd-{{ ($i % 4) + 1 }} glass rounded-3xl p-6 lift relative overflow-hidden">
                    <div class="absolute -top-12 -right-12 w-40 h-40 rounded-full opacity-20" style="background:{{ $p['color'] }};"></div>
                    <div class="relative w-12 h-12 rounded-2xl flex items-center justify-center mb-4" style="background: {{ $p['color'] }}; box-shadow: 0 12px 30px -12px {{ $p['color'] }};">
                        <i class="fas {{ $p['icon'] }} text-white"></i>
                    </div>
                    <div class="relative text-[11px] font-bold uppercase tracking-wider mb-1" style="color: {{ $p['color'] }};">{{ $p['eyebrow'] }}</div>
                    <h3 class="relative text-lg font-bold mb-4 leading-snug">{!! $p['title'] !!}</h3>

                    {{-- Mini visual preview, one per pillar --}}
                    <div class="pillar-preview relative mb-5 rounded-2xl p-3 overflow-hidden" style="background:rgba(255,255,255,.03); border:1px solid rgba(255,255,255,.06);" aria-hidden="true">
                        @if($i === 0)
                            {{-- Bio-link mini card --}}
                            <div class="flex items-center gap-2.5 mb-2.5">
                                <div class="w-9 h-9 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0" style="background:linear-gradient(135deg, {{ $p['color'] }}, #7c3aed);">M</div>
                                <div class="min-w-0">
                                    <div class="text-[11px] font-bold truncate">@maya.daily</div>
                                    <div class="text-[9px] text-gray-400 truncate">1inme.com/maya</div>
                                </div>
                                <span class="ml-auto inline-flex items-center gap-1 text-[8px] font-bold px-1.5 py-0.5 rounded-full" style="background:rgba(27,212,217,.15); color:{{ $p['color'] }};"><span class="pp-live-dot" style="background:{{ $p['color'] }};"></span>LIVE</span>
                            </div>
                            <div class="space-y-1.5">
                                <div class="flex items-center gap-2 px-2 py-1.5 rounded-lg" style="background:rgba(255,255,255,.05);">
                                    <i class="fab fa-spotify text-[10px]" style="color:#1ed760"></i>
                                    <span class="text-[10px] font-semibold">New single — out now</span>
                                </div>
                                <div class="flex items-center gap-2 px-2 py-1.5 rounded-lg" style="background:linear-gradient(90deg, rgba(27,212,217,.18), rgba(124,58,237,.12));">
                                    <i class="fas fa-store text-[10px]" style="color:{{ $p['color'] }}"></i>
                                    <span class="text-[10px] font-semibold">Merch shop</span>
                                </div>
                            </div>
                        @elseif($i === 1)
                            {{-- Styled QR + NFC badge --}}
                            <div class="flex items-center gap-3">
                                <div class="relative w-16 h-16 rounded-xl flex-shrink-0 p-1.5" style="background:linear-gradient(135deg, {{ $p['color'] }}, #1bd4d9);">
                                    <div class="pp-qr-wrap w-full h-full rounded-md grid grid-cols-6 grid-rows-6 gap-[1px] bg-[#0b0d12] p-1">
                                        @php $cells = [1,0,1,1,0,1, 0,1,0,1,1,0, 1,1,1,0,1,1, 0,1,0,1,0,1, 1,0,1,1,1,0, 0,1,1,0,1,1]; @endphp
                                        @foreach($cells as $c)
                                            <span class="rounded-[1px]" style="background: {{ $c ? '#fff' : 'transparent' }};"></span>
                                        @endforeach
                                    </div>
                                    <div class="pp-nfc-pulse absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full flex items-center justify-center text-[8px] font-bold text-white" style="background:{{ $p['color'] }}; box-shadow:0 4px 10px -2px {{ $p['color'] }};">
                                        <i class="fas fa-wifi" style="transform:rotate(90deg)"></i>
                                    </div>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="text-[10px] text-gray-400">Scans · 7d</div>
                                    <div class="text-base font-bold leading-tight">1,420</div>
                                    <div class="flex items-center gap-1 mt-1">
                                        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded" style="background:rgba(124,58,237,.2); color:#c4b5fd;">NFC</span>
                                        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded" style="background:rgba(255,255,255,.06); color:#9ca3af;">QR</span>
                                    </div>
                                </div>
                            </div>
                        @elseif($i === 2)
                            {{-- Tip + DM bubble --}}
                            <div class="space-y-2">
                                <div class="pp-tip-card flex items-center gap-2 px-2.5 py-2 rounded-xl" style="background:linear-gradient(90deg, rgba(233,78,140,.18), rgba(255,138,60,.12));">
                                    <div class="w-7 h-7 rounded-lg flex items-center justify-center text-white" style="background:linear-gradient(135deg, {{ $p['color'] }}, #ff8a3c);">
                                        <i class="fas fa-hand-holding-dollar text-[10px]"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-[10px] text-gray-300">Tip from <span class="font-bold">@leo</span></div>
                                        <div class="text-[11px] font-bold">$ 5.00 · "love your set 🔥"</div>
                                    </div>
                                </div>
                                <div class="flex items-end gap-1.5">
                                    <div class="w-5 h-5 rounded-full flex items-center justify-center text-white text-[8px] font-bold flex-shrink-0" style="background:linear-gradient(135deg,#7c3aed,#1bd4d9);">A</div>
                                    <div class="pp-dm-bubble px-2.5 py-1.5 rounded-2xl rounded-bl-sm text-[10px]" style="background:rgba(255,255,255,.08);">
                                        when's the next drop?
                                    </div>
                                </div>
                            </div>
                        @else
                            {{-- AI Coach health gauge --}}
                            <div class="flex items-center gap-3">
                                <div class="relative w-16 h-16 flex-shrink-0">
                                    <svg viewBox="0 0 36 36" class="w-full h-full -rotate-90">
                                        <circle cx="18" cy="18" r="15.5" fill="none" stroke="rgba(255,255,255,.08)" stroke-width="3"/>
                                        <circle class="pp-coach-arc" cx="18" cy="18" r="15.5" fill="none" stroke="url(#coachGrad{{ $i }})" stroke-width="3" stroke-linecap="round" stroke-dasharray="97.39" stroke-dashoffset="12.66"/>
                                        <defs>
                                            <linearGradient id="coachGrad{{ $i }}" x1="0" x2="1" y1="0" y2="1">
                                                <stop offset="0%" stop-color="{{ $p['color'] }}"/>
                                                <stop offset="100%" stop-color="#1bd4d9"/>
                                            </linearGradient>
                                        </defs>
                                    </svg>
                                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                                        <div class="pp-coach-num text-sm font-bold leading-none">87</div>
                                        <div class="text-[7px] text-gray-400 uppercase tracking-wider mt-0.5">health</div>
                                    </div>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5 mb-1">
                                        <i class="fas fa-bolt text-[10px]" style="color:{{ $p['color'] }}"></i>
                                        <span class="text-[10px] font-bold uppercase tracking-wider" style="color:{{ $p['color'] }}">AI Coach</span>
                                    </div>
                                    <div class="text-[10px] text-gray-300 leading-snug mb-1.5">Add a QR to your latest post — <span class="font-bold text-white">+12% est.</span></div>
                                    <div class="flex items-end gap-0.5 h-4">
                                        @foreach([4,7,5,9,6,11,8,12,10,14] as $bi => $h)
                                            <span class="pp-coach-bar flex-1 rounded-sm" style="height:{{ $h * 6 }}%;background:linear-gradient(180deg, {{ $p['color'] }}, #1bd4d9);animation-delay:{{ 0.2 + $bi * 0.07 }}s;"></span>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <ul class="relative space-y-2.5 text-sm text-gray-300">
                        @foreach($p['items'] as [$ic, $label])
                            <li class="flex items-start gap-2.5">
                                <span class="mt-0.5 w-5 h-5 rounded-md flex items-center justify-center flex-shrink-0" style="background: rgba(255,255,255,0.06); color: {{ $p['color'] }};">
                                    <i class="fas {{ $ic }} text-[10px]"></i>
                                </span>
                                <span>{!! $label !!}</span>
                            </li>
                        @endforeach
                    </ul>
                    @if(!empty($p['href']))
                        <div class="relative mt-5 pt-4 border-t border-white/5">
                            <a href="{{ $p['href'] }}" class="inline-flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider hover:translate-x-0.5 transition" style="color: {{ $p['color'] }};">
                                Explore <i class="fas fa-arrow-right text-[10px]"></i>
                            </a>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    </div>
</section>

@include('home.partials.ai-suite')
@include('home.partials.resume')
{{-- ============================ WORKSPACE & TEAM ============================ --}}
<section id="workspace-team" class="py-24 lg:py-32 relative overflow-hidden">
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-14 max-w-3xl mx-auto">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c1)">Workspace &amp; Team</div>
            <h2 class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mb-5">
                Run 1INME with <span class="grad-text">your whole team.</span>
            </h2>
            <p class="reveal rd-2 text-lg text-gray-400">
                Multiple workspaces, real teammates with real roles, fine-grained permissions and per-workspace billing — built for agencies, founders and busy creators.
            </p>
        </div>

        <div class="grid lg:grid-cols-2 gap-10 items-center">
            <div class="reveal rd-2">
                <div class="grid sm:grid-cols-2 gap-4">
                    @foreach([
                        ['fa-layer-group','#1bd4d9','Multiple workspaces','One per brand, client or side project — fully isolated.'],
                        ['fa-user-plus','#7c3aed','Invite teammates','Add members by email. They get their own login.'],
                        ['fa-user-shield','#e94e8c','Roles &amp; permissions','Owner, Admin, Editor, Viewer — locked down where it counts.'],
                        ['fa-credit-card','#ff8a3c','Billing per workspace','Separate plans &amp; invoices for each workspace.'],
                    ] as $i => $f)
                        <div class="reveal rd-{{ $i+1 }} glass rounded-2xl p-5 lift">
                            <div class="w-11 h-11 rounded-xl flex items-center justify-center mb-3" style="background: {{ $f[1] }}; box-shadow: 0 12px 30px -12px {{ $f[1] }};">
                                <i class="fas {{ $f[0] }} text-white"></i>
                            </div>
                            <h3 class="text-base font-bold mb-1">{!! $f[2] !!}</h3>
                            <p class="text-xs text-gray-400 leading-relaxed">{!! $f[3] !!}</p>
                        </div>
                    @endforeach
                </div>
                <div class="reveal rd-5 mt-8">
                    <a href="{{ route('site.workspace-team') }}" class="btn-bounce btn-glow inline-flex items-center justify-center gap-2 px-7 py-3.5 grad-bar text-white rounded-full text-sm font-bold">
                        Explore Workspace &amp; Team <i class="fas fa-arrow-right text-xs"></i>
                    </a>
                </div>
            </div>

            <div class="reveal rd-3">
                <div class="glass rounded-3xl p-6 sm:p-8 tilt relative overflow-hidden ws-card">
                    <div class="absolute -top-16 -right-16 w-56 h-56 rounded-full opacity-30" style="background:var(--c2);"></div>
                    <div class="absolute -bottom-20 -left-20 w-64 h-64 rounded-full opacity-20" style="background:var(--c2);"></div>

                    {{-- Live cursors floating across the panel --}}
                    <div class="ws-cursor" aria-hidden="true">
                        <svg viewBox="0 0 16 16" fill="none"><path d="M2 2 L14 8 L8 9.5 L6.5 14 Z" fill="#a78bfa" stroke="#fff" stroke-width="1"/></svg>
                        <span class="lbl">Jane</span>
                    </div>
                    <div class="ws-cursor c2" aria-hidden="true">
                        <svg viewBox="0 0 16 16" fill="none"><path d="M2 2 L14 8 L8 9.5 L6.5 14 Z" fill="#22d3ee" stroke="#fff" stroke-width="1"/></svg>
                        <span class="lbl">Marco</span>
                    </div>

                    <div class="relative">
                        {{-- Header --}}
                        <div class="flex items-center justify-between mb-5">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl grad-bar flex items-center justify-center text-white font-bold">A</div>
                                <div>
                                    <div class="text-sm font-bold">Acme Studio</div>
                                    <div class="text-[11px] text-gray-400 flex items-center gap-1.5">
                                        <span class="relative flex h-1.5 w-1.5">
                                            <span class="absolute inline-flex h-full w-full rounded-full opacity-75 animate-ping" style="background:#22c55e"></span>
                                            <span class="relative inline-flex rounded-full h-1.5 w-1.5" style="background:#22c55e"></span>
                                        </span>
                                        Pro workspace · 6 members · 5 online
                                    </div>
                                </div>
                            </div>
                            <span class="text-[10px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-full" style="background:rgba(27,212,217,.15); color:var(--c1);">Active</span>
                        </div>

                        {{-- Live activity rows --}}
                        @php
                            $teammates = [
                                [
                                    'name' => 'Jane Doe',
                                    'role' => 'Owner',
                                    'avatar' => '/images/hero-roles/role_designer.jpg',
                                    'badge_class' => 'ws-b-edit',
                                    'badge' => 'Editing',
                                    'task_icon' => 'fa-pen',
                                    'task' => 'Spring Campaign page',
                                    'extra' => 'typing',
                                    'delay' => '0s',
                                ],
                                [
                                    'name' => 'Marco Perez',
                                    'role' => 'Admin',
                                    'avatar' => '/images/hero-roles/role_developer.jpg',
                                    'badge_class' => 'ws-b-up',
                                    'badge' => 'Uploading',
                                    'task_icon' => 'fa-cloud-arrow-up',
                                    'task' => '12 assets · Brand kit',
                                    'extra' => 'progress',
                                    'delay' => '.12s',
                                ],
                                [
                                    'name' => 'Aisha Khan',
                                    'role' => 'Editor',
                                    'avatar' => '/images/hero-roles/role_writer.jpg',
                                    'badge_class' => 'ws-b-comment',
                                    'badge' => 'Commenting',
                                    'task_icon' => 'fa-comment-dots',
                                    'task' => 'on “Hero copy v3”',
                                    'extra' => null,
                                    'delay' => '.24s',
                                ],
                                [
                                    'name' => 'Devon Smith',
                                    'role' => 'Editor',
                                    'avatar' => '/images/hero-roles/role_business.jpg',
                                    'badge_class' => 'ws-b-ok',
                                    'badge' => 'Approved',
                                    'task_icon' => 'fa-circle-check',
                                    'task' => 'Q1 Report · just now',
                                    'extra' => null,
                                    'delay' => '.36s',
                                ],
                                [
                                    'name' => 'Priya Nair',
                                    'role' => 'Viewer',
                                    'avatar' => '/images/hero-roles/role_photographer.jpg',
                                    'badge_class' => 'ws-b-view',
                                    'badge' => 'Viewing',
                                    'task_icon' => 'fa-chart-line',
                                    'task' => 'Analytics dashboard',
                                    'extra' => null,
                                    'delay' => '.48s',
                                ],
                            ];
                        @endphp
                        <div class="space-y-2.5">
                            @foreach($teammates as $m)
                                <div class="ws-row" style="animation-delay: {{ $m['delay'] }}">
                                    <div class="ws-avatar is-online">
                                        <img src="{{ $m['avatar'] }}" alt="{{ $m['name'] }}" loading="lazy">
                                    </div>
                                    <div class="ws-meta">
                                        <div class="ws-name">
                                            {{ $m['name'] }}
                                            <span class="text-[10px] font-semibold uppercase tracking-wider text-gray-500">· {{ $m['role'] }}</span>
                                        </div>
                                        <div class="ws-task">
                                            <i class="fas {{ $m['task_icon'] }}"></i>
                                            <span class="truncate">{{ $m['task'] }}</span>
                                            @if($m['extra'] === 'typing')
                                                <span class="ws-typing" aria-hidden="true"><span></span><span></span><span></span></span>
                                            @endif
                                        </div>
                                        @if($m['extra'] === 'progress')
                                            <div class="ws-prog" aria-hidden="true"><div class="bar"></div></div>
                                        @endif
                                    </div>
                                    <span class="ws-badge {{ $m['badge_class'] }}">
                                        <span class="dot"></span>{{ $m['badge'] }}
                                    </span>
                                </div>
                            @endforeach
                        </div>

                        {{-- Footer: online stack + actions --}}
                        <div class="mt-5 flex items-center justify-between gap-3 flex-wrap">
                            <div class="flex items-center gap-3">
                                <div class="ws-online-stack" aria-label="Online now">
                                    @foreach($teammates as $m)
                                        <div class="av"><img src="{{ $m['avatar'] }}" alt="" loading="lazy"></div>
                                    @endforeach
                                </div>
                                <span class="text-[11px] text-gray-400">Collaborating live</span>
                            </div>
                            <div class="flex items-center gap-4 text-[11px] text-gray-400">
                                <span><i class="fas fa-arrow-right-arrow-left mr-1.5"></i>Switch</span>
                                <span><i class="fas fa-receipt mr-1.5"></i>Billing</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ============================ BUZZ ============================ --}}
<section id="buzz" class="py-24 lg:py-32 relative overflow-hidden">
    <div class="absolute inset-0 -z-10" style="background:rgba(124,58,237,.06);"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-14 max-w-3xl mx-auto">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c3)">Buzz</div>
            <h2 class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mb-5">
                Show visitors <span class="grad-text">real momentum.</span>
            </h2>
            <p class="reveal rd-2 text-lg text-gray-400">
                Buzz is the social-proof widget already wired into every 1INME biolink. Live signups, visits and purchases pop up right on your page so visitors see the room is busy — and act.
            </p>
        </div>

        <div class="grid lg:grid-cols-2 gap-10 items-center">
            <div class="reveal rd-3 order-2 lg:order-1">
                <div class="relative glass rounded-3xl p-6 sm:p-8 tilt overflow-hidden" style="min-height: 360px;">
                    <div class="absolute -bottom-20 -left-20 w-64 h-64 rounded-full opacity-30" style="background:var(--c2);"></div>
                    <div class="relative">
                        <div class="flex items-center justify-between mb-4">
                            <div class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Live on your biolink</div>
                            <span class="flex items-center gap-1.5 text-[10px] font-bold px-2.5 py-1 rounded-full" style="background:rgba(74,222,128,.15);color:#4ade80">
                                <span class="w-1.5 h-1.5 rounded-full pulse-dot" style="background:#4ade80"></span>7 events · last min
                            </span>
                        </div>

                        <div class="buzz-feed">
                            {{-- 1 · NEW FOLLOW with real avatar --}}
                            <div class="buzz-card fresh">
                                <span class="fresh-tag">✨ Just now</span>
                                <div class="bz-follow">
                                    <div class="bz-avatar">
                                        <img src="/images/hero-roles/role_designer-200.jpg" alt="Sara" loading="lazy" decoding="async" width="40" height="40">
                                        <span class="on" aria-hidden="true"></span>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="name">Sara from Berlin</div>
                                        <div class="meta"><i class="fas fa-user-plus text-[9px] mr-1" style="color:var(--c1)"></i>just followed you · 12s ago</div>
                                    </div>
                                    <a href="#" class="btn">Follow back</a>
                                </div>
                            </div>

                            {{-- 2 · PURCHASE with product thumb + price --}}
                            <div class="buzz-card">
                                <div class="bz-buy">
                                    <div class="bz-thumb">
                                        <img src="/images/hero-roles/thumb_design-320.jpg" alt="Lightroom Pack" loading="lazy" decoding="async" width="64" height="64">
                                        <span class="tag">Preset</span>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="product">🛒 Lightroom Pack · Vol II</div>
                                        <div class="who">bought by <b class="text-white">@nora.cph</b> · 42s ago</div>
                                    </div>
                                    <span class="price"><span class="d"></span>+$24.00</span>
                                </div>
                            </div>

                            {{-- 3 · LIVE VIEWERS with bar --}}
                            <div class="buzz-card">
                                <div class="bz-views">
                                    <div class="ic"><i class="fas fa-eye"></i></div>
                                    <div class="min-w-0 w-full">
                                        <div class="row">
                                            <span><b>🇳🇬 Lagos</b> &amp; 5 cities viewing now</span>
                                            <span class="num">+18</span>
                                        </div>
                                        <div class="track"><div class="fill"></div></div>
                                    </div>
                                </div>
                            </div>

                            {{-- 4 · TIP with spinning coin --}}
                            <div class="buzz-card">
                                <div class="bz-tip">
                                    <div class="bz-coin">$</div>
                                    <div class="min-w-0">
                                        <div class="who"><b>@yuki.draws</b> sent you a tip</div>
                                        <div class="msg">“Loved your latest pack — keep going!”</div>
                                    </div>
                                    <div class="amt">$5<small>.00</small></div>
                                </div>
                            </div>

                            {{-- 5 · FORM submission --}}
                            <div class="buzz-card">
                                <div class="bz-form">
                                    <div class="ic"><i class="fas fa-envelope-open-text"></i></div>
                                    <div class="min-w-0">
                                        <div class="who">Marco from Madrid · contact form</div>
                                        <div class="subj">“Hi! Available for a wedding shoot in June?”</div>
                                    </div>
                                    <span class="pri">High</span>
                                </div>
                            </div>

                            {{-- 6 · QR scan with sparkline --}}
                            <div class="buzz-card">
                                <div class="bz-qr">
                                    <div class="ic"><i class="fas fa-qrcode"></i></div>
                                    <div class="min-w-0">
                                        <div class="label">QR · Studio poster scanned</div>
                                        <div class="meta">+127 scans today · peak 4:20 pm</div>
                                    </div>
                                    <span class="spark" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></span>
                                </div>
                            </div>

                            {{-- 7 · GOAL hit (full-width progress) --}}
                            <div class="buzz-card bz-goal">
                                <div class="top">
                                    <div class="trophy"><i class="fas fa-trophy text-sm"></i></div>
                                    <div class="title">🎉 Monthly goal hit · 1,000 followers</div>
                                    <div class="pct">100%</div>
                                </div>
                                <div class="track">
                                    <div class="fill"></div>
                                    <span class="conf" aria-hidden="true">🎊</span>
                                </div>
                            </div>
                        </div>

                        <div class="text-center mt-4 text-[10px] text-gray-500 uppercase tracking-wider font-semibold">
                            <i class="fas fa-circle-down mr-1 opacity-60"></i> 12 more events today
                        </div>
                    </div>
                </div>
            </div>

            <div class="reveal rd-2 order-1 lg:order-2">
                <div class="grid sm:grid-cols-2 gap-4">
                    @foreach([
                        ['fa-bolt','#ffc845','Real-time activity','Live signups, visits, purchases &amp; form fills.'],
                        ['fa-toggle-on','#1bd4d9','Zero setup','Already integrated with your biolink — flip it on.'],
                        ['fa-sliders','#e94e8c','Pick what shows','Choose events &amp; priorities; hide the rest.'],
                        ['fa-user-secret','#7c3aed','Privacy-first','Names masked, locations coarse, dismissible.'],
                    ] as $i => $f)
                        <div class="reveal rd-{{ $i+1 }} glass rounded-2xl p-5 lift">
                            <div class="w-11 h-11 rounded-xl flex items-center justify-center mb-3" style="background: {{ $f[1] }}; box-shadow: 0 12px 30px -12px {{ $f[1] }};">
                                <i class="fas {{ $f[0] }} text-white"></i>
                            </div>
                            <h3 class="text-base font-bold mb-1">{!! $f[2] !!}</h3>
                            <p class="text-xs text-gray-400 leading-relaxed">{!! $f[3] !!}</p>
                        </div>
                    @endforeach
                </div>
                <div class="reveal rd-5 mt-8">
                    <a href="{{ route('site.buzz') }}" class="btn-bounce btn-glow inline-flex items-center justify-center gap-2 px-7 py-3.5 grad-bar text-white rounded-full text-sm font-bold">
                        See how Buzz works <i class="fas fa-arrow-right text-xs"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ============================ BY THE NUMBERS (stats strip) ============================ --}}
<section id="stats" class="py-12 lg:py-16 relative overflow-hidden" aria-label="By the numbers">
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('public.partials.marketing-stats')
    </div>
</section>

{{-- ============================ TESTIMONIAL MARQUEE ============================ --}}
<section id="proof" class="py-20 lg:py-24 relative overflow-hidden">
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c5)">Social proof</div>
            <h2 class="reveal rd-1 text-3xl sm:text-4xl lg:text-5xl font-bold">Loved by people who <span class="grad-text">do the most.</span></h2>
        </div>
    </div>

    @php
        try {
            $__topReviews    = \App\Modules\Admin\Models\Testimonial::active()->where('row', 'top')->ordered()->get();
            $__bottomReviews = \App\Modules\Admin\Models\Testimonial::active()->where('row', 'bottom')->ordered()->get();
        } catch (\Throwable $e) {
            $__topReviews = collect();
            $__bottomReviews = collect();
        }
    @endphp

    @if($__topReviews->isNotEmpty())
        <div class="overflow-hidden mb-4" style="mask-image: linear-gradient(90deg, transparent, #000 8%, #000 92%, transparent);">
            <div class="flex whitespace-nowrap marquee">
                @for($i = 0; $i < 2; $i++)
                    @foreach($__topReviews as $r)
                        <div class="inline-block w-[340px] sm:w-[400px] mx-3 align-top">
                            <div class="glass rounded-3xl p-6 lift">
                                <div class="flex text-base mb-3" style="color:var(--c5)">
                                    @for($s = 0; $s < $r->rating; $s++)<i class="fas fa-star {{ $s ? 'ml-0.5' : '' }}"></i>@endfor
                                </div>
                                <p class="text-sm text-gray-200 mb-4 whitespace-normal">&ldquo;{{ $r->quote }}&rdquo;</p>
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold text-white" style="background: linear-gradient(135deg, {{ $r->accent_color }}, var(--c2));">{{ $r->initial() }}</div>
                                    <div>
                                        <div class="text-sm font-bold">{{ $r->author_name }}</div>
                                        <div class="text-[11px] text-gray-500">{{ $r->author_role }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endfor
            </div>
        </div>
    @endif

    @if($__bottomReviews->isNotEmpty())
        <div class="overflow-hidden" style="mask-image: linear-gradient(90deg, transparent, #000 8%, #000 92%, transparent);">
            <div class="flex whitespace-nowrap marquee-rev">
                @for($i = 0; $i < 2; $i++)
                    @foreach($__bottomReviews as $r)
                        <div class="inline-block w-[340px] sm:w-[400px] mx-3 align-top">
                            <div class="glass rounded-3xl p-6 lift">
                                <div class="flex text-base mb-3" style="color:var(--c5)">
                                    @for($s = 0; $s < $r->rating; $s++)<i class="fas fa-star {{ $s ? 'ml-0.5' : '' }}"></i>@endfor
                                </div>
                                <p class="text-sm text-gray-200 mb-4 whitespace-normal">&ldquo;{{ $r->quote }}&rdquo;</p>
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold text-white" style="background: linear-gradient(135deg, {{ $r->accent_color }}, var(--c2));">{{ $r->initial() }}</div>
                                    <div>
                                        <div class="text-sm font-bold">{{ $r->author_name }}</div>
                                        <div class="text-[11px] text-gray-500">{{ $r->author_role }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endfor
            </div>
        </div>
    @endif
</section>

{{-- ============================ FAQ (homepage — searchable, chip-filtered) ============================ --}}
@php
    $__homeFaqGroups = \App\Modules\Common\Support\SitePagesContent::homepageFaqs();
    $__homeFaqHighlights = \App\Modules\Common\Support\SitePagesContent::homepageFaqHighlights();
    $__faqJsonLd = [
        '@context' => 'https://schema.org',
        '@type'    => 'FAQPage',
        'mainEntity' => array_map(function ($q) {
            return [
                '@type' => 'Question',
                'name'  => $q['q'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q['a']],
            ];
        }, $__homeFaqHighlights),
    ];
@endphp
<script type="application/ld+json">{!! json_encode($__faqJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
<section id="faq" class="pt-16 pb-10 lg:pt-20 lg:pb-12 relative overflow-hidden">
    <div class="relative max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-8">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c4)">FAQ</div>
            <h2 class="reveal rd-1 text-3xl sm:text-4xl font-bold tracking-tight mb-2">Questions? <span class="grad-text">Answered.</span></h2>
            <p class="reveal rd-2 text-sm text-gray-400">A quick highlight reel — the full searchable library lives on the FAQ page.</p>
        </div>

        <div class="reveal rd-3 space-y-3">
            @foreach($__homeFaqHighlights as $f)
                <details class="faq-item glass rounded-2xl px-5 py-4 hover:bg-white/[.06] transition-colors">
                    <summary class="flex items-center justify-between gap-4 cursor-pointer">
                        <span class="font-semibold text-sm sm:text-base pr-4">{{ $f['q'] }}</span>
                        <span class="faq-icon w-6 h-6 rounded-full grad-bar text-white flex items-center justify-center font-bold flex-shrink-0">
                            <i class="fas fa-plus text-[10px]"></i>
                        </span>
                    </summary>
                    <p class="mt-3 text-sm text-gray-300 leading-relaxed">{{ $f['a'] }}</p>
                </details>
            @endforeach
        </div>

        <div class="reveal rd-4 mt-6 text-center">
            <a href="{{ route('site.faqs') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-violet-300 hover:text-violet-200 transition">
                Browse all answers <i class="fas fa-arrow-right text-xs"></i>
            </a>
        </div>
    </div>
</section>

{{-- ============================ FEATURED POSTS CAROUSEL ============================ --}}
@if(!empty($featuredBlogPosts) && $featuredBlogPosts->count())
<section id="blog-featured" class="pt-14 pb-12 lg:pt-16 lg:pb-14 relative overflow-hidden">
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-end justify-between mb-10 gap-6 flex-wrap">
            <div>
                <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c4)">From the blog</div>
                <h2 class="reveal rd-1 text-4xl sm:text-5xl font-bold tracking-tight mb-3">Featured <span class="grad-text">stories.</span></h2>
                <p class="reveal rd-2 text-gray-400 max-w-xl">Tips, product news and creator deep-dives — fresh from the 1INME team.</p>
            </div>
            <a href="{{ route('site.blogs.index') }}" class="hidden sm:inline-flex items-center gap-2 text-sm font-semibold text-violet-300 hover:text-violet-200 transition">
                Browse all posts
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
            </a>
        </div>

        {{-- Snap-scroll carousel on mobile, 3-up grid from md+. --}}
        <div class="-mx-4 sm:mx-0 px-4 sm:px-0 flex sm:grid sm:grid-cols-2 md:grid-cols-3 gap-6 overflow-x-auto sm:overflow-visible snap-x snap-mandatory scroll-smooth pb-4 sm:pb-0 [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
            @foreach($featuredBlogPosts as $post)
                <a href="{{ route('site.blogs.show', $post->slug) }}"
                   class="group shrink-0 w-[85%] sm:w-auto snap-start block bg-white/[0.03] border border-white/10 rounded-2xl overflow-hidden hover:border-violet-500/40 transition reveal rd-{{ $loop->iteration + 1 }}">
                    @if($post->cover_image)
                        <div class="aspect-[16/9] bg-white/5 overflow-hidden">
                            <img src="{{ $post->cover_image }}" alt="" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                        </div>
                    @else
                        <div class="aspect-[16/9]" style="background:rgba(124,58,237,.18);"></div>
                    @endif
                    <div class="p-6">
                        @if($post->category)
                            <span class="inline-block text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded-full mb-3" style="background: {{ $post->category->color ? $post->category->color . '22' : 'rgba(124,58,237,.15)' }}; color: {{ $post->category->color ?: '#a78bfa' }};">{{ $post->category->name }}</span>
                        @endif
                        <h3 class="text-lg font-semibold text-white group-hover:text-violet-200 transition">{{ $post->title }}</h3>
                        @if($post->excerpt)
                            <p class="mt-2 text-sm text-gray-400 line-clamp-3">{{ $post->excerpt }}</p>
                        @endif
                        <div class="mt-4 flex items-center gap-2 text-xs text-white/50">
                            <span>{{ optional($post->published_at)->format('M j, Y') }}</span>
                            <span>·</span>
                            <span>{{ $post->reading_time_min }} min read</span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-8 sm:hidden text-center">
            <a href="{{ route('site.blogs.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-violet-300 hover:text-violet-200 transition">
                Browse all posts
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
            </a>
        </div>
    </div>
</section>
@endif

{{-- ============================ TRUST BAND (security & reliability) ============================ --}}
<section id="trust" class="py-12 lg:py-14 relative overflow-hidden" aria-label="Trust signals">
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="reveal glass rounded-3xl p-6 sm:p-8">
            <div class="grid md:grid-cols-2 gap-6 items-center">
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-[.2em] mb-2" style="color:var(--c1)">Built on trust</div>
                    <h3 class="text-2xl sm:text-3xl font-bold text-white leading-tight">Your data stays <span class="grad-text">your data.</span></h3>
                    <p class="text-sm text-gray-400 mt-2">Encrypted in transit and at rest, GDPR-friendly by default, no third-party trackers on your published pages — ever.</p>
                </div>
                <ul class="grid grid-cols-2 gap-3">
                    @foreach([
                        ['fa-shield-halved', '99.9% uptime', 'Multi-region edge'],
                        ['fa-lock', 'TLS 1.3', 'End-to-end encrypted'],
                        ['fa-user-shield', 'GDPR-ready', 'EU/UK SCCs in place'],
                        ['fa-server', 'Daily backups', '30-day retention'],
                    ] as $i => $t)
                        <li class="flex items-center gap-3 p-3 rounded-2xl bg-white/[.04] border border-white/5">
                            <span class="w-10 h-10 rounded-xl flex items-center justify-center grad-bar shrink-0"><i class="fas {{ $t[0] }} text-white text-sm"></i></span>
                            <div>
                                <div class="text-sm font-bold text-white leading-tight">{{ $t[1] }}</div>
                                <div class="text-[11px] text-gray-400">{{ $t[2] }}</div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</section>

{{-- ============================ HOW WE COMPARE ============================ --}}
@include('public.partials._compare', ['compact' => true, 'eyebrowOverride' => 'How we compare'])
@php
    // Legacy inline arrays kept commented out — replaced by shared partial above.
    /*
    $__cmpCompetitors = [
        ['key' => 'ours',     'name' => '1INME',         'badge' => 'Better deal',           'isOurs' => true],
        ['key' => 'linktree', 'name' => 'Linktree',      'badge' => 'Half the cost',         'isOurs' => false],
        ['key' => 'bitly',    'name' => 'Bitly',         'badge' => 'More features included', 'isOurs' => false],
        ['key' => 'beacons',  'name' => 'Beacons',       'badge' => 'Up to 1/10th the price','isOurs' => false],
    ];
    // 10 features. Order chosen to front-load 1INME-only wins.
    $__cmpFeatures = [
        ['Biolink pages',             ['ours' => true, 'linktree' => true,  'bitly' => true,  'beacons' => true]],
        ['Branded short links',       ['ours' => true, 'linktree' => false, 'bitly' => true,  'beacons' => false]],
        ['Dynamic QR codes',          ['ours' => true, 'linktree' => true,  'bitly' => true,  'beacons' => true]],
        ['Built-in analytics',        ['ours' => true, 'linktree' => true,  'bitly' => true,  'beacons' => true]],
        ['Live visitor map',          ['ours' => true, 'linktree' => false, 'bitly' => false, 'beacons' => false]],
        ['Performance coach',         ['ours' => true, 'linktree' => false, 'bitly' => false, 'beacons' => false]],
        ['Team workspaces',           ['ours' => true, 'linktree' => true,  'bitly' => true,  'beacons' => false]],
        ['Direct messaging',          ['ours' => true, 'linktree' => false, 'bitly' => false, 'beacons' => false]],
        ['Scheduled posts',           ['ours' => true, 'linktree' => false, 'bitly' => false, 'beacons' => true]],
        ['Custom domains',            ['ours' => true, 'linktree' => true,  'bitly' => true,  'beacons' => true]],
    ];
    */
@endphp
@if(false)
<section id="compare-legacy" class="py-20 lg:py-28 relative overflow-hidden">
    <div class="mesh-bg" aria-hidden="true"></div>
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12 max-w-2xl mx-auto">
            <div data-anim="fade-up" class="text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c4)">How we compare</div>
            <h2 data-anim="fade-up" class="text-4xl sm:text-5xl font-bold tracking-tight mb-4">
                More features. <span class="grad-text">Better deal.</span>
            </h2>
            <p data-anim="fade-up" class="text-gray-400">See how 1INME stacks up against the link-in-bio tools you already know — and why creators are switching.</p>
        </div>

        {{-- ===== Desktop / tablet matrix ===== --}}
        <div data-anim="fade-up" class="hidden md:block cmp-wrap">
            <div class="grad-border rounded-3xl overflow-hidden relative">
                {{-- Highlighted column band overlays the 1INME column (col 2 of 5: feature col + 4 brand cols) --}}
                <div class="cmp-ours-band" style="left: 40%; width: calc(60% / 4);"></div>

                {{-- Header --}}
                <div class="grid items-center px-4 sm:px-6 py-5 bg-white/[.03] text-xs font-bold uppercase tracking-wider text-gray-400 relative z-[1]"
                     style="grid-template-columns: 40% repeat(4, 1fr);">
                    <div>Feature</div>
                    @foreach($__cmpCompetitors as $c)
                        <div class="text-center">
                            @if($c['isOurs'])
                                <span class="cmp-brand-ours text-xs">
                                    <i class="fas fa-bolt"></i> {{ $c['name'] }}
                                </span>
                            @else
                                <span class="text-gray-300 text-sm normal-case tracking-normal font-semibold">{{ $c['name'] }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- Rows --}}
                <div class="cmp-stagger" data-anim="fade">
                    @foreach($__cmpFeatures as $row)
                        @php [$label, $support] = $row; @endphp
                        <div class="cmp-row grid items-center px-4 sm:px-6 py-4 border-t border-white/5 text-sm"
                             style="grid-template-columns: 40% repeat(4, 1fr);">
                            <div class="text-gray-200 font-medium">{{ $label }}</div>
                            @foreach($__cmpCompetitors as $c)
                                <div class="text-center">
                                    @if($support[$c['key']])
                                        <span class="cmp-mark {{ $c['isOurs'] ? 'cmp-mark-yes-ours' : 'cmp-mark-yes' }}" aria-label="Included">
                                            <svg class="cmp-draw" width="{{ $c['isOurs'] ? 18 : 14 }}" height="{{ $c['isOurs'] ? 18 : 14 }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M5 12.5l4.5 4.5L19 7"/>
                                            </svg>
                                        </span>
                                    @else
                                        <span class="cmp-mark cmp-mark-no" aria-label="Not included">
                                            <svg class="cmp-draw" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true">
                                                <path d="M6 12h12"/>
                                            </svg>
                                        </span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endforeach

                    {{-- Footer badges row --}}
                    <div class="cmp-row grid items-center px-4 sm:px-6 py-5 border-t border-white/10 bg-white/[.02]"
                         style="grid-template-columns: 40% repeat(4, 1fr);">
                        <div class="text-xs font-bold uppercase tracking-wider text-gray-400">The bottom line</div>
                        @foreach($__cmpCompetitors as $c)
                            <div class="text-center">
                                <span class="cmp-badge {{ $c['isOurs'] ? 'cmp-badge-ours' : '' }}">
                                    @if($c['isOurs'])<i class="fas fa-star text-[10px]"></i>@endif
                                    {{ $c['badge'] }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== Mobile stacked cards ===== --}}
        <div class="md:hidden space-y-4" data-anim="fade">
            @foreach($__cmpCompetitors as $c)
                <div data-anim="fade-up" class="cmp-card {{ $c['isOurs'] ? 'cmp-card-ours' : '' }}">
                    <div class="flex items-center justify-between gap-3 mb-4">
                        <div class="flex items-center gap-2">
                            @if($c['isOurs'])
                                <span class="cmp-brand-ours text-xs"><i class="fas fa-bolt"></i> {{ $c['name'] }}</span>
                            @else
                                <span class="text-base font-bold text-gray-100">{{ $c['name'] }}</span>
                            @endif
                        </div>
                        <span class="cmp-badge {{ $c['isOurs'] ? 'cmp-badge-ours' : '' }}">
                            @if($c['isOurs'])<i class="fas fa-star text-[10px]"></i>@endif
                            {{ $c['badge'] }}
                        </span>
                    </div>
                    <ul class="cmp-stagger space-y-2.5" data-anim="fade">
                        @foreach($__cmpFeatures as $row)
                            @php [$label, $support] = $row; $on = $support[$c['key']]; @endphp
                            <li class="cmp-row flex items-center gap-3 text-sm">
                                @if($on)
                                    <span class="cmp-mark {{ $c['isOurs'] ? 'cmp-mark-yes-ours' : 'cmp-mark-yes' }}" style="width:24px;height:24px;">
                                        <svg class="cmp-draw" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M5 12.5l4.5 4.5L19 7"/>
                                        </svg>
                                    </span>
                                @else
                                    <span class="cmp-mark cmp-mark-no" style="width:24px;height:24px;">
                                        <svg class="cmp-draw" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true">
                                            <path d="M6 12h12"/>
                                        </svg>
                                    </span>
                                @endif
                                <span class="{{ $on ? 'text-gray-100' : 'text-gray-500 line-through' }}">{{ $label }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>

        <p data-anim="fade-up" class="text-center text-xs text-gray-500 mt-6">Comparison reflects publicly listed feature sets at the time of writing. We never quote a competitor's price.</p>
    </div>
</section>
@endif

@include('home.partials.pricing')
{{-- ============================ FINAL CTA ============================ --}}
{{-- Visually distinct from the gradient hero blocks above: a single asymmetric
     glass card with a left-aligned headline + right-aligned action, so the
     closing run reads as "cards → trust strip → links → one final CTA". --}}
<section id="cta-final" class="py-16 lg:py-20 relative overflow-hidden">
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="reveal glass rounded-[2rem] p-8 sm:p-12 relative overflow-hidden border border-white/10">
            <div class="absolute -top-24 -right-20 w-80 h-80 rounded-full opacity-30 blur-3xl" style="background: var(--c2);"></div>
            <div class="absolute -bottom-24 -left-20 w-80 h-80 rounded-full opacity-25 blur-3xl" style="background: var(--c4);"></div>

            <div class="relative grid lg:grid-cols-[1fr_auto] gap-8 lg:gap-10 items-center">
                <div class="text-center lg:text-left">
                    <div class="text-[11px] font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c5)">Ready when you are</div>
                    <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold leading-tight">
                        Your audience is <span class="grad-text">already searching for you.</span>
                    </h2>
                    <p class="text-base text-gray-400 mt-4 max-w-xl mx-auto lg:mx-0">
                        Build the page. Share the link. Watch them show up — live on a map.
                    </p>
                </div>
                <div class="flex flex-col sm:flex-row lg:flex-col gap-3 shrink-0 items-stretch sm:justify-center lg:items-stretch">
                    <button type="button" onclick="window.trackMarketingEvent && window.trackMarketingEvent('landing_home_cta','final_cta'); window.dispatchEvent(new CustomEvent('open-auth',{detail:{tab:'register'}}))" class="btn-bounce btn-glow inline-flex items-center justify-center gap-2 px-8 py-4 grad-bar text-white rounded-full text-base font-bold whitespace-nowrap">
                        Sign up free <i class="fas fa-arrow-right text-xs"></i>
                    </button>
                    <a href="#features" class="btn-bounce inline-flex items-center justify-center gap-2 px-8 py-4 glass-2 text-white rounded-full text-base font-bold whitespace-nowrap">
                        See features
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ============================ FOOTER ============================ --}}
<footer class="bg-[#08020f] text-white pt-16 pb-8 border-t border-white/5">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid md:grid-cols-6 gap-8 mb-12">
            <div class="md:col-span-2">
                <a href="{{ route('home') }}" class="inline-flex items-center" aria-label="1INME home">
                    @include('common.partials.brand-logo', ['height' => 'h-9'])
                </a>
                <p class="text-sm text-gray-500 mt-3 leading-relaxed max-w-sm">The all-in-one link platform: build a drag-and-drop biolink, share it everywhere, and grow with live analytics and a built-in Performance Coach.</p>
            </div>
            <div>
                <h4 class="text-xs font-bold text-gray-300 uppercase tracking-wider mb-4">Product</h4>
                <ul class="space-y-2.5">
                    <li><a href="#features" class="text-sm text-gray-500 hover:text-white">Features</a></li>
                    <li><a href="#how-it-works" class="text-sm text-gray-500 hover:text-white">How it works</a></li>
                    <li><a href="{{ route('site.workspace-team') }}" class="text-sm text-gray-500 hover:text-white">Workspace &amp; Team</a></li>
                    <li><a href="#pricing" class="text-sm text-gray-500 hover:text-white">Pricing</a></li>
                    <li><a href="{{ route('site.api-docs') }}" class="text-sm text-gray-500 hover:text-white">API</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-xs font-bold text-gray-300 uppercase tracking-wider mb-4">Solutions</h4>
                <ul class="space-y-2.5">
                    <li><a href="{{ route('site.services') }}" class="text-sm text-gray-500 hover:text-white">Use cases</a></li>
                    <li><a href="{{ route('site.discovery') }}" class="text-sm text-gray-500 hover:text-white">Discover creators</a></li>
                    <li><a href="{{ route('site.creators-feed') }}" class="text-sm text-gray-500 hover:text-white">Creators feed</a></li>
                    <li><a href="{{ route('site.buzz') }}" class="text-sm text-gray-500 hover:text-white">Buzz</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-xs font-bold text-gray-300 uppercase tracking-wider mb-4">Company</h4>
                <ul class="space-y-2.5">
                    <li><a href="{{ route('site.about') }}" class="text-sm text-gray-500 hover:text-white">About</a></li>
                    <li><a href="{{ route('site.contact') }}" class="text-sm text-gray-500 hover:text-white">Contact</a></li>
                    <li><a href="{{ route('site.faqs') }}" class="text-sm text-gray-500 hover:text-white">FAQs</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-xs font-bold text-gray-300 uppercase tracking-wider mb-4">Legal</h4>
                <ul class="space-y-2.5">
                    <li><a href="{{ route('site.terms') }}" class="text-sm text-gray-500 hover:text-white">Terms</a></li>
                    <li><a href="{{ route('site.privacy') }}" class="text-sm text-gray-500 hover:text-white">Privacy</a></li>
                    <li><a href="{{ route('site.refunds') }}" class="text-sm text-gray-500 hover:text-white">Refunds</a></li>
                    <li><a href="{{ route('site.cookies') }}" class="text-sm text-gray-500 hover:text-white">Cookies</a></li>
                    <li><a href="{{ route('site.gdpr') }}" class="text-sm text-gray-500 hover:text-white">GDPR</a></li>
                </ul>
            </div>
        </div>
        <div class="border-t border-white/5 pt-6 pb-6">
            @include('common.partials.social-row')
        </div>
        <div class="border-t border-white/5 pt-5 pb-5">
            @include('common.partials.shortcut-hint')
        </div>
        <div class="border-t border-white/5 pt-6 flex flex-col sm:flex-row justify-between items-center gap-3">
            <p class="text-sm text-gray-600">&copy; {{ date('Y') }} 1INME. All rights reserved.</p>
            <div class="flex items-center gap-3 text-xs text-gray-600">
                @php
                    $__ccCfgHome = \App\Modules\Common\Support\CookieConsentConfig::shouldRender('site')
                        ? \App\Modules\Common\Support\CookieConsentConfig::get() : null;
                @endphp
                @if($__ccCfgHome)
                    @php
                        $__ccCopyHome = \App\Modules\Common\Support\CookieConsentConfig::copyFor($__ccCfgHome);
                        $__ccPolicyHome = $__ccCopyHome['policy_link_url'] ?? '/cookies';
                        $__ccReopenHome = $__ccCopyHome['reopen_link_label'] ?? 'Cookie preferences';
                    @endphp
                    <a href="{{ $__ccPolicyHome }}"
                       class="cc-footer-link text-gray-500 hover:text-white"
                       aria-label="{{ $__ccReopenHome }}"
                       onclick="if(window.openCookiePreferences){return window.openCookiePreferences(event);}">
                        {{ $__ccReopenHome }}
                    </a>
                    <span class="text-white/10">·</span>
                @endif
                <p>One link to everything.</p>
            </div>
        </div>
    </div>
</footer>

@include('common.partials.global-shortcuts')

<script>
    document.documentElement.classList.add('js');
    document.addEventListener('DOMContentLoaded', () => {
        const reveals = document.querySelectorAll('.reveal');
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
            setTimeout(() => reveals.forEach(el => el.classList.add('visible')), 250);
        } else {
            reveals.forEach(el => el.classList.add('visible'));
        }

        // Toggle pp-in-view on pillar preview blocks so their subtle animations
        // only run while the card is on screen (and pause when scrolled away).
        const pillarPreviews = document.querySelectorAll('.pillar-preview');
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
        const audCards = document.querySelectorAll('.audience-card');
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

        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
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
    });
</script>
@include('common.partials.cookie-consent', ['surface' => 'site'])
@include('common.partials.site-assistant', ['surface' => 'marketing'])
</body>
</html>
