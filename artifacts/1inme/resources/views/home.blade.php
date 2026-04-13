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
                    <span class="text-white">1IN</span><span class="text-[#d2f34c]">ME</span>
                </a>

                <div class="hidden md:flex items-center gap-8">
                    <a href="#use-cases" class="text-sm text-gray-300 hover:text-[#d2f34c] transition-colors">Use Cases</a>
                    <a href="#features" class="text-sm text-gray-300 hover:text-[#d2f34c] transition-colors">Features</a>
                    <a href="#how-it-works" class="text-sm text-gray-300 hover:text-[#d2f34c] transition-colors">How It Works</a>
                    <a href="#pricing" class="text-sm text-gray-300 hover:text-[#d2f34c] transition-colors">Pricing</a>
                </div>

                <div class="hidden md:flex items-center gap-3">
                    @auth
                        <a href="{{ route('user.dashboard') }}" class="px-6 py-2.5 bg-[#d2f34c] text-[#1e2330] rounded-full text-sm font-bold hover:bg-[#e4ff6e] transition-all">Dashboard</a>
                    @else
                        <a href="{{ route('user.login') }}" class="px-4 py-2 text-sm font-medium text-gray-300 hover:text-white transition-colors">Log in</a>
                        <a href="{{ route('user.register') }}" class="px-6 py-2.5 bg-[#d2f34c] text-[#1e2330] rounded-full text-sm font-bold hover:bg-[#e4ff6e] transition-all">Sign up free</a>
                    @endauth
                </div>

                <button @click="mobileOpen = !mobileOpen" class="md:hidden p-2 text-gray-300">
                    <svg x-show="!mobileOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    <svg x-show="mobileOpen" x-cloak class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div x-show="mobileOpen" x-cloak x-transition class="md:hidden pb-4 border-t border-white/10 mt-2 pt-4 space-y-2">
                <a href="#use-cases" @click="mobileOpen = false" class="block px-4 py-2 text-sm text-gray-300 hover:text-[#d2f34c] rounded-lg">Use Cases</a>
                <a href="#features" @click="mobileOpen = false" class="block px-4 py-2 text-sm text-gray-300 hover:text-[#d2f34c] rounded-lg">Features</a>
                <a href="#how-it-works" @click="mobileOpen = false" class="block px-4 py-2 text-sm text-gray-300 hover:text-[#d2f34c] rounded-lg">How It Works</a>
                <a href="#pricing" @click="mobileOpen = false" class="block px-4 py-2 text-sm text-gray-300 hover:text-[#d2f34c] rounded-lg">Pricing</a>
                <div class="pt-2 border-t border-white/10 space-y-2">
                    @auth
                        <a href="{{ route('user.dashboard') }}" class="block px-4 py-2.5 bg-[#d2f34c] text-[#1e2330] rounded-lg text-sm font-bold text-center">Dashboard</a>
                    @else
                        <a href="{{ route('user.login') }}" class="block px-4 py-2 text-sm text-gray-300">Log in</a>
                        <a href="{{ route('user.register') }}" class="block px-4 py-2.5 bg-[#d2f34c] text-[#1e2330] rounded-lg text-sm font-bold text-center">Sign up free</a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <section class="relative min-h-screen bg-[#1e2330] pt-28 pb-20 lg:pt-36 lg:pb-28 overflow-hidden">
        <div class="absolute top-[-20%] left-[-10%] w-[600px] h-[600px] bg-[#7c3aed] rounded-full mix-blend-screen filter blur-[120px] opacity-30 blob-spin"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[500px] h-[500px] bg-[#06b6d4] rounded-full mix-blend-screen filter blur-[100px] opacity-25 blob-spin" style="animation-delay: -12s;"></div>
        <div class="absolute top-[30%] right-[20%] w-[400px] h-[400px] bg-[#f43f5e] rounded-full mix-blend-screen filter blur-[100px] opacity-20 blob-spin" style="animation-delay: -8s;"></div>
        <div class="absolute bottom-[20%] left-[15%] w-[300px] h-[300px] bg-[#d2f34c] rounded-full mix-blend-screen filter blur-[80px] opacity-15 blob-spin" style="animation-delay: -18s;"></div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">
                <div class="text-center lg:text-left">
                    <div class="reveal inline-flex items-center gap-2 px-4 py-1.5 bg-white/10 border border-white/10 rounded-full text-[#d2f34c] text-sm font-medium mb-6 backdrop-blur-sm">
                        <span class="relative flex h-2 w-2"><span class="absolute inline-flex h-full w-full rounded-full bg-[#d2f34c] opacity-75 animate-ping"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-[#d2f34c]"></span></span>
                        The link management platform
                    </div>

                    <h1 class="reveal reveal-delay-1 text-5xl sm:text-6xl lg:text-7xl font-bold leading-[1.05] tracking-tight text-white mb-6">
                        Everything<br>you are. In<br>
                        <span class="text-[#d2f34c]">one simple</span><br>
                        <span class="text-[#d2f34c]">link.</span>
                    </h1>

                    <p class="reveal reveal-delay-2 text-lg sm:text-xl text-gray-400 max-w-lg mx-auto lg:mx-0 mb-8 leading-relaxed">
                        Connect your audience to all of your content with just one link. Bio pages, short URLs, file sharing, QR codes, analytics, and more.
                    </p>

                    <div class="reveal reveal-delay-3 flex flex-col sm:flex-row gap-3 justify-center lg:justify-start">
                        <a href="{{ route('user.register') }}" class="px-8 py-4 bg-[#d2f34c] text-[#1e2330] rounded-full text-base font-bold hover:bg-[#e4ff6e] transition-all hover:shadow-xl hover:shadow-[#d2f34c]/20 hover:-translate-y-0.5">
                            Get started for free
                        </a>
                        <a href="#use-cases" class="px-8 py-4 bg-white/10 text-white rounded-full text-base font-semibold border border-white/10 hover:bg-white/15 hover:border-white/20 transition-all backdrop-blur-sm">
                            See how it works
                        </a>
                    </div>

                    <div class="reveal reveal-delay-4 flex items-center gap-6 mt-8 justify-center lg:justify-start text-sm text-gray-500">
                        <span class="flex items-center gap-1.5"><i class="fas fa-check text-[#d2f34c]"></i> Free forever plan</span>
                        <span class="flex items-center gap-1.5"><i class="fas fa-check text-[#d2f34c]"></i> No credit card</span>
                    </div>
                </div>

                <div class="reveal reveal-delay-3 relative flex justify-center lg:justify-end">
                    <div class="relative w-[280px] sm:w-[320px]">
                        <div class="hero-float">
                            <div class="bg-gradient-to-br from-[#d2f34c] via-[#06b6d4] to-[#7c3aed] rounded-[2rem] p-[3px] shadow-2xl shadow-[#7c3aed]/30">
                                <div class="bg-[#1e2330] rounded-[1.85rem] p-5 space-y-3">
                                    <div class="flex flex-col items-center mb-1">
                                        <div class="w-18 h-18 rounded-full bg-gradient-to-br from-[#d2f34c] via-[#06b6d4] to-[#7c3aed] p-[2px] mb-3">
                                            <div class="w-full h-full rounded-full bg-[#1e2330] flex items-center justify-center">
                                                <span class="text-[#d2f34c] text-xl font-bold w-16 h-16 flex items-center justify-center">JD</span>
                                            </div>
                                        </div>
                                        <h3 class="text-base font-bold text-white">Jane Doe</h3>
                                        <p class="text-xs text-gray-500">Creator & Designer</p>
                                    </div>
                                    <a class="block w-full py-3 px-4 bg-[#d2f34c] text-[#1e2330] rounded-xl text-sm font-bold text-center">
                                        <i class="fas fa-globe mr-2"></i>My Portfolio
                                    </a>
                                    <a class="block w-full py-3 px-4 bg-[#7c3aed] text-white rounded-xl text-sm font-medium text-center">
                                        <i class="fab fa-youtube mr-2"></i>YouTube Channel
                                    </a>
                                    <a class="block w-full py-3 px-4 bg-[#06b6d4] text-white rounded-xl text-sm font-medium text-center">
                                        <i class="fab fa-instagram mr-2"></i>Instagram
                                    </a>
                                    <a class="block w-full py-3 px-4 bg-[#f43f5e] text-white rounded-xl text-sm font-medium text-center">
                                        <i class="fas fa-store mr-2"></i>Shop My Merch
                                    </a>
                                    <div class="flex justify-center gap-3 pt-1">
                                        <span class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center text-gray-400 text-xs"><i class="fab fa-twitter"></i></span>
                                        <span class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center text-gray-400 text-xs"><i class="fab fa-tiktok"></i></span>
                                        <span class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center text-gray-400 text-xs"><i class="fab fa-linkedin"></i></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="hero-float-2 absolute -top-4 -right-8 bg-[#d2f34c] rounded-2xl shadow-xl shadow-[#d2f34c]/20 p-3 flex items-center gap-2.5">
                            <div class="w-9 h-9 bg-[#1e2330] rounded-lg flex items-center justify-center"><i class="fas fa-chart-line text-[#d2f34c] text-sm"></i></div>
                            <div>
                                <div class="text-[10px] text-[#1e2330]/60 font-medium">Total Clicks</div>
                                <div class="text-sm font-bold text-[#1e2330]">24,891</div>
                            </div>
                        </div>

                        <div class="hero-float absolute -bottom-6 -left-8 bg-[#7c3aed] rounded-2xl shadow-xl shadow-[#7c3aed]/30 p-3 flex items-center gap-2.5">
                            <div class="w-9 h-9 bg-white/15 rounded-lg flex items-center justify-center"><i class="fas fa-qrcode text-white text-sm"></i></div>
                            <div>
                                <div class="text-[10px] text-white/60 font-medium">QR Scans</div>
                                <div class="text-sm font-bold text-white">3,204</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="bg-[#d2f34c] py-4 overflow-hidden">
        <div class="flex whitespace-nowrap marquee">
            @for($i = 0; $i < 2; $i++)
            <span class="inline-flex items-center gap-8 mx-4 text-[#1e2330]">
                <span class="text-sm font-bold uppercase tracking-wider flex items-center gap-2"><i class="fas fa-link"></i> URL Shortener</span>
                <span class="text-xl opacity-30">&bull;</span>
                <span class="text-sm font-bold uppercase tracking-wider flex items-center gap-2"><i class="fas fa-id-card"></i> Bio Links</span>
                <span class="text-xl opacity-30">&bull;</span>
                <span class="text-sm font-bold uppercase tracking-wider flex items-center gap-2"><i class="fas fa-file-arrow-down"></i> File Sharing</span>
                <span class="text-xl opacity-30">&bull;</span>
                <span class="text-sm font-bold uppercase tracking-wider flex items-center gap-2"><i class="fas fa-qrcode"></i> QR Codes</span>
                <span class="text-xl opacity-30">&bull;</span>
                <span class="text-sm font-bold uppercase tracking-wider flex items-center gap-2"><i class="fas fa-chart-bar"></i> Analytics</span>
                <span class="text-xl opacity-30">&bull;</span>
                <span class="text-sm font-bold uppercase tracking-wider flex items-center gap-2"><i class="fas fa-bullseye"></i> Tracking Pixels</span>
                <span class="text-xl opacity-30">&bull;</span>
                <span class="text-sm font-bold uppercase tracking-wider flex items-center gap-2"><i class="fas fa-calendar-check"></i> ICS Events</span>
                <span class="text-xl opacity-30">&bull;</span>
                <span class="text-sm font-bold uppercase tracking-wider flex items-center gap-2"><i class="fas fa-address-card"></i> VCF Cards</span>
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
                <p class="reveal reveal-delay-1 text-lg text-[#1e2330]/60 max-w-2xl mx-auto">
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
                    ['badge' => 'CREATORS & INFLUENCERS', 'badgeBg' => '#7c3aed', 'title' => 'Your brand, your rules', 'desc' => 'Build a stunning bio link page that showcases everything you create. From YouTube videos to merch stores, social profiles to booking links.', 'checks' => ['Customizable bio link pages', 'Social media link integration', 'Click analytics & audience insights', 'Retargeting pixels for fan growth'], 'checkColor' => '#7c3aed', 'mockupBg' => 'from-[#7c3aed] to-[#a855f7]', 'mockupIcon' => 'fa-camera', 'mockupName' => '@CreatorStudio', 'mockupSub' => 'Content Creator', 'links' => [['bg' => '#1e2330', 'text' => 'white', 'icon' => 'fab fa-youtube', 'label' => 'Latest Video'], ['bg' => '#7c3aed', 'text' => 'white', 'icon' => 'fas fa-shopping-bag', 'label' => 'Merch Store'], ['bg' => '#d2f34c', 'text' => '#1e2330', 'icon' => 'fab fa-patreon', 'label' => 'Support Me']]],
                    ['badge' => 'SMALL BUSINESSES', 'badgeBg' => '#059669', 'title' => 'Grow offline & online', 'desc' => 'Print QR codes on menus, packaging, and signage. Share product catalogs as files. Create branded short URLs that build trust.', 'checks' => ['QR codes for menus & products', 'File sharing for catalogs', 'Branded short URLs', 'Location-based link routing'], 'checkColor' => '#059669', 'mockupBg' => 'from-[#059669] to-[#10b981]', 'mockupIcon' => 'fa-coffee', 'mockupName' => 'Cafe Bloom', 'mockupSub' => 'Local Coffee Shop', 'links' => [['bg' => '#1e2330', 'text' => 'white', 'icon' => 'fas fa-utensils', 'label' => 'View Our Menu'], ['bg' => '#059669', 'text' => 'white', 'icon' => 'fas fa-file-pdf', 'label' => 'Download Catalog'], ['bg' => '#d2f34c', 'text' => '#1e2330', 'icon' => 'fas fa-star', 'label' => 'Leave a Review']]],
                    ['badge' => 'FREELANCERS & AGENCIES', 'badgeBg' => '#2563eb', 'title' => 'Professional identity, simplified', 'desc' => 'Share your portfolio, generate VCF contact cards for networking, and create branded link pages that impress.', 'checks' => ['VCF digital business cards', 'Portfolio & case study links', 'Branded bio pages for clients', 'Password-protected links'], 'checkColor' => '#2563eb', 'mockupBg' => 'from-[#2563eb] to-[#3b82f6]', 'mockupIcon' => 'fa-pen-nib', 'mockupName' => 'Alex Rivera', 'mockupSub' => 'UX Designer', 'links' => [['bg' => '#1e2330', 'text' => 'white', 'icon' => 'fas fa-briefcase', 'label' => 'View Portfolio'], ['bg' => '#2563eb', 'text' => 'white', 'icon' => 'fas fa-address-card', 'label' => 'Save Contact'], ['bg' => '#d2f34c', 'text' => '#1e2330', 'icon' => 'fab fa-linkedin', 'label' => 'LinkedIn']]],
                    ['badge' => 'EVENT ORGANIZERS', 'badgeBg' => '#ea580c', 'title' => 'Events made effortless', 'desc' => 'Generate ICS calendar invites, create QR codes for check-in, and share event details through a single link.', 'checks' => ['ICS calendar file generation', 'QR codes for event check-in', 'Ticket & RSVP short links', 'Expiring links for time-sensitive content'], 'checkColor' => '#ea580c', 'mockupBg' => 'from-[#ea580c] to-[#f97316]', 'mockupIcon' => 'fa-music', 'mockupName' => 'SoundWave Fest', 'mockupSub' => 'Music Festival 2026', 'links' => [['bg' => '#1e2330', 'text' => 'white', 'icon' => 'fas fa-ticket-alt', 'label' => 'Get Tickets'], ['bg' => '#ea580c', 'text' => 'white', 'icon' => 'fas fa-calendar-plus', 'label' => 'Add to Calendar'], ['bg' => '#d2f34c', 'text' => '#1e2330', 'icon' => 'fas fa-map-marker-alt', 'label' => 'Venue & Directions']]],
                    ['badge' => 'E-COMMERCE & RETAIL', 'badgeBg' => '#e11d48', 'title' => 'Drive sales from everywhere', 'desc' => 'Shorten product links for social ads, embed tracking pixels for retargeting, and measure every click.', 'checks' => ['Product link shortening', 'Facebook & Google tracking pixels', 'UTM parameter builder', 'Conversion tracking & analytics'], 'checkColor' => '#e11d48', 'mockupBg' => 'from-[#e11d48] to-[#f43f5e]', 'mockupIcon' => 'fa-gem', 'mockupName' => 'Luxe Boutique', 'mockupSub' => 'Fashion & Accessories', 'links' => [['bg' => '#1e2330', 'text' => 'white', 'icon' => 'fas fa-fire', 'label' => 'New Arrivals'], ['bg' => '#e11d48', 'text' => 'white', 'icon' => 'fas fa-tag', 'label' => 'Sale - 40% Off'], ['bg' => '#d2f34c', 'text' => '#1e2330', 'icon' => 'fas fa-gift', 'label' => 'Gift Cards']]],
                    ['badge' => 'NONPROFITS & EDUCATION', 'badgeBg' => '#0891b2', 'title' => 'Share knowledge, inspire action', 'desc' => 'Distribute learning materials, create donation links, and organize resources in a single page.', 'checks' => ['File sharing for resources', 'Donation & fundraising links', 'Bio page for program info', 'QR codes for classrooms'], 'checkColor' => '#0891b2', 'mockupBg' => 'from-[#0891b2] to-[#06b6d4]', 'mockupIcon' => 'fa-heart', 'mockupName' => 'GreenFuture Org', 'mockupSub' => 'Environmental Nonprofit', 'links' => [['bg' => '#1e2330', 'text' => 'white', 'icon' => 'fas fa-hand-holding-heart', 'label' => 'Donate Now'], ['bg' => '#0891b2', 'text' => 'white', 'icon' => 'fas fa-file-download', 'label' => 'Annual Report'], ['bg' => '#d2f34c', 'text' => '#1e2330', 'icon' => 'fas fa-users', 'label' => 'Volunteer Sign Up']]],
                ];
                @endphp

                @foreach($catData as $i => $cat)
                <div x-show="active === {{ $i }}" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-y-6" x-transition:enter-end="opacity-100 translate-y-0" @if($i > 0) x-cloak @endif class="grid lg:grid-cols-2 gap-12 items-center">
                    <div>
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold mb-4 text-white" style="background-color: {{ $cat['badgeBg'] }}">{{ $cat['badge'] }}</div>
                        <h3 class="text-3xl sm:text-4xl font-bold text-[#1e2330] mb-4">{{ $cat['title'] }}</h3>
                        <p class="text-[#1e2330]/60 mb-6 leading-relaxed text-lg">{{ $cat['desc'] }}</p>
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
                        Create and customize your 1INME in minutes
                    </h2>
                    <p class="reveal reveal-delay-1 text-lg text-white/70 mb-8 leading-relaxed">
                        Connect all your content across social media, websites, stores and more in one link. Customize every detail to match your brand and drive more clicks.
                    </p>
                    <a href="{{ route('user.register') }}" class="reveal reveal-delay-2 inline-flex items-center gap-2 px-8 py-4 bg-[#d2f34c] text-[#1e2330] rounded-full text-base font-bold hover:bg-[#e4ff6e] transition-all hover:shadow-xl hover:-translate-y-0.5">
                        Get started for free
                    </a>
                </div>
                <div class="reveal reveal-delay-2 flex justify-center">
                    <div class="grid grid-cols-2 gap-4 max-w-sm">
                        <div class="card-hover bg-white/15 backdrop-blur-sm rounded-2xl p-5 text-center border border-white/10">
                            <div class="text-3xl mb-2"><i class="fas fa-link text-[#d2f34c]"></i></div>
                            <div class="text-white font-bold text-sm">Short URLs</div>
                            <div class="text-white/50 text-xs mt-1">301 & 302 redirects</div>
                        </div>
                        <div class="card-hover bg-white/15 backdrop-blur-sm rounded-2xl p-5 text-center border border-white/10 mt-8">
                            <div class="text-3xl mb-2"><i class="fas fa-qrcode text-[#d2f34c]"></i></div>
                            <div class="text-white font-bold text-sm">QR Codes</div>
                            <div class="text-white/50 text-xs mt-1">Custom logos & colors</div>
                        </div>
                        <div class="card-hover bg-white/15 backdrop-blur-sm rounded-2xl p-5 text-center border border-white/10">
                            <div class="text-3xl mb-2"><i class="fas fa-file-arrow-down text-[#d2f34c]"></i></div>
                            <div class="text-white font-bold text-sm">File Sharing</div>
                            <div class="text-white/50 text-xs mt-1">PDF & image preview</div>
                        </div>
                        <div class="card-hover bg-white/15 backdrop-blur-sm rounded-2xl p-5 text-center border border-white/10 mt-8">
                            <div class="text-3xl mb-2"><i class="fas fa-chart-bar text-[#d2f34c]"></i></div>
                            <div class="text-white font-bold text-sm">Analytics</div>
                            <div class="text-white/50 text-xs mt-1">Real-time insights</div>
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
                                <div class="w-10 h-10 rounded-full bg-[#d2f34c] flex items-center justify-center"><i class="fas fa-qrcode text-[#1e2330]"></i></div>
                                <div>
                                    <div class="text-white font-bold text-sm">QR Code Generator</div>
                                    <div class="text-white/50 text-xs">PNG & SVG export</div>
                                </div>
                            </div>
                            <div class="bg-white rounded-2xl p-6 flex items-center justify-center mb-4">
                                <div class="w-36 h-36 bg-[#1e2330] rounded-xl flex items-center justify-center">
                                    <i class="fas fa-qrcode text-6xl text-[#d2f34c]"></i>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <div class="flex-1 py-2 bg-[#d2f34c] text-[#1e2330] rounded-lg text-xs font-bold text-center">Download PNG</div>
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
                    <a href="{{ route('user.register') }}" class="reveal reveal-delay-2 inline-flex items-center gap-2 px-8 py-4 bg-[#d2f34c] text-[#1e2330] rounded-full text-base font-bold hover:bg-[#e4ff6e] transition-all hover:shadow-xl hover:-translate-y-0.5">
                        Get started for free
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="py-24 lg:py-32 bg-[#d2f34c]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div>
                    <h2 class="reveal text-4xl sm:text-5xl lg:text-6xl font-bold text-[#1e2330] mb-6 leading-tight">
                        Analyze your audience and keep them engaged
                    </h2>
                    <p class="reveal reveal-delay-1 text-lg text-[#1e2330]/60 mb-8 leading-relaxed">
                        Track your engagement over time, monitor clicks and learn what's converting your audience. Make informed updates on the fly to keep them coming back.
                    </p>
                    <a href="{{ route('user.register') }}" class="reveal reveal-delay-2 inline-flex items-center gap-2 px-8 py-4 bg-[#1e2330] text-[#d2f34c] rounded-full text-base font-bold hover:bg-[#2a3040] transition-all hover:shadow-xl hover:-translate-y-0.5">
                        Get started for free
                    </a>
                </div>
                <div class="reveal reveal-delay-2 flex justify-center">
                    <div class="bg-[#1e2330] rounded-3xl p-6 w-full max-w-sm shadow-2xl shadow-[#1e2330]/20">
                        <div class="flex items-center justify-between mb-6">
                            <div class="text-white font-bold">Analytics</div>
                            <div class="text-xs text-[#d2f34c] font-medium bg-[#d2f34c]/10 px-2 py-1 rounded-full">Live</div>
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
                                <div class="flex-1 bg-white/5 rounded-full h-3 overflow-hidden"><div class="h-full rounded-full bg-[#d2f34c]" style="width: 31%"></div></div>
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
                    Up and running in <span class="text-[#d2f34c]">3 minutes</span>
                </h2>
                <p class="reveal reveal-delay-1 text-lg text-white/60 max-w-2xl mx-auto">
                    No technical skills needed. Create your first link in seconds.
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-8 lg:gap-12 relative">
                <div class="hidden md:block absolute top-20 left-[20%] right-[20%] h-1 bg-white/10 rounded-full"></div>

                <div class="reveal reveal-delay-1 text-center relative">
                    <div class="w-20 h-20 mx-auto bg-[#d2f34c] rounded-2xl flex items-center justify-center text-[#1e2330] text-3xl font-bold mb-6 shadow-lg shadow-[#d2f34c]/20 relative z-10 rotate-3 hover:rotate-0 transition-transform">1</div>
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
                <a href="{{ route('user.register') }}" class="inline-flex items-center gap-2 px-8 py-4 bg-[#d2f34c] text-[#1e2330] rounded-full text-base font-bold hover:bg-[#e4ff6e] transition-all hover:shadow-xl hover:-translate-y-0.5">
                    Start building now <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </section>

    <section id="pricing" class="py-24 lg:py-32 bg-[#1e2330]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="reveal text-4xl sm:text-5xl lg:text-6xl font-bold text-white mb-4">
                    Simple, <span class="text-[#d2f34c]">transparent</span> pricing
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
                        <li class="flex items-center gap-2 text-sm text-gray-300"><i class="fas fa-check text-[#d2f34c] text-xs"></i>Up to 10 links</li>
                        <li class="flex items-center gap-2 text-sm text-gray-300"><i class="fas fa-check text-[#d2f34c] text-xs"></i>Basic analytics</li>
                        <li class="flex items-center gap-2 text-sm text-gray-300"><i class="fas fa-check text-[#d2f34c] text-xs"></i>QR code generation</li>
                        <li class="flex items-center gap-2 text-sm text-gray-300"><i class="fas fa-check text-[#d2f34c] text-xs"></i>5MB file uploads</li>
                    </ul>
                    <a href="{{ route('user.register') }}" class="block w-full py-3.5 text-center text-white rounded-full text-sm font-bold border-2 border-white/20 hover:border-white/40 hover:bg-white/5 transition-all">Get started</a>
                </div>

                <div class="reveal reveal-delay-2 card-hover bg-gradient-to-br from-[#7c3aed] to-[#a855f7] rounded-3xl p-8 relative overflow-hidden shadow-2xl shadow-[#7c3aed]/30 scale-105">
                    <div class="absolute top-4 right-4 px-3 py-1 bg-[#d2f34c] text-[#1e2330] text-xs font-bold rounded-full">POPULAR</div>
                    <div class="text-sm font-bold text-white/70 uppercase tracking-wide mb-2">Pro</div>
                    <div class="text-5xl font-bold text-white mb-1">$9<span class="text-lg font-medium text-white/50">/mo</span></div>
                    <div class="text-sm text-white/50 mb-6">For creators & pros</div>
                    <ul class="space-y-3 mb-8">
                        <li class="flex items-center gap-2 text-sm text-white"><i class="fas fa-check text-[#d2f34c] text-xs"></i>Unlimited links</li>
                        <li class="flex items-center gap-2 text-sm text-white"><i class="fas fa-check text-[#d2f34c] text-xs"></i>Advanced analytics</li>
                        <li class="flex items-center gap-2 text-sm text-white"><i class="fas fa-check text-[#d2f34c] text-xs"></i>Custom domains</li>
                        <li class="flex items-center gap-2 text-sm text-white"><i class="fas fa-check text-[#d2f34c] text-xs"></i>50MB file uploads</li>
                        <li class="flex items-center gap-2 text-sm text-white"><i class="fas fa-check text-[#d2f34c] text-xs"></i>Tracking pixels</li>
                    </ul>
                    <a href="{{ route('user.register') }}" class="block w-full py-3.5 text-center bg-[#d2f34c] text-[#1e2330] rounded-full text-sm font-bold hover:bg-[#e4ff6e] transition-all">Start free trial</a>
                </div>

                <div class="reveal reveal-delay-3 card-hover bg-white/5 rounded-3xl p-8 border border-white/10 backdrop-blur-sm">
                    <div class="text-sm font-bold text-gray-400 uppercase tracking-wide mb-2">Business</div>
                    <div class="text-5xl font-bold text-white mb-1">$29<span class="text-lg font-medium text-gray-500">/mo</span></div>
                    <div class="text-sm text-gray-500 mb-6">For teams & orgs</div>
                    <ul class="space-y-3 mb-8">
                        <li class="flex items-center gap-2 text-sm text-gray-300"><i class="fas fa-check text-[#d2f34c] text-xs"></i>Everything in Pro</li>
                        <li class="flex items-center gap-2 text-sm text-gray-300"><i class="fas fa-check text-[#d2f34c] text-xs"></i>Team collaboration</li>
                        <li class="flex items-center gap-2 text-sm text-gray-300"><i class="fas fa-check text-[#d2f34c] text-xs"></i>200MB file uploads</li>
                        <li class="flex items-center gap-2 text-sm text-gray-300"><i class="fas fa-check text-[#d2f34c] text-xs"></i>API access</li>
                        <li class="flex items-center gap-2 text-sm text-gray-300"><i class="fas fa-check text-[#d2f34c] text-xs"></i>Priority support</li>
                    </ul>
                    <a href="{{ route('user.register') }}" class="block w-full py-3.5 text-center text-white rounded-full text-sm font-bold border-2 border-white/20 hover:border-white/40 hover:bg-white/5 transition-all">Get started</a>
                </div>
            </div>
        </div>
    </section>

    <section class="py-24 lg:py-32 bg-[#059669] relative overflow-hidden">
        <div class="absolute top-0 right-0 w-96 h-96 bg-[#d2f34c] rounded-full mix-blend-soft-light filter blur-[100px] opacity-30"></div>
        <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="reveal text-4xl sm:text-5xl lg:text-6xl font-bold text-white mb-6 leading-tight">
                Ready to simplify your online presence?
            </h2>
            <p class="reveal reveal-delay-1 text-lg text-white/70 mb-10 max-w-xl mx-auto">
                Join thousands of creators and businesses who trust 1INME to manage their links.
            </p>
            <a href="{{ route('user.register') }}" class="reveal reveal-delay-2 inline-flex items-center gap-2 px-10 py-5 bg-[#d2f34c] text-[#1e2330] rounded-full text-lg font-bold hover:bg-[#e4ff6e] transition-all hover:shadow-xl hover:-translate-y-1">
                Create your free account <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </section>

    <footer class="bg-[#1e2330] text-white pt-16 pb-8 border-t border-white/5">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-4 gap-8 mb-12">
                <div>
                    <a href="{{ route('home') }}" class="text-2xl font-bold tracking-tight">
                        <span class="text-white">1IN</span><span class="text-[#d2f34c]">ME</span>
                    </a>
                    <p class="text-sm text-gray-500 mt-3 leading-relaxed">Everything you are. In one simple link. The all-in-one link management platform.</p>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-gray-400 uppercase tracking-wide mb-4">Product</h4>
                    <ul class="space-y-2.5">
                        <li><a href="#features" class="text-sm text-gray-500 hover:text-[#d2f34c] transition-colors">Features</a></li>
                        <li><a href="#pricing" class="text-sm text-gray-500 hover:text-[#d2f34c] transition-colors">Pricing</a></li>
                        <li><a href="#use-cases" class="text-sm text-gray-500 hover:text-[#d2f34c] transition-colors">Use Cases</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-gray-400 uppercase tracking-wide mb-4">Tools</h4>
                    <ul class="space-y-2.5">
                        <li><a href="{{ route('user.register') }}" class="text-sm text-gray-500 hover:text-[#d2f34c] transition-colors">URL Shortener</a></li>
                        <li><a href="{{ route('user.register') }}" class="text-sm text-gray-500 hover:text-[#d2f34c] transition-colors">Bio Link Builder</a></li>
                        <li><a href="{{ route('user.register') }}" class="text-sm text-gray-500 hover:text-[#d2f34c] transition-colors">QR Generator</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-gray-400 uppercase tracking-wide mb-4">Account</h4>
                    <ul class="space-y-2.5">
                        @auth
                            <li><a href="{{ route('user.dashboard') }}" class="text-sm text-gray-500 hover:text-[#d2f34c] transition-colors">Dashboard</a></li>
                            <li><a href="{{ route('user.profile.edit') }}" class="text-sm text-gray-500 hover:text-[#d2f34c] transition-colors">Profile</a></li>
                        @else
                            <li><a href="{{ route('user.login') }}" class="text-sm text-gray-500 hover:text-[#d2f34c] transition-colors">Log in</a></li>
                            <li><a href="{{ route('user.register') }}" class="text-sm text-gray-500 hover:text-[#d2f34c] transition-colors">Sign up</a></li>
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
