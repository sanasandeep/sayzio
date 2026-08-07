@extends('public.layouts.site')

@php
    $page = (object) [
        'title'            => 'Events are unavailable',
        'meta_description' => 'Events are not available on 1IN.ME right now. Explore everything else creators share from one link.',
    ];
    $homeUrl = url('/');
@endphp

@push('head')
<style>
    /* Light-mode legibility: dark-base design paired with per-element
       light-mode rules (no blanket overrides). Dark mode is untouched. */
    html.light-mode .evun-body { color:#4b5563 !important; }        /* was text-gray-400 */
    html.light-mode .evun-card-heading.evun-card-heading { color:#111827 !important; }/* was text-white */
    html.light-mode .evun-card-body.evun-card-body { color:#374151 !important; }      /* was text-gray-300 */
    html.light-mode .evun-btn-secondary.evun-btn-secondary { background:rgba(17,24,39,.06) !important; border-color:rgba(17,24,39,.15) !important; color:#111827 !important; }
    html.light-mode .evun-btn-secondary.evun-btn-secondary:hover { background:rgba(17,24,39,.10) !important; }
</style>
@endpush

@section('content')
<section class="relative pt-16 pb-12 lg:pt-24 lg:pb-16 overflow-hidden">
    <div class="absolute -top-32 -left-32 w-[500px] h-[500px] rounded-full"
         style="background:radial-gradient(circle,rgba(61,107,255,0.18) 0%,transparent 70%);"></div>
    <div class="absolute -bottom-40 -right-40 w-[520px] h-[520px] rounded-full"
         style="background:radial-gradient(circle,rgba(59,130,246,0.14) 0%,transparent 70%);"></div>

    <div class="relative max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <div class="inline-flex items-center justify-center mb-6">
            @include('common.partials.brand-logo', ['height' => 'h-10', 'alt' => '1IN.ME'])
        </div>

        <div class="text-xs uppercase tracking-[0.3em] text-blue-400 mb-3">Events</div>
        <h1 class="text-4xl sm:text-5xl font-bold tracking-tight">Events aren't available right now</h1>
        <p class="mt-4 text-lg text-gray-400 evun-body max-w-2xl mx-auto">
            The event page you're looking for can't be shown because events are
            currently switched off on 1IN.ME. Please check back later; the link
            you followed may work again once events return.
        </p>
    </div>
</section>

<section class="pb-20">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-gradient-to-br from-blue-500/[0.10] to-blue-500/[0.06] border border-blue-400/20 rounded-2xl p-6 sm:p-10 text-center">
            <h2 class="text-2xl sm:text-3xl font-bold text-white evun-card-heading">
                In the meantime…
            </h2>
            <p class="mt-3 text-gray-300 evun-card-body max-w-xl mx-auto">
                Creators on 1IN.ME share everything from one tidy link: pages,
                products, and profiles. Head back to the homepage to explore.
            </p>

            <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-center">
                <a href="{{ $homeUrl }}"
                   class="inline-flex items-center justify-center px-6 py-3 bg-[#3d6bff] hover:bg-[#2342c7] text-white rounded-full text-sm font-bold transition">
                    Back to homepage
                </a>
            </div>
        </div>
    </div>
</section>
@endsection
