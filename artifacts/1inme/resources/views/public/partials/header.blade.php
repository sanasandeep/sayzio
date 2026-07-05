@php
    $useModal    = $useModal ?? false;
    // When true the header overlays page content (used by the home page so its
    // hero/aurora treatment is preserved). Otherwise it sticks in normal flow.
    $fixed       = $fixed ?? false;
    $homeUrl     = route('home');
    // On the home page, "Pricing" should anchor-scroll to the in-page section.
    // On every other page, it should navigate to the dedicated /pricing page.
    $pricingHref = request()->routeIs('home') ? '#pricing' : route('site.pricing');

    // Mega-menu link groups. [href, fa-icon, title, one-line description].
    // Plain `&` in titles/descriptions — echoed via {{ }} so Blade escapes them.
    $navProductCore = [
        [route('site.features'),       'fa-bolt',         'Features',           'Everything Sayzio can do'],
        [route('site.how-it-works'),   'fa-play',         'How it works',       'Step-by-step setup'],
        [route('site.analytics'),      'fa-chart-line',   'Analytics',          'Live geo, coach & cohorts'],
        [route('site.audience'),       'fa-users',        'Audience',           'Followers, digest emails & directory'],
        [route('site.integrations'),   'fa-plug',         'Integrations',       'One-click social connections'],
        [route('site.domains'),        'fa-globe',        'Domains & aliases',  'Branded domains & custom slugs'],
        [route('site.forms'),          'fa-list-check',   'Form Builder',       '21 field types & instant alerts'],
        [route('site.notifications'),  'fa-bell',         'Notifications',      'In-app, email & push, your way'],
        [route('site.workspace-team'), 'fa-people-group', 'Workspace & Team',   'Roles, permissions, audit logs'],
        [route('site.api-docs'),       'fa-code',         'API',                'Build with Sayzio'],
    ];
    $navProductAi = [
        [route('site.ai-marketing-strategist'), 'fa-wand-magic-sparkles', 'AI Marketing Strategist', 'A growth plan from your own data'],
        [route('site.ai-chatbot'),         'fa-comments',        'AI Chatbot',         '24/7 chat on your biolink'],
        [route('site.ai-agent'),           'fa-robot',           'AI Agent',           'Runs multi-step tasks for you'],
        [route('site.ai-widget'),          'fa-window-restore',  'AI Widget',          'Embed on any website'],
        [route('site.ai-voice-assistant'), 'fa-headset',         'AI Voice Assistant', 'Picks up calls in your voice'],
        [route('site.whatsapp-agent'),     'fa-comment-dots',    'WhatsApp Agent',     'Build links by chatting on WhatsApp'],
        [route('site.ai-dashboard'),       'fa-gauge-high',      'AI Dashboard',       'Presets or a prompt build your layout'],
    ];
    $navProductCareer = [
        [route('site.resume-builder'), 'fa-file-lines', 'Résumé & Portfolio', 'Build a CV & portfolio link in 5 min'],
    ];
    $navSolutions = [
        [route('site.services'),                    'fa-bullseye',            'Use cases',              'For creators, brands, agencies & teams'],
        [route('site.compare.index'),               'fa-scale-balanced',      'Compare Sayzio',          'vs Linktree, Beacons, Bitly & more'],
        [route('site.demos'),                       'fa-wand-magic-sparkles', 'See what you can build', 'Live demo of every link type'],
        [route('site.discovery'),                   'fa-compass',             'Discover creators',      'Browse the public directory'],
        [route('site.creators-feed'),               'fa-stream',              'Creators feed',          'What the community is shipping'],
        [route('site.buzz'),                        'fa-bullhorn',            'Buzz',                   'News, press & love'],
        [route('events.index'),                     'fa-calendar-day',        'Events & RSVPs',         'Run launches with one link'],
        [route('site.features').'#cat-referrals',   'fa-gift',                'Referrals',              'Reward fans who spread the word'],
    ];
    $navUseCases = \App\Modules\Common\Support\SitePagesContent::useCaseMeta();
@endphp
<div x-data="{ mobileOpen:false, mobileGroup:null, openMenu:null, scrolled:false {{ $useModal ? ', authOpen:false, authTab:\'login\', authHandle:\'\'' : '' }} }"
     {{-- For the in-flow sticky header (non-home pages) the Alpine wrapper must
          NOT form a containing block, or the sticky <nav> would be trapped in a
          box only as tall as itself and unstick immediately. `display:contents`
          dissolves this wrapper so the nav's containing block becomes the body,
          giving it the full scrollable page to stay pinned across. The fixed
          (home) header is positioned out of flow, so it keeps a normal box. --}}
     @if(! $fixed) style="display: contents;" @endif
     x-init="scrolled = window.scrollY > 8"
     @scroll.window.passive="scrolled = window.scrollY > 8"
     x-effect="document.body.style.overflow = (mobileOpen{{ $useModal ? ' || authOpen' : '' }}) ? 'hidden' : ''"
     @keydown.escape.window="openMenu=null; mobileOpen=false; mobileGroup=null"{!! $useModal ? ' @open-auth.window="authTab = ($event.detail && $event.detail.tab) || \'register\'; authHandle = ($event.detail && $event.detail.handle) || \'\'; authOpen = true; mobileOpen = false"' : '' !!}>
<nav class="{{ $fixed ? 'fixed' : 'sticky' }} top-0 inset-x-0 {{ $fixed ? 'z-50' : 'z-40' }}" style="top: var(--inme-anno-h, 0px);">
    <div class="mkt-navbar-bar" :class="scrolled ? 'is-stuck' : ''">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="relative flex items-center justify-between h-16">
            {{-- Brand (desktop wordmark) --}}
            <a href="{{ route('home') }}" class="hidden lg:inline-flex items-center" aria-label="Sayzio home">
                @include('common.partials.brand-logo', ['height' => 'h-9'])
            </a>

            {{-- Brand (mobile centered icon) --}}
            <a href="{{ route('home') }}" class="lg:hidden absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 inline-flex items-center" aria-label="Sayzio home">
                @include('common.partials.brand-logo', ['variant' => 'icon', 'height' => 'h-9'])
            </a>

            {{-- Desktop nav --}}
            <div class="hidden lg:flex items-center gap-x-1 xl:gap-x-2 min-w-0 flex-1 justify-center px-4 relative"
                 @click.outside="openMenu=null"
                 @mouseleave="openMenu=null">

                {{-- Product trigger --}}
                <button type="button"
                        @click="openMenu === 'product' ? openMenu=null : openMenu='product'"
                        @mouseenter="openMenu='product'"
                        :aria-expanded="openMenu === 'product'"
                        class="inline-flex items-center gap-1 px-3 py-2 text-sm rounded-lg whitespace-nowrap transition-colors"
                        :class="openMenu === 'product' ? 'text-blue-400' : 'text-gray-300 hover:text-blue-400'">
                    Product <i class="fas fa-chevron-down text-[10px] opacity-70 transition-transform" :class="openMenu === 'product' ? 'rotate-180' : ''"></i>
                </button>

                {{-- Solutions trigger --}}
                <button type="button"
                        @click="openMenu === 'solutions' ? openMenu=null : openMenu='solutions'"
                        @mouseenter="openMenu='solutions'"
                        :aria-expanded="openMenu === 'solutions'"
                        class="inline-flex items-center gap-1 px-3 py-2 text-sm rounded-lg whitespace-nowrap transition-colors"
                        :class="openMenu === 'solutions' ? 'text-blue-400' : 'text-gray-300 hover:text-blue-400'">
                    Solutions <i class="fas fa-chevron-down text-[10px] opacity-70 transition-transform" :class="openMenu === 'solutions' ? 'rotate-180' : ''"></i>
                </button>

                <a href="{{ route('site.features') }}" @mouseenter="openMenu=null" class="px-3 py-2 text-sm text-gray-300 hover:text-blue-400 whitespace-nowrap">Features</a>
                <a href="{{ $pricingHref }}" @mouseenter="openMenu=null" class="px-3 py-2 text-sm text-gray-300 hover:text-blue-400 whitespace-nowrap">Pricing</a>
                <a href="{{ route('site.about') }}" @mouseenter="openMenu=null" class="px-3 py-2 text-sm text-gray-300 hover:text-blue-400 whitespace-nowrap">About</a>
                <a href="{{ route('site.contact') }}" @mouseenter="openMenu=null" class="px-3 py-2 text-sm text-gray-300 hover:text-blue-400 whitespace-nowrap">Contact</a>

                {{-- ============ Product mega panel ============ --}}
                {{-- Outer wrapper has no visible chrome and a top padding "bridge" so
                     the cursor can travel from the trigger to the panel without
                     crossing a dead zone (which would trip @mouseleave). Centered via
                     left-0/right-0 + mx-auto so it never overflows the viewport edge.
                     Enter-only x-transition (leave shorthand is buggy in this app). --}}
                <div x-show="openMenu === 'product'" x-cloak
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 -translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     class="absolute left-0 right-0 mx-auto top-full w-[min(56rem,calc(100vw-2rem))] pt-3 z-[60]">
                    <div class="rounded-2xl border border-white/10 bg-[#1e2330] shadow-2xl shadow-black/40 overflow-hidden">
                        <span aria-hidden class="block h-1 w-full bg-gradient-to-r from-blue-500 via-fuchsia-500 to-blue-500"></span>
                        <div class="grid grid-cols-[1.9fr_1fr_minmax(12rem,0.95fr)] gap-5 p-5">
                            {{-- Core product (two-up) --}}
                            <div>
                                <div class="px-1 pb-2 text-[10px] font-bold uppercase tracking-wider text-gray-500">Core product</div>
                                <div class="grid grid-cols-2 gap-1">
                                    @foreach($navProductCore as [$__href, $__icon, $__title, $__desc])
                                        <a href="{{ $__href }}" class="group flex items-start gap-3 px-3 py-2.5 rounded-xl hover:bg-white/5 transition-colors">
                                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-500/10 text-blue-300 transition-transform group-hover:scale-110">
                                                <i class="fas {{ $__icon }} text-sm"></i>
                                            </span>
                                            <span class="min-w-0">
                                                <span class="block text-sm font-semibold text-white">{{ $__title }}</span>
                                                <span class="block text-xs leading-snug text-gray-500">{{ $__desc }}</span>
                                            </span>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                            {{-- AI suite + Career --}}
                            <div>
                                <div class="px-1 pb-2 text-[10px] font-bold uppercase tracking-wider text-gray-500">AI suite</div>
                                <div class="space-y-0.5">
                                    @foreach($navProductAi as [$__href, $__icon, $__title, $__desc])
                                        <a href="{{ $__href }}" class="group flex items-start gap-3 px-3 py-2.5 rounded-xl hover:bg-white/5 transition-colors">
                                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-500/10 text-blue-300 transition-transform group-hover:scale-110">
                                                <i class="fas {{ $__icon }} text-sm"></i>
                                            </span>
                                            <span class="min-w-0">
                                                <span class="block text-sm font-semibold text-white">{{ $__title }}</span>
                                                <span class="block text-xs leading-snug text-gray-500">{{ $__desc }}</span>
                                            </span>
                                        </a>
                                    @endforeach
                                </div>
                                <div class="px-1 pt-3 pb-2 text-[10px] font-bold uppercase tracking-wider text-gray-500">Career</div>
                                <div class="space-y-0.5">
                                    @foreach($navProductCareer as [$__href, $__icon, $__title, $__desc])
                                        <a href="{{ $__href }}" class="group flex items-start gap-3 px-3 py-2.5 rounded-xl hover:bg-white/5 transition-colors">
                                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-500/10 text-blue-300 transition-transform group-hover:scale-110">
                                                <i class="fas {{ $__icon }} text-sm"></i>
                                            </span>
                                            <span class="min-w-0">
                                                <span class="block text-sm font-semibold text-white">{{ $__title }}</span>
                                                <span class="block text-xs leading-snug text-gray-500">{{ $__desc }}</span>
                                            </span>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                            {{-- Featured --}}
                            <div class="relative overflow-hidden rounded-xl border border-blue-400/30 bg-white/5 bg-gradient-to-br from-blue-600/20 via-fuchsia-500/10 to-transparent p-5 flex flex-col">
                                <span aria-hidden class="pointer-events-none absolute -right-8 -top-8 h-28 w-28 rounded-full bg-blue-500/25 blur-2xl"></span>
                                <span class="relative inline-flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-blue-300">
                                    <i class="fas fa-sparkles"></i> What you can create
                                </span>
                                <span class="relative mt-2 block text-base font-bold leading-tight text-white">Everything in one link</span>
                                <span class="relative mt-1.5 block flex-1 text-xs leading-snug text-gray-400">Links, Link in Bio pages, QR codes, résumés and AI pages — all fully branded.</span>
                                <a href="{{ route('site.features') }}" class="relative mt-4 inline-flex items-center gap-1.5 self-start rounded-full bg-[#3d6bff] px-4 py-2 text-xs font-bold text-white hover:bg-[#2342c7] transition-colors">
                                    Explore features <i class="fas fa-arrow-right text-[10px]"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ============ Solutions mega panel ============ --}}
                <div x-show="openMenu === 'solutions'" x-cloak
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 -translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     class="absolute left-0 right-0 mx-auto top-full w-[min(56rem,calc(100vw-2rem))] pt-3 z-[60]">
                    <div class="rounded-2xl border border-white/10 bg-[#1e2330] shadow-2xl shadow-black/40 overflow-hidden">
                        <span aria-hidden class="block h-1 w-full bg-gradient-to-r from-pink-500 via-fuchsia-500 to-pink-500"></span>
                        <div class="grid grid-cols-[1.9fr_1fr_minmax(12rem,0.95fr)] gap-5 p-5">
                            {{-- Explore (two-up) --}}
                            <div>
                                <div class="px-1 pb-2 text-[10px] font-bold uppercase tracking-wider text-gray-500">Explore</div>
                                <div class="grid grid-cols-2 gap-1">
                                    @foreach($navSolutions as [$__href, $__icon, $__title, $__desc])
                                        <a href="{{ $__href }}" class="group flex items-start gap-3 px-3 py-2.5 rounded-xl hover:bg-white/5 transition-colors">
                                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-pink-500/10 text-pink-300 transition-transform group-hover:scale-110">
                                                <i class="fas {{ $__icon }} text-sm"></i>
                                            </span>
                                            <span class="min-w-0">
                                                <span class="block text-sm font-semibold text-white">{{ $__title }}</span>
                                                <span class="block text-xs leading-snug text-gray-500">{{ $__desc }}</span>
                                            </span>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                            {{-- Sayzio for… --}}
                            <div>
                                <div class="px-1 pb-2 text-[10px] font-bold uppercase tracking-wider text-gray-500">Sayzio for…</div>
                                <div class="space-y-0.5">
                                    @foreach($navUseCases as $__ucSlug => $__ucMeta)
                                        <a href="{{ route('site.use-case', $__ucSlug) }}" class="group flex items-start gap-3 px-3 py-2.5 rounded-xl hover:bg-white/5 transition-colors">
                                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-pink-500/10 text-pink-300 transition-transform group-hover:scale-110">
                                                <i class="fas {{ $__ucMeta['icon'] }} text-sm"></i>
                                            </span>
                                            <span class="min-w-0">
                                                <span class="block text-sm font-semibold text-white">{{ $__ucMeta['eyebrow'] }}</span>
                                                <span class="block text-xs leading-snug text-gray-500">{{ $__ucMeta['nav_desc'] ?? $__ucMeta['tagline'] }}</span>
                                            </span>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                            {{-- Featured --}}
                            <div class="relative overflow-hidden rounded-xl border border-pink-400/30 bg-white/5 bg-gradient-to-br from-pink-600/20 via-fuchsia-500/10 to-transparent p-5 flex flex-col">
                                <span aria-hidden class="pointer-events-none absolute -right-8 -top-8 h-28 w-28 rounded-full bg-pink-500/25 blur-2xl"></span>
                                <span class="relative inline-flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-pink-300">
                                    <i class="fas fa-compass"></i> Not sure where to start?
                                </span>
                                <span class="relative mt-2 block text-base font-bold leading-tight text-white">One link for every goal</span>
                                <span class="relative mt-1.5 block flex-1 text-xs leading-snug text-gray-400">Built for creators, brands, agencies, coaches and local business.</span>
                                <a href="{{ route('site.services') }}" class="relative mt-4 inline-flex items-center gap-1.5 self-start rounded-full bg-[#3d6bff] px-4 py-2 text-xs font-bold text-white hover:bg-[#2342c7] transition-colors">
                                    See use cases <i class="fas fa-arrow-right text-[10px]"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Auth CTAs --}}
            <div class="hidden lg:flex items-center gap-3">
                <button type="button"
                        x-data="{ light: document.documentElement.classList.contains('light-mode') }"
                        x-init="window.addEventListener('inme-theme-changed', e => light = e.detail.light)"
                        @click="light = (window.inmeToggleTheme ? window.inmeToggleTheme() : !light)"
                        class="mkt-theme-toggle"
                        :title="light ? 'Switch to dark mode' : 'Switch to light mode'"
                        :aria-label="light ? 'Switch to dark mode' : 'Switch to light mode'">
                    <i :class="light ? 'fas fa-sun' : 'fas fa-moon'" class="text-sm"></i>
                </button>
                @auth
                    <a href="{{ route('user.dashboard') }}" class="px-6 py-2.5 bg-[#3d6bff] text-white rounded-full text-sm font-bold hover:bg-[#2342c7]">Dashboard</a>
                    <div class="relative" x-data="{ acctOpen:false }" @keydown.escape="acctOpen=false">
                        <button type="button" @click="acctOpen=!acctOpen" :aria-expanded="acctOpen"
                                class="mkt-theme-toggle" title="Account" aria-label="Account menu">
                            <i class="fas fa-user text-sm"></i>
                        </button>
                        <div x-show="acctOpen" x-cloak @click.outside="acctOpen=false"
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             class="absolute right-0 mt-2 w-44 rounded-xl mkt-acct-menu border border-white/10 shadow-xl p-1.5 z-50">
                            <form action="{{ route('user.logout') }}" method="POST">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-2.5 px-3 py-2 rounded-lg text-sm text-gray-300 hover:bg-red-500/10 hover:text-red-400">
                                    <i class="fas fa-sign-out-alt w-4 text-center text-sm"></i>
                                    <span>Sign out</span>
                                </button>
                            </form>
                        </div>
                    </div>
                @else
                    @if($useModal)
                        <button type="button" @click="authTab='login'; authOpen=true" class="px-4 py-2 text-sm font-medium text-gray-300 hover:text-white">Login</button>
                        <button type="button" @click="authTab='register'; authOpen=true" class="px-6 py-2.5 bg-[#3d6bff] text-white rounded-full text-sm font-bold hover:bg-[#2342c7]">Register</button>
                    @else
                        <a href="{{ route('login.page') }}" class="px-4 py-2 text-sm font-medium text-gray-300 hover:text-white">Login</a>
                        <a href="{{ route('register.page') }}" class="px-6 py-2.5 bg-[#3d6bff] text-white rounded-full text-sm font-bold hover:bg-[#2342c7]">Register</a>
                    @endif
                @endauth
            </div>

            <div class="lg:hidden flex items-center gap-2 ml-auto">
                <button type="button"
                        x-data="{ light: document.documentElement.classList.contains('light-mode') }"
                        x-init="window.addEventListener('inme-theme-changed', e => light = e.detail.light)"
                        @click="light = (window.inmeToggleTheme ? window.inmeToggleTheme() : !light)"
                        class="mkt-theme-toggle"
                        style="width:36px;height:36px;"
                        :aria-label="light ? 'Switch to dark mode' : 'Switch to light mode'">
                    <i :class="light ? 'fas fa-sun' : 'fas fa-moon'" class="text-xs"></i>
                </button>
                <button @click="mobileOpen=!mobileOpen" class="p-2 text-gray-300" aria-label="Toggle menu" :aria-expanded="mobileOpen">
                    <i class="fas fa-bars" x-show="!mobileOpen"></i>
                    <i class="fas fa-times" x-show="mobileOpen" x-cloak></i>
                </button>
            </div>
        </div>

        {{-- Mobile menu — collapsible accordions (x-collapse plugin is bundled) --}}
        <div x-show="mobileOpen" x-cloak class="lg:hidden pb-4 border-t border-white/10 mt-2 pt-3 space-y-2 overflow-y-auto overscroll-contain"
             style="max-height: calc(100dvh - var(--inme-anno-h, 0px) - 5rem); -webkit-overflow-scrolling: touch;">

            {{-- Product --}}
            <div class="rounded-xl border border-white/10 overflow-hidden">
                <button type="button"
                        @click="mobileGroup === 'm-product' ? mobileGroup=null : mobileGroup='m-product'"
                        :aria-expanded="mobileGroup === 'm-product'"
                        class="flex w-full items-center justify-between gap-3 px-3 py-3 text-left text-sm font-semibold"
                        :class="mobileGroup === 'm-product' ? 'text-blue-400' : 'text-white'">
                    <span>Product</span>
                    <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform" :class="mobileGroup === 'm-product' ? 'rotate-180' : ''"></i>
                </button>
                <div x-show="mobileGroup === 'm-product'" x-collapse x-cloak class="px-2 pb-2 space-y-0.5">
                    @foreach(array_merge($navProductCore, $navProductAi, $navProductCareer) as [$__href, $__icon, $__title, $__desc])
                        <a href="{{ $__href }}" @click="mobileOpen=false" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-white/5">
                            <i class="fas {{ $__icon }} w-4 text-center text-blue-300 text-sm"></i>
                            <span class="text-sm text-gray-300">{{ $__title }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- Solutions --}}
            <div class="rounded-xl border border-white/10 overflow-hidden">
                <button type="button"
                        @click="mobileGroup === 'm-solutions' ? mobileGroup=null : mobileGroup='m-solutions'"
                        :aria-expanded="mobileGroup === 'm-solutions'"
                        class="flex w-full items-center justify-between gap-3 px-3 py-3 text-left text-sm font-semibold"
                        :class="mobileGroup === 'm-solutions' ? 'text-blue-400' : 'text-white'">
                    <span>Solutions</span>
                    <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform" :class="mobileGroup === 'm-solutions' ? 'rotate-180' : ''"></i>
                </button>
                <div x-show="mobileGroup === 'm-solutions'" x-collapse x-cloak class="px-2 pb-2 space-y-0.5">
                    @foreach($navSolutions as [$__href, $__icon, $__title, $__desc])
                        <a href="{{ $__href }}" @click="mobileOpen=false" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-white/5">
                            <i class="fas {{ $__icon }} w-4 text-center text-pink-300 text-sm"></i>
                            <span class="text-sm text-gray-300">{{ $__title }}</span>
                        </a>
                    @endforeach
                    <div class="px-3 pt-2 pb-1 text-[10px] font-bold uppercase tracking-wider text-gray-500">Sayzio for…</div>
                    @foreach($navUseCases as $__ucSlug => $__ucMeta)
                        <a href="{{ route('site.use-case', $__ucSlug) }}" @click="mobileOpen=false" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-white/5">
                            <i class="fas {{ $__ucMeta['icon'] }} w-4 text-center text-pink-300 text-sm"></i>
                            <span class="text-sm text-gray-300">{{ $__ucMeta['eyebrow'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- Company --}}
            <div class="rounded-xl border border-white/10 overflow-hidden">
                <button type="button"
                        @click="mobileGroup === 'm-company' ? mobileGroup=null : mobileGroup='m-company'"
                        :aria-expanded="mobileGroup === 'm-company'"
                        class="flex w-full items-center justify-between gap-3 px-3 py-3 text-left text-sm font-semibold"
                        :class="mobileGroup === 'm-company' ? 'text-blue-400' : 'text-white'">
                    <span>Company</span>
                    <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform" :class="mobileGroup === 'm-company' ? 'rotate-180' : ''"></i>
                </button>
                <div x-show="mobileGroup === 'm-company'" x-collapse x-cloak class="px-2 pb-2 space-y-0.5">
                    <a href="{{ $pricingHref }}" @click="mobileOpen=false" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-white/5"><i class="fas fa-tag w-4 text-center text-blue-300 text-sm"></i><span class="text-sm text-gray-300">Pricing</span></a>
                    <a href="{{ route('site.about') }}" @click="mobileOpen=false" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-white/5"><i class="fas fa-circle-info w-4 text-center text-blue-300 text-sm"></i><span class="text-sm text-gray-300">About</span></a>
                    <a href="{{ route('site.contact') }}" @click="mobileOpen=false" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-white/5"><i class="fas fa-envelope w-4 text-center text-blue-300 text-sm"></i><span class="text-sm text-gray-300">Contact</span></a>
                    <a href="{{ route('site.faqs') }}" @click="mobileOpen=false" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-white/5"><i class="fas fa-circle-question w-4 text-center text-blue-300 text-sm"></i><span class="text-sm text-gray-300">FAQs</span></a>
                </div>
            </div>

            <div class="pt-3 border-t border-white/10 space-y-2">
                @auth
                    <a href="{{ route('user.dashboard') }}" class="block px-4 py-2.5 bg-[#3d6bff] text-white rounded-lg text-sm font-bold text-center">Dashboard</a>
                    <form action="{{ route('user.logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="flex w-full items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold text-gray-300 border border-white/10 hover:bg-red-500/10 hover:text-red-400">
                            <i class="fas fa-sign-out-alt text-xs"></i>
                            <span>Sign out</span>
                        </button>
                    </form>
                @else
                    @if($useModal)
                        <button type="button" @click="authTab='login'; authOpen=true; mobileOpen=false" class="w-full text-left px-4 py-2 text-sm text-gray-300">Login</button>
                        <button type="button" @click="authTab='register'; authOpen=true; mobileOpen=false" class="block w-full px-4 py-2.5 bg-[#3d6bff] text-white rounded-lg text-sm font-bold text-center">Register</button>
                    @else
                        <a href="{{ route('login.page') }}" class="block px-4 py-2 text-sm text-gray-300">Login</a>
                        <a href="{{ route('register.page') }}" class="block px-4 py-2.5 bg-[#3d6bff] text-white rounded-lg text-sm font-bold text-center">Register</a>
                    @endif
                @endauth
            </div>
        </div>
    </div>
    </div>
</nav>
@if($useModal)
    @include('public.partials.auth-modal')
@endif
</div>
