@extends('public.layouts.site')
@section('title', $page->title ?? 'How it works')

@section('content')
@php
    $steps = [
        [
            'n' => '01',
            'title' => 'Sign up in seconds',
            'desc'  => 'Create your free account with email or one click via Google. Pick your unique 1inme.co handle and you are live.',
            'img'   => asset('images/marketing/how-it-works/build.png'),
            'icon'  => 'fa-user-plus',
            'tags'  => ['No card required', 'Free forever plan', 'Pick a handle'],
        ],
        [
            'n' => '02',
            'title' => 'Build your one link',
            'desc'  => 'Drag-and-drop blocks for socials, music, shop, video, calendar, newsletter, contact and more. Pick a theme or design every pixel — fonts, colours, layout.',
            'img'   => asset('images/marketing/features/biolink.png'),
            'icon'  => 'fa-wand-magic-sparkles',
            'tags'  => ['40+ block types', 'Themes & custom CSS', 'Mobile preview'],
        ],
        [
            'n' => '03',
            'title' => 'Share it everywhere',
            'desc'  => 'Drop it in every bio, attach a branded short link to a campaign, generate a dynamic QR code for offline — one link, every channel.',
            'img'   => asset('images/marketing/features/qr-code.png'),
            'icon'  => 'fa-share-nodes',
            'tags'  => ['Branded short links', 'Dynamic QR codes', 'UTM autotagging'],
        ],
        [
            'n' => '04',
            'title' => 'Grow with live data',
            'desc'  => 'See exactly which links convert, where your traffic comes from, and what to fix next. The Performance Coach turns analytics into one-tap actions.',
            'img'   => asset('images/marketing/features/analytics.png'),
            'icon'  => 'fa-chart-line',
            'tags'  => ['Live analytics', 'Geo + device', 'Performance Coach'],
        ],
    ];
@endphp

{{-- ─────────────  HERO  ───────────── --}}
<section class="relative pt-20 pb-16 lg:pt-28 lg:pb-20 overflow-hidden">
    <div class="mesh-bg"></div>
    <div class="absolute inset-0 grid-bg opacity-50 pointer-events-none"></div>
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-2 gap-10 lg:gap-14 items-center">
            <div data-anim="fade-right">
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider bg-violet-500/10 border border-violet-400/20 text-violet-300">
                    <i class="fas fa-route text-[10px]"></i> {{ $page->title ?? 'How it works' }}
                </span>
                <h1 class="mt-5 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight leading-[1.05]">
                    From zero to live <span class="grad-text">in four steps</span>.
                </h1>
                <p class="mt-5 text-lg text-gray-400 max-w-xl leading-relaxed">
                    {{ $page->meta_description ?? 'You do not need a designer, a developer or a weekend. Sign up, build your one link, share it everywhere, then watch the data come in.' }}
                </p>
                <div class="mt-7 flex flex-wrap items-center gap-3">
                    <a href="{{ route('register.page') }}" class="px-6 py-3 bg-violet-600 hover:bg-violet-700 text-white rounded-full text-sm font-bold inline-flex items-center gap-2">
                        <i class="fas fa-rocket text-xs"></i> Start free
                    </a>
                    <a href="#step-01" class="px-5 py-3 rounded-full text-sm font-medium text-gray-200 border border-white/15 hover:bg-white/5">
                        Walk me through it
                    </a>
                </div>
                <div class="mt-8 flex items-center gap-6" data-anim="fade-up" data-stagger>
                    <div>
                        <div class="text-2xl font-bold text-white"><span data-count="120000" data-count-suffix="+"></span></div>
                        <div class="text-xs text-gray-500 uppercase tracking-wider">Creators on board</div>
                    </div>
                    <div class="w-px h-10 bg-white/10"></div>
                    <div>
                        <div class="text-2xl font-bold text-white"><span data-count="4.2" data-count-suffix="M+"></span></div>
                        <div class="text-xs text-gray-500 uppercase tracking-wider">Clicks last month</div>
                    </div>
                    <div class="w-px h-10 bg-white/10 hidden sm:block"></div>
                    <div class="hidden sm:block">
                        <div class="text-2xl font-bold text-white"><span data-count="99.99" data-count-suffix="%"></span></div>
                        <div class="text-xs text-gray-500 uppercase tracking-wider">Uptime</div>
                    </div>
                </div>
            </div>
            <div data-anim="fade-left" data-tilt="6" class="relative">
                <div class="img-frame img-tilt aspect-[16/10]">
                    <img src="{{ asset('images/marketing/how-it-works/hero.png') }}" alt="Diagram of the 1INME setup flow">
                </div>
                <div class="absolute -bottom-6 -left-6 bg-[#11101c] border border-white/10 rounded-2xl p-4 flex items-center gap-3 shadow-2xl float-y">
                    <div class="w-10 h-10 rounded-xl bg-emerald-500 flex items-center justify-center text-white">
                        <i class="fas fa-check"></i>
                    </div>
                    <div>
                        <div class="text-sm font-semibold text-white">Live in 2&nbsp;min</div>
                        <div class="text-xs text-gray-400">Average setup time</div>
                    </div>
                </div>
                <div class="absolute -top-5 -right-4 bg-[#11101c] border border-white/10 rounded-2xl p-3 flex items-center gap-2 shadow-2xl float-y" style="animation-delay:-3s">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 pulse-dot text-emerald-400/40"></span>
                    <span class="text-xs font-semibold text-gray-200">3 visitors right now</span>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ─────────────  STEPS  ───────────── --}}
<section class="relative pb-24">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-20">
        @foreach($steps as $i => $step)
            @php $reverse = $i % 2 === 1; @endphp
            <div id="step-{{ $step['n'] }}" class="grid lg:grid-cols-2 gap-10 lg:gap-16 items-center">
                <div class="{{ $reverse ? 'lg:order-2' : '' }}" data-anim="{{ $reverse ? 'fade-left' : 'fade-right' }}">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="text-5xl font-bold grad-text leading-none">{{ $step['n'] }}</span>
                        <div class="h-px flex-1 bg-violet-500/30"></div>
                        <i class="fas {{ $step['icon'] }} text-violet-300"></i>
                    </div>
                    <h2 class="text-3xl sm:text-4xl font-bold tracking-tight">{{ $step['title'] }}</h2>
                    <p class="mt-4 text-gray-400 leading-relaxed text-base">{{ $step['desc'] }}</p>
                    <div class="mt-5 flex flex-wrap gap-2" data-anim="fade-up" data-stagger>
                        @foreach($step['tags'] as $tag)
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-white/5 border border-white/10 text-xs text-gray-300">
                                <i class="fas fa-circle-check text-violet-400 text-[10px]"></i>{{ $tag }}
                            </span>
                        @endforeach
                    </div>
                </div>
                <div class="{{ $reverse ? 'lg:order-1' : '' }}" data-anim="{{ $reverse ? 'fade-right' : 'fade-left' }}" data-tilt="5">
                    <div class="img-frame img-tilt aspect-[4/3]">
                        <img src="{{ $step['img'] }}" alt="{{ $step['title'] }}">
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</section>

{{-- Templates & link types --}}
<section class="pb-20">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 grid lg:grid-cols-2 gap-8" data-anim="fade-up">
        <div class="grad-border rounded-3xl p-7 sm:p-8 relative overflow-hidden">
            <div class="mesh-bg opacity-50"></div>
            <div class="relative">
                <div class="text-xs font-bold uppercase tracking-[.2em] text-violet-300 mb-2">
                    <i class="fas fa-rectangle-list"></i> Templates
                </div>
                <h3 class="text-2xl sm:text-3xl font-bold tracking-tight">Skip the blank canvas.</h3>
                <p class="mt-3 text-gray-300 leading-relaxed">
                    Pick a professionally designed template, swap in your details, and you’re live in two minutes. Built for creators, brands, agencies, restaurants, coaches and more — and you can save your own as a reusable template for clients or your team.
                </p>
                <div class="mt-5">
                    <a href="{{ route('site.features') }}#cat-templates" class="inline-flex items-center gap-1.5 text-sm font-semibold text-violet-300 hover:text-white">
                        Browse templates <i class="fas fa-arrow-right text-[10px]"></i>
                    </a>
                </div>
            </div>
        </div>
        <div class="glass rounded-3xl p-7 sm:p-8 relative overflow-hidden">
            <div class="text-xs font-bold uppercase tracking-[.2em] text-cyan-300 mb-2">
                <i class="fas fa-link"></i> Friendly link types
            </div>
            <h3 class="text-2xl sm:text-3xl font-bold tracking-tight">Plain-English link types.</h3>
            <p class="mt-3 text-gray-300 leading-relaxed">
                No jargon — just pick what the link should do. Every type is named for what it actually does for your visitor.
            </p>
            <ul class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-2.5 text-sm text-gray-300">
                @foreach([
                    ['fa-arrow-up-right-from-square', 'Send to a website'],
                    ['fa-file-arrow-down',            'Download a file'],
                    ['fa-image',                      'Show a splash page'],
                    ['fa-mobile-screen',              'Open an app'],
                    ['fa-address-card',               'Save my contact (vCard)'],
                    ['fa-calendar-plus',              'Add to calendar (.ics)'],
                    ['fa-wifi',                       'Join my Wi-Fi'],
                    ['fa-shuffle',                    'Pick one of many (A/B)'],
                ] as [$ic, $label])
                    <li class="flex items-start gap-2.5">
                        <span class="mt-0.5 w-6 h-6 rounded-md flex items-center justify-center text-cyan-300 flex-shrink-0" style="background:rgba(34,211,238,.1);">
                            <i class="fas {{ $ic }} text-[11px]"></i>
                        </span>
                        <span>{{ $label }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</section>

{{-- ─────────────  CTA BAND  ───────────── --}}
<section class="pb-24">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grad-border rounded-3xl p-8 sm:p-12 text-center relative overflow-hidden" data-anim="fade-up">
            <div class="mesh-bg opacity-50"></div>
            <div class="relative">
                <h3 class="text-3xl sm:text-4xl font-bold tracking-tight">Your link in <span class="grad-text">two minutes</span>.</h3>
                <p class="mt-4 text-gray-300 max-w-2xl mx-auto">No card, no commitment. Spin up your 1INME, drop in a few links, and start tracking what works.</p>
                <div class="mt-7 flex flex-wrap items-center justify-center gap-3">
                    <a href="{{ route('register.page') }}" class="px-7 py-3 bg-violet-600 hover:bg-violet-700 text-white rounded-full text-sm font-bold">Create my 1INME</a>
                    <a href="{{ route('site.features') }}" class="px-6 py-3 rounded-full text-sm font-medium text-gray-200 border border-white/15 hover:bg-white/5">See all features</a>
                </div>
            </div>
        </div>
    </div>
</section>

@include('public.blogs.partials.latest-cta')

@include('public.partials.subscribe-block', [
    'heading' => 'Get tips and templates the way you like them.',
    'subtext' => 'Once-a-month notes on what is working for creators on 1INME — pick email, WhatsApp Channel, or 1:1 DM. Actionable, no fluff, opt out any time.',
    'source'  => 'how-it-works',
])
@endsection
