@php
    $items   = is_array($s['items'] ?? null) ? $s['items'] : [];
    $layout  = $s['layout'] ?? ($s['_registry']['layout'] ?? 'compact');
    $accent  = $s['accent_color'] ?? '#7c3aed';
    $title   = trim($s['title'] ?? '');
    $disclaimer = trim($s['disclaimer'] ?? 'Some links may earn a commission.');
@endphp

<div class="mb-4 glass-block rounded-xl p-4">
    @if($title !== '')
        <p class="text-sm font-semibold mb-2" style="color: {{ $fontColor }};">{{ $title }}</p>
    @endif

    @if($layout === 'grid')
        <div class="grid grid-cols-2 gap-2">
            @foreach($items as $it)
                <a href="{{ $it['url'] ?? '#' }}" target="_blank" rel="noopener sponsored"
                   class="block p-3 rounded-xl border transition text-center" style="border-color: {{ $fontColor }}1a; background: {{ $fontColor }}08; color: {{ $fontColor }};">
                    @if(!empty($it['thumbnail']))
                        <img src="{{ $it['thumbnail'] }}" alt="" class="w-full h-20 rounded-lg object-cover mb-2">
                    @endif
                    <div class="text-xs font-semibold truncate">{{ $it['name'] ?? 'Product' }}</div>
                    @if(!empty($it['price']))<div class="text-xs font-bold mt-0.5" style="color: {{ $accent }};">{{ $it['price'] }}</div>@endif
                    <span class="inline-block mt-1 text-[9px] uppercase tracking-wider opacity-60">Affiliate</span>
                </a>
            @endforeach
        </div>
    @elseif($layout === 'cards')
        <div class="space-y-2">
            @foreach($items as $it)
                <a href="{{ $it['url'] ?? '#' }}" target="_blank" rel="noopener sponsored"
                   class="flex items-center gap-3 p-3 rounded-xl border transition" style="border-color: {{ $fontColor }}1a; background: {{ $fontColor }}08; color: {{ $fontColor }};">
                    @if(!empty($it['thumbnail']))
                        <img src="{{ $it['thumbnail'] }}" alt="" class="w-14 h-14 rounded-lg object-cover flex-shrink-0">
                    @else
                        <div class="w-14 h-14 rounded-lg flex items-center justify-center flex-shrink-0" style="background: {{ $accent }}22;">
                            <i class="fas fa-tag text-lg" style="color: {{ $accent }};"></i>
                        </div>
                    @endif
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-semibold truncate">{{ $it['name'] ?? 'Product' }}</div>
                        @if(!empty($it['merchant']))<div class="text-[11px] opacity-60 truncate">{{ $it['merchant'] }}</div>@endif
                        @if(!empty($it['price']))<div class="text-sm font-bold mt-0.5" style="color: {{ $accent }};">{{ $it['price'] }}</div>@endif
                    </div>
                    <span class="text-[9px] uppercase tracking-wider opacity-60 self-start">Aff.</span>
                </a>
            @endforeach
        </div>
    @else
        <div class="space-y-1">
            @foreach($items as $it)
                <a href="{{ $it['url'] ?? '#' }}" target="_blank" rel="noopener sponsored"
                   class="flex items-center gap-3 px-2 py-2 rounded-lg" style="color: {{ $fontColor }};">
                    <i class="fas fa-tag w-4 text-center text-xs" style="color: {{ $accent }};"></i>
                    <span class="flex-1 text-sm truncate">{{ $it['name'] ?? 'Product' }}</span>
                    @if(!empty($it['price']))<span class="text-xs font-semibold" style="color: {{ $accent }};">{{ $it['price'] }}</span>@endif
                    <span class="text-[9px] uppercase tracking-wider opacity-50">Aff</span>
                </a>
            @endforeach
        </div>
    @endif

    @if($disclaimer !== '')
        <p class="text-[10px] opacity-50 mt-3" style="color: {{ $fontColor }};">{{ $disclaimer }}</p>
    @endif
</div>
