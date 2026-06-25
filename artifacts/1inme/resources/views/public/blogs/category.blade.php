@php
    $page = (object) [ 'title' => $pageTitle, 'meta_description' => $pageMeta ];
@endphp
@extends('public.layouts.site')
@section('title', $pageTitle)

@section('content')
<section class="pt-20 pb-10">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 text-center">
        <p class="text-xs uppercase tracking-[0.2em] text-blue-400">Category</p>
        <h1 class="mt-2 text-4xl sm:text-5xl font-bold tracking-tight" style="color: {{ $category->color ?: '#fff' }};">{{ $category->name }}</h1>
        @if($category->description)
            <p class="mt-3 text-gray-400 max-w-2xl mx-auto">{{ $category->description }}</p>
        @endif
        <div class="mt-6 flex flex-wrap justify-center gap-2">
            <a href="{{ route('site.blogs.index') }}" class="px-3 py-1.5 rounded-full text-xs font-medium bg-white/10 hover:bg-white/15">All</a>
            @foreach($categories as $c)
                <a href="{{ route('site.blogs.category', $c->slug) }}" class="px-3 py-1.5 rounded-full text-xs font-medium {{ $c->id === $category->id ? 'bg-blue-600 text-white' : 'bg-white/5 hover:bg-white/10 border border-white/10' }}">{{ $c->name }}</a>
            @endforeach
        </div>
    </div>
</section>

<section class="pb-20">
    <div class="max-w-6xl mx-auto px-4 sm:px-6">
        @if($posts->count())
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($posts as $post)
                    @include('public.blogs.partials.card', ['post' => $post])
                @endforeach
            </div>
            <div class="mt-10">{{ $posts->links() }}</div>
        @else
            <p class="text-center text-white/50">No articles in this category yet.</p>
        @endif
    </div>
</section>

@include('public.partials.subscribe-block', [
    'heading' => 'Get new posts in this category.',
    'subtext' => 'Pick email, WhatsApp Channel, or DM and we will send you new articles as they go live.',
    'source'  => 'blogs-category',
])
@endsection
