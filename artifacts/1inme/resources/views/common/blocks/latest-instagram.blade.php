    @php
        app(\App\Modules\User\Services\BiolinkLatestContentService::class)->refreshIfStale($block);
        $s = $block->settings ?? $s;
        $url = $s['post_url'] ?? '';
    @endphp
    <div class="mb-4 glass-block rounded-xl overflow-hidden">
        @if($url)
            <a href="{{ $url }}" target="_blank" rel="noopener" class="block">
                @if(!empty($s['thumbnail']))
                    <img src="{{ $s['thumbnail'] }}" alt="" class="w-full object-cover" style="aspect-ratio:1/1;">
                @else
                    <div class="flex items-center justify-center text-white/40 bg-gradient-to-br from-pink-500/20 to-purple-500/20" style="aspect-ratio:1/1;">
                        <i class="fab fa-instagram text-5xl"></i>
                    </div>
                @endif
                @if(!empty($s['caption']))<div class="p-3 text-sm line-clamp-2">{{ $s['caption'] }}</div>@endif
            </a>
        @elseif(!empty($s['handle']))
            <a href="https://instagram.com/{{ ltrim($s['handle'], '@/') }}" target="_blank" rel="noopener" class="block p-4 text-center">
                <i class="fab fa-instagram text-3xl mb-2" style="color:#E1306C"></i>
                <div class="text-sm font-medium">@{{ ltrim($s['handle'], '@/') }} on Instagram</div>
            </a>
        @else
            <div class="p-4 text-center text-xs text-white/40">Add an Instagram handle</div>
        @endif
    </div>
