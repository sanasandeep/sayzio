    @php $_lnkLayout = $block->settings['_style']['link_layout'] ?? ''; $_accent = $s['accent_color'] ?? '#f59e0b'; @endphp
    @if($_lnkLayout === 'plain_text')
        <a href="{{ $s['url'] ?? '#' }}" target="_blank" rel="noopener"
           class="block mb-3 text-center text-base font-semibold underline decoration-1 underline-offset-4 hover:decoration-2 transition"
           style="color: {{ $block->settings['_style']['text_color'] ?? $_accent }};">
            <i class="fas fa-thumbtack text-[10px] mr-1.5"></i>{{ $s['text'] ?? 'Featured' }}
            @if(!empty($s['description']))<div class="text-xs font-normal opacity-70 mt-1 no-underline">{{ $s['description'] }}</div>@endif
        </a>
    @elseif($_lnkLayout === 'image_cover' && !empty($s['thumbnail']))
        <a href="{{ $s['url'] ?? '#' }}" target="_blank" rel="noopener"
           class="block w-full mb-3 rounded-2xl overflow-hidden transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl relative"
           style="aspect-ratio: 16/8; background-image: linear-gradient(180deg, rgba(0,0,0,0.1) 0%, rgba(0,0,0,0.75) 100%), url('{{ $s['thumbnail'] }}'); background-size: cover; background-position: center;">
            <div class="absolute top-2 right-2 px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider" style="background: {{ $_accent }}; color:#fff;">
                <i class="fas fa-thumbtack mr-1"></i>Featured
            </div>
            <div class="absolute inset-0 flex flex-col justify-end p-5">
                <p class="font-bold text-white text-lg drop-shadow-lg">{{ $s['text'] ?? 'Featured' }}</p>
                @if(!empty($s['description']))<p class="text-sm text-white/80 mt-1 drop-shadow">{{ $s['description'] }}</p>@endif
            </div>
        </a>
    @else
        <a href="{{ $s['url'] ?? '#' }}" target="_blank" rel="noopener"
           class="bio-btn block w-full mb-3 transition-all duration-300 relative overflow-hidden"
           style="background: linear-gradient(135deg, {{ $_accent }}, {{ $_accent }}cc); color:#fff;">
            <div class="absolute top-0 right-3 -translate-y-0 px-2 py-0.5 rounded-b-md text-[10px] font-bold uppercase tracking-wider" style="background: rgba(0,0,0,.35);">
                <i class="fas fa-thumbtack mr-1"></i>Featured
            </div>
            <div class="flex items-center gap-3 px-5 py-4">
                @if(!empty($s['thumbnail']))
                    <img src="{{ $s['thumbnail'] }}" alt="" class="w-12 h-12 rounded-lg object-cover flex-shrink-0">
                @elseif(!empty($s['icon']))
                    <i class="{{ fa_icon_class($s['icon']) }} text-2xl flex-shrink-0"></i>
                @endif
                <div class="text-left flex-1 min-w-0">
                    <div class="font-semibold truncate">{{ $s['text'] ?? 'Featured' }}</div>
                    @if(!empty($s['description']))<div class="text-xs opacity-90 truncate">{{ $s['description'] }}</div>@endif
                </div>
                <i class="fas fa-arrow-right opacity-70 flex-shrink-0"></i>
            </div>
        </a>
    @endif
