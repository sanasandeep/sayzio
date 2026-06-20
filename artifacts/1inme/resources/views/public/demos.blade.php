@extends('public.layouts.site')

@section('title', 'See what you can build')

@section('content')
{{-- HERO --}}
<section class="relative pt-20 pb-10 lg:pt-28 lg:pb-12 overflow-hidden">
    <div class="mesh-bg"></div>
    <div class="absolute inset-0 grid-bg opacity-40 pointer-events-none"></div>
    <div class="relative max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-violet-500/10 border border-violet-400/20 text-xs text-violet-300 uppercase tracking-wider font-semibold" data-anim="fade-up">
            <i class="fas fa-wand-magic-sparkles text-[10px]"></i> Live demo gallery
        </span>
        <h1 class="mt-5 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight leading-[1.05]" data-anim="fade-up">
            See what you can <span class="grad-text">build</span>.
        </h1>
        <p class="mt-5 text-lg text-gray-400 max-w-2xl mx-auto leading-relaxed" data-anim="fade-up">
            One dashboard, ten distinct kinds of link. Open any live example below to see exactly how it looks and works — then build your own in minutes.
        </p>
        <div class="mt-7 flex flex-wrap items-center justify-center gap-3" data-anim="fade-up">
            <a href="{{ route('register.page') }}" class="px-6 py-3 bg-violet-600 hover:bg-violet-700 text-white rounded-full text-sm font-bold">Start free</a>
            <a href="{{ route('site.features') }}" class="px-5 py-3 rounded-full text-sm font-medium text-gray-200 border border-white/15 hover:bg-white/5">Browse all features</a>
        </div>
    </div>
</section>

{{-- GALLERY --}}
<section class="relative pb-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @if(empty($cards))
            <div class="max-w-xl mx-auto text-center rounded-3xl border border-white/10 bg-white/[0.02] p-10">
                <i class="fas fa-screwdriver-wrench text-3xl text-violet-300"></i>
                <h2 class="mt-4 text-xl font-bold text-white">Demos are on their way</h2>
                <p class="mt-2 text-sm text-gray-400">
                    Our live examples are being prepared. In the meantime,
                    <a href="{{ route('site.features') }}" class="text-violet-300 hover:text-violet-200 underline underline-offset-2">explore every feature</a>
                    or <a href="{{ route('register.page') }}" class="text-violet-300 hover:text-violet-200 underline underline-offset-2">start building free</a>.
                </p>
            </div>
        @else
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5" data-anim="fade-up" data-stagger>
                @foreach($cards as $card)
                    <a href="{{ $card['url'] }}"
                       target="_blank" rel="noopener"
                       class="demo-card group relative flex flex-col rounded-2xl border border-white/10 bg-white/[0.02] p-6 overflow-hidden">
                        <span class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-violet-500/10 border border-violet-400/20 text-violet-300 text-lg">
                            <i class="fas {{ $card['icon'] }}"></i>
                        </span>
                        <h3 class="mt-4 text-lg font-bold text-white">{{ $card['name'] }}</h3>
                        @if($card['description'] !== '')
                            <p class="mt-2 text-sm text-gray-400 leading-relaxed flex-1">{{ $card['description'] }}</p>
                        @endif
                        <span class="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-violet-300 group-hover:text-violet-200">
                            View live demo
                            <i class="fas fa-arrow-right text-xs transition-transform group-hover:translate-x-0.5"></i>
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</section>

{{-- CTA --}}
<section class="relative pb-28">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="rounded-3xl border border-violet-400/20 bg-gradient-to-br from-violet-600/15 to-fuchsia-600/10 p-10 text-center">
            <h2 class="text-2xl sm:text-3xl font-bold text-white">Ready to make your own?</h2>
            <p class="mt-3 text-gray-300 max-w-xl mx-auto">Pick a link type, customise it, and share it from a single URL — every visit and click tracked.</p>
            <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                <a href="{{ route('register.page') }}" class="px-6 py-3 bg-violet-600 hover:bg-violet-700 text-white rounded-full text-sm font-bold">Create your first link</a>
                <a href="{{ $pricingHref ?? route('site.pricing') }}" class="px-5 py-3 rounded-full text-sm font-medium text-gray-200 border border-white/15 hover:bg-white/5">See pricing</a>
            </div>
        </div>
    </div>
</section>
@endsection

@push('head')
<style>
    .demo-card { transition: border-color .2s ease, transform .25s ease, background .2s ease; }
    .demo-card:hover { border-color: rgba(167,139,250,.4); transform: translateY(-3px); background: rgba(167,139,250,.05); }
</style>
@endpush
