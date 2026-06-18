    @php $btnInline = $btnInline ?? ''; @endphp
    @php $_lnkLayout = $block->settings['_style']['link_layout'] ?? ''; @endphp
    @php
        $_url   = $s['url'] ?? '#';
        $_txt   = $s['text'] ?? 'Link';
        $_icon  = !empty($s['icon']) ? fa_icon_class($s['icon']) : '';
        $_thumb = $s['thumbnail'] ?? '';
        $_st    = $block->settings['_style'] ?? [];
        $_accent = $_st['border_color'] ?? '';
        if ($_accent === '' || $_accent === 'transparent') $_accent = $_st['text_color'] ?? '';
        if ($_accent === '') $_accent = '#7c3aed';
    @endphp
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
    @elseif($_lnkLayout === 'icon_left')
        <a href="{{ $_url }}" target="_blank" rel="noopener"
           class="bio-btn block w-full px-6 py-3.5 mb-3 font-medium transition-all duration-300 flex items-center justify-center gap-3"
           @if($btnInline) style="{{ $btnInline }}" @endif>
            @if($_icon)<i class="{{ $_icon }}"></i>@endif<span>{{ $_txt }}</span>
        </a>
    @elseif($_lnkLayout === 'icon_right')
        <a href="{{ $_url }}" target="_blank" rel="noopener"
           class="bio-btn block w-full px-6 py-3.5 mb-3 font-medium transition-all duration-300 flex items-center justify-center gap-3"
           @if($btnInline) style="{{ $btnInline }}" @endif>
            <span>{{ $_txt }}</span>@if($_icon)<i class="{{ $_icon }}"></i>@else<i class="fas fa-arrow-right"></i>@endif
        </a>
    @elseif($_lnkLayout === 'icon_both')
        <a href="{{ $_url }}" target="_blank" rel="noopener"
           class="bio-btn block w-full px-6 py-3.5 mb-3 font-medium transition-all duration-300 flex items-center justify-between gap-3"
           @if($btnInline) style="{{ $btnInline }}" @endif>
            <i class="{{ $_icon ?: 'fas fa-link' }}"></i><span class="flex-1 text-center">{{ $_txt }}</span><i class="fas fa-chevron-right"></i>
        </a>
    @elseif($_lnkLayout === 'icon_only')
        <a href="{{ $_url }}" target="_blank" rel="noopener" title="{{ $_txt }}" aria-label="{{ $_txt }}"
           class="bio-btn block w-full px-6 py-3.5 mb-3 transition-all duration-300 flex items-center justify-center"
           @if($btnInline) style="{{ $btnInline }}" @endif>
            <i class="{{ $_icon ?: 'fas fa-link' }} text-lg"></i>
        </a>
    @elseif($_lnkLayout === 'icon_circle_left')
        <a href="{{ $_url }}" target="_blank" rel="noopener"
           class="bio-btn relative block w-full px-12 py-3.5 mb-3 font-medium transition-all duration-300 flex items-center justify-center"
           @if($btnInline) style="{{ $btnInline }}" @endif>
            <span class="absolute left-2.5 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full flex items-center justify-center" style="background: {{ $_accent }};"><i class="{{ $_icon ?: 'fas fa-link' }} text-white text-sm"></i></span>
            <span>{{ $_txt }}</span>
        </a>
    @elseif($_lnkLayout === 'icon_circle_right')
        <a href="{{ $_url }}" target="_blank" rel="noopener"
           class="bio-btn relative block w-full px-12 py-3.5 mb-3 font-medium transition-all duration-300 flex items-center justify-center"
           @if($btnInline) style="{{ $btnInline }}" @endif>
            <span>{{ $_txt }}</span>
            <span class="absolute right-2.5 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full flex items-center justify-center" style="background: {{ $_accent }};"><i class="{{ $_icon ?: 'fas fa-link' }} text-white text-sm"></i></span>
        </a>
    @elseif($_lnkLayout === 'icon_box')
        <a href="{{ $_url }}" target="_blank" rel="noopener"
           class="bio-btn relative block w-full px-14 py-3.5 mb-3 font-medium transition-all duration-300 flex items-center"
           @if($btnInline) style="{{ $btnInline }}" @endif>
            <span class="absolute left-1.5 top-1/2 -translate-y-1/2 w-10 h-10 rounded-lg flex items-center justify-center" style="background: {{ $_accent }};"><i class="{{ $_icon ?: 'fas fa-link' }} text-white"></i></span>
            <span class="flex-1">{{ $_txt }}</span>
        </a>
    @elseif($_lnkLayout === 'image_left')
        <a href="{{ $_url }}" target="_blank" rel="noopener"
           class="bio-btn block w-full mb-3 overflow-hidden transition-all duration-300 flex items-center gap-3 pr-4"
           @if($btnInline) style="{{ $btnInline }}" @endif>
            @if($_thumb)<img src="{{ $_thumb }}" class="w-14 h-14 object-cover flex-shrink-0" alt="">@elseif($_icon)<span class="w-14 h-14 flex items-center justify-center flex-shrink-0"><i class="{{ $_icon }} text-xl"></i></span>@endif
            <span class="flex-1 font-medium py-3.5 text-left">{{ $_txt }}</span>
            <i class="fas fa-chevron-right opacity-50"></i>
        </a>
    @elseif($_lnkLayout === 'image_right')
        <a href="{{ $_url }}" target="_blank" rel="noopener"
           class="bio-btn block w-full mb-3 overflow-hidden transition-all duration-300 flex items-center gap-3 pl-4"
           @if($btnInline) style="{{ $btnInline }}" @endif>
            <span class="flex-1 font-medium py-3.5 text-left">{{ $_txt }}</span>
            <i class="fas fa-chevron-right opacity-50"></i>
            @if($_thumb)<img src="{{ $_thumb }}" class="w-14 h-14 object-cover flex-shrink-0" alt="">@elseif($_icon)<span class="w-14 h-14 flex items-center justify-center flex-shrink-0"><i class="{{ $_icon }} text-xl"></i></span>@endif
        </a>
    @elseif($_lnkLayout === 'image_top')
        <a href="{{ $_url }}" target="_blank" rel="noopener"
           class="bio-btn block w-full mb-3 overflow-hidden transition-all duration-300"
           @if($btnInline) style="{{ $btnInline }}" @endif>
            @if($_thumb)<img src="{{ $_thumb }}" class="w-full h-28 object-cover" alt="">@endif
            <div class="px-5 py-3 font-medium flex items-center justify-center gap-2">@if($_icon)<i class="{{ $_icon }}"></i>@endif<span>{{ $_txt }}</span></div>
        </a>
    @elseif(in_array($_lnkLayout, ['image_icon_rounded', 'image_icon_square', 'image_icon_circle'], true))
        @php $_imgR = $_lnkLayout === 'image_icon_circle' ? 'rounded-full' : ($_lnkLayout === 'image_icon_square' ? 'rounded-none' : 'rounded-lg'); @endphp
        <a href="{{ $_url }}" target="_blank" rel="noopener"
           class="bio-btn block w-full px-4 py-3 mb-3 font-medium transition-all duration-300 flex items-center gap-3"
           @if($btnInline) style="{{ $btnInline }}" @endif>
            @if($_thumb)<img src="{{ $_thumb }}" class="w-9 h-9 object-cover flex-shrink-0 {{ $_imgR }}" alt="">@elseif($_icon)<span class="w-9 h-9 flex items-center justify-center flex-shrink-0 {{ $_imgR }}" style="background: {{ $_accent }};"><i class="{{ $_icon }} text-white text-sm"></i></span>@endif
            <span class="flex-1 text-left">{{ $_txt }}</span>
            <i class="fas fa-chevron-right opacity-40"></i>
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
