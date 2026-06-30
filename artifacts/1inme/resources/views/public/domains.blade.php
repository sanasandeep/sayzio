@extends('public.layouts.site')
@section('title', 'Domains & URL Aliases')

@php
    $accent = '#3d6bff';
    // Live, admin-managed branded domains (shared by the GlobalDomainsComposer);
    // falls back to a static branded list when none are configured.
    $globalDomains = $showcaseDomains ?? \App\Modules\User\Models\Domain::SHOWCASE_FALLBACK;
    // Natural-language "a, b, c or d" phrasing for the feature blurb.
    $globalDomainsPhrase = count($globalDomains) > 1
        ? implode(', ', array_slice($globalDomains, 0, -1)) . ' or ' . end($globalDomains)
        : ($globalDomains[0] ?? '');
    $features = [
        [
            'icon'  => 'fa-layer-group',
            'title' => 'Multiple branded global domains',
            'desc'  => 'Skip the DNS setup entirely. Pick one of our shared, branded domains when you create a link or Link in Bio — ' . $globalDomainsPhrase . ' — and you’re live instantly with a clean, memorable URL.',
        ],
        [
            'icon'  => 'fa-globe',
            'title' => 'Bring your own custom domain',
            'desc'  => 'Connect a personal or brand domain like links.yourbrand.com so every link looks 100% you. Point one CNAME record at us, we verify it automatically, and your domain is ready to attach to links — no token juggling.',
        ],
        [
            'icon'  => 'fa-tags',
            'title' => 'Custom URL aliases',
            'desc'  => 'Every link gets a memorable primary slug, and you can add multiple extra aliases that all open the same page with no redirect — perfect for campaign variants, typos you want to catch, and channel-specific URLs.',
        ],
        [
            'icon'  => 'fa-shield-halved',
            'title' => 'Verified & secured',
            'desc'  => 'Custom domains are verified by a simple CNAME check before they go live, and connection health stays visible so you always know which domains are ready to serve your links.',
        ],
    ];
    $faqAnchors = [
        ['q' => 'Which domains can I use for free?',          'href' => route('site.faqs') . '#cat-domains-aliases'],
        ['q' => 'How do I connect my own custom domain?',     'href' => route('site.faqs') . '#cat-domains-aliases'],
        ['q' => 'Can one link have more than one URL?',       'href' => route('site.faqs') . '#cat-domains-aliases'],
    ];
@endphp

@section('content')
{{-- HERO --}}
<section class="relative pt-20 pb-16 lg:pt-28 lg:pb-20 overflow-hidden">
    <div class="mesh-bg"></div>
    <div class="absolute inset-0 grid-bg opacity-50 pointer-events-none"></div>
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-2 gap-10 lg:gap-14 items-center">
            <div data-anim="fade-right">
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider border"
                      style="background: {{ $accent }}1a; border-color: {{ $accent }}33; color: #bccfff;">
                    <i class="fas fa-globe text-[10px]"></i> Domains &amp; URL aliases
                </span>
                <h1 class="mt-5 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight leading-[1.05]">
                    Pick a domain that fits.
                    <span class="block grad-text">Or bring your own.</span>
                </h1>
                <p class="mt-5 text-lg text-gray-400 max-w-xl leading-relaxed">
                    Launch on one of our branded shared domains, connect your own custom domain with a single CNAME record, or give any link a memorable slug — with multiple aliases pointing at the same page.
                </p>
                <div class="mt-7 flex flex-wrap items-center gap-3">
                    @guest
                        <a href="{{ route('register.page') }}" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-full text-sm font-bold inline-flex items-center gap-2">
                            <i class="fas fa-rocket text-xs"></i> Claim your link free
                        </a>
                    @else
                        <a href="{{ route('user.dashboard') }}" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-full text-sm font-bold inline-flex items-center gap-2">
                            <i class="fas fa-rocket text-xs"></i> Go to your dashboard
                        </a>
                    @endguest
                    <a href="{{ route('site.pricing') }}" class="px-5 py-3 rounded-full text-sm font-medium text-gray-200 border border-white/15 hover:bg-white/5">
                        See custom-domain plans
                    </a>
                </div>
                <div class="mt-8 flex flex-wrap items-center gap-3" data-anim="fade-up" data-stagger>
                    @foreach($globalDomains as $dom)
                        <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/5 border border-white/10 text-xs text-gray-300">
                            <i class="fas fa-globe text-sm" style="color: #bccfff;"></i>
                            {{ $dom }}
                        </span>
                    @endforeach
                </div>
            </div>
            <div data-anim="fade-left" data-tilt="6" class="relative">
                <div class="img-frame img-tilt aspect-[16/10] flex items-center justify-center"
                     style="background:{{ $accent }}1f;">
                    <i class="fas fa-globe text-[120px] opacity-80" style="color: #bccfff;"></i>
                </div>
                <div class="absolute -bottom-6 -left-6 bg-[#11101c] border border-white/10 rounded-2xl p-4 flex items-center gap-3 shadow-2xl float-y">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white"
                         style="background: {{ $accent }};">
                        <i class="fas fa-circle-check"></i>
                    </div>
                    <div>
                        <div class="text-sm font-semibold text-white">links.yourbrand.com</div>
                        <div class="text-xs text-gray-400">Verified · auto-SSL</div>
                    </div>
                </div>
                <div class="absolute -top-5 -right-4 bg-[#11101c] border border-white/10 rounded-2xl p-3 flex items-center gap-2 shadow-2xl float-y" style="animation-delay:-3s">
                    <span class="w-2.5 h-2.5 rounded-full pulse-dot" style="background: #bccfff;"></span>
                    <span class="text-xs font-semibold text-gray-200">3 aliases · 1 page</span>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- FEATURES --}}
<section class="relative pb-20">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid md:grid-cols-2 gap-5">
            @foreach($features as $i => $f)
                <article class="glass rounded-3xl p-7 lift relative overflow-hidden" data-anim="fade-up" data-stagger>
                    <div class="absolute -top-12 -right-12 w-40 h-40 rounded-full opacity-20"
                         style="background: {{ $accent }};"></div>
                    <div class="relative w-11 h-11 rounded-2xl flex items-center justify-center mb-4 text-white"
                         style="background: {{ $accent }}; box-shadow: 0 12px 30px -12px {{ $accent }};">
                        <i class="fas {{ $f['icon'] }}"></i>
                    </div>
                    <h2 class="relative text-xl font-bold mb-3 leading-snug">{{ $f['title'] }}</h2>
                    <p class="relative text-sm text-gray-300 leading-relaxed">{{ $f['desc'] }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>

{{-- GLOBAL DOMAINS SHOWCASE --}}
<section class="pb-20">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-8" data-anim="fade-up">
            <div class="text-xs font-bold uppercase tracking-[.2em] mb-3" style="color: #bccfff;">Branded domains</div>
            <h3 class="text-2xl sm:text-3xl font-bold tracking-tight">Choose a domain that <span class="grad-text">matches your vibe</span>.</h3>
            <p class="mt-3 text-gray-400 max-w-2xl mx-auto">Every account can build on one of our shared, branded domains — no purchase, no DNS, no waiting. Just pick one and your link is live.</p>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4" data-anim="fade-up" data-stagger>
            @foreach($globalDomains as $dom)
                <div class="glass rounded-2xl p-6 text-center lift">
                    <div class="w-11 h-11 mx-auto rounded-2xl flex items-center justify-center mb-3 text-white"
                         style="background: {{ $accent }}; box-shadow: 0 12px 30px -12px {{ $accent }};">
                        <i class="fas fa-globe"></i>
                    </div>
                    <div class="text-base font-bold text-white break-all">{{ $dom }}</div>
                    <div class="mt-1 text-xs text-gray-500 font-mono break-all">{{ strtolower($dom) }}/you</div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- URL ALIASES DEEP DIVE --}}
<section class="pb-20">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="glass rounded-3xl p-8 sm:p-10 relative overflow-hidden grid md:grid-cols-2 gap-8 items-center" data-anim="fade-up">
            <div class="relative">
                <div class="text-xs font-bold uppercase tracking-[.2em] mb-2" style="color: #bccfff;">One page, many doors</div>
                <h3 class="text-2xl sm:text-3xl font-bold tracking-tight">A primary slug, plus all the aliases you need.</h3>
                <p class="mt-3 text-gray-400">Set a memorable primary slug for any link, then add extra aliases that all open the very same page — with no redirect hop. Great for campaign variants, channel-specific URLs, and catching the typos people make.</p>
            </div>
            <div class="relative space-y-2.5" aria-hidden="true">
                <div class="flex items-center gap-2 px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-sm font-mono">
                    <i class="fas fa-star text-xs" style="color:#ffc845;"></i>
                    <span class="text-gray-400">1in.me/</span><span class="text-white font-semibold">spring-drop</span>
                    <span class="ml-auto text-[10px] uppercase tracking-wider text-gray-500">primary</span>
                </div>
                <div class="flex items-center gap-2 px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-sm font-mono">
                    <i class="fas fa-link text-xs" style="color:#bccfff;"></i>
                    <span class="text-gray-400">1in.me/</span><span class="text-gray-200">sale</span>
                    <span class="ml-auto text-[10px] uppercase tracking-wider text-gray-500">alias</span>
                </div>
                <div class="flex items-center gap-2 px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-sm font-mono">
                    <i class="fas fa-link text-xs" style="color:#bccfff;"></i>
                    <span class="text-gray-400">1in.me/</span><span class="text-gray-200">drop24</span>
                    <span class="ml-auto text-[10px] uppercase tracking-wider text-gray-500">alias</span>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- WORKS WITH YOUR PLAN --}}
<section class="pb-20">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grad-border rounded-3xl p-8 sm:p-10 relative overflow-hidden grid md:grid-cols-[1fr_auto] gap-6 items-center" data-anim="fade-up">
            <div class="mesh-bg opacity-50"></div>
            <div class="relative">
                <div class="text-xs font-bold uppercase tracking-[.2em] mb-2" style="color: #bccfff;">Works with your plan</div>
                <h3 class="text-2xl sm:text-3xl font-bold tracking-tight">Branded domains &amp; aliases free. Custom domains on paid plans.</h3>
                <p class="mt-3 text-gray-400 max-w-2xl">Every plan can build on our shared branded domains and add custom URL aliases. Connecting your own custom domain with CNAME verification unlocks on a paid plan.</p>
            </div>
            <div class="relative flex flex-wrap gap-3">
                <a href="{{ route('site.pricing') }}" class="px-5 py-3 rounded-full bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold whitespace-nowrap">See plans</a>
            </div>
        </div>
    </div>
</section>

{{-- FAQ TEASER --}}
<section class="pb-20">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-8" data-anim="fade-up">
            <div class="text-xs font-bold uppercase tracking-[.2em] mb-3" style="color: #bccfff;">FAQ</div>
            <h3 class="text-2xl sm:text-3xl font-bold tracking-tight">Common questions about <span class="grad-text">domains</span>.</h3>
        </div>
        <div class="space-y-3" data-anim="fade-up" data-stagger>
            @foreach($faqAnchors as $faq)
                <a href="{{ $faq['href'] }}" class="group glass rounded-2xl p-5 flex items-center justify-between gap-4 hover:border-white/20">
                    <span class="text-base font-semibold text-white">{{ $faq['q'] }}</span>
                    <span class="shrink-0 w-7 h-7 rounded-full border border-white/15 flex items-center justify-center text-gray-300 group-hover:translate-x-0.5 transition">
                        <i class="fas fa-arrow-right text-[10px]"></i>
                    </span>
                </a>
            @endforeach
            <div class="text-center pt-4">
                <a href="{{ route('site.faqs') }}" class="text-sm font-semibold" style="color: #bccfff;">See all FAQs <i class="fas fa-arrow-right text-[10px]"></i></a>
            </div>
        </div>
    </div>
</section>

{{-- CTA BAND --}}
<section class="pb-24">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grad-border rounded-3xl p-8 sm:p-12 text-center relative overflow-hidden" data-anim="fade-up">
            <div class="mesh-bg opacity-50"></div>
            <div class="relative">
                <h3 class="text-3xl sm:text-4xl font-bold tracking-tight">Your link. <span class="grad-text">Your domain.</span></h3>
                <p class="mt-4 text-gray-300 max-w-2xl mx-auto">Spin up a free Sayzio, pick a branded domain, and add a memorable slug in under a minute — then connect your own domain whenever you’re ready.</p>
                <div class="mt-7 flex flex-wrap items-center justify-center gap-3">
                    @guest
                        <a href="{{ route('register.page') }}" class="px-7 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-full text-sm font-bold">Get started free</a>
                    @else
                        <a href="{{ route('user.dashboard') }}" class="px-7 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-full text-sm font-bold">Go to your dashboard</a>
                    @endguest
                    <a href="{{ route('site.features') }}" class="px-6 py-3 rounded-full text-sm font-medium text-gray-200 border border-white/15 hover:bg-white/5">See all features</a>
                </div>
            </div>
        </div>
    </div>
</section>

@include('public.partials.subscribe-block', [
    'heading' => 'New domain options when we add them.',
    'subtext' => 'A short note when we ship a new branded domain or improve custom-domain tooling — pick email, WhatsApp Channel, or DM.',
    'source'  => 'domains',
])
@endsection
