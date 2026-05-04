    @php $btnInline = $btnInline ?? ''; @endphp
    @php $_lnkLayout = $block->settings['_style']['link_layout'] ?? ''; @endphp
    @if(!empty($s['is_featured']))
        @php $accent = $s['accent_color'] ?? '#f59e0b'; @endphp
        <a href="{{ $s['url'] ?? '#' }}" target="_blank" rel="noopener"
           class="block w-full mb-3 rounded-2xl overflow-hidden transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl relative"
           style="background: linear-gradient(135deg, {{ $accent }} 0%, {{ $accent }}dd 100%); box-shadow: 0 8px 24px {{ $accent }}55;{{ $btnInline ? ' ' . $btnInline : '' }}">
            <div class="absolute top-2 right-2 px-2 py-0.5 text-[10px] font-bold rounded-full bg-white/25 text-white tracking-wide">
                <i class="fas fa-thumbtack"></i> FEATURED
            </div>
            <div class="px-6 py-5 flex items-center gap-4">
                @if(!empty($s['thumbnail']))<img src="{{ $s['thumbnail'] }}" class="w-12 h-12 rounded-xl object-cover" alt="">
                @elseif(!empty($s['icon']))<div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center"><i class="{{ fa_icon_class($s['icon']) }} text-xl text-white"></i></div>@endif
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-white truncate">{{ $s['text'] ?? 'Link' }}</p>
                    @if(!empty($s['description']))<p class="text-xs text-white/80 mt-0.5 truncate">{{ $s['description'] }}</p>@endif
                </div>
                <i class="fas fa-arrow-right text-white/60"></i>
            </div>
        </a>
    @elseif($_lnkLayout === 'plain_text')
        <a href="{{ $s['url'] ?? '#' }}" target="_blank" rel="noopener"
           class="block mb-3 text-center text-sm font-medium underline decoration-1 underline-offset-4 hover:decoration-2 transition"
           style="color: {{ $block->settings['_style']['text_color'] ?? '#a78bfa' }};">
            @if(!empty($s['icon']))<i class="{{ fa_icon_class($s['icon']) }} mr-1.5"></i>@endif{{ $s['text'] ?? 'Link' }}
        </a>
    @elseif($_lnkLayout === 'image_cover' && !empty($s['thumbnail']))
        <a href="{{ $s['url'] ?? '#' }}" target="_blank" rel="noopener"
           class="block w-full mb-3 rounded-2xl overflow-hidden transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl relative"
           style="aspect-ratio: 16/7; background-image: linear-gradient(180deg, rgba(0,0,0,0.1) 0%, rgba(0,0,0,0.7) 100%), url('{{ $s['thumbnail'] }}'); background-size: cover; background-position: center;{{ $btnInline ? ' ' . $btnInline : '' }}">
            <div class="absolute inset-0 flex items-end p-5">
                <div class="flex items-center gap-2 text-white font-bold drop-shadow-lg">
                    @if(!empty($s['icon']))<i class="{{ fa_icon_class($s['icon']) }}"></i>@endif
                    <span>{{ $s['text'] ?? 'Link' }}</span>
                </div>
            </div>
        </a>
    @else
        <a href="{{ $s['url'] ?? '#' }}" target="_blank" rel="noopener"
           class="bio-btn block w-full px-6 py-3.5 mb-3 text-center font-medium transition-all duration-300 flex items-center justify-center gap-3"
           @if($btnInline) style="{{ $btnInline }}" @endif>
            @if(!empty($s['thumbnail']))<img src="{{ $s['thumbnail'] }}" class="w-6 h-6 rounded object-cover" alt="">
            @elseif(!empty($s['icon']))<i class="{{ fa_icon_class($s['icon']) }}"></i>@endif
            <span>{{ $s['text'] ?? 'Link' }}</span>
        </a>
    @endif
