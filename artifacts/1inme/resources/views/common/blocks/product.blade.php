    <div class="mb-4 glass-block rounded-xl overflow-hidden">
        @if(!empty($s['image']))<img src="{{ $s['image'] }}" alt="{{ $s['name'] ?? '' }}" class="w-full h-48 object-cover">@endif
        <div class="p-4">
            <div class="flex items-start justify-between">
                <div><p class="font-semibold text-sm">{{ $s['name'] ?? '' }}</p></div>
                @if(!empty($s['price']))<span class="font-bold text-lg">{{ $s['price'] }}</span>@endif
            </div>
            @if(!empty($s['description']))<p class="text-xs mt-2" style="color:{{ $fontColor }}88">{{ $s['description'] }}</p>@endif
            @if(!empty($s['url']))<a href="{{ $s['url'] }}" target="_blank" class="bio-btn block w-full text-center mt-3 py-2.5 text-sm font-medium">Buy Now</a>@endif
        </div>
    </div>
