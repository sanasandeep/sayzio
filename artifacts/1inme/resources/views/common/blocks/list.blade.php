    @php
        // Normalize items so legacy string entries and new {text,icon} entries
        // both work.
        $_rawItems = $s['items'] ?? [];
        $_items = array_map(function ($i) {
            if (is_array($i)) return ['text' => (string)($i['text'] ?? ''), 'icon' => (string)($i['icon'] ?? '')];
            return ['text' => (string)$i, 'icon' => ''];
        }, $_rawItems);
        $_style = $s['style'] ?? 'clean';
        $_defaultIcon = fa_icon_class($s['icon'] ?? 'fa-check', 'fas fa-check');
        $_accent = $btnColor ?? '#3d6bff';
    @endphp

    @if($block->type === 'list')
        @switch($_style)
            @case('boxed')
                <div class="mb-4 grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @foreach($_items as $it)
                        @php $ic = $it['icon'] ? fa_icon_class($it['icon'], $_defaultIcon) : $_defaultIcon; @endphp
                        <div class="rounded-xl px-3 py-3 flex items-start gap-2 text-sm"
                             style="background: {{ $_accent }}14; border: 1px solid {{ $_accent }}33; color: {{ $fontColor }}dd;">
                            <i class="{{ $ic }} mt-0.5 text-xs" style="color: {{ $_accent }};"></i>
                            <span>{{ $it['text'] }}</span>
                        </div>
                    @endforeach
                </div>
                @break
            @case('divided')
                <div class="mb-4 glass-block rounded-xl overflow-hidden">
                    @foreach($_items as $idx => $it)
                        @php $ic = $it['icon'] ? fa_icon_class($it['icon'], $_defaultIcon) : $_defaultIcon; @endphp
                        <div class="flex items-center gap-3 px-4 py-3 text-sm" @if($idx > 0) style="border-top: 1px solid {{ $fontColor }}1a; color: {{ $fontColor }}dd;" @else style="color: {{ $fontColor }}dd;" @endif>
                            <i class="{{ $ic }} text-xs" style="color: {{ $_accent }};"></i>
                            <span class="flex-1">{{ $it['text'] }}</span>
                        </div>
                    @endforeach
                </div>
                @break
            @case('checklist')
                <div class="mb-4 glass-block rounded-xl p-4 space-y-2">
                    @foreach($_items as $it)
                        @php $ic = $it['icon'] ? fa_icon_class($it['icon'], 'fas fa-check') : 'fas fa-check'; @endphp
                        <div class="flex items-start gap-3 text-sm" style="color: {{ $fontColor }}dd;">
                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-md flex-shrink-0 mt-px"
                                  style="background: {{ $_accent }}; color: #fff;">
                                <i class="{{ $ic }} text-[10px]"></i>
                            </span>
                            <span class="flex-1">{{ $it['text'] }}</span>
                        </div>
                    @endforeach
                </div>
                @break
            @case('timeline')
                <div class="mb-4 glass-block rounded-xl p-4">
                    <div class="relative pl-6">
                        <span class="absolute left-2 top-1 bottom-1 w-px" style="background: {{ $_accent }}55;"></span>
                        @foreach($_items as $it)
                            @php $ic = $it['icon'] ? fa_icon_class($it['icon'], $_defaultIcon) : $_defaultIcon; @endphp
                            <div class="relative pb-3 last:pb-0 text-sm" style="color: {{ $fontColor }}dd;">
                                <span class="absolute -left-[18px] top-1 inline-flex items-center justify-center w-4 h-4 rounded-full"
                                      style="background: {{ $_accent }}; box-shadow: 0 0 0 3px {{ $_accent }}22;">
                                    <i class="{{ $ic }} text-[8px] text-white"></i>
                                </span>
                                <span>{{ $it['text'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
                @break
            @default
                <div class="mb-4 glass-block rounded-xl p-4">
                    <ul class="space-y-2">
                        @foreach($_items as $it)
                            @php $ic = $it['icon'] ? fa_icon_class($it['icon'], $_defaultIcon) : $_defaultIcon; @endphp
                            <li class="flex items-start gap-2 text-sm">
                                <i class="{{ $ic }} mt-0.5 text-xs" style="color: {{ $_accent }};"></i>
                                <span style="color: {{ $fontColor }}cc">{{ $it['text'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
        @endswitch
    @else
        @switch($_style)
            @case('boxed')
                <div class="mb-4 grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @foreach($_items as $idx => $it)
                        <div class="rounded-xl px-3 py-3 flex items-start gap-3 text-sm"
                             style="background: {{ $_accent }}14; border: 1px solid {{ $_accent }}33; color: {{ $fontColor }}dd;">
                            <span class="font-bold text-xs" style="color: {{ $_accent }};">{{ $idx + 1 }}.</span>
                            <span>{{ $it['text'] }}</span>
                        </div>
                    @endforeach
                </div>
                @break
            @case('divided')
                <div class="mb-4 glass-block rounded-xl overflow-hidden">
                    @foreach($_items as $idx => $it)
                        <div class="flex items-center gap-3 px-4 py-3 text-sm" @if($idx > 0) style="border-top: 1px solid {{ $fontColor }}1a; color: {{ $fontColor }}dd;" @else style="color: {{ $fontColor }}dd;" @endif>
                            <span class="font-bold text-xs w-5 text-right" style="color: {{ $_accent }};">{{ $idx + 1 }}</span>
                            <span class="flex-1">{{ $it['text'] }}</span>
                        </div>
                    @endforeach
                </div>
                @break
            @case('pill')
                <div class="mb-4 glass-block rounded-xl p-4 space-y-2">
                    @foreach($_items as $idx => $it)
                        <div class="flex items-start gap-3 text-sm" style="color: {{ $fontColor }}dd;">
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-[11px] font-bold flex-shrink-0"
                                  style="background: {{ $_accent }}; color: #fff;">{{ $idx + 1 }}</span>
                            <span class="flex-1 mt-0.5">{{ $it['text'] }}</span>
                        </div>
                    @endforeach
                </div>
                @break
            @case('badge_square')
                <div class="mb-4 glass-block rounded-xl p-4 space-y-2">
                    @foreach($_items as $idx => $it)
                        <div class="flex items-start gap-3 text-sm" style="color: {{ $fontColor }}dd;">
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-md text-[11px] font-bold flex-shrink-0"
                                  style="background: {{ $_accent }}22; color: {{ $_accent }}; border: 1px solid {{ $_accent }}66;">{{ $idx + 1 }}</span>
                            <span class="flex-1 mt-0.5">{{ $it['text'] }}</span>
                        </div>
                    @endforeach
                </div>
                @break
            @case('outlined')
                <div class="mb-4 glass-block rounded-xl p-4 space-y-3">
                    @foreach($_items as $idx => $it)
                        <div class="flex items-baseline gap-3 text-sm" style="color: {{ $fontColor }}dd;">
                            <span class="font-extrabold text-3xl leading-none" style="color: transparent; -webkit-text-stroke: 1.5px {{ $_accent }};">{{ str_pad($idx + 1, 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="flex-1">{{ $it['text'] }}</span>
                        </div>
                    @endforeach
                </div>
                @break
            @default
                <div class="mb-4 glass-block rounded-xl p-4">
                    <ol class="space-y-2 list-decimal list-inside">
                        @foreach($_items as $it)
                            <li class="text-sm" style="color: {{ $fontColor }}cc">{{ $it['text'] }}</li>
                        @endforeach
                    </ol>
                </div>
        @endswitch
    @endif
