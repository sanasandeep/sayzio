    <div class="mb-4 glass-block rounded-xl overflow-hidden">
        @if(!empty($s['image']))<img src="{{ $s['image'] }}" alt="{{ $s['name'] ?? '' }}" class="w-full h-48 object-cover">@endif
        <div class="p-4">
            <div class="flex items-start justify-between">
                <div><p class="font-semibold text-sm">{{ $s['name'] ?? '' }}</p>@if(!empty($s['badge']))<span class="inline-block mt-1 rounded-full px-2 py-0.5 text-xs" style="background:#eef2ff;color:#3730a3">{{ $s['badge'] }}</span>@endif</div>
                @if(!empty($s['price']))<span class="font-bold text-lg">{{ $s['price'] }}</span>@elseif(!empty($s['native_checkout']) && (int)($s['price_cents'] ?? 0) > 0)<span class="font-bold text-lg">{{ $s['currency'] ?? 'USD' }} {{ number_format(($s['price_cents'] ?? 0) / 100, 2) }}</span>@endif
            </div>
            @if(!empty($s['description']))<p class="text-xs mt-2" style="color:color-mix(in srgb, {{ $fontColor }} 53.33%, transparent)">{{ $s['description'] }}</p>@endif
            @if(!empty($s['native_checkout']) && (int)($s['price_cents'] ?? 0) > 0)
                <button type="button" x-data @click="$store.bioStore.{{ !empty($__storeMultiple) ? 'add' : 'buy' }}({{ (int) $block->id }})" :disabled="$store.bioStore.busy" class="bio-btn block w-full text-center mt-3 py-2.5 text-sm font-medium">{{ !empty($__storeMultiple) ? 'Add to Cart' : 'Buy Now' }}</button>
            @elseif(!empty($s['url']))<a href="{{ $s['url'] }}" target="_blank" class="bio-btn block w-full text-center mt-3 py-2.5 text-sm font-medium">Buy Now</a>@endif
        </div>
    </div>
