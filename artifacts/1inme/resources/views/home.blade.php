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
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/marketing-anim.css') }}?v=2">
    <script src="{{ asset('js/marketing-anim.js') }}?v=1" defer></script>
    <script>
        try {
            tailwind.config = {
                theme: { extend: { fontFamily: { sans: ['Space Grotesk', 'sans-serif'] } } }
            }
        } catch(e) {}
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
        .aurora b:nth-child(2) { bottom:-15%; right:-10%; width:55vw; height:55vw; background: var(--c1); animation-delay: -8s; }
        .aurora b:nth-child(3) { top:30%; left:40%; width:40vw; height:40vw; background: var(--c3); animation-delay: -14s; }
        .aurora b:nth-child(4) { top:60%; left:5%; width:35vw; height:35vw; background: var(--c4); opacity:.7; animation-delay: -18s; }
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
            background: conic-gradient(from 0deg, var(--c1), var(--c2), var(--c3), var(--c4), var(--c5), var(--c1));
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
            background: linear-gradient(95deg, var(--c1), var(--c2) 30%, var(--c3) 55%, var(--c4) 78%, var(--c5));
            background-size: 200% 100%;
            -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
            animation: gradShift 9s ease-in-out infinite;
        }
        @keyframes gradShift { 0%,100%{ background-position: 0% 50%;} 50%{ background-position: 100% 50%;} }

        /* ============ Logo gradient bar ============ */
        .grad-bar {
            background: linear-gradient(95deg, var(--c1), var(--c2), var(--c3), var(--c4), var(--c5));
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
            background: var(--phone-bg, linear-gradient(140deg,#7c3aed,#e94e8c));
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

        /* ============ Brand logo dark mode ============ */
        .brand-logo--light { display: none; }

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
    </style>
</head>
<body class="overflow-x-hidden">

{{-- ============ Aurora background ============ --}}
<div class="aurora" aria-hidden="true"><b></b><b></b><b></b><b></b></div>

{{-- ============================ NAV ============================ --}}
<div x-data="{ mobileOpen: false, authOpen: false, authTab: 'login' }">
<nav class="fixed top-0 inset-x-0 z-50 bg-[#0a0a14]/80 backdrop-blur-xl border-b border-white/5"
     role="banner">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <a href="{{ route('home') }}" class="inline-flex items-center" aria-label="1INME home">
                @include('common.partials.brand-logo', ['height' => 'h-9'])
            </a>
            <div class="hidden md:flex items-center gap-1 lg:gap-2" role="navigation" aria-label="Primary"
                 x-data="{ openMenu:null }" @click.outside="openMenu=null">
                {{-- Product dropdown --}}
                <div class="relative">
                    <button type="button"
                            @click="openMenu === 'product' ? openMenu=null : openMenu='product'"
                            :aria-expanded="openMenu === 'product'"
                            class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-gray-300 hover:text-white rounded-lg">
                        Product <i class="fas fa-chevron-down text-[10px] opacity-70" :class="openMenu === 'product' ? 'rotate-180' : ''"></i>
                    </button>
                    <div x-show="openMenu === 'product'" x-cloak x-transition.opacity.duration.150ms
                         class="absolute left-0 top-full mt-2 w-72 rounded-2xl border border-white/10 bg-[#11101c] shadow-2xl shadow-black/60 p-2 z-50">
                        <a href="#everything" @click="openMenu=null" class="flex items-start gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5">
                            <i class="fas fa-grip text-violet-400 mt-1"></i>
                            <span><span class="block text-sm font-semibold text-white">Everything you get</span><span class="block text-xs text-gray-500">All four pillars in one place</span></span>
                        </a>
                        <a href="#features" @click="openMenu=null" class="flex items-start gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5">
                            <i class="fas fa-bolt text-violet-400 mt-1"></i>
                            <span><span class="block text-sm font-semibold text-white">Features</span><span class="block text-xs text-gray-500">Build, share &amp; grow</span></span>
                        </a>
                        <a href="#audience" @click="openMenu=null" class="flex items-start gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5">
                            <i class="fas fa-users text-violet-400 mt-1"></i>
                            <span><span class="block text-sm font-semibold text-white">Who it&rsquo;s for</span><span class="block text-xs text-gray-500">Creators, businesses, networking pros</span></span>
                        </a>
                        <a href="#how-it-works" @click="openMenu=null" class="flex items-start gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5">
                            <i class="fas fa-play text-violet-400 mt-1"></i>
                            <span><span class="block text-sm font-semibold text-white">How it works</span><span class="block text-xs text-gray-500">Step-by-step setup</span></span>
                        </a>
                        <a href="{{ route('site.workspace-team') }}" class="flex items-start gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5">
                            <i class="fas fa-people-group text-violet-400 mt-1"></i>
                            <span><span class="block text-sm font-semibold text-white">Workspace &amp; Team</span><span class="block text-xs text-gray-500">Roles, permissions, audit logs</span></span>
                        </a>
                        <a href="{{ route('site.api-docs') }}" class="flex items-start gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5">
                            <i class="fas fa-code text-violet-400 mt-1"></i>
                            <span><span class="block text-sm font-semibold text-white">API</span><span class="block text-xs text-gray-500">Build with 1INME</span></span>
                        </a>
                    </div>
                </div>
                {{-- Solutions dropdown --}}
                <div class="relative">
                    <button type="button"
                            @click="openMenu === 'solutions' ? openMenu=null : openMenu='solutions'"
                            :aria-expanded="openMenu === 'solutions'"
                            class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-gray-300 hover:text-white rounded-lg">
                        Solutions <i class="fas fa-chevron-down text-[10px] opacity-70" :class="openMenu === 'solutions' ? 'rotate-180' : ''"></i>
                    </button>
                    <div x-show="openMenu === 'solutions'" x-cloak x-transition.opacity.duration.150ms
                         class="absolute left-0 top-full mt-2 w-72 rounded-2xl border border-white/10 bg-[#11101c] shadow-2xl shadow-black/60 p-2 z-50">
                        <a href="{{ route('site.services') }}" class="flex items-start gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5">
                            <i class="fas fa-bullseye text-pink-400 mt-1"></i>
                            <span><span class="block text-sm font-semibold text-white">Use cases</span><span class="block text-xs text-gray-500">For creators, brands, agencies &amp; teams</span></span>
                        </a>
                        <a href="{{ route('site.discovery') }}" class="flex items-start gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5">
                            <i class="fas fa-compass text-pink-400 mt-1"></i>
                            <span><span class="block text-sm font-semibold text-white">Discover creators</span><span class="block text-xs text-gray-500">Browse the public directory</span></span>
                        </a>
                        <a href="{{ route('site.creators-feed') }}" class="flex items-start gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5">
                            <i class="fas fa-stream text-pink-400 mt-1"></i>
                            <span><span class="block text-sm font-semibold text-white">Creators feed</span><span class="block text-xs text-gray-500">What the community is shipping</span></span>
                        </a>
                        <a href="{{ route('site.buzz') }}" class="flex items-start gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5">
                            <i class="fas fa-bullhorn text-pink-400 mt-1"></i>
                            <span><span class="block text-sm font-semibold text-white">Buzz</span><span class="block text-xs text-gray-500">News, press &amp; love</span></span>
                        </a>
                    </div>
                </div>
                <a href="#pricing" class="px-3 py-2 text-sm font-medium text-gray-300 hover:text-white">Pricing</a>
                <a href="{{ route('site.about') }}" class="px-3 py-2 text-sm font-medium text-gray-300 hover:text-white">About</a>
                <a href="{{ route('site.contact') }}" class="px-3 py-2 text-sm font-medium text-gray-300 hover:text-white">Contact</a>
            </div>
            <div class="hidden md:flex items-center gap-3" x-data="homeThemeToggle()">
                <button type="button"
                        @click="toggle()"
                        class="theme-btn"
                        :aria-label="light ? 'Switch to dark mode' : 'Switch to light mode'"
                        :title="light ? 'Switch to dark mode' : 'Switch to light mode'">
                    <i class="fa-solid" :class="light ? 'fa-moon' : 'fa-sun'"></i>
                </button>
                @auth
                    <a href="{{ route('user.dashboard') }}" class="btn-bounce px-5 py-2.5 grad-bar text-white rounded-full text-sm font-bold">Dashboard</a>
                @else
                    <button type="button" @click="authTab='login'; authOpen=true" class="px-4 py-2 text-sm font-medium text-gray-300 hover:text-white rounded-full hover:bg-white/5">Log in</button>
                    <button type="button" @click="authTab='register'; authOpen=true" class="btn-bounce btn-glow px-5 py-2.5 grad-bar text-white rounded-full text-sm font-bold shadow-lg shadow-[#7c3aed]/30">Sign up free</button>
                @endauth
            </div>
            <button @click="mobileOpen = !mobileOpen" class="md:hidden p-2 text-gray-300" aria-label="Toggle menu" :aria-expanded="mobileOpen">
                <svg x-show="!mobileOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                <svg x-show="mobileOpen" x-cloak class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div x-show="mobileOpen" x-cloak x-transition class="md:hidden pb-4 border-t border-white/10 mt-2 pt-3 space-y-1">
            <div class="px-3 pt-1 pb-1 text-[10px] font-bold uppercase tracking-wider text-gray-500">Product</div>
            <a href="#everything" @click="mobileOpen=false" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">Everything you get</a>
            <a href="#features" @click="mobileOpen=false" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">Features</a>
            <a href="#audience" @click="mobileOpen=false" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">Who it&rsquo;s for</a>
            <a href="#how-it-works" @click="mobileOpen=false" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">How it works</a>
            <a href="{{ route('site.workspace-team') }}" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">Workspace &amp; Team</a>
            <a href="{{ route('site.api-docs') }}" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">API</a>

            <div class="px-3 pt-3 pb-1 text-[10px] font-bold uppercase tracking-wider text-gray-500">Solutions</div>
            <a href="{{ route('site.services') }}" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">Use cases</a>
            <a href="{{ route('site.discovery') }}" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">Discover creators</a>
            <a href="{{ route('site.creators-feed') }}" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">Creators feed</a>
            <a href="{{ route('site.buzz') }}" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">Buzz</a>

            <div class="px-3 pt-3 pb-1 text-[10px] font-bold uppercase tracking-wider text-gray-500">Company</div>
            <a href="#pricing" @click="mobileOpen=false" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">Pricing</a>
            <a href="{{ route('site.about') }}" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">About</a>
            <a href="{{ route('site.contact') }}" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">Contact</a>
            <a href="{{ route('site.faqs') }}" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">FAQs</a>
            <div class="pt-2 border-t border-white/10 space-y-2">
                @auth
                    <a href="{{ route('user.dashboard') }}" class="block px-4 py-2.5 grad-bar text-white rounded-full text-sm font-bold text-center">Dashboard</a>
                @else
                    <button type="button" @click="authTab='login'; authOpen=true; mobileOpen=false" class="block w-full text-left px-4 py-2 text-sm text-gray-300">Log in</button>
                    <button type="button" @click="authTab='register'; authOpen=true; mobileOpen=false" class="block w-full px-4 py-2.5 grad-bar text-white rounded-full text-sm font-bold text-center">Sign up free</button>
                @endauth
            </div>
        </div>
    </div>
</nav>
@include('public.partials.auth-modal')
</div>

{{-- ============================ HERO ============================ --}}
@php
    // Shared category-tagged thumbnail pool, reused across roles.
    $galleryPool = [
        ['src' => '/images/hero-roles/thumb_youtube.jpg', 'category' => 'Video',   'alt' => 'Latest video'],
        ['src' => '/images/hero-roles/thumb_artwork.jpg', 'category' => 'Art',     'alt' => 'Artwork'],
        ['src' => '/images/hero-roles/thumb_album.jpg',   'category' => 'Music',   'alt' => 'Album cover'],
        ['src' => '/images/hero-roles/thumb_merch.jpg',   'category' => 'Merch',   'alt' => 'Merch drop'],
        ['src' => '/images/hero-roles/thumb_photo.jpg',   'category' => 'Photo',   'alt' => 'Photo print'],
        ['src' => '/images/hero-roles/thumb_podcast.jpg', 'category' => 'Podcast', 'alt' => 'Podcast cover'],
        ['src' => '/images/hero-roles/thumb_writing.jpg', 'category' => 'Writing', 'alt' => 'Latest essay'],
        ['src' => '/images/hero-roles/thumb_food.jpg',    'category' => 'Food',    'alt' => 'Recipe of the week'],
        ['src' => '/images/hero-roles/thumb_fitness.jpg', 'category' => 'Fitness', 'alt' => 'Workout plan'],
        ['src' => '/images/hero-roles/thumb_design.jpg',  'category' => 'Design',  'alt' => 'Design case study'],
        ['src' => '/images/hero-roles/thumb_code.jpg',    'category' => 'Code',    'alt' => 'Open source project'],
        ['src' => '/images/hero-roles/thumb_stream.jpg',  'category' => 'Stream',  'alt' => 'Live stream'],
        ['src' => '/images/hero-roles/thumb_course.jpg',  'category' => 'Course',  'alt' => 'Online course'],
        ['src' => '/images/hero-roles/thumb_book.jpg',    'category' => 'Book',    'alt' => 'Latest book'],
        ['src' => '/images/hero-roles/thumb_travel.jpg',  'category' => 'Travel',  'alt' => 'Travel guide'],
    ];

    $heroRoles = [
        [
            'word' => 'Creator',
            'theme' => 'creator',
            'wallpaper' => 'linear-gradient(140deg,#7c3aed 0%,#e94e8c 60%,#ff8a3c 100%)',
            'tint' => '#7c3aed',
            'categories' => ['Video','Merch','Photo','Music','Art','Podcast'],
            'gallery' => $galleryPool,
            'profile' => ['avatar' => '/images/hero-roles/role_creator.jpg', 'handle' => '@jamie.creates', 'tag' => 'Storyteller · 24.1k followers', 'socials' => ['fa-youtube','fa-instagram','fa-tiktok','fa-x-twitter']],
            'blocks' => [
                ['icon' => 'fas fa-video',             'color' => '#ff5252', 'title' => 'Latest video',       'sub' => 'New drop · 2 days ago',   'thumb' => '/images/hero-roles/thumb_youtube.jpg'],
                ['icon' => 'fas fa-envelope-open-text','color' => '#7c3aed', 'title' => 'Join the newsletter','sub' => 'Weekly · 12k subs'],
                ['icon' => 'fas fa-store',             'color' => '#ff8a3c', 'title' => 'Shop merch',         'sub' => 'New tees in stock',       'thumb' => '/images/hero-roles/thumb_merch.jpg'],
            ],
        ],
        [
            'word' => 'Artist',
            'theme' => 'gallery',
            'wallpaper' => 'linear-gradient(140deg,#e94e8c 0%,#ff8a3c 55%,#ffc845 100%)',
            'tint' => '#e94e8c',
            'categories' => ['Art','Photo','Merch','Music','Video','Podcast'],
            'gallery' => $galleryPool,
            'profile' => ['avatar' => '/images/hero-roles/role_artist.jpg', 'handle' => '@aria.studio', 'tag' => 'Mixed-media artist · Berlin', 'socials' => ['fa-instagram','fa-pinterest','fa-behance','fa-tiktok']],
            'blocks' => [
                ['icon' => 'fas fa-images',             'color' => '#e94e8c', 'title' => 'Latest collection', 'sub' => 'Petals & Concrete · 12 pcs', 'thumb' => '/images/hero-roles/thumb_artwork.jpg'],
                ['icon' => 'fab fa-spotify',            'color' => '#1ed760', 'title' => 'Studio playlist',   'sub' => '4hr ambient mix'],
                ['icon' => 'fas fa-hand-holding-heart', 'color' => '#ff8a3c', 'title' => 'Tip jar',           'sub' => 'Buy me a coffee'],
            ],
        ],
        [
            'word' => 'Businessman',
            'theme' => 'business',
            'wallpaper' => 'linear-gradient(140deg,#0f172a 0%,#1bd4d9 60%,#7c3aed 100%)',
            'tint' => '#1bd4d9',
            'categories' => ['Photo','Video','Podcast','Merch','Art','Music'],
            'gallery' => $galleryPool,
            'profile' => ['avatar' => '/images/hero-roles/role_business.jpg', 'handle' => '@marcus.solutions', 'tag' => 'Founder · B2B Consulting', 'socials' => ['fa-linkedin','fa-x-twitter','fa-medium','fa-youtube']],
            'blocks' => [
                ['icon' => 'fas fa-concierge-bell', 'color' => '#7c3aed', 'title' => 'Services & pricing', 'sub' => 'Strategy · Audits · Retainers'],
                ['icon' => 'fas fa-calendar-check', 'color' => '#1bd4d9', 'title' => 'Book a call',        'sub' => '30 min · Calendly'],
                ['icon' => 'fas fa-paper-plane',    'color' => '#ff8a3c', 'title' => 'Get a quote',        'sub' => 'Reply within 24h'],
            ],
        ],
        [
            'word' => 'Musician',
            'theme' => 'music',
            'wallpaper' => 'linear-gradient(140deg,#0f3a2a 0%,#1ed760 55%,#1bd4d9 100%)',
            'tint' => '#1ed760',
            'categories' => ['Music','Merch','Video','Podcast','Art','Photo'],
            'gallery' => $galleryPool,
            'profile' => ['avatar' => '/images/hero-roles/role_musician.jpg', 'handle' => '@luna.live', 'tag' => 'Indie pop · New EP out now', 'socials' => ['fa-spotify','fa-apple','fa-youtube','fa-instagram']],
            'blocks' => [
                ['icon' => 'fab fa-spotify',     'color' => '#1ed760', 'title' => 'New EP — Saltwater', 'sub' => '5 tracks · Listen now', 'thumb' => '/images/hero-roles/thumb_album.jpg'],
                ['icon' => 'fas fa-ticket-alt',  'color' => '#e94e8c', 'title' => 'Tour 2026',          'sub' => '12 cities · Tickets live'],
                ['icon' => 'fas fa-store',       'color' => '#ffc845', 'title' => 'Vinyl & tees',       'sub' => 'Limited drop',          'thumb' => '/images/hero-roles/thumb_merch.jpg'],
            ],
        ],
        [
            'word' => 'Coach',
            'theme' => 'coach',
            'wallpaper' => 'linear-gradient(140deg,#1bd4d9 0%,#7c3aed 60%,#ffc845 100%)',
            'tint' => '#1bd4d9',
            'categories' => ['Video','Photo','Podcast','Music','Merch','Art'],
            'gallery' => $galleryPool,
            'profile' => ['avatar' => '/images/hero-roles/role_coach.jpg', 'handle' => '@coach.kai', 'tag' => 'Strength coach · 1:1 + group', 'socials' => ['fa-instagram','fa-tiktok','fa-youtube','fa-spotify']],
            'blocks' => [
                ['icon' => 'fas fa-calendar-check',  'color' => '#1bd4d9', 'title' => 'Book a session',     'sub' => '45 min consult'],
                ['icon' => 'fas fa-quote-right',     'color' => '#7c3aed', 'title' => 'Wins from clients',  'sub' => '140+ five-star reviews'],
                ['icon' => 'fas fa-clipboard-list',  'color' => '#ff8a3c', 'title' => 'Free intake form',   'sub' => '2 minutes · No fluff'],
            ],
        ],
        [
            'word' => 'Photographer',
            'theme' => 'portfolio',
            'wallpaper' => 'linear-gradient(140deg,#0a2540 0%,#1bd4d9 55%,#7c3aed 100%)',
            'tint' => '#1bd4d9',
            'categories' => ['Photo','Art','Merch','Video','Music','Podcast'],
            'gallery' => $galleryPool,
            'profile' => ['avatar' => '/images/hero-roles/role_photographer.jpg', 'handle' => '@iris.frames', 'tag' => 'Travel & landscape · Iceland', 'socials' => ['fa-instagram','fa-pinterest','fa-flickr','fa-x-twitter']],
            'blocks' => [
                ['icon' => 'fas fa-th',            'color' => '#1bd4d9', 'title' => 'Portfolio · 2026', 'sub' => '48 photos',         'thumb' => '/images/hero-roles/thumb_photo.jpg'],
                ['icon' => 'fas fa-shopping-bag',  'color' => '#ff8a3c', 'title' => 'Print shop',       'sub' => 'A2 / A3 / canvas'],
                ['icon' => 'fas fa-paper-plane',   'color' => '#e94e8c', 'title' => 'Hire me',          'sub' => 'Weddings · Brand'],
            ],
        ],
        [
            'word' => 'Influencer',
            'theme' => 'social',
            'wallpaper' => 'linear-gradient(140deg,#e94e8c 0%,#7c3aed 50%,#ffc845 100%)',
            'tint' => '#e94e8c',
            'categories' => ['Video','Photo','Merch','Music','Art','Podcast'],
            'gallery' => $galleryPool,
            'profile' => ['avatar' => '/images/hero-roles/role_influencer.jpg', 'handle' => '@maya.daily', 'tag' => 'Lifestyle · 480k across socials', 'socials' => ['fa-instagram','fa-tiktok','fa-youtube','fa-snapchat']],
            'blocks' => [
                ['icon' => 'fab fa-instagram', 'color' => '#e94e8c', 'title' => 'Latest reel',     'sub' => 'Spring haul'],
                ['icon' => 'fab fa-tiktok',    'color' => '#1bd4d9', 'title' => 'Trending today',  'sub' => '2.1M views'],
                ['icon' => 'fas fa-handshake', 'color' => '#ffc845', 'title' => 'Brand deals',     'sub' => 'Press kit · Rates'],
            ],
        ],
        [
            'word' => 'Podcaster',
            'theme' => 'podcast',
            'wallpaper' => 'linear-gradient(140deg,#ff8a3c 0%,#e94e8c 50%,#7c3aed 100%)',
            'tint' => '#ff8a3c',
            'categories' => ['Podcast','Music','Video','Art','Merch','Photo'],
            'gallery' => $galleryPool,
            'profile' => ['avatar' => '/images/hero-roles/role_podcaster.jpg', 'handle' => '@theo.talks', 'tag' => 'Weekly tech & culture', 'socials' => ['fa-spotify','fa-apple','fa-youtube','fa-x-twitter']],
            'blocks' => [
                ['icon' => 'fab fa-apple',              'color' => '#ffffff', 'title' => 'Apple Podcasts',  'sub' => 'Ep. 87 · 42 min',     'thumb' => '/images/hero-roles/thumb_podcast.jpg'],
                ['icon' => 'fab fa-spotify',            'color' => '#1ed760', 'title' => 'Spotify',         'sub' => 'Subscribe · 18k listeners'],
                ['icon' => 'fas fa-envelope-open-text', 'color' => '#ff8a3c', 'title' => 'Show notes',      'sub' => 'Newsletter every Friday'],
            ],
        ],
        [
            'word' => 'Writer',
            'theme' => 'creator',
            'wallpaper' => 'linear-gradient(140deg,#1e1b4b 0%,#7c3aed 55%,#ec4899 100%)',
            'tint' => '#a855f7',
            'categories' => ['Writing','Book','Podcast','Video','Art','Photo'],
            'gallery' => $galleryPool,
            'profile' => ['avatar' => '/images/hero-roles/role_writer.jpg', 'handle' => '@nora.writes', 'tag' => 'Essayist · Substack 18k', 'socials' => ['fa-substack','fa-medium','fa-x-twitter','fa-instagram']],
            'blocks' => [
                ['icon' => 'fas fa-feather',            'color' => '#a855f7', 'title' => 'New essay',         'sub' => 'On slow internet · 12 min', 'thumb' => '/images/hero-roles/thumb_writing.jpg'],
                ['icon' => 'fas fa-envelope-open-text', 'color' => '#7c3aed', 'title' => 'Subscribe free',    'sub' => 'Weekly long reads'],
                ['icon' => 'fas fa-book-open',          'color' => '#ffc845', 'title' => 'Buy the book',      'sub' => 'Quiet Signals · paperback', 'thumb' => '/images/hero-roles/thumb_book.jpg'],
            ],
        ],
        [
            'word' => 'Chef',
            'theme' => 'creator',
            'wallpaper' => 'linear-gradient(140deg,#7c2d12 0%,#fb923c 55%,#fde047 100%)',
            'tint' => '#fb923c',
            'categories' => ['Food','Video','Photo','Course','Merch','Podcast'],
            'gallery' => $galleryPool,
            'profile' => ['avatar' => '/images/hero-roles/role_chef.jpg', 'handle' => '@chef.remi', 'tag' => 'Recipes · Pop-ups · Cookbook', 'socials' => ['fa-youtube','fa-instagram','fa-tiktok','fa-pinterest']],
            'blocks' => [
                ['icon' => 'fas fa-utensils',           'color' => '#fb923c', 'title' => 'Recipe of the week', 'sub' => '20-min weeknight pasta',  'thumb' => '/images/hero-roles/thumb_food.jpg'],
                ['icon' => 'fas fa-graduation-cap',     'color' => '#7c3aed', 'title' => 'Knife skills course','sub' => '6 lessons · self-paced',  'thumb' => '/images/hero-roles/thumb_course.jpg'],
                ['icon' => 'fas fa-store',              'color' => '#ff8a3c', 'title' => 'Shop the spice kit', 'sub' => 'Limited drop',           'thumb' => '/images/hero-roles/thumb_merch.jpg'],
            ],
        ],
        [
            'word' => 'Yogi',
            'theme' => 'coach',
            'wallpaper' => 'linear-gradient(140deg,#064e3b 0%,#10b981 55%,#fde047 100%)',
            'tint' => '#10b981',
            'categories' => ['Fitness','Video','Course','Podcast','Photo','Music'],
            'gallery' => $galleryPool,
            'profile' => ['avatar' => '/images/hero-roles/role_fitness.jpg', 'handle' => '@yoga.with.sage', 'tag' => 'Yoga & breathwork · Online + Bali', 'socials' => ['fa-youtube','fa-instagram','fa-spotify','fa-tiktok']],
            'blocks' => [
                ['icon' => 'fas fa-dumbbell',           'color' => '#10b981', 'title' => '30-day flow',        'sub' => 'Daily 20-min sessions',   'thumb' => '/images/hero-roles/thumb_fitness.jpg'],
                ['icon' => 'fas fa-calendar-check',     'color' => '#1bd4d9', 'title' => 'Book a 1:1',         'sub' => '60 min · Zoom or Bali'],
                ['icon' => 'fas fa-graduation-cap',     'color' => '#7c3aed', 'title' => 'Teacher training',   'sub' => '200hr · Cohort 6 open',   'thumb' => '/images/hero-roles/thumb_course.jpg'],
            ],
        ],
        [
            'word' => 'Designer',
            'theme' => 'gallery',
            'wallpaper' => 'linear-gradient(140deg,#312e81 0%,#ec4899 55%,#fbbf24 100%)',
            'tint' => '#ec4899',
            'categories' => ['Design','Art','Photo','Merch','Video','Course'],
            'gallery' => $galleryPool,
            'profile' => ['avatar' => '/images/hero-roles/role_designer.jpg', 'handle' => '@studio.kova', 'tag' => 'Brand & product designer · Lisbon', 'socials' => ['fa-dribbble','fa-behance','fa-instagram','fa-linkedin']],
            'blocks' => [
                ['icon' => 'fas fa-pen-ruler',          'color' => '#ec4899', 'title' => 'Selected work',     'sub' => '14 case studies',         'thumb' => '/images/hero-roles/thumb_design.jpg'],
                ['icon' => 'fas fa-paper-plane',        'color' => '#7c3aed', 'title' => 'Hire the studio',   'sub' => 'Brand · Web · Product'],
                ['icon' => 'fas fa-store',              'color' => '#ffc845', 'title' => 'Template shop',     'sub' => 'Figma kits · ready to ship'],
            ],
        ],
        [
            'word' => 'Developer',
            'theme' => 'creator',
            'wallpaper' => 'linear-gradient(140deg,#0f172a 0%,#1bd4d9 55%,#7c3aed 100%)',
            'tint' => '#1bd4d9',
            'categories' => ['Code','Video','Course','Podcast','Writing','Design'],
            'gallery' => $galleryPool,
            'profile' => ['avatar' => '/images/hero-roles/role_developer.jpg', 'handle' => '@dev.with.kai', 'tag' => 'Open source · Indie hacker', 'socials' => ['fa-github','fa-x-twitter','fa-youtube','fa-linkedin']],
            'blocks' => [
                ['icon' => 'fas fa-code',               'color' => '#ffffff', 'title' => 'Open source',       'sub' => '8.4k ★ · TypeScript',     'thumb' => '/images/hero-roles/thumb_code.jpg'],
                ['icon' => 'fas fa-graduation-cap',     'color' => '#1bd4d9', 'title' => 'Build with me',     'sub' => 'Course · 24 lessons',     'thumb' => '/images/hero-roles/thumb_course.jpg'],
                ['icon' => 'fas fa-feather',            'color' => '#7c3aed', 'title' => 'Engineering blog',  'sub' => 'Weekly deep dives',       'thumb' => '/images/hero-roles/thumb_writing.jpg'],
            ],
        ],
        [
            'word' => 'Streamer',
            'theme' => 'social',
            'wallpaper' => 'linear-gradient(140deg,#3b0764 0%,#a855f7 55%,#ec4899 100%)',
            'tint' => '#a855f7',
            'categories' => ['Stream','Video','Merch','Music','Podcast','Photo'],
            'gallery' => $galleryPool,
            'profile' => ['avatar' => '/images/hero-roles/role_streamer.jpg', 'handle' => '@nyx.plays', 'tag' => 'Variety streamer · 92k Twitch', 'socials' => ['fa-twitch','fa-youtube','fa-discord','fa-x-twitter']],
            'blocks' => [
                ['icon' => 'fab fa-twitch',             'color' => '#a855f7', 'title' => 'Live now',          'sub' => 'Speedrun night · 1.2k watching', 'thumb' => '/images/hero-roles/thumb_stream.jpg'],
                ['icon' => 'fab fa-discord',            'color' => '#5865f2', 'title' => 'Join the Discord',  'sub' => '14k members'],
                ['icon' => 'fas fa-store',              'color' => '#ffc845', 'title' => 'Merch · Hoodies',   'sub' => 'New season drop',         'thumb' => '/images/hero-roles/thumb_merch.jpg'],
            ],
        ],
        [
            'word' => 'Educator',
            'theme' => 'coach',
            'wallpaper' => 'linear-gradient(140deg,#0c4a6e 0%,#38bdf8 55%,#7c3aed 100%)',
            'tint' => '#38bdf8',
            'categories' => ['Course','Video','Writing','Podcast','Book','Photo'],
            'gallery' => $galleryPool,
            'profile' => ['avatar' => '/images/hero-roles/role_educator.jpg', 'handle' => '@ms.alvarez', 'tag' => 'Tutor · SAT · Calculus · 1:1', 'socials' => ['fa-youtube','fa-instagram','fa-tiktok','fa-linkedin']],
            'blocks' => [
                ['icon' => 'fas fa-graduation-cap',     'color' => '#38bdf8', 'title' => 'Live cohort',       'sub' => 'Spring intake open',      'thumb' => '/images/hero-roles/thumb_course.jpg'],
                ['icon' => 'fas fa-calendar-check',     'color' => '#7c3aed', 'title' => 'Book a session',    'sub' => '50 min · Zoom'],
                ['icon' => 'fas fa-clipboard-list',     'color' => '#ff8a3c', 'title' => 'Free practice pack','sub' => 'PDFs · Drills · Keys'],
            ],
        ],
        [
            'word' => 'Author',
            'theme' => 'gallery',
            'wallpaper' => 'linear-gradient(140deg,#451a03 0%,#f59e0b 55%,#ec4899 100%)',
            'tint' => '#f59e0b',
            'categories' => ['Book','Writing','Podcast','Video','Photo','Art'],
            'gallery' => $galleryPool,
            'profile' => ['avatar' => '/images/hero-roles/role_author.jpg', 'handle' => '@iain.morrow', 'tag' => 'Novelist · Quiet Signals out now', 'socials' => ['fa-goodreads','fa-instagram','fa-x-twitter','fa-medium']],
            'blocks' => [
                ['icon' => 'fas fa-book-open',          'color' => '#f59e0b', 'title' => 'Buy Quiet Signals', 'sub' => 'Hardcover · audiobook',   'thumb' => '/images/hero-roles/thumb_book.jpg'],
                ['icon' => 'fas fa-feather',            'color' => '#7c3aed', 'title' => 'Read a chapter',    'sub' => 'Free preview'],
                ['icon' => 'fas fa-calendar-check',     'color' => '#1bd4d9', 'title' => 'Tour & signings',   'sub' => '8 cities · Spring'],
            ],
        ],
        [
            'word' => 'Nonprofit',
            'theme' => 'business',
            'wallpaper' => 'linear-gradient(140deg,#064e3b 0%,#22c55e 55%,#1bd4d9 100%)',
            'tint' => '#22c55e',
            'categories' => ['Video','Photo','Writing','Podcast','Merch','Music'],
            'gallery' => $galleryPool,
            'profile' => ['avatar' => '/images/hero-roles/role_nonprofit.jpg', 'handle' => '@cleanwave.org', 'tag' => 'Ocean cleanup · 501(c)(3)', 'socials' => ['fa-instagram','fa-youtube','fa-linkedin','fa-x-twitter']],
            'blocks' => [
                ['icon' => 'fas fa-hand-holding-heart', 'color' => '#22c55e', 'title' => 'Donate today',      'sub' => 'Every $5 = 20 lbs cleaned'],
                ['icon' => 'fas fa-people-group',       'color' => '#1bd4d9', 'title' => 'Volunteer',         'sub' => 'Beach cleanups · monthly'],
                ['icon' => 'fas fa-chart-line',         'color' => '#7c3aed', 'title' => 'Impact report',     'sub' => '2025 · 8.4M lbs removed'],
            ],
        ],
        [
            'word' => 'Realtor',
            'theme' => 'business',
            'wallpaper' => 'linear-gradient(140deg,#0a2540 0%,#1bd4d9 55%,#ffc845 100%)',
            'tint' => '#1bd4d9',
            'categories' => ['Photo','Video','Writing','Podcast','Art','Merch'],
            'gallery' => $galleryPool,
            'profile' => ['avatar' => '/images/hero-roles/role_realtor.jpg', 'handle' => '@home.with.eli', 'tag' => 'Realtor® · Austin TX · 9 yrs', 'socials' => ['fa-instagram','fa-youtube','fa-linkedin','fa-tiktok']],
            'blocks' => [
                ['icon' => 'fas fa-house',              'color' => '#1bd4d9', 'title' => 'Featured listings', 'sub' => '12 active · Austin metro', 'thumb' => '/images/hero-roles/thumb_photo.jpg'],
                ['icon' => 'fas fa-calendar-check',     'color' => '#7c3aed', 'title' => 'Book a tour',       'sub' => 'In-person or virtual'],
                ['icon' => 'fas fa-calculator',         'color' => '#ff8a3c', 'title' => 'Free home valuation','sub' => '60-second estimate'],
            ],
        ],
        [
            'word' => 'Traveler',
            'theme' => 'social',
            'wallpaper' => 'linear-gradient(140deg,#0c4a6e 0%,#06b6d4 55%,#fde047 100%)',
            'tint' => '#06b6d4',
            'categories' => ['Travel','Photo','Video','Writing','Podcast','Course'],
            'gallery' => $galleryPool,
            'profile' => ['avatar' => '/images/hero-roles/role_travel.jpg', 'handle' => '@wander.with.io', 'tag' => 'Travel · 38 countries · Maps & guides', 'socials' => ['fa-instagram','fa-youtube','fa-tiktok','fa-pinterest']],
            'blocks' => [
                ['icon' => 'fas fa-plane',              'color' => '#06b6d4', 'title' => 'City guides',       'sub' => '12 cities · live maps',   'thumb' => '/images/hero-roles/thumb_travel.jpg'],
                ['icon' => 'fas fa-camera',             'color' => '#ec4899', 'title' => 'Lightroom presets', 'sub' => 'Sun-soaked · Misty pack'],
                ['icon' => 'fas fa-envelope-open-text', 'color' => '#ffc845', 'title' => 'Trip newsletter',   'sub' => 'Monthly · 24k readers'],
            ],
        ],
    ];

    // Visible block-type icons cluster shown in the hero.
    $heroBlockIcons = [
        ['i' => 'fas fa-store',              'c' => '#ff8a3c', 'l' => 'Merch'],
        ['i' => 'fas fa-link',               'c' => '#1bd4d9', 'l' => 'Link'],
        ['i' => 'fas fa-qrcode',             'c' => '#7c3aed', 'l' => 'QR'],
        ['i' => 'fas fa-music',              'c' => '#e94e8c', 'l' => 'Music'],
        ['i' => 'fas fa-video',              'c' => '#ffc845', 'l' => 'Video'],
        ['i' => 'fas fa-image',              'c' => '#1bd4d9', 'l' => 'Image'],
        ['i' => 'fas fa-microphone',         'c' => '#ff8a3c', 'l' => 'Podcast'],
        ['i' => 'fas fa-calendar-check',     'c' => '#7c3aed', 'l' => 'Calendar'],
        ['i' => 'fas fa-book-open',          'c' => '#f59e0b', 'l' => 'Book'],
        ['i' => 'fas fa-graduation-cap',     'c' => '#38bdf8', 'l' => 'Course'],
        ['i' => 'fas fa-utensils',           'c' => '#fb923c', 'l' => 'Recipe'],
        ['i' => 'fas fa-feather',            'c' => '#a855f7', 'l' => 'Writing'],
        ['i' => 'fas fa-code',               'c' => '#ffffff', 'l' => 'Code'],
        ['i' => 'fas fa-dumbbell',           'c' => '#10b981', 'l' => 'Fitness'],
        ['i' => 'fas fa-plane',              'c' => '#06b6d4', 'l' => 'Travel'],
        ['i' => 'fas fa-house',              'c' => '#1bd4d9', 'l' => 'Listing'],
        ['i' => 'fas fa-hand-holding-heart', 'c' => '#22c55e', 'l' => 'Donate'],
    ];
@endphp

<section class="relative pt-28 pb-20 lg:pt-44 lg:pb-32 xl:pt-52 xl:pb-40 overflow-hidden" aria-labelledby="hero-h">
    {{-- Drifting confetti --}}
    <div class="confetti drift-a" style="left:8%;  bottom:-20vh;"><div class="w-3 h-3 rounded-sm" style="background:var(--c1)"></div></div>
    <div class="confetti drift-b" style="left:18%; bottom:-30vh; animation-delay:-3s"><div class="w-2 h-6 rounded-full" style="background:var(--c3)"></div></div>
    <div class="confetti drift-a" style="left:78%; bottom:-25vh; animation-delay:-6s"><div class="w-4 h-4 rounded-full" style="background:var(--c4)"></div></div>
    <div class="confetti drift-b" style="left:88%; bottom:-15vh; animation-delay:-9s"><div class="w-3 h-3 rotate-45" style="background:var(--c5)"></div></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-10 xl:px-12">
        <div class="hero-grid grid grid-cols-1 gap-y-12 lg:grid-cols-[1.05fr_1fr] lg:gap-x-12 xl:gap-x-16 lg:items-center">
            <div class="text-center lg:text-left lg:max-w-[600px]">
                <div class="reveal inline-flex items-center gap-2 px-4 py-1.5 glass rounded-full text-xs font-semibold mb-8">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full rounded-full" style="background:var(--c1)"></span>
                        <span class="ring-pulse" style="inset:0;background:var(--c1);"></span>
                    </span>
                    <span class="grad-text">All-in-one growth stack · Free Forever · Native mobile app</span>
                </div>

                <h1 id="hero-h" class="reveal rd-1 text-5xl sm:text-6xl lg:text-7xl font-bold leading-[1.05] tracking-tight mb-8">
                    <span class="block">I am a</span>
                    <span class="relative inline-block min-h-[1.1em]">
                        <span id="hero-role-word" class="grad-text role-word">Creator</span>
                        <svg class="absolute -bottom-3 left-0 w-full" height="14" viewBox="0 0 220 14" preserveAspectRatio="none" aria-hidden="true">
                            <path class="draw-line" d="M2 9 Q 60 2, 110 8 T 218 6" stroke="url(#g)" stroke-width="5" fill="none" stroke-linecap="round"/>
                            <defs><linearGradient id="g"><stop offset="0%" stop-color="#1bd4d9"/><stop offset="50%" stop-color="#7c3aed"/><stop offset="100%" stop-color="#ffc845"/></linearGradient></defs>
                        </svg>
                    </span>
                    <span class="sr-only" aria-live="polite" aria-atomic="true" id="hero-role-sr">Creator</span>
                </h1>

                <p class="reveal rd-2 text-lg sm:text-xl text-gray-400 max-w-xl mx-auto lg:mx-0 mb-10 leading-relaxed">
                    Whoever you are, 1INME is the <strong class="text-white">all-in-one</strong> link, monetization &amp; growth stack: drag-and-drop biolink pages, branded short links, dynamic QR codes, NFC tags, built-in DMs, an AI Performance Coach and a native mobile app — <strong class="text-white">free forever</strong>, no card required.
                </p>

                <div class="reveal rd-3 flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-5 justify-center lg:justify-start">
                    <button type="button" @click="authTab='register'; authOpen=true" class="btn-bounce btn-glow inline-flex items-center justify-center gap-2 px-8 py-4 grad-bar text-white rounded-full text-base font-bold whitespace-nowrap shrink-0">
                        Make mine free <i class="fas fa-arrow-right text-sm"></i>
                    </button>
                    <a href="#features" class="inline-flex items-center justify-center gap-1.5 text-sm font-semibold text-gray-300 hover:text-white">
                        See it live <i class="fas fa-arrow-right text-[11px]"></i>
                    </a>
                </div>

                @php
                    $__trustStripRaw = (array) \App\Modules\Admin\Models\AppSetting::get('marketing_trust_strip', []);
                    $__trustStrip = \App\Modules\Common\Support\SitePagesContent::normalizeTrustStrip($__trustStripRaw);
                    if (empty($__trustStrip)) {
                        $__trustStrip = \App\Modules\Common\Support\SitePagesContent::trustStripDefault();
                    }
                    $__trustColors = ['var(--c1)', 'var(--c3)', 'var(--c5)', 'var(--c2)', 'var(--c4)'];
                @endphp
                <div class="reveal rd-4 flex flex-wrap items-center gap-x-6 gap-y-3 mt-12 justify-center lg:justify-start text-sm">
                    @foreach($__trustStrip as $i => $__t)
                        <span class="flex items-center gap-2 text-gray-400">
                            <i class="fas {{ $__t['icon'] ?? 'fa-check' }} text-[13px]" style="color: {{ $__trustColors[$i % count($__trustColors)] }}"></i>
                            <span class="font-bold text-white">{{ $__t['value'] ?? '' }}</span>
                            <span class="text-gray-500">{{ $__t['label'] ?? '' }}</span>
                        </span>
                    @endforeach
                </div>
            </div>

            {{-- Hero phone mockup + gallery + block icons --}}
            <div class="reveal rd-2 relative stack-scene lg:justify-self-end w-full max-w-[560px] mx-auto" id="hero-phone-scene">
                {{-- Decorative stickers (kept inside the phone column on lg+ so they don't float into the headline area) --}}
                <div class="sticker hidden lg:block top-4 right-6 w-10 h-10 rounded-full wiggle shake-hover opacity-80" style="background:var(--c4)"></div>
                <div class="sticker top-12 right-2 w-8 h-8 rounded-lg spin-slow opacity-70" style="background:var(--c5)"></div>
                <div class="sticker hidden lg:block bottom-32 right-0 w-9 h-9 rounded-2xl wiggle opacity-80" style="background:var(--c1); animation-delay:-1s"></div>
                <div class="sticker top-1/3 -right-3 w-6 h-6 rounded-full wiggle opacity-80" style="background:var(--c3); animation-delay:-2s"></div>

                {{-- Phone mockup --}}
                <div class="relative flex items-center justify-center hero-phone-stage">
                    <div class="hero-phone-frame">
                    <div id="hero-phone-wrap" class="hero-phone-wrap float-c">
                        <div class="hero-phone">
                            <div id="hero-phone-screen" class="hero-phone-screen">
                                <div class="hero-notch"></div>
                                <div id="hero-stack" class="hero-phone-content" aria-hidden="true"></div>
                            </div>
                        </div>
                    </div>

                    {{-- Floating info cards (desktop only) --}}
                    <div class="float-b float-card float-card--visitors hidden lg:block" aria-hidden="true">
                        <div class="flex items-center justify-between mb-1">
                            <span class="float-card-label">Live visitors</span>
                            <span class="flex items-center gap-1 text-[9px] font-bold" style="color:var(--c1)"><span class="w-1.5 h-1.5 rounded-full pulse-dot" style="background:var(--c1)"></span>NOW</span>
                        </div>
                        <div class="text-xl font-bold" id="hero-tick-visitors" data-tick-visitors>247</div>
                        <svg class="w-full h-6" viewBox="0 0 100 30" preserveAspectRatio="none">
                            <polyline class="spark-line" fill="none" stroke="url(#sl)" stroke-width="2.5" stroke-linecap="round" points="0,22 12,18 24,20 36,12 48,15 60,8 72,11 84,5 100,7"/>
                            <defs><linearGradient id="sl"><stop offset="0%" stop-color="#1bd4d9"/><stop offset="100%" stop-color="#e94e8c"/></linearGradient></defs>
                        </svg>
                    </div>

                    <div class="float-c float-card float-card--coach hidden lg:block" style="animation-delay:-2s" aria-hidden="true">
                        <div class="flex items-center gap-2 mb-1.5">
                            <div class="w-8 h-8 rounded-xl flex items-center justify-center grad-bar"><i class="fas fa-bolt text-white text-xs"></i></div>
                            <div>
                                <span class="float-card-label">Performance Coach</span>
                                <div class="text-xs font-bold">Health score 87</div>
                            </div>
                        </div>
                        <div class="h-1.5 bg-white/10 rounded-full overflow-hidden">
                            <div class="h-full grad-bar rounded-full" style="width:87%"></div>
                        </div>
                    </div>

                    <div class="float-a float-card float-card--toplink hidden lg:block" style="animation-delay:-1s" aria-hidden="true">
                        <div class="flex items-center justify-between mb-1">
                            <span class="float-card-label">Top link</span>
                            <span class="text-[9px] font-bold" style="color:#1ed760">+18%</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-lg flex items-center justify-center" style="background:rgba(124,58,237,.2);color:#a78bfa"><i class="fas fa-link text-xs"></i></div>
                            <div class="min-w-0">
                                <div class="text-[11px] font-bold truncate">Latest drop</div>
                                <div class="text-[9px] text-gray-400">1,284 clicks</div>
                            </div>
                        </div>
                    </div>

                    <div class="float-b float-card float-card--conv hidden lg:block" style="animation-delay:-3.5s" aria-hidden="true">
                        <span class="float-card-label">Conversions today</span>
                        <div class="flex items-baseline gap-2 mt-0.5">
                            <div class="text-xl font-bold">38</div>
                            <span class="text-[10px] font-bold" style="color:#1ed760">+12%</span>
                        </div>
                        <div class="flex items-end gap-0.5 h-5 mt-1">
                            @foreach([6,9,5,11,8,14,10,16,13,18] as $h)
                                <span class="flex-1 rounded-sm" style="height:{{ $h * 5 }}%;background:linear-gradient(180deg,#1bd4d9,#7c3aed)"></span>
                            @endforeach
                        </div>
                    </div>

                    <div class="float-c float-card float-card--qr hidden lg:block" style="animation-delay:-1.5s" aria-hidden="true">
                        <div class="flex items-center gap-2">
                            <div class="w-9 h-9 rounded-lg flex items-center justify-center" style="background:rgba(124,58,237,.2);color:#a78bfa"><i class="fas fa-qrcode text-base"></i></div>
                            <div>
                                <span class="float-card-label">QR scans</span>
                                <div class="text-sm font-bold leading-tight">1,420 <span class="text-[10px] text-gray-400 font-normal">/ 7d</span></div>
                            </div>
                        </div>
                    </div>

                    <div class="float-a float-card float-card--follower hidden lg:block" style="animation-delay:-2.5s" aria-hidden="true">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-[11px] font-bold" style="background:linear-gradient(135deg,#ec4899,#7c3aed)">M</div>
                            <div class="min-w-0">
                                <span class="float-card-label">New follower</span>
                                <div class="text-[11px] font-bold truncate">@maya.daily</div>
                                <div class="text-[9px] text-gray-400">just now</div>
                            </div>
                        </div>
                    </div>

                    <div class="float-b float-card float-card--revenue hidden lg:block" style="animation-delay:-4s" aria-hidden="true">
                        <span class="float-card-label">Revenue today</span>
                        <div class="flex items-baseline gap-2 mt-0.5">
                            <div class="text-xl font-bold" id="hero-tick-revenue" data-tick-revenue>$ 412</div>
                            <span class="text-[10px] font-bold" style="color:#1ed760">▲ 9%</span>
                        </div>
                        <div class="flex items-center gap-1 mt-1 text-[9px] text-gray-400">
                            <i class="fas fa-store" style="color:#ff8a3c"></i> 6 orders · 2 tips
                        </div>
                    </div>
                    </div>{{-- /hero-phone-frame --}}
                </div>

                {{-- Compact horizontal interactive tile strip (all breakpoints) --}}
                <div class="mt-6">
                    <div class="hero-rail-label text-[10px] font-bold uppercase tracking-[.18em] text-gray-400 text-center lg:text-left mb-2 px-1">
                        Looks like a <span id="hero-rail-role-label" class="grad-text">creator</span> page
                    </div>
                    <div id="hero-tile-rail" class="hero-tile-rail" role="group" aria-label="Choose a profile preview"></div>
                </div>

                {{-- Mobile-only stacked stats row (replaces floating cards on small screens) --}}
                <div class="hero-mobile-stats lg:hidden mt-5" aria-hidden="true">
                    <div class="hero-mstat">
                        <span class="lbl"><span class="w-1.5 h-1.5 rounded-full pulse-dot inline-block mr-1" style="background:var(--c1)"></span>Live</span>
                        <span class="val">247</span>
                        <span class="sub">visitors</span>
                    </div>
                    <div class="hero-mstat">
                        <span class="lbl"><i class="fas fa-bolt" style="color:#ffc845"></i> Coach</span>
                        <span class="val">87</span>
                        <span class="sub">health</span>
                    </div>
                    <div class="hero-mstat">
                        <span class="lbl"><i class="fas fa-qrcode" style="color:#a78bfa"></i> QR</span>
                        <span class="val">1.4k</span>
                        <span class="sub">scans</span>
                    </div>
                    <div class="hero-mstat">
                        <span class="lbl"><i class="fas fa-store" style="color:#ff8a3c"></i> Today</span>
                        <span class="val">$412</span>
                        <span class="sub">revenue</span>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        // Floating-card metric tickers: gently increment Live visitors and
        // Revenue today so the hero feels alive. Pauses when off-screen,
        // when the tab is hidden, and respects prefers-reduced-motion.
        (function () {
            const reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (reduce) return;
            const visEl = document.getElementById('hero-tick-visitors');
            const revEl = document.getElementById('hero-tick-revenue');
            if (!visEl && !revEl) return;

            const parseNum = (el, fallback) => {
                if (!el) return fallback;
                const n = parseInt((el.textContent || '').replace(/[^0-9]/g, ''), 10);
                return Number.isFinite(n) ? n : fallback;
            };
            let visitors = parseNum(visEl, 247);
            let revenue  = parseNum(revEl, 412);
            let inView = false;
            let timer = null;

            function flash(el) {
                if (!el) return;
                el.style.transition = 'color .25s ease';
                const prev = el.style.color;
                el.style.color = '#1ed760';
                setTimeout(() => { el.style.color = prev; }, 280);
            }

            function tick() {
                if (document.hidden || !inView) return;
                if (visEl && Math.random() < 0.85) {
                    visitors += Math.random() < 0.15 ? -1 : (Math.random() < 0.4 ? 2 : 1);
                    if (visitors < 180) visitors = 180;
                    if (visitors > 320) visitors = 320;
                    visEl.textContent = visitors.toLocaleString();
                    flash(visEl);
                }
                if (revEl && Math.random() < 0.5) {
                    revenue += 1 + Math.floor(Math.random() * 6);
                    revEl.textContent = '$ ' + revenue.toLocaleString();
                    flash(revEl);
                }
            }

            function start() {
                if (timer) return;
                timer = setInterval(tick, 2200);
            }
            function stop() {
                if (!timer) return;
                clearInterval(timer);
                timer = null;
            }

            const target = (visEl || revEl).closest('.hero-phone-stage') || (visEl || revEl);
            if ('IntersectionObserver' in window) {
                const io = new IntersectionObserver((entries) => {
                    entries.forEach(e => {
                        inView = e.isIntersecting;
                        if (inView) start(); else stop();
                    });
                }, { threshold: 0.15 });
                io.observe(target);
            } else {
                inView = true;
                start();
            }

            document.addEventListener('visibilitychange', () => {
                if (document.hidden) stop(); else if (inView) start();
            });
        })();

        (function () {
            const ROLES   = @json($heroRoles);
            const word    = document.getElementById('hero-role-word');
            const sr      = document.getElementById('hero-role-sr');
            const stack   = document.getElementById('hero-stack');
            const screen  = document.getElementById('hero-phone-screen');
            const gallery = document.getElementById('hero-gallery');
            const galLbl  = document.getElementById('hero-gallery-label');
            const railLbl = document.getElementById('hero-rail-role-label');
            const phoneWrap = document.getElementById('hero-phone-wrap');
            const phoneScene = document.getElementById('hero-phone-scene');
            const tileRail = document.getElementById('hero-tile-rail');
            if (!word || !stack) return;

            const AUTO_ROTATE_MS = 3000;
            const SWAP_MS        = 220;
            const USER_PAUSE_MS  = 6000;
            let pauseUntil = 0;

            const reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const isDesktop = window.matchMedia && window.matchMedia('(min-width: 1024px)').matches;
            const escapeHTML = (s) => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

            function orderedGallery(role) {
                const items = (role.gallery || []).slice();
                const order = role.categories || [];
                const rank = (cat) => {
                    const i = order.indexOf(cat);
                    return i === -1 ? 999 : i;
                };
                items.sort((a,b) => rank(a.category) - rank(b.category));
                return items;
            }

            function buildGalleryHTML(role) {
                return orderedGallery(role).map((g, i) => `
                    <div class="hero-gallery-item gallery-shimmer" style="--gd:${i * 60}ms">
                        ${pictureThumb(g.src, '', 120, 120, '(max-width: 1023px) 110px, 120px', g.alt || '')}
                        <span class="gallery-cat">${escapeHTML(g.category)}</span>
                    </div>`).join('');
            }

            // A pool of distinct wallpapers. Every role swap picks a
            // fresh one at random (never repeats the previous one) so
            // the phone feels alive — not locked to a single gradient
            // per role. Role's own wallpaper is kept as the seed.
            const WALLPAPERS = [
                'linear-gradient(140deg,#7c3aed 0%,#e94e8c 60%,#ff8a3c 100%)',
                'linear-gradient(140deg,#e94e8c 0%,#ff8a3c 55%,#ffc845 100%)',
                'linear-gradient(140deg,#0f172a 0%,#1bd4d9 60%,#7c3aed 100%)',
                'linear-gradient(140deg,#0f3a2a 0%,#1ed760 55%,#1bd4d9 100%)',
                'linear-gradient(140deg,#1bd4d9 0%,#7c3aed 60%,#ffc845 100%)',
                'linear-gradient(140deg,#0a2540 0%,#1bd4d9 55%,#7c3aed 100%)',
                'linear-gradient(140deg,#e94e8c 0%,#7c3aed 50%,#ffc845 100%)',
                'linear-gradient(140deg,#ff8a3c 0%,#e94e8c 50%,#7c3aed 100%)',
                'linear-gradient(160deg,#0b132b 0%,#3a0ca3 45%,#f72585 100%)',
                'linear-gradient(135deg,#06b6d4 0%,#3b82f6 55%,#9333ea 100%)',
                'linear-gradient(150deg,#fde047 0%,#fb923c 45%,#ef4444 100%)',
                'linear-gradient(135deg,#064e3b 0%,#10b981 50%,#fde047 100%)',
                'linear-gradient(140deg,#312e81 0%,#ec4899 55%,#fbbf24 100%)',
                'linear-gradient(160deg,#1e1b4b 0%,#7c3aed 45%,#22d3ee 100%)',
            ];
            let lastWallpaper = null;
            function applyWallpaper(role) {
                if (!screen) return;
                // Different wallpaper each call. Include the role's
                // own wallpaper as an option but never pick the same
                // value as the previous render. Dedupe by value so a
                // role gradient that also appears in WALLPAPERS can't
                // be re-selected under a different index.
                const pool = Array.from(new Set([role.wallpaper, ...WALLPAPERS].filter(Boolean)));
                let pick;
                do { pick = pool[Math.floor(Math.random() * pool.length)]; }
                while (pool.length > 1 && pick === lastWallpaper);
                lastWallpaper = pick;
                screen.style.background = pick;
            }

            function pickFromGallery(role, category, fallbackIndex) {
                const g = role.gallery || [];
                const hit = g.find(x => x.category === category);
                if (hit) return hit.src;
                return (g[fallbackIndex] || g[0] || {}).src || '';
            }

            // ---- Responsive image helpers (WebP + JPEG fallback) ----
            function heroImgBase(src) {
                // strip leading slash-safe extension; works for /images/hero-roles/foo.jpg
                return (src || '').replace(/\.jpe?g$/i, '');
            }
            // Avatar / role headshot — only ever displayed up to ~120px wide.
            function pictureAvatar(src, cls, w, h) {
                const base = heroImgBase(src);
                const webp = `${base}-200.webp`;
                const jpg  = `${base}-200.jpg`;
                return `<picture>`
                    + `<source type="image/webp" srcset="${escapeHTML(webp)}">`
                    + `<img class="${escapeHTML(cls)}" src="${escapeHTML(jpg)}" alt="" loading="lazy" decoding="async" width="${w}" height="${h}">`
                    + `</picture>`;
            }
            // Thumb / cover / gallery image — displayed anywhere from ~50px to ~280px.
            // opts: { eager: bool } — when true, marks above-the-fold image as eager + high priority.
            function pictureThumb(src, cls, w, h, sizes, alt, opts) {
                const base = heroImgBase(src);
                const altA = escapeHTML(alt || '');
                const sz   = escapeHTML(sizes || '(max-width: 640px) 50vw, 320px');
                const eager = !!(opts && opts.eager);
                const loadAttr = eager ? 'eager' : 'lazy';
                const fpAttr   = eager ? ' fetchpriority="high"' : '';
                return `<picture>`
                    + `<source type="image/webp" srcset="${escapeHTML(base)}-320.webp 320w, ${escapeHTML(base)}-640.webp 640w" sizes="${sz}">`
                    + `<source type="image/jpeg" srcset="${escapeHTML(base)}-320.jpg 320w, ${escapeHTML(base)}-640.jpg 640w" sizes="${sz}">`
                    + `<img class="${escapeHTML(cls)}" src="${escapeHTML(base)}-320.jpg" alt="${altA}" loading="${loadAttr}"${fpAttr} decoding="async" width="${w}" height="${h}">`
                    + `</picture>`;
            }

            // Map category -> Font Awesome icon for tile fallback covers.
            const CAT_ICONS = {
                Video:'fas fa-video', Art:'fas fa-palette', Music:'fas fa-music',
                Merch:'fas fa-store', Photo:'fas fa-camera', Podcast:'fas fa-microphone',
                Writing:'fas fa-feather', Food:'fas fa-utensils', Fitness:'fas fa-dumbbell',
                Design:'fas fa-pen-ruler', Code:'fab fa-github', Stream:'fab fa-twitch',
                Course:'fas fa-graduation-cap', Book:'fas fa-book-open', Travel:'fas fa-plane',
            };
            function fallbackTileCover(role) {
                const cat = (role.categories || [])[0] || '';
                const ico = CAT_ICONS[cat] || 'fas fa-shapes';
                const bg  = role.wallpaper || 'linear-gradient(140deg,#7c3aed,#1bd4d9)';
                return `<span class="hero-tile-fallback" style="background:${bg}">`
                     + `<i class="${ico}" aria-hidden="true"></i>`
                     + `<span class="ftl">${escapeHTML(cat || role.word)}</span>`
                     + `</span>`;
            }

            // Each theme supplies its own bespoke profile block so the
            // profile never looks the same between role swaps. The shared
            // .hp-prof skeleton supplies the glass card frame; per-theme
            // `var-*` classes layer on the unique treatment.
            function profFor(role) {
                const p = role.profile;
                const h = escapeHTML(p.handle);
                const t = escapeHTML(p.tag);
                const av = p.avatar;
                const verified = '<i class="fas fa-circle-check pvd"></i>';
                const avatarImg = pictureAvatar(av, 'pav', 56, 56);

                switch (role.theme) {
                    case 'creator':
                        return `<div class="hp-prof var-creator theme-block" style="--d:0ms">
                            <div class="prow">
                              ${avatarImg}
                              <div class="min-w-0 flex-1">
                                <div class="ph">${h}${verified}</div>
                                <div class="pt">${t}</div>
                              </div>
                              <i class="fas fa-video" style="color:#ff5252;font-size:17px"></i>
                            </div>
                            <div class="pstats">
                              <div class="ps"><span class="sv">24.1k</span><span class="sl">Subscribers</span></div>
                              <div class="ps"><span class="sv">486</span><span class="sl">Videos</span></div>
                              <div class="ps"><span class="sv">1.2M</span><span class="sl">Views</span></div>
                            </div>
                          </div>`;
                    case 'gallery': // Artist
                        return `<div class="hp-prof var-artist theme-block" style="--d:0ms">
                            <div class="prow">
                              ${avatarImg}
                              <div class="min-w-0 flex-1">
                                <div class="ph">${h}${verified}</div>
                                <div class="pt">${t}</div>
                                <div class="swatch" aria-hidden="true">
                                  <i style="background:#e94e8c"></i>
                                  <i style="background:#ff8a3c"></i>
                                  <i style="background:#ffc845"></i>
                                  <i style="background:#1bd4d9"></i>
                                  <i style="background:#7c3aed"></i>
                                </div>
                              </div>
                              <i class="fas fa-palette" style="color:#ffc845;font-size:17px"></i>
                            </div>
                          </div>`;
                    case 'music':
                        return `<div class="hp-prof var-music theme-block" style="--d:0ms">
                            <div class="prow">
                              ${avatarImg}
                              <div class="min-w-0 flex-1">
                                <div class="ph">${h}${verified}</div>
                                <div class="pt">${t}</div>
                                <span class="npill"><i class="fas fa-music"></i>Now on tour · EP out</span>
                              </div>
                              <i class="fas fa-music" style="color:#1ed760;font-size:17px"></i>
                            </div>
                          </div>`;
                    case 'business':
                        return `<div class="hp-prof var-business theme-block" style="--d:0ms">
                            <div class="prow">
                              <div class="avwrap">${avatarImg}<span class="online" aria-hidden="true"></span></div>
                              <div class="min-w-0 flex-1">
                                <div class="ph">${h}${verified}</div>
                                <div class="pt">${t} · Accepting briefs</div>
                                <div class="bbadges">
                                  <span class="bbadge">Strategy</span>
                                  <span class="bbadge">B2B</span>
                                  <span class="bbadge">SaaS</span>
                                </div>
                              </div>
                              <i class="fas fa-briefcase" style="color:#0ea5e9;font-size:17px"></i>
                            </div>
                          </div>`;
                    case 'coach':
                        return `<div class="hp-prof var-coach theme-block" style="--d:0ms">
                            <div class="prow">
                              ${avatarImg}
                              <div class="min-w-0 flex-1">
                                <div class="ph">${h}${verified}</div>
                                <div class="pt">${t}</div>
                                <div class="chips">
                                  <span class="chip"><i class="fas fa-bolt"></i>NASM-CPT</span>
                                  <span class="chip"><i class="fas fa-star"></i>4.9 · 140+</span>
                                </div>
                              </div>
                              <i class="fas fa-dumbbell" style="color:#ffc845;font-size:17px"></i>
                            </div>
                          </div>`;
                    case 'portfolio': // Photographer
                        return `<div class="hp-prof var-photo theme-block" style="--d:0ms">
                            <div class="prow">
                              ${avatarImg}
                              <div class="min-w-0 flex-1">
                                <div class="ph">${h}${verified}</div>
                                <div class="pt">${t}</div>
                                <div class="loc"><i class="fas fa-location-dot"></i>Reykjavík · Available Jun</div>
                                <div class="gear">
                                  <span class="gr">Sony A7R V</span>
                                  <span class="gr">24-70 GM</span>
                                  <span class="gr">RAW</span>
                                </div>
                              </div>
                              <i class="fas fa-camera-retro" style="color:#22d3ee;font-size:17px"></i>
                            </div>
                          </div>`;
                    case 'social': // Influencer
                        return `<div class="hp-prof var-social theme-block" style="--d:0ms">
                            <div class="prow">
                              ${avatarImg}
                              <div class="min-w-0 flex-1">
                                <div class="ph">${h}${verified}</div>
                                <div class="pt">${t}</div>
                              </div>
                              <i class="fas fa-fire" style="color:#ef4444;font-size:17px"></i>
                            </div>
                            <div class="fgrid">
                              <div class="fg"><div class="fv">312k</div><div class="fl"><i class="fas fa-users"></i> Followers</div></div>
                              <div class="fg"><div class="fv">180k</div><div class="fl"><i class="fas fa-eye"></i> Reach</div></div>
                              <div class="fg"><div class="fv">94k</div><div class="fl"><i class="fas fa-heart"></i> Likes</div></div>
                            </div>
                          </div>`;
                    case 'podcast':
                        return `<div class="hp-prof var-podcast theme-block" style="--d:0ms">
                            <div class="prow">
                              ${avatarImg}
                              <div class="min-w-0 flex-1">
                                <div class="ph">${h}${verified}</div>
                                <div class="pt">${t}</div>
                                <span class="air"><i aria-hidden="true"></i>On air · Ep 87 live</span>
                              </div>
                              <i class="fas fa-microphone-lines" style="color:#ff8a3c;font-size:17px"></i>
                            </div>
                          </div>`;
                    default:
                        return `<div class="hp-prof theme-block" style="--d:0ms">
                            <div class="prow">
                              ${avatarImg}
                              <div class="min-w-0 flex-1">
                                <div class="ph">${h}${verified}</div>
                                <div class="pt">${t}</div>
                              </div>
                            </div>
                          </div>`;
                }
            }

            // Creator — full biolink list; blocks are bigger and there
            // are more of them so the stack fills the phone screen.
            function renderCreator(role) {
                const blocks = (role.blocks || []).map((b, i) => {
                    const delay = (i + 1) * 110;
                    const thumb = b.thumb
                        ? pictureThumb(b.thumb, 'card-thumb', 50, 50, '50px', '')
                        : `<div class="card-icon" style="background:${escapeHTML(b.color)}33;color:${escapeHTML(b.color)}"><i class="${escapeHTML(b.icon)}"></i></div>`;
                    return `
                        <div class="stack-card theme-block" style="--d:${delay}ms">
                            ${thumb}
                            <div class="card-body">
                                <div class="card-title">${escapeHTML(b.title)}</div>
                                <div class="card-sub">${escapeHTML(b.sub || '')}</div>
                            </div>
                            <i class="fas fa-arrow-right card-cta"></i>
                        </div>`;
                }).join('');
                const last = (role.blocks || []).length * 110 + 120;
                return profFor(role) + blocks
                    + `<div class="hp-cta theme-block" style="--d:${last}ms"><i class="fas fa-hand-holding-heart"></i>Tip · Join members</div>`;
            }

            function renderMusic(role) {
                const cover  = pickFromGallery(role, 'Music', 0);
                const merch  = pickFromGallery(role, 'Merch', 1);
                const b0     = (role.blocks || [])[0] || {};
                return profFor(role)
                    + `<div class="hp-music-card theme-block" style="--d:110ms">
                            ${pictureThumb(cover, 'hp-music-cover', 280, 110, '(max-width: 1023px) 260px, 320px', '')}
                            <div class="hp-music-eq" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
                            <div class="hp-music-meta">
                                <div class="mt"><div class="mt-t">${escapeHTML(b0.title || 'New EP')}</div><div class="mt-s">${escapeHTML(b0.sub || 'Listen now')}</div></div>
                                <div class="hp-music-play"><i class="fas fa-play" style="font-size:11px"></i></div>
                            </div>
                       </div>`
                    + `<div class="theme-block" style="--d:200ms">
                            <div class="hp-track"><span class="num">1</span><span class="nm">Saltwater</span><span class="du">3:42</span></div>
                       </div>`
                    + `<div class="theme-block" style="--d:250ms">
                            <div class="hp-track"><span class="num">2</span><span class="nm">Drift</span><span class="du">4:15</span></div>
                       </div>`
                    + `<div class="theme-block" style="--d:300ms">
                            <div class="hp-track"><span class="num">3</span><span class="nm">Afterglow</span><span class="du">3:28</span></div>
                       </div>`
                    + `<div class="hp-biz-cta theme-block" style="--d:360ms">
                            <div class="ic" style="background:#e94e8c22;color:#fff"><i class="fas fa-ticket-alt"></i></div>
                            <div class="bd"><div class="bt">Tour 2026 · Tickets live</div><div class="bs">12 cities · Starts Jun 4</div></div>
                            <i class="fas fa-arrow-right" style="opacity:.7"></i>
                       </div>`
                    + `<div class="hp-merch theme-block" style="--d:420ms">
                            ${pictureThumb(merch, '', 80, 80, '80px', '')}
                            <div class="mi"><div class="mt">Vinyl + tee bundle</div><div class="ms">Limited · Ships worldwide</div></div>
                            <span class="mp">$ 38</span>
                       </div>`
                    + `<div class="hp-cta theme-block" style="--d:480ms"><i class="fas fa-headphones"></i>Stream · Save · Share</div>`;
            }

            function renderGallery(role) {
                const g = role.gallery || [];
                const cells = g.slice(0, 6).map((x) => `
                    <div class="gi">${pictureThumb(x.src, '', 100, 100, '100px', x.alt || '')}
                        <span class="badge">${escapeHTML(x.category)}</span>
                    </div>`).join('');
                const more = g.slice(6, 9).map((x) => `
                    <div class="gi">${pictureThumb(x.src, '', 100, 100, '100px', x.alt || '')}</div>`).join('');
                return profFor(role)
                    + `<div class="hp-grid-3 theme-block" style="--d:110ms">${cells}</div>`
                    + (more ? `<div class="hp-grid-3 theme-block" style="--d:200ms">${more}</div>` : '')
                    + `<div class="hp-stat-row theme-block" style="--d:260ms">
                            <div class="hp-stat"><div class="sv">86</div><div class="sl">Works</div></div>
                            <div class="hp-stat"><div class="sv">12</div><div class="sl">Shows</div></div>
                            <div class="hp-stat"><div class="sv">4.9</div><div class="sl">Rating</div></div>
                       </div>`
                    + `<div class="hp-cta theme-block" style="--d:320ms"><i class="fas fa-shopping-bag"></i>Shop the collection</div>`
                    + `<div class="hp-cta dark theme-block" style="--d:380ms"><i class="fas fa-hand-holding-heart"></i>Tip jar</div>`;
            }

            function renderPortfolio(role) {
                const g = role.gallery || [];
                const feature = pickFromGallery(role, 'Photo', 0);
                const rest = g.filter(x => x.src !== feature);
                const grid4 = rest.slice(0, 4).map(x => `
                    <div class="gi">${pictureThumb(x.src, '', 140, 140, '140px', x.alt || '')}</div>`).join('');
                const grid2 = rest.slice(4, 6).map(x => `
                    <div class="gi">${pictureThumb(x.src, '', 140, 140, '140px', x.alt || '')}</div>`).join('');
                return profFor(role)
                    + `<div class="hp-feature theme-block" style="--d:110ms">
                            ${pictureThumb(feature, '', 280, 180, '(max-width: 1023px) 260px, 320px', '')}
                            <div class="lbl"><span>Iceland · 2026</span><span><i class="fas fa-camera"></i> 48</span></div>
                       </div>`
                    + `<div class="hp-grid-2 theme-block" style="--d:200ms">${grid4}</div>`
                    + (grid2 ? `<div class="hp-grid-2 theme-block" style="--d:260ms">${grid2}</div>` : '')
                    + `<div class="hp-biz-cta theme-block" style="--d:320ms">
                            <div class="ic"><i class="fas fa-print"></i></div>
                            <div class="bd"><div class="bt">Fine-art print shop</div><div class="bs">A2 / A3 / canvas · Worldwide</div></div>
                            <i class="fas fa-arrow-right" style="opacity:.7"></i>
                       </div>`
                    + `<div class="hp-cta theme-block" style="--d:380ms"><i class="fas fa-paper-plane"></i>Hire me · Weddings · Brand</div>`;
            }

            function renderBusiness(role) {
                return profFor(role)
                    + `<div class="hp-biz-cta theme-block" style="--d:110ms">
                            <div class="ic"><i class="fas fa-calendar-check"></i></div>
                            <div class="bd"><div class="bt">Book a strategy call</div><div class="bs">30 min · Calendly · Free intro</div></div>
                            <i class="fas fa-arrow-right" style="opacity:.7"></i>
                       </div>`
                    + `<div class="hp-svc-list theme-block" style="--d:200ms">
                            <div class="hp-svc"><div class="st">Audit</div><div class="sp">$ 1.2k</div></div>
                            <div class="hp-svc"><div class="st">Retainer</div><div class="sp">$ 4.5k/mo</div></div>
                       </div>`
                    + `<div class="hp-svc-list theme-block" style="--d:250ms">
                            <div class="hp-svc"><div class="st">Sprint</div><div class="sp">$ 2.5k</div></div>
                            <div class="hp-svc"><div class="st">Advisory</div><div class="sp">$ 600/hr</div></div>
                       </div>`
                    + `<div class="hp-stat-row theme-block" style="--d:320ms">
                            <div class="hp-stat"><div class="sv">120+</div><div class="sl">Clients</div></div>
                            <div class="hp-stat"><div class="sv">4.9</div><div class="sl">Rating</div></div>
                            <div class="hp-stat"><div class="sv">8 yr</div><div class="sl">Exp</div></div>
                       </div>`
                    + `<div class="hp-quote theme-block" style="--d:380ms">
                            Cut our CAC 38% in one quarter — Marcus is our unfair advantage.
                            <div class="qa"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><span>· Priya, CEO</span></div>
                       </div>`
                    + `<div class="hp-cta theme-block" style="--d:440ms"><i class="fas fa-paper-plane"></i>Get a proposal</div>`;
            }

            function renderCoach(role) {
                const reel = pickFromGallery(role, 'Video', 0);
                return profFor(role)
                    + `<div class="hp-stat-row theme-block" style="--d:110ms">
                            <div class="hp-stat"><div class="sv">140+</div><div class="sl">Clients</div></div>
                            <div class="hp-stat"><div class="sv">4.9★</div><div class="sl">Rating</div></div>
                            <div class="hp-stat"><div class="sv">12wk</div><div class="sl">Programs</div></div>
                       </div>`
                    + `<div class="hp-reel theme-block" style="--d:180ms">
                            ${pictureThumb(reel, '', 280, 360, '(max-width: 1023px) 260px, 320px', '')}
                            <div class="ov"></div>
                            <div class="play"><i class="fas fa-play" style="font-size:12px"></i></div>
                            <div class="lb"><span><i class="fas fa-fire"></i> Form check</span><span><i class="fas fa-heart"></i> 12k</span></div>
                       </div>`
                    + `<div class="hp-quote theme-block" style="--d:260ms">
                            Lost 9 kg, deadlift up 40 kg in 12 weeks — Kai's plan just works.
                            <div class="qa"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><span>· Sara, client</span></div>
                       </div>`
                    + `<div class="hp-svc-list theme-block" style="--d:320ms">
                            <div class="hp-svc"><div class="st">1:1 Coach</div><div class="sp">$ 180/mo</div></div>
                            <div class="hp-svc"><div class="st">Group</div><div class="sp">$ 65/mo</div></div>
                       </div>`
                    + `<div class="hp-biz-cta theme-block" style="--d:380ms">
                            <div class="ic"><i class="fas fa-calendar-check"></i></div>
                            <div class="bd"><div class="bt">Book a free consult</div><div class="bs">45 min · Zoom · Slots open</div></div>
                            <i class="fas fa-arrow-right" style="opacity:.7"></i>
                       </div>`
                    + `<div class="hp-cta dark theme-block" style="--d:440ms"><i class="fas fa-clipboard-list"></i>Free intake form</div>`;
            }

            function renderPodcast(role) {
                const cover = pickFromGallery(role, 'Podcast', 0);
                return profFor(role)
                    + `<div class="hp-pod-card theme-block" style="--d:110ms">
                            ${pictureThumb(cover, '', 280, 160, '(max-width: 1023px) 260px, 320px', '')}
                            <div class="pm">
                                <div class="pe">Ep. 87 · New</div>
                                <div class="pt">Building in public</div>
                                <div class="pd">42 min · Tech &amp; culture</div>
                            </div>
                            <div class="pp"><i class="fas fa-play" style="font-size:11px"></i></div>
                       </div>`
                    + `<div class="hp-wave theme-block" style="--d:200ms">
                            <span style="font-weight:800">2:14</span>
                            <svg viewBox="0 0 100 14" preserveAspectRatio="none"><polyline fill="none" stroke="#fff" stroke-width="1.4" stroke-linecap="round" points="0,8 8,4 14,11 22,3 30,9 38,5 46,12 54,2 62,9 70,6 78,11 86,4 94,9 100,7"/></svg>
                            <span style="opacity:.75">42:00</span>
                       </div>`
                    + `<div class="hp-ep theme-block" style="--d:260ms">
                            <span class="epn">#86</span><span class="ept">Shipping vs polishing</span><span class="epd">38m</span>
                       </div>`
                    + `<div class="hp-ep theme-block" style="--d:310ms">
                            <span class="epn">#85</span><span class="ept">Pricing your side project</span><span class="epd">45m</span>
                       </div>`
                    + `<div class="hp-stat-row theme-block" style="--d:360ms">
                            <div class="hp-stat"><div class="sv">87</div><div class="sl">Episodes</div></div>
                            <div class="hp-stat"><div class="sv">18k</div><div class="sl">Listeners</div></div>
                            <div class="hp-stat"><div class="sv">4.8</div><div class="sl">Rating</div></div>
                       </div>`
                    + `<div class="hp-cta dark theme-block" style="--d:420ms"><i class="fas fa-envelope-open-text"></i>Show notes &amp; newsletter</div>`;
            }

            function renderSocial(role) {
                const g = role.gallery || [];
                const reel = pickFromGallery(role, 'Video', 0);
                const stories = ['Reels','Hauls','Travel','Q&amp;A','BTS'];
                const storyHTML = stories.map((nm, i) => {
                    const src = (g[i] || g[0] || {}).src || '';
                    return `<div class="hp-story"><div class="ring">${pictureThumb(src, '', 56, 56, '56px', '')}</div><div class="nm">${nm}</div></div>`;
                }).join('');
                const posts = g.slice(0, 4).map(x => `
                    <div class="gi">${pictureThumb(x.src, '', 140, 140, '140px', x.alt || '')}
                        <span class="hrt"><i class="fas fa-heart"></i>${Math.floor(Math.random()*80)+20}k</span>
                    </div>`).join('');
                return profFor(role)
                    + `<div class="hp-stories theme-block" style="--d:110ms">${storyHTML}</div>`
                    + `<div class="hp-reel theme-block" style="--d:180ms">
                            ${pictureThumb(reel, '', 280, 360, '(max-width: 1023px) 260px, 320px', '')}
                            <div class="ov"></div>
                            <div class="play"><i class="fas fa-play" style="font-size:12px"></i></div>
                            <div class="lb"><span><i class="fas fa-eye"></i> 312k</span><span><i class="fas fa-heart"></i> 28k</span></div>
                       </div>`
                    + `<div class="hp-grid-4 theme-block" style="--d:250ms">${posts}</div>`
                    + `<div class="hp-biz-cta theme-block" style="--d:320ms">
                            <div class="ic" style="background:#ffc84522;color:#fff"><i class="fas fa-handshake"></i></div>
                            <div class="bd"><div class="bt">Brand deals · Press kit</div><div class="bs">Rates · Past campaigns · Reach</div></div>
                            <i class="fas fa-arrow-right" style="opacity:.7"></i>
                       </div>`;
            }

            const THEMES = {
                creator: renderCreator,
                gallery: renderGallery,
                portfolio: renderPortfolio,
                business: renderBusiness,
                coach: renderCoach,
                music: renderMusic,
                podcast: renderPodcast,
                social: renderSocial,
            };

            function buildStackHTML(role) {
                const fn = THEMES[role.theme] || renderCreator;
                return fn(role);
            }

            // ---- Compact horizontal interactive tile strip (all breakpoints) ----
            function buildTileRailHTML() {
                if (!tileRail) return;
                const html = ROLES.map((role, i) => {
                    const cat = (role.categories || [])[0];
                    const src = pickFromGallery(role, cat, 0);
                    const eager = i < 6;
                    const cover = src
                        ? pictureThumb(src, 'hero-tile-img', 80, 60, '80px', role.word + ' preview', { eager })
                        : fallbackTileCover(role);
                    return `<button type="button" class="hero-tile${i===0?' is-active':''}" `
                         + `data-role-i="${i}" aria-pressed="${i===0?'true':'false'}" `
                         + `aria-label="Show ${escapeHTML(role.word)} preview" tabindex="0">`
                         + `<span class="hero-tile-thumb">${cover}</span>`
                         + `<span class="hero-tile-label">${escapeHTML(role.word)}</span>`
                         + `</button>`;
                }).join('');
                tileRail.innerHTML = html;
            }

            function syncActiveTile(role) {
                if (!tileRail) return;
                const idx = ROLES.indexOf(role);
                const tiles = tileRail.querySelectorAll('.hero-tile');
                tiles.forEach((el, i) => {
                    const active = i === idx;
                    el.classList.toggle('is-active', active);
                    el.setAttribute('aria-pressed', active ? 'true' : 'false');
                });
                if (idx >= 0 && tiles[idx]) {
                    // Centre the active tile *within the rail's own horizontal
                    // scroll* — never use scrollIntoView, because on mobile the
                    // tile rail sits below the fold and scrollIntoView would
                    // also scroll the page vertically, pushing the hero
                    // headline / CTAs off-screen. We compute scrollLeft
                    // manually so only the rail moves.
                    try {
                        const tile = tiles[idx];
                        const target = tile.offsetLeft - (tileRail.clientWidth / 2) + (tile.offsetWidth / 2);
                        const max = Math.max(0, tileRail.scrollWidth - tileRail.clientWidth);
                        const left = Math.max(0, Math.min(max, target));
                        if (typeof tileRail.scrollTo === 'function') {
                            tileRail.scrollTo({ left, behavior: reduce ? 'auto' : 'smooth' });
                        } else {
                            tileRail.scrollLeft = left;
                        }
                    } catch (_) { /* no-op */ }
                }
            }

            function paintRoleVisuals(role) {
                applyWallpaper(role);
                if (gallery) gallery.innerHTML = buildGalleryHTML(role);
                if (galLbl) galLbl.textContent = role.word.toLowerCase();
                if (railLbl) railLbl.textContent = role.word.toLowerCase();
                syncActiveTile(role);
            }

            function setRole(role, opts) {
                opts = opts || {};
                if (opts.fromUser) pauseUntil = Date.now() + USER_PAUSE_MS;
                if (sr) sr.textContent = role.word;
                if (reduce) {
                    // Simple opacity crossfade fallback (no shimmer / animation)
                    word.classList.add('rm-out');
                    stack.classList.add('rm-out');
                    setTimeout(() => {
                        word.textContent = role.word;
                        stack.innerHTML = buildStackHTML(role);
                        paintRoleVisuals(role);
                        word.classList.remove('rm-out');
                        stack.classList.remove('rm-out');
                    }, 0);
                    return;
                }
                // Animate word out, swap text, animate in
                word.classList.remove('word-in');
                word.classList.add('word-out');
                // Animate stack out
                stack.classList.add('stack-out');
                setTimeout(() => {
                    word.textContent = role.word;
                    word.classList.remove('word-out');
                    // force reflow then play in
                    void word.offsetWidth;
                    word.classList.add('word-in');
                    stack.classList.remove('stack-out');
                    stack.innerHTML = buildStackHTML(role);
                    paintRoleVisuals(role);
                }, SWAP_MS);
            }

            let i = 0;
            // Build interactive rail before initial paint so syncActiveTile finds tiles.
            buildTileRailHTML();
            // Initial paint (no out animation)
            stack.innerHTML = buildStackHTML(ROLES[0]);
            word.textContent = ROLES[0].word;
            paintRoleVisuals(ROLES[0]);
            if (!reduce) word.classList.add('word-in');

            // Tile interactions: click pins (pauses auto-rotate), hover previews
            // without pinning (auto-rotate keeps running underneath).
            if (tileRail) {
                let hoverTimer = 0;
                const previewByIndex = (idx) => {
                    if (idx < 0 || idx >= ROLES.length) return;
                    i = idx; // keep auto-rotate counter in sync after preview
                    setRole(ROLES[idx]); // no fromUser → no pause
                };
                const pinByIndex = (idx) => {
                    if (idx < 0 || idx >= ROLES.length) return;
                    i = idx;
                    setRole(ROLES[idx], { fromUser: true }); // pauses rotate
                };
                tileRail.addEventListener('click', (e) => {
                    const tile = e.target.closest('.hero-tile');
                    if (!tile) return;
                    clearTimeout(hoverTimer);
                    pinByIndex(parseInt(tile.dataset.roleI, 10));
                });
                tileRail.addEventListener('mouseover', (e) => {
                    const tile = e.target.closest('.hero-tile');
                    if (!tile) return;
                    clearTimeout(hoverTimer);
                    const idx = parseInt(tile.dataset.roleI, 10);
                    hoverTimer = setTimeout(() => previewByIndex(idx), 140);
                });
                tileRail.addEventListener('mouseleave', () => {
                    clearTimeout(hoverTimer);
                });
                // Keyboard: arrow keys to move focus along the rail; Enter/Space activate via native button.
                tileRail.addEventListener('keydown', (e) => {
                    if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft' &&
                        e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
                    const tile = e.target.closest('.hero-tile');
                    if (!tile) return;
                    e.preventDefault();
                    const idx = parseInt(tile.dataset.roleI, 10);
                    const fwd = (e.key === 'ArrowRight' || e.key === 'ArrowDown');
                    const next = fwd
                        ? Math.min(ROLES.length - 1, idx + 1)
                        : Math.max(0, idx - 1);
                    const tiles = tileRail.querySelectorAll('.hero-tile');
                    if (tiles[next]) tiles[next].focus();
                });
            }

            setInterval(() => {
                if (Date.now() < pauseUntil) return;
                i = (i + 1) % ROLES.length;
                setRole(ROLES[i]);
            }, AUTO_ROTATE_MS);

            // Cursor parallax tilt on the phone (desktop only, no reduced motion).
            if (phoneWrap && phoneScene && isDesktop && !reduce) {
                let raf = 0, tx = 0, ty = 0;
                phoneScene.addEventListener('mousemove', (e) => {
                    const r = phoneScene.getBoundingClientRect();
                    const cx = (e.clientX - r.left) / r.width  - 0.5;
                    const cy = (e.clientY - r.top)  / r.height - 0.5;
                    tx = -cy * 8; // rotateX
                    ty =  cx * 10; // rotateY
                    if (!raf) raf = requestAnimationFrame(() => {
                        phoneWrap.style.transform = `perspective(900px) rotateX(${tx}deg) rotateY(${ty}deg)`;
                        raf = 0;
                    });
                });
                phoneScene.addEventListener('mouseleave', () => {
                    phoneWrap.style.transform = '';
                });
            }
        })();
    </script>
</section>

{{-- ============================ MARQUEE STRIP ============================ --}}
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

{{-- ============================ 1 · BUILD ============================ --}}
<section id="features" class="py-24 lg:py-32 relative overflow-hidden">
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16 max-w-3xl mx-auto">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c1)">01 · Build</div>
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
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c3)">02 · Share</div>
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
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c5)">03 · Grow</div>
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
            <div class="reveal rd-2 lg:col-span-5 rounded-3xl p-7 tilt relative overflow-hidden text-white" style="background: linear-gradient(140deg, var(--c2), var(--c3) 70%, var(--c4));">
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
    ];
@endphp
<section id="everything" class="py-24 lg:py-32 relative overflow-hidden" aria-labelledby="everything-h">
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-14 max-w-3xl mx-auto">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c5)">04 · Everything you get</div>
            <h2 id="everything-h" class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mb-5">
                One platform.<br><span class="grad-text">The whole growth stack.</span>
            </h2>
            <p class="reveal rd-2 text-lg text-gray-400">
                Four pillars, one login, free forever. No more stitching together five different tools to launch, share, sell and grow.
            </p>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
            @foreach($__pillars as $i => $p)
                <article class="reveal rd-{{ ($i % 4) + 1 }} glass rounded-3xl p-6 lift relative overflow-hidden">
                    <div class="absolute -top-12 -right-12 w-40 h-40 rounded-full opacity-20" style="background:radial-gradient(circle, {{ $p['color'] }}, transparent 70%);"></div>
                    <div class="relative w-12 h-12 rounded-2xl flex items-center justify-center mb-4" style="background: linear-gradient(135deg, {{ $p['color'] }}, var(--c2)); box-shadow: 0 12px 30px -12px {{ $p['color'] }};">
                        <i class="fas {{ $p['icon'] }} text-white"></i>
                    </div>
                    <div class="relative text-[11px] font-bold uppercase tracking-wider mb-1" style="color: {{ $p['color'] }};">{{ $p['eyebrow'] }}</div>
                    <h3 class="relative text-lg font-bold mb-4 leading-snug">{!! $p['title'] !!}</h3>

                    {{-- Mini visual preview, one per pillar --}}
                    <div class="pillar-preview relative mb-5 rounded-2xl p-3 overflow-hidden" style="background:linear-gradient(135deg, rgba(255,255,255,.04), rgba(255,255,255,.02)); border:1px solid rgba(255,255,255,.06);" aria-hidden="true">
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
                </article>
            @endforeach
        </div>
    </div>
</section>

{{-- ============================ AI SUITE ============================ --}}
@php
    $__aiProducts = [
        [
            'eyebrow' => 'AI Chatbot',
            'title'   => 'A 24/7 chatbot trained on your biolink.',
            'desc'    => 'Greets every visitor in your voice, answers from your real content, captures leads and books calls — never asleep.',
            'icon'    => 'fa-comments',
            'color'   => '#7c3aed',
            'route'   => 'site.ai-chatbot',
        ],
        [
            'eyebrow' => 'AI Agent',
            'title'   => 'A teammate that runs multi-step tasks.',
            'desc'    => 'Qualifies leads, drafts outreach, updates your contacts and follows up — across your inbox, calendar and CRM.',
            'icon'    => 'fa-robot',
            'color'   => '#1bd4d9',
            'route'   => 'site.ai-agent',
        ],
        [
            'eyebrow' => 'AI Widget',
            'title'   => 'Embed an AI assistant on any website.',
            'desc'    => 'One snippet on WordPress, Shopify, Webflow or your custom site — answers questions and routes hot leads to your inbox.',
            'icon'    => 'fa-window-restore',
            'color'   => '#e94e8c',
            'route'   => 'site.ai-widget',
        ],
        [
            'eyebrow' => 'AI Voice Assistant',
            'title'   => 'Picks up calls in your voice.',
            'desc'    => 'AI receptionist that answers your number, qualifies callers, books real meetings and warm-transfers when it matters.',
            'icon'    => 'fa-headset',
            'color'   => '#ff8a3c',
            'route'   => 'site.ai-voice-assistant',
        ],
    ];
@endphp
@php
    $__aiKey = function ($eyebrow) {
        return [
            'AI Chatbot'         => 'chatbot',
            'AI Agent'           => 'agent',
            'AI Widget'          => 'widget',
            'AI Voice Assistant' => 'voice',
        ][$eyebrow] ?? 'chatbot';
    };
@endphp
<style>
    /* ===== AI Suite v2 — animated illustrations ===== */
    .ai-suite-v2 { isolation: isolate; }
    .ai-suite-v2 .ai-bg-grid {
        position: absolute; inset: 0; pointer-events: none; opacity: .35;
        background-image:
            linear-gradient(rgba(255,255,255,.045) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255,255,255,.045) 1px, transparent 1px);
        background-size: 44px 44px;
        mask-image: radial-gradient(ellipse 70% 60% at 50% 40%, #000 30%, transparent 80%);
        -webkit-mask-image: radial-gradient(ellipse 70% 60% at 50% 40%, #000 30%, transparent 80%);
    }
    .ai-suite-v2 .ai-bg-blob {
        position: absolute; width: 460px; height: 460px;
        border-radius: 9999px; filter: blur(80px);
        opacity: .35; pointer-events: none;
        animation: aiBlobFloat 18s ease-in-out infinite;
    }
    .ai-suite-v2 .ai-bg-blob-a { top: -120px; left: -100px; background: radial-gradient(circle, #7c3aed, transparent 65%); }
    .ai-suite-v2 .ai-bg-blob-b { bottom: -180px; right: -120px; background: radial-gradient(circle, #ec4899, transparent 65%); animation-delay: -6s; }
    .ai-suite-v2 .ai-bg-blob-c { top: 30%; right: 25%; width: 320px; height: 320px; background: radial-gradient(circle, #22d3ee, transparent 65%); animation-delay: -12s; opacity: .22; }
    @keyframes aiBlobFloat {
        0%,100% { transform: translate3d(0,0,0) scale(1); }
        50%     { transform: translate3d(40px,-30px,0) scale(1.08); }
    }
    html.light-mode .ai-suite-v2 .ai-bg-blob { opacity: .18; }
    html.light-mode .ai-suite-v2 .ai-bg-grid { opacity: .35;
        background-image:
            linear-gradient(rgba(15,23,42,.06) 1px, transparent 1px),
            linear-gradient(90deg, rgba(15,23,42,.06) 1px, transparent 1px);
    }

    /* Headline shimmer sweep */
    .ai-shimmer {
        background: linear-gradient(90deg, #a78bfa 0%, #ec4899 25%, #22d3ee 50%, #ec4899 75%, #a78bfa 100%);
        background-size: 200% 100%;
        -webkit-background-clip: text; background-clip: text;
        color: transparent;
        animation: aiShimmer 6s linear infinite;
    }
    @keyframes aiShimmer { 0%{ background-position: 0% 50%; } 100%{ background-position: 200% 50%; } }

    /* Card */
    .ai-card {
        position: relative; display: block; overflow: hidden;
        border-radius: 1.5rem;
        background: linear-gradient(180deg, rgba(255,255,255,.05), rgba(255,255,255,.015));
        border: 1px solid rgba(255,255,255,.08);
        padding: 1.25rem 1.25rem 1.4rem;
        transition: transform .4s cubic-bezier(.2,.7,.2,1), border-color .35s ease, box-shadow .4s ease;
    }
    .ai-card:hover, .ai-card:focus-visible {
        transform: translateY(-6px);
        border-color: color-mix(in srgb, var(--ai-accent, #7c3aed) 55%, transparent);
        box-shadow: 0 30px 70px -28px color-mix(in srgb, var(--ai-accent, #7c3aed) 70%, transparent);
    }
    html.light-mode .ai-card {
        background: #ffffff;
        border-color: rgba(15,23,42,.08);
        box-shadow: 0 4px 14px -8px rgba(15,23,42,.10);
    }
    .ai-card-glow {
        position: absolute; inset: -1px; border-radius: inherit; pointer-events: none;
        background: radial-gradient(60% 50% at 50% 0%, color-mix(in srgb, var(--ai-accent, #7c3aed) 35%, transparent), transparent 70%);
        opacity: .35; transition: opacity .4s ease;
    }
    .ai-card:hover .ai-card-glow { opacity: 1; }
    .ai-card-corner {
        position: absolute; top: -50px; right: -50px; width: 160px; height: 160px;
        border-radius: 9999px; opacity: .22;
        background: radial-gradient(circle, var(--ai-accent, #7c3aed), transparent 70%);
    }

    /* Illustration frame */
    .ai-illus {
        position: relative; height: 132px; border-radius: 1rem; overflow: hidden;
        margin-bottom: 1rem;
        background:
            radial-gradient(120% 100% at 0% 0%, color-mix(in srgb, var(--ai-accent, #7c3aed) 22%, transparent), transparent 60%),
            linear-gradient(180deg, rgba(255,255,255,.04), rgba(255,255,255,.015));
        border: 1px solid rgba(255,255,255,.08);
    }
    html.light-mode .ai-illus {
        background:
            radial-gradient(120% 100% at 0% 0%, color-mix(in srgb, var(--ai-accent, #7c3aed) 14%, transparent), transparent 60%),
            linear-gradient(180deg, #f8fafc, #ffffff);
        border-color: rgba(15,23,42,.08);
    }

    /* ---- Chatbot illustration ---- */
    .ai-chat-bubble {
        position: absolute; padding: .42rem .65rem; border-radius: .9rem;
        font-size: .68rem; font-weight: 600; line-height: 1.1;
        max-width: 70%;
        animation: aiChatPop .5s ease both;
    }
    .ai-chat-b1 { top: 14px; left: 12px; background: rgba(255,255,255,.10); color: #e5e7eb; border-bottom-left-radius: .3rem; animation-delay: .1s; }
    .ai-chat-b2 { top: 50px; right: 12px; background: linear-gradient(135deg, var(--ai-accent), #6366f1); color: #fff; border-bottom-right-radius: .3rem; animation-delay: .8s; }
    .ai-chat-b3 { bottom: 16px; left: 12px; background: rgba(255,255,255,.10); color: #e5e7eb; border-bottom-left-radius: .3rem; padding: .55rem .75rem; animation-delay: 1.6s; }
    html.light-mode .ai-chat-b1, html.light-mode .ai-chat-b3 { background: rgba(15,23,42,.06); color: #1e293b; }
    @keyframes aiChatPop { 0%{ opacity:0; transform: translateY(8px) scale(.9);} 100%{ opacity:1; transform: translateY(0) scale(1);} }
    .ai-typing { display: inline-flex; gap: 3px; vertical-align: middle; }
    .ai-typing i {
        width: 5px; height: 5px; border-radius: 9999px; background: currentColor;
        animation: aiTyping 1.2s ease-in-out infinite;
    }
    .ai-typing i:nth-child(2) { animation-delay: .15s; }
    .ai-typing i:nth-child(3) { animation-delay: .3s; }
    @keyframes aiTyping { 0%,100% { opacity: .3; transform: translateY(0);} 50% { opacity: 1; transform: translateY(-3px);} }
    .ai-card:hover .ai-chat-bubble { animation-duration: .35s; }

    /* ---- Agent illustration ---- */
    .ai-tasklist {
        position: absolute; inset: 14px; display: flex; flex-direction: column; gap: 7px;
        padding: 10px; border-radius: .75rem;
        background: rgba(0,0,0,.25);
        border: 1px solid rgba(255,255,255,.06);
    }
    html.light-mode .ai-tasklist { background: #fff; border-color: rgba(15,23,42,.06); box-shadow: inset 0 0 0 1px rgba(15,23,42,.02); }
    .ai-task {
        display: flex; align-items: center; gap: 8px;
        font-size: .68rem; font-weight: 600; color: #e5e7eb;
    }
    html.light-mode .ai-task { color: #334155; }
    .ai-task-check {
        flex: 0 0 auto; width: 14px; height: 14px; border-radius: 4px;
        border: 1.5px solid color-mix(in srgb, var(--ai-accent) 60%, #94a3b8);
        position: relative; overflow: hidden;
    }
    .ai-task-check::after {
        content: ""; position: absolute; left: 2px; top: 4px; width: 7px; height: 4px;
        border-left: 2px solid #fff; border-bottom: 2px solid #fff;
        transform: rotate(-45deg) scale(0); transform-origin: left top;
        transition: transform .25s ease;
    }
    .ai-task.done .ai-task-check { background: var(--ai-accent); border-color: var(--ai-accent); }
    .ai-task.done .ai-task-check::after { transform: rotate(-45deg) scale(1); }
    .ai-task-bar { flex: 1; height: 6px; border-radius: 9999px; background: rgba(255,255,255,.10); overflow: hidden; }
    html.light-mode .ai-task-bar { background: rgba(15,23,42,.08); }
    .ai-task-bar i {
        display: block; height: 100%; width: 0; background: linear-gradient(90deg, var(--ai-accent), #22d3ee);
        animation: aiTaskFill 4s ease-in-out infinite;
    }
    .ai-task:nth-child(1) { animation: aiTaskRow 4s ease-in-out infinite; }
    .ai-task:nth-child(2) { animation: aiTaskRow 4s ease-in-out infinite 1.2s; }
    .ai-task:nth-child(3) { animation: aiTaskRow 4s ease-in-out infinite 2.4s; }
    @keyframes aiTaskRow {
        0%, 8%   { opacity: .55; }
        10%, 35% { opacity: 1; }
        40%      { opacity: 1; }
    }
    .ai-task:nth-child(1) .ai-task-bar i { animation-delay: 0s; }
    .ai-task:nth-child(2) .ai-task-bar i { animation-delay: 1.2s; }
    .ai-task:nth-child(3) .ai-task-bar i { animation-delay: 2.4s; }
    @keyframes aiTaskFill { 0%{ width: 0; } 60%, 100%{ width: 100%; } }
    .ai-card:hover .ai-task .ai-task-check { background: var(--ai-accent); border-color: var(--ai-accent); }
    .ai-card:hover .ai-task .ai-task-check::after { transform: rotate(-45deg) scale(1); }

    /* ---- Widget illustration (browser frame) ---- */
    .ai-browser {
        position: absolute; inset: 14px; border-radius: .75rem; overflow: hidden;
        background: rgba(0,0,0,.30);
        border: 1px solid rgba(255,255,255,.08);
        display: flex; flex-direction: column;
    }
    html.light-mode .ai-browser { background: #fff; border-color: rgba(15,23,42,.08); }
    .ai-browser-bar {
        display: flex; align-items: center; gap: 4px;
        padding: 5px 7px; background: rgba(255,255,255,.04);
        border-bottom: 1px solid rgba(255,255,255,.06);
    }
    html.light-mode .ai-browser-bar { background: #f1f5f9; border-bottom-color: rgba(15,23,42,.06); }
    .ai-browser-bar span { width: 6px; height: 6px; border-radius: 9999px; background: rgba(255,255,255,.20); }
    html.light-mode .ai-browser-bar span { background: rgba(15,23,42,.18); }
    .ai-browser-body { position: relative; flex: 1; padding: 8px; display: flex; flex-direction: column; gap: 5px; }
    .ai-browser-line { height: 5px; border-radius: 9999px; background: rgba(255,255,255,.08); }
    html.light-mode .ai-browser-line { background: rgba(15,23,42,.08); }
    .ai-browser-line.l1 { width: 70%; }
    .ai-browser-line.l2 { width: 90%; }
    .ai-browser-line.l3 { width: 55%; }
    .ai-widget-pop {
        position: absolute; right: 8px; bottom: 8px;
        width: 64px; padding: 6px 8px; border-radius: .55rem;
        background: linear-gradient(135deg, var(--ai-accent), #f97316);
        color: #fff; font-size: .58rem; font-weight: 700;
        box-shadow: 0 8px 22px -8px var(--ai-accent);
        display: flex; align-items: center; gap: 5px;
        transform-origin: bottom right;
        animation: aiWidgetPop 4s ease-in-out infinite;
    }
    .ai-widget-pop::before {
        content: ""; width: 7px; height: 7px; border-radius: 9999px; background: #fff;
        box-shadow: 0 0 0 0 rgba(255,255,255,.6);
        animation: aiWidgetDot 1.6s ease-out infinite;
    }
    @keyframes aiWidgetPop {
        0%, 20%, 100% { transform: scale(0) rotate(-12deg); opacity: 0; }
        30%, 75%      { transform: scale(1) rotate(0); opacity: 1; }
        85%           { transform: scale(.9) rotate(-4deg); opacity: .6; }
    }
    @keyframes aiWidgetDot {
        0% { box-shadow: 0 0 0 0 rgba(255,255,255,.55); }
        80%, 100% { box-shadow: 0 0 0 8px rgba(255,255,255,0); }
    }
    .ai-card:hover .ai-widget-pop { animation-duration: 2.4s; }

    /* ---- Voice illustration ---- */
    .ai-voice {
        position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; gap: 14px;
    }
    .ai-phone {
        width: 46px; height: 70px; border-radius: 10px;
        background: linear-gradient(160deg, #1f2937, #0f172a);
        border: 1px solid rgba(255,255,255,.10);
        box-shadow: 0 14px 34px -16px rgba(0,0,0,.55), inset 0 0 0 1px rgba(255,255,255,.04);
        position: relative; overflow: hidden;
        animation: aiPhoneRing 2.4s ease-in-out infinite;
    }
    .ai-phone::before {
        content: ""; position: absolute; left: 50%; top: 6px; transform: translateX(-50%);
        width: 14px; height: 3px; border-radius: 9999px; background: rgba(255,255,255,.18);
    }
    .ai-phone::after {
        content: "\f095"; /* fa phone */
        font-family: "Font Awesome 6 Free", "Font Awesome 5 Free", "FontAwesome";
        font-weight: 900; font-size: 18px;
        position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%);
        color: var(--ai-accent);
        text-shadow: 0 0 14px color-mix(in srgb, var(--ai-accent) 70%, transparent);
    }
    @keyframes aiPhoneRing {
        0%, 100% { transform: rotate(-3deg); }
        50%      { transform: rotate(3deg); }
    }
    .ai-wave { display: flex; align-items: center; gap: 4px; height: 56px; }
    .ai-wave i {
        display: block; width: 4px; border-radius: 9999px;
        background: linear-gradient(180deg, var(--ai-accent), #ec4899);
        animation: aiWaveBar 1.2s ease-in-out infinite;
    }
    .ai-wave i:nth-child(1){ height: 18px; animation-delay: 0s; }
    .ai-wave i:nth-child(2){ height: 30px; animation-delay: .12s; }
    .ai-wave i:nth-child(3){ height: 44px; animation-delay: .24s; }
    .ai-wave i:nth-child(4){ height: 26px; animation-delay: .36s; }
    .ai-wave i:nth-child(5){ height: 38px; animation-delay: .48s; }
    .ai-wave i:nth-child(6){ height: 22px; animation-delay: .60s; }
    .ai-wave i:nth-child(7){ height: 14px; animation-delay: .72s; }
    @keyframes aiWaveBar {
        0%,100% { transform: scaleY(.35); }
        50%     { transform: scaleY(1); }
    }
    .ai-card:hover .ai-wave i { animation-duration: .7s; }
    .ai-card:hover .ai-phone { animation-duration: 1.2s; }

    /* Reveal stagger — uses existing `.reveal` mechanism */
    .ai-suite-v2 .ai-card { opacity: 0; transform: translateY(18px); transition: opacity .7s ease, transform .7s cubic-bezier(.2,.7,.2,1); }
    .ai-suite-v2 .ai-card.in-view, .ai-suite-v2 .reveal.in-view .ai-card { opacity: 1; transform: translateY(0); }
    .ai-suite-v2 .ai-card.d1 { transition-delay: .05s; }
    .ai-suite-v2 .ai-card.d2 { transition-delay: .15s; }
    .ai-suite-v2 .ai-card.d3 { transition-delay: .25s; }
    .ai-suite-v2 .ai-card.d4 { transition-delay: .35s; }

    @media (prefers-reduced-motion: reduce) {
        .ai-suite-v2 .ai-bg-blob,
        .ai-shimmer,
        .ai-chat-bubble,
        .ai-typing i,
        .ai-task,
        .ai-task-bar i,
        .ai-widget-pop,
        .ai-widget-pop::before,
        .ai-phone,
        .ai-wave i { animation: none !important; }
        .ai-shimmer { background: linear-gradient(90deg, #a78bfa, #ec4899, #22d3ee); -webkit-background-clip: text; background-clip: text; }
        .ai-chat-bubble { opacity: 1; transform: none; }
        .ai-widget-pop { transform: none; opacity: 1; }
        .ai-suite-v2 .ai-card { opacity: 1; transform: none; }
    }
</style>
<section id="ai-suite" class="ai-suite-v2 py-24 lg:py-32 relative overflow-hidden" aria-labelledby="ai-suite-h">
    <div class="ai-bg-grid" aria-hidden="true"></div>
    <div class="ai-bg-blob ai-bg-blob-a" aria-hidden="true"></div>
    <div class="ai-bg-blob ai-bg-blob-b" aria-hidden="true"></div>
    <div class="ai-bg-blob ai-bg-blob-c" aria-hidden="true"></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-14 max-w-3xl mx-auto">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:#7c3aed">05 · AI suite</div>
            <h2 id="ai-suite-h" class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mb-5">
                Built-in AI that <span class="ai-shimmer">works the room</span> for you.
            </h2>
            <p class="reveal rd-2 text-lg text-gray-400">
                A chatbot for your biolink, an agent that runs playbooks, an embeddable widget for any site, and a voice assistant that picks up your calls — all under one login.
            </p>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
            @foreach($__aiProducts as $i => $a)
                @php $key = $__aiKey($a['eyebrow']); @endphp
                <a href="{{ route($a['route']) }}"
                   class="ai-card reveal rd-{{ ($i % 4) + 1 }} d{{ $i + 1 }} group"
                   style="--ai-accent: {{ $a['color'] }};"
                   aria-label="{{ $a['eyebrow'] }} — learn more">
                    <span class="ai-card-glow" aria-hidden="true"></span>
                    <span class="ai-card-corner" aria-hidden="true"></span>

                    <div class="ai-illus" aria-hidden="true">
                        @if($key === 'chatbot')
                            <div class="ai-chat-bubble ai-chat-b1">Hey! 👋</div>
                            <div class="ai-chat-bubble ai-chat-b2">Got it — booking a slot for you.</div>
                            <div class="ai-chat-bubble ai-chat-b3">
                                <span class="ai-typing" style="color: {{ $a['color'] }};">
                                    <i></i><i></i><i></i>
                                </span>
                            </div>
                        @elseif($key === 'agent')
                            <div class="ai-tasklist">
                                <div class="ai-task done">
                                    <span class="ai-task-check"></span>
                                    <span>Qualify lead</span>
                                    <span class="ai-task-bar"><i></i></span>
                                </div>
                                <div class="ai-task done">
                                    <span class="ai-task-check"></span>
                                    <span>Draft email</span>
                                    <span class="ai-task-bar"><i></i></span>
                                </div>
                                <div class="ai-task">
                                    <span class="ai-task-check"></span>
                                    <span>Schedule follow-up</span>
                                    <span class="ai-task-bar"><i></i></span>
                                </div>
                            </div>
                        @elseif($key === 'widget')
                            <div class="ai-browser">
                                <div class="ai-browser-bar">
                                    <span></span><span></span><span></span>
                                </div>
                                <div class="ai-browser-body">
                                    <div class="ai-browser-line l1"></div>
                                    <div class="ai-browser-line l2"></div>
                                    <div class="ai-browser-line l3"></div>
                                    <div class="ai-browser-line l2"></div>
                                    <div class="ai-widget-pop">
                                        <span>Ask AI</span>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="ai-voice">
                                <div class="ai-phone"></div>
                                <div class="ai-wave">
                                    <i></i><i></i><i></i><i></i><i></i><i></i><i></i>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="relative text-[11px] font-bold uppercase tracking-wider mb-1" style="color: {{ $a['color'] }};">{{ $a['eyebrow'] }}</div>
                    <h3 class="relative text-lg font-bold mb-2 leading-snug">{{ $a['title'] }}</h3>
                    <p class="relative text-sm text-gray-400 leading-relaxed mb-4">{{ $a['desc'] }}</p>
                    <span class="relative inline-flex items-center gap-1.5 text-xs font-semibold" style="color: {{ $a['color'] }};">
                        Learn more <i class="fas fa-arrow-right text-[10px] group-hover:translate-x-0.5 transition"></i>
                    </span>
                </a>
            @endforeach
        </div>
    </div>
</section>

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
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c3)">06 · Built for you</div>
            <h2 id="audience-h" class="reveal rd-1 text-3xl sm:text-4xl lg:text-5xl font-bold tracking-tight mb-4">
                Built for <span class="grad-text">creators, brands &amp; networking pros.</span>
            </h2>
            <p class="reveal rd-2 text-gray-400">Pick the one that fits you &mdash; the same all-in-one toolkit powers all three.</p>
        </div>

        <div class="grid md:grid-cols-3 gap-5">
            @foreach($__audiences as $i => $a)
                <article class="audience-card reveal rd-{{ $i + 1 }} glass rounded-3xl p-7 tilt relative overflow-hidden flex flex-col">
                    <div class="aud-blob absolute -top-16 -right-16 w-48 h-48 rounded-full opacity-25" style="background:radial-gradient(circle, {{ $a['color'] }}, transparent 70%);animation-delay:{{ $i * 1.2 }}s;"></div>
                    <div class="aud-icon relative w-14 h-14 rounded-2xl flex items-center justify-center mb-5" style="background: linear-gradient(135deg, {{ $a['color'] }}, var(--c2)); box-shadow: 0 12px 30px -10px {{ $a['color'] }};animation-delay:{{ $i * 0.4 }}s;">
                        <i class="fas {{ $a['icon'] }} text-xl text-white" style="animation-delay:{{ $i * 0.5 }}s;"></i>
                    </div>
                    <div class="relative text-[11px] font-bold uppercase tracking-wider mb-2" style="color: {{ $a['color'] }};">{{ $a['eyebrow'] }}</div>
                    <h3 class="relative text-xl font-bold mb-3 leading-snug">{!! $a['title'] !!}</h3>
                    <p class="relative text-sm text-gray-400 leading-relaxed mb-6 flex-1">{!! $a['desc'] !!}</p>
                    <button type="button" @click="authTab='register'; authOpen=true" class="relative btn-bounce inline-flex items-center justify-center gap-2 px-5 py-2.5 grad-bar text-white rounded-full text-sm font-bold self-start">
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
            background: linear-gradient(90deg, rgba(124,58,237,0), #1bd4d9 18%, #7c3aed 50%, #e94e8c 82%, rgba(233,78,140,0));
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
        background: linear-gradient(135deg, var(--hiw-color, #7c3aed), #ec4899);
        -webkit-background-clip: text; background-clip: text; color: transparent; opacity: .14;
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
        background: linear-gradient(135deg, rgba(124,58,237,.18), rgba(236,72,153,.14), rgba(34,211,238,.12));
        border: 1px solid rgba(255,255,255,.08);
    }
    .hiw-cta-wrap::before {
        content:""; position:absolute; inset:-1px; border-radius:inherit; pointer-events:none;
        background: conic-gradient(from 180deg at 50% 50%, #7c3aed, #ec4899, #22d3ee, #7c3aed);
        opacity:.18; filter: blur(20px);
    }
</style>
<section id="how-it-works" class="py-20 lg:py-28 relative overflow-hidden">
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-14 max-w-3xl mx-auto">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c2)">07 · How it works</div>
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
                    <div class="hiw-icon-wrap" style="background: linear-gradient(135deg, {{ $s[5] }}, var(--c2));"><i class="fas {{ $s[4] }} text-xl text-white"></i></div>
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
                    <button type="button" @click="authTab='register'; authOpen=true" class="btn-bounce btn-glow inline-flex items-center gap-2 px-7 py-3.5 grad-bar text-white rounded-full text-sm font-bold">
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

{{-- ============================ WORKSPACE & TEAM ============================ --}}
<section id="workspace-team" class="py-24 lg:py-32 relative overflow-hidden">
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-14 max-w-3xl mx-auto">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c1)">08 · Workspace &amp; Team</div>
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
                            <div class="w-11 h-11 rounded-xl flex items-center justify-center mb-3" style="background: linear-gradient(135deg, {{ $f[1] }}, var(--c2)); box-shadow: 0 12px 30px -12px {{ $f[1] }};">
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
                    <div class="absolute -top-16 -right-16 w-56 h-56 rounded-full opacity-30" style="background:radial-gradient(circle,var(--c2),transparent 70%);"></div>
                    <div class="absolute -bottom-20 -left-20 w-64 h-64 rounded-full opacity-20" style="background:radial-gradient(circle,var(--c3),transparent 70%);"></div>

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
    <div class="absolute inset-0 -z-10" style="background:radial-gradient(60% 50% at 80% 30%, rgba(233,78,140,.15), transparent 70%);"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-14 max-w-3xl mx-auto">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c3)">09 · Buzz</div>
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
                    <div class="absolute -bottom-20 -left-20 w-64 h-64 rounded-full opacity-30" style="background:radial-gradient(circle,var(--c3),transparent 70%);"></div>
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
                                        <img src="/images/hero-roles/role_designer-200.jpg" alt="Sara">
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
                                        <img src="/images/hero-roles/thumb_design-320.jpg" alt="Lightroom Pack">
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
                            <div class="w-11 h-11 rounded-xl flex items-center justify-center mb-3" style="background: linear-gradient(135deg, {{ $f[1] }}, var(--c3)); box-shadow: 0 12px 30px -12px {{ $f[1] }};">
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

{{-- ============================ TESTIMONIAL MARQUEE ============================ --}}
<section class="py-20 lg:py-24 relative overflow-hidden">
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c5)">10 · Social proof</div>
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
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c4)">11 · FAQ</div>
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

{{-- ============================ BY THE NUMBERS (stats strip) ============================ --}}
<section id="stats" class="py-12 lg:py-16 relative overflow-hidden" aria-label="By the numbers">
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('public.partials.marketing-stats')
    </div>
</section>

{{-- ============================ FEATURED POSTS CAROUSEL ============================ --}}
@if(!empty($featuredBlogPosts) && $featuredBlogPosts->count())
<section class="pt-14 pb-12 lg:pt-16 lg:pb-14 relative overflow-hidden">
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
                        <div class="aspect-[16/9]" style="background:linear-gradient(135deg, rgba(124,58,237,.25), rgba(56,189,248,.18));"></div>
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

{{-- ============================ FREE HERE / PAID THERE (lead-in to compare) ============================ --}}
<section class="pt-4 pb-2 lg:pt-8 lg:pb-4 relative overflow-hidden" aria-label="Free here, paid elsewhere">
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="reveal glass rounded-3xl px-6 py-6 sm:px-8 sm:py-7 flex flex-col lg:flex-row items-center gap-5 lg:gap-7">
            <div class="flex items-center gap-3 shrink-0">
                <span class="w-11 h-11 rounded-2xl flex items-center justify-center grad-bar shadow-lg shadow-violet-500/30">
                    <i class="fas fa-gift text-white"></i>
                </span>
                <div class="text-left">
                    <div class="text-[11px] font-bold uppercase tracking-wider" style="color:var(--c5)">Free here, paid there</div>
                    <div class="text-base sm:text-lg font-bold leading-tight">What costs extra elsewhere is on the <span class="grad-text">Free Forever</span> plan.</div>
                </div>
            </div>
            <div class="flex flex-wrap items-center justify-center lg:justify-end gap-2 lg:ml-auto">
                @foreach([
                    ['fa-infinity',  'Unlimited links'],
                    ['fa-message',   'Built-in DMs'],
                    ['fa-bolt',      'AI Coach'],
                    ['fa-mobile-screen', 'Native mobile app'],
                ] as [$ic, $lbl])
                    <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/[.06] border border-white/10 text-xs sm:text-sm font-semibold text-white">
                        <i class="fas {{ $ic }} text-[11px]" style="color:var(--c1)"></i> {{ $lbl }}
                    </span>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- ============================ TRUST BAND (security & reliability) ============================ --}}
<section class="py-12 lg:py-14 relative overflow-hidden" aria-label="Trust signals">
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
@include('public.partials._compare', ['compact' => true, 'eyebrowOverride' => '12 · How we compare'])
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

{{-- ============================ PRICING ============================ --}}
<section id="pricing" class="py-20 lg:py-24 relative overflow-hidden" x-data="{ billing: 'monthly' }">
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12 max-w-3xl mx-auto">
            <div class="reveal inline-flex items-center gap-2 text-xs font-bold uppercase tracking-[.2em] mb-3 px-3 py-1 rounded-full" style="color:var(--c1); background: rgba(124,58,237,0.10);">
                <span class="inline-block w-1.5 h-1.5 rounded-full" style="background:var(--c1)"></span>
                13 · Pricing
            </div>
            <h2 class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mb-5">Simple, <span class="grad-text">transparent pricing.</span></h2>
            <p class="reveal rd-2 text-lg text-gray-400">Start free. Upgrade only when you outgrow it.</p>
        </div>

        <div class="flex flex-col sm:flex-row items-center justify-center gap-4 mb-8">
            @include('public.pricing._currency_badge', [
                'currency'       => $currency ?? 'USD',
                'currencySource' => $currencySource ?? \App\Services\PricingResolver::SOURCE_GEO,
                'user'           => $user ?? auth()->user(),
                'switchRoute'    => 'upgrade.public.switch-currency',
                'compact'        => true,
            ])

            {{-- Monthly / Annual billing toggle --}}
            <div class="inline-flex items-center gap-1 p-1 rounded-full glass border border-white/10" role="tablist" aria-label="Billing cadence">
                <button type="button" role="tab" :aria-selected="billing === 'monthly'" @click="billing = 'monthly'"
                        :class="billing === 'monthly' ? 'grad-bar text-white shadow-lg shadow-[#7c3aed]/30' : 'text-gray-300 hover:text-white'"
                        class="px-4 py-1.5 rounded-full text-xs font-bold uppercase tracking-wider transition-all">
                    Monthly
                </button>
                <button type="button" role="tab" :aria-selected="billing === 'annual'" @click="billing = 'annual'"
                        :class="billing === 'annual' ? 'grad-bar text-white shadow-lg shadow-[#7c3aed]/30' : 'text-gray-300 hover:text-white'"
                        class="px-4 py-1.5 rounded-full text-xs font-bold uppercase tracking-wider transition-all flex items-center gap-1.5">
                    Annual
                    <span class="px-1.5 py-0.5 rounded-full text-[9px] font-extrabold bg-emerald-400/20 text-emerald-300 border border-emerald-400/40">Save 2 months</span>
                </button>
            </div>
        </div>

        @php
            $freePlans = collect($plans)->filter(fn($p) => !empty($p['is_free']))->values();
            $paidPlans = collect($plans)->reject(fn($p) => !empty($p['is_free']))->values();
            $cheapestPaid = $paidPlans->sortBy(fn($p) => (int) ($p['monthly']['amount_minor'] ?? PHP_INT_MAX))->first();
            $premiumHighlights = [
                ['fa-infinity',          'Unlimited links & bio pages'],
                ['fa-chart-line',        'Advanced analytics & A/B tests'],
                ['fa-users',             'Team seats & roles'],
                ['fa-globe',             'Custom domains'],
                ['fa-robot',             'AI Coach + AI replies'],
                ['fa-shield-halved',     'Priority support'],
            ];
        @endphp
        <div class="grid md:grid-cols-2 gap-6 max-w-4xl mx-auto">
            @foreach($freePlans as $i => $plan)
                @php $featured = false; $f = $plan['features']; @endphp
                <div class="reveal rd-{{ $i + 1 }} lift group relative rounded-3xl p-8 transition-all duration-300 hover:-translate-y-1 glass hover:shadow-xl hover:shadow-[#7c3aed]/10 overflow-hidden" style="border: 1px solid rgba(255,255,255,0.08);">
                    {{-- Animated background blobs --}}
                    <div class="absolute -top-24 -right-24 w-72 h-72 rounded-full opacity-25 blur-3xl pointer-events-none" style="background: radial-gradient(circle, var(--c2), transparent 70%); animation: floatA 9s ease-in-out infinite;"></div>
                    <div class="absolute -bottom-24 -left-24 w-72 h-72 rounded-full opacity-20 blur-3xl pointer-events-none" style="background: radial-gradient(circle, var(--c4), transparent 70%); animation: floatB 11s ease-in-out infinite;"></div>
                    {{-- Sparkles --}}
                    <span class="free-spark" style="top:14%;left:82%; animation-delay:0s"></span>
                    <span class="free-spark" style="top:46%;left:6%;  animation-delay:1.4s"></span>
                    <span class="free-spark" style="top:70%;left:88%; animation-delay:.7s"></span>

                    <div class="relative">
                    <div class="text-xs font-bold uppercase tracking-wider mb-3 text-gray-400 flex items-center gap-2">
                        <span class="inline-flex w-5 h-5 rounded-full grad-bar items-center justify-center"><i class="fas fa-gift text-[8px] text-white"></i></span>
                        {{ $plan['name'] }}
                    </div>

                    @if($plan['is_free'])
                        <div class="mb-4 flex items-center gap-4 flex-wrap">
                            <div class="free-pill-wrap relative inline-flex">
                                {{-- Pulsing glow halo --}}
                                <span class="absolute -inset-2 rounded-3xl opacity-40 blur-xl pointer-events-none" style="background: linear-gradient(135deg, var(--c1), var(--c2), var(--c3), var(--c4)); animation: pulseDot 2.4s ease-in-out infinite;"></span>
                                {{-- The actual pill --}}
                                <span class="relative inline-flex items-center px-5 py-2 rounded-2xl text-3xl sm:text-4xl font-extrabold tracking-tight text-white" style="background: linear-gradient(135deg, var(--c2), var(--c3) 50%, var(--c4)); letter-spacing: 0.05em;">
                                    FREE
                                    <i class="fas fa-sparkles ml-1.5 text-xs" style="animation: wiggle 2s ease-in-out infinite;"></i>
                                </span>
                            </div>
                            <div class="leading-tight">
                                <div class="text-[10px] uppercase tracking-wider font-bold flex items-center gap-1.5" style="color:#4ade80">
                                    <span class="relative flex w-2 h-2"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-400"></span></span>
                                    Forever
                                </div>
                                <div class="text-[11px] text-gray-400 mt-0.5">No card. No expiry.</div>
                            </div>
                        </div>
                    @else
                        @php
                            // Annual = 10× monthly (i.e. 2 months free) per the
                            // FAQ promise. Pure UI estimate; checkout still
                            // controls the actual cadence.
                            $monthlyMinor = (int) ($plan['monthly']['amount_minor'] ?? 0);
                            $currencyCode = (string) ($plan['monthly']['currency'] ?? 'USD');
                            $annualEquivMonthlyMinor = (int) round($monthlyMinor * 10 / 12);
                            $annualTotalMinor = $monthlyMinor * 10;
                            $annualEquivPretty = \App\Services\PricingResolver::money($annualEquivMonthlyMinor, $currencyCode);
                            $annualTotalPretty = \App\Services\PricingResolver::money($annualTotalMinor, $currencyCode);
                        @endphp
                        <div class="text-[11px] uppercase tracking-wider font-semibold {{ $featured ? 'text-white/70' : 'text-gray-400' }} mb-1">Starts at</div>
                        <div x-show="billing === 'monthly'" class="text-5xl font-bold mb-1 text-white leading-none">
                            {{ $plan['monthly']['formatted'] }}<span class="text-lg font-medium {{ $featured ? 'text-white/60' : 'text-gray-500' }}">/mo</span>
                        </div>
                        <div x-show="billing === 'annual'" x-cloak class="mb-1 leading-none">
                            <div class="text-5xl font-bold text-white flex items-baseline gap-2">
                                {{ $annualEquivPretty }}<span class="text-lg font-medium {{ $featured ? 'text-white/60' : 'text-gray-500' }}">/mo</span>
                            </div>
                            <div class="mt-1.5 flex items-center gap-2 text-[11px] {{ $featured ? 'text-white/80' : 'text-gray-400' }}">
                                <span class="line-through opacity-70">{{ $plan['monthly']['formatted'] }}/mo</span>
                                <span class="px-1.5 py-0.5 rounded-full text-[9px] font-extrabold bg-emerald-400/25 text-emerald-200 border border-emerald-400/40">2 months free</span>
                            </div>
                            <div class="text-[11px] mt-1 {{ $featured ? 'text-white/70' : 'text-gray-500' }}">Billed yearly · {{ $annualTotalPretty }}/yr</div>
                        </div>
                        @if(!empty($plan['tax']))
                            @foreach($plan['tax']['tax_breakdown'] as $line)
                                <div class="text-[11px] {{ $featured ? 'text-white/70' : 'text-gray-500' }}">+ {{ $line['label'] }} {{ \App\Services\PricingResolver::money((int) $line['amount_minor'], $plan['monthly']['currency']) }}</div>
                            @endforeach
                            <div class="text-[11px] font-semibold {{ $featured ? 'text-white' : 'text-gray-300' }} mb-1">Total {{ \App\Services\PricingResolver::money((int) $plan['tax']['grand_total_minor'], $plan['monthly']['currency']) }}/mo</div>
                            @if(!empty($plan['tax']['reverse_charge_note']))
                                <div class="text-[10px] italic {{ $featured ? 'text-white/60' : 'text-gray-500' }} mb-1">{{ $plan['tax']['reverse_charge_note'] }}</div>
                            @endif
                        @else
                            <div class="text-[11px] {{ $featured ? 'text-white/60' : 'text-gray-500' }} mb-1">+ taxes as applicable (shown at checkout)</div>
                        @endif
                    @endif
                    @if(!$plan['is_free'] && !empty($plan['description']))
                        <div class="text-sm mb-5 text-gray-400">{{ $plan['description'] }}</div>
                    @endif

                    {{-- Feature blocks (richer than plain bullets) --}}
                    <div class="space-y-2 mb-5">
                        @foreach(['max_links' => ['fa-link', 'links'], 'max_biolinks' => ['fa-id-card', 'bio pages'], 'storage_limit_mb' => ['fa-database', 'MB storage'], 'contacts_max' => ['fa-address-book', 'contacts']] as $key => $meta)
                            @if(isset($f[$key]))
                                <div class="free-row flex items-center gap-3 p-2.5 rounded-xl bg-white/[.04] border border-white/5 hover:border-white/15 hover:bg-white/[.06] transition group/row">
                                    <span class="w-9 h-9 rounded-lg flex items-center justify-center grad-bar shrink-0 group-hover/row:scale-110 transition" style="box-shadow: 0 8px 20px -8px rgba(124,58,237,.6);">
                                        <i class="fas {{ $meta[0] }} text-white text-[12px]"></i>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-sm font-bold text-white leading-tight">{{ (int) $f[$key] === -1 ? 'Unlimited' : number_format((int) $f[$key]) }} {{ $meta[1] }}</div>
                                        <div class="text-[10px] text-gray-500 uppercase tracking-wider">Included on Free</div>
                                    </div>
                                    <i class="fas fa-check text-xs" style="color:var(--c1)"></i>
                                </div>
                            @endif
                        @endforeach
                    </div>

                    {{-- 0% reassurance strip --}}
                    <div class="grid grid-cols-3 gap-2 mb-6 px-3 py-3 rounded-xl bg-emerald-500/[.06] border border-emerald-500/15">
                        @foreach([['0%', 'Card'], ['0%', 'Trial'], ['100%', 'Yours']] as $z)
                            <div class="text-center">
                                <div class="text-base font-extrabold text-emerald-300 leading-none">{{ $z[0] }}</div>
                                <div class="text-[9px] uppercase tracking-wider text-emerald-200/70 mt-0.5">{{ $z[1] }}</div>
                            </div>
                        @endforeach
                    </div>

                    <button type="button" @click="authTab='register'; authOpen=true" class="btn-bounce block w-full py-3.5 text-center rounded-full text-sm font-bold transition-transform group-hover:scale-[1.02] grad-bar text-white">
                        Get started free <i class="fas fa-arrow-right text-xs ml-1"></i>
                    </button>
                    </div>{{-- /.relative --}}
                </div>
            @endforeach

            {{-- Premium promo card. Outer wrapper isolates the badge so the inner
                 link can use overflow-hidden for blob effects without clipping it. --}}
            <div class="relative reveal rd-2 md:scale-[1.03]">
                {{-- Floating badge --}}
                <div class="absolute -top-3.5 left-1/2 -translate-x-1/2 z-20 pointer-events-none">
                    <div class="px-4 py-1.5 bg-white text-[#7c3aed] text-[11px] font-extrabold rounded-full uppercase tracking-wider shadow-lg flex items-center gap-1.5" style="box-shadow: 0 8px 24px -8px rgba(124,58,237,.6), 0 0 0 4px rgba(255,255,255,.08);">
                        <i class="fas fa-crown text-[10px]" style="animation: wiggle 2.4s ease-in-out infinite; transform-origin: 50% 80%;"></i>
                        Premium
                    </div>
                </div>

                <a href="{{ route('site.pricing') }}"
                   class="lift group relative block rounded-3xl p-8 pt-9 text-white shadow-2xl shadow-[#7c3aed]/40 hover:shadow-[#7c3aed]/60 transition-all duration-300 hover:-translate-y-1 overflow-hidden"
                   style="background: linear-gradient(150deg, var(--c2), var(--c3) 60%, var(--c4));">
                    {{-- Ambient blobs --}}
                    <div class="absolute -top-16 -right-16 w-56 h-56 rounded-full bg-white/15 blur-3xl pointer-events-none" style="animation: floatA 10s ease-in-out infinite;"></div>
                    <div class="absolute -bottom-16 -left-16 w-56 h-56 rounded-full bg-white/10 blur-3xl pointer-events-none" style="animation: floatB 12s ease-in-out infinite;"></div>
                    {{-- Diagonal shimmer sweep --}}
                    <div class="absolute inset-0 prem-shimmer pointer-events-none"></div>
                    {{-- Sparkles --}}
                    <span class="prem-spark" style="top:18%;left:88%; animation-delay:0s"></span>
                    <span class="prem-spark" style="top:50%;left:6%;  animation-delay:1.1s"></span>
                    <span class="prem-spark" style="top:78%;left:84%; animation-delay:.6s"></span>

                    <div class="relative">
                        <div class="text-xs font-bold uppercase tracking-wider text-white/80 mb-3">Premium features</div>
                        <h3 class="text-2xl sm:text-3xl font-extrabold leading-tight mb-2">
                            Built for serious <span class="relative inline-block">creators &amp; teams.<span class="absolute left-0 right-0 -bottom-1 h-[3px] rounded-full bg-white/40"></span></span>
                        </h3>
                        <p class="text-sm text-white/80 mb-6">Everything in Free, plus the tools you grow into.</p>

                        {{-- Each feature is its own animated block --}}
                        <div class="grid grid-cols-2 gap-2 mb-6">
                            @foreach($premiumHighlights as $hi => $h)
                                <div class="prem-feat relative rounded-xl p-3 bg-white/[.12] backdrop-blur-sm border border-white/15 hover:bg-white/[.22] hover:-translate-y-0.5 transition-all duration-200" style="animation-delay: {{ $hi * 90 }}ms">
                                    <span class="absolute top-1.5 right-2 text-[8px] font-bold text-white/45 tracking-wider">0{{ $hi + 1 }}</span>
                                    <div class="flex items-start gap-2">
                                        <span class="prem-feat-ico w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center shrink-0 transition">
                                            <i class="fas {{ $h[0] }} text-[13px]"></i>
                                        </span>
                                        <span class="text-[12px] font-semibold leading-snug pt-1">{{ $h[1] }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if($cheapestPaid)
                            <div class="flex items-center justify-between gap-3 mb-5 p-3 rounded-xl bg-white/15 backdrop-blur-sm border border-white/20">
                                <div class="leading-tight">
                                    <div class="text-[10px] uppercase tracking-wider font-bold text-white/70">Plans starting from</div>
                                    <div class="flex items-baseline gap-1">
                                        <span class="text-2xl font-extrabold">{{ $cheapestPaid['monthly']['formatted'] }}</span>
                                        <span class="text-xs text-white/70">/mo</span>
                                    </div>
                                </div>
                                <span class="text-[10px] px-2 py-1 rounded-full bg-emerald-300/30 text-emerald-50 font-bold uppercase tracking-wider whitespace-nowrap">Cancel anytime</span>
                            </div>
                        @endif

                        <span class="btn-bounce inline-flex items-center justify-center gap-2 w-full py-3.5 text-center rounded-full text-sm font-bold bg-white text-[#7c3aed] hover:bg-gray-100 transition-transform group-hover:scale-[1.02]">
                            Explore premium plans <i class="fas fa-arrow-right text-xs transition-transform group-hover:translate-x-1"></i>
                        </span>
                    </div>
                </a>
            </div>
        </div>

        {{-- Pricing trust strip — sits directly under the cards as a slim reassurance row --}}
        <div class="reveal mt-8 max-w-4xl mx-auto flex flex-wrap items-center justify-center gap-x-6 gap-y-3 text-xs sm:text-sm text-gray-300">
            @foreach([
                ['fa-shield-halved', 'Cancel any time'],
                ['fa-receipt', 'Tax-inclusive invoices'],
            ] as $t)
                <span class="inline-flex items-center gap-2"><i class="fas {{ $t[0] }} text-[11px]" style="color:var(--c1)"></i>{{ $t[1] }}</span>
            @endforeach
        </div>

        {{-- Slim "more pricing details" link row — replaces the previous oversized drill-down card. --}}
        <div class="reveal mt-6 max-w-4xl mx-auto flex flex-wrap items-center justify-center gap-x-5 gap-y-2 text-sm text-gray-400">
            <span class="text-gray-500">More pricing details:</span>
            <a href="{{ route('site.pricing') }}" class="inline-flex items-center gap-1.5 text-violet-300 hover:text-violet-200 font-semibold transition">
                <i class="fas fa-tags text-[11px]"></i> Compare all plans
            </a>
            <span class="text-gray-700">·</span>
            <a href="{{ route('site.pricing', ['view' => 'coins']) }}" class="inline-flex items-center gap-1.5 text-violet-300 hover:text-violet-200 font-semibold transition">
                <i class="fas fa-coins text-[11px] text-amber-400"></i> Coin packages
            </a>
            <span class="text-gray-700">·</span>
            <a href="{{ route('site.premium-features') }}" class="inline-flex items-center gap-1.5 text-violet-300 hover:text-violet-200 font-semibold transition">
                <i class="fas fa-star text-[11px] text-amber-300"></i> Premium features
            </a>
        </div>
    </div>
</section>

{{-- ============================ FINAL CTA ============================ --}}
{{-- Visually distinct from the gradient hero blocks above: a single asymmetric
     glass card with a left-aligned headline + right-aligned action, so the
     closing run reads as "cards → trust strip → links → one final CTA". --}}
<section class="py-16 lg:py-20 relative overflow-hidden">
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
                    <button type="button" @click="authTab='register'; authOpen=true" class="btn-bounce btn-glow inline-flex items-center justify-center gap-2 px-8 py-4 grad-bar text-white rounded-full text-base font-bold whitespace-nowrap">
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

{{-- ============================ SUBSCRIBE BLOCK ============================ --}}
@include('public.partials.subscribe-block', [
    'heading' => 'Get the 1INME drop, your way.',
    'subtext' => 'Pick the channel that fits — email, WhatsApp Channel, or DM. Product updates, growth playbooks for creators, and the occasional template — once a month.',
    'source'  => 'home',
])

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

        // Mouse-parallax for hero phone
        const hero = document.querySelector('section[aria-labelledby="hero-h"]');
        const phone = hero?.querySelector('.phone');
        if (hero && phone && window.matchMedia('(prefers-reduced-motion: no-preference)').matches) {
            hero.addEventListener('mousemove', (e) => {
                const rect = hero.getBoundingClientRect();
                const x = (e.clientX - rect.left) / rect.width - 0.5;
                const y = (e.clientY - rect.top) / rect.height - 0.5;
                phone.style.transform = `translate(${x * 18}px, ${y * 14}px) rotate(${x * 4}deg)`;
            });
            hero.addEventListener('mouseleave', () => { phone.style.transform = ''; });
        }
    });
</script>
@include('common.partials.cookie-consent', ['surface' => 'site'])
@include('common.partials.site-assistant', ['surface' => 'marketing'])
</body>
</html>
