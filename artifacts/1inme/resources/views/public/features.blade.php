@extends('public.layouts.site')

@section('title', $page->title ?? 'Features')

@php
    $featureName = function ($f) {
        if (!is_array($f)) return '';
        return $f['name'] ?? ($f[0] ?? '');
    };
    $featureDescription = function ($f) {
        if (!is_array($f)) return '';
        return $f['description'] ?? ($f[1] ?? '');
    };
    $showcase = [
        ['img' => asset('images/marketing/features/biolink.png'),  'label' => 'Biolink builder', 'desc' => 'Drag, drop, ship.'],
        ['img' => asset('images/marketing/features/qr-code.png'),  'label' => 'Dynamic QR',     'desc' => 'Scannable. Trackable.'],
        ['img' => asset('images/marketing/features/analytics.png'),'label' => 'Live analytics', 'desc' => 'Numbers that move.'],
    ];
@endphp

@push('head')
<style>
    html { scroll-behavior: smooth; scroll-padding-top: 5rem; }
    .feature-cat-card { transition: border-color .2s ease, transform .25s ease; }
    .feature-cat-card:hover { border-color: rgba(167,139,250,.35); transform: translateY(-2px); }
    .feature-row { border-top: 1px solid rgba(255,255,255,.06); }
    .feature-row:first-child { border-top: 0; }
    .toc-link { transition: color .15s ease, background .15s ease, transform .15s ease; }
    .toc-link:hover { color:#a78bfa; background: rgba(167,139,250,.10); transform: translateY(-1px); }
</style>
@endpush

@section('content')
{{-- HERO --}}
<section class="relative pt-20 pb-12 lg:pt-28 lg:pb-16 overflow-hidden">
    <div class="mesh-bg"></div>
    <div class="absolute inset-0 grid-bg opacity-40 pointer-events-none"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-[1.1fr_1fr] gap-10 lg:gap-14 items-center">
            <div data-anim="fade-right">
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-violet-500/10 border border-violet-400/20 text-xs text-violet-300 uppercase tracking-wider font-semibold">
                    <i class="fas fa-sparkles text-[10px]"></i> Everything 1INME can do
                </span>
                <h1 class="mt-5 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight leading-[1.05]">
                    Your link, <span class="grad-text">supercharged</span>.
                </h1>
                <p class="mt-5 text-lg text-gray-400 max-w-xl leading-relaxed">
                    {{ $page->meta_description ?? 'A complete tour of every capability inside 1INME — from your biolink and short links to inboxes, teams, billing, and beyond. No hidden lists, nothing collapsed.' }}
                </p>
                <div class="mt-7 flex flex-wrap items-center gap-3">
                    <a href="{{ route('register.page') }}" class="px-6 py-3 bg-violet-600 hover:bg-violet-700 text-white rounded-full text-sm font-bold">Start free</a>
                    <a href="#categories" class="px-5 py-3 rounded-full text-sm font-medium text-gray-200 border border-white/15 hover:bg-white/5">Browse all features</a>
                </div>
                <div class="mt-10 grid grid-cols-3 gap-6 max-w-md" data-anim="fade-up" data-stagger>
                    <div><div class="text-3xl font-bold"><span data-count="40" data-count-suffix="+"></span></div><div class="text-xs uppercase tracking-wider text-gray-500 mt-1">Block types</div></div>
                    <div><div class="text-3xl font-bold"><span data-count="14"></span></div><div class="text-xs uppercase tracking-wider text-gray-500 mt-1">Categories</div></div>
                    <div><div class="text-3xl font-bold"><span data-count="120000" data-count-suffix="+"></span></div><div class="text-xs uppercase tracking-wider text-gray-500 mt-1">Creators</div></div>
                </div>
            </div>
            <div data-anim="fade-left" data-tilt="6" class="relative">
                <div class="img-frame img-tilt aspect-[5/4]">
                    <img src="{{ asset('images/marketing/features/hero.png') }}" alt="Phone showing a 1INME biolink page">
                </div>
                <div class="absolute -top-4 -left-4 bg-[#11101c] border border-white/10 rounded-2xl p-3 flex items-center gap-2 shadow-2xl float-y">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 pulse-dot text-emerald-400/40"></span>
                    <span class="text-xs font-semibold text-gray-200">Live · 247 visitors</span>
                </div>
                <div class="absolute -bottom-5 -right-5 bg-[#11101c] border border-white/10 rounded-2xl px-4 py-3 shadow-2xl float-y" style="animation-delay:-3s">
                    <div class="text-[10px] uppercase tracking-wider text-gray-500">Top link today</div>
                    <div class="text-sm font-bold text-white mt-0.5">/new-album · <span class="text-emerald-400">+18%</span></div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- SHOWCASE STRIP --}}
<section class="pb-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid md:grid-cols-3 gap-5" data-anim="fade-up" data-stagger>
            @foreach($showcase as $s)
                <div class="group relative" data-tilt="4">
                    <div class="img-frame img-tilt aspect-[4/3]">
                        <img src="{{ $s['img'] }}" alt="{{ $s['label'] }} preview">
                    </div>
                    <div class="absolute bottom-4 left-4 right-4 z-10">
                        <div class="text-sm font-bold text-white">{{ $s['label'] }}</div>
                        <div class="text-xs text-gray-300">{{ $s['desc'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- TOC --}}
<section id="categories" class="pb-12">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-5 sm:p-6" data-anim="fade-up">
            <div class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-3">Jump to a category</div>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2" data-stagger>
                @foreach($categories as $cat)
                    <a href="#cat-{{ $cat['id'] }}" class="toc-link flex items-center gap-2 px-3 py-2 rounded-lg text-sm text-gray-300">
                        <i class="fas {{ $cat['icon'] }} text-violet-400 w-4 text-center"></i>
                        <span class="truncate">{{ $cat['heading'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- CATEGORIES --}}
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pb-20 space-y-10">
    @foreach($categories as $i => $cat)
        <section id="cat-{{ $cat['id'] }}" class="feature-cat-card bg-white/[0.03] border border-white/10 rounded-2xl p-6 sm:p-10" data-anim="fade-up">
            <div class="flex items-start gap-4 mb-6">
                <div class="shrink-0 w-12 h-12 rounded-xl bg-gradient-to-br from-violet-500/30 to-fuchsia-500/20 border border-violet-400/30 flex items-center justify-center">
                    <i class="fas {{ $cat['icon'] }} text-violet-300 text-lg"></i>
                </div>
                <div class="min-w-0">
                    <div class="text-xs font-semibold uppercase tracking-wider text-violet-300/80 mb-1">Category {{ $i + 1 }}</div>
                    <h2 class="text-2xl sm:text-3xl font-bold text-white">{{ $cat['heading'] }}</h2>
                    <p class="mt-2 text-gray-400 leading-relaxed max-w-3xl">{{ $cat['intro'] }}</p>
                </div>
            </div>
            <div class="rounded-xl border border-white/5 bg-black/10 overflow-hidden">
                @foreach($cat['features'] as $feat)
                    @php $featLink = is_array($feat) ? trim((string) ($feat['link'] ?? '')) : ''; @endphp
                    <div class="feature-row grid grid-cols-1 md:grid-cols-3 gap-2 md:gap-6 px-5 py-4">
                        <div class="md:col-span-1">
                            <div class="flex items-start gap-2">
                                <i class="fas fa-circle-check text-violet-400 mt-1 text-sm"></i>
                                <div class="font-semibold text-white">
                                    @if($featLink !== '')
                                        <a href="{{ $featLink }}" class="hover:text-violet-300 underline-offset-4 hover:underline">{{ $featureName($feat) }}</a>
                                    @else
                                        {{ $featureName($feat) }}
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="md:col-span-2 text-gray-400 text-sm leading-relaxed md:pt-0.5">
                            {{ $featureDescription($feat) }}
                            @if($featLink !== '')
                                <a href="{{ $featLink }}" class="ml-1 text-violet-300 hover:text-violet-200 font-semibold">Learn more <i class="fas fa-arrow-right text-[10px]"></i></a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach
</div>

@php
    $__featuresTestimonials = (array) \App\Modules\Admin\Models\AppSetting::get('marketing_features_testimonials', []);
    if (empty($__featuresTestimonials)) {
        $__featuresTestimonials = \App\Modules\Common\Support\SitePagesContent::testimonialsDefault();
    }
@endphp
@include('public.partials.testimonials', [
    'testimonials' => $__featuresTestimonials,
    'eyebrow' => 'What people say',
    'heading' => 'Teams and creators ship faster with 1INME.',
])

{{-- CTA --}}
<section class="pb-12">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grad-border rounded-3xl p-8 sm:p-12 text-center relative overflow-hidden" data-anim="fade-up">
            <div class="mesh-bg opacity-50"></div>
            <div class="relative">
                <h3 class="text-3xl sm:text-4xl font-bold tracking-tight">Ready to put it all to work?</h3>
                <p class="mt-3 text-gray-400 max-w-2xl mx-auto">Spin up your biolink, drop in your first link, and explore every feature on this page from your dashboard.</p>
                <div class="mt-7 flex flex-wrap items-center justify-center gap-3">
                    @auth
                        <a href="{{ route('user.dashboard') }}" class="px-7 py-3 bg-violet-600 hover:bg-violet-700 text-white rounded-full text-sm font-bold">Open dashboard</a>
                    @else
                        <a href="{{ route('register.page') }}" class="px-7 py-3 bg-violet-600 hover:bg-violet-700 text-white rounded-full text-sm font-bold">Get started free</a>
                        <a href="{{ route('site.how-it-works') }}" class="px-6 py-3 border border-white/15 text-gray-200 rounded-full text-sm font-semibold hover:bg-white/5">See how it works</a>
                    @endauth
                </div>
            </div>
        </div>
    </div>
</section>

@include('public.blogs.partials.latest-cta')

@include('public.partials.newsletter-cta', [
    'heading' => 'Want a heads-up when we ship new features?',
    'subtext' => 'Join the 1INME newsletter for product updates, growth playbooks for creators, and the occasional template — once a month.',
    'source'  => 'features-cta',
])
@endsection
