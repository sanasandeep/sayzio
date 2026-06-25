@php
    $items   = is_array($s['items'] ?? null) ? $s['items'] : [];
    $layout  = $s['layout'] ?? ($s['_registry']['layout'] ?? 'list');
    $title   = trim($s['title'] ?? '');
    $accent  = $s['accent_color'] ?? '#3d6bff';
@endphp

<div class="mb-4">
    @if($title !== '')
        <p class="text-sm font-semibold mb-2 px-1" style="color: {{ $fontColor }};">{{ $title }}</p>
    @endif

    @if($layout === 'grid')
        <div class="grid grid-cols-2 gap-2">
            @foreach($items as $it)
                <a href="{{ $it['url'] ?? '#' }}" target="_blank" rel="noopener"
                   class="rounded-xl px-3 py-3 text-center text-sm font-medium transition border hover:-translate-y-0.5"
                   style="background: {{ $fontColor }}08; border-color: {{ $fontColor }}1a; color: {{ $fontColor }};">
                    @if(!empty($it['icon']))<i class="{{ fa_icon_class($it['icon']) }} mr-1.5" style="color: {{ $accent }};"></i>@endif
                    <span class="truncate">{{ $it['text'] ?? 'Link' }}</span>
                </a>
            @endforeach
        </div>
    @else
        <div class="space-y-2">
            @foreach($items as $it)
                <a href="{{ $it['url'] ?? '#' }}" target="_blank" rel="noopener"
                   class="block rounded-xl px-4 py-3 transition border hover:-translate-y-0.5"
                   style="background: {{ $fontColor }}08; border-color: {{ $fontColor }}1a; color: {{ $fontColor }};">
                    <div class="flex items-center gap-3">
                        @if(!empty($it['icon']))<i class="{{ fa_icon_class($it['icon']) }} w-5 text-center" style="color: {{ $accent }};"></i>@endif
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium truncate">{{ $it['text'] ?? 'Link' }}</div>
                            @if(!empty($it['description']))<div class="text-xs opacity-60 truncate">{{ $it['description'] }}</div>@endif
                        </div>
                        <i class="fas fa-arrow-right text-xs opacity-40"></i>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
