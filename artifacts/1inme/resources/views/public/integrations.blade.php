@extends('public.layouts.site')
@section('title', 'Social Integrations')

@php
    $accent = '#7c3aed';
    $networks = [
        ['icon' => 'fa-instagram',  'name' => 'Instagram',  'color' => '#e1306c'],
        ['icon' => 'fa-tiktok',     'name' => 'TikTok',     'color' => '#69c9d0'],
        ['icon' => 'fa-facebook',   'name' => 'Facebook',   'color' => '#1877f2'],
        ['icon' => 'fa-x-twitter',  'name' => 'X (Twitter)','color' => '#ffffff'],
        ['icon' => 'fa-linkedin',   'name' => 'LinkedIn',   'color' => '#0a66c2'],
        ['icon' => 'fa-pinterest',  'name' => 'Pinterest',  'color' => '#e60023'],
    ];
    $features = [
        [
            'icon'  => 'fa-plug',
            'title' => 'One-click connect, on every network',
            'desc'  => 'Tap a button, approve once, done. No copy-pasting tokens, no fragile manual setup, and no developer required to wire up Instagram, TikTok, Facebook, X, LinkedIn or Pinterest.',
        ],
        [
            'icon'  => 'fa-arrows-rotate',
            'title' => 'Auto-retry on broken connections',
            'desc'  => 'When a token expires or a platform forces a re-auth, we keep trying with smart back-off and only ping you when we actually need a one-click reconnect — no silent failures.',
        ],
        [
            'icon'  => 'fa-circle-check',
            'title' => 'Status visibility you can trust',
            'desc'  => 'Every connection shows a live "healthy / needs reconnect / paused" status with a last-synced timestamp, so you always know exactly which networks are publishing and which need attention.',
        ],
        [
            'icon'  => 'fa-bell',
            'title' => 'Notifications when something breaks',
            'desc'  => 'In-app, email and (optionally) push alerts the moment a connection goes stale — with a one-tap reconnect flow that takes seconds to clear.',
        ],
    ];
    $faqAnchors = [
        ['q' => 'How do I connect my Instagram account?',                'href' => route('site.faqs') . '#cat-audience-events-referrals'],
        ['q' => 'What happens when a token expires?',                    'href' => route('site.faqs') . '#cat-audience-events-referrals'],
        ['q' => 'Can my agency manage many social connections at once?', 'href' => route('site.faqs') . '#cat-audience-events-referrals'],
    ];
@endphp

@section('content')
<section class="relative pt-20 pb-16 lg:pt-28 lg:pb-20 overflow-hidden">
    <div class="mesh-bg"></div>
    <div class="absolute inset-0 grid-bg opacity-50 pointer-events-none"></div>
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-2 gap-10 lg:gap-14 items-center">
            <div data-anim="fade-right">
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider border"
                      style="background: {{ $accent }}1a; border-color: {{ $accent }}33; color: #c4b5fd;">
                    <i class="fas fa-plug text-[10px]"></i> Social Integrations
                </span>
                <h1 class="mt-5 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight leading-[1.05]">
                    Every social network.
                    <span class="block grad-text">Connected in one click.</span>
                </h1>
                <p class="mt-5 text-lg text-gray-400 max-w-xl leading-relaxed">
                    Plug in Instagram, TikTok, Facebook, X, LinkedIn and Pinterest with a single tap. Connections auto-retry when tokens expire, status is always visible, and you get a notification the moment something needs your attention.
                </p>
                <div class="mt-7 flex flex-wrap items-center gap-3">
                    <a href="{{ route('register.page') }}" class="px-6 py-3 bg-violet-600 hover:bg-violet-700 text-white rounded-full text-sm font-bold inline-flex items-center gap-2">
                        <i class="fas fa-rocket text-xs"></i> Connect your accounts
                    </a>
                    <a href="{{ route('site.features') }}#cat-integrations" class="px-5 py-3 rounded-full text-sm font-medium text-gray-200 border border-white/15 hover:bg-white/5">
                        See all integrations
                    </a>
                </div>
                <div class="mt-8 flex flex-wrap items-center gap-3" data-anim="fade-up" data-stagger>
                    @foreach($networks as $n)
                        <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/5 border border-white/10 text-xs text-gray-300">
                            <i class="fa-brands {{ $n['icon'] }} text-sm" style="color: {{ $n['color'] }};"></i>
                            {{ $n['name'] }}
                        </span>
                    @endforeach
                </div>
            </div>
            <div data-anim="fade-left" data-tilt="6" class="relative">
                <div class="img-frame img-tilt aspect-[16/10] flex items-center justify-center"
                     style="background:{{ $accent }}1f;">
                    <i class="fas fa-plug text-[120px] opacity-80" style="color: #c4b5fd;"></i>
                </div>
                <div class="absolute -bottom-6 -left-6 bg-[#11101c] border border-white/10 rounded-2xl p-4 flex items-center gap-3 shadow-2xl float-y">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white"
                         style="background: {{ $accent }};">
                        <i class="fas fa-circle-check"></i>
                    </div>
                    <div>
                        <div class="text-sm font-semibold text-white">6 of 6 healthy</div>
                        <div class="text-xs text-gray-400">Last synced · 12s ago</div>
                    </div>
                </div>
                <div class="absolute -top-5 -right-4 bg-[#11101c] border border-white/10 rounded-2xl p-3 flex items-center gap-2 shadow-2xl float-y" style="animation-delay:-3s">
                    <span class="w-2.5 h-2.5 rounded-full pulse-dot" style="background: #c4b5fd;"></span>
                    <span class="text-xs font-semibold text-gray-200">Auto-retry · on</span>
                </div>
            </div>
        </div>
    </div>
</section>

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

<section class="pb-20">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grad-border rounded-3xl p-8 sm:p-10 relative overflow-hidden grid md:grid-cols-[1fr_auto] gap-6 items-center" data-anim="fade-up">
            <div class="mesh-bg opacity-50"></div>
            <div class="relative">
                <div class="text-xs font-bold uppercase tracking-[.2em] mb-2" style="color: #c4b5fd;">Works with your plan</div>
                <h3 class="text-2xl sm:text-3xl font-bold tracking-tight">Connect socials free. Multi-account &amp; agency tooling on paid plans.</h3>
                <p class="mt-3 text-gray-400 max-w-2xl">Every plan can connect at least one of each network. Multiple accounts per network, agency client switching, and bulk reconnect tools unlock on a paid plan.</p>
            </div>
            <div class="relative flex flex-wrap gap-3">
                <a href="{{ route('site.pricing') }}" class="px-5 py-3 rounded-full bg-violet-600 hover:bg-violet-700 text-white text-sm font-bold whitespace-nowrap">See plans</a>
                <a href="{{ route('site.premium-features') }}" class="px-5 py-3 rounded-full border border-white/15 text-gray-200 hover:bg-white/5 text-sm font-semibold whitespace-nowrap">Premium features</a>
            </div>
        </div>
    </div>
</section>

<section class="pb-20">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-8" data-anim="fade-up">
            <div class="text-xs font-bold uppercase tracking-[.2em] mb-3" style="color: #c4b5fd;">FAQ</div>
            <h3 class="text-2xl sm:text-3xl font-bold tracking-tight">Common questions about <span class="grad-text">integrations</span>.</h3>
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
                <a href="{{ route('site.faqs') }}" class="text-sm font-semibold" style="color: #c4b5fd;">See all FAQs <i class="fas fa-arrow-right text-[10px]"></i></a>
            </div>
        </div>
    </div>
</section>

<section class="pb-24">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grad-border rounded-3xl p-8 sm:p-12 text-center relative overflow-hidden" data-anim="fade-up">
            <div class="mesh-bg opacity-50"></div>
            <div class="relative">
                <h3 class="text-3xl sm:text-4xl font-bold tracking-tight">Plug it in. <span class="grad-text">Forget about it.</span></h3>
                <p class="mt-4 text-gray-300 max-w-2xl mx-auto">Spin up your free 1INME, connect every network you live on, and let auto-retry keep things running while you ship.</p>
                <div class="mt-7 flex flex-wrap items-center justify-center gap-3">
                    <a href="{{ route('register.page') }}" class="px-7 py-3 bg-violet-600 hover:bg-violet-700 text-white rounded-full text-sm font-bold">Connect now — free</a>
                    <a href="{{ route('site.features') }}" class="px-6 py-3 rounded-full text-sm font-medium text-gray-200 border border-white/15 hover:bg-white/5">See all features</a>
                </div>
            </div>
        </div>
    </div>
</section>

@include('public.partials.subscribe-block', [
    'heading' => 'New integrations the moment they ship.',
    'subtext' => 'A short note when we add a network or polish an existing connector — pick email, WhatsApp Channel, or DM.',
    'source'  => 'integrations',
])
@endsection
