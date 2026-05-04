    @php
        app(\App\Modules\User\Services\BiolinkLatestContentService::class)->refreshIfStale($block);
        $s = $block->settings ?? $s;
        $vid = $s['video_id'] ?? '';
    @endphp
    <div class="mb-4 glass-block rounded-xl overflow-hidden">
        @if($vid)
            <div class="relative" style="padding-bottom:56.25%; height:0;">
                <iframe src="https://www.youtube.com/embed/{{ $vid }}" frameborder="0" allow="accelerometer; clipboard-write; encrypted-media; picture-in-picture" allowfullscreen
                        class="absolute inset-0 w-full h-full"></iframe>
            </div>
            @if(!empty($s['title']))<div class="p-3 text-sm font-medium">{{ $s['title'] }}</div>@endif
        @elseif(!empty($s['channel']))
            <a href="https://youtube.com/{{ ltrim($s['channel'], '@/') }}" target="_blank" rel="noopener" class="block p-4 text-center">
                <i class="fab fa-youtube text-3xl text-red-500 mb-2"></i>
                <div class="text-sm font-medium">Latest from @{{ ltrim($s['channel'], '@/') }}</div>
                <div class="text-xs opacity-60 mt-1">Open channel</div>
            </a>
        @else
            <div class="p-4 text-center text-xs text-white/40">Add a YouTube channel handle</div>
        @endif
    </div>
