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
    $heroRoles = [
        [
            'word' => 'Creator',
            'profile' => ['avatar' => '/images/hero-roles/role_creator.jpg', 'handle' => '@jamie.creates', 'tag' => 'Storyteller · 24.1k followers', 'socials' => ['fa-youtube','fa-instagram','fa-tiktok','fa-x-twitter']],
            'blocks' => [
                ['icon' => 'fab fa-youtube',           'color' => '#ff0033', 'title' => 'Latest video',       'sub' => 'New drop · 2 days ago',   'thumb' => '/images/hero-roles/thumb_youtube.jpg'],
                ['icon' => 'fas fa-envelope-open-text','color' => '#7c3aed', 'title' => 'Join the newsletter','sub' => 'Weekly · 12k subs'],
                ['icon' => 'fas fa-store',             'color' => '#ff8a3c', 'title' => 'Shop merch',         'sub' => 'New tees in stock',       'thumb' => '/images/hero-roles/thumb_merch.jpg'],
            ],
        ],
        [
            'word' => 'Artist',
            'profile' => ['avatar' => '/images/hero-roles/role_artist.jpg', 'handle' => '@aria.studio', 'tag' => 'Mixed-media artist · Berlin', 'socials' => ['fa-instagram','fa-pinterest','fa-behance','fa-tiktok']],
            'blocks' => [
                ['icon' => 'fas fa-images',             'color' => '#e94e8c', 'title' => 'Latest collection', 'sub' => 'Petals & Concrete · 12 pcs', 'thumb' => '/images/hero-roles/thumb_artwork.jpg'],
                ['icon' => 'fab fa-spotify',            'color' => '#1ed760', 'title' => 'Studio playlist',   'sub' => '4hr ambient mix'],
                ['icon' => 'fas fa-hand-holding-heart', 'color' => '#ff8a3c', 'title' => 'Tip jar',           'sub' => 'Buy me a coffee'],
            ],
        ],
        [
            'word' => 'Businessman',
            'profile' => ['avatar' => '/images/hero-roles/role_business.jpg', 'handle' => '@marcus.solutions', 'tag' => 'Founder · B2B Consulting', 'socials' => ['fa-linkedin','fa-x-twitter','fa-medium','fa-youtube']],
            'blocks' => [
                ['icon' => 'fas fa-concierge-bell', 'color' => '#7c3aed', 'title' => 'Services & pricing', 'sub' => 'Strategy · Audits · Retainers'],
                ['icon' => 'fas fa-calendar-check', 'color' => '#1bd4d9', 'title' => 'Book a call',        'sub' => '30 min · Calendly'],
                ['icon' => 'fas fa-paper-plane',    'color' => '#ff8a3c', 'title' => 'Get a quote',        'sub' => 'Reply within 24h'],
            ],
        ],
        [
            'word' => 'Musician',
            'profile' => ['avatar' => '/images/hero-roles/role_musician.jpg', 'handle' => '@luna.live', 'tag' => 'Indie pop · New EP out now', 'socials' => ['fa-spotify','fa-apple','fa-youtube','fa-instagram']],
            'blocks' => [
                ['icon' => 'fab fa-spotify',     'color' => '#1ed760', 'title' => 'New EP — Saltwater', 'sub' => '5 tracks · Listen now', 'thumb' => '/images/hero-roles/thumb_album.jpg'],
                ['icon' => 'fas fa-ticket-alt',  'color' => '#e94e8c', 'title' => 'Tour 2026',          'sub' => '12 cities · Tickets live'],
                ['icon' => 'fas fa-store',       'color' => '#ffc845', 'title' => 'Vinyl & tees',       'sub' => 'Limited drop',          'thumb' => '/images/hero-roles/thumb_merch.jpg'],
            ],
        ],
        [
            'word' => 'Coach',
            'profile' => ['avatar' => '/images/hero-roles/role_coach.jpg', 'handle' => '@coach.kai', 'tag' => 'Strength coach · 1:1 + group', 'socials' => ['fa-instagram','fa-tiktok','fa-youtube','fa-spotify']],
            'blocks' => [
                ['icon' => 'fas fa-calendar-check',  'color' => '#1bd4d9', 'title' => 'Book a session',     'sub' => '45 min consult'],
                ['icon' => 'fas fa-quote-right',     'color' => '#7c3aed', 'title' => 'Wins from clients',  'sub' => '140+ five-star reviews'],
                ['icon' => 'fas fa-clipboard-list',  'color' => '#ff8a3c', 'title' => 'Free intake form',   'sub' => '2 minutes · No fluff'],
            ],
        ],
        [
            'word' => 'Photographer',
            'profile' => ['avatar' => '/images/hero-roles/role_photographer.jpg', 'handle' => '@iris.frames', 'tag' => 'Travel & landscape · Iceland', 'socials' => ['fa-instagram','fa-pinterest','fa-flickr','fa-x-twitter']],
            'blocks' => [
                ['icon' => 'fas fa-th',            'color' => '#1bd4d9', 'title' => 'Portfolio · 2026', 'sub' => '48 photos',         'thumb' => '/images/hero-roles/thumb_photo.jpg'],
                ['icon' => 'fas fa-shopping-bag',  'color' => '#ff8a3c', 'title' => 'Print shop',       'sub' => 'A2 / A3 / canvas'],
                ['icon' => 'fas fa-paper-plane',   'color' => '#e94e8c', 'title' => 'Hire me',          'sub' => 'Weddings · Brand'],
            ],
        ],
        [
            'word' => 'Influencer',
            'profile' => ['avatar' => '/images/hero-roles/role_influencer.jpg', 'handle' => '@maya.daily', 'tag' => 'Lifestyle · 480k across socials', 'socials' => ['fa-instagram','fa-tiktok','fa-youtube','fa-snapchat']],
            'blocks' => [
                ['icon' => 'fab fa-instagram', 'color' => '#e94e8c', 'title' => 'Latest reel',     'sub' => 'Spring haul'],
                ['icon' => 'fab fa-tiktok',    'color' => '#1bd4d9', 'title' => 'Trending today',  'sub' => '2.1M views'],
                ['icon' => 'fas fa-handshake', 'color' => '#ffc845', 'title' => 'Brand deals',     'sub' => 'Press kit · Rates'],
            ],
        ],
        [
            'word' => 'Podcaster',
            'profile' => ['avatar' => '/images/hero-roles/role_podcaster.jpg', 'handle' => '@theo.talks', 'tag' => 'Weekly tech & culture', 'socials' => ['fa-spotify','fa-apple','fa-youtube','fa-x-twitter']],
            'blocks' => [
                ['icon' => 'fab fa-apple',              'color' => '#ffffff', 'title' => 'Apple Podcasts',  'sub' => 'Ep. 87 · 42 min',     'thumb' => '/images/hero-roles/thumb_podcast.jpg'],
                ['icon' => 'fab fa-spotify',            'color' => '#1ed760', 'title' => 'Spotify',         'sub' => 'Subscribe · 18k listeners'],
                ['icon' => 'fas fa-envelope-open-text', 'color' => '#ff8a3c', 'title' => 'Show notes',      'sub' => 'Newsletter every Friday'],
            ],
        ],
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

            {{-- Hero 3D biolink stack --}}
            <div class="reveal rd-2 relative h-[560px] sm:h-[600px] stack-scene">
                {{-- Decorative stickers --}}
                <div class="sticker top-2 left-4 w-10 h-10 rounded-full wiggle shake-hover opacity-80" style="background:var(--c4)"></div>
                <div class="sticker top-12 right-2 w-8 h-8 rounded-lg spin-slow opacity-70" style="background:var(--c5)"></div>
                <div class="sticker bottom-4 left-2 w-9 h-9 rounded-2xl wiggle opacity-80" style="background:var(--c1); animation-delay:-1s"></div>
                <div class="sticker top-1/2 -right-3 w-6 h-6 rounded-full wiggle opacity-80" style="background:var(--c3); animation-delay:-2s"></div>

                {{-- 3D stack --}}
                <div class="absolute inset-0 flex items-center justify-center">
                    <div class="stack-3d">
                        <div id="hero-stack" class="stack-inner" aria-hidden="true"></div>
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
        </div>
    </div>

    <script>
        (function () {
            const ROLES = @json($heroRoles);
            const word  = document.getElementById('hero-role-word');
            const sr    = document.getElementById('hero-role-sr');
            const stack = document.getElementById('hero-stack');
            if (!word || !stack) return;

            const reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const escapeHTML = (s) => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

            function buildStackHTML(role) {
                const p = role.profile;
                const socials = (p.socials || []).map(s => `<span><i class="fab ${escapeHTML(s)}"></i></span>`).join('');
                const profile = `
                    <div class="stack-card is-profile" style="--d:0ms">
                        <div class="profile-row">
                            <img class="profile-avatar" src="${escapeHTML(p.avatar)}" alt="" loading="lazy">
                            <div class="min-w-0">
                                <div class="profile-handle">${escapeHTML(p.handle)}</div>
                                <div class="profile-tag">${escapeHTML(p.tag)}</div>
                            </div>
                        </div>
                        <div class="profile-socials">${socials}</div>
                    </div>`;
                const blocks = (role.blocks || []).map((b, i) => {
                    const delay = (i + 1) * 110;
                    const thumb = b.thumb
                        ? `<img class="card-thumb" src="${escapeHTML(b.thumb)}" alt="" loading="lazy">`
                        : `<div class="card-icon" style="background:${escapeHTML(b.color)}33;color:${escapeHTML(b.color)}"><i class="${escapeHTML(b.icon)}"></i></div>`;
                    return `
                        <div class="stack-card" style="--d:${delay}ms">
                            ${thumb}
                            <div class="card-body">
                                <div class="card-title">${escapeHTML(b.title)}</div>
                                <div class="card-sub">${escapeHTML(b.sub || '')}</div>
                            </div>
                            <i class="fas fa-arrow-right card-cta"></i>
                        </div>`;
                }).join('');
                return profile + blocks;
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
                }, 360);
            }

            let i = 0;
            // Initial paint (no out animation)
            stack.innerHTML = buildStackHTML(ROLES[0]);
            word.textContent = ROLES[0].word;
            if (!reduce) word.classList.add('word-in');

            setInterval(() => {
                i = (i + 1) % ROLES.length;
                setRole(ROLES[i]);
            }, 4200);
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
