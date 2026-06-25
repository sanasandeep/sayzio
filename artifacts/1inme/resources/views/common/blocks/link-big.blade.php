    @php $btnInline = $btnInline ?? ''; $_lnkLayout = $block->settings['_style']['link_layout'] ?? ''; @endphp
    @if($_lnkLayout === 'plain_text')
        <a href="{{ $s['url'] ?? '#' }}" target="_blank" rel="noopener"
           class="block mb-3 text-center text-base font-semibold underline decoration-1 underline-offset-4 hover:decoration-2 transition"
           style="color: {{ $block->settings['_style']['text_color'] ?? '#90acff' }};">
            @if(!empty($s['icon']))<i class="{{ fa_icon_class($s['icon']) }} mr-1.5"></i>@endif{{ $s['text'] ?? 'Link' }}
            @if(!empty($s['description']))<div class="text-xs font-normal opacity-70 mt-1 no-underline">{{ $s['description'] }}</div>@endif
        </a>
    @elseif($_lnkLayout === 'image_cover' && !empty($s['thumbnail']))
        <a href="{{ $s['url'] ?? '#' }}" target="_blank" rel="noopener"
           class="block w-full mb-3 rounded-2xl overflow-hidden transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl relative"
           style="aspect-ratio: 16/9; background-image: linear-gradient(180deg, rgba(0,0,0,0.1) 0%, rgba(0,0,0,0.75) 100%), url('{{ $s['thumbnail'] }}'); background-size: cover; background-position: center;{{ $btnInline ? ' ' . $btnInline : '' }}">
            <div class="absolute inset-0 flex flex-col justify-end p-5">
                <p class="font-bold text-white text-lg drop-shadow-lg">{{ $s['text'] ?? 'Link' }}</p>
                @if(!empty($s['description']))<p class="text-sm text-white/80 mt-1 drop-shadow">{{ $s['description'] }}</p>@endif
            </div>
        </a>
    @else
        <a href="{{ $s['url'] ?? '#' }}" target="_blank" rel="noopener"
           class="block w-full mb-3 rounded-2xl overflow-hidden transition-all duration-300 hover:-translate-y-1 hover:shadow-lg"
           style="background: {{ $s['bg_color'] ?? ($btnColor ?? '#3d6bff') }};{{ $btnInline ? ' ' . $btnInline : '' }}">
            <div class="px-6 py-5 flex items-center gap-4">
                @if(!empty($s['thumbnail']))<img src="{{ $s['thumbnail'] }}" class="w-12 h-12 rounded-xl object-cover" alt="">
                @elseif(!empty($s['icon']))<div class="w-12 h-12 rounded-xl bg-white/10 flex items-center justify-center"><i class="{{ fa_icon_class($s['icon']) }} text-xl"></i></div>@endif
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-white truncate">{{ $s['text'] ?? 'Link' }}</p>
                    @if(!empty($s['description']))<p class="text-xs text-white/60 mt-0.5 truncate">{{ $s['description'] }}</p>@endif
                </div>
                <i class="fas fa-arrow-right text-white/40"></i>
            </div>
        </a>
    @endif
