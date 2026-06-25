@extends('public.layouts.site')
@section('title', 'Notifications')

@php
    $accent = '#1bd4d9';
    $features = [
        [
            'icon'  => 'fa-bell',
            'title' => 'One unified notification feed',
            'desc'  => 'New followers, direct messages, orders, mentions and system events all land in a single feed. Mark everything read at once, or dismiss items and restore them from a 30-day history.',
        ],
        [
            'icon'  => 'fa-sliders',
            'title' => 'Per-event preferences',
            'desc'  => 'A full preferences matrix across 20+ event types. Switch in-app, email and mobile push on or off independently for each one — you decide exactly what reaches you and how.',
        ],
        [
            'icon'  => 'fa-tower-broadcast',
            'title' => 'In-app, email & mobile push',
            'desc'  => 'Real-time in-app alerts, transactional emails, and mobile push that deep-links you straight to the action — a new order, a reply, or a security warning.',
        ],
        [
            'icon'  => 'fa-calendar-day',
            'title' => 'Digests on your schedule',
            'desc'  => 'Get weekly activity and follower digests delivered on the exact day and hour you choose, in your own timezone — so the round-up lands when you actually read it.',
        ],
        [
            'icon'  => 'fa-shield-halved',
            'title' => 'Security & health alerts',
            'desc'  => 'Stay ahead of trouble with alerts for suspicious logins, broken social connections, custom-domain DNS drift and developer API usage thresholds.',
        ],
        [
            'icon'  => 'fa-arrow-pointer',
            'title' => 'Tap straight to the action',
            'desc'  => 'Every notification carries context, so a tap takes you right to the message, order or link that needs you — no hunting through the app.',
        ],
    ];
    $faqAnchors = [
        ['q' => 'Which events can I get notified about?',  'href' => route('site.faqs')],
        ['q' => 'Can I turn off email but keep in-app?',   'href' => route('site.faqs')],
        ['q' => 'When do digest emails arrive?',           'href' => route('site.faqs')],
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
                      style="background: {{ $accent }}1a; border-color: {{ $accent }}33; color: {{ $accent }};">
                    <i class="fas fa-bell text-[10px]"></i> Notifications
                </span>
                <h1 class="mt-5 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight leading-[1.05]">
                    Never miss what matters.
                    <span class="block grad-text">You choose how you hear it.</span>
                </h1>
                <p class="mt-5 text-lg text-gray-400 max-w-xl leading-relaxed">
                    A unified notification feed plus in-app, email and mobile push alerts — with a per-event preferences matrix so every follower, order, mention or security alert reaches you exactly the way you want.
                </p>
                <div class="mt-7 flex flex-wrap items-center gap-3">
                    <a href="{{ route('register.page') }}" class="px-6 py-3 bg-violet-600 hover:bg-violet-700 text-white rounded-full text-sm font-bold inline-flex items-center gap-2">
                        <i class="fas fa-rocket text-xs"></i> Get started free
                    </a>
                    <a href="{{ route('site.features') }}" class="px-5 py-3 rounded-full text-sm font-medium text-gray-200 border border-white/15 hover:bg-white/5">
                        See all features
                    </a>
                </div>
            </div>
            <div data-anim="fade-left" data-tilt="6" class="relative">
                <div class="img-frame img-tilt aspect-[16/10] flex items-center justify-center"
                     style="background:{{ $accent }}1f;">
                    <i class="fas fa-bell text-[120px] opacity-80" style="color: {{ $accent }};"></i>
                </div>
                <div class="absolute -bottom-6 -left-6 bg-[#11101c] border border-white/10 rounded-2xl p-4 flex items-center gap-3 shadow-2xl float-y">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white"
                         style="background: {{ $accent }};">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div>
                        <div class="text-sm font-semibold text-white">3 new followers</div>
                        <div class="text-xs text-gray-400">In-app · push delivered</div>
                    </div>
                </div>
                <div class="absolute -top-5 -right-4 bg-[#11101c] border border-white/10 rounded-2xl p-3 flex items-center gap-2 shadow-2xl float-y" style="animation-delay:-3s">
                    <span class="w-2.5 h-2.5 rounded-full pulse-dot" style="background: {{ $accent }};"></span>
                    <span class="text-xs font-semibold text-gray-200">Digest · Mon 9am</span>
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
                <div class="text-xs font-bold uppercase tracking-[.2em] mb-2" style="color: {{ $accent }};">Built into every plan</div>
                <h3 class="text-2xl sm:text-3xl font-bold tracking-tight">In-app and email alerts for everyone. Scheduling &amp; advanced alerts on paid plans.</h3>
                <p class="mt-3 text-gray-400 max-w-2xl">Every account gets the unified feed with in-app and email notifications. Digest scheduling and advanced delivery options unlock as you grow.</p>
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
            <div class="text-xs font-bold uppercase tracking-[.2em] mb-3" style="color: {{ $accent }};">FAQ</div>
            <h3 class="text-2xl sm:text-3xl font-bold tracking-tight">Common questions about <span class="grad-text">notifications</span>.</h3>
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
                <a href="{{ route('site.faqs') }}" class="text-sm font-semibold" style="color: {{ $accent }};">See all FAQs <i class="fas fa-arrow-right text-[10px]"></i></a>
            </div>
        </div>
    </div>
</section>

<section class="pb-24">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grad-border rounded-3xl p-8 sm:p-12 text-center relative overflow-hidden" data-anim="fade-up">
            <div class="mesh-bg opacity-50"></div>
            <div class="relative">
                <h3 class="text-3xl sm:text-4xl font-bold tracking-tight">Stay in the loop, <span class="grad-text">on your terms.</span></h3>
                <p class="mt-4 text-gray-300 max-w-2xl mx-auto">Spin up a free Sayzio, set your notification preferences once, and let the right alerts find you on the right channel.</p>
                <div class="mt-7 flex flex-wrap items-center justify-center gap-3">
                    <a href="{{ route('register.page') }}" class="px-7 py-3 bg-violet-600 hover:bg-violet-700 text-white rounded-full text-sm font-bold">Get started free</a>
                    <a href="{{ route('login.page') }}" class="px-6 py-3 rounded-full text-sm font-medium text-gray-200 border border-white/15 hover:bg-white/5">Log in</a>
                    <a href="{{ route('site.features') }}" class="px-6 py-3 rounded-full text-sm font-medium text-gray-200 border border-white/15 hover:bg-white/5">Explore all features</a>
                </div>
            </div>
        </div>
    </div>
</section>

@include('public.partials.subscribe-block', [
    'heading' => 'Product updates, the moment they ship.',
    'subtext' => 'Once-a-month notes on what is new on Sayzio — email, WhatsApp Channel, or DM, your call.',
    'source'  => 'notifications',
])
@endsection
