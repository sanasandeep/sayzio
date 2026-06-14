@extends('public.layouts.site')
@section('title', 'Compare 1INME')

@section('content')
@php
    /** @var array<int, array<string, mixed>> $competitors */
    $competitors = $competitors ?? [];
    $total       = $total ?? 0;
    $ourScore    = $ourScore ?? 0;
@endphp

{{-- ─────────────  HERO  ───────────── --}}
<section class="relative pt-20 pb-14 lg:pt-28 lg:pb-16 overflow-hidden">
    <div class="mesh-bg" aria-hidden="true"></div>
    <div class="absolute inset-0 grid-bg opacity-50 pointer-events-none" aria-hidden="true"></div>
    <div class="relative max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <span data-anim="fade-up" class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider border border-white/15 text-gray-200">
            <i class="fas fa-scale-balanced text-[10px]" style="color:var(--c4)"></i> Honest comparisons
        </span>
        <h1 data-anim="fade-up" class="mt-5 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight leading-[1.05]">
            How 1INME compares to <span class="grad-text">the tools you know</span>.
        </h1>
        <p data-anim="fade-up" class="mt-5 text-lg text-gray-400 max-w-2xl mx-auto leading-relaxed">
            Already using another link-in-bio or short-link tool? Pick it below for a full,
            side-by-side breakdown across <span class="text-white font-semibold">{{ $total }} features</span> —
            and an honest take on where each tool wins.
        </p>
        <div data-anim="fade-up" class="mt-7 flex flex-wrap items-center justify-center gap-3">
            <a href="{{ url('/register') }}" class="px-6 py-3 bg-violet-600 hover:bg-violet-700 text-white rounded-full text-sm font-bold inline-flex items-center gap-2">
                <i class="fas fa-rocket text-xs"></i> Start free
            </a>
            <a href="{{ route('site.pricing') }}#compare" class="px-5 py-3 rounded-full text-sm font-medium text-gray-200 border border-white/15 hover:bg-white/5">
                See the full matrix
            </a>
        </div>
    </div>
</section>

{{-- ─────────────  COMPETITOR CARDS  ───────────── --}}
<section class="relative pb-10">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4" data-anim="fade-up" data-stagger>
            @foreach($competitors as $c)
                <a href="{{ route('site.compare.show', ['competitor' => $c['key']]) }}"
                   class="group glass rounded-3xl p-6 lift block relative overflow-hidden">
                    <div class="absolute -top-12 -right-12 w-40 h-40 rounded-full opacity-20" aria-hidden="true"
                         style="background:radial-gradient(circle, {{ $c['accent'] }}, transparent 70%);"></div>
                    <div class="relative flex items-center gap-3 mb-4">
                        <span class="w-11 h-11 rounded-2xl flex items-center justify-center text-white text-lg"
                              style="background:linear-gradient(135deg, {{ $c['accent'] }}, #7c3aed);">
                            <i class="fas {{ $c['icon'] }}"></i>
                        </span>
                        <div>
                            <div class="text-lg font-bold text-white leading-none">1INME <span class="text-gray-500 font-normal">vs</span> {{ $c['name'] }}</div>
                            <div class="text-xs text-gray-400 mt-1">{{ $c['tagline'] }}</div>
                        </div>
                    </div>
                    <div class="relative flex items-center gap-2 mb-4">
                        <span class="cmp-badge cmp-badge-ours text-[11px]"><i class="fas fa-bolt"></i> 1INME {{ $c['our_score'] }}/{{ $c['total'] }}</span>
                        <span class="cmp-badge text-[11px]">{{ $c['name'] }} {{ $c['rival_score'] }}/{{ $c['total'] }}</span>
                    </div>
                    <p class="relative text-sm text-gray-300 leading-relaxed">
                        1INME leads on <span class="grad-text font-bold">{{ $c['wins'] }}</span> more features.
                    </p>
                    <div class="relative mt-4 inline-flex items-center gap-1.5 text-sm font-semibold" style="color: {{ $c['accent'] }};">
                        See the full comparison
                        <i class="fas fa-arrow-right text-[11px] group-hover:translate-x-0.5 transition"></i>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</section>

{{-- ─────────────  FULL INTERACTIVE MATRIX  ───────────── --}}
@include('public.partials._compare', ['compact' => false, 'anchorId' => 'compare', 'eyebrowOverride' => 'Side by side'])

{{-- ─────────────  CTA BAND  ───────────── --}}
<section class="pb-24">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grad-border rounded-3xl p-8 sm:p-12 text-center relative overflow-hidden" data-anim="fade-up">
            <div class="mesh-bg opacity-50" aria-hidden="true"></div>
            <div class="relative">
                <h3 class="text-3xl sm:text-4xl font-bold tracking-tight">One link. <span class="grad-text">The whole stack.</span></h3>
                <p class="mt-4 text-gray-300 max-w-2xl mx-auto">Stop paying for four tools that each do one thing. Build your free 1INME and bring everything under one link.</p>
                <div class="mt-7 flex flex-wrap items-center justify-center gap-3">
                    <a href="{{ url('/register') }}" class="px-7 py-3 bg-violet-600 hover:bg-violet-700 text-white rounded-full text-sm font-bold">Start free</a>
                    <a href="{{ route('site.features') }}" class="px-6 py-3 rounded-full text-sm font-medium text-gray-200 border border-white/15 hover:bg-white/5">See all features</a>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
