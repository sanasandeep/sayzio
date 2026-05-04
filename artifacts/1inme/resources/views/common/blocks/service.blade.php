    <div class="mb-4 glass-block rounded-xl p-4">
        <div class="flex items-start gap-3">
            <div class="w-12 h-12 rounded-xl bg-purple-500/20 flex items-center justify-center flex-shrink-0"><i class="{{ fa_icon_class($s['icon'] ?? 'fas fa-star', 'fas fa-star') }} text-purple-400"></i></div>
            <div class="flex-1">
                <p class="font-semibold text-sm">{{ $s['name'] ?? '' }}</p>
                @if(!empty($s['price']))<p class="text-xs text-purple-400 mt-0.5">{{ $s['price'] }}</p>@endif
                @if(!empty($s['description']))<p class="text-xs mt-1" style="color:{{ $fontColor }}88">{{ $s['description'] }}</p>@endif
            </div>
        </div>
        @if(!empty($s['url']))<a href="{{ $s['url'] }}" target="_blank" class="bio-btn block w-full text-center mt-3 py-2 text-sm font-medium">Learn More</a>@endif
    </div>
