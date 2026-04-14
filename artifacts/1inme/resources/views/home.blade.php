<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>1INME - Everything you are. In one simple link.</title>
    <meta name="description" content="Connect your audience to all of your content with just one link. Bio links, URL shortener, file sharing, QR codes, and more.">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Space Grotesk', 'sans-serif'] },
                }
            }
        }
    </script>
    <style>
        [x-cloak] { display: none !important; }
        .reveal { opacity: 0; transform: translateY(40px); transition: opacity 0.8s cubic-bezier(0.16, 1, 0.3, 1), transform 0.8s cubic-bezier(0.16, 1, 0.3, 1); }
        .reveal.visible { opacity: 1; transform: translateY(0); }
        .reveal-delay-1 { transition-delay: 0.15s; }
        .reveal-delay-2 { transition-delay: 0.3s; }
        .reveal-delay-3 { transition-delay: 0.45s; }
        .reveal-delay-4 { transition-delay: 0.6s; }
        .hero-float { animation: heroFloat 5s ease-in-out infinite; }
        @keyframes heroFloat { 0%,100% { transform: translateY(0) rotate(-2deg); } 50% { transform: translateY(-16px) rotate(2deg); } }
        .hero-float-2 { animation: heroFloat2 6s ease-in-out infinite; }
        @keyframes heroFloat2 { 0%,100% { transform: translateY(0) rotate(1deg); } 50% { transform: translateY(-12px) rotate(-1deg); } }
        .blob-spin { animation: blobSpin 25s linear infinite; }
        @keyframes blobSpin { 0% { transform: rotate(0deg) scale(1); } 50% { transform: rotate(180deg) scale(1.15); } 100% { transform: rotate(360deg) scale(1); } }
        .marquee { animation: marquee 30s linear infinite; }
        @keyframes marquee { 0% { transform: translateX(0); } 100% { transform: translateX(-50%); } }
        .card-hover { transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
        .card-hover:hover { transform: translateY(-8px) scale(1.02); }
        .bounce-subtle { animation: bounceSub 2s ease-in-out infinite; }
        @keyframes bounceSub { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }
    </style>
</head>
<body class="font-sans overflow-x-hidden">

    <nav class="fixed top-0 left-0 right-0 z-50 bg-[#1e2330]/90 backdrop-blur-xl border-b border-white/5" x-data="{ mobileOpen: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <a href="{{ route('home') }}" class="text-2xl font-bold tracking-tight">
                    <span class="text-white">1IN</span><span class="text-[#a855f7]">ME</span>
                </a>

                <div class="hidden md:flex items-center gap-8">
                    <a href="#use-cases" class="text-sm text-gray-300 hover:text-[#a855f7] transition-colors">Use Cases</a>
                    <a href="#features" class="text-sm text-gray-300 hover:text-[#a855f7] transition-colors">Features</a>
                    <a href="#how-it-works" class="text-sm text-gray-300 hover:text-[#a855f7] transition-colors">How It Works</a>
                    <a href="#pricing" class="text-sm text-gray-300 hover:text-[#a855f7] transition-colors">Pricing</a>
                </div>

                <div class="hidden md:flex items-center gap-3">
                    @auth
                        <a href="{{ route('user.dashboard') }}" class="px-6 py-2.5 bg-[#7c3aed] text-white rounded-full text-sm font-bold hover:bg-[#6d28d9] transition-all">Dashboard</a>
                    @else
                        <a href="{{ route('user.login') }}" class="px-4 py-2 text-sm font-medium text-gray-300 hover:text-white transition-colors">Log in</a>
                        <a href="{{ route('user.register') }}" class="px-6 py-2.5 bg-[#7c3aed] text-white rounded-full text-sm font-bold hover:bg-[#6d28d9] transition-all">Sign up free</a>
                    @endauth
                </div>

                <button @click="mobileOpen = !mobileOpen" class="md:hidden p-2 text-gray-300">
                    <svg x-show="!mobileOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    <svg x-show="mobileOpen" x-cloak class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div x-show="mobileOpen" x-cloak x-transition class="md:hidden pb-4 border-t border-white/10 mt-2 pt-4 space-y-2">
                <a href="#use-cases" @click="mobileOpen = false" class="block px-4 py-2 text-sm text-gray-300 hover:text-[#a855f7] rounded-lg">Use Cases</a>
                <a href="#features" @click="mobileOpen = false" class="block px-4 py-2 text-sm text-gray-300 hover:text-[#a855f7] rounded-lg">Features</a>
                <a href="#how-it-works" @click="mobileOpen = false" class="block px-4 py-2 text-sm text-gray-300 hover:text-[#a855f7] rounded-lg">How It Works</a>
                <a href="#pricing" @click="mobileOpen = false" class="block px-4 py-2 text-sm text-gray-300 hover:text-[#a855f7] rounded-lg">Pricing</a>
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

    <section class="relative min-h-screen bg-[#1e2330] pt-28 pb-20 lg:pt-36 lg:pb-28 overflow-hidden">
        <div class="absolute top-[-20%] left-[-10%] w-[600px] h-[600px] bg-[#7c3aed] rounded-full mix-blend-screen filter blur-[120px] opacity-30 blob-spin"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[500px] h-[500px] bg-[#06b6d4] rounded-full mix-blend-screen filter blur-[100px] opacity-25 blob-spin" style="animation-delay: -12s;"></div>
        <div class="absolute top-[30%] right-[20%] w-[400px] h-[400px] bg-[#f43f5e] rounded-full mix-blend-screen filter blur-[100px] opacity-20 blob-spin" style="animation-delay: -8s;"></div>
        <div class="absolute bottom-[20%] left-[15%] w-[300px] h-[300px] bg-[#7c3aed] rounded-full mix-blend-screen filter blur-[80px] opacity-15 blob-spin" style="animation-delay: -18s;"></div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">
                <div class="text-center lg:text-left">
                    <div class="reveal inline-flex items-center gap-2 px-4 py-1.5 bg-white/10 border border-white/10 rounded-full text-[#a855f7] text-sm font-medium mb-6 backdrop-blur-sm">
                        <span class="relative flex h-2 w-2"><span class="absolute inline-flex h-full w-full rounded-full bg-[#7c3aed] opacity-75 animate-ping"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-[#7c3aed]"></span></span>
                        The link management platform
                    </div>

                    <h1 class="reveal reveal-delay-1 text-5xl sm:text-6xl lg:text-7xl font-bold leading-[1.05] tracking-tight text-white mb-6">
                        Everything<br>you are. In<br>
                        <span class="text-[#a855f7]">one simple</span><br>
                        <span class="text-[#a855f7]">link.</span>
                    </h1>

                    <p class="reveal reveal-delay-2 text-lg sm:text-xl text-gray-400 max-w-lg mx-auto lg:mx-0 mb-8 leading-relaxed">
                        Build entire websites from a single link. Text, images, videos, audio, files, embeds, multi-column layouts — the design possibilities are unlimited.
                    </p>

                    <div class="reveal reveal-delay-3 flex flex-col sm:flex-row gap-3 justify-center lg:justify-start">
                        <a href="{{ route('user.register') }}" class="px-8 py-4 bg-[#7c3aed] text-white rounded-full text-base font-bold hover:bg-[#6d28d9] transition-all hover:shadow-xl hover:shadow-[#7c3aed]/20 hover:-translate-y-0.5">
                            Get started for free
                        </a>
                        <a href="#use-cases" class="px-8 py-4 bg-white/10 text-white rounded-full text-base font-semibold border border-white/10 hover:bg-white/15 hover:border-white/20 transition-all backdrop-blur-sm">
                            See how it works
                        </a>
                    </div>

                    <div class="reveal reveal-delay-4 flex items-center gap-6 mt-8 justify-center lg:justify-start text-sm text-gray-500">
                        <span class="flex items-center gap-1.5"><i class="fas fa-check text-[#a855f7]"></i> Free forever plan</span>
                        <span class="flex items-center gap-1.5"><i class="fas fa-check text-[#a855f7]"></i> No credit card</span>
                    </div>
                </div>

                <div class="reveal reveal-delay-3 relative flex justify-center lg:justify-end">
                    <div class="relative w-[310px] sm:w-[360px]">
                        <div class="hero-float">
                            <div class="bg-gradient-to-br from-[#a855f7] via-[#06b6d4] to-[#7c3aed] rounded-[2rem] p-[3px] shadow-2xl shadow-[#7c3aed]/30">
                                <div class="bg-[#1e2330] rounded-[1.85rem] p-4 space-y-2.5">
                                    <div class="flex items-center gap-3 mb-1">
                                        <div class="w-12 h-12 rounded-full bg-gradient-to-br from-[#a855f7] via-[#06b6d4] to-[#7c3aed] p-[2px] flex-shrink-0">
                                            <div class="w-full h-full rounded-full bg-[#1e2330] flex items-center justify-center">
                                                <span class="text-[#a855f7] text-sm font-bold">JD</span>
                                            </div>
                                        </div>
                                        <div>
                                            <h3 class="text-sm font-bold text-white leading-tight">Jane Doe Studio</h3>
                                            <p class="text-[10px] text-gray-500">Creator & Designer</p>
                                        </div>
                                    </div>

                                    <div class="bg-white/5 rounded-xl p-3 border border-white/5">
                                        <p class="text-[11px] text-gray-300 leading-relaxed">Welcome to my creative space! Explore my latest work, watch tutorials, and grab exclusive downloads.</p>
                                    </div>

                                    <div class="grid grid-cols-2 gap-2">
                                        <div class="bg-gradient-to-br from-[#7c3aed]/30 to-[#7c3aed]/10 rounded-xl p-2.5 border border-[#7c3aed]/20">
                                            <div class="w-full aspect-video bg-[#7c3aed]/20 rounded-lg flex items-center justify-center mb-1.5">
                                                <div class="w-7 h-7 rounded-full bg-[#7c3aed] flex items-center justify-center"><i class="fas fa-play text-white text-[8px] ml-0.5"></i></div>
                                            </div>
                                            <div class="text-[9px] text-gray-400 font-medium">LATEST VIDEO</div>
                                            <div class="text-[10px] text-white font-bold truncate">Design Tips 2026</div>
                                        </div>
                                        <div class="bg-gradient-to-br from-[#06b6d4]/30 to-[#06b6d4]/10 rounded-xl p-2.5 border border-[#06b6d4]/20">
                                            <div class="w-full aspect-video bg-[#06b6d4]/20 rounded-lg flex items-center justify-center mb-1.5 overflow-hidden">
                                                <div class="grid grid-cols-2 gap-0.5 w-full h-full p-1">
                                                    <div class="bg-[#06b6d4]/30 rounded"></div>
                                                    <div class="bg-[#7c3aed]/30 rounded"></div>
                                                    <div class="bg-[#f43f5e]/30 rounded"></div>
                                                    <div class="bg-[#7c3aed]/30 rounded"></div>
                                                </div>
                                            </div>
                                            <div class="text-[9px] text-gray-400 font-medium">GALLERY</div>
                                            <div class="text-[10px] text-white font-bold truncate">Portfolio Work</div>
                                        </div>
                                    </div>

                                    <div class="bg-white/5 rounded-xl p-2.5 border border-white/5 flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-[#f43f5e]/20 flex items-center justify-center flex-shrink-0"><i class="fas fa-headphones text-[#f43f5e] text-xs"></i></div>
                                        <div class="flex-1 min-w-0">
                                            <div class="text-[9px] text-gray-400 font-medium">NOW PLAYING</div>
                                            <div class="text-[10px] text-white font-bold truncate">Creative Flow Podcast</div>
                                        </div>
                                        <div class="flex items-end gap-[2px] h-4">
                                            <div class="w-[3px] bg-[#7c3aed] rounded-full animate-pulse" style="height: 40%"></div>
                                            <div class="w-[3px] bg-[#7c3aed] rounded-full animate-pulse" style="height: 80%; animation-delay: 0.15s"></div>
                                            <div class="w-[3px] bg-[#7c3aed] rounded-full animate-pulse" style="height: 55%; animation-delay: 0.3s"></div>
                                            <div class="w-[3px] bg-[#7c3aed] rounded-full animate-pulse" style="height: 90%; animation-delay: 0.1s"></div>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-2">
                                        <a class="block py-2.5 px-3 bg-[#7c3aed] text-white rounded-xl text-[11px] font-bold text-center">
                                            <i class="fas fa-download mr-1"></i>Free Templates
                                        </a>
                                        <a class="block py-2.5 px-3 bg-[#e11d48] text-white rounded-xl text-[11px] font-bold text-center">
                                            <i class="fas fa-store mr-1"></i>Shop Merch
                                        </a>
                                    </div>

                                    <div class="bg-white/5 rounded-xl p-2.5 border border-white/5">
                                        <div class="flex items-center gap-2 mb-1.5">
                                            <i class="fas fa-code text-[#06b6d4] text-[10px]"></i>
                                            <div class="text-[9px] text-gray-400 font-medium">EMBEDDED WIDGET</div>
                                        </div>
                                        <div class="w-full h-8 bg-gradient-to-r from-[#06b6d4]/15 via-[#7c3aed]/15 to-[#a855f7]/15 rounded-lg flex items-center justify-center">
                                            <span class="text-[9px] text-gray-500">Spotify / YouTube / Map embed</span>
                                        </div>
                                    </div>

                                    <div class="flex justify-center gap-2.5 pt-0.5">
                                        <span class="w-7 h-7 rounded-full bg-white/10 flex items-center justify-center text-gray-400 text-[10px]"><i class="fab fa-twitter"></i></span>
                                        <span class="w-7 h-7 rounded-full bg-white/10 flex items-center justify-center text-gray-400 text-[10px]"><i class="fab fa-tiktok"></i></span>
                                        <span class="w-7 h-7 rounded-full bg-white/10 flex items-center justify-center text-gray-400 text-[10px]"><i class="fab fa-youtube"></i></span>
                                        <span class="w-7 h-7 rounded-full bg-white/10 flex items-center justify-center text-gray-400 text-[10px]"><i class="fab fa-linkedin"></i></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="hero-float-2 absolute -top-3 -right-6 bg-[#7c3aed] rounded-2xl shadow-xl shadow-[#7c3aed]/20 p-2.5 flex items-center gap-2">
                            <div class="w-8 h-8 bg-white/15 rounded-lg flex items-center justify-center"><i class="fas fa-columns text-white text-xs"></i></div>
                            <div>
                                <div class="text-[9px] text-white/60 font-medium">Layout</div>
                                <div class="text-[11px] font-bold text-white">Multi-Column</div>
                            </div>
                        </div>

                        <div class="hero-float absolute -bottom-4 -left-6 bg-[#7c3aed] rounded-2xl shadow-xl shadow-[#7c3aed]/30 p-2.5 flex items-center gap-2">
                            <div class="w-8 h-8 bg-white/15 rounded-lg flex items-center justify-center"><i class="fas fa-puzzle-piece text-white text-xs"></i></div>
                            <div>
                                <div class="text-[9px] text-white/60 font-medium">Blocks</div>
                                <div class="text-[11px] font-bold text-white">Unlimited</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="bg-[#7c3aed] py-4 overflow-hidden">
        <div class="flex whitespace-nowrap marquee">
            @for($i = 0; $i < 2; $i++)
            <span class="inline-flex items-center gap-8 mx-4 text-white">
                <span class="text-sm font-bold uppercase tracking-wider flex items-center gap-2"><i class="fas fa-columns"></i> Multi-Column Layouts</span>
                <span class="text-xl opacity-30">&bull;</span>
                <span class="text-sm font-bold uppercase tracking-wider flex items-center gap-2"><i class="fas fa-video"></i> Videos</span>
                <span class="text-xl opacity-30">&bull;</span>
                <span class="text-sm font-bold uppercase tracking-wider flex items-center gap-2"><i class="fas fa-headphones"></i> Audio</span>
                <span class="text-xl opacity-30">&bull;</span>
                <span class="text-sm font-bold uppercase tracking-wider flex items-center gap-2"><i class="fas fa-image"></i> Images</span>
                <span class="text-xl opacity-30">&bull;</span>
                <span class="text-sm font-bold uppercase tracking-wider flex items-center gap-2"><i class="fas fa-code"></i> Embeds</span>
                <span class="text-xl opacity-30">&bull;</span>
                <span class="text-sm font-bold uppercase tracking-wider flex items-center gap-2"><i class="fas fa-file-alt"></i> Files</span>
                <span class="text-xl opacity-30">&bull;</span>
                <span class="text-sm font-bold uppercase tracking-wider flex items-center gap-2"><i class="fas fa-id-card"></i> Bio Links</span>
                <span class="text-xl opacity-30">&bull;</span>
                <span class="text-sm font-bold uppercase tracking-wider flex items-center gap-2"><i class="fas fa-link"></i> URL Shortener</span>
                <span class="text-xl opacity-30">&bull;</span>
                <span class="text-sm font-bold uppercase tracking-wider flex items-center gap-2"><i class="fas fa-qrcode"></i> QR Codes</span>
                <span class="text-xl opacity-30">&bull;</span>
                <span class="text-sm font-bold uppercase tracking-wider flex items-center gap-2"><i class="fas fa-chart-bar"></i> Analytics</span>
                <span class="text-xl opacity-30">&bull;</span>
            </span>
            @endfor
        </div>
    </div>

    <section id="use-cases" class="py-24 lg:py-32 bg-[#e8d5f5]" x-data="{ active: 0, auto: true }" x-init="setInterval(() => { if(auto) active = (active + 1) % 6 }, 4000)">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="reveal text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight text-[#1e2330] mb-4">
                    Built for <span class="text-[#7c3aed]">every</span> business
                </h2>
                <p class="reveal reveal-delay-1 text-lg text-white/60 max-w-2xl mx-auto">
                    Whether you're a creator, entrepreneur, or enterprise — 1INME adapts to your needs.
                </p>
            </div>

            <div class="reveal reveal-delay-2 flex flex-wrap justify-center gap-2 mb-14">
                @php
                $categories = [
                    ['icon' => 'fa-palette', 'label' => 'Creators', 'bg' => '#7c3aed', 'text' => '#fff'],
                    ['icon' => 'fa-store', 'label' => 'Small Business', 'bg' => '#059669', 'text' => '#fff'],
                    ['icon' => 'fa-briefcase', 'label' => 'Freelancers', 'bg' => '#2563eb', 'text' => '#fff'],
                    ['icon' => 'fa-calendar-check', 'label' => 'Events', 'bg' => '#ea580c', 'text' => '#fff'],
                    ['icon' => 'fa-shopping-bag', 'label' => 'E-Commerce', 'bg' => '#e11d48', 'text' => '#fff'],
                    ['icon' => 'fa-graduation-cap', 'label' => 'Nonprofits', 'bg' => '#0891b2', 'text' => '#fff'],
                ];
                @endphp
                @foreach($categories as $i => $cat)
                <button @click="active = {{ $i }}; auto = false"
                    :class="active === {{ $i }} ? 'text-white shadow-lg scale-105' : 'bg-white text-[#1e2330] hover:bg-white/80 border border-[#1e2330]/10'"
                    :style="active === {{ $i }} ? 'background-color: {{ $cat['bg'] }}' : ''"
                    class="px-5 py-2.5 rounded-full text-sm font-bold transition-all duration-300 flex items-center gap-2">
                    <i class="fas {{ $cat['icon'] }}"></i>
                    {{ $cat['label'] }}
                </button>
                @endforeach
            </div>

            <div class="relative min-h-[420px]">
                @php
                $catData = [
                    ['badge' => 'CREATORS & INFLUENCERS', 'badgeBg' => '#7c3aed', 'title' => 'A whole website in one link', 'desc' => 'Go way beyond simple links. Add text blocks, images, video players, audio, file downloads, embeds, and arrange everything in multi-column layouts. The design possibilities are truly unlimited.', 'checks' => ['Text, images, video, audio & file blocks', 'Multi-column responsive layouts', 'Embed Spotify, YouTube, Maps & more', 'Unlimited design customization'], 'checkColor' => '#7c3aed', 'mockupBg' => 'from-[#7c3aed] to-[#a855f7]', 'mockupIcon' => 'fa-camera', 'mockupName' => '@CreatorStudio', 'mockupSub' => 'Content Creator', 'richMockup' => true, 'links' => []],
                    ['badge' => 'SMALL BUSINESSES', 'badgeBg' => '#059669', 'title' => 'Grow offline & online', 'desc' => 'Print QR codes on menus, packaging, and signage. Share product catalogs as files. Create branded short URLs that build trust.', 'checks' => ['QR codes for menus & products', 'File sharing for catalogs', 'Branded short URLs', 'Location-based link routing'], 'checkColor' => '#059669', 'mockupBg' => 'from-[#059669] to-[#10b981]', 'mockupIcon' => 'fa-coffee', 'mockupName' => 'Cafe Bloom', 'mockupSub' => 'Local Coffee Shop', 'links' => [['bg' => '#1e2330', 'text' => 'white', 'icon' => 'fas fa-utensils', 'label' => 'View Our Menu'], ['bg' => '#059669', 'text' => 'white', 'icon' => 'fas fa-file-pdf', 'label' => 'Download Catalog'], ['bg' => '#7c3aed', 'text' => 'white', 'icon' => 'fas fa-star', 'label' => 'Leave a Review']]],
                    ['badge' => 'FREELANCERS & AGENCIES', 'badgeBg' => '#2563eb', 'title' => 'Professional identity, simplified', 'desc' => 'Share your portfolio, generate VCF contact cards for networking, and create branded link pages that impress.', 'checks' => ['VCF digital business cards', 'Portfolio & case study links', 'Branded bio pages for clients', 'Password-protected links'], 'checkColor' => '#2563eb', 'mockupBg' => 'from-[#2563eb] to-[#3b82f6]', 'mockupIcon' => 'fa-pen-nib', 'mockupName' => 'Alex Rivera', 'mockupSub' => 'UX Designer', 'links' => [['bg' => '#1e2330', 'text' => 'white', 'icon' => 'fas fa-briefcase', 'label' => 'View Portfolio'], ['bg' => '#2563eb', 'text' => 'white', 'icon' => 'fas fa-address-card', 'label' => 'Save Contact'], ['bg' => '#7c3aed', 'text' => 'white', 'icon' => 'fab fa-linkedin', 'label' => 'LinkedIn']]],
                    ['badge' => 'EVENT ORGANIZERS', 'badgeBg' => '#ea580c', 'title' => 'Events made effortless', 'desc' => 'Generate ICS calendar invites, create QR codes for check-in, and share event details through a single link.', 'checks' => ['ICS calendar file generation', 'QR codes for event check-in', 'Ticket & RSVP short links', 'Expiring links for time-sensitive content'], 'checkColor' => '#ea580c', 'mockupBg' => 'from-[#ea580c] to-[#f97316]', 'mockupIcon' => 'fa-music', 'mockupName' => 'SoundWave Fest', 'mockupSub' => 'Music Festival 2026', 'links' => [['bg' => '#1e2330', 'text' => 'white', 'icon' => 'fas fa-ticket-alt', 'label' => 'Get Tickets'], ['bg' => '#ea580c', 'text' => 'white', 'icon' => 'fas fa-calendar-plus', 'label' => 'Add to Calendar'], ['bg' => '#7c3aed', 'text' => 'white', 'icon' => 'fas fa-map-marker-alt', 'label' => 'Venue & Directions']]],
                    ['badge' => 'E-COMMERCE & RETAIL', 'badgeBg' => '#e11d48', 'title' => 'Drive sales from everywhere', 'desc' => 'Shorten product links for social ads, embed tracking pixels for retargeting, and measure every click.', 'checks' => ['Product link shortening', 'Facebook & Google tracking pixels', 'UTM parameter builder', 'Conversion tracking & analytics'], 'checkColor' => '#e11d48', 'mockupBg' => 'from-[#e11d48] to-[#f43f5e]', 'mockupIcon' => 'fa-gem', 'mockupName' => 'Luxe Boutique', 'mockupSub' => 'Fashion & Accessories', 'links' => [['bg' => '#1e2330', 'text' => 'white', 'icon' => 'fas fa-fire', 'label' => 'New Arrivals'], ['bg' => '#e11d48', 'text' => 'white', 'icon' => 'fas fa-tag', 'label' => 'Sale - 40% Off'], ['bg' => '#7c3aed', 'text' => 'white', 'icon' => 'fas fa-gift', 'label' => 'Gift Cards']]],
                    ['badge' => 'NONPROFITS & EDUCATION', 'badgeBg' => '#0891b2', 'title' => 'Share knowledge, inspire action', 'desc' => 'Distribute learning materials, create donation links, and organize resources in a single page.', 'checks' => ['File sharing for resources', 'Donation & fundraising links', 'Bio page for program info', 'QR codes for classrooms'], 'checkColor' => '#0891b2', 'mockupBg' => 'from-[#0891b2] to-[#06b6d4]', 'mockupIcon' => 'fa-heart', 'mockupName' => 'GreenFuture Org', 'mockupSub' => 'Environmental Nonprofit', 'links' => [['bg' => '#1e2330', 'text' => 'white', 'icon' => 'fas fa-hand-holding-heart', 'label' => 'Donate Now'], ['bg' => '#0891b2', 'text' => 'white', 'icon' => 'fas fa-file-download', 'label' => 'Annual Report'], ['bg' => '#7c3aed', 'text' => 'white', 'icon' => 'fas fa-users', 'label' => 'Volunteer Sign Up']]],
                ];
                @endphp

                @foreach($catData as $i => $cat)
                <div x-show="active === {{ $i }}" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-y-6" x-transition:enter-end="opacity-100 translate-y-0" @if($i > 0) x-cloak @endif class="grid lg:grid-cols-2 gap-12 items-center">
                    <div>
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold mb-4 text-white" style="background-color: {{ $cat['badgeBg'] }}">{{ $cat['badge'] }}</div>
                        <h3 class="text-3xl sm:text-4xl font-bold text-[#1e2330] mb-4">{{ $cat['title'] }}</h3>
                        <p class="text-white/60 mb-6 leading-relaxed text-lg">{{ $cat['desc'] }}</p>
                        <ul class="space-y-3">
                            @foreach($cat['checks'] as $check)
                            <li class="flex items-center gap-3 text-[#1e2330]">
                                <span class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0" style="background-color: {{ $cat['checkColor'] }}20"><i class="fas fa-check text-xs" style="color: {{ $cat['checkColor'] }}"></i></span>
                                <span class="text-sm font-medium">{{ $check }}</span>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="flex justify-center">
                        @if(!empty($cat['richMockup']))
                        <div class="w-72 sm:w-80 bg-gradient-to-br {{ $cat['mockupBg'] }} rounded-[2rem] p-[3px] shadow-2xl">
                            <div class="bg-[#1e2330] rounded-[1.85rem] p-4 space-y-2">
                                <div class="flex items-center gap-2.5 mb-1">
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br {{ $cat['mockupBg'] }} flex items-center justify-center text-white text-sm"><i class="fas {{ $cat['mockupIcon'] }}"></i></div>
                                    <div><div class="font-bold text-xs text-white">{{ $cat['mockupName'] }}</div><div class="text-[10px] text-gray-500">{{ $cat['mockupSub'] }}</div></div>
                                </div>
                                <div class="bg-white/5 rounded-lg p-2 border border-white/5">
                                    <p class="text-[10px] text-gray-300 leading-relaxed">Hey! Welcome to my creative hub. Check out my latest projects, listen to my podcast, and grab free resources below.</p>
                                </div>
                                <div class="grid grid-cols-2 gap-1.5">
                                    <div class="bg-[#7c3aed]/20 rounded-lg p-2 border border-[#7c3aed]/15">
                                        <div class="w-full aspect-video bg-[#7c3aed]/25 rounded flex items-center justify-center mb-1"><div class="w-5 h-5 rounded-full bg-[#7c3aed] flex items-center justify-center"><i class="fas fa-play text-white text-[6px] ml-px"></i></div></div>
                                        <div class="text-[8px] text-gray-400">VIDEO</div>
                                        <div class="text-[9px] text-white font-bold truncate">Studio Tour</div>
                                    </div>
                                    <div class="bg-[#06b6d4]/20 rounded-lg p-2 border border-[#06b6d4]/15">
                                        <div class="w-full aspect-video bg-[#06b6d4]/25 rounded overflow-hidden mb-1"><div class="grid grid-cols-2 gap-px w-full h-full p-0.5"><div class="bg-[#06b6d4]/30 rounded-sm"></div><div class="bg-[#7c3aed]/30 rounded-sm"></div><div class="bg-[#f43f5e]/30 rounded-sm"></div><div class="bg-[#7c3aed]/30 rounded-sm"></div></div></div>
                                        <div class="text-[8px] text-gray-400">GALLERY</div>
                                        <div class="text-[9px] text-white font-bold truncate">My Work</div>
                                    </div>
                                </div>
                                <div class="bg-white/5 rounded-lg p-2 border border-white/5 flex items-center gap-2">
                                    <div class="w-6 h-6 rounded bg-[#f43f5e]/20 flex items-center justify-center flex-shrink-0"><i class="fas fa-headphones text-[#f43f5e] text-[8px]"></i></div>
                                    <div class="flex-1 min-w-0"><div class="text-[8px] text-gray-400">AUDIO</div><div class="text-[9px] text-white font-bold truncate">Creative Flow Ep. 42</div></div>
                                    <div class="flex items-end gap-[1.5px] h-3"><div class="w-[2px] bg-[#7c3aed] rounded-full animate-pulse" style="height:35%"></div><div class="w-[2px] bg-[#7c3aed] rounded-full animate-pulse" style="height:75%;animation-delay:.15s"></div><div class="w-[2px] bg-[#7c3aed] rounded-full animate-pulse" style="height:50%;animation-delay:.3s"></div><div class="w-[2px] bg-[#7c3aed] rounded-full animate-pulse" style="height:85%;animation-delay:.1s"></div></div>
                                </div>
                                <div class="grid grid-cols-2 gap-1.5">
                                    <div class="py-2 rounded-lg text-[10px] font-bold text-center bg-[#7c3aed] text-white"><i class="fas fa-download mr-1"></i>Free Kit</div>
                                    <div class="py-2 rounded-lg text-[10px] font-bold text-center bg-[#e11d48] text-white"><i class="fas fa-store mr-1"></i>Shop</div>
                                </div>
                                <div class="bg-white/5 rounded-lg p-1.5 border border-white/5">
                                    <div class="flex items-center gap-1.5 mb-1"><i class="fas fa-code text-[#06b6d4] text-[8px]"></i><div class="text-[8px] text-gray-400">EMBED</div></div>
                                    <div class="w-full h-6 bg-gradient-to-r from-[#06b6d4]/10 via-[#7c3aed]/10 to-[#a855f7]/10 rounded flex items-center justify-center"><span class="text-[7px] text-gray-500">Spotify / YouTube embed</span></div>
                                </div>
                            </div>
                        </div>
                        @else
                        <div class="w-64 sm:w-72 bg-gradient-to-br {{ $cat['mockupBg'] }} rounded-[2rem] p-[3px] shadow-2xl">
                            <div class="bg-[#1e2330] rounded-[1.85rem] p-5 space-y-3">
                                <div class="flex flex-col items-center">
                                    <div class="w-14 h-14 rounded-full bg-gradient-to-br {{ $cat['mockupBg'] }} flex items-center justify-center text-white text-lg font-bold mb-2"><i class="fas {{ $cat['mockupIcon'] }}"></i></div>
                                    <div class="font-bold text-sm text-white">{{ $cat['mockupName'] }}</div>
                                    <div class="text-xs text-gray-500">{{ $cat['mockupSub'] }}</div>
                                </div>
                                @foreach($cat['links'] as $link)
                                <div class="py-2.5 rounded-xl text-xs font-bold text-center" style="background-color: {{ $link['bg'] }}; color: {{ $link['text'] }}"><i class="{{ $link['icon'] }} mr-1"></i>{{ $link['label'] }}</div>
                                @endforeach
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>

            <div class="flex justify-center gap-2 mt-12">
                @for($i = 0; $i < 6; $i++)
                <button @click="active = {{ $i }}; auto = false" :class="active === {{ $i }} ? 'w-10' : 'w-2.5'" class="h-2.5 rounded-full transition-all duration-500" :style="active === {{ $i }} ? 'background-color: #7c3aed' : 'background-color: #1e233040'"></button>
                @endfor
            </div>
        </div>
    </section>

    <section class="py-24 lg:py-32 bg-[#e11d48] relative overflow-hidden">
        <div class="absolute top-0 left-0 w-full h-full opacity-10">
            <div class="absolute top-10 left-10 w-40 h-40 border-4 border-white rounded-full"></div>
            <div class="absolute bottom-10 right-20 w-60 h-60 border-4 border-white rounded-full"></div>
            <div class="absolute top-1/2 left-1/3 w-20 h-20 border-4 border-white rounded-full"></div>
        </div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div>
                    <h2 class="reveal text-4xl sm:text-5xl lg:text-6xl font-bold text-white mb-6 leading-tight">
                        Not just links — build entire websites
                    </h2>
                    <p class="reveal reveal-delay-1 text-lg text-white/70 mb-8 leading-relaxed">
                        Add text, images, videos, audio, files, embeds, and more. Arrange blocks in multi-column layouts that adapt to any device. The design is completely in your hands.
                    </p>
                    <a href="{{ route('user.register') }}" class="reveal reveal-delay-2 inline-flex items-center gap-2 px-8 py-4 bg-[#7c3aed] text-white rounded-full text-base font-bold hover:bg-[#6d28d9] transition-all hover:shadow-xl hover:-translate-y-0.5">
                        Get started for free
                    </a>
                </div>
                <div class="reveal reveal-delay-2 flex justify-center">
                    <div class="grid grid-cols-3 gap-3 max-w-md">
                        <div class="card-hover bg-white/15 backdrop-blur-sm rounded-2xl p-4 text-center border border-white/10">
                            <div class="text-2xl mb-1.5"><i class="fas fa-font text-[#a855f7]"></i></div>
                            <div class="text-white font-bold text-xs">Text</div>
                        </div>
                        <div class="card-hover bg-white/15 backdrop-blur-sm rounded-2xl p-4 text-center border border-white/10 mt-4">
                            <div class="text-2xl mb-1.5"><i class="fas fa-image text-[#06b6d4]"></i></div>
                            <div class="text-white font-bold text-xs">Images</div>
                        </div>
                        <div class="card-hover bg-white/15 backdrop-blur-sm rounded-2xl p-4 text-center border border-white/10">
                            <div class="text-2xl mb-1.5"><i class="fas fa-video text-[#7c3aed]"></i></div>
                            <div class="text-white font-bold text-xs">Videos</div>
                        </div>
                        <div class="card-hover bg-white/15 backdrop-blur-sm rounded-2xl p-4 text-center border border-white/10 mt-4">
                            <div class="text-2xl mb-1.5"><i class="fas fa-headphones text-[#f43f5e]"></i></div>
                            <div class="text-white font-bold text-xs">Audio</div>
                        </div>
                        <div class="card-hover bg-white/15 backdrop-blur-sm rounded-2xl p-4 text-center border border-white/10">
                            <div class="text-2xl mb-1.5"><i class="fas fa-file-alt text-[#ea580c]"></i></div>
                            <div class="text-white font-bold text-xs">Files</div>
                        </div>
                        <div class="card-hover bg-white/15 backdrop-blur-sm rounded-2xl p-4 text-center border border-white/10 mt-4">
                            <div class="text-2xl mb-1.5"><i class="fas fa-code text-[#a855f7]"></i></div>
                            <div class="text-white font-bold text-xs">Embeds</div>
                        </div>
                        <div class="card-hover bg-white/15 backdrop-blur-sm rounded-2xl p-4 text-center border border-white/10 col-span-3">
                            <div class="text-2xl mb-1.5"><i class="fas fa-columns text-[#a855f7]"></i></div>
                            <div class="text-white font-bold text-xs">Multi-Column Responsive Layouts</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="features" class="py-24 lg:py-32 bg-[#06b6d4]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div class="reveal flex justify-center order-2 lg:order-1">
                    <div class="w-72 sm:w-80">
                        <div class="bg-white/15 backdrop-blur-sm rounded-3xl p-6 border border-white/10">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="w-10 h-10 rounded-full bg-[#7c3aed] flex items-center justify-center"><i class="fas fa-qrcode text-white"></i></div>
                                <div>
                                    <div class="text-white font-bold text-sm">QR Code Generator</div>
                                    <div class="text-white/50 text-xs">PNG & SVG export</div>
                                </div>
                            </div>
                            <div class="bg-white rounded-2xl p-6 flex items-center justify-center mb-4">
                                <div class="w-36 h-36 bg-[#1e2330] rounded-xl flex items-center justify-center">
                                    <i class="fas fa-qrcode text-6xl text-[#a855f7]"></i>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <div class="flex-1 py-2 bg-[#7c3aed] text-white rounded-lg text-xs font-bold text-center">Download PNG</div>
                                <div class="flex-1 py-2 bg-white/15 text-white rounded-lg text-xs font-bold text-center border border-white/10">Download SVG</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="order-1 lg:order-2">
                    <h2 class="reveal text-4xl sm:text-5xl lg:text-6xl font-bold text-white mb-6 leading-tight">
                        Share your 1INME anywhere you like!
                    </h2>
                    <p class="reveal reveal-delay-1 text-lg text-white/70 mb-8 leading-relaxed">
                        Add your unique 1INME URL to all the platforms and places you find your audience. Then use your QR code to drive your offline traffic back to your link.
                    </p>
                    <a href="{{ route('user.register') }}" class="reveal reveal-delay-2 inline-flex items-center gap-2 px-8 py-4 bg-[#7c3aed] text-white rounded-full text-base font-bold hover:bg-[#6d28d9] transition-all hover:shadow-xl hover:-translate-y-0.5">
                        Get started for free
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="py-24 lg:py-32 bg-[#7c3aed]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div>
                    <h2 class="reveal text-4xl sm:text-5xl lg:text-6xl font-bold text-white mb-6 leading-tight">
                        Analyze your audience and keep them engaged
                    </h2>
                    <p class="reveal reveal-delay-1 text-lg text-white/70 mb-8 leading-relaxed">
                        Track your engagement over time, monitor clicks and learn what's converting your audience. Make informed updates on the fly to keep them coming back.
                    </p>
                    <a href="{{ route('user.register') }}" class="reveal reveal-delay-2 inline-flex items-center gap-2 px-8 py-4 bg-[#1e2330] text-[#a855f7] rounded-full text-base font-bold hover:bg-[#2a3040] transition-all hover:shadow-xl hover:-translate-y-0.5">
                        Get started for free
                    </a>
                </div>
                <div class="reveal reveal-delay-2 flex justify-center">
                    <div class="bg-[#1e2330] rounded-3xl p-6 w-full max-w-sm shadow-2xl shadow-[#1e2330]/20">
                        <div class="flex items-center justify-between mb-6">
                            <div class="text-white font-bold">Analytics</div>
                            <div class="text-xs text-[#a855f7] font-medium bg-[#7c3aed]/10 px-2 py-1 rounded-full">Live</div>
                        </div>
                        <div class="grid grid-cols-2 gap-3 mb-5">
                            <div class="bg-white/5 rounded-xl p-3 border border-white/5">
                                <div class="text-xs text-gray-500 mb-1">Total Clicks</div>
                                <div class="text-xl font-bold text-white">24.8K</div>
                                <div class="text-xs text-green-400 mt-1"><i class="fas fa-arrow-up mr-1"></i>12%</div>
                            </div>
                            <div class="bg-white/5 rounded-xl p-3 border border-white/5">
                                <div class="text-xs text-gray-500 mb-1">Unique Visitors</div>
                                <div class="text-xl font-bold text-white">18.2K</div>
                                <div class="text-xs text-green-400 mt-1"><i class="fas fa-arrow-up mr-1"></i>8%</div>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <div class="flex items-center gap-3">
                                <div class="text-xs text-gray-500 w-16">Portfolio</div>
                                <div class="flex-1 bg-white/5 rounded-full h-3 overflow-hidden"><div class="h-full rounded-full bg-[#7c3aed]" style="width: 78%"></div></div>
                                <div class="text-xs text-white font-medium w-10 text-right">78%</div>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="text-xs text-gray-500 w-16">YouTube</div>
                                <div class="flex-1 bg-white/5 rounded-full h-3 overflow-hidden"><div class="h-full rounded-full bg-[#e11d48]" style="width: 62%"></div></div>
                                <div class="text-xs text-white font-medium w-10 text-right">62%</div>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="text-xs text-gray-500 w-16">Shop</div>
                                <div class="flex-1 bg-white/5 rounded-full h-3 overflow-hidden"><div class="h-full rounded-full bg-[#06b6d4]" style="width: 45%"></div></div>
                                <div class="text-xs text-white font-medium w-10 text-right">45%</div>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="text-xs text-gray-500 w-16">Contact</div>
                                <div class="flex-1 bg-white/5 rounded-full h-3 overflow-hidden"><div class="h-full rounded-full bg-[#7c3aed]" style="width: 31%"></div></div>
                                <div class="text-xs text-white font-medium w-10 text-right">31%</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-20 bg-[#faf5ff] overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-4">
                <h2 class="reveal text-4xl sm:text-5xl font-bold text-[#1e2330]">The only link-in-bio trusted by</h2>
            </div>
            <div class="overflow-hidden py-4">
                <div class="flex whitespace-nowrap marquee" style="animation-duration: 20s;">
                    @for($i = 0; $i < 2; $i++)
                    <span class="inline-flex items-center gap-6 mx-6">
                        <span class="text-4xl sm:text-5xl font-bold text-[#7c3aed]">creators</span>
                        <span class="text-4xl sm:text-5xl font-bold text-[#06b6d4]">businesses</span>
                        <span class="text-4xl sm:text-5xl font-bold text-[#e11d48]">influencers</span>
                        <span class="text-4xl sm:text-5xl font-bold text-[#ea580c]">freelancers</span>
                        <span class="text-4xl sm:text-5xl font-bold text-[#059669]">nonprofits</span>
                        <span class="text-4xl sm:text-5xl font-bold text-[#2563eb]">educators</span>
                    </span>
                    @endfor
                </div>
            </div>
        </div>
    </section>

    <section id="how-it-works" class="py-24 lg:py-32 bg-[#2563eb]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="reveal text-4xl sm:text-5xl lg:text-6xl font-bold text-white mb-4">
                    Up and running in <span class="text-[#a855f7]">3 minutes</span>
                </h2>
                <p class="reveal reveal-delay-1 text-lg text-white/60 max-w-2xl mx-auto">
                    No technical skills needed. Create your first link in seconds.
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-8 lg:gap-12 relative">
                <div class="hidden md:block absolute top-20 left-[20%] right-[20%] h-1 bg-white/10 rounded-full"></div>

                <div class="reveal reveal-delay-1 text-center relative">
                    <div class="w-20 h-20 mx-auto bg-[#7c3aed] rounded-2xl flex items-center justify-center text-white text-3xl font-bold mb-6 shadow-lg shadow-[#7c3aed]/20 relative z-10 rotate-3 hover:rotate-0 transition-transform">1</div>
                    <h3 class="text-xl font-bold text-white mb-3">Sign up free</h3>
                    <p class="text-white/60 text-sm leading-relaxed">Create your account in seconds. No credit card required. Choose from Free, Pro, or Business plans.</p>
                </div>

                <div class="reveal reveal-delay-2 text-center relative">
                    <div class="w-20 h-20 mx-auto bg-[#7c3aed] rounded-2xl flex items-center justify-center text-white text-3xl font-bold mb-6 shadow-lg shadow-[#7c3aed]/30 relative z-10 -rotate-2 hover:rotate-0 transition-transform">2</div>
                    <h3 class="text-xl font-bold text-white mb-3">Create your links</h3>
                    <p class="text-white/60 text-sm leading-relaxed">Build bio pages, shorten URLs, upload files, generate QR codes, or create contact cards — all from one dashboard.</p>
                </div>

                <div class="reveal reveal-delay-3 text-center relative">
                    <div class="w-20 h-20 mx-auto bg-[#e11d48] rounded-2xl flex items-center justify-center text-white text-3xl font-bold mb-6 shadow-lg shadow-[#e11d48]/30 relative z-10 rotate-2 hover:rotate-0 transition-transform">3</div>
                    <h3 class="text-xl font-bold text-white mb-3">Share everywhere</h3>
                    <p class="text-white/60 text-sm leading-relaxed">Add your link to social bios, print QR codes, or embed them anywhere. Track performance with real-time analytics.</p>
                </div>
            </div>

            <div class="reveal reveal-delay-4 text-center mt-14">
                <a href="{{ route('user.register') }}" class="inline-flex items-center gap-2 px-8 py-4 bg-[#7c3aed] text-white rounded-full text-base font-bold hover:bg-[#6d28d9] transition-all hover:shadow-xl hover:-translate-y-0.5">
                    Start building now <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </section>

    <section id="pricing" class="py-24 lg:py-32 bg-[#1e2330]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="reveal text-4xl sm:text-5xl lg:text-6xl font-bold text-white mb-4">
                    Simple, <span class="text-[#a855f7]">transparent</span> pricing
                </h2>
                <p class="reveal reveal-delay-1 text-lg text-gray-400 max-w-2xl mx-auto">
                    Start free, upgrade when you need more. No hidden fees, ever.
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-6 max-w-5xl mx-auto">
                <div class="reveal reveal-delay-1 card-hover bg-white/5 rounded-3xl p-8 border border-white/10 backdrop-blur-sm">
                    <div class="text-sm font-bold text-gray-400 uppercase tracking-wide mb-2">Free</div>
                    <div class="text-5xl font-bold text-white mb-1">$0</div>
                    <div class="text-sm text-gray-500 mb-6">Forever free</div>
                    <ul class="space-y-3 mb-8">
                        <li class="flex items-center gap-2 text-sm text-gray-300"><i class="fas fa-check text-[#a855f7] text-xs"></i>Up to 10 links</li>
                        <li class="flex items-center gap-2 text-sm text-gray-300"><i class="fas fa-check text-[#a855f7] text-xs"></i>Basic analytics</li>
                        <li class="flex items-center gap-2 text-sm text-gray-300"><i class="fas fa-check text-[#a855f7] text-xs"></i>QR code generation</li>
                        <li class="flex items-center gap-2 text-sm text-gray-300"><i class="fas fa-check text-[#a855f7] text-xs"></i>5MB file uploads</li>
                    </ul>
                    <a href="{{ route('user.register') }}" class="block w-full py-3.5 text-center text-white rounded-full text-sm font-bold border-2 border-white/20 hover:border-white/40 hover:bg-white/5 transition-all">Get started</a>
                </div>

                <div class="reveal reveal-delay-2 card-hover bg-gradient-to-br from-[#7c3aed] to-[#a855f7] rounded-3xl p-8 relative overflow-hidden shadow-2xl shadow-[#7c3aed]/30 scale-105">
                    <div class="absolute top-4 right-4 px-3 py-1 bg-[#7c3aed] text-white text-xs font-bold rounded-full">POPULAR</div>
                    <div class="text-sm font-bold text-white/70 uppercase tracking-wide mb-2">Pro</div>
                    <div class="text-5xl font-bold text-white mb-1">$9<span class="text-lg font-medium text-white/50">/mo</span></div>
                    <div class="text-sm text-white/50 mb-6">For creators & pros</div>
                    <ul class="space-y-3 mb-8">
                        <li class="flex items-center gap-2 text-sm text-white"><i class="fas fa-check text-[#a855f7] text-xs"></i>Unlimited links</li>
                        <li class="flex items-center gap-2 text-sm text-white"><i class="fas fa-check text-[#a855f7] text-xs"></i>Advanced analytics</li>
                        <li class="flex items-center gap-2 text-sm text-white"><i class="fas fa-check text-[#a855f7] text-xs"></i>Custom domains</li>
                        <li class="flex items-center gap-2 text-sm text-white"><i class="fas fa-check text-[#a855f7] text-xs"></i>50MB file uploads</li>
                        <li class="flex items-center gap-2 text-sm text-white"><i class="fas fa-check text-[#a855f7] text-xs"></i>Tracking pixels</li>
                    </ul>
                    <a href="{{ route('user.register') }}" class="block w-full py-3.5 text-center bg-[#7c3aed] text-white rounded-full text-sm font-bold hover:bg-[#6d28d9] transition-all">Start free trial</a>
                </div>

                <div class="reveal reveal-delay-3 card-hover bg-white/5 rounded-3xl p-8 border border-white/10 backdrop-blur-sm">
                    <div class="text-sm font-bold text-gray-400 uppercase tracking-wide mb-2">Business</div>
                    <div class="text-5xl font-bold text-white mb-1">$29<span class="text-lg font-medium text-gray-500">/mo</span></div>
                    <div class="text-sm text-gray-500 mb-6">For teams & orgs</div>
                    <ul class="space-y-3 mb-8">
                        <li class="flex items-center gap-2 text-sm text-gray-300"><i class="fas fa-check text-[#a855f7] text-xs"></i>Everything in Pro</li>
                        <li class="flex items-center gap-2 text-sm text-gray-300"><i class="fas fa-check text-[#a855f7] text-xs"></i>Team collaboration</li>
                        <li class="flex items-center gap-2 text-sm text-gray-300"><i class="fas fa-check text-[#a855f7] text-xs"></i>200MB file uploads</li>
                        <li class="flex items-center gap-2 text-sm text-gray-300"><i class="fas fa-check text-[#a855f7] text-xs"></i>API access</li>
                        <li class="flex items-center gap-2 text-sm text-gray-300"><i class="fas fa-check text-[#a855f7] text-xs"></i>Priority support</li>
                    </ul>
                    <a href="{{ route('user.register') }}" class="block w-full py-3.5 text-center text-white rounded-full text-sm font-bold border-2 border-white/20 hover:border-white/40 hover:bg-white/5 transition-all">Get started</a>
                </div>
            </div>
        </div>
    </section>

    <section class="py-24 lg:py-32 bg-[#059669] relative overflow-hidden">
        <div class="absolute top-0 right-0 w-96 h-96 bg-[#7c3aed] rounded-full mix-blend-soft-light filter blur-[100px] opacity-30"></div>
        <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="reveal text-4xl sm:text-5xl lg:text-6xl font-bold text-white mb-6 leading-tight">
                Ready to simplify your online presence?
            </h2>
            <p class="reveal reveal-delay-1 text-lg text-white/70 mb-10 max-w-xl mx-auto">
                Join thousands of creators and businesses who trust 1INME to manage their links.
            </p>
            <a href="{{ route('user.register') }}" class="reveal reveal-delay-2 inline-flex items-center gap-2 px-10 py-5 bg-[#7c3aed] text-white rounded-full text-lg font-bold hover:bg-[#6d28d9] transition-all hover:shadow-xl hover:-translate-y-1">
                Create your free account <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </section>

    <footer class="bg-[#1e2330] text-white pt-16 pb-8 border-t border-white/5">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-4 gap-8 mb-12">
                <div>
                    <a href="{{ route('home') }}" class="text-2xl font-bold tracking-tight">
                        <span class="text-white">1IN</span><span class="text-[#a855f7]">ME</span>
                    </a>
                    <p class="text-sm text-gray-500 mt-3 leading-relaxed">Everything you are. In one simple link. The all-in-one link management platform.</p>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-gray-400 uppercase tracking-wide mb-4">Product</h4>
                    <ul class="space-y-2.5">
                        <li><a href="#features" class="text-sm text-gray-500 hover:text-[#a855f7] transition-colors">Features</a></li>
                        <li><a href="#pricing" class="text-sm text-gray-500 hover:text-[#a855f7] transition-colors">Pricing</a></li>
                        <li><a href="#use-cases" class="text-sm text-gray-500 hover:text-[#a855f7] transition-colors">Use Cases</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-gray-400 uppercase tracking-wide mb-4">Tools</h4>
                    <ul class="space-y-2.5">
                        <li><a href="{{ route('user.register') }}" class="text-sm text-gray-500 hover:text-[#a855f7] transition-colors">URL Shortener</a></li>
                        <li><a href="{{ route('user.register') }}" class="text-sm text-gray-500 hover:text-[#a855f7] transition-colors">Bio Link Builder</a></li>
                        <li><a href="{{ route('user.register') }}" class="text-sm text-gray-500 hover:text-[#a855f7] transition-colors">QR Generator</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-gray-400 uppercase tracking-wide mb-4">Account</h4>
                    <ul class="space-y-2.5">
                        @auth
                            <li><a href="{{ route('user.dashboard') }}" class="text-sm text-gray-500 hover:text-[#a855f7] transition-colors">Dashboard</a></li>
                            <li><a href="{{ route('user.profile.edit') }}" class="text-sm text-gray-500 hover:text-[#a855f7] transition-colors">Profile</a></li>
                        @else
                            <li><a href="{{ route('user.login') }}" class="text-sm text-gray-500 hover:text-[#a855f7] transition-colors">Log in</a></li>
                            <li><a href="{{ route('user.register') }}" class="text-sm text-gray-500 hover:text-[#a855f7] transition-colors">Sign up</a></li>
                        @endauth
                    </ul>
                </div>
            </div>
            <div class="border-t border-white/5 pt-8 text-center">
                <p class="text-sm text-gray-600">&copy; {{ date('Y') }} 1INME. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                    }
                });
            }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });

            document.querySelectorAll('.reveal').forEach(el => observer.observe(el));

            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    const target = document.querySelector(this.getAttribute('href'));
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
