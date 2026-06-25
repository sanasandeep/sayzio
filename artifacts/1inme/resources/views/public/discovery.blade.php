@extends('public.layouts.site')
@section('content')
{{-- HERO --}}
<section class="relative pt-20 pb-12 lg:pt-28 lg:pb-14 overflow-hidden">
    <div class="mesh-bg"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid lg:grid-cols-2 gap-10 items-center">
        <div data-anim="fade-right">
            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-violet-500/10 border border-violet-400/20 text-xs text-violet-300 uppercase tracking-wider font-semibold">
                <i class="fas fa-compass text-[10px]"></i> Public directory
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
            @if($showSearch)
                <form method="GET" action="{{ route('site.discovery') }}" class="mt-7 flex gap-2 max-w-lg">
                    <input type="text" name="q" value="{{ $q }}" placeholder="Search creators, handles, topics…"
                           class="flex-1 px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-sm text-white placeholder-white/40 focus:outline-none focus:border-violet-500 transition">
                    <button type="submit" class="px-5 py-3 bg-violet-600 hover:bg-violet-700 text-white rounded-xl text-sm font-bold transition">
                        <i class="fas fa-search mr-1"></i> Search
                    </button>
                </form>
            @endif
            <div class="mt-7 flex items-center gap-6 text-sm">
                <div><div class="text-2xl font-bold"><span data-count="{{ $biolinks->total() }}"></span></div><div class="text-xs uppercase tracking-wider text-gray-500 mt-0.5">Public profiles</div></div>
                <div class="w-px h-10 bg-white/10"></div>
                <div><div class="text-2xl font-bold"><span data-count="120000" data-count-suffix="+"></span></div><div class="text-xs uppercase tracking-wider text-gray-500 mt-0.5">Followers</div></div>
            </div>
        </div>
        <div data-anim="fade-left" data-tilt="6" class="relative">
            <div class="img-frame img-tilt aspect-[5/4]">
                <img src="{{ asset('images/marketing/discovery/hero.png') }}" alt="Public Sayzio creator profiles">
            </div>
            <div class="absolute -bottom-5 -left-5 bg-[#11101c] border border-white/10 rounded-2xl p-3 pr-4 flex items-center gap-3 shadow-2xl float-y">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 pulse-dot text-emerald-400/40"></span>
                <span class="text-xs font-semibold text-gray-200">42 creators joined this week</span>
            </div>
        </div>
    </div>
</section>

{{-- GRID --}}
<section class="pb-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @if($biolinks->isEmpty())
            <div class="text-center text-gray-500 text-sm py-16" data-anim="fade-up">
                @if($q !== '')
                    No Link in Bio pages match "<span class="text-white">{{ $q }}</span>" yet.
                @else
                    No public Link in Bio pages yet — check back soon.
                @endif
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5" data-anim="fade-up" data-stagger>
                @foreach($biolinks as $b)
                    @php($u = $b->user)
                    <a href="/{{ $b->alias }}" target="_blank" rel="noopener"
                       class="group bg-white/[0.03] hover:bg-white/[0.06] border border-white/10 hover:border-violet-500/40 rounded-2xl p-5 transition-all duration-300 hover:-translate-y-1 flex flex-col">
                        <div class="flex items-center gap-3">
                            @if($u && $u->avatar)
                                <img src="{{ $u->avatar }}" alt="{{ $u?->name ?? 'Creator' }}" class="w-11 h-11 rounded-full object-cover ring-2 ring-white/5 group-hover:ring-violet-400/40 transition">
                            @else
                                <div class="w-11 h-11 rounded-full bg-gradient-to-br from-violet-500 to-fuchsia-500 flex items-center justify-center text-white text-sm font-bold ring-2 ring-white/5 group-hover:ring-violet-400/40 transition">
                                    {{ strtoupper(mb_substr($u?->name ?? $b->title ?? '?', 0, 1)) }}
                                </div>
                            @endif
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-white truncate group-hover:text-violet-300 transition">
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

@include('public.partials.subscribe-block', [
    'heading' => 'Discover new creators every week.',
    'subtext' => 'Subscribe by email, WhatsApp Channel, or DM and get fresh creator picks plus product updates.',
    'source'  => 'discovery',
])
@endsection
