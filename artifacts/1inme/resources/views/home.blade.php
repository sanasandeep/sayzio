<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>1INME — One link to everything.</title>
    <meta name="description" content="1INME is the all-in-one link platform: drag-and-drop biolinks, short links, dynamic QR codes, live geographic analytics, a Performance Coach, follower system, forms, social proof and more.">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: { extend: { fontFamily: { sans: ['Space Grotesk', 'sans-serif'] } } }
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
        body { font-family: 'Space Grotesk', sans-serif; color: #fff; }
        [x-cloak] { display: none !important; }

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

        /* ============ Reveal-on-scroll (bouncy) ============ */
        .reveal { opacity: 1; transform: none; transition: opacity .7s cubic-bezier(.16,1,.3,1), transform .7s cubic-bezier(.34,1.56,.64,1); }
        .js .reveal:not(.visible) { opacity: 0; transform: translateY(40px) scale(.94); }
        .reveal.visible { opacity: 1; transform: none; }
        .rd-1 { transition-delay: .08s }  .rd-2 { transition-delay: .18s }
        .rd-3 { transition-delay: .28s }  .rd-4 { transition-delay: .38s }
        .rd-5 { transition-delay: .48s }  .rd-6 { transition-delay: .58s }

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
        .role-word.word-out { animation: wordOut .35s ease both; }
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
        .stack-out .stack-card { animation: cardOut .35s ease forwards; animation-delay: 0ms; }
        @keyframes cardOut {
            0%   { opacity: 1; transform: translateY(0) scale(1); filter: blur(0); }
            100% { opacity: 0; transform: translateY(-24px) scale(.92); filter: blur(4px); }
        }

        @media (max-width: 1023px) {
            .stack-3d { animation: none; transform: rotateX(0) rotateY(0); }
            .stack-inner { width: 280px; }
        }

        /* ============ Reduced motion ============ */
        @media (prefers-reduced-motion: reduce) {
            .reveal, .aurora b, .float-a, .float-b, .float-c, .wiggle, .spin-slow,
            .marquee, .marquee-rev, .eq i, .pulse-dot, .ring-pulse, .spark-line,
            .gauge-arc, .draw-line, .grad-text, .drift-a, .drift-b, .pop-in, .btn-glow::after,
            .stack-3d, .stack-card, .role-word {
                animation: none !important; transition: none !important; transform: none !important; opacity: 1 !important;
            }
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

        @media (max-width: 1023px) {
            .hero-phone-wrap { width: 260px; transform: none !important; }
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
                display: flex; gap: 8px; overflow-x: auto; scroll-snap-type: x mandatory;
                padding-bottom: 4px;
                scrollbar-width: none;
            }
            .hero-gallery::-webkit-scrollbar { display: none; }
            .hero-gallery-item { flex: 0 0 84px; scroll-snap-align: start; }
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
    </style>
</head>
<body class="overflow-x-hidden">

{{-- ============ Aurora background ============ --}}
<div class="aurora" aria-hidden="true"><b></b><b></b><b></b><b></b></div>

{{-- ============================ NAV ============================ --}}
<nav class="fixed top-0 inset-x-0 z-50 bg-[#0a0a14]/80 backdrop-blur-xl border-b border-white/5"
     x-data="{ mobileOpen: false, authOpen: false, authTab: 'login' }" role="banner">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <a href="{{ route('home') }}" class="inline-flex items-center" aria-label="1INME home">
                @include('common.partials.brand-logo', ['height' => 'h-9'])
            </a>
            <div class="hidden md:flex items-center gap-7" role="navigation" aria-label="Primary">
                <a href="#features" class="text-sm font-medium text-gray-300 hover:text-white">Features</a>
                <a href="#how-it-works" class="text-sm font-medium text-gray-300 hover:text-white">How it works</a>
                <a href="{{ route('site.discovery') }}" class="text-sm font-medium text-gray-300 hover:text-white">Discover</a>
                <a href="{{ route('site.creators-feed') }}" class="text-sm font-medium text-gray-300 hover:text-white">Feed</a>
                <a href="#pricing" class="text-sm font-medium text-gray-300 hover:text-white">Pricing</a>
            </div>
            <div class="hidden md:flex items-center gap-3">
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
            <a href="#features" @click="mobileOpen=false" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">Features</a>
            <a href="#how-it-works" @click="mobileOpen=false" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">How it works</a>
            <a href="{{ route('site.discovery') }}" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">Discover</a>
            <a href="{{ route('site.creators-feed') }}" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">Feed</a>
            <a href="#pricing" @click="mobileOpen=false" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">Pricing</a>
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
    @include('public.partials.auth-modal')
</nav>

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
                ['icon' => 'fab fa-youtube',           'color' => '#ff0033', 'title' => 'Latest video',       'sub' => 'New drop · 2 days ago',   'thumb' => '/images/hero-roles/thumb_youtube.jpg'],
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
    ];

    // Visible block-type icons cluster shown in the hero.
    $heroBlockIcons = [
        ['i' => 'fab fa-youtube',            'c' => '#ff0033', 'l' => 'YouTube'],
        ['i' => 'fab fa-spotify',            'c' => '#1ed760', 'l' => 'Spotify'],
        ['i' => 'fas fa-store',              'c' => '#ff8a3c', 'l' => 'Merch'],
        ['i' => 'fas fa-link',               'c' => '#1bd4d9', 'l' => 'Link'],
        ['i' => 'fas fa-qrcode',             'c' => '#7c3aed', 'l' => 'QR'],
        ['i' => 'fas fa-music',              'c' => '#e94e8c', 'l' => 'Music'],
        ['i' => 'fas fa-video',              'c' => '#ffc845', 'l' => 'Video'],
        ['i' => 'fas fa-image',              'c' => '#1bd4d9', 'l' => 'Image'],
        ['i' => 'fas fa-microphone',         'c' => '#ff8a3c', 'l' => 'Podcast'],
        ['i' => 'fas fa-calendar-check',     'c' => '#7c3aed', 'l' => 'Calendar'],
    ];
@endphp

<section class="relative pt-28 pb-20 lg:pt-36 lg:pb-28 overflow-hidden" aria-labelledby="hero-h">
    {{-- Drifting confetti --}}
    <div class="confetti drift-a" style="left:8%;  bottom:-20vh;"><div class="w-3 h-3 rounded-sm" style="background:var(--c1)"></div></div>
    <div class="confetti drift-b" style="left:18%; bottom:-30vh; animation-delay:-3s"><div class="w-2 h-6 rounded-full" style="background:var(--c3)"></div></div>
    <div class="confetti drift-a" style="left:78%; bottom:-25vh; animation-delay:-6s"><div class="w-4 h-4 rounded-full" style="background:var(--c4)"></div></div>
    <div class="confetti drift-b" style="left:88%; bottom:-15vh; animation-delay:-9s"><div class="w-3 h-3 rotate-45" style="background:var(--c5)"></div></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-2 gap-14 items-center">
            <div class="text-center lg:text-left">
                <div class="reveal inline-flex items-center gap-2 px-4 py-1.5 glass rounded-full text-xs font-semibold mb-6">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full rounded-full" style="background:var(--c1)"></span>
                        <span class="ring-pulse" style="inset:0;background:var(--c1);"></span>
                    </span>
                    <span class="grad-text">Built for every kind of you · Live analytics · QR codes</span>
                </div>

                <h1 id="hero-h" class="reveal rd-1 text-5xl sm:text-6xl lg:text-7xl font-bold leading-[1.05] tracking-tight mb-6">
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

                <p class="reveal rd-2 text-lg sm:text-xl text-gray-400 max-w-xl mx-auto lg:mx-0 mb-8 leading-relaxed">
                    Whoever you are, 1INME gives you <strong class="text-white">one link</strong> for everything: drag-and-drop biolink pages, branded short links, dynamic QR codes, plus live analytics and an AI-style Performance Coach.
                </p>

                <div class="reveal rd-3 flex flex-col sm:flex-row gap-3 justify-center lg:justify-start">
                    <button type="button" @click="authTab='register'; authOpen=true" class="btn-bounce btn-glow inline-flex items-center justify-center gap-2 px-8 py-4 grad-bar text-white rounded-full text-base font-bold">
                        Make mine free <i class="fas fa-arrow-right text-sm"></i>
                    </button>
                    <a href="#features" class="btn-bounce inline-flex items-center justify-center gap-2 px-8 py-4 glass-2 text-white rounded-full text-base font-semibold">
                        See it live
                    </a>
                </div>

                <div class="reveal rd-4 flex flex-wrap items-center gap-x-6 gap-y-2 mt-8 justify-center lg:justify-start text-sm text-gray-500">
                    <span class="flex items-center gap-1.5"><i class="fas fa-check" style="color:var(--c1)"></i> Free forever plan</span>
                    <span class="flex items-center gap-1.5"><i class="fas fa-check" style="color:var(--c3)"></i> No credit card</span>
                    <span class="flex items-center gap-1.5"><i class="fas fa-check" style="color:var(--c5)"></i> Set up in minutes</span>
                </div>
            </div>

            {{-- Hero phone mockup + gallery + block icons --}}
            <div class="reveal rd-2 relative stack-scene" id="hero-phone-scene">
                {{-- Decorative stickers --}}
                <div class="sticker top-2 left-4 w-10 h-10 rounded-full wiggle shake-hover opacity-80" style="background:var(--c4)"></div>
                <div class="sticker top-12 right-2 w-8 h-8 rounded-lg spin-slow opacity-70" style="background:var(--c5)"></div>
                <div class="sticker bottom-32 left-0 w-9 h-9 rounded-2xl wiggle opacity-80" style="background:var(--c1); animation-delay:-1s"></div>
                <div class="sticker top-1/3 -right-3 w-6 h-6 rounded-full wiggle opacity-80" style="background:var(--c3); animation-delay:-2s"></div>

                {{-- Phone mockup --}}
                <div class="relative flex items-center justify-center">
                    <div id="hero-phone-wrap" class="hero-phone-wrap float-c">
                        <div class="hero-phone">
                            <div id="hero-phone-screen" class="hero-phone-screen">
                                <div class="hero-notch"></div>
                                <div id="hero-stack" class="hero-phone-content" aria-hidden="true"></div>
                            </div>
                        </div>
                    </div>

                    {{-- Live visitors card (compact) --}}
                    <div class="float-b absolute top-2 right-0 sm:top-4 sm:-right-2 glass-2 rounded-2xl p-2.5 w-[150px] shadow-2xl shadow-[#1bd4d9]/20 z-10 hidden sm:block">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-[9px] uppercase tracking-wider text-gray-400 font-bold">Live visitors</span>
                            <span class="flex items-center gap-1 text-[9px] font-bold" style="color:var(--c1)"><span class="w-1.5 h-1.5 rounded-full pulse-dot" style="background:var(--c1)"></span>NOW</span>
                        </div>
                        <div class="text-xl font-bold">247</div>
                        <svg class="w-full h-6" viewBox="0 0 100 30" preserveAspectRatio="none">
                            <polyline class="spark-line" fill="none" stroke="url(#sl)" stroke-width="2.5" stroke-linecap="round" points="0,22 12,18 24,20 36,12 48,15 60,8 72,11 84,5 100,7"/>
                            <defs><linearGradient id="sl"><stop offset="0%" stop-color="#1bd4d9"/><stop offset="100%" stop-color="#e94e8c"/></linearGradient></defs>
                        </svg>
                    </div>

                    {{-- Performance Coach card (compact) --}}
                    <div class="float-c absolute bottom-4 -left-2 sm:bottom-8 sm:left-0 glass-2 rounded-2xl p-2.5 w-[180px] shadow-2xl shadow-[#7c3aed]/30 z-10 hidden sm:block" style="animation-delay:-2s">
                        <div class="flex items-center gap-2 mb-1.5">
                            <div class="w-8 h-8 rounded-xl flex items-center justify-center grad-bar"><i class="fas fa-bolt text-white text-xs"></i></div>
                            <div>
                                <div class="text-[9px] uppercase tracking-wider text-gray-400 font-bold">Performance Coach</div>
                                <div class="text-xs font-bold">Health score 87</div>
                            </div>
                        </div>
                        <div class="h-1.5 bg-white/10 rounded-full overflow-hidden">
                            <div class="h-full grad-bar rounded-full" style="width:87%"></div>
                        </div>
                    </div>
                </div>

                {{-- Category gallery --}}
                <div class="mt-6">
                    <div class="flex items-center justify-between mb-2 px-1">
                        <div class="text-[10px] font-bold uppercase tracking-[.18em] text-gray-400">Looks like a <span id="hero-gallery-label" class="grad-text">creator</span> page</div>
                        <div class="text-[10px] text-gray-500 hidden sm:block">Drop in any block</div>
                    </div>
                    <div id="hero-gallery" class="hero-gallery" aria-hidden="true"></div>
                </div>

                {{-- Block-type icons cluster --}}
                <div class="mt-5">
                    <div class="hero-blocks">
                        @foreach($heroBlockIcons as $idx => $bi)
                            <span class="hero-block-chip" style="--bdur:{{ 4 + ($idx % 5) * 0.6 }}s; --bdel:{{ -1 * ($idx * 0.35) }}s; --brot:{{ ($idx % 2 ? 4 : -4) }}deg;">
                                <i class="{{ $bi['i'] }}" style="color:{{ $bi['c'] }}"></i>{{ $bi['l'] }}
                            </span>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const ROLES   = @json($heroRoles);
            const word    = document.getElementById('hero-role-word');
            const sr      = document.getElementById('hero-role-sr');
            const stack   = document.getElementById('hero-stack');
            const screen  = document.getElementById('hero-phone-screen');
            const gallery = document.getElementById('hero-gallery');
            const galLbl  = document.getElementById('hero-gallery-label');
            const phoneWrap = document.getElementById('hero-phone-wrap');
            const phoneScene = document.getElementById('hero-phone-scene');
            if (!word || !stack) return;

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
                        ${pictureThumb(g.src, '', 120, 120, '(max-width: 1023px) 84px, 120px', g.alt || '')}
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
            function pictureThumb(src, cls, w, h, sizes, alt) {
                const base = heroImgBase(src);
                const altA = escapeHTML(alt || '');
                const sz   = escapeHTML(sizes || '(max-width: 640px) 50vw, 320px');
                return `<picture>`
                    + `<source type="image/webp" srcset="${escapeHTML(base)}-320.webp 320w, ${escapeHTML(base)}-640.webp 640w" sizes="${sz}">`
                    + `<source type="image/jpeg" srcset="${escapeHTML(base)}-320.jpg 320w, ${escapeHTML(base)}-640.jpg 640w" sizes="${sz}">`
                    + `<img class="${escapeHTML(cls)}" src="${escapeHTML(base)}-320.jpg" alt="${altA}" loading="lazy" decoding="async" width="${w}" height="${h}">`
                    + `</picture>`;
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
                              <i class="fab fa-youtube" style="color:#ff0033;font-size:18px"></i>
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
                              <i class="fab fa-spotify" style="color:#1ed760;font-size:18px"></i>
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
                              <i class="fab fa-linkedin" style="color:#0ea5e9;font-size:18px"></i>
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
                              <div class="fg"><div class="fv">312k</div><div class="fl"><i class="fab fa-instagram"></i> IG</div></div>
                              <div class="fg"><div class="fv">180k</div><div class="fl"><i class="fab fa-tiktok"></i> TT</div></div>
                              <div class="fg"><div class="fv">94k</div><div class="fl"><i class="fab fa-youtube"></i> YT</div></div>
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
                    + `<div class="hp-pill-row theme-block" style="--d:${last}ms">
                           <div class="hp-pill"><i class="fab fa-youtube" style="color:#ff0033"></i>YouTube</div>
                           <div class="hp-pill"><i class="fab fa-instagram" style="color:#e94e8c"></i>IG</div>
                           <div class="hp-pill"><i class="fab fa-tiktok"></i>TikTok</div>
                       </div>`
                    + `<div class="hp-cta theme-block" style="--d:${last+60}ms"><i class="fas fa-hand-holding-heart"></i>Tip · Join members</div>`;
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
                    + `<div class="hp-pill-row theme-block" style="--d:480ms">
                            <div class="hp-pill"><i class="fab fa-spotify" style="color:#1ed760"></i>Spotify</div>
                            <div class="hp-pill"><i class="fab fa-apple"></i>Apple</div>
                            <div class="hp-pill"><i class="fab fa-youtube" style="color:#ff5252"></i>YouTube</div>
                       </div>`;
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
                    + `<div class="hp-pill-row theme-block" style="--d:420ms">
                            <div class="hp-pill"><i class="fab fa-apple"></i>Apple</div>
                            <div class="hp-pill"><i class="fab fa-spotify" style="color:#1ed760"></i>Spotify</div>
                            <div class="hp-pill"><i class="fab fa-youtube" style="color:#ff5252"></i>YouTube</div>
                       </div>`
                    + `<div class="hp-cta dark theme-block" style="--d:480ms"><i class="fas fa-envelope-open-text"></i>Show notes &amp; newsletter</div>`;
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
                            <div class="lb"><span><i class="fab fa-instagram"></i> 312k</span><span><i class="fas fa-heart"></i> 28k</span></div>
                       </div>`
                    + `<div class="hp-grid-4 theme-block" style="--d:250ms">${posts}</div>`
                    + `<div class="hp-biz-cta theme-block" style="--d:320ms">
                            <div class="ic" style="background:#ffc84522;color:#fff"><i class="fas fa-handshake"></i></div>
                            <div class="bd"><div class="bt">Brand deals · Press kit</div><div class="bs">Rates · Past campaigns · Reach</div></div>
                            <i class="fas fa-arrow-right" style="opacity:.7"></i>
                       </div>`
                    + `<div class="hp-pill-row theme-block" style="--d:380ms">
                            <div class="hp-pill"><i class="fab fa-tiktok"></i>TikTok</div>
                            <div class="hp-pill"><i class="fab fa-youtube" style="color:#ff5252"></i>YouTube</div>
                            <div class="hp-pill"><i class="fab fa-snapchat" style="color:#fde047"></i>Snap</div>
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

            function paintRoleVisuals(role) {
                applyWallpaper(role);
                if (gallery) gallery.innerHTML = buildGalleryHTML(role);
                if (galLbl) galLbl.textContent = role.word.toLowerCase();
            }

            function setRole(role) {
                if (sr) sr.textContent = role.word;
                if (reduce) {
                    // Simple opacity crossfade fallback
                    word.classList.add('rm-out');
                    stack.classList.add('rm-out');
                    setTimeout(() => {
                        word.textContent = role.word;
                        stack.innerHTML = buildStackHTML(role);
                        paintRoleVisuals(role);
                        word.classList.remove('rm-out');
                        stack.classList.remove('rm-out');
                    }, 350);
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
                }, 360);
            }

            let i = 0;
            // Initial paint (no out animation)
            stack.innerHTML = buildStackHTML(ROLES[0]);
            word.textContent = ROLES[0].word;
            paintRoleVisuals(ROLES[0]);
            if (!reduce) word.classList.add('word-in');

            setInterval(() => {
                i = (i + 1) % ROLES.length;
                setRole(ROLES[i]);
            }, 4200);

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
                    <div class="col-span-12 sm:col-span-7 bg-[#0a0a14] rounded-2xl p-3 border border-white/5 space-y-2">
                        <div class="flex items-center gap-3 bg-white/5 rounded-xl p-3 border border-white/10 lift">
                            <i class="fas fa-grip-vertical text-gray-500 text-xs"></i>
                            <div class="w-9 h-9 rounded-lg flex items-center justify-center" style="background:rgba(27,212,217,.2)"><i class="fas fa-image text-sm" style="color:var(--c1)"></i></div>
                            <div class="flex-1 min-w-0"><div class="text-xs font-bold">Hero image</div><div class="text-[10px] text-gray-500">1200×630 · WEBP</div></div>
                        </div>
                        <div class="flex items-center gap-3 bg-white/5 rounded-xl p-3 border border-white/10 lift">
                            <i class="fas fa-grip-vertical text-gray-500 text-xs"></i>
                            <div class="w-9 h-9 rounded-lg flex items-center justify-center" style="background:rgba(124,58,237,.25)"><i class="fas fa-link text-sm" style="color:var(--c2)"></i></div>
                            <div class="flex-1 min-w-0"><div class="text-xs font-bold">Free Templates</div><div class="text-[10px] text-gray-500">jane.co/templates</div></div>
                        </div>
                        <div class="flex items-center gap-3 rounded-xl p-3 border-2 lift" style="background:rgba(233,78,140,.15);border-color:rgba(233,78,140,.4)">
                            <i class="fas fa-grip-vertical text-xs" style="color:var(--c3)"></i>
                            <div class="w-9 h-9 rounded-lg flex items-center justify-center" style="background:rgba(255,138,60,.25)"><i class="fas fa-store text-sm" style="color:var(--c4)"></i></div>
                            <div class="flex-1 min-w-0"><div class="text-xs font-bold">Shop Merch</div><div class="text-[10px] text-gray-500">22 products</div></div>
                            <span class="text-[9px] uppercase tracking-wider px-1.5 py-0.5 rounded font-bold text-white" style="background:var(--c3)">Live</span>
                        </div>
                        <div class="flex items-center gap-3 bg-white/5 rounded-xl p-3 border border-white/10 lift">
                            <i class="fas fa-grip-vertical text-gray-500 text-xs"></i>
                            <div class="w-9 h-9 rounded-lg flex items-center justify-center" style="background:rgba(255,200,69,.25)"><i class="fas fa-wpforms text-sm" style="color:var(--c5)"></i></div>
                            <div class="flex-1 min-w-0"><div class="text-xs font-bold">Newsletter form</div><div class="text-[10px] text-gray-500">Email + name</div></div>
                        </div>
                    </div>
                    <div class="col-span-12 sm:col-span-5 bg-[#0a0a14] rounded-2xl p-3 border border-white/5 flex items-center justify-center">
                        <div class="phone scale-90">
                            <div class="phone-screen p-3 pt-9 text-center text-white" style="background: linear-gradient(180deg, var(--c2), var(--c3));">
                                <div class="w-10 h-10 mx-auto rounded-full bg-white/30 flex items-center justify-center text-xs font-bold">JD</div>
                                <div class="mt-1 text-[10px] font-bold">@jane</div>
                                <div class="mt-2 space-y-1.5">
                                    <div class="bg-white/95 text-[#0e0e10] rounded-xl py-1.5 text-[10px] font-bold">Templates</div>
                                    <div class="rounded-xl py-1.5 text-[10px] font-bold text-[#0e0e10]" style="background:var(--c5)">Shop</div>
                                    <div class="bg-white/95 text-[#0e0e10] rounded-xl py-1.5 text-[10px] font-bold">Newsletter</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="reveal rd-2 lg:col-span-5 grid grid-cols-1 gap-6">
                <div class="glass rounded-3xl p-6 lift">
                    <div class="w-12 h-12 rounded-2xl flex items-center justify-center mb-4" style="background:rgba(27,212,217,.2)"><i class="fas fa-palette text-xl" style="color:var(--c1)"></i></div>
                    <h3 class="text-lg font-bold mb-2">Themes &amp; design controls</h3>
                    <p class="text-sm text-gray-400">Pick from beautiful presets, then tweak fonts, colours and layouts to match your vibe.</p>
                </div>
                <div class="glass rounded-3xl p-6 lift">
                    <div class="w-12 h-12 rounded-2xl flex items-center justify-center mb-4" style="background:rgba(124,58,237,.25)"><i class="fas fa-link text-xl" style="color:#c4b5fd"></i></div>
                    <h3 class="text-lg font-bold mb-2">Custom domains</h3>
                    <p class="text-sm text-gray-400">Connect your own domain on paid plans for short links and biolink pages.</p>
                </div>
                <div class="glass rounded-3xl p-6 lift">
                    <div class="w-12 h-12 rounded-2xl flex items-center justify-center mb-4" style="background:rgba(233,78,140,.2)"><i class="fas fa-mobile-screen text-xl" style="color:var(--c3)"></i></div>
                    <h3 class="text-lg font-bold mb-2">Mobile-first by default</h3>
                    <p class="text-sm text-gray-400">Every theme looks razor-sharp on small screens — that's where your audience is.</p>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ============================ 2 · SHARE ============================ --}}
<section class="py-24 lg:py-32 relative overflow-hidden">
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16 max-w-3xl mx-auto">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c3)">02 · Share</div>
            <h2 class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mb-5">
                Share your 1INME<br><span class="grad-text">anywhere you like.</span>
            </h2>
            <p class="reveal rd-2 text-lg text-gray-400">Branded short links and dynamic QR codes you can repoint at any time. Add your link to bios, posters, business cards, packaging — anywhere.</p>
        </div>

        <div class="grid md:grid-cols-3 gap-6">
            <div class="reveal rd-1 glass rounded-3xl p-7 tilt relative overflow-hidden">
                <div class="absolute -top-10 -right-10 w-40 h-40 rounded-full opacity-30" style="background:var(--c1)"></div>
                <div class="relative">
                    <div class="w-12 h-12 rounded-2xl flex items-center justify-center mb-4" style="background:rgba(27,212,217,.2)"><i class="fas fa-link text-xl" style="color:var(--c1)"></i></div>
                    <h3 class="text-xl font-bold mb-2">Branded short links</h3>
                    <p class="text-sm text-gray-400 mb-5">Custom slugs, UTM-ready, click tracking. Looks like you, not a random shortener.</p>
                    <div class="bg-[#0a0a14]/60 border border-white/10 rounded-xl px-3 py-2 font-mono text-xs flex items-center gap-2">
                        <i class="fas fa-link text-[10px]" style="color:var(--c1)"></i>
                        <span class="text-white">1inme.co/<span style="color:var(--c1)">spring-drop</span></span>
                    </div>
                </div>
            </div>

            <div class="reveal rd-2 glass rounded-3xl p-7 tilt relative overflow-hidden">
                <div class="absolute -top-10 -right-10 w-40 h-40 rounded-full opacity-30" style="background:var(--c3)"></div>
                <div class="relative flex flex-col items-center text-center">
                    <div class="w-12 h-12 rounded-2xl flex items-center justify-center mb-4" style="background:rgba(233,78,140,.2)"><i class="fas fa-qrcode text-xl" style="color:var(--c3)"></i></div>
                    <h3 class="text-xl font-bold mb-2">Dynamic QR codes</h3>
                    <p class="text-sm text-gray-400 mb-5">Print once, redirect forever. Change the destination without reprinting.</p>
                    <div class="w-32 h-32 bg-white rounded-xl p-2 wiggle">
                        <div class="w-full h-full" style="background-image:radial-gradient(#0e0e10 1.5px,transparent 1.5px);background-size:7px 7px;"></div>
                    </div>
                </div>
            </div>

            <div class="reveal rd-3 glass rounded-3xl p-7 tilt relative overflow-hidden">
                <div class="absolute -top-10 -right-10 w-40 h-40 rounded-full opacity-30" style="background:var(--c4)"></div>
                <div class="relative">
                    <div class="w-12 h-12 rounded-2xl flex items-center justify-center mb-4" style="background:rgba(255,138,60,.2)"><i class="fas fa-share-nodes text-xl" style="color:var(--c4)"></i></div>
                    <h3 class="text-xl font-bold mb-2">Channel-ready</h3>
                    <p class="text-sm text-gray-400 mb-5">Pre-made share cards for every channel. Pixels, UTM and OG ready out of the box.</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach(['fa-instagram'=>'#e94e8c','fa-tiktok'=>'#1bd4d9','fa-youtube'=>'#e94e8c','fa-x-twitter'=>'#7c3aed','fa-linkedin'=>'#1bd4d9','fa-facebook'=>'#7c3aed'] as $ic => $col)
                            <span class="w-9 h-9 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center text-sm shake-hover" style="color:{{ $col }}"><i class="fab {{ $ic }}"></i></span>
                        @endforeach
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
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-wider mb-1" style="color:var(--c1)">Live geo heatmap</div>
                        <h3 class="text-xl font-bold">247 visitors right now in 14 countries</h3>
                    </div>
                    <span class="flex items-center gap-1.5 text-xs font-bold px-3 py-1 rounded-full" style="background:rgba(27,212,217,.15);color:var(--c1)"><span class="w-1.5 h-1.5 rounded-full pulse-dot" style="background:var(--c1)"></span>LIVE</span>
                </div>
                <div class="relative aspect-[16/9] rounded-2xl overflow-hidden bg-[#0a0a14] border border-white/5">
                    <svg viewBox="0 0 320 180" class="w-full h-full opacity-40">
                        <ellipse cx="60" cy="70" rx="32" ry="20" fill="#7c3aed"/>
                        <ellipse cx="155" cy="55" rx="42" ry="22" fill="#7c3aed"/>
                        <ellipse cx="245" cy="78" rx="32" ry="18" fill="#7c3aed"/>
                        <ellipse cx="90" cy="120" rx="26" ry="14" fill="#7c3aed"/>
                        <ellipse cx="210" cy="125" rx="34" ry="18" fill="#7c3aed"/>
                    </svg>
                    {{-- Pulsing pins --}}
                    @foreach([['18%','35%','#1bd4d9'],['42%','22%','#e94e8c'],['68%','40%','#ffc845'],['28%','62%','#ff8a3c'],['72%','68%','#7c3aed'],['52%','55%','#1bd4d9']] as $i => $p)
                        <span class="absolute" style="left:{{ $p[0] }};top:{{ $p[1] }};">
                            <span class="ring-pulse" style="inset:0;width:14px;height:14px;background:{{ $p[2] }};animation-delay:{{ -$i*0.4 }}s"></span>
                            <span class="block w-2.5 h-2.5 rounded-full pulse-dot" style="background:{{ $p[2] }};animation-delay:{{ -$i*0.3 }}s"></span>
                        </span>
                    @endforeach
                </div>
                <div class="grid grid-cols-3 gap-3 mt-5">
                    <div class="text-center">
                        <div class="text-2xl font-bold grad-text">38.4k</div>
                        <div class="text-[10px] uppercase tracking-wider text-gray-500">7-day visits</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold grad-text">9.1k</div>
                        <div class="text-[10px] uppercase tracking-wider text-gray-500">QR scans</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold grad-text">2.4k</div>
                        <div class="text-[10px] uppercase tracking-wider text-gray-500">New followers</div>
                    </div>
                </div>
            </div>

            {{-- Coach card --}}
            <div class="reveal rd-2 lg:col-span-5 rounded-3xl p-7 tilt relative overflow-hidden text-white" style="background: linear-gradient(140deg, var(--c2), var(--c3) 70%, var(--c4));">
                <div class="absolute -top-12 -right-12 w-48 h-48 rounded-full bg-white/10"></div>
                <div class="relative">
                    <div class="text-xs font-bold uppercase tracking-wider mb-1 text-white/80">Performance Coach</div>
                    <h3 class="text-2xl font-bold mb-5">Health score 87 <span class="text-white/70 text-base font-normal">/ 100</span></h3>
                    <div class="flex justify-center mb-5">
                        <svg viewBox="0 0 100 100" class="w-32 h-32">
                            <circle cx="50" cy="50" r="40" fill="none" stroke="rgba(255,255,255,.2)" stroke-width="9"/>
                            <circle class="gauge-arc" cx="50" cy="50" r="40" fill="none" stroke="#fff" stroke-width="9" stroke-linecap="round" transform="rotate(-90 50 50)"/>
                            <text x="50" y="56" text-anchor="middle" font-size="22" font-weight="700" fill="#fff">87</text>
                        </svg>
                    </div>
                    <ul class="space-y-2 text-sm">
                        <li class="flex items-start gap-2 bg-white/15 rounded-xl p-3"><i class="fas fa-bolt mt-0.5"></i><span><b>Swap your top block.</b> "Free Templates" CTR drop -12%.</span></li>
                        <li class="flex items-start gap-2 bg-white/15 rounded-xl p-3"><i class="fas fa-bolt mt-0.5"></i><span><b>Add social proof.</b> Pages with reviews convert 1.7×.</span></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ============================ AVATAR ROW (creators trust) ============================ --}}
<section class="py-16 lg:py-20 relative overflow-hidden">
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h3 class="reveal text-2xl sm:text-3xl lg:text-4xl font-bold mb-3">Built for <span class="grad-text">creators, brands &amp; small businesses.</span></h3>
        <p class="reveal rd-1 text-gray-400 mb-10">Whatever you do, you can do it from one link.</p>
        <div class="reveal rd-2 flex items-center justify-center gap-3 flex-wrap">
            @php $creatorColors = ['#1bd4d9','#7c3aed','#e94e8c','#ff8a3c','#ffc845','#7c3aed','#e94e8c','#1bd4d9']; @endphp
            @foreach($creatorColors as $i => $c)
                <div class="relative shake-hover">
                    <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl border-2 border-white/20 flex items-center justify-center text-2xl font-bold text-white" style="background: linear-gradient(135deg, {{ $c }}, var(--c2)); transform:rotate({{ $i % 2 ? -4 : 4 }}deg);">
                        {{ ['🎨','🎵','🛒','📸','🎙️','✨','🍔','🚀'][$i] }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ============================ HOW IT WORKS ============================ --}}
<section id="how-it-works" class="py-24 lg:py-32 relative overflow-hidden">
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16 max-w-3xl mx-auto">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c2)">How it works</div>
            <h2 class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mb-5">
                Three steps. <span class="grad-text">Zero friction.</span>
            </h2>
        </div>

        <div class="grid md:grid-cols-3 gap-6 max-w-5xl mx-auto">
            @foreach([
                ['1','Sign up free','Email or phone — no card. You\'re live in under a minute.','fa-user-plus','#1bd4d9'],
                ['2','Build your page','Drag &amp; drop blocks. Add short links, QR codes &amp; forms.','fa-grip-vertical','#7c3aed'],
                ['3','Share &amp; grow','Share one URL everywhere. Watch live analytics roll in.','fa-rocket','#e94e8c'],
            ] as $i => $s)
                <div class="reveal rd-{{ $i+1 }} relative glass rounded-3xl p-7 tilt">
                    <div class="absolute top-4 right-5 text-7xl font-bold opacity-10 grad-text">{{ $s[0] }}</div>
                    <div class="relative w-14 h-14 rounded-2xl flex items-center justify-center mb-5" style="background: linear-gradient(135deg, {{ $s[4] }}, var(--c2)); box-shadow: 0 12px 30px -10px {{ $s[4] }};"><i class="fas {{ $s[3] }} text-xl text-white"></i></div>
                    <h3 class="relative text-xl font-bold mb-2">{!! $s[1] !!}</h3>
                    <p class="relative text-sm text-gray-400">{!! $s[2] !!}</p>
                </div>
            @endforeach
        </div>

        <div class="reveal rd-4 mt-12 text-center">
            <button type="button" @click="authTab='register'; authOpen=true" class="btn-bounce btn-glow inline-flex items-center justify-center gap-2 px-8 py-4 grad-bar text-white rounded-full text-base font-bold">
                Try it free <i class="fas fa-arrow-right text-sm"></i>
            </button>
        </div>
    </div>
</section>

{{-- ============================ TESTIMONIAL MARQUEE ============================ --}}
<section class="py-20 lg:py-24 relative overflow-hidden">
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h2 class="reveal text-3xl sm:text-4xl lg:text-5xl font-bold">Loved by people who <span class="grad-text">do the most.</span></h2>
        </div>
    </div>

    <div class="overflow-hidden mb-4" style="mask-image: linear-gradient(90deg, transparent, #000 8%, #000 92%, transparent);">
        <div class="flex whitespace-nowrap marquee">
            @php
                $reviews = [
                    ['1INME made it stupidly easy to put my podcast, shop and templates on one page.', 'Jane Doe', 'Creator', '#1bd4d9'],
                    ['The QR codes paid for the plan in a week — I changed the destination 3 times without reprinting.', 'Marco P.', 'Café owner', '#e94e8c'],
                    ['Finally I can see where my audience actually lives. Game changer.', 'Aisha K.', 'Travel writer', '#ffc845'],
                    ['The Performance Coach is like having a growth marketer on speed-dial.', 'Devon S.', 'Indie founder', '#7c3aed'],
                    ['Set up my whole agency contact page in 10 minutes.', 'Priya N.', 'Agency lead', '#ff8a3c'],
                ];
            @endphp
            @for($i = 0; $i < 2; $i++)
                @foreach($reviews as $r)
                    <div class="inline-block w-[340px] sm:w-[400px] mx-3 align-top">
                        <div class="glass rounded-3xl p-6 lift">
                            <div class="flex text-base mb-3" style="color:var(--c5)"><i class="fas fa-star"></i><i class="fas fa-star ml-0.5"></i><i class="fas fa-star ml-0.5"></i><i class="fas fa-star ml-0.5"></i><i class="fas fa-star ml-0.5"></i></div>
                            <p class="text-sm text-gray-200 mb-4 whitespace-normal">"{{ $r[0] }}"</p>
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold text-white" style="background: linear-gradient(135deg, {{ $r[3] }}, var(--c2));">{{ strtoupper(substr($r[1],0,1)) }}</div>
                                <div>
                                    <div class="text-sm font-bold">{{ $r[1] }}</div>
                                    <div class="text-[11px] text-gray-500">{{ $r[2] }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            @endfor
        </div>
    </div>
    <div class="overflow-hidden" style="mask-image: linear-gradient(90deg, transparent, #000 8%, #000 92%, transparent);">
        <div class="flex whitespace-nowrap marquee-rev">
            @for($i = 0; $i < 2; $i++)
                @foreach(array_reverse($reviews) as $r)
                    <div class="inline-block w-[340px] sm:w-[400px] mx-3 align-top">
                        <div class="glass rounded-3xl p-6 lift">
                            <div class="flex text-base mb-3" style="color:var(--c5)"><i class="fas fa-star"></i><i class="fas fa-star ml-0.5"></i><i class="fas fa-star ml-0.5"></i><i class="fas fa-star ml-0.5"></i><i class="fas fa-star ml-0.5"></i></div>
                            <p class="text-sm text-gray-200 mb-4 whitespace-normal">"{{ $r[0] }}"</p>
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold text-white" style="background: linear-gradient(135deg, {{ $r[3] }}, var(--c2));">{{ strtoupper(substr($r[1],0,1)) }}</div>
                                <div>
                                    <div class="text-sm font-bold">{{ $r[1] }}</div>
                                    <div class="text-[11px] text-gray-500">{{ $r[2] }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            @endfor
        </div>
    </div>
</section>

{{-- ============================ FAQ ============================ --}}
<section class="py-24 lg:py-28 relative overflow-hidden">
    <div class="relative max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c4)">FAQ</div>
            <h2 class="reveal rd-1 text-4xl sm:text-5xl font-bold tracking-tight mb-3">Questions? <span class="grad-text">Answered.</span></h2>
            <p class="reveal rd-2 text-gray-400">Everything you might be wondering about 1INME.</p>
        </div>

        <div class="reveal rd-2 space-y-3">
            @foreach([
                ['Is there really a free plan?', 'Yes — our Free plan is forever free and lets you create biolinks, short links and dynamic QR codes.'],
                ['Do I need a credit card to sign up?', 'No. Sign up with just your email or phone — no card required.'],
                ['Can I use my own custom domain?', 'Yes. Paid plans let you connect a custom domain for your short links and biolink page.'],
                ['How does the Performance Coach work?', 'It looks at your live analytics, finds the weakest links, and suggests one-click fixes — like swapping a low-CTR block or adding social proof.'],
                ['Can I see who is visiting in real time?', 'Yes. Live visitor pins show you where your audience is right now on a world map.'],
                ['How do I cancel?', 'You can downgrade to the Free plan or cancel from your account settings at any time.'],
            ] as $f)
                <details class="faq-item glass rounded-2xl px-5 py-4 hover:bg-white/[.06] transition-colors">
                    <summary class="flex items-center justify-between gap-4">
                        <span class="font-bold text-base sm:text-lg pr-4">{{ $f[0] }}</span>
                        <span class="faq-icon w-7 h-7 rounded-full grad-bar text-white flex items-center justify-center font-bold flex-shrink-0">
                            <i class="fas fa-plus text-xs"></i>
                        </span>
                    </summary>
                    <p class="mt-3 text-sm text-gray-300 leading-relaxed">{{ $f[1] }}</p>
                </details>
            @endforeach
        </div>
    </div>
</section>

{{-- ============================ PRICING ============================ --}}
<section id="pricing" class="py-24 lg:py-32 relative overflow-hidden">
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12 max-w-3xl mx-auto">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c1)">Pricing</div>
            <h2 class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mb-5">Simple, <span class="grad-text">transparent pricing.</span></h2>
            <p class="reveal rd-2 text-lg text-gray-400">Start free. Upgrade only when you outgrow it.</p>
        </div>

        @php $showSwitcher = auth()->check() ? empty(auth()->user()->country) : true; @endphp
        @if ($showSwitcher)
        <div class="flex items-center justify-center gap-2 mb-8">
            <span class="text-xs uppercase tracking-wider text-gray-500">Show prices in:</span>
            <form method="POST" action="{{ route('upgrade.public.switch-currency') }}" class="inline-flex">
                @csrf
                <button type="submit" name="currency" value="USD" class="px-3 py-1 text-xs rounded-l-full border border-white/10 {{ ($currency ?? 'USD') === 'USD' ? 'grad-bar text-white' : 'bg-white/5 text-gray-300 hover:bg-white/10' }}">USD&nbsp;($)</button>
                <button type="submit" name="currency" value="INR" class="px-3 py-1 text-xs rounded-r-full border border-white/10 border-l-0 {{ ($currency ?? 'USD') === 'INR' ? 'grad-bar text-white' : 'bg-white/5 text-gray-300 hover:bg-white/10' }}">INR&nbsp;(₹)</button>
            </form>
        </div>
        @endif

        @php
            $planCount = max(1, count($plans));
            $gridClass = match (true) {
                $planCount >= 4 => 'md:grid-cols-2 lg:grid-cols-4',
                $planCount === 3 => 'md:grid-cols-3',
                $planCount === 2 => 'md:grid-cols-2',
                default => 'md:grid-cols-1',
            };
        @endphp
        <div class="grid {{ $gridClass }} gap-6 max-w-6xl mx-auto">
            @foreach($plans as $i => $plan)
                @php $featured = $i === 1; $f = $plan['features']; @endphp
                <div class="reveal rd-{{ $i + 1 }} lift relative rounded-3xl p-8 {{ $featured ? 'text-white shadow-2xl shadow-[#7c3aed]/30 scale-[1.03]' : 'glass' }}" @if($featured) style="background: linear-gradient(150deg, var(--c2), var(--c3) 60%, var(--c4));" @endif>
                    @if($featured)
                        <div class="absolute -top-3 right-6 px-3 py-1 grad-bar text-white text-[10px] font-bold rounded-full uppercase tracking-wider">Most popular</div>
                    @endif
                    <div class="text-xs font-bold uppercase tracking-wider mb-2 {{ $featured ? 'text-white/80' : 'text-gray-400' }}">{{ $plan['name'] }}</div>
                    <div class="text-5xl font-bold mb-1 text-white">
                        {{ $plan['monthly']['formatted'] }}@unless($plan['is_free'])<span class="text-lg font-medium {{ $featured ? 'text-white/60' : 'text-gray-500' }}">/mo</span>@endunless
                    </div>
                    @unless($plan['is_free'])
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
                    @endunless
                    <div class="text-sm mb-6 {{ $featured ? 'text-white/70' : 'text-gray-500' }}">{{ $plan['description'] ?: ($plan['is_free'] ? 'Forever free' : 'Per user, billed monthly') }}</div>
                    <ul class="space-y-3 mb-8">
                        @foreach(['max_links' => 'links', 'max_biolinks' => 'bio pages', 'storage_limit_mb' => 'MB storage', 'contacts_max' => 'contacts'] as $key => $label)
                            @if(isset($f[$key]))
                                <li class="flex items-center gap-2 text-sm text-white">
                                    <i class="fas fa-check text-xs {{ $featured ? 'text-white' : '' }}" @unless($featured) style="color:var(--c1)" @endunless></i>
                                    {{ (int) $f[$key] === -1 ? 'Unlimited' : number_format((int) $f[$key]) }} {{ $label }}
                                </li>
                            @endif
                        @endforeach
                    </ul>
                    <button type="button" @click="authTab='register'; authOpen=true" class="btn-bounce block w-full py-3.5 text-center rounded-full text-sm font-bold {{ $featured ? 'bg-white text-[#7c3aed] hover:bg-gray-100' : 'grad-bar text-white' }}">
                        {{ $plan['is_free'] ? 'Get started free' : 'Start free trial' }}
                    </button>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ============================ FINAL CTA ============================ --}}
<section class="py-24 lg:py-32 relative overflow-hidden">
    <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="reveal text-4xl sm:text-5xl lg:text-7xl font-bold mb-6 leading-tight">
            Your audience is<br>
            <span class="grad-text">already searching for you.</span>
        </h2>
        <p class="reveal rd-1 text-lg text-gray-400 mb-10 max-w-xl mx-auto">
            Build the page. Share the link. Watch them show up — live on a map.
        </p>
        <div class="reveal rd-2 flex flex-col sm:flex-row gap-3 justify-center">
            <button type="button" @click="authTab='register'; authOpen=true" class="btn-bounce btn-glow inline-flex items-center gap-2 px-10 py-5 grad-bar text-white rounded-full text-lg font-bold">
                Sign up free <i class="fas fa-arrow-right"></i>
            </button>
            <a href="#features" class="btn-bounce inline-flex items-center gap-2 px-10 py-5 glass-2 text-white rounded-full text-lg font-bold">
                See features
            </a>
        </div>
    </div>
</section>

{{-- ============================ FOOTER ============================ --}}
<footer class="bg-[#08020f] text-white pt-16 pb-8 border-t border-white/5">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid md:grid-cols-5 gap-8 mb-12">
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
                    <li><a href="{{ route('site.discovery') }}" class="text-sm text-gray-500 hover:text-white">Discover</a></li>
                    <li><a href="{{ route('site.creators-feed') }}" class="text-sm text-gray-500 hover:text-white">Creators feed</a></li>
                    <li><a href="#how-it-works" class="text-sm text-gray-500 hover:text-white">How it works</a></li>
                    <li><a href="#pricing" class="text-sm text-gray-500 hover:text-white">Pricing</a></li>
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
        <div class="border-t border-white/5 pt-8 flex flex-col sm:flex-row justify-between items-center gap-3">
            <p class="text-sm text-gray-600">&copy; {{ date('Y') }} 1INME. All rights reserved.</p>
            <p class="text-xs text-gray-600">One link to everything.</p>
        </div>
    </div>
</footer>

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
</body>
</html>
