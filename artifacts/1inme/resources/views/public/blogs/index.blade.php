@php
    $page = (object) [
        'title' => $pageTitle ?? 'Blog',
        'meta_description' => $pageMeta ?? '',
    ];
@endphp
@extends('public.layouts.site')
@section('title', $pageTitle)

@push('head')
    <link rel="alternate" type="application/rss+xml" title="Sayzio Blog" href="{{ route('site.blogs.rss') }}">
@endpush

@section('content')
<section class="relative pt-20 pb-12 overflow-hidden">
    <div class="absolute -top-32 -left-32 w-[500px] h-[500px] rounded-full" style="background:rgba(61,107,255,.06);"></div>
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 text-center">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-400">{{ $settings['hero_eyebrow'] }}</p>
        <h1 class="mt-3 text-4xl sm:text-5xl font-bold tracking-tight">{{ $settings['hero_heading'] }}</h1>
        <p class="mt-4 text-lg text-gray-400 max-w-2xl mx-auto">{{ $settings['hero_subheading'] }}</p>

        <form method="GET" action="{{ route('site.blogs.index') }}" class="mt-8 max-w-xl mx-auto flex">
            <input type="text" name="q" value="{{ $q }}" placeholder="Search articles…" class="flex-1 px-4 py-3 bg-white/5 border border-white/10 rounded-l-xl text-sm text-white placeholder-white/40 focus:border-blue-500 outline-none">
            <button class="px-5 py-3 bg-blue-600 hover:bg-blue-700 rounded-r-xl text-sm font-medium"><i class="fas fa-search"></i></button>
        </form>

        @if($categories->count())
            <div class="mt-6 flex flex-wrap justify-center gap-2">
                <a href="{{ route('site.blogs.index') }}" class="px-3 py-1.5 rounded-full text-xs font-medium bg-white/10 hover:bg-white/15">All</a>
                @foreach($categories as $cat)
                    <a href="{{ route('site.blogs.category', $cat->slug) }}" class="px-3 py-1.5 rounded-full text-xs font-medium bg-white/5 hover:bg-white/10 border border-white/10" style="border-color: {{ $cat->color ?: 'rgba(255,255,255,0.1)' }};">{{ $cat->name }}</a>
                @endforeach
            </div>
        @endif

        @if(!empty($popularTags) && $popularTags->count())
            <div class="mt-3 flex flex-wrap justify-center gap-1.5">
                <span class="text-[11px] uppercase tracking-wider text-white/40 self-center mr-1">Tags:</span>
                @foreach($popularTags as $t)
                    @php $isActive = $activeTag && $activeTag->id === $t->id; @endphp
                    <a href="{{ route('site.blogs.index', array_filter(['q' => $q ?: null, 'tag' => $isActive ? null : $t->slug])) }}"
                       class="px-2.5 py-1 rounded-full text-[11px] {{ $isActive ? 'bg-blue-600 text-white' : 'bg-white/5 hover:bg-white/10 text-white/70 border border-white/10' }}">
                        #{{ $t->name }}<span class="ml-1 text-white/40">{{ $t->posts_count }}</span>
                    </a>
                @endforeach
                @if($activeTag)
                    <a href="{{ route('site.blogs.index', array_filter(['q' => $q ?: null])) }}" class="ml-1 px-2.5 py-1 rounded-full text-[11px] bg-white/5 hover:bg-white/10 text-white/60"><i class="fas fa-times mr-1"></i>Clear</a>
                @endif
            </div>
        @endif
    </div>
</section>

@if($featured->count() && empty($q))
    <section class="pb-12">
        <div class="max-w-6xl mx-auto px-4 sm:px-6">
            <h2 class="text-xs font-semibold uppercase tracking-[0.2em] text-white/50 mb-4">Featured</h2>
            <div class="grid md:grid-cols-3 gap-6">
                @foreach($featured as $post)
                    @include('public.blogs.partials.card', ['post' => $post, 'feature' => $loop->first])
                @endforeach
            </div>
        </div>
    </section>
@endif

<section class="pb-20">
    <div class="max-w-6xl mx-auto px-4 sm:px-6">
        @if($posts->total() === 0)
            <div class="text-center text-white/50 py-20">No articles found{{ $q ? ' for "' . e($q) . '"' : '' }}.</div>
        @else
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($posts as $post)
                    @include('public.blogs.partials.card', ['post' => $post])
                @endforeach
            </div>
            <div class="mt-10">{{ $posts->links() }}</div>
        @endif
    </div>
</section>

@include('public.partials.subscribe-block', [
    'heading' => 'Never miss a Sayzio post.',
    'subtext' => 'Get new articles your way — email, WhatsApp Channel, or DM. Once-a-month round-ups, no spam.',
    'source'  => 'blogs-index',
])
@endsection
