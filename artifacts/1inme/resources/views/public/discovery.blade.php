@extends('public.layouts.site')
@section('content')
<section class="relative pt-16 pb-10 lg:pt-24 lg:pb-12 overflow-hidden">
    <div class="absolute -top-32 -left-32 w-[500px] h-[500px] rounded-full" style="background:radial-gradient(circle,rgba(124,58,237,0.18) 0%,transparent 70%);"></div>
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

        @if($showSearch)
            <form method="GET" action="{{ route('site.discovery') }}" class="mt-8 max-w-xl mx-auto flex gap-2">
                <input type="text" name="q" value="{{ $q }}" placeholder="Search creators, handles, topics…"
                       class="flex-1 px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-sm text-white placeholder-white/40 focus:outline-none focus:border-violet-500">
                <button type="submit" class="px-5 py-3 bg-violet-600 hover:bg-violet-700 text-white rounded-xl text-sm font-medium">
                    <i class="fas fa-search mr-1"></i> Search
                </button>
            </form>
        @endif
    </div>
</section>

<section class="pb-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @if($biolinks->isEmpty())
            <div class="text-center text-gray-500 text-sm py-16">
                @if($q !== '')
                    No biolinks match "<span class="text-white">{{ $q }}</span>" yet.
                @else
                    No public biolinks yet — check back soon.
                @endif
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                @foreach($biolinks as $b)
                    @php($u = $b->user)
                    <a href="/{{ $b->alias }}" target="_blank" rel="noopener"
                       class="group bg-white/[0.03] hover:bg-white/[0.06] border border-white/10 hover:border-violet-500/40 rounded-2xl p-5 transition flex flex-col">
                        <div class="flex items-center gap-3">
                            @if($u && $u->avatar)
                                <img src="{{ $u->avatar }}" alt="" class="w-11 h-11 rounded-full object-cover">
                            @else
                                <div class="w-11 h-11 rounded-full bg-violet-600/20 flex items-center justify-center text-violet-300 text-sm font-bold">
                                    {{ strtoupper(mb_substr($u?->name ?? $b->title ?? '?', 0, 1)) }}
                                </div>
                            @endif
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-white truncate group-hover:text-violet-300">
                                    {{ $b->title ?: ($u?->name ?: $b->alias) }}
                                </div>
                                <div class="text-[11px] text-white/40 truncate">@{{ $u?->handle ?: $b->alias }}</div>
                            </div>
                        </div>
                        @if(!empty($u?->bio))
                            <p class="mt-3 text-xs text-gray-400 line-clamp-3">{{ $u->bio }}</p>
                        @endif
                        <div class="mt-4 pt-3 border-t border-white/5 flex items-center justify-between text-[11px] text-white/40">
                            <span><i class="fas fa-users mr-1"></i> {{ number_format((int)($u->followers_count ?? 0)) }} followers</span>
                            <span class="text-violet-400 group-hover:translate-x-0.5 transition">Open <i class="fas fa-arrow-right ml-1"></i></span>
                        </div>
                    </a>
                @endforeach
            </div>
            <div class="mt-10">{{ $biolinks->links() }}</div>
        @endif
    </div>
</section>
@endsection
