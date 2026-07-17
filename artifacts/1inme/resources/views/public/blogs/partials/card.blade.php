<a href="{{ route('site.blogs.show', $post->slug) }}" class="group block bg-white/[0.03] border border-white/10 rounded-2xl overflow-hidden hover:border-blue-500/40 transition">
    @if($post->cover_image)
        <div class="aspect-[16/9] bg-white/5 overflow-hidden">
            <img src="{{ \App\Support\PublicStorageUrl::resolve($post->cover_image) }}" alt="" class="w-full h-full object-cover group-hover:scale-105 transition duration-500" loading="lazy">
        </div>
    @else
        <div class="aspect-[16/9] flex items-center justify-center" style="background:rgba(61,107,255,.18);">
            <i class="fas fa-feather-pointed text-3xl text-white/40"></i>
        </div>
    @endif
    <div class="p-5">
        @if($post->category)
            <span class="inline-block text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded-full" style="background: {{ $post->category->color ? $post->category->color . '22' : 'rgba(61,107,255,.15)' }}; color: {{ $post->category->color ?: '#90acff' }};">{{ $post->category->name }}</span>
        @endif
        <h3 class="mt-2 text-lg font-semibold text-white group-hover:text-blue-300 transition line-clamp-2">{{ $post->title }}</h3>
        @if($post->excerpt)
            <p class="mt-2 text-sm text-gray-400 line-clamp-3">{{ $post->excerpt }}</p>
        @endif
        @if($post->author)
            <div class="mt-3 flex items-center gap-2 text-[11px] text-white/60">
                @if(!empty($post->author->avatar))
                    <img src="{{ \App\Support\PublicStorageUrl::resolve($post->author->avatar) }}" alt="" class="w-5 h-5 rounded-full object-cover">
                @else
                    <span class="w-5 h-5 rounded-full bg-blue-600/40 inline-flex items-center justify-center text-[9px] font-bold text-white">{{ strtoupper(mb_substr($post->author->name ?? '?', 0, 1)) }}</span>
                @endif
                <span>{{ $post->author->name }}</span>
            </div>
        @endif
        <div class="mt-4 flex items-center justify-between text-[11px] text-white/50">
            <span>{{ optional($post->published_at)->format('M j, Y') }}</span>
            <span>{{ $post->reading_time_min }} min read</span>
        </div>
    </div>
</a>
