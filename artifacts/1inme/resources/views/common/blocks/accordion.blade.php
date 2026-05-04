@php
    $items  = is_array($s['items'] ?? null) ? $s['items'] : [];
    $layout = $s['layout'] ?? ($s['_registry']['layout'] ?? 'plain');
    $accent = $s['accent_color'] ?? '#7c3aed';
@endphp

<div class="mb-4 space-y-2" x-data="{ open: -1 }">
    @foreach($items as $idx => $it)
        <div @class([
                'rounded-xl overflow-hidden border' => $layout === 'cards',
                'border-b' => $layout !== 'cards',
            ])
            style="@if($layout === 'cards') background: {{ $fontColor }}08; border-color: {{ $fontColor }}1a; @else border-color: {{ $fontColor }}1a; @endif color: {{ $fontColor }};">
            <button type="button" @click="open = open === {{ $idx }} ? -1 : {{ $idx }}"
                    class="w-full flex items-center justify-between gap-3 text-left py-3 {{ $layout === 'cards' ? 'px-4' : 'px-1' }}">
                <span class="text-sm font-medium">{{ $it['title'] ?? 'Item ' . ($idx + 1) }}</span>
                <i class="fas fa-chevron-down text-xs opacity-60 transition-transform"
                   :class="{ 'rotate-180': open === {{ $idx }} }"></i>
            </button>
            <div x-show="open === {{ $idx }}" x-collapse class="text-xs opacity-80 pb-3 {{ $layout === 'cards' ? 'px-4' : 'px-1' }}">
                @if(!empty($it['html']))
                    {!! strip_tags($it['html'], '<p><br><a><strong><em><u><ul><ol><li>') !!}
                @else
                    <p class="leading-relaxed">{{ $it['body'] ?? '' }}</p>
                @endif
            </div>
        </div>
    @endforeach
</div>
