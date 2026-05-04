@php
    $tabs   = is_array($s['tabs'] ?? null) ? $s['tabs'] : [];
    $layout = $s['layout'] ?? ($s['_registry']['layout'] ?? 'tabs');
    $accent = $s['accent_color'] ?? '#7c3aed';
    $tabsId = 'tabs_' . $block->id;
@endphp

<div class="mb-4 glass-block rounded-xl p-4" x-data="{ active: 0 }" id="{{ $tabsId }}">
    @if(!empty($tabs))
        <div class="flex gap-2 mb-3 overflow-x-auto"
             @class([
                 'border-b' => $layout === 'underline',
             ])
             style="border-color: {{ $fontColor }}1a;">
            @foreach($tabs as $idx => $t)
                <button type="button" @click="active = {{ $idx }}"
                        class="text-xs font-medium whitespace-nowrap transition"
                        :class="{
                            'opacity-100': active === {{ $idx }},
                            'opacity-50': active !== {{ $idx }},
                        }"
                        @if($layout === 'pills')
                            :style="active === {{ $idx }} ? 'background: {{ $accent }}; color:#fff' : 'background: {{ $fontColor }}10; color: {{ $fontColor }}'"
                            class="text-xs font-medium whitespace-nowrap rounded-full px-3 py-1.5 transition"
                        @elseif($layout === 'underline')
                            :style="active === {{ $idx }} ? 'border-bottom: 2px solid {{ $accent }}; color: {{ $fontColor }}' : 'border-bottom: 2px solid transparent; color: {{ $fontColor }}99'"
                            class="text-xs font-medium whitespace-nowrap px-2 pb-2 transition -mb-px"
                        @else
                            :style="active === {{ $idx }} ? 'background: {{ $accent }}22; color: {{ $accent }}' : 'background: transparent; color: {{ $fontColor }}'"
                            class="text-xs font-medium whitespace-nowrap rounded-lg px-3 py-1.5 transition"
                        @endif
                        >
                    {{ $t['label'] ?? 'Tab ' . ($idx + 1) }}
                </button>
            @endforeach
        </div>
        <div>
            @foreach($tabs as $idx => $t)
                <div x-show="active === {{ $idx }}" class="text-sm" style="color: {{ $fontColor }}cc;">
                    @if(!empty($t['html']))
                        {!! strip_tags($t['html'], '<p><br><a><strong><em><u><ul><ol><li><span>') !!}
                    @else
                        <p class="leading-relaxed">{{ $t['text'] ?? '' }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        <p class="text-xs opacity-50 text-center py-3" style="color: {{ $fontColor }};">Add tabs to get started</p>
    @endif
</div>
