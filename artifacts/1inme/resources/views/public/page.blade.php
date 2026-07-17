@extends('public.layouts.site')
@section('content')
<section class="relative pt-16 pb-12 lg:pt-24 lg:pb-16 overflow-hidden">
    <div class="absolute -top-32 -left-32 w-[500px] h-[500px] rounded-full" style="background:rgba(61,107,255,.06);"></div>
    <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-4xl sm:text-5xl font-bold tracking-tight">{{ $page->title }}</h1>
        @if($page->meta_description)
            <p class="mt-4 text-lg text-gray-400 max-w-2xl mx-auto">{{ $page->meta_description }}</p>
        @endif
    </div>
</section>

<section class="pb-20">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">
        @foreach(($page->sections ?? []) as $section)
            <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-6 sm:p-8">
                @if(!empty($section['heading']))
                    <h2 class="text-xl sm:text-2xl font-bold mb-3 text-white">{{ $section['heading'] }}</h2>
                @endif
                <div class="prose-light text-gray-300 leading-relaxed">
                    {!! \App\Services\SafeHtml::render($section['body'] ?? '') !!}
                </div>
            </div>
        @endforeach
        @if(empty($page->sections))
            <div class="text-center text-gray-500 text-sm">This page hasn't been written yet.</div>
        @endif
    </div>
</section>

@php
    $bb = (array) (data_get($page, 'extra.blog_block') ?? []);
    $relatedBlog = collect();
    if (!empty($bb['enabled'])) {
        try {
            $limit = (int) max(1, min(6, $bb['limit'] ?? 3));
            $ids   = array_values(array_filter(array_map('intval', (array) ($bb['post_ids'] ?? []))));
            $q = \App\Modules\Common\Models\BlogPost::published()->with('category');
            if (!empty($ids)) {
                $q->whereIn('id', $ids);
            } elseif (!empty($bb['category_id'])) {
                $q->where('category_id', (int) $bb['category_id']);
            }
            $relatedBlog = $q->orderByDesc('published_at')->take($limit)->get();
        } catch (\Throwable $e) {
            $relatedBlog = collect();
        }
    }
@endphp
@if($relatedBlog->count())
    <section class="pb-20">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-xl sm:text-2xl font-bold text-white mb-6">{{ $bb['heading'] ?? 'Related from the blog' }}</h2>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach($relatedBlog as $p)
                    <a href="{{ route('site.blogs.show', $p->slug) }}" class="block bg-white/[0.04] hover:bg-white/[0.07] border border-white/10 rounded-2xl overflow-hidden transition">
                        @if($p->cover_image)
                            <div class="aspect-[16/9] bg-white/5 overflow-hidden"><img src="{{ \App\Support\PublicStorageUrl::resolve($p->cover_image) }}" alt="" loading="lazy" class="w-full h-full object-cover"></div>
                        @endif
                        <div class="p-4">
                            @if($p->category)<span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full" style="background: {{ $p->category->color ? $p->category->color . '22' : 'rgba(61,107,255,.15)' }}; color: {{ $p->category->color ?: '#90acff' }};">{{ $p->category->name }}</span>@endif
                            <h3 class="mt-2 text-sm font-semibold text-white line-clamp-2">{{ $p->title }}</h3>
                            @if($p->excerpt)<p class="mt-1 text-xs text-white/60 line-clamp-2">{{ $p->excerpt }}</p>@endif
                            <p class="mt-3 text-[11px] text-white/40">{{ optional($p->published_at)->format('M j, Y') }} · {{ $p->reading_time_min }} min</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endif
@endsection
