    <div class="mb-4 space-y-3">
        @foreach(($s['items'] ?? []) as $item)
        <div class="glass-block rounded-xl p-4">
            <div class="flex items-center gap-3 mb-2">
                @if(!empty($item['avatar']))<img src="{{ $item['avatar'] }}" class="w-10 h-10 rounded-full object-cover" alt="">
                @else<div class="w-10 h-10 rounded-full bg-indigo-500/20 flex items-center justify-center"><span class="text-sm font-bold">{{ strtoupper(substr($item['name'] ?? 'A', 0, 1)) }}</span></div>@endif
                <div><p class="text-sm font-medium">{{ $item['name'] ?? '' }}</p>
                <div class="flex gap-0.5">@for($star = 1; $star <= 5; $star++)<i class="fas fa-star text-xs {{ $star <= ($item['rating'] ?? 5) ? 'text-yellow-400' : 'text-white/20' }}"></i>@endfor</div></div>
            </div>
            <p class="text-sm" style="color:{{ $fontColor }}cc">{{ $item['text'] ?? '' }}</p>
        </div>
        @endforeach
    </div>
