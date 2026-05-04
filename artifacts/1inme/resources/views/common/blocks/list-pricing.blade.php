    @php
        $_pStyle = $s['style'] ?? 'classic';
        $_pItems = array_map(fn($i) => is_array($i) ? [
            'name'        => (string)($i['name'] ?? ''),
            'description' => (string)($i['description'] ?? ''),
            'price'       => (string)($i['price'] ?? ''),
            'period'      => (string)($i['period'] ?? ''),
            'included'    => (bool)($i['included'] ?? true),
            'featured'    => (bool)($i['featured'] ?? false),
            'thumbnail'   => (string)($i['thumbnail'] ?? ''),
            'icon'        => (string)($i['icon'] ?? ''),
        ] : ['name' => (string)$i, 'description' => '', 'price' => '', 'period' => '', 'included' => true, 'featured' => false, 'thumbnail' => '', 'icon' => ''], (array)($s['items'] ?? []));
        $_pAccent = $btnColor ?? '#7c3aed';
    @endphp

    @switch($_pStyle)
        @case('menu')
            <div class="mb-4 glass-block rounded-xl p-4 space-y-3">
                @foreach($_pItems as $it)
                    <div class="flex items-start gap-3" style="color: {{ $fontColor }}dd;">
                        @if($it['thumbnail'])
                            <img src="{{ $it['thumbnail'] }}" alt="" class="w-12 h-12 rounded-lg object-cover flex-shrink-0">
                        @elseif($it['icon'])
                            <div class="w-12 h-12 rounded-lg flex items-center justify-center flex-shrink-0" style="background: {{ $_pAccent }}22;">
                                <i class="{{ fa_icon_class($it['icon'], 'fas fa-utensils') }}" style="color: {{ $_pAccent }};"></i>
                            </div>
                        @endif
                        <div class="flex-1 min-w-0">
                            <div class="flex items-baseline gap-2">
                                <span class="text-sm font-semibold truncate">{{ $it['name'] }}</span>
                                <span class="flex-1 border-b border-dotted opacity-30" style="border-color: {{ $fontColor }};"></span>
                                <span class="text-sm font-bold whitespace-nowrap" style="color: {{ $_pAccent }};">{{ $it['price'] }}<span class="text-xs opacity-70">{{ $it['period'] }}</span></span>
                            </div>
                            @if($it['description'])
                                <p class="text-xs mt-1" style="color: {{ $fontColor }}99;">{{ $it['description'] }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            @break

        @case('cards')
            <div class="mb-4 grid grid-cols-1 {{ count($_pItems) >= 3 ? 'sm:grid-cols-3' : 'sm:grid-cols-2' }} gap-3">
                @foreach($_pItems as $it)
                    @php $isF = !empty($it['featured']); @endphp
                    <div class="rounded-2xl p-4 flex flex-col text-center relative {{ $isF ? 'sm:-translate-y-1' : '' }}"
                         style="background: {{ $isF ? $_pAccent . '22' : 'rgba(255,255,255,0.04)' }}; border: 1.5px solid {{ $isF ? $_pAccent : 'rgba(255,255,255,0.1)' }}; color: {{ $fontColor }}; {{ $isF ? 'box-shadow: 0 12px 32px -12px ' . $_pAccent . '88;' : '' }}">
                        @if($isF)
                            <span class="absolute -top-2 left-1/2 -translate-x-1/2 px-2 py-0.5 text-[10px] font-bold rounded-full text-white" style="background: {{ $_pAccent }};">POPULAR</span>
                        @endif
                        @if($it['thumbnail'])
                            <img src="{{ $it['thumbnail'] }}" alt="" class="w-12 h-12 rounded-xl object-cover mx-auto mb-2">
                        @elseif($it['icon'])
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center mx-auto mb-2" style="background: {{ $_pAccent }}22;">
                                <i class="{{ fa_icon_class($it['icon'], 'fas fa-tag') }} text-lg" style="color: {{ $_pAccent }};"></i>
                            </div>
                        @endif
                        <div class="text-sm font-semibold">{{ $it['name'] }}</div>
                        @if($it['description'])
                            <p class="text-xs mt-1 opacity-70">{{ $it['description'] }}</p>
                        @endif
                        <div class="mt-3">
                            <span class="text-2xl font-bold" style="color: {{ $isF ? $_pAccent : 'inherit' }};">{{ $it['price'] }}</span>
                            <span class="text-xs opacity-70">{{ $it['period'] }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
            @break

        @case('comparison')
            <div class="mb-4 glass-block rounded-xl overflow-hidden">
                @foreach($_pItems as $idx => $it)
                    <div class="flex items-center gap-3 px-4 py-3" @if($idx > 0) style="border-top: 1px solid {{ $fontColor }}1a; color: {{ $fontColor }};" @else style="color: {{ $fontColor }};" @endif>
                        @if($it['included'])
                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full flex-shrink-0" style="background: rgba(34,197,94,0.18);">
                                <i class="fas fa-check text-[10px]" style="color: #22c55e;"></i>
                            </span>
                        @else
                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full flex-shrink-0" style="background: rgba(239,68,68,0.15);">
                                <i class="fas fa-times text-[10px]" style="color: #ef4444;"></i>
                            </span>
                        @endif
                        <div class="flex-1 min-w-0 {{ $it['included'] ? '' : 'opacity-50' }}">
                            <div class="text-sm font-medium truncate">{{ $it['name'] }}</div>
                            @if($it['description'])
                                <div class="text-[11px] opacity-70 truncate">{{ $it['description'] }}</div>
                            @endif
                        </div>
                        @if($it['price'])
                            <span class="text-sm font-semibold whitespace-nowrap {{ $it['included'] ? '' : 'opacity-50' }}" style="color: {{ $_pAccent }};">{{ $it['price'] }}<span class="text-xs opacity-70">{{ $it['period'] }}</span></span>
                        @endif
                    </div>
                @endforeach
            </div>
            @break

        @case('featured')
            @php
                $_feat = collect($_pItems)->first(fn($i) => !empty($i['featured'])) ?? ($_pItems[0] ?? null);
                $_others = array_values(array_filter($_pItems, fn($i) => $i !== $_feat));
            @endphp
            <div class="mb-4 space-y-3">
                @if($_feat)
                    <div class="rounded-2xl p-5 text-center relative overflow-hidden"
                         style="background: linear-gradient(135deg, {{ $_pAccent }}, {{ $_pAccent }}dd); color: #fff; box-shadow: 0 12px 32px -8px {{ $_pAccent }}88;">
                        <span class="absolute top-2 right-3 px-2 py-0.5 text-[10px] font-bold rounded-full bg-white/25 tracking-wide">★ FEATURED</span>
                        <div class="text-base font-bold">{{ $_feat['name'] }}</div>
                        @if($_feat['description'])
                            <p class="text-sm mt-1 opacity-90">{{ $_feat['description'] }}</p>
                        @endif
                        <div class="mt-3"><span class="text-3xl font-extrabold">{{ $_feat['price'] }}</span><span class="text-sm opacity-80">{{ $_feat['period'] }}</span></div>
                    </div>
                @endif
                @if(!empty($_others))
                    <div class="glass-block rounded-xl overflow-hidden">
                        @foreach($_others as $idx => $it)
                            <div class="flex items-baseline gap-2 px-4 py-3" @if($idx > 0) style="border-top: 1px solid {{ $fontColor }}1a; color: {{ $fontColor }}dd;" @else style="color: {{ $fontColor }}dd;" @endif>
                                <span class="text-sm font-medium">{{ $it['name'] }}</span>
                                @if($it['description'])
                                    <span class="text-xs opacity-60 truncate">— {{ $it['description'] }}</span>
                                @endif
                                <span class="flex-1 border-b border-dotted opacity-30" style="border-color: {{ $fontColor }};"></span>
                                <span class="text-sm font-semibold whitespace-nowrap" style="color: {{ $_pAccent }};">{{ $it['price'] }}<span class="text-xs opacity-70">{{ $it['period'] }}</span></span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
            @break

        @default
            {{-- classic: name + dot leaders + price --}}
            <div class="mb-4 glass-block rounded-xl p-4 space-y-2">
                @foreach($_pItems as $it)
                    <div class="flex items-baseline gap-2 text-sm {{ $it['included'] ? '' : 'opacity-50 line-through' }}" style="color: {{ $fontColor }}dd;">
                        <span class="font-medium">{{ $it['name'] }}</span>
                        <span class="flex-1 border-b border-dotted opacity-30" style="border-color: {{ $fontColor }};"></span>
                        <span class="font-bold whitespace-nowrap" style="color: {{ $_pAccent }};">{{ $it['price'] }}<span class="text-xs opacity-70">{{ $it['period'] }}</span></span>
                    </div>
                @endforeach
            </div>
    @endswitch
