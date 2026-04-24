@extends('public.layouts.site')

@section('title', $page->title ?? 'What you can do with 1INME')

@section('content')
@php
    $heroTitle    = $page->title ?? 'What you can do with 1INME';
    $heroSubtitle = $page->meta_description ?? 'One link, endless setups. Pick the scenario that sounds like you and see how 1INME fits — then spin up your own in a couple of minutes.';
    $bottomCtaLabel = $page->cta_label ?? 'Create your 1INME';
    $bottomCtaUrl   = $page->cta_url   ?? '/register';
    $images = [
        asset('images/marketing/services/creator.png'),
        asset('images/marketing/services/agency.png'),
        asset('images/marketing/services/ecommerce.png'),
        asset('images/marketing/services/coach.png'),
    ];
@endphp

{{-- HERO --}}
<section class="relative pt-20 pb-12 lg:pt-28 lg:pb-16 overflow-hidden">
    <div class="mesh-bg"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid lg:grid-cols-2 gap-10 items-center">
        <div data-anim="fade-right">
            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-medium bg-white/5 border border-white/10 text-violet-300 uppercase tracking-wider">
                <i class="fas fa-sparkles text-[10px]"></i> Use cases
            </span>
            <h1 class="mt-5 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight leading-[1.05]">
                {{ $heroTitle }}
            </h1>
            <p class="mt-5 text-lg text-gray-400 max-w-xl leading-relaxed">
                {{ $heroSubtitle }}
            </p>
            <div class="mt-7 flex flex-wrap items-center gap-3">
                <a href="{{ route('register.page') }}" class="px-6 py-3 bg-violet-600 hover:bg-violet-700 text-white rounded-full text-sm font-bold">Get started free</a>
                <a href="{{ route('site.features') }}" class="px-5 py-3 rounded-full text-sm font-medium text-gray-200 border border-white/15 hover:bg-white/5">See all features</a>
            </div>
        </div>
        <div data-anim="fade-left" class="relative">
            <div class="grid grid-cols-2 gap-3" data-stagger>
                <div class="img-frame aspect-[4/5]" data-tilt="4"><img src="{{ $images[0] }}" alt="Content creator at work"></div>
                <div class="img-frame aspect-[4/5] mt-8" data-tilt="4"><img src="{{ $images[1] }}" alt="Marketing agency team"></div>
                <div class="img-frame aspect-[4/5] -mt-6" data-tilt="4"><img src="{{ $images[2] }}" alt="Small shop owner packing orders"></div>
                <div class="img-frame aspect-[4/5] mt-2" data-tilt="4"><img src="{{ $images[3] }}" alt="Online coach on a video call"></div>
            </div>
        </div>
    </div>
</section>

{{-- USE CASE GRID --}}
<section class="pb-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6" data-anim="fade-up" data-stagger>
            @foreach($useCases as $i => $uc)
                <article class="relative group bg-white/[0.03] border border-white/10 rounded-2xl overflow-hidden flex flex-col hover:border-violet-400/40 hover:-translate-y-1 transition-all duration-300">
                    <div class="img-frame rounded-none border-0 aspect-[16/10]">
                        <img src="{{ $images[$i % count($images)] }}" alt="{{ $uc['title'] ?? '1INME use case' }}">
                    </div>
                    <div class="relative flex-1 flex flex-col p-6 sm:p-7">
                        <div class="absolute -top-7 left-6 w-12 h-12 rounded-xl bg-gradient-to-br {{ $uc['tint'] }} border border-white/15 flex items-center justify-center text-white text-lg shadow-2xl">
                            <i class="fas {{ $uc['icon'] }}"></i>
                        </div>
                        <h2 class="mt-4 text-xl font-bold text-white">{{ $uc['title'] }}</h2>
                        @if(!empty($uc['tagline']))
                            <p class="mt-1 text-sm font-medium text-violet-300">{{ $uc['tagline'] }}</p>
                        @endif
                        @if(!empty($uc['desc']))
                            <p class="mt-3 text-sm text-gray-400 leading-relaxed">{{ $uc['desc'] }}</p>
                        @endif
                        @if(!empty($uc['bullets']))
                            <ul class="mt-4 space-y-2 text-sm text-gray-300">
                                @foreach($uc['bullets'] as $b)
                                    <li class="flex items-start gap-2">
                                        <i class="fas fa-check text-violet-400 mt-1 text-xs"></i>
                                        <span>{{ $b }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        <div class="mt-6 pt-5 border-t border-white/10">
                            <a href="{{ $uc['cta_url'] }}" class="inline-flex items-center gap-2 text-sm font-semibold text-violet-300 hover:text-white">
                                {{ $uc['cta_label'] }} <i class="fas fa-arrow-right text-xs transition-transform group-hover:translate-x-1"></i>
                            </a>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-14 grad-border rounded-3xl p-8 sm:p-10 text-center relative overflow-hidden" data-anim="fade-up">
            <div class="mesh-bg opacity-50"></div>
            <div class="relative">
                <h2 class="text-2xl sm:text-3xl font-bold">Not sure which fits?</h2>
                <p class="mt-3 text-gray-300 max-w-2xl mx-auto text-sm sm:text-base">
                    Start free and switch your setup any time — most people end up using 1INME for two or three of these at once.
                </p>
                <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                    <a href="{{ $bottomCtaUrl }}" class="px-6 py-3 bg-violet-600 hover:bg-violet-700 text-white rounded-full text-sm font-bold">{{ $bottomCtaLabel }}</a>
                    <a href="{{ route('site.contact') }}" class="px-5 py-3 rounded-full text-sm font-medium text-gray-200 border border-white/15 hover:bg-white/5">Talk to us</a>
                </div>
            </div>
        </div>
    </div>
</section>

@include('public.partials.subscribe-block', [
    'heading' => 'See what 1INME ships next.',
    'subtext' => 'Use cases, templates, and playbooks for your industry — straight to email, WhatsApp Channel, or DM.',
    'source'  => 'services',
])
@endsection
