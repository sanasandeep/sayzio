@php
    $page = (object) [ 'title' => $pageTitle, 'meta_description' => $pageMeta ];
@endphp
@extends('public.layouts.site')
@section('title', $pageTitle)

@section('content')
<section class="pt-20 pb-10">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 text-center">
        <p class="text-xs uppercase tracking-[0.2em] text-blue-400">Tag</p>
        <h1 class="mt-2 text-4xl sm:text-5xl font-bold tracking-tight">#{{ $tag->name }}</h1>
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
            <p class="text-center text-white/50">No articles tagged #{{ $tag->name }} yet.</p>
        @endif
    </div>
</section>

@include('public.partials.subscribe-block', [
    'heading' => 'Get new posts on this tag.',
    'subtext' => 'Pick email, WhatsApp Channel, or DM and we will send you new articles as they go live.',
    'source'  => 'blogs-tag',
])
@endsection
