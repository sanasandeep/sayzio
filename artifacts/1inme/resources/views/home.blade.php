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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        brand: { 50:'#f0f9ff',100:'#e0f2fe',200:'#bae6fd',300:'#7dd3fc',400:'#38bdf8',500:'#0ea5e9',600:'#0284c7',700:'#0369a1',800:'#075985',900:'#0c4a6e' },
                    }
                }
            }
        }
    </script>
    <style>
        [x-cloak] { display: none !important; }
        .reveal { opacity: 0; transform: translateY(40px); transition: opacity 0.8s cubic-bezier(0.16, 1, 0.3, 1), transform 0.8s cubic-bezier(0.16, 1, 0.3, 1); }
        .reveal.visible { opacity: 1; transform: translateY(0); }
        .reveal-delay-1 { transition-delay: 0.1s; }
        .reveal-delay-2 { transition-delay: 0.2s; }
        .reveal-delay-3 { transition-delay: 0.3s; }
        .reveal-delay-4 { transition-delay: 0.4s; }
        .reveal-delay-5 { transition-delay: 0.5s; }
        .hero-float { animation: heroFloat 6s ease-in-out infinite; }
        @keyframes heroFloat { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-12px); } }
        .hero-float-delay { animation: heroFloat 6s ease-in-out infinite; animation-delay: -3s; }
        .gradient-text { background: linear-gradient(135deg, #0ea5e9, #8b5cf6, #ec4899); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .category-card { transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
        .category-card:hover { transform: translateY(-6px); }
        .pulse-ring { animation: pulseRing 2s ease-out infinite; }
        @keyframes pulseRing { 0% { transform: scale(1); opacity: 0.4; } 100% { transform: scale(1.5); opacity: 0; } }
        .bg-grid { background-image: radial-gradient(circle, #e5e7eb 1px, transparent 1px); background-size: 24px 24px; }
    </style>
</head>
<body class="bg-white font-sans text-gray-900 overflow-x-hidden">

    <nav class="fixed top-0 left-0 right-0 z-50 bg-white/80 backdrop-blur-lg border-b border-gray-100" x-data="{ mobileOpen: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <a href="{{ route('home') }}" class="text-2xl font-extrabold tracking-tight">
                    <span class="text-gray-900">1IN</span><span class="text-brand-500">ME</span>
                </a>

                <div class="hidden md:flex items-center gap-6">
                    <a href="#categories" class="text-sm text-gray-600 hover:text-gray-900 transition-colors">Use Cases</a>
                    <a href="#features" class="text-sm text-gray-600 hover:text-gray-900 transition-colors">Features</a>
                    <a href="#how-it-works" class="text-sm text-gray-600 hover:text-gray-900 transition-colors">How It Works</a>
                    <a href="#pricing" class="text-sm text-gray-600 hover:text-gray-900 transition-colors">Pricing</a>
                </div>

                <div class="hidden md:flex items-center gap-3">
                    @auth
                        <a href="{{ route('user.dashboard') }}" class="px-5 py-2.5 bg-brand-500 text-white rounded-full text-sm font-semibold hover:bg-brand-600 transition-all hover:shadow-lg hover:shadow-brand-500/25">Dashboard</a>
                    @else
                        <a href="{{ route('user.login') }}" class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-900 transition-colors">Log in</a>
                        <a href="{{ route('user.register') }}" class="px-5 py-2.5 bg-gray-900 text-white rounded-full text-sm font-semibold hover:bg-gray-800 transition-all hover:shadow-lg">Sign up free</a>
                    @endauth
                </div>

                <button @click="mobileOpen = !mobileOpen" class="md:hidden p-2 text-gray-600">
                    <i class="fas" :class="mobileOpen ? 'fa-times' : 'fa-bars'" x-text=""></i>
                    <svg x-show="!mobileOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    <svg x-show="mobileOpen" x-cloak class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div x-show="mobileOpen" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="md:hidden pb-4 border-t border-gray-100 mt-2 pt-4 space-y-2">
                <a href="#categories" @click="mobileOpen = false" class="block px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 rounded-lg">Use Cases</a>
                <a href="#features" @click="mobileOpen = false" class="block px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 rounded-lg">Features</a>
                <a href="#how-it-works" @click="mobileOpen = false" class="block px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 rounded-lg">How It Works</a>
                <a href="#pricing" @click="mobileOpen = false" class="block px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 rounded-lg">Pricing</a>
                <div class="pt-2 border-t border-gray-100 space-y-2">
                    @auth
                        <a href="{{ route('user.dashboard') }}" class="block px-4 py-2.5 bg-brand-500 text-white rounded-lg text-sm font-semibold text-center">Dashboard</a>
                    @else
                        <a href="{{ route('user.login') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-lg">Log in</a>
                        <a href="{{ route('user.register') }}" class="block px-4 py-2.5 bg-gray-900 text-white rounded-lg text-sm font-semibold text-center">Sign up free</a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <section class="relative pt-32 pb-20 lg:pt-40 lg:pb-32 overflow-hidden">
        <div class="absolute inset-0 bg-grid opacity-40"></div>
        <div class="absolute top-20 left-10 w-72 h-72 bg-brand-200 rounded-full mix-blend-multiply filter blur-3xl opacity-30 hero-float"></div>
        <div class="absolute top-40 right-10 w-72 h-72 bg-purple-200 rounded-full mix-blend-multiply filter blur-3xl opacity-30 hero-float-delay"></div>
        <div class="absolute bottom-10 left-1/3 w-72 h-72 bg-pink-200 rounded-full mix-blend-multiply filter blur-3xl opacity-20 hero-float"></div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">
                <div class="text-center lg:text-left">
                    <div class="reveal inline-flex items-center gap-2 px-4 py-1.5 bg-brand-50 border border-brand-100 rounded-full text-brand-700 text-sm font-medium mb-6">
                        <span class="relative flex h-2 w-2"><span class="pulse-ring absolute inline-flex h-full w-full rounded-full bg-brand-400"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-brand-500"></span></span>
                        The link management platform
                    </div>

                    <h1 class="reveal reveal-delay-1 text-4xl sm:text-5xl lg:text-6xl font-extrabold leading-[1.1] tracking-tight mb-6">
                        Everything you are.<br>
                        <span class="gradient-text">In one simple link.</span>
                    </h1>

                    <p class="reveal reveal-delay-2 text-lg sm:text-xl text-gray-600 max-w-lg mx-auto lg:mx-0 mb-8 leading-relaxed">
                        Connect your audience to all of your content with just one link. Bio pages, short URLs, file sharing, QR codes, analytics, and more.
                    </p>

                    <div class="reveal reveal-delay-3 flex flex-col sm:flex-row gap-3 justify-center lg:justify-start">
                        <a href="{{ route('user.register') }}" class="px-8 py-4 bg-gray-900 text-white rounded-full text-base font-semibold hover:bg-gray-800 transition-all hover:shadow-xl hover:shadow-gray-900/20 hover:-translate-y-0.5">
                            Get started for free
                        </a>
                        <a href="#categories" class="px-8 py-4 bg-white text-gray-700 rounded-full text-base font-semibold border border-gray-200 hover:border-gray-300 hover:bg-gray-50 transition-all">
                            See how it works
                        </a>
                    </div>

                    <div class="reveal reveal-delay-4 flex items-center gap-6 mt-8 justify-center lg:justify-start text-sm text-gray-500">
                        <span class="flex items-center gap-1.5"><i class="fas fa-check text-green-500"></i> Free forever plan</span>
                        <span class="flex items-center gap-1.5"><i class="fas fa-check text-green-500"></i> No credit card</span>
                    </div>
                </div>

                <div class="reveal reveal-delay-3 relative flex justify-center lg:justify-end">
                    <div class="relative w-[300px] sm:w-[340px]">
                        <div class="hero-float bg-gradient-to-br from-brand-500 via-purple-500 to-pink-500 rounded-[2rem] p-1 shadow-2xl shadow-brand-500/20">
                            <div class="bg-white rounded-[1.75rem] p-6 space-y-4">
                                <div class="flex flex-col items-center mb-2">
                                    <div class="w-20 h-20 rounded-full bg-gradient-to-br from-brand-400 to-purple-500 flex items-center justify-center text-white text-2xl font-bold mb-3">JD</div>
                                    <h3 class="text-lg font-bold text-gray-900">Jane Doe</h3>
                                    <p class="text-sm text-gray-500">Creator & Designer</p>
                                </div>
                                <a class="block w-full py-3 px-4 bg-gray-900 text-white rounded-xl text-sm font-medium text-center hover:bg-gray-800 transition-colors">
                                    <i class="fas fa-globe mr-2"></i>My Portfolio
                                </a>
                                <a class="block w-full py-3 px-4 bg-brand-50 text-brand-700 rounded-xl text-sm font-medium text-center border border-brand-100">
                                    <i class="fab fa-youtube mr-2"></i>YouTube Channel
                                </a>
                                <a class="block w-full py-3 px-4 bg-purple-50 text-purple-700 rounded-xl text-sm font-medium text-center border border-purple-100">
                                    <i class="fab fa-instagram mr-2"></i>Instagram
                                </a>
                                <a class="block w-full py-3 px-4 bg-pink-50 text-pink-700 rounded-xl text-sm font-medium text-center border border-pink-100">
                                    <i class="fas fa-store mr-2"></i>Shop My Merch
                                </a>
                                <div class="flex justify-center gap-4 pt-2">
                                    <span class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 text-xs"><i class="fab fa-twitter"></i></span>
                                    <span class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 text-xs"><i class="fab fa-tiktok"></i></span>
                                    <span class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 text-xs"><i class="fab fa-linkedin"></i></span>
                                </div>
                            </div>
                        </div>

                        <div class="hero-float-delay absolute -top-4 -right-6 bg-white rounded-2xl shadow-xl border border-gray-100 p-3 flex items-center gap-2.5">
                            <div class="w-9 h-9 bg-green-100 rounded-lg flex items-center justify-center"><i class="fas fa-chart-line text-green-600 text-sm"></i></div>
                            <div>
                                <div class="text-xs text-gray-500">Total Clicks</div>
                                <div class="text-sm font-bold text-gray-900">24,891</div>
                            </div>
                        </div>

                        <div class="hero-float absolute -bottom-4 -left-6 bg-white rounded-2xl shadow-xl border border-gray-100 p-3 flex items-center gap-2.5">
                            <div class="w-9 h-9 bg-purple-100 rounded-lg flex items-center justify-center"><i class="fas fa-qrcode text-purple-600 text-sm"></i></div>
                            <div>
                                <div class="text-xs text-gray-500">QR Scans</div>
                                <div class="text-sm font-bold text-gray-900">3,204</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-16 bg-gray-50 border-y border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="reveal grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
                <div>
                    <div class="text-3xl font-extrabold text-gray-900">6</div>
                    <div class="text-sm text-gray-500 mt-1">Link Types</div>
                </div>
                <div>
                    <div class="text-3xl font-extrabold text-gray-900">10+</div>
                    <div class="text-sm text-gray-500 mt-1">Tracking Pixels</div>
                </div>
                <div>
                    <div class="text-3xl font-extrabold text-gray-900">100%</div>
                    <div class="text-sm text-gray-500 mt-1">Free to Start</div>
                </div>
                <div>
                    <div class="text-3xl font-extrabold text-gray-900">&infin;</div>
                    <div class="text-sm text-gray-500 mt-1">Possibilities</div>
                </div>
            </div>
        </div>
    </section>

    <section id="categories" class="py-20 lg:py-28" x-data="{ active: 0, auto: true }" x-init="setInterval(() => { if(auto) active = (active + 1) % 6 }, 4000)">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="reveal text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight mb-4">
                    Built for <span class="gradient-text">every business</span>
                </h2>
                <p class="reveal reveal-delay-1 text-lg text-gray-600 max-w-2xl mx-auto">
                    Whether you're a creator, entrepreneur, or enterprise — 1INME adapts to your needs with powerful link management tools.
                </p>
            </div>

            <div class="reveal reveal-delay-2 flex flex-wrap justify-center gap-2 mb-12">
                @php
                $categories = [
                    ['icon' => 'fa-palette', 'label' => 'Creators', 'color' => 'brand'],
                    ['icon' => 'fa-store', 'label' => 'Small Business', 'color' => 'emerald'],
                    ['icon' => 'fa-briefcase', 'label' => 'Freelancers', 'color' => 'violet'],
                    ['icon' => 'fa-calendar-check', 'label' => 'Events', 'color' => 'orange'],
                    ['icon' => 'fa-shopping-bag', 'label' => 'E-Commerce', 'color' => 'rose'],
                    ['icon' => 'fa-graduation-cap', 'label' => 'Nonprofits', 'color' => 'teal'],
                ];
                @endphp
                @foreach($categories as $i => $cat)
                <button @click="active = {{ $i }}; auto = false"
                    :class="active === {{ $i }} ? 'bg-gray-900 text-white shadow-lg' : 'bg-white text-gray-600 hover:bg-gray-50 border border-gray-200'"
                    class="px-5 py-2.5 rounded-full text-sm font-medium transition-all duration-300 flex items-center gap-2">
                    <i class="fas {{ $cat['icon'] }}"></i>
                    {{ $cat['label'] }}
                </button>
                @endforeach
            </div>

            <div class="relative">
                <div x-show="active === 0" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" class="grid lg:grid-cols-2 gap-10 items-center">
                    <div>
                        <div class="inline-flex items-center gap-2 px-3 py-1 bg-sky-50 text-sky-700 rounded-full text-xs font-semibold mb-4">CREATORS & INFLUENCERS</div>
                        <h3 class="text-2xl sm:text-3xl font-bold mb-4">Your brand, your rules</h3>
                        <p class="text-gray-600 mb-6 leading-relaxed">Build a stunning bio link page that showcases everything you create. From YouTube videos to merch stores, social profiles to booking links — all in one customizable page.</p>
                        <ul class="space-y-3">
                            <li class="flex items-center gap-3 text-sm text-gray-700"><span class="w-6 h-6 bg-sky-100 rounded-full flex items-center justify-center flex-shrink-0"><i class="fas fa-check text-sky-600 text-xs"></i></span>Customizable bio link pages</li>
                            <li class="flex items-center gap-3 text-sm text-gray-700"><span class="w-6 h-6 bg-sky-100 rounded-full flex items-center justify-center flex-shrink-0"><i class="fas fa-check text-sky-600 text-xs"></i></span>Social media link integration</li>
                            <li class="flex items-center gap-3 text-sm text-gray-700"><span class="w-6 h-6 bg-sky-100 rounded-full flex items-center justify-center flex-shrink-0"><i class="fas fa-check text-sky-600 text-xs"></i></span>Click analytics & audience insights</li>
                            <li class="flex items-center gap-3 text-sm text-gray-700"><span class="w-6 h-6 bg-sky-100 rounded-full flex items-center justify-center flex-shrink-0"><i class="fas fa-check text-sky-600 text-xs"></i></span>Retargeting pixels for fan growth</li>
                        </ul>
                    </div>
                    <div class="flex justify-center">
                        <div class="w-72 bg-gradient-to-br from-sky-400 to-blue-600 rounded-[2rem] p-1 shadow-2xl shadow-sky-500/20">
                            <div class="bg-white rounded-[1.75rem] p-5 space-y-3">
                                <div class="flex flex-col items-center"><div class="w-16 h-16 rounded-full bg-gradient-to-br from-sky-400 to-blue-500 flex items-center justify-center text-white text-xl font-bold mb-2"><i class="fas fa-camera"></i></div><div class="font-bold text-sm">@CreatorStudio</div><div class="text-xs text-gray-400">Content Creator</div></div>
                                <div class="py-2.5 bg-gray-900 text-white rounded-xl text-xs font-medium text-center"><i class="fab fa-youtube mr-1"></i>Latest Video</div>
                                <div class="py-2.5 bg-sky-50 text-sky-700 rounded-xl text-xs font-medium text-center border border-sky-100"><i class="fas fa-shopping-bag mr-1"></i>Merch Store</div>
                                <div class="py-2.5 bg-purple-50 text-purple-700 rounded-xl text-xs font-medium text-center border border-purple-100"><i class="fab fa-patreon mr-1"></i>Support Me</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div x-show="active === 1" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" x-cloak class="grid lg:grid-cols-2 gap-10 items-center">
                    <div>
                        <div class="inline-flex items-center gap-2 px-3 py-1 bg-emerald-50 text-emerald-700 rounded-full text-xs font-semibold mb-4">SMALL BUSINESSES</div>
                        <h3 class="text-2xl sm:text-3xl font-bold mb-4">Grow your business offline & online</h3>
                        <p class="text-gray-600 mb-6 leading-relaxed">Print QR codes on menus, packaging, and signage. Share product catalogs as files. Create branded short URLs that build trust and drive traffic.</p>
                        <ul class="space-y-3">
                            <li class="flex items-center gap-3 text-sm text-gray-700"><span class="w-6 h-6 bg-emerald-100 rounded-full flex items-center justify-center flex-shrink-0"><i class="fas fa-check text-emerald-600 text-xs"></i></span>QR codes for menus & products</li>
                            <li class="flex items-center gap-3 text-sm text-gray-700"><span class="w-6 h-6 bg-emerald-100 rounded-full flex items-center justify-center flex-shrink-0"><i class="fas fa-check text-emerald-600 text-xs"></i></span>File sharing for catalogs & brochures</li>
                            <li class="flex items-center gap-3 text-sm text-gray-700"><span class="w-6 h-6 bg-emerald-100 rounded-full flex items-center justify-center flex-shrink-0"><i class="fas fa-check text-emerald-600 text-xs"></i></span>Branded short URLs</li>
                            <li class="flex items-center gap-3 text-sm text-gray-700"><span class="w-6 h-6 bg-emerald-100 rounded-full flex items-center justify-center flex-shrink-0"><i class="fas fa-check text-emerald-600 text-xs"></i></span>Location-based link routing</li>
                        </ul>
                    </div>
                    <div class="flex justify-center">
                        <div class="w-72 bg-gradient-to-br from-emerald-400 to-green-600 rounded-[2rem] p-1 shadow-2xl shadow-emerald-500/20">
                            <div class="bg-white rounded-[1.75rem] p-5 space-y-3">
                                <div class="flex flex-col items-center"><div class="w-16 h-16 rounded-full bg-gradient-to-br from-emerald-400 to-green-500 flex items-center justify-center text-white text-xl font-bold mb-2"><i class="fas fa-coffee"></i></div><div class="font-bold text-sm">Cafe Bloom</div><div class="text-xs text-gray-400">Local Coffee Shop</div></div>
                                <div class="py-2.5 bg-gray-900 text-white rounded-xl text-xs font-medium text-center"><i class="fas fa-utensils mr-1"></i>View Our Menu</div>
                                <div class="py-2.5 bg-emerald-50 text-emerald-700 rounded-xl text-xs font-medium text-center border border-emerald-100"><i class="fas fa-file-pdf mr-1"></i>Download Catalog</div>
                                <div class="py-2.5 bg-amber-50 text-amber-700 rounded-xl text-xs font-medium text-center border border-amber-100"><i class="fas fa-star mr-1"></i>Leave a Review</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div x-show="active === 2" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" x-cloak class="grid lg:grid-cols-2 gap-10 items-center">
                    <div>
                        <div class="inline-flex items-center gap-2 px-3 py-1 bg-violet-50 text-violet-700 rounded-full text-xs font-semibold mb-4">FREELANCERS & AGENCIES</div>
                        <h3 class="text-2xl sm:text-3xl font-bold mb-4">Your professional identity, simplified</h3>
                        <p class="text-gray-600 mb-6 leading-relaxed">Share your portfolio, generate VCF contact cards for networking events, and create branded link pages that make lasting first impressions.</p>
                        <ul class="space-y-3">
                            <li class="flex items-center gap-3 text-sm text-gray-700"><span class="w-6 h-6 bg-violet-100 rounded-full flex items-center justify-center flex-shrink-0"><i class="fas fa-check text-violet-600 text-xs"></i></span>VCF digital business cards</li>
                            <li class="flex items-center gap-3 text-sm text-gray-700"><span class="w-6 h-6 bg-violet-100 rounded-full flex items-center justify-center flex-shrink-0"><i class="fas fa-check text-violet-600 text-xs"></i></span>Portfolio & case study links</li>
                            <li class="flex items-center gap-3 text-sm text-gray-700"><span class="w-6 h-6 bg-violet-100 rounded-full flex items-center justify-center flex-shrink-0"><i class="fas fa-check text-violet-600 text-xs"></i></span>Branded bio pages for clients</li>
                            <li class="flex items-center gap-3 text-sm text-gray-700"><span class="w-6 h-6 bg-violet-100 rounded-full flex items-center justify-center flex-shrink-0"><i class="fas fa-check text-violet-600 text-xs"></i></span>Password-protected links</li>
                        </ul>
                    </div>
                    <div class="flex justify-center">
                        <div class="w-72 bg-gradient-to-br from-violet-400 to-purple-600 rounded-[2rem] p-1 shadow-2xl shadow-violet-500/20">
                            <div class="bg-white rounded-[1.75rem] p-5 space-y-3">
                                <div class="flex flex-col items-center"><div class="w-16 h-16 rounded-full bg-gradient-to-br from-violet-400 to-purple-500 flex items-center justify-center text-white text-xl font-bold mb-2"><i class="fas fa-pen-nib"></i></div><div class="font-bold text-sm">Alex Rivera</div><div class="text-xs text-gray-400">UX Designer</div></div>
                                <div class="py-2.5 bg-gray-900 text-white rounded-xl text-xs font-medium text-center"><i class="fas fa-briefcase mr-1"></i>View Portfolio</div>
                                <div class="py-2.5 bg-violet-50 text-violet-700 rounded-xl text-xs font-medium text-center border border-violet-100"><i class="fas fa-address-card mr-1"></i>Save Contact (VCF)</div>
                                <div class="py-2.5 bg-blue-50 text-blue-700 rounded-xl text-xs font-medium text-center border border-blue-100"><i class="fab fa-linkedin mr-1"></i>LinkedIn</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div x-show="active === 3" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" x-cloak class="grid lg:grid-cols-2 gap-10 items-center">
                    <div>
                        <div class="inline-flex items-center gap-2 px-3 py-1 bg-orange-50 text-orange-700 rounded-full text-xs font-semibold mb-4">EVENT ORGANIZERS</div>
                        <h3 class="text-2xl sm:text-3xl font-bold mb-4">Events made effortless</h3>
                        <p class="text-gray-600 mb-6 leading-relaxed">Generate ICS calendar invites that attendees can add with one tap. Create QR codes for check-in, and share event details through a single link.</p>
                        <ul class="space-y-3">
                            <li class="flex items-center gap-3 text-sm text-gray-700"><span class="w-6 h-6 bg-orange-100 rounded-full flex items-center justify-center flex-shrink-0"><i class="fas fa-check text-orange-600 text-xs"></i></span>ICS calendar file generation</li>
                            <li class="flex items-center gap-3 text-sm text-gray-700"><span class="w-6 h-6 bg-orange-100 rounded-full flex items-center justify-center flex-shrink-0"><i class="fas fa-check text-orange-600 text-xs"></i></span>QR codes for event check-in</li>
                            <li class="flex items-center gap-3 text-sm text-gray-700"><span class="w-6 h-6 bg-orange-100 rounded-full flex items-center justify-center flex-shrink-0"><i class="fas fa-check text-orange-600 text-xs"></i></span>Ticket & RSVP short links</li>
                            <li class="flex items-center gap-3 text-sm text-gray-700"><span class="w-6 h-6 bg-orange-100 rounded-full flex items-center justify-center flex-shrink-0"><i class="fas fa-check text-orange-600 text-xs"></i></span>Expiring links for time-sensitive content</li>
                        </ul>
                    </div>
                    <div class="flex justify-center">
                        <div class="w-72 bg-gradient-to-br from-orange-400 to-red-500 rounded-[2rem] p-1 shadow-2xl shadow-orange-500/20">
                            <div class="bg-white rounded-[1.75rem] p-5 space-y-3">
                                <div class="flex flex-col items-center"><div class="w-16 h-16 rounded-full bg-gradient-to-br from-orange-400 to-red-500 flex items-center justify-center text-white text-xl font-bold mb-2"><i class="fas fa-music"></i></div><div class="font-bold text-sm">SoundWave Fest</div><div class="text-xs text-gray-400">Music Festival 2026</div></div>
                                <div class="py-2.5 bg-gray-900 text-white rounded-xl text-xs font-medium text-center"><i class="fas fa-ticket-alt mr-1"></i>Get Tickets</div>
                                <div class="py-2.5 bg-orange-50 text-orange-700 rounded-xl text-xs font-medium text-center border border-orange-100"><i class="fas fa-calendar-plus mr-1"></i>Add to Calendar</div>
                                <div class="py-2.5 bg-red-50 text-red-700 rounded-xl text-xs font-medium text-center border border-red-100"><i class="fas fa-map-marker-alt mr-1"></i>Venue & Directions</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div x-show="active === 4" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" x-cloak class="grid lg:grid-cols-2 gap-10 items-center">
                    <div>
                        <div class="inline-flex items-center gap-2 px-3 py-1 bg-rose-50 text-rose-700 rounded-full text-xs font-semibold mb-4">E-COMMERCE & RETAIL</div>
                        <h3 class="text-2xl sm:text-3xl font-bold mb-4">Drive sales from everywhere</h3>
                        <p class="text-gray-600 mb-6 leading-relaxed">Shorten product links for social ads, embed tracking pixels for retargeting, and measure every click to optimize your marketing ROI.</p>
                        <ul class="space-y-3">
                            <li class="flex items-center gap-3 text-sm text-gray-700"><span class="w-6 h-6 bg-rose-100 rounded-full flex items-center justify-center flex-shrink-0"><i class="fas fa-check text-rose-600 text-xs"></i></span>Product link shortening</li>
                            <li class="flex items-center gap-3 text-sm text-gray-700"><span class="w-6 h-6 bg-rose-100 rounded-full flex items-center justify-center flex-shrink-0"><i class="fas fa-check text-rose-600 text-xs"></i></span>Facebook & Google tracking pixels</li>
                            <li class="flex items-center gap-3 text-sm text-gray-700"><span class="w-6 h-6 bg-rose-100 rounded-full flex items-center justify-center flex-shrink-0"><i class="fas fa-check text-rose-600 text-xs"></i></span>UTM parameter builder</li>
                            <li class="flex items-center gap-3 text-sm text-gray-700"><span class="w-6 h-6 bg-rose-100 rounded-full flex items-center justify-center flex-shrink-0"><i class="fas fa-check text-rose-600 text-xs"></i></span>Conversion tracking & analytics</li>
                        </ul>
                    </div>
                    <div class="flex justify-center">
                        <div class="w-72 bg-gradient-to-br from-rose-400 to-pink-600 rounded-[2rem] p-1 shadow-2xl shadow-rose-500/20">
                            <div class="bg-white rounded-[1.75rem] p-5 space-y-3">
                                <div class="flex flex-col items-center"><div class="w-16 h-16 rounded-full bg-gradient-to-br from-rose-400 to-pink-500 flex items-center justify-center text-white text-xl font-bold mb-2"><i class="fas fa-gem"></i></div><div class="font-bold text-sm">Luxe Boutique</div><div class="text-xs text-gray-400">Fashion & Accessories</div></div>
                                <div class="py-2.5 bg-gray-900 text-white rounded-xl text-xs font-medium text-center"><i class="fas fa-fire mr-1"></i>New Arrivals</div>
                                <div class="py-2.5 bg-rose-50 text-rose-700 rounded-xl text-xs font-medium text-center border border-rose-100"><i class="fas fa-tag mr-1"></i>Sale - 40% Off</div>
                                <div class="py-2.5 bg-pink-50 text-pink-700 rounded-xl text-xs font-medium text-center border border-pink-100"><i class="fas fa-gift mr-1"></i>Gift Cards</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div x-show="active === 5" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" x-cloak class="grid lg:grid-cols-2 gap-10 items-center">
                    <div>
                        <div class="inline-flex items-center gap-2 px-3 py-1 bg-teal-50 text-teal-700 rounded-full text-xs font-semibold mb-4">NONPROFITS & EDUCATION</div>
                        <h3 class="text-2xl sm:text-3xl font-bold mb-4">Share knowledge, inspire action</h3>
                        <p class="text-gray-600 mb-6 leading-relaxed">Distribute learning materials as downloadable files, create donation links, and organize resources in a single accessible page for your community.</p>
                        <ul class="space-y-3">
                            <li class="flex items-center gap-3 text-sm text-gray-700"><span class="w-6 h-6 bg-teal-100 rounded-full flex items-center justify-center flex-shrink-0"><i class="fas fa-check text-teal-600 text-xs"></i></span>File sharing for resources & materials</li>
                            <li class="flex items-center gap-3 text-sm text-gray-700"><span class="w-6 h-6 bg-teal-100 rounded-full flex items-center justify-center flex-shrink-0"><i class="fas fa-check text-teal-600 text-xs"></i></span>Donation & fundraising links</li>
                            <li class="flex items-center gap-3 text-sm text-gray-700"><span class="w-6 h-6 bg-teal-100 rounded-full flex items-center justify-center flex-shrink-0"><i class="fas fa-check text-teal-600 text-xs"></i></span>Bio page for program information</li>
                            <li class="flex items-center gap-3 text-sm text-gray-700"><span class="w-6 h-6 bg-teal-100 rounded-full flex items-center justify-center flex-shrink-0"><i class="fas fa-check text-teal-600 text-xs"></i></span>QR codes for classroom materials</li>
                        </ul>
                    </div>
                    <div class="flex justify-center">
                        <div class="w-72 bg-gradient-to-br from-teal-400 to-cyan-600 rounded-[2rem] p-1 shadow-2xl shadow-teal-500/20">
                            <div class="bg-white rounded-[1.75rem] p-5 space-y-3">
                                <div class="flex flex-col items-center"><div class="w-16 h-16 rounded-full bg-gradient-to-br from-teal-400 to-cyan-500 flex items-center justify-center text-white text-xl font-bold mb-2"><i class="fas fa-heart"></i></div><div class="font-bold text-sm">GreenFuture Org</div><div class="text-xs text-gray-400">Environmental Nonprofit</div></div>
                                <div class="py-2.5 bg-gray-900 text-white rounded-xl text-xs font-medium text-center"><i class="fas fa-hand-holding-heart mr-1"></i>Donate Now</div>
                                <div class="py-2.5 bg-teal-50 text-teal-700 rounded-xl text-xs font-medium text-center border border-teal-100"><i class="fas fa-file-download mr-1"></i>Annual Report (PDF)</div>
                                <div class="py-2.5 bg-cyan-50 text-cyan-700 rounded-xl text-xs font-medium text-center border border-cyan-100"><i class="fas fa-users mr-1"></i>Volunteer Sign Up</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-center gap-2 mt-10">
                @for($i = 0; $i < 6; $i++)
                <button @click="active = {{ $i }}; auto = false" :class="active === {{ $i }} ? 'bg-gray-900 w-8' : 'bg-gray-300 w-2'" class="h-2 rounded-full transition-all duration-500"></button>
                @endfor
            </div>
        </div>
    </section>

    <section id="features" class="py-20 lg:py-28 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="reveal text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight mb-4">
                    Powerful tools, <span class="gradient-text">simple to use</span>
                </h2>
                <p class="reveal reveal-delay-1 text-lg text-gray-600 max-w-2xl mx-auto">
                    Everything you need to manage, share, and track your links — all from one dashboard.
                </p>
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @php
                $features = [
                    ['icon' => 'fa-link', 'title' => 'URL Shortener', 'desc' => 'Create clean, branded short links with custom aliases. Choose 301 or 302 redirects, add UTM parameters, and track every click.', 'color' => 'blue', 'bg' => 'from-blue-500 to-cyan-500'],
                    ['icon' => 'fa-id-card', 'title' => 'Bio Link Pages', 'desc' => 'Build beautiful link-in-bio pages that showcase your brand. Customizable design with social links and unlimited buttons.', 'color' => 'purple', 'bg' => 'from-purple-500 to-pink-500'],
                    ['icon' => 'fa-file-arrow-down', 'title' => 'File Sharing', 'desc' => 'Share files with branded download pages or direct links. Preview images and PDFs inline. Control access with passwords.', 'color' => 'emerald', 'bg' => 'from-emerald-500 to-green-500'],
                    ['icon' => 'fa-qrcode', 'title' => 'QR Code Generator', 'desc' => 'Generate customizable QR codes with your brand colors and logo overlay. Download as PNG or SVG, or embed anywhere.', 'color' => 'orange', 'bg' => 'from-orange-500 to-red-500'],
                    ['icon' => 'fa-chart-bar', 'title' => 'Analytics', 'desc' => 'Track clicks, geographic data, devices, and referrers in real time. Understand your audience and optimize performance.', 'color' => 'indigo', 'bg' => 'from-indigo-500 to-violet-500'],
                    ['icon' => 'fa-bullseye', 'title' => 'Tracking Pixels', 'desc' => 'Add Facebook, Google, TikTok, and 7+ other retargeting pixels to your links. Grow your audience with every click.', 'color' => 'rose', 'bg' => 'from-rose-500 to-pink-500'],
                ];
                @endphp

                @foreach($features as $idx => $feature)
                <div class="reveal reveal-delay-{{ ($idx % 3) + 1 }} category-card group bg-white rounded-2xl p-6 border border-gray-100 hover:border-gray-200 hover:shadow-xl transition-all duration-300">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br {{ $feature['bg'] }} flex items-center justify-center text-white mb-5 group-hover:scale-110 transition-transform duration-300">
                        <i class="fas {{ $feature['icon'] }} text-lg"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">{{ $feature['title'] }}</h3>
                    <p class="text-sm text-gray-600 leading-relaxed">{{ $feature['desc'] }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    <section id="how-it-works" class="py-20 lg:py-28">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="reveal text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight mb-4">
                    Up and running in <span class="gradient-text">3 minutes</span>
                </h2>
                <p class="reveal reveal-delay-1 text-lg text-gray-600 max-w-2xl mx-auto">
                    No technical skills needed. Create your first link in seconds.
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-8 lg:gap-12 relative">
                <div class="hidden md:block absolute top-16 left-[16.6%] right-[16.6%] h-0.5 bg-gradient-to-r from-brand-200 via-purple-200 to-pink-200"></div>

                <div class="reveal reveal-delay-1 text-center relative">
                    <div class="w-16 h-16 mx-auto bg-gradient-to-br from-brand-500 to-cyan-500 rounded-2xl flex items-center justify-center text-white text-2xl font-extrabold mb-6 shadow-lg shadow-brand-500/20 relative z-10">1</div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Sign up free</h3>
                    <p class="text-gray-600 text-sm leading-relaxed">Create your account in seconds. No credit card required. Choose from Free, Pro, or Business plans.</p>
                </div>

                <div class="reveal reveal-delay-2 text-center relative">
                    <div class="w-16 h-16 mx-auto bg-gradient-to-br from-purple-500 to-violet-500 rounded-2xl flex items-center justify-center text-white text-2xl font-extrabold mb-6 shadow-lg shadow-purple-500/20 relative z-10">2</div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Create your links</h3>
                    <p class="text-gray-600 text-sm leading-relaxed">Build bio pages, shorten URLs, upload files, generate QR codes, or create contact cards — all from one dashboard.</p>
                </div>

                <div class="reveal reveal-delay-3 text-center relative">
                    <div class="w-16 h-16 mx-auto bg-gradient-to-br from-pink-500 to-rose-500 rounded-2xl flex items-center justify-center text-white text-2xl font-extrabold mb-6 shadow-lg shadow-pink-500/20 relative z-10">3</div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Share everywhere</h3>
                    <p class="text-gray-600 text-sm leading-relaxed">Add your link to social bios, print QR codes, or embed them anywhere. Track performance with real-time analytics.</p>
                </div>
            </div>

            <div class="reveal reveal-delay-4 text-center mt-12">
                <a href="{{ route('user.register') }}" class="inline-flex items-center gap-2 px-8 py-4 bg-gray-900 text-white rounded-full text-base font-semibold hover:bg-gray-800 transition-all hover:shadow-xl hover:shadow-gray-900/20 hover:-translate-y-0.5">
                    Start building now <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </section>

    <section id="pricing" class="py-20 lg:py-28 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="reveal text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight mb-4">
                    Simple, <span class="gradient-text">transparent pricing</span>
                </h2>
                <p class="reveal reveal-delay-1 text-lg text-gray-600 max-w-2xl mx-auto">
                    Start free, upgrade when you need more. No hidden fees, ever.
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-6 max-w-5xl mx-auto">
                <div class="reveal reveal-delay-1 category-card bg-white rounded-2xl p-8 border border-gray-200">
                    <div class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-2">Free</div>
                    <div class="text-4xl font-extrabold text-gray-900 mb-1">$0</div>
                    <div class="text-sm text-gray-500 mb-6">Forever free</div>
                    <ul class="space-y-3 mb-8">
                        <li class="flex items-center gap-2 text-sm text-gray-700"><i class="fas fa-check text-green-500 text-xs"></i>Up to 10 links</li>
                        <li class="flex items-center gap-2 text-sm text-gray-700"><i class="fas fa-check text-green-500 text-xs"></i>Basic analytics</li>
                        <li class="flex items-center gap-2 text-sm text-gray-700"><i class="fas fa-check text-green-500 text-xs"></i>QR code generation</li>
                        <li class="flex items-center gap-2 text-sm text-gray-700"><i class="fas fa-check text-green-500 text-xs"></i>5MB file uploads</li>
                    </ul>
                    <a href="{{ route('user.register') }}" class="block w-full py-3 text-center bg-white text-gray-900 rounded-xl text-sm font-semibold border-2 border-gray-200 hover:border-gray-300 hover:bg-gray-50 transition-all">Get started</a>
                </div>

                <div class="reveal reveal-delay-2 category-card bg-gray-900 rounded-2xl p-8 text-white relative overflow-hidden">
                    <div class="absolute top-4 right-4 px-2.5 py-0.5 bg-brand-500 text-white text-xs font-bold rounded-full">POPULAR</div>
                    <div class="text-sm font-semibold text-gray-400 uppercase tracking-wide mb-2">Pro</div>
                    <div class="text-4xl font-extrabold mb-1">$9<span class="text-lg font-medium text-gray-400">/mo</span></div>
                    <div class="text-sm text-gray-400 mb-6">For creators & professionals</div>
                    <ul class="space-y-3 mb-8">
                        <li class="flex items-center gap-2 text-sm"><i class="fas fa-check text-brand-400 text-xs"></i>Unlimited links</li>
                        <li class="flex items-center gap-2 text-sm"><i class="fas fa-check text-brand-400 text-xs"></i>Advanced analytics</li>
                        <li class="flex items-center gap-2 text-sm"><i class="fas fa-check text-brand-400 text-xs"></i>Custom domains</li>
                        <li class="flex items-center gap-2 text-sm"><i class="fas fa-check text-brand-400 text-xs"></i>50MB file uploads</li>
                        <li class="flex items-center gap-2 text-sm"><i class="fas fa-check text-brand-400 text-xs"></i>Tracking pixels</li>
                    </ul>
                    <a href="{{ route('user.register') }}" class="block w-full py-3 text-center bg-brand-500 text-white rounded-xl text-sm font-semibold hover:bg-brand-600 transition-all">Start free trial</a>
                </div>

                <div class="reveal reveal-delay-3 category-card bg-white rounded-2xl p-8 border border-gray-200">
                    <div class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-2">Business</div>
                    <div class="text-4xl font-extrabold text-gray-900 mb-1">$29<span class="text-lg font-medium text-gray-400">/mo</span></div>
                    <div class="text-sm text-gray-500 mb-6">For teams & organizations</div>
                    <ul class="space-y-3 mb-8">
                        <li class="flex items-center gap-2 text-sm text-gray-700"><i class="fas fa-check text-green-500 text-xs"></i>Everything in Pro</li>
                        <li class="flex items-center gap-2 text-sm text-gray-700"><i class="fas fa-check text-green-500 text-xs"></i>Team collaboration</li>
                        <li class="flex items-center gap-2 text-sm text-gray-700"><i class="fas fa-check text-green-500 text-xs"></i>200MB file uploads</li>
                        <li class="flex items-center gap-2 text-sm text-gray-700"><i class="fas fa-check text-green-500 text-xs"></i>API access</li>
                        <li class="flex items-center gap-2 text-sm text-gray-700"><i class="fas fa-check text-green-500 text-xs"></i>Priority support</li>
                    </ul>
                    <a href="{{ route('user.register') }}" class="block w-full py-3 text-center bg-white text-gray-900 rounded-xl text-sm font-semibold border-2 border-gray-200 hover:border-gray-300 hover:bg-gray-50 transition-all">Get started</a>
                </div>
            </div>
        </div>
    </section>

    <section class="py-20 lg:py-28">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <div class="reveal bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 rounded-3xl p-12 lg:p-16 relative overflow-hidden">
                <div class="absolute inset-0 bg-grid opacity-5"></div>
                <div class="absolute top-0 left-1/2 -translate-x-1/2 w-96 h-96 bg-brand-500 rounded-full mix-blend-soft-light filter blur-3xl opacity-20"></div>
                <div class="relative">
                    <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-white mb-4 tracking-tight">
                        Ready to simplify<br>your online presence?
                    </h2>
                    <p class="text-lg text-gray-400 mb-8 max-w-xl mx-auto">
                        Join thousands of creators and businesses who trust 1INME to manage their links.
                    </p>
                    <a href="{{ route('user.register') }}" class="inline-flex items-center gap-2 px-8 py-4 bg-white text-gray-900 rounded-full text-base font-semibold hover:bg-gray-100 transition-all hover:shadow-xl hover:-translate-y-0.5">
                        Create your free account <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <footer class="bg-gray-900 text-white pt-16 pb-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-4 gap-8 mb-12">
                <div>
                    <a href="{{ route('home') }}" class="text-2xl font-extrabold tracking-tight">
                        <span class="text-white">1IN</span><span class="text-brand-400">ME</span>
                    </a>
                    <p class="text-sm text-gray-400 mt-3 leading-relaxed">Everything you are. In one simple link. The all-in-one link management platform.</p>
                </div>
                <div>
                    <h4 class="text-sm font-semibold text-gray-300 uppercase tracking-wide mb-4">Product</h4>
                    <ul class="space-y-2.5">
                        <li><a href="#features" class="text-sm text-gray-400 hover:text-white transition-colors">Features</a></li>
                        <li><a href="#pricing" class="text-sm text-gray-400 hover:text-white transition-colors">Pricing</a></li>
                        <li><a href="#categories" class="text-sm text-gray-400 hover:text-white transition-colors">Use Cases</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-sm font-semibold text-gray-300 uppercase tracking-wide mb-4">Tools</h4>
                    <ul class="space-y-2.5">
                        <li><a href="{{ route('user.register') }}" class="text-sm text-gray-400 hover:text-white transition-colors">URL Shortener</a></li>
                        <li><a href="{{ route('user.register') }}" class="text-sm text-gray-400 hover:text-white transition-colors">Bio Link Builder</a></li>
                        <li><a href="{{ route('user.register') }}" class="text-sm text-gray-400 hover:text-white transition-colors">QR Generator</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-sm font-semibold text-gray-300 uppercase tracking-wide mb-4">Account</h4>
                    <ul class="space-y-2.5">
                        <li><a href="{{ route('user.login') }}" class="text-sm text-gray-400 hover:text-white transition-colors">Log in</a></li>
                        <li><a href="{{ route('user.register') }}" class="text-sm text-gray-400 hover:text-white transition-colors">Sign up</a></li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-gray-800 pt-8 text-center">
                <p class="text-sm text-gray-500">&copy; {{ date('Y') }} 1INME. All rights reserved.</p>
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
