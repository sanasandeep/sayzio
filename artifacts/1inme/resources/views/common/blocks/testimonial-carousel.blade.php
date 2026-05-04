@php
    $items  = is_array($s['items'] ?? null) ? $s['items'] : [];
    $layout = $s['layout'] ?? ($s['_registry']['layout'] ?? 'carousel');
    $accent = $s['accent_color'] ?? '#7c3aed';
@endphp

@if($layout === 'stack')
    <div class="mb-4 space-y-3">
        @foreach($items as $t)
            <div class="rounded-2xl p-4 border" style="background: {{ $fontColor }}08; border-color: {{ $fontColor }}1a;">
                <p class="text-sm italic leading-relaxed mb-2" style="color: {{ $fontColor }};">&ldquo;{{ $t['quote'] ?? '' }}&rdquo;</p>
                <div class="flex items-center gap-2">
                    @if(!empty($t['avatar']))
                        <img src="{{ $t['avatar'] }}" alt="" class="w-8 h-8 rounded-full object-cover">
                    @endif
                    <div>
                        <div class="text-xs font-semibold" style="color: {{ $fontColor }};">{{ $t['name'] ?? '' }}</div>
                        @if(!empty($t['title']))<div class="text-[10px] opacity-60" style="color: {{ $fontColor }};">{{ $t['title'] }}</div>@endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@else
    <div class="mb-4" x-data="{ idx: 0, count: {{ count($items) }} }">
        @if(empty($items))
            <div class="glass-block rounded-xl p-4 text-center text-xs opacity-50" style="color: {{ $fontColor }};">No testimonials yet</div>
        @else
            <div class="relative glass-block rounded-2xl p-5 overflow-hidden">
                @foreach($items as $i => $t)
                    <div x-show="idx === {{ $i }}" class="text-center">
                        <i class="fas fa-quote-left text-2xl mb-2" style="color: {{ $accent }};"></i>
                        <p class="text-sm italic leading-relaxed mb-3" style="color: {{ $fontColor }};">&ldquo;{{ $t['quote'] ?? '' }}&rdquo;</p>
                        @if(!empty($t['avatar']))
                            <img src="{{ $t['avatar'] }}" alt="" class="w-12 h-12 rounded-full object-cover mx-auto mb-1">
                        @endif
                        <div class="text-xs font-semibold" style="color: {{ $fontColor }};">{{ $t['name'] ?? '' }}</div>
                        @if(!empty($t['title']))<div class="text-[10px] opacity-60" style="color: {{ $fontColor }};">{{ $t['title'] }}</div>@endif
                    </div>
                @endforeach
                @if(count($items) > 1)
                    <div class="flex items-center justify-center gap-3 mt-4">
                        <button type="button" @click="idx = (idx - 1 + count) % count" class="w-8 h-8 rounded-full flex items-center justify-center" style="background: {{ $fontColor }}10; color: {{ $fontColor }};">
                            <i class="fas fa-chevron-left text-xs"></i>
                        </button>
                        <div class="flex gap-1.5">
                            @foreach($items as $i => $_)
                                <button type="button" @click="idx = {{ $i }}"
                                        class="w-1.5 h-1.5 rounded-full transition"
                                        :style="idx === {{ $i }} ? 'background: {{ $accent }}; width: 16px' : 'background: {{ $fontColor }}40'"></button>
                            @endforeach
                        </div>
                        <button type="button" @click="idx = (idx + 1) % count" class="w-8 h-8 rounded-full flex items-center justify-center" style="background: {{ $fontColor }}10; color: {{ $fontColor }};">
                            <i class="fas fa-chevron-right text-xs"></i>
                        </button>
                    </div>
                @endif
            </div>
        @endif
    </div>
@endif
