@php
    $useModal    = $useModal ?? false;
    // When true the header overlays page content (used by the home page so its
    // hero/aurora treatment is preserved). Otherwise it sticks in normal flow.
    $fixed       = $fixed ?? false;
    $homeUrl     = route('home');
    // On the home page, "Pricing" should anchor-scroll to the in-page section.
    // On every other page, it should navigate to the dedicated /pricing page.
    $pricingHref = request()->routeIs('home') ? '#pricing' : route('site.pricing');
@endphp
<div x-data="{ mobileOpen:false, openMenu:null {{ $useModal ? ', authOpen:false, authTab:\'login\'' : '' }} }"
     @keydown.escape.window="openMenu=null; mobileOpen=false"{!! $useModal ? ' @open-auth.window="authTab = ($event.detail && $event.detail.tab) || \'register\'; authOpen = true; mobileOpen = false"' : '' !!}>
<nav class="{{ $fixed ? 'fixed' : 'sticky' }} top-0 inset-x-0 {{ $fixed ? 'z-50' : 'z-40' }} bg-[#1e2330]/90 backdrop-blur-xl border-b border-white/5" style="top: var(--inme-anno-h, 0px);">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            {{-- Brand --}}
            <a href="{{ route('home') }}" class="inline-flex items-center" aria-label="1INME home">
                @include('common.partials.brand-logo', ['height' => 'h-9'])
            </a>

            {{-- Desktop nav --}}
            <div class="hidden lg:flex items-center gap-x-1 xl:gap-x-2 min-w-0 flex-1 justify-center px-4"
                 @click.outside="openMenu=null">

                {{-- Product dropdown --}}
                <div class="relative">
                    <button type="button"
                            @click="openMenu === 'product' ? openMenu=null : openMenu='product'"
                            :aria-expanded="openMenu === 'product'"
                            class="inline-flex items-center gap-1 px-3 py-2 text-sm text-gray-300 hover:text-violet-400 rounded-lg whitespace-nowrap">
                        Product <i class="fas fa-chevron-down text-[10px] opacity-70" :class="openMenu === 'product' ? 'rotate-180' : ''"></i>
                    </button>
                    <div x-show="openMenu === 'product'" x-cloak
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="absolute left-0 top-full mt-2 w-72 rounded-2xl border border-white/10 bg-[#1e2330] shadow-2xl shadow-black/40 p-2 z-[60]">
                        <a href="{{ route('site.features') }}" class="flex items-start gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5">
                            <i class="fas fa-bolt text-violet-400 mt-1"></i>
                            <span><span class="block text-sm font-semibold text-white">Features</span><span class="block text-xs text-gray-500">Everything 1INME can do</span></span>
                        </a>
                        <a href="{{ route('site.how-it-works') }}" class="flex items-start gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5">
                            <i class="fas fa-play text-violet-400 mt-1"></i>
                            <span><span class="block text-sm font-semibold text-white">How it works</span><span class="block text-xs text-gray-500">Step-by-step setup</span></span>
                        </a>
                        <a href="{{ route('site.analytics') }}" class="flex items-start gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5">
                            <i class="fas fa-chart-line text-violet-400 mt-1"></i>
                            <span><span class="block text-sm font-semibold text-white">Analytics</span><span class="block text-xs text-gray-500">Live geo, coach &amp; cohorts</span></span>
                        </a>
                        <a href="{{ route('site.audience') }}" class="flex items-start gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5">
                            <i class="fas fa-users text-violet-400 mt-1"></i>
                            <span><span class="block text-sm font-semibold text-white">Audience</span><span class="block text-xs text-gray-500">Followers, digest emails &amp; directory</span></span>
                        </a>
                        <a href="{{ route('site.integrations') }}" class="flex items-start gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5">
                            <i class="fas fa-plug text-violet-400 mt-1"></i>
                            <span><span class="block text-sm font-semibold text-white">Integrations</span><span class="block text-xs text-gray-500">One-click social connections</span></span>
                        </a>
                        <a href="{{ route('site.domains') }}" class="flex items-start gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5">
                            <i class="fas fa-globe text-violet-400 mt-1"></i>
                            <span><span class="block text-sm font-semibold text-white">Domains &amp; aliases</span><span class="block text-xs text-gray-500">Branded domains &amp; custom slugs</span></span>
                        </a>
                        <a href="{{ route('site.workspace-team') }}" class="flex items-start gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5">
                            <i class="fas fa-people-group text-violet-400 mt-1"></i>
                            <span><span class="block text-sm font-semibold text-white">Workspace &amp; Team</span><span class="block text-xs text-gray-500">Roles, permissions, audit logs</span></span>
                        </a>
                        <a href="{{ route('site.api-docs') }}" class="flex items-start gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5">
                            <i class="fas fa-code text-violet-400 mt-1"></i>
                            <span><span class="block text-sm font-semibold text-white">API</span><span class="block text-xs text-gray-500">Build with 1INME</span></span>
                        </a>
                        <div class="my-1 border-t border-white/5"></div>
                        <div class="px-3 pt-1.5 pb-1 text-[10px] font-bold uppercase tracking-wider text-gray-500">AI suite</div>
                        <a href="{{ route('site.ai-chatbot') }}" class="flex items-start gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5">
                            <i class="fas fa-comments text-violet-400 mt-1"></i>
                            <span><span class="block text-sm font-semibold text-white">AI Chatbot</span><span class="block text-xs text-gray-500">24/7 chat on your biolink</span></span>
                        </a>
                        <a href="{{ route('site.ai-agent') }}" class="flex items-start gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5">
                            <i class="fas fa-robot text-violet-400 mt-1"></i>
                            <span><span class="block text-sm font-semibold text-white">AI Agent</span><span class="block text-xs text-gray-500">Runs multi-step tasks for you</span></span>
                        </a>
                        <a href="{{ route('site.ai-widget') }}" class="flex items-start gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5">
                            <i class="fas fa-window-restore text-violet-400 mt-1"></i>
                            <span><span class="block text-sm font-semibold text-white">AI Widget</span><span class="block text-xs text-gray-500">Embed on any website</span></span>
                        </a>
                        <a href="{{ route('site.ai-voice-assistant') }}" class="flex items-start gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5">
                            <i class="fas fa-headset text-violet-400 mt-1"></i>
                            <span><span class="block text-sm font-semibold text-white">AI Voice Assistant</span><span class="block text-xs text-gray-500">Picks up calls in your voice</span></span>
                        </a>
                        <div class="my-1 border-t border-white/5"></div>
                        <div class="px-3 pt-1.5 pb-1 text-[10px] font-bold uppercase tracking-wider text-gray-500">Career</div>
                        <a href="{{ route('site.resume-builder') }}" class="flex items-start gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5">
                            <i class="fas fa-file-lines text-violet-400 mt-1"></i>
                            <span><span class="block text-sm font-semibold text-white">Résumé &amp; Portfolio</span><span class="block text-xs text-gray-500">Build a CV &amp; portfolio link in 5 min</span></span>
                        </a>
                    </div>
                </div>

                {{-- Solutions dropdown --}}
                <div class="relative">
                    <button type="button"
                            @click="openMenu === 'solutions' ? openMenu=null : openMenu='solutions'"
                            :aria-expanded="openMenu === 'solutions'"
                            class="inline-flex items-center gap-1 px-3 py-2 text-sm text-gray-300 hover:text-violet-400 rounded-lg whitespace-nowrap">
                        Solutions <i class="fas fa-chevron-down text-[10px] opacity-70" :class="openMenu === 'solutions' ? 'rotate-180' : ''"></i>
                    </button>
                    <div x-show="openMenu === 'solutions'" x-cloak
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="absolute left-0 top-full mt-2 w-72 rounded-2xl border border-white/10 bg-[#1e2330] shadow-2xl shadow-black/40 p-2 z-[60]">
                        <a href="{{ route('site.services') }}" class="flex items-start gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5">
                            <i class="fas fa-bullseye text-pink-400 mt-1"></i>
                            <span><span class="block text-sm font-semibold text-white">Use cases</span><span class="block text-xs text-gray-500">For creators, brands, agencies &amp; teams</span></span>
                        </a>
                        <a href="{{ route('site.compare.index') }}" class="flex items-start gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5">
                            <i class="fas fa-scale-balanced text-pink-400 mt-1"></i>
                            <span><span class="block text-sm font-semibold text-white">Compare 1INME</span><span class="block text-xs text-gray-500">vs Linktree, Beacons, Bitly &amp; more</span></span>
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
                        <a href="{{ route('site.features') }}#cat-events" class="flex items-start gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5">
                            <i class="fas fa-calendar-day text-pink-400 mt-1"></i>
                            <span><span class="block text-sm font-semibold text-white">Events &amp; RSVPs</span><span class="block text-xs text-gray-500">Run launches with one link</span></span>
                        </a>
                        <a href="{{ route('site.features') }}#cat-referrals" class="flex items-start gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5">
                            <i class="fas fa-gift text-pink-400 mt-1"></i>
                            <span><span class="block text-sm font-semibold text-white">Referrals</span><span class="block text-xs text-gray-500">Reward fans who spread the word</span></span>
                        </a>
                        <div class="my-1 border-t border-white/5"></div>
                        <div class="px-3 pt-1.5 pb-1 text-[10px] font-bold uppercase tracking-wider text-gray-500">1INME for…</div>
                        @foreach(\App\Modules\Common\Support\SitePagesContent::useCaseMeta() as $__ucSlug => $__ucMeta)
                            <a href="{{ route('site.use-case', $__ucSlug) }}" class="flex items-start gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5">
                                <i class="fas {{ $__ucMeta['icon'] }} text-pink-400 mt-1"></i>
                                <span><span class="block text-sm font-semibold text-white">{{ $__ucMeta['eyebrow'] }}</span><span class="block text-xs text-gray-500">{{ $__ucMeta['nav_desc'] ?? $__ucMeta['tagline'] }}</span></span>
                            </a>
                        @endforeach
                    </div>
                </div>

                <a href="{{ route('site.features') }}" class="px-3 py-2 text-sm text-gray-300 hover:text-violet-400 whitespace-nowrap">Features</a>
                <a href="{{ $pricingHref }}" class="px-3 py-2 text-sm text-gray-300 hover:text-violet-400 whitespace-nowrap">Pricing</a>
                <a href="{{ route('site.premium-features') }}" class="px-3 py-2 text-sm text-gray-300 hover:text-violet-400 whitespace-nowrap">Premium</a>
                <a href="{{ route('site.about') }}" class="px-3 py-2 text-sm text-gray-300 hover:text-violet-400 whitespace-nowrap">About</a>
                <a href="{{ route('site.contact') }}" class="px-3 py-2 text-sm text-gray-300 hover:text-violet-400 whitespace-nowrap">Contact</a>
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
                    <a href="{{ route('user.dashboard') }}" class="px-6 py-2.5 bg-[#7c3aed] text-white rounded-full text-sm font-bold hover:bg-[#6d28d9]">Dashboard</a>
                @else
                    @if($useModal)
                        <button type="button" @click="authTab='login'; authOpen=true" class="px-4 py-2 text-sm font-medium text-gray-300 hover:text-white">Login</button>
                        <button type="button" @click="authTab='register'; authOpen=true" class="px-6 py-2.5 bg-[#7c3aed] text-white rounded-full text-sm font-bold hover:bg-[#6d28d9]">Register</button>
                    @else
                        <a href="{{ route('login.page') }}" class="px-4 py-2 text-sm font-medium text-gray-300 hover:text-white">Login</a>
                        <a href="{{ route('register.page') }}" class="px-6 py-2.5 bg-[#7c3aed] text-white rounded-full text-sm font-bold hover:bg-[#6d28d9]">Register</a>
                    @endif
                @endauth
            </div>

            <div class="lg:hidden flex items-center gap-2">
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

        {{-- Mobile menu --}}
        <div x-show="mobileOpen" x-cloak class="lg:hidden pb-4 border-t border-white/10 mt-2 pt-3 space-y-1">
            <div class="px-3 pt-1 pb-1 text-[10px] font-bold uppercase tracking-wider text-gray-500">Product</div>
            <a href="{{ route('site.features') }}" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">Features</a>
            <a href="{{ route('site.how-it-works') }}" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">How it works</a>
            <a href="{{ route('site.analytics') }}" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">Analytics</a>
            <a href="{{ route('site.audience') }}" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">Audience</a>
            <a href="{{ route('site.integrations') }}" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">Integrations</a>
            <a href="{{ route('site.domains') }}" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">Domains &amp; aliases</a>
            <a href="{{ route('site.workspace-team') }}" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">Workspace &amp; Team</a>
            <a href="{{ route('site.api-docs') }}" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">API</a>

            <div class="px-3 pt-3 pb-1 text-[10px] font-bold uppercase tracking-wider text-gray-500">AI suite</div>
            <a href="{{ route('site.ai-chatbot') }}" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">AI Chatbot</a>
            <a href="{{ route('site.ai-agent') }}" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">AI Agent</a>
            <a href="{{ route('site.ai-widget') }}" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">AI Widget</a>
            <a href="{{ route('site.ai-voice-assistant') }}" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">AI Voice Assistant</a>
            <a href="{{ route('site.resume-builder') }}" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">Résumé &amp; Portfolio</a>

            <div class="px-3 pt-3 pb-1 text-[10px] font-bold uppercase tracking-wider text-gray-500">Solutions</div>
            <a href="{{ route('site.services') }}" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">Use cases</a>
            <a href="{{ route('site.compare.index') }}" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">Compare 1INME</a>
            <a href="{{ route('site.discovery') }}" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">Discover creators</a>
            <a href="{{ route('site.creators-feed') }}" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">Creators feed</a>
            <a href="{{ route('site.buzz') }}" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">Buzz</a>
            <a href="{{ route('site.features') }}#cat-events" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">Events &amp; RSVPs</a>
            <a href="{{ route('site.features') }}#cat-referrals" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">Referrals</a>

            <div class="px-3 pt-3 pb-1 text-[10px] font-bold uppercase tracking-wider text-gray-500">1INME for…</div>
            @foreach(\App\Modules\Common\Support\SitePagesContent::useCaseMeta() as $__ucSlug => $__ucMeta)
                <a href="{{ route('site.use-case', $__ucSlug) }}" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">{{ $__ucMeta['eyebrow'] }}</a>
            @endforeach

            <div class="px-3 pt-3 pb-1 text-[10px] font-bold uppercase tracking-wider text-gray-500">Company</div>
            <a href="{{ $pricingHref }}" @click="mobileOpen=false" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">Pricing</a>
            <a href="{{ route('site.premium-features') }}" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">Premium features</a>
            <a href="{{ route('site.about') }}" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">About</a>
            <a href="{{ route('site.contact') }}" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">Contact</a>
            <a href="{{ route('site.faqs') }}" class="block px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-white/5">FAQs</a>

            <div class="pt-3 border-t border-white/10 space-y-2">
                @auth
                    <a href="{{ route('user.dashboard') }}" class="block px-4 py-2.5 bg-[#7c3aed] text-white rounded-lg text-sm font-bold text-center">Dashboard</a>
                @else
                    @if($useModal)
                        <button type="button" @click="authTab='login'; authOpen=true; mobileOpen=false" class="w-full text-left px-4 py-2 text-sm text-gray-300">Login</button>
                        <button type="button" @click="authTab='register'; authOpen=true; mobileOpen=false" class="block w-full px-4 py-2.5 bg-[#7c3aed] text-white rounded-lg text-sm font-bold text-center">Register</button>
                    @else
                        <a href="{{ route('login.page') }}" class="block px-4 py-2 text-sm text-gray-300">Login</a>
                        <a href="{{ route('register.page') }}" class="block px-4 py-2.5 bg-[#7c3aed] text-white rounded-lg text-sm font-bold text-center">Register</a>
                    @endif
                @endauth
            </div>
        </div>
    </div>
</nav>
@if($useModal)
    @include('public.partials.auth-modal')
@endif
</div>
