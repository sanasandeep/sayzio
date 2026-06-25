@php
    /** @var array $s */
    /** @var string $fontColor */
    $name   = trim($s['name'] ?? '');
    $items  = is_array($s['items'] ?? null) ? $s['items'] : [];
    $accent = $s['accent_color'] ?? '#3d6bff';
    $layout = $s['layout'] ?? 'plain';
@endphp

<div class="mb-4 {{ $layout === 'card' ? 'glass-block rounded-xl p-4' : '' }}">
    @if($name !== '')
        <p class="text-xs uppercase tracking-wider mb-2 font-semibold"
           style="color: {{ $accent }};">{{ $name }}</p>
    @endif
    <div class="space-y-2">
        @foreach($items as $it)
            <div class="flex items-baseline gap-2" style="color: {{ $fontColor }};">
                <span class="text-sm font-medium truncate">{{ $it['name'] ?? '' }}</span>
                <span class="flex-1 border-b border-dotted opacity-30"
                      style="border-color: {{ $fontColor }};"></span>
                @if(!empty($it['price']))
                    <span class="text-sm font-semibold whitespace-nowrap"
                          style="color: {{ $accent }};">{{ $it['price'] }}</span>
                @endif
            </div>
            @if(!empty($it['description']))
                <p class="text-xs opacity-60 -mt-1" style="color: {{ $fontColor }};">{{ $it['description'] }}</p>
            @endif
        @endforeach
    </div>
</div>
