@extends('public.layouts.site')
@section('content')
{{-- HERO --}}
<section class="relative pt-20 pb-12 lg:pt-28 lg:pb-14 overflow-hidden">
    <div class="mesh-bg"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid lg:grid-cols-2 gap-10 items-center">
        <div data-anim="fade-right">
            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-violet-500/10 border border-violet-400/20 text-xs text-violet-300 uppercase tracking-wider font-semibold">
                <i class="fas fa-stream text-[10px]"></i> Live feed
            </span>
            <h1 class="mt-5 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight leading-[1.05]">
                {{ $page->title }}
            </h1>
            @if($page->meta_description)
                <p class="mt-5 text-lg text-gray-400 max-w-xl leading-relaxed">{{ $page->meta_description }}</p>
            @endif
            @foreach(($page->sections ?? []) as $section)
                @if(!empty($section['body']))
                    <p class="mt-3 text-base text-gray-400 max-w-xl leading-relaxed">{{ $section['body'] }}</p>
                @endif
            @endforeach
            <div class="mt-7 flex items-center gap-6 text-sm">
                <div><div class="text-2xl font-bold"><span data-count="{{ $posts->total() }}"></span></div><div class="text-xs uppercase tracking-wider text-gray-500 mt-0.5">Posts</div></div>
                <div class="w-px h-10 bg-white/10"></div>
                <div><div class="text-2xl font-bold flex items-baseline gap-1"><span class="w-2 h-2 rounded-full bg-emerald-400 pulse-dot text-emerald-400/40"></span><span class="text-base font-semibold text-emerald-300">Live</span></div><div class="text-xs uppercase tracking-wider text-gray-500 mt-0.5">Updated continuously</div></div>
            </div>
        </div>
        <div data-anim="fade-left" data-tilt="5">
            <div class="img-frame img-tilt aspect-[5/4]">
                <img src="{{ asset('images/marketing/creators-feed/hero.png') }}" alt="Creators sharing posts on 1INME">
            </div>
        </div>
    </div>
</section>

{{-- FEED --}}
<section class="pb-24">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        @if($posts->isEmpty())
            <div class="text-center text-gray-500 text-sm py-16" data-anim="fade-up">No posts in the feed yet.</div>
        @else
            <div class="space-y-5" data-anim="fade-up" data-stagger>
                @foreach($posts as $p)
                    @php($u = $p->user)
                    <article class="bg-white/[0.03] border border-white/10 rounded-2xl p-5 sm:p-6 hover:border-violet-400/40 transition">
                        <header class="flex items-center gap-3 mb-3">
                            @if($u && $u->avatar)
                                <img src="{{ $u->avatar }}" alt="{{ $u?->name ?? 'Avatar' }}" class="w-10 h-10 rounded-full object-cover ring-2 ring-white/5">
                            @else
                                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-violet-500 to-fuchsia-500 flex items-center justify-center text-white text-sm font-bold ring-2 ring-white/5">
                                    {{ strtoupper(mb_substr($u?->name ?? '?', 0, 1)) }}
                                </div>
                            @endif
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-white truncate">{{ $u?->name ?: 'Creator' }}</div>
                                <div class="text-[11px] text-white/40 truncate">
                                    @if($u?->handle)
                                        <a href="/{{ $u->handle }}" class="hover:text-violet-400">@{{ $u->handle }}</a> ·
                                    @endif
                                    {{ $p->published_at?->diffForHumans() }}
                                </div>
                            </div>
                            @if($showPinned && $p->pinned_at)
                                <span class="text-[10px] uppercase tracking-wider px-2 py-1 rounded-full bg-violet-500/15 text-violet-300"><i class="fas fa-thumbtack mr-1"></i>Pinned</span>
                            @endif
                        </header>
                        @if($p->title)
                            <h2 class="text-lg sm:text-xl font-bold text-white mb-2">{{ $p->title }}</h2>
                        @endif
                        @if($p->image)
                            <div class="img-frame mb-3 aspect-[16/10]"><img src="{{ $p->image }}" alt="{{ $p->title ?? 'Post image' }}"></div>
                        @endif
                        @if($p->body)
                            <div class="prose-light text-gray-300 leading-relaxed text-sm">
                                {!! nl2br(e($p->body)) !!}
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
            <div class="mt-10">{{ $posts->links() }}</div>
        @endif
    </div>
</section>

@include('public.partials.subscribe-block', [
    'heading' => 'Get the best of the feed in your inbox.',
    'subtext' => 'Email, WhatsApp Channel, or DM — pick how you want to follow new posts and creators.',
    'source'  => 'creators-feed',
])
@endsection
