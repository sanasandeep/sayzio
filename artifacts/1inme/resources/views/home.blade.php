<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>1INME — Your link, your page, your audience. All in one.</title>
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
            --bg: #1e2330;
            --bg-2: #161b26;
            --purple: #7c3aed;
            --purple-2: #8b5cf6;
            --cyan: #06b6d4;
            --pink: #f43f5e;
        }
        html, body { background: var(--bg); }
        body { font-family: 'Space Grotesk', sans-serif; color: #fff; }
        [x-cloak] { display: none !important; }

        /* ---------- Reveal-on-scroll ---------- */
        .reveal { opacity: 1; transform: none; transition: opacity .8s cubic-bezier(.16,1,.3,1), transform .8s cubic-bezier(.16,1,.3,1); }
        .js .reveal:not(.visible) { opacity: 0; transform: translateY(28px); }
        .reveal.visible { opacity: 1; transform: none; }
        .rd-1 { transition-delay: .1s }  .rd-2 { transition-delay: .2s }
        .rd-3 { transition-delay: .3s }  .rd-4 { transition-delay: .4s }
        .rd-5 { transition-delay: .5s }

        /* ---------- Ambient blobs ---------- */
        .blob { position: absolute; border-radius: 50%; filter: blur(110px); mix-blend-mode: screen; pointer-events: none; }
        .blob-spin { animation: blobSpin 28s linear infinite; }
        @keyframes blobSpin { 0% { transform: rotate(0) scale(1); } 50% { transform: rotate(180deg) scale(1.18);} 100% { transform: rotate(360deg) scale(1);} }

        /* ---------- Float / pulse ---------- */
        .float-a { animation: floatA 6s ease-in-out infinite; }
        @keyframes floatA { 0%,100%{ transform: translateY(0) rotate(-1deg);} 50%{ transform: translateY(-14px) rotate(1deg);} }
        .float-b { animation: floatB 7s ease-in-out infinite; }
        @keyframes floatB { 0%,100%{ transform: translateY(0) rotate(1deg);} 50%{ transform: translateY(-10px) rotate(-1deg);} }
        .pulse-ring { animation: pulseRing 2.2s cubic-bezier(0,0,.2,1) infinite; }
        @keyframes pulseRing { 0%{ transform: scale(.6); opacity:.9 } 80%,100%{ transform: scale(2.4); opacity:0 } }
        .pulse-dot { animation: pulseDot 2.2s ease-in-out infinite; }
        @keyframes pulseDot { 0%,100%{ transform: scale(1) } 50%{ transform: scale(1.15) } }

        /* ---------- Marquee ---------- */
        .marquee { animation: marquee 35s linear infinite; }
        @keyframes marquee { 0% { transform: translateX(0); } 100% { transform: translateX(-50%); } }

        /* ---------- Cards ---------- */
        .card-hover { transition: transform .35s cubic-bezier(.16,1,.3,1), box-shadow .35s; }
        .card-hover:hover { transform: translateY(-6px); }

        /* ---------- Equalizer bars ---------- */
        .eq i { display:inline-block; width:3px; margin-right:2px; background: var(--purple-2); border-radius:2px; animation: eq 1.1s ease-in-out infinite; }
        .eq i:nth-child(1){ height:35%; animation-delay:0s }
        .eq i:nth-child(2){ height:80%; animation-delay:.15s }
        .eq i:nth-child(3){ height:55%; animation-delay:.3s }
        .eq i:nth-child(4){ height:90%; animation-delay:.1s }
        @keyframes eq { 0%,100%{ transform: scaleY(.45);} 50%{ transform: scaleY(1);} }

        /* ---------- Trend / sparkline ---------- */
        .spark-line { stroke-dasharray: 600; stroke-dashoffset: 600; animation: drawLine 2.4s ease-out forwards; }
        @keyframes drawLine { to { stroke-dashoffset: 0; } }

        /* ---------- Health gauge ---------- */
        .gauge-arc { stroke-dasharray: 251; stroke-dashoffset: 251; animation: gaugeFill 1.8s ease-out forwards; }
        @keyframes gaugeFill { to { stroke-dashoffset: 75; } }

        /* ---------- Drag chip ---------- */
        .drag-chip { transition: transform .25s ease, box-shadow .25s ease; cursor: grab; }
        .drag-chip:hover { transform: translateX(6px) scale(1.02); box-shadow: 0 8px 20px -8px rgba(124,58,237,.5); }

        /* ---------- Reduced motion ---------- */
        @media (prefers-reduced-motion: reduce) {
            .reveal { opacity: 1 !important; transform: none !important; transition: none !important; }
            .blob-spin, .float-a, .float-b, .pulse-ring, .pulse-dot, .eq i, .marquee, .spark-line, .gauge-arc { animation: none !important; }
            .spark-line { stroke-dashoffset: 0 !important; }
            .gauge-arc { stroke-dashoffset: 75 !important; }
        }

        /* ---------- Focus states (a11y) ---------- */
        a:focus-visible, button:focus-visible {
            outline: 2px solid var(--purple-2);
            outline-offset: 2px;
            border-radius: 6px;
        }

        /* ---------- Section dividers ---------- */
        .section-eyebrow { letter-spacing: .18em; text-transform: uppercase; font-size: .72rem; font-weight: 700; color: var(--purple-2); }

        /* ---------- Brand logo (dark-only on this page) ---------- */
        .brand-logo--light { display: none; }
    </style>
</head>
<body class="overflow-x-hidden">

{{-- ============================ NAV ============================ --}}
<nav class="fixed top-0 inset-x-0 z-50 bg-[#1e2330]/85 backdrop-blur-xl border-b border-white/5" x-data="{ mobileOpen: false }" role="banner">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <a href="{{ route('home') }}" class="inline-flex items-center" aria-label="1INME home">
                @include('common.partials.brand-logo', ['height' => 'h-9'])
            </a>
            <div class="hidden md:flex items-center gap-8" role="navigation" aria-label="Primary">
                <a href="#use-cases" class="text-sm text-gray-300 hover:text-[#8b5cf6] transition-colors">Use Cases</a>
                <a href="#features" class="text-sm text-gray-300 hover:text-[#8b5cf6] transition-colors">Features</a>
                <a href="#how-it-works" class="text-sm text-gray-300 hover:text-[#8b5cf6] transition-colors">How It Works</a>
                <a href="#pricing" class="text-sm text-gray-300 hover:text-[#8b5cf6] transition-colors">Pricing</a>
            </div>
            <div class="hidden md:flex items-center gap-3">
                @auth
                    <a href="{{ route('user.dashboard') }}" class="px-6 py-2.5 bg-[#7c3aed] text-white rounded-full text-sm font-bold hover:bg-[#6d28d9] transition-all">Dashboard</a>
                @else
                    <a href="{{ route('user.login') }}" class="px-4 py-2 text-sm font-medium text-gray-300 hover:text-white transition-colors">Log in</a>
                    <a href="{{ route('user.register') }}" class="px-6 py-2.5 bg-[#7c3aed] text-white rounded-full text-sm font-bold hover:bg-[#6d28d9] transition-all hover:shadow-lg hover:shadow-[#7c3aed]/30">Sign up free</a>
                @endauth
            </div>
            <button @click="mobileOpen = !mobileOpen" class="md:hidden p-2 text-gray-300" aria-label="Toggle menu" :aria-expanded="mobileOpen">
                <svg x-show="!mobileOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                <svg x-show="mobileOpen" x-cloak class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div x-show="mobileOpen" x-cloak x-transition class="md:hidden pb-4 border-t border-white/10 mt-2 pt-3 space-y-1">
            <a href="#use-cases" @click="mobileOpen=false" class="block px-3 py-2 text-sm text-gray-300 hover:text-[#8b5cf6] rounded-lg">Use Cases</a>
            <a href="#features" @click="mobileOpen=false" class="block px-3 py-2 text-sm text-gray-300 hover:text-[#8b5cf6] rounded-lg">Features</a>
            <a href="#how-it-works" @click="mobileOpen=false" class="block px-3 py-2 text-sm text-gray-300 hover:text-[#8b5cf6] rounded-lg">How It Works</a>
            <a href="#pricing" @click="mobileOpen=false" class="block px-3 py-2 text-sm text-gray-300 hover:text-[#8b5cf6] rounded-lg">Pricing</a>
            <div class="pt-2 border-t border-white/10 space-y-2">
                @auth
                    <a href="{{ route('user.dashboard') }}" class="block px-4 py-2.5 bg-[#7c3aed] text-white rounded-lg text-sm font-bold text-center">Dashboard</a>
                @else
                    <a href="{{ route('user.login') }}" class="block px-4 py-2 text-sm text-gray-300">Log in</a>
                    <a href="{{ route('user.register') }}" class="block px-4 py-2.5 bg-[#7c3aed] text-white rounded-lg text-sm font-bold text-center">Sign up free</a>
                @endauth
            </div>
        </div>
    </div>
</nav>

{{-- ============================ HERO ============================ --}}
<section class="relative min-h-screen pt-28 pb-20 lg:pt-36 lg:pb-28 overflow-hidden" aria-labelledby="hero-h">
    <div class="blob blob-spin" style="top:-15%; left:-8%; width:560px; height:560px; background:#7c3aed; opacity:.3"></div>
    <div class="blob blob-spin" style="bottom:-10%; right:-8%; width:480px; height:480px; background:#06b6d4; opacity:.25; animation-delay:-12s"></div>
    <div class="blob blob-spin" style="top:30%; right:25%; width:360px; height:360px; background:#f43f5e; opacity:.18; animation-delay:-8s"></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-2 gap-14 items-center">
            <div class="text-center lg:text-left">
                <div class="reveal inline-flex items-center gap-2 px-4 py-1.5 bg-white/10 border border-white/10 rounded-full text-[#8b5cf6] text-xs font-semibold mb-6 backdrop-blur-sm">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full rounded-full bg-[#7c3aed] opacity-75 animate-ping"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-[#7c3aed]"></span>
                    </span>
                    Now with live visitor pins, Performance Coach &amp; more
                </div>

                <h1 id="hero-h" class="reveal rd-1 text-5xl sm:text-6xl lg:text-7xl font-bold leading-[1.05] tracking-tight mb-6">
                    Your link.<br>Your page.<br>
                    <span class="bg-gradient-to-r from-[#8b5cf6] via-[#a78bfa] to-[#06b6d4] bg-clip-text text-transparent">Your audience.</span>
                </h1>

                <p class="reveal rd-2 text-lg sm:text-xl text-gray-400 max-w-xl mx-auto lg:mx-0 mb-8 leading-relaxed">
                    1INME is the all-in-one platform to <strong class="text-white">build</strong> drag-and-drop biolink pages, <strong class="text-white">share</strong> them anywhere with short links and QR codes, and <strong class="text-white">grow</strong> with live analytics and an AI-style Performance Coach.
                </p>

                <div class="reveal rd-3 flex flex-col sm:flex-row gap-3 justify-center lg:justify-start">
                    <a href="{{ route('user.register') }}" class="px-8 py-4 bg-[#7c3aed] text-white rounded-full text-base font-bold hover:bg-[#6d28d9] transition-all hover:shadow-xl hover:shadow-[#7c3aed]/30 hover:-translate-y-0.5">
                        Sign up free
                    </a>
                    <a href="#features" class="px-8 py-4 bg-white/10 text-white rounded-full text-base font-semibold border border-white/15 hover:bg-white/15 transition-all backdrop-blur-sm">
                        See it live <i class="fas fa-arrow-right ml-1 text-xs"></i>
                    </a>
                </div>

                <div class="reveal rd-4 flex flex-wrap items-center gap-x-6 gap-y-2 mt-8 justify-center lg:justify-start text-sm text-gray-500">
                    <span class="flex items-center gap-1.5"><i class="fas fa-check text-[#8b5cf6]"></i> Free forever plan</span>
                    <span class="flex items-center gap-1.5"><i class="fas fa-check text-[#8b5cf6]"></i> No credit card</span>
                    <span class="flex items-center gap-1.5"><i class="fas fa-check text-[#8b5cf6]"></i> Set up in minutes</span>
                </div>
            </div>

            {{-- Hero visual: phone with biolink + floating analytics card --}}
            <div class="reveal rd-3 relative flex justify-center lg:justify-end">
                <div class="relative w-[300px] sm:w-[340px]">
                    <div class="float-a">
                        <div class="bg-gradient-to-br from-[#8b5cf6] via-[#06b6d4] to-[#7c3aed] rounded-[2rem] p-[3px] shadow-2xl shadow-[#7c3aed]/30">
                            <div class="bg-[#1e2330] rounded-[1.85rem] p-4 space-y-2.5">
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 rounded-full bg-gradient-to-br from-[#8b5cf6] to-[#06b6d4] p-[2px]">
                                        <div class="w-full h-full rounded-full bg-[#1e2330] flex items-center justify-center text-[#8b5cf6] text-sm font-bold">JD</div>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-bold leading-tight">Jane Doe Studio</h3>
                                        <p class="text-[10px] text-gray-500">Creator · 12.4k followers</p>
                                    </div>
                                </div>
                                <div class="bg-white/5 rounded-xl p-3 border border-white/5">
                                    <p class="text-[11px] text-gray-300 leading-relaxed">Latest drops, free templates, and the new podcast — all in one place.</p>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div class="bg-gradient-to-br from-[#7c3aed]/30 to-[#7c3aed]/10 rounded-xl p-2.5 border border-[#7c3aed]/20">
                                        <div class="w-full aspect-video bg-[#7c3aed]/25 rounded-lg flex items-center justify-center mb-1.5">
                                            <div class="w-7 h-7 rounded-full bg-[#7c3aed] flex items-center justify-center"><i class="fas fa-play text-white text-[8px] ml-0.5"></i></div>
                                        </div>
                                        <div class="text-[9px] text-gray-400">VIDEO</div>
                                        <div class="text-[10px] font-bold truncate">Design Tips '26</div>
                                    </div>
                                    <div class="bg-gradient-to-br from-[#06b6d4]/30 to-[#06b6d4]/10 rounded-xl p-2.5 border border-[#06b6d4]/20">
                                        <div class="w-full aspect-video rounded-lg overflow-hidden mb-1.5 grid grid-cols-2 gap-px p-1 bg-[#06b6d4]/15">
                                            <div class="bg-[#06b6d4]/40 rounded-sm"></div>
                                            <div class="bg-[#7c3aed]/40 rounded-sm"></div>
                                            <div class="bg-[#f43f5e]/40 rounded-sm"></div>
                                            <div class="bg-[#7c3aed]/40 rounded-sm"></div>
                                        </div>
                                        <div class="text-[9px] text-gray-400">GALLERY</div>
                                        <div class="text-[10px] font-bold truncate">Portfolio</div>
                                    </div>
                                </div>
                                <div class="bg-white/5 rounded-xl p-2.5 border border-white/5 flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-[#f43f5e]/20 flex items-center justify-center"><i class="fas fa-headphones text-[#f43f5e] text-xs"></i></div>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-[9px] text-gray-400">PODCAST</div>
                                        <div class="text-[10px] font-bold truncate">Creative Flow Ep. 42</div>
                                    </div>
                                    <div class="eq h-4 flex items-end"><i></i><i></i><i></i><i></i></div>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div class="py-2.5 px-3 bg-[#7c3aed] rounded-xl text-[11px] font-bold text-center"><i class="fas fa-download mr-1"></i>Free Templates</div>
                                    <div class="py-2.5 px-3 bg-[#e11d48] rounded-xl text-[11px] font-bold text-center"><i class="fas fa-store mr-1"></i>Shop Merch</div>
                                </div>
                                <div class="flex justify-center gap-2 pt-1">
                                    <span class="w-7 h-7 rounded-full bg-white/10 flex items-center justify-center text-gray-400 text-[10px]"><i class="fab fa-twitter"></i></span>
                                    <span class="w-7 h-7 rounded-full bg-white/10 flex items-center justify-center text-gray-400 text-[10px]"><i class="fab fa-tiktok"></i></span>
                                    <span class="w-7 h-7 rounded-full bg-white/10 flex items-center justify-center text-gray-400 text-[10px]"><i class="fab fa-youtube"></i></span>
                                    <span class="w-7 h-7 rounded-full bg-white/10 flex items-center justify-center text-gray-400 text-[10px]"><i class="fab fa-linkedin"></i></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Floating analytics chip (top-right) --}}
                    <div class="float-b absolute -top-6 -right-8 bg-[#1e2330] rounded-2xl shadow-2xl shadow-[#06b6d4]/20 p-3 border border-white/10 w-[170px]">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-[9px] text-gray-400 uppercase tracking-wider">Live visitors</span>
                            <span class="flex items-center gap-1 text-[9px] text-emerald-400"><span class="w-1.5 h-1.5 rounded-full bg-emerald-400 pulse-dot"></span>now</span>
                        </div>
                        <div class="text-2xl font-bold mb-1">247</div>
                        <svg class="w-full h-8" viewBox="0 0 100 30" preserveAspectRatio="none">
                            <polyline class="spark-line" fill="none" stroke="#8b5cf6" stroke-width="2" stroke-linecap="round" points="0,22 12,18 24,20 36,12 48,15 60,8 72,11 84,5 100,7"/>
                        </svg>
                        <div class="text-[9px] text-emerald-400 mt-1"><i class="fas fa-arrow-up mr-0.5"></i>+18% today</div>
                    </div>

                    {{-- Floating Coach chip (bottom-left) --}}
                    <div class="float-a absolute -bottom-6 -left-10 bg-[#1e2330] rounded-2xl shadow-2xl shadow-[#7c3aed]/20 p-3 border border-white/10 w-[180px]" style="animation-delay:-2s">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="w-8 h-8 rounded-lg bg-[#7c3aed]/20 flex items-center justify-center"><i class="fas fa-bolt text-[#a78bfa] text-xs"></i></div>
                            <div>
                                <div class="text-[9px] text-gray-400 uppercase tracking-wider">Health</div>
                                <div class="text-sm font-bold">Score 87</div>
                            </div>
                        </div>
                        <div class="h-1.5 bg-white/10 rounded-full overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-[#8b5cf6] to-[#06b6d4] rounded-full" style="width:87%"></div>
                        </div>
                        <div class="text-[9px] text-gray-500 mt-1.5">2 quick fixes available</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ============================ MARQUEE ============================ --}}
<div class="bg-gradient-to-r from-[#7c3aed] via-[#8b5cf6] to-[#7c3aed] py-4 overflow-hidden border-y border-white/10" aria-hidden="true">
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
                ['fa-camera','Snapshot Exports'],
            ] as $item)
                <span class="text-sm font-bold uppercase tracking-wider flex items-center gap-2"><i class="fas {{ $item[0] }}"></i>{!! $item[1] !!}</span>
                <span class="text-xl opacity-30">&bull;</span>
            @endforeach
        </span>
        @endfor
    </div>
</div>

{{-- ============================ 1. BUILD YOUR PAGE ============================ --}}
<section id="features" class="py-24 lg:py-32 relative overflow-hidden" aria-labelledby="build-h">
    <div class="blob" style="top:10%; left:-10%; width:400px; height:400px; background:#7c3aed; opacity:.15"></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16 max-w-3xl mx-auto">
            <div class="reveal section-eyebrow mb-3">01 · Build your page</div>
            <h2 id="build-h" class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mb-5">
                A whole website,<br>
                <span class="bg-gradient-to-r from-[#8b5cf6] to-[#06b6d4] bg-clip-text text-transparent">drag-and-drop simple.</span>
            </h2>
            <p class="reveal rd-2 text-lg text-gray-400">
                Stack blocks for text, images, video, audio, files and embeds. Arrange them in multi-column layouts. Pick a theme. Go live.
            </p>
        </div>

        <div class="grid lg:grid-cols-12 gap-6">
            {{-- Big drag-and-drop mock --}}
            <div class="reveal rd-1 lg:col-span-7 bg-white/[0.03] border border-white/10 rounded-3xl p-7 backdrop-blur-sm card-hover">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <div class="text-xs text-[#8b5cf6] font-bold uppercase tracking-wider mb-1">Drag-and-drop biolink editor</div>
                        <h3 class="text-xl font-bold">Reorder blocks. Build columns. Ship.</h3>
                    </div>
                    <span class="hidden sm:inline-flex items-center gap-1 px-3 py-1 rounded-full bg-[#7c3aed]/20 text-[#a78bfa] text-xs font-semibold"><i class="fas fa-grip-vertical"></i> Drag</span>
                </div>

                <div class="grid grid-cols-12 gap-3">
                    {{-- Left: editor canvas --}}
                    <div class="col-span-12 sm:col-span-7 bg-[#0f1320] rounded-2xl p-3 border border-white/5 space-y-2">
                        <div class="drag-chip flex items-center gap-3 bg-white/5 rounded-xl p-3 border border-white/10">
                            <i class="fas fa-grip-vertical text-gray-500 text-xs"></i>
                            <div class="w-9 h-9 rounded-lg bg-[#7c3aed]/20 flex items-center justify-center"><i class="fas fa-image text-[#a78bfa] text-sm"></i></div>
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-bold">Hero image</div>
                                <div class="text-[10px] text-gray-500">Full-width banner</div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="drag-chip flex items-center gap-2 bg-white/5 rounded-xl p-2.5 border border-white/10">
                                <div class="w-8 h-8 rounded-lg bg-[#06b6d4]/20 flex items-center justify-center"><i class="fas fa-video text-[#06b6d4] text-xs"></i></div>
                                <div class="text-[11px] font-bold truncate">YouTube</div>
                            </div>
                            <div class="drag-chip flex items-center gap-2 bg-white/5 rounded-xl p-2.5 border border-white/10">
                                <div class="w-8 h-8 rounded-lg bg-[#f43f5e]/20 flex items-center justify-center"><i class="fas fa-headphones text-[#f43f5e] text-xs"></i></div>
                                <div class="text-[11px] font-bold truncate">Spotify</div>
                            </div>
                        </div>
                        <div class="drag-chip flex items-center gap-3 bg-gradient-to-r from-[#7c3aed]/20 to-[#06b6d4]/20 rounded-xl p-3 border border-[#7c3aed]/30">
                            <i class="fas fa-grip-vertical text-gray-400 text-xs"></i>
                            <div class="w-9 h-9 rounded-lg bg-[#7c3aed] flex items-center justify-center"><i class="fas fa-columns text-white text-sm"></i></div>
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-bold">2-column row</div>
                                <div class="text-[10px] text-gray-300">Drop blocks inside</div>
                            </div>
                            <span class="text-[9px] px-2 py-0.5 rounded-full bg-white/10 font-semibold">DRAGGING</span>
                        </div>
                        <div class="drag-chip flex items-center gap-3 bg-white/5 rounded-xl p-3 border border-white/10">
                            <i class="fas fa-grip-vertical text-gray-500 text-xs"></i>
                            <div class="w-9 h-9 rounded-lg bg-[#059669]/20 flex items-center justify-center"><i class="fas fa-file-alt text-emerald-400 text-sm"></i></div>
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-bold">Free PDF download</div>
                                <div class="text-[10px] text-gray-500">2.4 MB · gated</div>
                            </div>
                        </div>
                        <div class="drag-chip flex items-center gap-3 bg-white/5 rounded-xl p-3 border border-white/10">
                            <i class="fas fa-grip-vertical text-gray-500 text-xs"></i>
                            <div class="w-9 h-9 rounded-lg bg-[#06b6d4]/20 flex items-center justify-center"><i class="fas fa-map-marker-alt text-[#06b6d4] text-sm"></i></div>
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-bold">Maps embed</div>
                                <div class="text-[10px] text-gray-500">Studio location</div>
                            </div>
                        </div>
                    </div>

                    {{-- Right: block palette --}}
                    <div class="col-span-12 sm:col-span-5 bg-[#0f1320] rounded-2xl p-3 border border-white/5">
                        <div class="text-[10px] uppercase tracking-wider text-gray-500 mb-2 px-1 font-bold">Block library</div>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach([
                                ['fa-font','Text','#a78bfa'],
                                ['fa-image','Image','#06b6d4'],
                                ['fa-video','Video','#f43f5e'],
                                ['fa-headphones','Audio','#fbbf24'],
                                ['fa-file-alt','File','#34d399'],
                                ['fa-code','Embed','#8b5cf6'],
                                ['fa-link','Link','#06b6d4'],
                                ['fa-wpforms','Form','#f472b6'],
                                ['fa-columns','Cols','#7c3aed'],
                            ] as $b)
                                <div class="aspect-square rounded-xl bg-white/5 border border-white/10 flex flex-col items-center justify-center gap-1 hover:bg-white/10 transition-colors cursor-grab">
                                    <i class="fas {{ $b[0] }} text-sm" style="color:{{ $b[2] }}"></i>
                                    <span class="text-[9px] font-bold text-gray-300">{{ $b[1] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right column: themes + splash + embeds --}}
            <div class="lg:col-span-5 grid gap-6 content-start">
                <div class="reveal rd-2 bg-white/[0.03] border border-white/10 rounded-3xl p-7 card-hover">
                    <div class="text-xs text-[#06b6d4] font-bold uppercase tracking-wider mb-2">Themes &amp; card galleries</div>
                    <h3 class="text-xl font-bold mb-3">Pre-designed looks. Endlessly tweakable.</h3>
                    <p class="text-gray-400 text-sm mb-4">Pick a starter theme, swap colors and fonts, and your whole page restyles instantly.</p>
                    <div class="grid grid-cols-3 gap-2">
                        <div class="aspect-[3/4] rounded-xl bg-gradient-to-br from-[#7c3aed] to-[#06b6d4] p-1.5"><div class="w-full h-full rounded-lg bg-[#1e2330]/40 flex flex-col gap-1 p-1.5"><div class="h-1.5 bg-white/40 rounded-full"></div><div class="h-1.5 bg-white/30 rounded-full w-2/3"></div><div class="flex-1"></div><div class="h-2 bg-white/50 rounded"></div><div class="h-2 bg-white/40 rounded"></div></div></div>
                        <div class="aspect-[3/4] rounded-xl bg-gradient-to-br from-[#f43f5e] to-[#fbbf24] p-1.5"><div class="w-full h-full rounded-lg bg-white/95 flex flex-col gap-1 p-1.5"><div class="h-1.5 bg-[#1e2330]/40 rounded-full"></div><div class="h-1.5 bg-[#1e2330]/30 rounded-full w-2/3"></div><div class="flex-1"></div><div class="h-2 bg-[#f43f5e] rounded"></div><div class="h-2 bg-[#1e2330] rounded"></div></div></div>
                        <div class="aspect-[3/4] rounded-xl bg-[#0f1320] border border-white/10 p-1.5"><div class="w-full h-full rounded-lg flex flex-col gap-1 p-1.5"><div class="h-1.5 bg-emerald-400/60 rounded-full"></div><div class="h-1.5 bg-white/20 rounded-full w-2/3"></div><div class="flex-1"></div><div class="h-2 bg-emerald-400 rounded"></div><div class="h-2 bg-white/15 rounded"></div></div></div>
                    </div>
                </div>

                <div class="reveal rd-3 bg-white/[0.03] border border-white/10 rounded-3xl p-7 card-hover">
                    <div class="text-xs text-[#f472b6] font-bold uppercase tracking-wider mb-2">Splash pages</div>
                    <h3 class="text-xl font-bold mb-3">Roll out the red carpet</h3>
                    <p class="text-gray-400 text-sm mb-4">Wrap any link with a branded interstitial — perfect for launches, age gates and announcements.</p>
                    <div class="rounded-xl bg-gradient-to-br from-[#f472b6]/20 to-[#7c3aed]/20 border border-[#f472b6]/20 p-4 text-center">
                        <div class="text-[10px] uppercase tracking-wider text-[#f472b6] font-bold mb-1">Now launching</div>
                        <div class="text-base font-bold mb-2">Spring Collection 2026</div>
                        <div class="inline-flex px-4 py-1.5 bg-white/10 rounded-full text-xs font-semibold">Continue →</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ============================ 2. SHARE EVERYWHERE ============================ --}}
<section class="py-24 lg:py-32 bg-gradient-to-b from-transparent via-[#161b26] to-transparent" aria-labelledby="share-h">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16 max-w-3xl mx-auto">
            <div class="reveal section-eyebrow mb-3">02 · Share everywhere</div>
            <h2 id="share-h" class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mb-5">
                One link.<br><span class="text-[#06b6d4]">Every channel.</span>
            </h2>
            <p class="reveal rd-2 text-lg text-gray-400">Shorten URLs, host files, generate QR codes, and turn followers into a movement.</p>
        </div>

        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div class="reveal rd-1 bg-[#1e2330] border border-white/10 rounded-3xl p-7 card-hover">
                <div class="w-12 h-12 rounded-2xl bg-[#7c3aed]/20 flex items-center justify-center mb-4"><i class="fas fa-link text-[#a78bfa] text-xl"></i></div>
                <h3 class="text-xl font-bold mb-2">Smart short links</h3>
                <p class="text-gray-400 text-sm mb-4">Branded slugs, password protection, expiry dates and click caps — built in.</p>
                <div class="bg-[#0f1320] rounded-xl p-3 border border-white/5 font-mono text-xs">
                    <span class="text-gray-500">1inme.io/</span><span class="text-[#a78bfa] font-bold">spring-launch</span>
                </div>
            </div>

            <div class="reveal rd-2 bg-[#1e2330] border border-white/10 rounded-3xl p-7 card-hover">
                <div class="w-12 h-12 rounded-2xl bg-[#06b6d4]/20 flex items-center justify-center mb-4"><i class="fas fa-file-arrow-up text-[#06b6d4] text-xl"></i></div>
                <h3 class="text-xl font-bold mb-2">File links</h3>
                <p class="text-gray-400 text-sm mb-4">Drop a PDF, deck or asset and get a trackable link instantly. No CDN setup.</p>
                <div class="flex items-center gap-3 bg-[#0f1320] rounded-xl p-3 border border-white/5">
                    <div class="w-9 h-9 rounded-lg bg-[#06b6d4]/15 flex items-center justify-center"><i class="fas fa-file-pdf text-[#06b6d4]"></i></div>
                    <div class="flex-1 min-w-0">
                        <div class="text-xs font-bold truncate">media-kit-2026.pdf</div>
                        <div class="text-[10px] text-gray-500">2.4 MB · 1,284 downloads</div>
                    </div>
                </div>
            </div>

            <div class="reveal rd-3 bg-[#1e2330] border border-white/10 rounded-3xl p-7 card-hover">
                <div class="w-12 h-12 rounded-2xl bg-[#f472b6]/20 flex items-center justify-center mb-4"><i class="fas fa-qrcode text-[#f472b6] text-xl"></i></div>
                <h3 class="text-xl font-bold mb-2">Dynamic QR codes</h3>
                <p class="text-gray-400 text-sm mb-4">Print once, redirect forever. Update destinations without reprinting.</p>
                <div class="bg-white rounded-xl p-3 flex items-center justify-center">
                    <div class="grid grid-cols-7 gap-px w-24 h-24">
                        @for($i = 0; $i < 49; $i++)
                            <div class="bg-{{ ($i * 7 + 3) % 5 < 2 ? 'white' : '[#1e2330]' }} rounded-sm"></div>
                        @endfor
                    </div>
                </div>
            </div>

            <div class="reveal rd-1 bg-[#1e2330] border border-white/10 rounded-3xl p-7 card-hover">
                <div class="w-12 h-12 rounded-2xl bg-[#fbbf24]/20 flex items-center justify-center mb-4"><i class="fas fa-fingerprint text-[#fbbf24] text-xl"></i></div>
                <h3 class="text-xl font-bold mb-2">Social sign-in for visitors</h3>
                <p class="text-gray-400 text-sm mb-4">Visitors log in with Google, Apple, or email OTP — so you actually know who showed up.</p>
                <div class="flex gap-2">
                    <div class="flex-1 py-2 bg-white text-[#1e2330] rounded-lg text-[11px] font-bold text-center"><i class="fab fa-google mr-1"></i>Google</div>
                    <div class="flex-1 py-2 bg-black text-white rounded-lg text-[11px] font-bold text-center"><i class="fab fa-apple mr-1"></i>Apple</div>
                    <div class="flex-1 py-2 bg-white/10 rounded-lg text-[11px] font-bold text-center"><i class="fas fa-envelope mr-1"></i>Email</div>
                </div>
            </div>

            <div class="reveal rd-2 bg-[#1e2330] border border-white/10 rounded-3xl p-7 card-hover md:col-span-2">
                <div class="flex items-start gap-5">
                    <div class="w-12 h-12 rounded-2xl bg-[#7c3aed]/20 flex items-center justify-center flex-shrink-0"><i class="fas fa-users text-[#a78bfa] text-xl"></i></div>
                    <div class="flex-1">
                        <h3 class="text-xl font-bold mb-2">Follower system &amp; public feed</h3>
                        <p class="text-gray-400 text-sm mb-4">Visitors can follow your page and get a personalized feed of every new post you publish — like a built-in social network of fans.</p>
                        <div class="flex items-center gap-3 flex-wrap">
                            <div class="flex -space-x-2">
                                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-[#7c3aed] to-[#8b5cf6] border-2 border-[#1e2330]"></div>
                                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-[#06b6d4] to-[#22d3ee] border-2 border-[#1e2330]"></div>
                                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-[#f43f5e] to-[#f472b6] border-2 border-[#1e2330]"></div>
                                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-[#fbbf24] to-[#f97316] border-2 border-[#1e2330]"></div>
                                <div class="w-8 h-8 rounded-full bg-white/10 border-2 border-[#1e2330] flex items-center justify-center text-[10px] font-bold">+2k</div>
                            </div>
                            <div class="px-3 py-1.5 rounded-full bg-[#7c3aed] text-xs font-bold inline-flex items-center gap-1.5"><i class="fas fa-plus text-[10px]"></i> Following</div>
                            <div class="text-xs text-gray-500">2,481 followers · 3 new posts in feed</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ============================ 3. UNDERSTAND YOUR AUDIENCE ============================ --}}
<section class="py-24 lg:py-32 relative overflow-hidden" aria-labelledby="audience-h">
    <div class="blob" style="top:20%; right:-10%; width:500px; height:500px; background:#06b6d4; opacity:.18"></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16 max-w-3xl mx-auto">
            <div class="reveal section-eyebrow mb-3">03 · Understand your audience</div>
            <h2 id="audience-h" class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mb-5">
                See every visitor.<br><span class="bg-gradient-to-r from-[#06b6d4] to-[#8b5cf6] bg-clip-text text-transparent">Live, on a map.</span>
            </h2>
            <p class="reveal rd-2 text-lg text-gray-400">A geographic heatmap with pulsing pins for visitors hitting your page right now. Plus snapshots you can export and share.</p>
        </div>

        <div class="grid lg:grid-cols-5 gap-6 items-stretch">
            {{-- World map mock --}}
            <div class="reveal rd-1 lg:col-span-3 bg-[#1e2330] border border-white/10 rounded-3xl p-7 card-hover relative overflow-hidden">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <div class="text-xs text-[#06b6d4] font-bold uppercase tracking-wider mb-1">Geographic heatmap</div>
                        <h3 class="text-xl font-bold">Live visitor pins</h3>
                    </div>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/20 text-emerald-400 text-xs font-semibold"><span class="w-1.5 h-1.5 rounded-full bg-emerald-400 pulse-dot"></span>Live</span>
                </div>

                <div class="relative aspect-[16/9] rounded-2xl bg-[#0f1320] border border-white/5 overflow-hidden">
                    {{-- Stylized world map (svg silhouette) --}}
                    <svg viewBox="0 0 800 400" class="absolute inset-0 w-full h-full opacity-40" preserveAspectRatio="xMidYMid slice">
                        <defs>
                            <pattern id="dots" width="10" height="10" patternUnits="userSpaceOnUse">
                                <circle cx="2" cy="2" r="1" fill="#7c3aed" opacity=".4"/>
                            </pattern>
                        </defs>
                        <path d="M100,180 Q150,120 220,140 T350,150 T500,160 T700,140 L720,260 Q600,290 480,270 T260,290 T100,280 Z" fill="url(#dots)"/>
                        <path d="M120,310 Q200,290 280,320 T440,310 T620,330" fill="none" stroke="#06b6d4" stroke-width="1" opacity=".3"/>
                    </svg>
                    {{-- Pulsing pins --}}
                    @php $pins = [['18%','38%','#7c3aed'], ['46%','42%','#06b6d4'], ['62%','55%','#f43f5e'], ['78%','48%','#fbbf24'], ['32%','62%','#a78bfa'], ['52%','30%','#22d3ee']]; @endphp
                    @foreach($pins as $i => $p)
                        <div class="absolute" style="left:{{ $p[0] }}; top:{{ $p[1] }};">
                            <div class="relative">
                                <span class="absolute inset-0 rounded-full pulse-ring" style="background:{{ $p[2] }}; width:14px; height:14px; opacity:.6; animation-delay: -{{ $i * 0.4 }}s"></span>
                                <span class="relative block w-3.5 h-3.5 rounded-full ring-2 ring-white/20" style="background:{{ $p[2] }}"></span>
                            </div>
                        </div>
                    @endforeach
                    {{-- City callout --}}
                    <div class="absolute bottom-3 left-3 bg-[#1e2330]/90 backdrop-blur-sm rounded-xl p-2.5 border border-white/10 text-xs">
                        <div class="text-[9px] uppercase tracking-wider text-gray-500">Top city</div>
                        <div class="font-bold">Mumbai · <span class="text-[#06b6d4]">38 live</span></div>
                    </div>
                    <div class="absolute top-3 right-3 bg-[#1e2330]/90 backdrop-blur-sm rounded-xl p-2.5 border border-white/10 text-xs">
                        <div class="text-[9px] uppercase tracking-wider text-gray-500">Visitors / hr</div>
                        <div class="font-bold text-[#a78bfa]">1,247 ↑</div>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3 mt-4">
                    <div class="bg-[#0f1320] rounded-xl p-3 border border-white/5">
                        <div class="text-[10px] text-gray-500 uppercase tracking-wider">Cities</div>
                        <div class="text-lg font-bold">142</div>
                    </div>
                    <div class="bg-[#0f1320] rounded-xl p-3 border border-white/5">
                        <div class="text-[10px] text-gray-500 uppercase tracking-wider">Countries</div>
                        <div class="text-lg font-bold">38</div>
                    </div>
                    <div class="bg-[#0f1320] rounded-xl p-3 border border-white/5">
                        <div class="text-[10px] text-gray-500 uppercase tracking-wider">Live now</div>
                        <div class="text-lg font-bold text-emerald-400">247</div>
                    </div>
                </div>
            </div>

            {{-- Right stack: snapshot + clicks chart --}}
            <div class="lg:col-span-2 grid gap-6">
                <div class="reveal rd-2 bg-[#1e2330] border border-white/10 rounded-3xl p-6 card-hover">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <div class="text-xs text-[#f472b6] font-bold uppercase tracking-wider mb-1">Snapshots</div>
                            <h3 class="text-base font-bold">Export &amp; share</h3>
                        </div>
                        <i class="fas fa-camera text-[#f472b6]"></i>
                    </div>
                    <p class="text-gray-400 text-xs mb-3">One-click PNG exports of any chart. Share to social with a tap.</p>
                    <div class="bg-gradient-to-br from-[#7c3aed]/20 to-[#06b6d4]/20 rounded-xl p-3 border border-white/10">
                        <div class="flex items-center justify-between mb-2">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Snapshot · Apr 2026</div>
                            <i class="fas fa-share-nodes text-xs text-gray-400"></i>
                        </div>
                        <svg class="w-full h-12" viewBox="0 0 200 50" preserveAspectRatio="none">
                            <polyline class="spark-line" fill="none" stroke="#a78bfa" stroke-width="2" points="0,40 25,32 50,35 75,22 100,28 125,15 150,18 175,8 200,12"/>
                        </svg>
                    </div>
                </div>

                <div class="reveal rd-3 bg-[#1e2330] border border-white/10 rounded-3xl p-6 card-hover">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <div class="text-xs text-[#06b6d4] font-bold uppercase tracking-wider mb-1">Clicks over time</div>
                            <h3 class="text-base font-bold">Track every tap</h3>
                        </div>
                    </div>
                    <div class="flex items-end gap-1.5 h-20">
                        @php $bars = [40, 55, 35, 70, 60, 85, 75, 95, 80, 100, 90, 110]; @endphp
                        @foreach($bars as $h)
                            <div class="flex-1 rounded-t-md bg-gradient-to-t from-[#7c3aed] to-[#06b6d4]" style="height:{{ $h * 0.7 }}%"></div>
                        @endforeach
                    </div>
                    <div class="flex justify-between text-[9px] text-gray-500 mt-2">
                        <span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ============================ 4. PERFORMANCE COACH ============================ --}}
<section class="py-24 lg:py-32 bg-[#161b26] border-y border-white/5" aria-labelledby="coach-h">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-2 gap-12 items-center">
            <div>
                <div class="reveal section-eyebrow mb-3">04 · Performance Coach</div>
                <h2 id="coach-h" class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mb-5">
                    A coach in your<br><span class="bg-gradient-to-r from-[#fbbf24] to-[#f43f5e] bg-clip-text text-transparent">corner.</span>
                </h2>
                <p class="reveal rd-2 text-lg text-gray-400 mb-6">
                    Get a real-time health score for your page, see exactly which factor is dragging you down, and fix it in one click — with undo, always.
                </p>

                <div class="space-y-3">
                    <div class="reveal rd-3 flex items-start gap-3 bg-white/[0.03] border border-white/10 rounded-2xl p-4">
                        <div class="w-9 h-9 rounded-lg bg-[#fbbf24]/20 flex items-center justify-center flex-shrink-0"><i class="fas fa-gauge-high text-[#fbbf24]"></i></div>
                        <div>
                            <div class="text-sm font-bold mb-0.5">Live health score &amp; trend chart</div>
                            <div class="text-xs text-gray-500">Watch your score climb week over week.</div>
                        </div>
                    </div>
                    <div class="reveal rd-3 flex items-start gap-3 bg-white/[0.03] border border-white/10 rounded-2xl p-4">
                        <div class="w-9 h-9 rounded-lg bg-[#f43f5e]/20 flex items-center justify-center flex-shrink-0"><i class="fas fa-bullseye text-[#f43f5e]"></i></div>
                        <div>
                            <div class="text-sm font-bold mb-0.5">Weakest-factor callouts</div>
                            <div class="text-xs text-gray-500">We point you straight at the one thing to fix next.</div>
                        </div>
                    </div>
                    <div class="reveal rd-4 flex items-start gap-3 bg-white/[0.03] border border-white/10 rounded-2xl p-4">
                        <div class="w-9 h-9 rounded-lg bg-emerald-500/20 flex items-center justify-center flex-shrink-0"><i class="fas fa-wand-magic-sparkles text-emerald-400"></i></div>
                        <div>
                            <div class="text-sm font-bold mb-0.5">One-click fixes (with undo)</div>
                            <div class="text-xs text-gray-500">Apply a recommended fix instantly. Revert with one tap.</div>
                        </div>
                    </div>
                    <div class="reveal rd-4 flex items-start gap-3 bg-white/[0.03] border border-white/10 rounded-2xl p-4">
                        <div class="w-9 h-9 rounded-lg bg-[#7c3aed]/20 flex items-center justify-center flex-shrink-0"><i class="fas fa-sliders text-[#a78bfa]"></i></div>
                        <div>
                            <div class="text-sm font-bold mb-0.5">Customizable thresholds</div>
                            <div class="text-xs text-gray-500">Tune what "healthy" means for you, with plain-English explanations.</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Coach card mock --}}
            <div class="reveal rd-2 relative">
                <div class="bg-[#1e2330] border border-white/10 rounded-3xl p-7 shadow-2xl shadow-[#7c3aed]/10">
                    <div class="flex items-center justify-between mb-5">
                        <div class="flex items-center gap-3">
                            <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-[#7c3aed] to-[#06b6d4] flex items-center justify-center"><i class="fas fa-bolt text-white"></i></div>
                            <div>
                                <div class="text-xs text-gray-500">Performance Coach</div>
                                <div class="text-sm font-bold">jane-doe.1inme.io</div>
                            </div>
                        </div>
                        <span class="px-2.5 py-1 rounded-full bg-emerald-500/15 text-emerald-400 text-[10px] font-bold uppercase tracking-wider">Healthy</span>
                    </div>

                    {{-- Gauge --}}
                    <div class="flex items-center justify-center mb-5">
                        <div class="relative w-44 h-44">
                            <svg viewBox="0 0 100 100" class="w-full h-full -rotate-90">
                                <circle cx="50" cy="50" r="40" fill="none" stroke="#ffffff10" stroke-width="8"/>
                                <circle class="gauge-arc" cx="50" cy="50" r="40" fill="none" stroke="url(#g1)" stroke-width="8" stroke-linecap="round"/>
                                <defs>
                                    <linearGradient id="g1"><stop offset="0%" stop-color="#7c3aed"/><stop offset="100%" stop-color="#06b6d4"/></linearGradient>
                                </defs>
                            </svg>
                            <div class="absolute inset-0 flex flex-col items-center justify-center">
                                <div class="text-5xl font-bold">87</div>
                                <div class="text-xs text-gray-500 uppercase tracking-wider">Health score</div>
                            </div>
                        </div>
                    </div>

                    {{-- Trend --}}
                    <div class="bg-[#0f1320] rounded-2xl p-4 border border-white/5 mb-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs text-gray-400">8-week trend</span>
                            <span class="text-xs text-emerald-400 font-bold">+14 pts</span>
                        </div>
                        <svg class="w-full h-14" viewBox="0 0 200 50" preserveAspectRatio="none">
                            <polyline class="spark-line" fill="none" stroke="url(#g2)" stroke-width="2.5" stroke-linecap="round" points="0,42 25,38 50,32 75,30 100,24 125,20 150,15 175,12 200,8"/>
                            <defs><linearGradient id="g2"><stop offset="0%" stop-color="#7c3aed"/><stop offset="100%" stop-color="#06b6d4"/></linearGradient></defs>
                        </svg>
                    </div>

                    {{-- Callout --}}
                    <div class="bg-gradient-to-r from-[#f43f5e]/15 to-transparent border border-[#f43f5e]/20 rounded-2xl p-4">
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-lg bg-[#f43f5e]/20 flex items-center justify-center flex-shrink-0 mt-0.5"><i class="fas fa-triangle-exclamation text-[#f43f5e] text-xs"></i></div>
                            <div class="flex-1 min-w-0">
                                <div class="text-xs text-gray-400 mb-0.5">Weakest factor</div>
                                <div class="text-sm font-bold mb-2">Mobile load time is 3.4s (target: 2s)</div>
                                <div class="flex gap-2">
                                    <button class="px-3 py-1.5 bg-[#7c3aed] rounded-lg text-[11px] font-bold hover:bg-[#6d28d9]"><i class="fas fa-wand-magic-sparkles mr-1"></i>One-click fix</button>
                                    <button class="px-3 py-1.5 bg-white/10 rounded-lg text-[11px] font-bold hover:bg-white/15">Adjust threshold</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ============================ 5. CAPTURE & ENGAGE ============================ --}}
<section class="py-24 lg:py-32" aria-labelledby="engage-h">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16 max-w-3xl mx-auto">
            <div class="reveal section-eyebrow mb-3">05 · Capture &amp; engage</div>
            <h2 id="engage-h" class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mb-5">
                Turn visitors into a<br><span class="text-[#f472b6]">community.</span>
            </h2>
            <p class="reveal rd-2 text-lg text-gray-400">Forms, social proof, follower digests and exports — all the tools to capture leads and keep them coming back.</p>
        </div>

        <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
            {{-- Form builder --}}
            <div class="reveal rd-1 bg-[#1e2330] border border-white/10 rounded-3xl p-6 card-hover">
                <div class="w-11 h-11 rounded-xl bg-[#7c3aed]/20 flex items-center justify-center mb-4"><i class="fas fa-wpforms text-[#a78bfa]"></i></div>
                <h3 class="font-bold mb-2">Public form builder</h3>
                <p class="text-xs text-gray-400 mb-4">Build forms with a visual editor. Embed anywhere or share as a link.</p>
                <div class="bg-[#0f1320] rounded-lg p-3 border border-white/5 space-y-2">
                    <div class="h-2 w-full bg-white/10 rounded-full"></div>
                    <div class="h-6 w-full bg-white/5 border border-white/10 rounded"></div>
                    <div class="h-6 w-full bg-white/5 border border-white/10 rounded"></div>
                    <div class="h-6 w-1/2 bg-[#7c3aed] rounded"></div>
                </div>
            </div>

            {{-- Social proof --}}
            <div class="reveal rd-2 bg-[#1e2330] border border-white/10 rounded-3xl p-6 card-hover">
                <div class="w-11 h-11 rounded-xl bg-[#fbbf24]/20 flex items-center justify-center mb-4"><i class="fas fa-bullhorn text-[#fbbf24]"></i></div>
                <h3 class="font-bold mb-2">Social proof widgets</h3>
                <p class="text-xs text-gray-400 mb-4">Show real-time signups, sales and visitor pops on your page.</p>
                <div class="bg-[#0f1320] rounded-xl p-2.5 border border-white/5 flex items-center gap-2">
                    <div class="w-7 h-7 rounded-full bg-gradient-to-br from-[#fbbf24] to-[#f43f5e] flex-shrink-0"></div>
                    <div class="text-[10px]">
                        <div class="font-bold">Sara just signed up!</div>
                        <div class="text-gray-500">2 min ago · Berlin</div>
                    </div>
                </div>
            </div>

            {{-- Follower digests --}}
            <div class="reveal rd-3 bg-[#1e2330] border border-white/10 rounded-3xl p-6 card-hover">
                <div class="w-11 h-11 rounded-xl bg-[#06b6d4]/20 flex items-center justify-center mb-4"><i class="fas fa-envelope-open-text text-[#06b6d4]"></i></div>
                <h3 class="font-bold mb-2">Follower email digests</h3>
                <p class="text-xs text-gray-400 mb-4">Auto-send a weekly recap of your latest posts to every follower.</p>
                <div class="bg-[#0f1320] rounded-lg p-3 border border-white/5">
                    <div class="text-[10px] uppercase tracking-wider text-gray-500 mb-1">Weekly digest</div>
                    <div class="text-xs font-bold mb-2">3 new posts this week</div>
                    <div class="space-y-1">
                        <div class="h-1.5 bg-white/10 rounded-full"></div>
                        <div class="h-1.5 bg-white/10 rounded-full w-4/5"></div>
                        <div class="h-1.5 bg-white/10 rounded-full w-2/3"></div>
                    </div>
                </div>
            </div>

            {{-- Follower exports --}}
            <div class="reveal rd-4 bg-[#1e2330] border border-white/10 rounded-3xl p-6 card-hover">
                <div class="w-11 h-11 rounded-xl bg-emerald-500/20 flex items-center justify-center mb-4"><i class="fas fa-file-export text-emerald-400"></i></div>
                <h3 class="font-bold mb-2">Follower list exports</h3>
                <p class="text-xs text-gray-400 mb-4">Download your follower list as CSV anytime — your audience belongs to you.</p>
                <div class="flex items-center gap-2 bg-[#0f1320] rounded-lg p-2.5 border border-white/5">
                    <i class="fas fa-file-csv text-emerald-400"></i>
                    <span class="text-xs font-mono">followers.csv</span>
                    <span class="text-[10px] text-gray-500 ml-auto">2,481 rows</span>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ============================ 6. CONNECT YOUR STACK ============================ --}}
<section class="py-24 lg:py-32 bg-[#161b26] border-y border-white/5" aria-labelledby="stack-h">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-2 gap-12 items-center">
            <div>
                <div class="reveal section-eyebrow mb-3">06 · Connect your stack</div>
                <h2 id="stack-h" class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mb-5">
                    Plug into the<br><span class="bg-gradient-to-r from-[#06b6d4] to-[#22d3ee] bg-clip-text text-transparent">tools you already use.</span>
                </h2>
                <p class="reveal rd-2 text-lg text-gray-400 mb-8">Sync Google Contacts, dial leads from inside the dashboard, and let visitors sign in with the social accounts they already have.</p>

                <div class="reveal rd-3 grid sm:grid-cols-2 gap-3">
                    <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-4">
                        <div class="flex items-center gap-3 mb-2"><i class="fab fa-google text-[#06b6d4] text-lg"></i><span class="text-sm font-bold">Google Contacts sync</span></div>
                        <p class="text-xs text-gray-500">Two-way sync — your contacts, everywhere.</p>
                    </div>
                    <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-4">
                        <div class="flex items-center gap-3 mb-2"><i class="fas fa-phone text-emerald-400 text-lg"></i><span class="text-sm font-bold">Built-in dialer</span></div>
                        <p class="text-xs text-gray-500">Click to call any contact, log everything.</p>
                    </div>
                    <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-4">
                        <div class="flex items-center gap-3 mb-2"><i class="fas fa-fingerprint text-[#a78bfa] text-lg"></i><span class="text-sm font-bold">Social auth providers</span></div>
                        <p class="text-xs text-gray-500">Google, Apple, email OTP for visitors.</p>
                    </div>
                    <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-4">
                        <div class="flex items-center gap-3 mb-2"><i class="fas fa-code text-[#f472b6] text-lg"></i><span class="text-sm font-bold">Embed widgets</span></div>
                        <p class="text-xs text-gray-500">Drop a 1INME form or proof widget on any site.</p>
                    </div>
                </div>
            </div>

            {{-- Stack visual --}}
            <div class="reveal rd-2 relative">
                <div class="relative aspect-square max-w-md mx-auto">
                    <div class="absolute inset-0 rounded-full border border-dashed border-white/10"></div>
                    <div class="absolute inset-8 rounded-full border border-dashed border-white/10"></div>
                    <div class="absolute inset-16 rounded-full border border-dashed border-white/10"></div>

                    <div class="absolute inset-0 flex items-center justify-center">
                        <div class="w-24 h-24 rounded-3xl bg-gradient-to-br from-[#7c3aed] to-[#06b6d4] flex items-center justify-center shadow-2xl shadow-[#7c3aed]/40">
                            <span class="text-white font-bold text-lg">1INME</span>
                        </div>
                    </div>

                    @php $logos = [
                        ['fab fa-google','#fff','top-0 left-1/2 -translate-x-1/2'],
                        ['fab fa-apple','#fff','top-1/4 right-0 translate-x-1/2'],
                        ['fab fa-facebook','#1877f2','bottom-1/4 right-0 translate-x-1/2'],
                        ['fab fa-spotify','#1ed760','bottom-0 left-1/2 -translate-x-1/2'],
                        ['fab fa-youtube','#f43f5e','bottom-1/4 left-0 -translate-x-1/2'],
                        ['fas fa-phone','#34d399','top-1/4 left-0 -translate-x-1/2'],
                    ]; @endphp
                    @foreach($logos as $i => $l)
                        <div class="absolute {{ $l[2] }} float-a" style="animation-delay:-{{ $i * 0.7 }}s">
                            <div class="w-14 h-14 rounded-2xl bg-[#1e2330] border border-white/10 flex items-center justify-center shadow-lg">
                                <i class="{{ $l[0] }} text-xl" style="color:{{ $l[1] }}"></i>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ============================ 7. USE CASES (refreshed) ============================ --}}
@php
    $useCases = [
        ['key'=>'creators',     'icon'=>'fa-palette',         'label'=>'Creators',       'color'=>'#7c3aed', 'badge'=>'CREATORS &amp; INFLUENCERS', 'title'=>'A whole studio in one link', 'desc'=>"Stack videos, audio, galleries and gated downloads on a drag-and-drop page. Followers get pinged when you ship something new.", 'checks'=>['Drag-and-drop multi-block pages','Built-in follower system &amp; feed','Live geo heatmap of every visitor','Performance Coach to grow faster']],
        ['key'=>'cafes',        'icon'=>'fa-mug-saucer',      'label'=>'Coffee Shops',   'color'=>'#059669', 'badge'=>'CAFES &amp; LOCAL SHOPS',     'title'=>'Bring your menu to life',     'desc'=>"Print one QR, update the menu forever. Capture reviews, run loyalty forms, and see which tables actually scan.", 'checks'=>['Dynamic QR codes for menus','Live visitor pins by city','Form builder for loyalty signups','Social proof widget for reviews']],
        ['key'=>'events',       'icon'=>'fa-calendar-check',  'label'=>'Events',         'color'=>'#ea580c', 'badge'=>'EVENT ORGANIZERS',          'title'=>'Tickets, RSVP, check-in.',    'desc'=>"Run the entire event off one link: ticket pages, RSVP forms, calendar files, and a check-in QR. Snapshot the analytics for sponsors.", 'checks'=>['RSVP &amp; ticket pages','QR check-in codes','Exportable analytics snapshots','Splash pages for announcements']],
        ['key'=>'ecom',         'icon'=>'fa-shopping-bag',    'label'=>'E-commerce',     'color'=>'#e11d48', 'badge'=>'E-COMMERCE &amp; RETAIL',     'title'=>'Drive sales from every channel','desc'=>"Build a shoppable bio page, drop social proof popups on conversions, and track every click with rich UTM and pixel support.", 'checks'=>['Shoppable bio pages','Social proof conversion popups','Pixel &amp; UTM tracking','One-click Coach fixes']],
        ['key'=>'freelancers',  'icon'=>'fa-briefcase',       'label'=>'Freelancers',    'color'=>'#2563eb', 'badge'=>'FREELANCERS &amp; AGENCIES',  'title'=>'A portfolio that closes deals','desc'=>"Showcase work, sync prospects from Google Contacts, dial them in one click, and capture leads with embedded forms.", 'checks'=>['Portfolio biolink pages','Google Contacts sync','Built-in dialer for outreach','Embedded lead forms']],
        ['key'=>'small-biz',    'icon'=>'fa-store',           'label'=>'Small Business', 'color'=>'#0891b2', 'badge'=>'SMALL BUSINESS',            'title'=>'Show up like a big brand',    'desc'=>"Branded short links, dynamic QR codes for packaging, follower digests for repeat customers — without hiring an agency.", 'checks'=>['Branded short links','Dynamic QR for packaging','Weekly follower digests','City-level visitor analytics']],
    ];
@endphp
<section id="use-cases" class="py-24 lg:py-32" aria-labelledby="usecases-h" x-data="{ active: 0, auto: true }" x-init="setInterval(()=>{ if(auto) active = (active+1) % {{ count($useCases) }} }, 5000)">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12 max-w-3xl mx-auto">
            <div class="reveal section-eyebrow mb-3">07 · Use cases</div>
            <h2 id="usecases-h" class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mb-5">
                Built for <span class="text-[#a78bfa]">every</span> kind of work.
            </h2>
            <p class="reveal rd-2 text-lg text-gray-400">From creators to coffee shops — 1INME shapes itself around what you do.</p>
        </div>

        <div class="reveal rd-2 flex flex-wrap justify-center gap-2 mb-10">
            @foreach($useCases as $i => $uc)
                <button @click="active = {{ $i }}; auto = false"
                        :class="active === {{ $i }} ? 'text-white shadow-lg scale-105' : 'bg-white/5 text-gray-300 hover:bg-white/10 border border-white/10'"
                        :style="active === {{ $i }} ? 'background-color: {{ $uc['color'] }}' : ''"
                        class="px-5 py-2.5 rounded-full text-sm font-bold transition-all flex items-center gap-2">
                    <i class="fas {{ $uc['icon'] }}"></i>{{ $uc['label'] }}
                </button>
            @endforeach
        </div>

        <div class="relative min-h-[420px]">
            @foreach($useCases as $i => $uc)
                <div x-show="active === {{ $i }}" @if($i > 0) x-cloak @endif x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" class="grid lg:grid-cols-2 gap-12 items-center">
                    <div>
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold mb-4" style="background-color: {{ $uc['color'] }}20; color: {{ $uc['color'] }}">{!! $uc['badge'] !!}</div>
                        <h3 class="text-3xl sm:text-4xl font-bold mb-4">{{ $uc['title'] }}</h3>
                        <p class="text-gray-400 mb-6 leading-relaxed text-lg">{{ $uc['desc'] }}</p>
                        <ul class="space-y-3">
                            @foreach($uc['checks'] as $check)
                                <li class="flex items-center gap-3">
                                    <span class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0" style="background-color: {{ $uc['color'] }}25"><i class="fas fa-check text-xs" style="color: {{ $uc['color'] }}"></i></span>
                                    <span class="text-sm text-gray-200 font-medium">{!! $check !!}</span>
                                </li>
                            @endforeach
                        </ul>
                        <a href="{{ route('user.register') }}" class="mt-6 inline-flex items-center gap-2 px-6 py-3 rounded-full text-sm font-bold text-white" style="background-color: {{ $uc['color'] }}">Try it free <i class="fas fa-arrow-right text-xs"></i></a>
                    </div>
                    <div class="flex justify-center">
                        <div class="w-72 sm:w-80 rounded-[2rem] p-[3px] shadow-2xl" style="background: linear-gradient(135deg, {{ $uc['color'] }}, {{ $uc['color'] }}88);">
                            <div class="bg-[#1e2330] rounded-[1.85rem] p-5 space-y-2.5">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-11 h-11 rounded-full flex items-center justify-center text-white" style="background-color: {{ $uc['color'] }}"><i class="fas {{ $uc['icon'] }}"></i></div>
                                    <div><div class="font-bold text-sm">{{ $uc['label'] }}</div><div class="text-[10px] text-gray-500">Powered by 1INME</div></div>
                                </div>
                                <div class="bg-white/5 rounded-lg p-2.5 border border-white/5 text-[11px] text-gray-300 leading-relaxed">{{ \Illuminate\Support\Str::limit(strip_tags($uc['desc']), 120) }}</div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div class="rounded-lg p-2 border border-white/5" style="background-color: {{ $uc['color'] }}15">
                                        <div class="text-[9px] text-gray-400 uppercase">Live</div>
                                        <div class="text-sm font-bold">247 <span class="text-[10px] text-emerald-400">↑</span></div>
                                    </div>
                                    <div class="rounded-lg p-2 border border-white/5 bg-white/5">
                                        <div class="text-[9px] text-gray-400 uppercase">Score</div>
                                        <div class="text-sm font-bold">87/100</div>
                                    </div>
                                </div>
                                <div class="py-2.5 rounded-lg text-xs font-bold text-center text-white" style="background-color: {{ $uc['color'] }}"><i class="fas fa-arrow-right mr-1"></i>Open page</div>
                                <div class="py-2 rounded-lg text-xs font-bold text-center bg-white/10"><i class="fas fa-qrcode mr-1"></i>Share QR</div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex justify-center gap-2 mt-10">
            @for($i = 0; $i < count($useCases); $i++)
                <button @click="active = {{ $i }}; auto = false" :class="active === {{ $i }} ? 'w-10' : 'w-2.5'" class="h-2.5 rounded-full transition-all" :style="active === {{ $i }} ? 'background-color: #7c3aed' : 'background-color: #ffffff20'" aria-label="Use case {{ $i + 1 }}"></button>
            @endfor
        </div>
    </div>
</section>

{{-- ============================ HOW IT WORKS ============================ --}}
<section id="how-it-works" class="py-24 lg:py-32 bg-[#161b26] border-y border-white/5" aria-labelledby="how-h">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16 max-w-3xl mx-auto">
            <div class="reveal section-eyebrow mb-3">08 · How it works</div>
            <h2 id="how-h" class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mb-5">
                Live in <span class="text-[#a78bfa]">3 minutes.</span>
            </h2>
            <p class="reveal rd-2 text-lg text-gray-400">No code. No designer. No credit card.</p>
        </div>

        <div class="grid md:grid-cols-3 gap-8 lg:gap-10 relative">
            <div class="hidden md:block absolute top-10 left-[18%] right-[18%] h-px bg-gradient-to-r from-transparent via-[#7c3aed]/40 to-transparent"></div>

            @foreach([
                ['1','Sign up free','Create your account in 30 seconds. No credit card.','fa-user-plus','#7c3aed'],
                ['2','Build your page','Drag in blocks, tweak a theme, drop in QR codes and forms.','fa-grip','#06b6d4'],
                ['3','Share &amp; grow','Track every visitor live, and let the Coach tell you what to fix next.','fa-rocket','#f43f5e'],
            ] as $i => $step)
                <div class="reveal rd-{{ $i + 1 }} text-center relative">
                    <div class="w-20 h-20 mx-auto rounded-2xl flex items-center justify-center text-white text-2xl font-bold mb-6 shadow-lg relative z-10" style="background-color: {{ $step[4] }}; box-shadow: 0 10px 30px -10px {{ $step[4] }}80;">
                        <i class="fas {{ $step[3] }}"></i>
                    </div>
                    <div class="text-xs text-[#a78bfa] font-bold uppercase tracking-wider mb-2">Step {{ $step[0] }}</div>
                    <h3 class="text-xl font-bold mb-3">{!! $step[1] !!}</h3>
                    <p class="text-gray-400 text-sm leading-relaxed">{!! $step[2] !!}</p>
                </div>
            @endforeach
        </div>

        <div class="reveal rd-4 text-center mt-14">
            <a href="{{ route('user.register') }}" class="inline-flex items-center gap-2 px-8 py-4 bg-[#7c3aed] text-white rounded-full text-base font-bold hover:bg-[#6d28d9] transition-all hover:shadow-xl hover:shadow-[#7c3aed]/30 hover:-translate-y-0.5">
                Start building now <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </div>
</section>

{{-- ============================ PRICING ============================ --}}
<section id="pricing" class="py-24 lg:py-32 relative overflow-hidden" aria-labelledby="pricing-h">
    <div class="blob" style="top:0; left:50%; transform:translateX(-50%); width:600px; height:400px; background:#7c3aed; opacity:.15"></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12 max-w-3xl mx-auto">
            <div class="reveal section-eyebrow mb-3">09 · Pricing</div>
            <h2 id="pricing-h" class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mb-5">
                Simple, <span class="text-[#a78bfa]">transparent</span> pricing.
            </h2>
            <p class="reveal rd-2 text-lg text-gray-400">Start free. Upgrade only when you outgrow it.</p>
        </div>

        @php
            $showSwitcher = auth()->check() ? empty(auth()->user()->country) : true;
        @endphp
        @if ($showSwitcher)
        <div class="flex items-center justify-center gap-2 mb-8">
            <span class="text-xs uppercase tracking-wider text-gray-500">Show prices in:</span>
            <form method="POST" action="{{ route('upgrade.public.switch-currency') }}" class="inline-flex">
                @csrf
                <button type="submit" name="currency" value="USD" class="px-3 py-1 text-xs rounded-l-full border border-white/10 {{ ($currency ?? 'USD') === 'USD' ? 'bg-[#7c3aed] text-white' : 'bg-white/5 text-gray-300 hover:bg-white/10' }}">USD&nbsp;($)</button>
                <button type="submit" name="currency" value="INR" class="px-3 py-1 text-xs rounded-r-full border border-white/10 border-l-0 {{ ($currency ?? 'USD') === 'INR' ? 'bg-[#7c3aed] text-white' : 'bg-white/5 text-gray-300 hover:bg-white/10' }}">INR&nbsp;(₹)</button>
            </form>
        </div>
        @endif

        @php
            $planCount = max(1, count($plans));
            $gridClass = match (true) {
                $planCount >= 4 => 'md:grid-cols-4',
                $planCount === 3 => 'md:grid-cols-3',
                $planCount === 2 => 'md:grid-cols-2',
                default => 'md:grid-cols-1',
            };
        @endphp
        <div class="grid {{ $gridClass }} gap-6 max-w-5xl mx-auto">
            @foreach($plans as $i => $plan)
                @php
                    $featured = $i === 1;
                    $f = $plan['features'];
                @endphp
                <div class="reveal rd-{{ $i + 1 }} card-hover relative rounded-3xl p-8 {{ $featured ? 'bg-gradient-to-br from-[#7c3aed] to-[#8b5cf6] shadow-2xl shadow-[#7c3aed]/30 scale-105' : 'bg-white/[0.03] border border-white/10 backdrop-blur-sm' }}">
                    @if($featured)
                        <div class="absolute -top-3 right-6 px-3 py-1 bg-[#06b6d4] text-[#1e2330] text-xs font-bold rounded-full">MOST POPULAR</div>
                    @endif
                    <div class="text-xs font-bold uppercase tracking-wider mb-2 {{ $featured ? 'text-white/70' : 'text-gray-400' }}">{{ $plan['name'] }}</div>
                    <div class="text-5xl font-bold mb-1 text-white">
                        {{ $plan['monthly']['formatted'] }}@unless($plan['is_free'])<span class="text-lg font-medium {{ $featured ? 'text-white/50' : 'text-gray-500' }}">/mo</span>@endunless
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
                            <div class="text-[11px] {{ $featured ? 'text-white/60' : 'text-gray-500' }} mb-1">+ taxes as applicable (GST/VAT shown at checkout once you add a billing address)</div>
                        @endif
                    @endunless
                    <div class="text-sm mb-6 {{ $featured ? 'text-white/60' : 'text-gray-500' }}">{{ $plan['description'] ?: ($plan['is_free'] ? 'Forever free' : 'Per user, billed monthly') }}</div>
                    <ul class="space-y-3 mb-8">
                        @foreach(['max_links' => 'links', 'max_biolinks' => 'bio pages', 'storage_limit_mb' => 'MB storage', 'contacts_max' => 'contacts'] as $key => $label)
                            @if(isset($f[$key]))
                                <li class="flex items-center gap-2 text-sm text-white">
                                    <i class="fas fa-check text-xs {{ $featured ? 'text-white' : 'text-[#a78bfa]' }}"></i>
                                    {{ (int) $f[$key] === -1 ? 'Unlimited' : number_format((int) $f[$key]) }} {{ $label }}
                                </li>
                            @endif
                        @endforeach
                    </ul>
                    <a href="{{ route('user.register') }}" class="block w-full py-3.5 text-center rounded-full text-sm font-bold transition-all {{ $featured ? 'bg-white text-[#7c3aed] hover:bg-gray-100' : 'border-2 border-white/20 text-white hover:border-white/40 hover:bg-white/5' }}">
                        {{ $plan['is_free'] ? 'Get started' : 'Start free trial' }}
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ============================ FINAL CTA ============================ --}}
<section class="py-24 lg:py-32 relative overflow-hidden" aria-labelledby="cta-h">
    <div class="blob blob-spin" style="top:-20%; left:10%; width:500px; height:500px; background:#7c3aed; opacity:.3"></div>
    <div class="blob blob-spin" style="bottom:-20%; right:10%; width:500px; height:500px; background:#06b6d4; opacity:.25; animation-delay:-12s"></div>

    <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 id="cta-h" class="reveal text-4xl sm:text-5xl lg:text-6xl font-bold mb-6 leading-tight">
            Your audience is<br>
            <span class="bg-gradient-to-r from-[#a78bfa] via-[#06b6d4] to-[#f472b6] bg-clip-text text-transparent">already searching for you.</span>
        </h2>
        <p class="reveal rd-1 text-lg text-gray-400 mb-10 max-w-xl mx-auto">
            Build the page. Share the link. Watch them show up — live on a map.
        </p>
        <div class="reveal rd-2 flex flex-col sm:flex-row gap-3 justify-center">
            <a href="{{ route('user.register') }}" class="inline-flex items-center gap-2 px-10 py-5 bg-[#7c3aed] text-white rounded-full text-lg font-bold hover:bg-[#6d28d9] transition-all hover:shadow-2xl hover:shadow-[#7c3aed]/40 hover:-translate-y-1">
                Sign up free <i class="fas fa-arrow-right"></i>
            </a>
            <a href="#features" class="inline-flex items-center gap-2 px-10 py-5 bg-white/10 border border-white/15 backdrop-blur-sm text-white rounded-full text-lg font-bold hover:bg-white/15 transition-all">
                See features
            </a>
        </div>
    </div>
</section>

{{-- ============================ FOOTER ============================ --}}
<footer class="bg-[#0f1320] text-white pt-16 pb-8 border-t border-white/5">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid md:grid-cols-5 gap-8 mb-12">
            <div class="md:col-span-2">
                <a href="{{ route('home') }}" class="inline-flex items-center" aria-label="1INME home">
                    @include('common.partials.brand-logo', ['height' => 'h-9'])
                </a>
                <p class="text-sm text-gray-500 mt-3 leading-relaxed max-w-sm">The all-in-one link platform: build a drag-and-drop biolink, share it everywhere, and grow with live analytics and a built-in Performance Coach.</p>
            </div>
            <div>
                <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4">Product</h4>
                <ul class="space-y-2.5">
                    <li><a href="#features" class="text-sm text-gray-500 hover:text-[#a78bfa] transition-colors">Features</a></li>
                    <li><a href="#use-cases" class="text-sm text-gray-500 hover:text-[#a78bfa] transition-colors">Use Cases</a></li>
                    <li><a href="#how-it-works" class="text-sm text-gray-500 hover:text-[#a78bfa] transition-colors">How It Works</a></li>
                    <li><a href="#pricing" class="text-sm text-gray-500 hover:text-[#a78bfa] transition-colors">Pricing</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4">Tools</h4>
                <ul class="space-y-2.5">
                    <li><a href="{{ route('user.register') }}" class="text-sm text-gray-500 hover:text-[#a78bfa] transition-colors">Biolink Editor</a></li>
                    <li><a href="{{ route('user.register') }}" class="text-sm text-gray-500 hover:text-[#a78bfa] transition-colors">Short Links</a></li>
                    <li><a href="{{ route('user.register') }}" class="text-sm text-gray-500 hover:text-[#a78bfa] transition-colors">QR Generator</a></li>
                    <li><a href="{{ route('user.register') }}" class="text-sm text-gray-500 hover:text-[#a78bfa] transition-colors">Form Builder</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4">Account</h4>
                <ul class="space-y-2.5">
                    @auth
                        <li><a href="{{ route('user.dashboard') }}" class="text-sm text-gray-500 hover:text-[#a78bfa] transition-colors">Dashboard</a></li>
                        <li><a href="{{ route('user.profile.edit') }}" class="text-sm text-gray-500 hover:text-[#a78bfa] transition-colors">Profile</a></li>
                    @else
                        <li><a href="{{ route('user.login') }}" class="text-sm text-gray-500 hover:text-[#a78bfa] transition-colors">Log in</a></li>
                        <li><a href="{{ route('user.register') }}" class="text-sm text-gray-500 hover:text-[#a78bfa] transition-colors">Sign up free</a></li>
                    @endauth
                </ul>
            </div>
        </div>
        <div class="border-t border-white/5 pt-8 flex flex-col sm:flex-row justify-between items-center gap-3">
            <p class="text-sm text-gray-600">&copy; {{ date('Y') }} 1INME. All rights reserved.</p>
            <p class="text-xs text-gray-600">Your link, your page, your audience. All in one.</p>
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
            // Safety net: if any reveal is still hidden after 1.5s (e.g.
            // headless screenshots, very tall viewports), force-show it.
            setTimeout(() => reveals.forEach(el => el.classList.add('visible')), 200);
        } else {
            reveals.forEach(el => el.classList.add('visible'));
        }

        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                const href = this.getAttribute('href');
                if (href === '#' || href.length < 2) return;
                const target = document.querySelector(href);
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    });
</script>
</body>
</html>
