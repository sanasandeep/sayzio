@extends('public.layouts.site')
@section('content')
<section class="relative pt-16 pb-10 lg:pt-24 lg:pb-12 overflow-hidden">
    <div class="absolute -top-32 -right-32 w-[500px] h-[500px] rounded-full" style="background:radial-gradient(circle,rgba(124,58,237,0.18) 0%,transparent 70%);"></div>
    <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-4xl sm:text-5xl font-bold tracking-tight">{{ $page->title }}</h1>
        @if($page->meta_description)
            <p class="mt-4 text-lg text-gray-400 max-w-2xl mx-auto">{{ $page->meta_description }}</p>
        @endif
        @foreach(($page->sections ?? []) as $section)
            @if(!empty($section['body']))
                <p class="mt-4 text-base text-gray-400 max-w-2xl mx-auto">{{ $section['body'] }}</p>
            @endif
        @endforeach
    </div>
</section>

<section class="pb-20">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        @if($posts->isEmpty())
            <div class="text-center text-gray-500 text-sm py-16">No posts in the feed yet.</div>
        @else
            <div class="space-y-5">
                @foreach($posts as $p)
                    @php($u = $p->user)
                    <article class="bg-white/[0.03] border border-white/10 rounded-2xl p-5 sm:p-6">
                        <header class="flex items-center gap-3 mb-3">
                            @if($u && $u->avatar)
                                <img src="{{ $u->avatar }}" alt="" class="w-10 h-10 rounded-full object-cover">
                            @else
                                <div class="w-10 h-10 rounded-full bg-violet-600/20 flex items-center justify-center text-violet-300 text-sm font-bold">
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
                            <img src="{{ $p->image }}" alt="" class="w-full rounded-xl mb-3 max-h-96 object-cover">
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
@endsection
