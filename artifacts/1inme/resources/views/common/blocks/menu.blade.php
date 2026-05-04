@php
    $items   = is_array($s['items'] ?? null) ? $s['items'] : [];
    $sections= is_array($s['sections'] ?? null) ? $s['sections'] : [];
    $layout  = $s['layout'] ?? ($s['_registry']['layout'] ?? 'classic');
    $accent  = $s['accent_color'] ?? '#7c3aed';
    $title   = trim($s['title'] ?? '');

    // Normalize: when `sections` is empty but `items` exists, wrap into a
    // single unnamed section so the renderers below can share one path.
    if (empty($sections) && !empty($items)) {
        $sections = [['name' => '', 'items' => $items]];
    }
@endphp

<div class="mb-4 glass-block rounded-xl p-4">
    @if($title !== '')
        <h3 class="text-base font-bold mb-3" style="color: {{ $fontColor }};">{{ $title }}</h3>
    @endif

    @forelse($sections as $section)
        @if(!empty($section['name']))
            <h4 class="text-xs font-semibold uppercase tracking-wider mt-3 mb-2" style="color: {{ $accent }};">{{ $section['name'] }}</h4>
        @endif

        @if($layout === 'cards')
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-2">
                @foreach($section['items'] ?? [] as $it)
                    <div class="rounded-xl p-3 border" style="border-color: {{ $fontColor }}1a; background: {{ $fontColor }}08;">
                        @if(!empty($it['thumbnail']))
                            <img src="{{ $it['thumbnail'] }}" alt="" class="w-full h-24 rounded-lg object-cover mb-2">
                        @endif
                        <div class="flex items-baseline justify-between gap-2">
                            <span class="text-sm font-semibold truncate" style="color: {{ $fontColor }};">{{ $it['name'] ?? '' }}</span>
                            <span class="text-sm font-bold whitespace-nowrap" style="color: {{ $accent }};">{{ $it['price'] ?? '' }}</span>
                        </div>
                        @if(!empty($it['description']))
                            <p class="text-xs mt-1 opacity-70" style="color: {{ $fontColor }};">{{ $it['description'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="space-y-2 mb-2">
                @foreach($section['items'] ?? [] as $it)
                    <div class="flex items-baseline gap-2 text-sm" style="color: {{ $fontColor }}dd;">
                        <span class="font-medium">{{ $it['name'] ?? '' }}</span>
                        <span class="flex-1 border-b border-dotted opacity-30" style="border-color: {{ $fontColor }};"></span>
                        <span class="font-bold whitespace-nowrap" style="color: {{ $accent }};">{{ $it['price'] ?? '' }}</span>
                    </div>
                    @if(!empty($it['description']))
                        <p class="text-xs opacity-60 -mt-1" style="color: {{ $fontColor }};">{{ $it['description'] }}</p>
                    @endif
                @endforeach
            </div>
        @endif
    @empty
        <p class="text-xs opacity-50 text-center py-4" style="color: {{ $fontColor }};">No menu items yet</p>
    @endforelse
</div>
