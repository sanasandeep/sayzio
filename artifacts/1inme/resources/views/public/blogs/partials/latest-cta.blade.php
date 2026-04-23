@php
    /** @var \Illuminate\Support\Collection|null $latestCta */
    /** @var bool $blogCtaEnabled */
    $blogCtaEnabled = $blogCtaEnabled ?? false;
    $latestCta      = $latestCta      ?? collect();
@endphp
@if($blogCtaEnabled && $latestCta->count())
    <section class="max-w-6xl mx-auto px-4 sm:px-6 my-16">
        <div class="rounded-3xl bg-gradient-to-br from-violet-600/15 via-pink-500/10 to-sky-500/10 border border-white/10 p-6 sm:p-8">
            <div class="flex items-end justify-between gap-4 mb-6">
                <div>
                    <p class="text-[11px] uppercase tracking-[0.2em] text-violet-300">From the blog</p>
                    <h3 class="mt-1 text-2xl sm:text-3xl font-bold text-white">Latest stories &amp; tips</h3>
                </div>
                <a href="{{ route('site.blogs.index') }}" class="text-sm text-white/80 hover:text-white">All posts <i class="fas fa-arrow-right ml-1 text-xs"></i></a>
            </div>
            <div class="grid sm:grid-cols-3 gap-4">
                @foreach($latestCta as $p)
                    <a href="{{ route('site.blogs.show', $p->slug) }}" class="block bg-white/[0.04] hover:bg-white/[0.07] border border-white/10 rounded-2xl p-4 transition">
                        @if($p->category)<span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full" style="background: {{ $p->category->color ? $p->category->color . '22' : 'rgba(124,58,237,.15)' }}; color: {{ $p->category->color ?: '#a78bfa' }};">{{ $p->category->name }}</span>@endif
                        <h4 class="mt-2 text-sm font-semibold text-white line-clamp-2">{{ $p->title }}</h4>
                        @if($p->excerpt)<p class="mt-1 text-xs text-white/60 line-clamp-2">{{ $p->excerpt }}</p>@endif
                        <p class="mt-3 text-[11px] text-white/40"><i class="far fa-clock mr-1"></i>{{ $p->reading_time_min }} min read</p>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endif
