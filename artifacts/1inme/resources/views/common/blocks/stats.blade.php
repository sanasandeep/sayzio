@php
    $items  = is_array($s['items'] ?? null) ? $s['items'] : [];
    $layout = $s['layout'] ?? ($s['_registry']['layout'] ?? 'row');
    $accent = $s['accent_color'] ?? '#3d6bff';
    $title  = trim($s['title'] ?? '');
@endphp

<div class="mb-4">
    @if($title !== '')
        <p class="text-sm font-semibold mb-2 text-center" style="color: {{ $fontColor }};">{{ $title }}</p>
    @endif
    <div class="@if($layout === 'grid') grid grid-cols-2 sm:grid-cols-3 gap-3 @else flex justify-around gap-3 glass-block rounded-xl p-4 @endif">
        @foreach($items as $it)
            <div class="text-center @if($layout === 'grid') glass-block rounded-xl p-3 @endif">
                <div class="text-2xl font-bold" style="color: {{ $accent }};">{{ $it['value'] ?? '0' }}</div>
                <div class="text-[10px] uppercase tracking-wider opacity-70 mt-0.5" style="color: {{ $fontColor }};">{{ $it['label'] ?? '' }}</div>
                @if(!empty($it['caption']))
                    <div class="text-[10px] opacity-50 mt-0.5" style="color: {{ $fontColor }};">{{ $it['caption'] }}</div>
                @endif
            </div>
        @endforeach
    </div>
</div>
