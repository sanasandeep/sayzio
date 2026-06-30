@extends('public.layouts.site')
@section('title', 'Analytics & Insights')

@php
    $accent = '#1bd4d9';
    $features = [
        [
            'icon'  => 'fa-globe',
            'title' => 'Live geo heatmap & visitor pins',
            'desc'  => 'Watch visitors land on your Link in Bio in real time on a world map, with city-level heat density so you instantly see where your audience is right now — perfect for timing posts, drops and AMAs.',
        ],
        [
            'icon'  => 'fa-camera',
            'title' => 'Snapshot share',
            'desc'  => 'One-click share a styled, branded snapshot of any analytics view — clicks, geo, top followers — straight to a client, a sponsor, or your own socials. No screenshots, no hand-cropping.',
        ],
        [
            'icon'  => 'fa-bolt',
            'title' => 'Performance Coach',
            'desc'  => 'A score, a trend line, and a prioritised list of what to fix next — every recommendation is one-click actionable: rewrite a headline, add a missing block, or A/B test a layout in seconds.',
        ],
        [
            'icon'  => 'fa-users',
            'title' => 'Followers tab & cohort retention',
            'desc'  => 'See who is following, who is sticking around, and how each weekly cohort compares — retention curves built right into the dashboard so you spot churn before it bites.',
        ],
        [
            'icon'  => 'fa-file-csv',
            'title' => 'Top Followers CSV export',
            'desc'  => 'Export your most engaged followers as CSV for outreach, gifting, sponsorship pitches or paid ads lookalikes — yours to keep, no platform lock-in.',
        ],
    ];
    $faqAnchors = [
        ['q' => 'What analytics do I get on the Free plan?',     'href' => route('site.faqs') . '#cat-analytics-ai-coach'],
        ['q' => 'How does the AI Performance Coach work?',       'href' => route('site.faqs') . '#cat-analytics-ai-coach'],
        ['q' => 'Can I see who is visiting in real time?',       'href' => route('site.faqs') . '#cat-analytics-ai-coach'],
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
                      style="background: {{ $accent }}1a; border-color: {{ $accent }}33; color: {{ $accent }};">
                    <i class="fas fa-chart-line text-[10px]"></i> Analytics & Insights
                </span>
                <h1 class="mt-5 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight leading-[1.05]">
                    See exactly what works.
                    <span class="block grad-text">Then fix what doesn’t — in one click.</span>
                </h1>
                <p class="mt-5 text-lg text-gray-400 max-w-xl leading-relaxed">
                    Live visitor pins, a geo heatmap, snapshot sharing, the AI Performance Coach, follower retention cohorts, and CSV exports of your top followers — every number you need to grow, in one dashboard.
                </p>
                <div class="mt-7 flex flex-wrap items-center gap-3">
                    @guest
                        <a href="{{ route('register.page') }}" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-full text-sm font-bold inline-flex items-center gap-2">
                            <i class="fas fa-rocket text-xs"></i> Start tracking free
                        </a>
                    @else
                        <a href="{{ route('user.dashboard') }}" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-full text-sm font-bold inline-flex items-center gap-2">
                            <i class="fas fa-rocket text-xs"></i> Go to your dashboard
                        </a>
                    @endguest
                    <a href="{{ route('site.features') }}#cat-analytics" class="px-5 py-3 rounded-full text-sm font-medium text-gray-200 border border-white/15 hover:bg-white/5">
                        See every analytics feature
                    </a>
                </div>
            </div>
            <div data-anim="fade-left" data-tilt="6" class="relative">
                <div class="img-frame img-tilt aspect-[16/10] flex items-center justify-center"
                     style="background:{{ $accent }}1f;">
                    <i class="fas fa-chart-line text-[120px] opacity-80" style="color: {{ $accent }};"></i>
                </div>
                <div class="absolute -bottom-6 -left-6 bg-[#11101c] border border-white/10 rounded-2xl p-4 flex items-center gap-3 shadow-2xl float-y">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white"
                         style="background: {{ $accent }};">
                        <i class="fas fa-map-location-dot"></i>
                    </div>
                    <div>
                        <div class="text-sm font-semibold text-white">247 visitors right now</div>
                        <div class="text-xs text-gray-400">12 countries · live pins</div>
                    </div>
                </div>
                <div class="absolute -top-5 -right-4 bg-[#11101c] border border-white/10 rounded-2xl p-3 flex items-center gap-2 shadow-2xl float-y" style="animation-delay:-3s">
                    <span class="w-2.5 h-2.5 rounded-full pulse-dot" style="background: {{ $accent }};"></span>
                    <span class="text-xs font-semibold text-gray-200">Coach score · 87</span>
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

{{-- WORKS WITH YOUR PLAN --}}
<section class="pb-20">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grad-border rounded-3xl p-8 sm:p-10 relative overflow-hidden grid md:grid-cols-[1fr_auto] gap-6 items-center" data-anim="fade-up">
            <div class="mesh-bg opacity-50"></div>
            <div class="relative">
                <div class="text-xs font-bold uppercase tracking-[.2em] mb-2" style="color: {{ $accent }};">Works with your plan</div>
                <h3 class="text-2xl sm:text-3xl font-bold tracking-tight">Core analytics free for life. Coach &amp; cohort retention on paid plans.</h3>
                <p class="mt-3 text-gray-400 max-w-2xl">Real-time visitor count, geo, devices and per-block click-through stay free forever. The Performance Coach, snapshot share, retention cohorts and Top Followers CSV unlock on a paid plan.</p>
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
            <div class="text-xs font-bold uppercase tracking-[.2em] mb-3" style="color: {{ $accent }};">FAQ</div>
            <h3 class="text-2xl sm:text-3xl font-bold tracking-tight">Common questions about <span class="grad-text">analytics</span>.</h3>
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

{{-- CTA BAND --}}
<section class="pb-24">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grad-border rounded-3xl p-8 sm:p-12 text-center relative overflow-hidden" data-anim="fade-up">
            <div class="mesh-bg opacity-50"></div>
            <div class="relative">
                <h3 class="text-3xl sm:text-4xl font-bold tracking-tight">Stop guessing. <span class="grad-text">Start measuring.</span></h3>
                <p class="mt-4 text-gray-300 max-w-2xl mx-auto">Spin up your free Sayzio, drop in your first link, and watch the live numbers roll in inside a minute.</p>
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
    'heading' => 'Get analytics tips when we ship them.',
    'subtext' => 'Once-a-month playbooks on what is moving the needle for creators on Sayzio — pick email, WhatsApp Channel, or DM.',
    'source'  => 'analytics',
])
@endsection
