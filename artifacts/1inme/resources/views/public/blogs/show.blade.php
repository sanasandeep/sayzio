@php
    $page = (object) [
        'title' => $pageTitle,
        'meta_description' => $pageMeta,
    ];
@endphp
@extends('public.layouts.site')
@section('title', $pageTitle)

@push('head')
    <link rel="canonical" href="{{ $post->canonical_url ?: route('site.blogs.show', $post->slug) }}">
    <meta property="og:title" content="{{ $post->meta_title ?: $post->title }}">
    <meta property="og:description" content="{{ $post->meta_description ?: $post->excerpt }}">
    <meta property="og:type" content="article">
    @if($post->og_image ?: $post->cover_image ?: ($settings['default_og_image'] ?? null))
        <meta property="og:image" content="{{ $post->og_image ?: $post->cover_image ?: $settings['default_og_image'] }}">
    @endif
    <meta name="twitter:card" content="summary_large_image">
    @php
        $jsonLd = [
            '@context'         => 'https://schema.org',
            '@type'            => 'BlogPosting',
            'headline'         => $post->title,
            'description'      => $post->meta_description ?: $post->excerpt,
            'image'            => $post->og_image ?: $post->cover_image,
            'datePublished'    => optional($post->published_at)->toIso8601String(),
            'author'           => ['@type' => 'Person', 'name' => optional($post->author)->name ?: '1INME'],
            'mainEntityOfPage' => route('site.blogs.show', $post->slug),
        ];
    @endphp
    <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES) !!}</script>
@endpush

@section('content')
<article class="pt-12 pb-20">
    <div class="max-w-3xl mx-auto px-4 sm:px-6">
        <a href="{{ route('site.blogs.index') }}" class="text-xs text-violet-400 hover:underline"><i class="fas fa-arrow-left mr-1"></i>Back to blog</a>

        <div class="mt-6">
            @if($post->category)
                <a href="{{ route('site.blogs.category', $post->category->slug) }}" class="inline-block text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded-full" style="background: {{ $post->category->color ? $post->category->color . '22' : 'rgba(124,58,237,.15)' }}; color: {{ $post->category->color ?: '#a78bfa' }};">{{ $post->category->name }}</a>
            @endif
            <h1 class="mt-3 text-3xl sm:text-4xl lg:text-5xl font-bold tracking-tight">{{ $post->title }}</h1>
            <div class="mt-5 flex items-center gap-3">
                @if($post->author)
                    @php $authorAvatar = $post->author->avatar ?? null; @endphp
                    @if($authorAvatar)
                        <img src="{{ $authorAvatar }}" alt="{{ $post->author->name }}" class="w-10 h-10 rounded-full object-cover">
                    @else
                        <div class="w-10 h-10 rounded-full flex items-center justify-center font-semibold text-white" style="background:#7c3aed;">{{ strtoupper(substr($post->author->name ?? '?', 0, 1)) }}</div>
                    @endif
                @endif
                <div class="text-xs text-white/60 leading-tight">
                    @if($post->author)<div class="text-sm text-white/90 font-medium">{{ $post->author->name }}</div>@endif
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5">
                        @if($post->published_at)<span><i class="far fa-calendar mr-1"></i>{{ $post->published_at->format('F j, Y') }}</span>@endif
                        <span><i class="far fa-clock mr-1"></i>{{ $post->reading_time_min }} min read</span>
                    </div>
                </div>
                @php $shareUrl = urlencode(route('site.blogs.show', $post->slug)); $shareTitle = urlencode($post->title); @endphp
                <div class="ml-auto hidden sm:flex items-center gap-1.5">
                    <a href="https://twitter.com/intent/tweet?url={{ $shareUrl }}&text={{ $shareTitle }}" target="_blank" rel="noopener" aria-label="Share on Twitter" class="w-8 h-8 rounded-full bg-white/5 hover:bg-white/10 flex items-center justify-center text-white/70"><i class="fab fa-x-twitter"></i></a>
                    <a href="https://www.facebook.com/sharer/sharer.php?u={{ $shareUrl }}" target="_blank" rel="noopener" aria-label="Share on Facebook" class="w-8 h-8 rounded-full bg-white/5 hover:bg-white/10 flex items-center justify-center text-white/70"><i class="fab fa-facebook-f"></i></a>
                    <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ $shareUrl }}" target="_blank" rel="noopener" aria-label="Share on LinkedIn" class="w-8 h-8 rounded-full bg-white/5 hover:bg-white/10 flex items-center justify-center text-white/70"><i class="fab fa-linkedin-in"></i></a>
                    <button type="button" onclick="navigator.clipboard.writeText('{{ route('site.blogs.show', $post->slug) }}'); this.innerHTML='<i class=\'fas fa-check\'></i>';" aria-label="Copy link" class="w-8 h-8 rounded-full bg-white/5 hover:bg-white/10 flex items-center justify-center text-white/70"><i class="fas fa-link"></i></button>
                </div>
            </div>
        </div>

        @if($post->cover_image)
            <div class="mt-8 rounded-2xl overflow-hidden">
                <img src="{{ $post->cover_image }}" alt="{{ $post->title }}" class="w-full h-auto">
            </div>
        @endif

        @if(!empty($toc) && count($toc) > 2)
            <nav class="mt-8 bg-white/[0.03] border border-white/10 rounded-xl p-5">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/50 mb-2">In this article</p>
                <ul class="space-y-1.5">
                    @foreach($toc as $h)
                        <li class="text-sm {{ $h['level'] === 3 ? 'pl-4' : '' }}"><a href="#{{ $h['id'] }}" class="text-white/80 hover:text-violet-300">{{ $h['text'] }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endif

        <div class="mt-8 prose-light text-gray-300 leading-relaxed">
            {!! \App\Services\SafeHtml::render($bodyHtml) !!}
        </div>

        @if($post->tags->count())
            <div class="mt-10 flex flex-wrap gap-2">
                @foreach($post->tags as $t)
                    <a href="{{ route('site.blogs.tag', $t->slug) }}" class="text-xs px-2.5 py-1 rounded-full bg-white/5 hover:bg-white/10 text-white/70">#{{ $t->name }}</a>
                @endforeach
            </div>
        @endif

        <div class="mt-10 flex justify-between text-sm">
            @if($prevPost)
                <a href="{{ route('site.blogs.show', $prevPost->slug) }}" class="text-white/70 hover:text-white"><i class="fas fa-arrow-left mr-1"></i>{{ Str::limit($prevPost->title, 40) }}</a>
            @else <span></span> @endif
            @if($nextPost)
                <a href="{{ route('site.blogs.show', $nextPost->slug) }}" class="text-white/70 hover:text-white">{{ Str::limit($nextPost->title, 40) }}<i class="fas fa-arrow-right ml-1"></i></a>
            @endif
        </div>
    </div>

    @if($related->count())
        <div class="max-w-6xl mx-auto px-4 sm:px-6 mt-16">
            <h2 class="text-xs font-semibold uppercase tracking-[0.2em] text-white/50 mb-4">Related reads</h2>
            <div class="grid md:grid-cols-3 gap-6">
                @foreach($related as $r)
                    @include('public.blogs.partials.card', ['post' => $r])
                @endforeach
            </div>
        </div>
    @endif

    @if($post->allow_comments)
        <div id="comments" class="max-w-3xl mx-auto px-4 sm:px-6 mt-16">
            <h2 class="text-2xl font-bold">Comments <span class="text-sm text-white/50">({{ $comments->total() }})</span></h2>

            @if(session('success'))
                <div class="mt-4 p-3 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="mt-4 p-3 rounded-lg bg-red-500/10 border border-red-500/20 text-red-300 text-sm">{{ session('error') }}</div>
            @endif

            @if($settings['approval_mode'] === 'closed')
                <p class="mt-6 text-white/50 text-sm">Comments are currently closed.</p>
            @elseif(!$commenter['type'])
                <p class="mt-6 text-white/60 text-sm">Please <a href="{{ route('user.login') }}" class="text-violet-400 hover:underline">sign in</a> to leave a comment.</p>
            @else
                <form method="POST" action="{{ route('site.blogs.comments.store', $post->slug) }}" class="mt-6 space-y-3">
                    @csrf
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full flex items-center justify-center font-semibold text-white" style="background: #7c3aed;">{{ strtoupper(substr($commenter['name'], 0, 1)) }}</div>
                        <span class="text-sm text-white/80">Posting as <strong>{{ $commenter['name'] }}</strong></span>
                    </div>
                    <textarea name="body" required rows="4" placeholder="Share your thoughts…" class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-sm text-white placeholder-white/40 focus:border-violet-500 outline-none">{{ old('body') }}</textarea>
                    @error('body')<p class="text-xs text-red-400">{{ $message }}</p>@enderror
                    <div class="flex justify-end">
                        <button class="px-5 py-2 bg-violet-600 hover:bg-violet-700 rounded-lg text-sm font-medium">Post comment</button>
                    </div>
                </form>
            @endif

            <div class="mt-10 space-y-6">
                @forelse($comments as $c)
                    <div id="comment-{{ $c->id }}" class="flex gap-3">
                        <div class="w-9 h-9 shrink-0 rounded-full flex items-center justify-center font-semibold text-white" style="background: #7c3aed;">{{ $c->authorInitial() }}</div>
                        <div class="flex-1">
                            <div class="bg-white/[0.03] border border-white/10 rounded-xl p-4">
                                <div class="flex items-center justify-between text-xs text-white/60">
                                    <span class="font-semibold text-white/90">{{ $c->author_name }}</span>
                                    <span>{{ $c->created_at->diffForHumans() }}</span>
                                </div>
                                <p class="mt-2 text-sm text-white/80 whitespace-pre-line">{{ $c->body }}</p>
                            </div>

                            @foreach($c->replies as $r)
                                <div id="comment-{{ $r->id }}" class="mt-3 ml-6 flex gap-3">
                                    <div class="w-8 h-8 shrink-0 rounded-full flex items-center justify-center text-xs font-semibold text-white" style="background: #7c3aed;">{{ $r->authorInitial() }}</div>
                                    <div class="flex-1 bg-violet-500/[0.06] border border-violet-500/20 rounded-xl p-4">
                                        <div class="flex items-center gap-2 text-xs text-white/70">
                                            <span class="font-semibold text-white">{{ $r->author_name }}</span>
                                            <span class="px-1.5 py-0.5 rounded bg-violet-500/20 text-violet-300 text-[10px] uppercase tracking-wider">Staff</span>
                                            <span class="text-white/40">· {{ $r->created_at->diffForHumans() }}</span>
                                        </div>
                                        <p class="mt-2 text-sm text-white/80 whitespace-pre-line">{{ $r->body }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="text-white/50 text-sm">Be the first to comment.</p>
                @endforelse
            </div>

            <div class="mt-8">{{ $comments->links() }}</div>
        </div>
    @endif
</article>

@include('public.partials.subscribe-block', [
    'heading' => 'Liked this post? Get the next one.',
    'subtext' => 'Pick email, WhatsApp Channel, or DM and we will send you new posts as they go live.',
    'source'  => 'blogs-show',
])
@endsection
