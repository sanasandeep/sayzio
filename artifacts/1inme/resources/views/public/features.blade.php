@extends('public.layouts.site')

@section('title', $page->title ?? 'Features')

@php
    // $categories is supplied by the controller, normalised from the
    // SitePage record so admins can edit headings, intros, and feature
    // rows from the admin UI without touching this template.
    $featureName = function ($f) {
        if (!is_array($f)) return '';
        return $f['name'] ?? ($f[0] ?? '');
    };
    $featureDescription = function ($f) {
        if (!is_array($f)) return '';
        return $f['description'] ?? ($f[1] ?? '');
    };
@endphp

@push('head')
<style>
    html { scroll-behavior: smooth; scroll-padding-top: 5rem; }
    .feature-cat-card { transition: border-color .15s ease, transform .15s ease; }
    .feature-cat-card:hover { border-color: rgba(167,139,250,.35); }
    .feature-row { border-top: 1px solid rgba(255,255,255,.06); }
    .feature-row:first-child { border-top: 0; }
    .toc-link { transition: color .15s ease, background .15s ease; }
    .toc-link:hover { color:#a78bfa; background: rgba(167,139,250,.08); }
</style>
@endpush

@section('content')
<section class="relative pt-16 pb-10 lg:pt-24 lg:pb-14 overflow-hidden">
    <div class="absolute -top-32 -left-32 w-[500px] h-[500px] rounded-full pointer-events-none" style="background:radial-gradient(circle,rgba(124,58,237,0.18) 0%,transparent 70%);"></div>
    <div class="absolute -bottom-32 -right-32 w-[500px] h-[500px] rounded-full pointer-events-none" style="background:radial-gradient(circle,rgba(236,72,153,0.12) 0%,transparent 70%);"></div>
    <div class="relative max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-violet-500/10 border border-violet-400/20 text-xs text-violet-300 uppercase tracking-wider font-semibold mb-4">
            <i class="fas fa-sparkles"></i> Everything 1INME can do
        </div>
        <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight">{{ $page->title ?? 'Features' }}</h1>
        <p class="mt-5 text-lg text-gray-400 max-w-3xl mx-auto leading-relaxed">
            {{ $page->meta_description ?? 'A complete tour of every capability inside 1INME — from your biolink and short links to inboxes, teams, billing, and beyond. No hidden lists, nothing collapsed: the whole product, on one page.' }}
        </p>
    </div>
</section>

<section class="pb-12">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-5 sm:p-6">
            <div class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-3">Jump to a category</div>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
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

<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pb-20 space-y-10">
    @foreach($categories as $i => $cat)
        <section id="cat-{{ $cat['id'] }}" class="feature-cat-card bg-white/[0.03] border border-white/10 rounded-2xl p-6 sm:p-10">
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
                    <div class="feature-row grid grid-cols-1 md:grid-cols-3 gap-2 md:gap-6 px-5 py-4">
                        <div class="md:col-span-1">
                            <div class="flex items-start gap-2">
                                <i class="fas fa-circle-check text-violet-400 mt-1 text-sm"></i>
                                <div class="font-semibold text-white">{{ $featureName($feat) }}</div>
                            </div>
                        </div>
                        <div class="md:col-span-2 text-gray-400 text-sm leading-relaxed md:pt-0.5">
                            {{ $featureDescription($feat) }}
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach
</div>

<section class="pb-24">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <div class="bg-gradient-to-br from-violet-500/15 via-fuchsia-500/10 to-transparent border border-violet-400/20 rounded-2xl p-8 sm:p-12">
            <h3 class="text-2xl sm:text-3xl font-bold text-white">Ready to put it all to work?</h3>
            <p class="mt-3 text-gray-400 max-w-2xl mx-auto">Spin up your biolink, drop in your first link, and explore every feature on this page from your dashboard.</p>
            <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                @auth
                    <a href="{{ route('user.dashboard') }}" class="px-6 py-3 bg-[#7c3aed] text-white rounded-full text-sm font-bold hover:bg-[#6d28d9]">Open dashboard</a>
                @else
                    <a href="{{ route('register.page') }}" class="px-6 py-3 bg-[#7c3aed] text-white rounded-full text-sm font-bold hover:bg-[#6d28d9]">Get started free</a>
                    <a href="{{ route('site.how-it-works') }}" class="px-6 py-3 border border-white/15 text-gray-200 rounded-full text-sm font-semibold hover:bg-white/5">See how it works</a>
                @endauth
            </div>
        </div>
    </div>
</section>
@endsection
