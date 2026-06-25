@extends('public.layouts.site')

@php
    $page = (object) [
        'title'            => 'Link not found',
        'meta_description' => "This 1IN.ME short link doesn't exist (or it's been removed). Create your own in seconds.",
    ];
    $attempted = isset($alias) ? trim((string) $alias) : '';
    if ($attempted !== '' && mb_strlen($attempted) > 64) {
        $attempted = mb_substr($attempted, 0, 61) . '…';
    }
    $registerUrl = \Illuminate\Support\Facades\Route::has('user.register')
        ? route('user.register')
        : '/register';
    $homeUrl = url('/');
@endphp

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

        <div class="text-xs uppercase tracking-[0.3em] text-blue-400 mb-3">Error 404</div>
        <h1 class="text-4xl sm:text-5xl font-bold tracking-tight">This link doesn't exist</h1>
        <p class="mt-4 text-lg text-gray-400 max-w-2xl mx-auto">
            The short link you followed isn't connected to a 1IN.ME page.
            It may have been removed, mistyped, or never created.
        </p>

        @if($attempted !== '')
            <div class="mt-6 inline-flex items-center gap-2 px-4 py-2 bg-white/[0.04] border border-white/10 rounded-full text-sm text-gray-300">
                <span class="text-gray-500">You tried:</span>
                <span class="font-mono text-white">/{{ $attempted }}</span>
            </div>
        @endif
    </div>
</section>

<section class="pb-20">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-gradient-to-br from-blue-500/[0.10] to-blue-500/[0.06] border border-blue-400/20 rounded-2xl p-6 sm:p-10 text-center">
            <h2 class="text-2xl sm:text-3xl font-bold text-white">
                Want a short link like this — but for you?
            </h2>
            <p class="mt-3 text-gray-300 max-w-xl mx-auto">
                Build your own 1IN.ME link in under a minute. Share one tidy URL for
                every page, profile, and product you care about.
            </p>

            <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-center">
                <a href="{{ $registerUrl }}"
                   class="inline-flex items-center justify-center px-6 py-3 bg-[#3d6bff] hover:bg-[#2342c7] text-white rounded-full text-sm font-bold transition">
                    Create your own 1IN.ME link
                </a>
                <a href="{{ $homeUrl }}"
                   class="inline-flex items-center justify-center px-6 py-3 bg-white/[0.05] hover:bg-white/[0.10] border border-white/10 text-white rounded-full text-sm font-semibold transition">
                    Back to homepage
                </a>
            </div>
        </div>
    </div>
</section>
@endsection
