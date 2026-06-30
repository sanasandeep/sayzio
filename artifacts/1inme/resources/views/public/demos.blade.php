@extends('public.layouts.site')

@section('title', 'See what you can build')

@section('content')
{{-- HERO --}}
<section class="relative pt-20 pb-10 lg:pt-28 lg:pb-12 overflow-hidden">
    <div class="mesh-bg"></div>
    <div class="absolute inset-0 grid-bg opacity-40 pointer-events-none"></div>
    <div class="relative max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-500/10 border border-blue-400/20 text-xs text-blue-300 uppercase tracking-wider font-semibold" data-anim="fade-up">
            <i class="fas fa-wand-magic-sparkles text-[10px]"></i> Live demo gallery
        </span>
        <h1 class="mt-5 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight leading-[1.05]" data-anim="fade-up">
            See what you can <span class="grad-text">build</span>.
        </h1>
        <p class="mt-5 text-lg text-gray-400 max-w-2xl mx-auto leading-relaxed" data-anim="fade-up">
            One dashboard, ten distinct kinds of link. Open any live example below to see exactly how it looks and works — then build your own in minutes.
        </p>
        <div class="mt-7 flex flex-wrap items-center justify-center gap-3" data-anim="fade-up">
            @guest
                <a href="{{ route('register.page') }}" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-full text-sm font-bold">Start free</a>
            @else
                <a href="{{ route('user.dashboard') }}" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-full text-sm font-bold">Go to your dashboard</a>
            @endguest
            <a href="{{ route('site.features') }}" class="px-5 py-3 rounded-full text-sm font-medium text-gray-200 border border-white/15 hover:bg-white/5">Browse all features</a>
        </div>
    </div>
</section>

{{-- GALLERY --}}
<section class="relative pb-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @if(empty($cards))
            <div class="max-w-xl mx-auto text-center rounded-3xl border border-white/10 bg-white/[0.02] p-10">
                <i class="fas fa-screwdriver-wrench text-3xl text-blue-300"></i>
                <h2 class="mt-4 text-xl font-bold text-white">Demos are on their way</h2>
                <p class="mt-2 text-sm text-gray-400">
                    Our live examples are being prepared. In the meantime,
                    <a href="{{ route('site.features') }}" class="text-blue-300 hover:text-blue-200 underline underline-offset-2">explore every feature</a>
                    or @guest<a href="{{ route('register.page') }}" class="text-blue-300 hover:text-blue-200 underline underline-offset-2">start building free</a>@else<a href="{{ route('user.dashboard') }}" class="text-blue-300 hover:text-blue-200 underline underline-offset-2">go to your dashboard</a>@endguest.
                </p>
            </div>
        @else
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5" data-anim="fade-up" data-stagger>
                @foreach($cards as $card)
                    <a href="{{ $card['url'] }}"
                       target="_blank" rel="noopener"
                       class="demo-card group relative flex flex-col rounded-2xl border border-white/10 bg-white/[0.02] p-6 overflow-hidden">
                        <span class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-blue-500/10 border border-blue-400/20 text-blue-300 text-lg">
                            <i class="fas {{ $card['icon'] }}"></i>
                        </span>
                        <h3 class="mt-4 text-lg font-bold text-white">{{ $card['name'] }}</h3>
                        @if($card['description'] !== '')
                            <p class="mt-2 text-sm text-gray-400 leading-relaxed flex-1">{{ $card['description'] }}</p>
                        @endif
                        <span class="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-blue-300 group-hover:text-blue-200">
                            View live demo
                            <i class="fas fa-arrow-right text-xs transition-transform group-hover:translate-x-0.5"></i>
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</section>

{{-- EMBED ANYWHERE --}}
<section class="relative pb-24">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="rounded-3xl border border-white/10 bg-white/[0.02] p-8 sm:p-10" data-anim="fade-up">
            <div class="grid lg:grid-cols-2 gap-8 items-center">
                <div>
                    <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-500/10 border border-blue-400/20 text-xs text-blue-300 uppercase tracking-wider font-semibold">
                        <i class="fas fa-code text-[10px]"></i> Embed anywhere
                    </span>
                    <h2 class="mt-4 text-2xl sm:text-3xl font-bold text-white">Drop any link onto your own site.</h2>
                    <p class="mt-3 text-gray-400 leading-relaxed">
                        Every link comes with a copy-paste embed code in its Settings. Pages render as a responsive
                        iframe; short links, files, events and contacts render as a compact card with the right
                        action button — and every view &amp; click still counts in your analytics.
                    </p>
                    @guest
                        <a href="{{ route('register.page') }}" class="mt-6 inline-flex items-center gap-2 px-5 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-full text-sm font-bold">
                            Get your embed code <i class="fas fa-arrow-right text-xs"></i>
                        </a>
                    @else
                        <a href="{{ route('user.dashboard') }}" class="mt-6 inline-flex items-center gap-2 px-5 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-full text-sm font-bold">
                            Go to your dashboard <i class="fas fa-arrow-right text-xs"></i>
                        </a>
                    @endguest
                </div>
                <div class="rounded-2xl border border-white/10 bg-black/30 p-4 overflow-x-auto">
                    <pre class="text-[12px] leading-relaxed text-blue-200/90 whitespace-pre-wrap break-all"><code>&lt;script src="{{ rtrim(config('app.url'), '/') }}/embed/link/your-alias/embed.js" async&gt;&lt;/script&gt;
&lt;div data-1inme-embed="your-alias"&gt;&lt;/div&gt;</code></pre>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- CTA --}}
<section class="relative pb-28">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="rounded-3xl border border-blue-400/20 bg-gradient-to-br from-blue-600/15 to-fuchsia-600/10 p-10 text-center">
            <h2 class="text-2xl sm:text-3xl font-bold text-white">Ready to make your own?</h2>
            <p class="mt-3 text-gray-300 max-w-xl mx-auto">Pick a link type, customise it, and share it from a single URL — every visit and click tracked.</p>
            <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                @guest
                    <a href="{{ route('register.page') }}" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-full text-sm font-bold">Create your first link</a>
                @else
                    <a href="{{ route('user.dashboard') }}" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-full text-sm font-bold">Go to your dashboard</a>
                @endguest
                <a href="{{ $pricingHref ?? route('site.pricing') }}" class="px-5 py-3 rounded-full text-sm font-medium text-gray-200 border border-white/15 hover:bg-white/5">See pricing</a>
            </div>
        </div>
    </div>
</section>
@endsection

@push('head')
<style>
    .demo-card { transition: border-color .2s ease, transform .25s ease, background .2s ease; }
    .demo-card:hover { border-color: rgba(144,172,255,.4); transform: translateY(-3px); background: rgba(144,172,255,.05); }
</style>
@endpush
