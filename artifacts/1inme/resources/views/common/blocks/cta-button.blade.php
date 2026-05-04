    @php $btnInline = $btnInline ?? ''; $_lnkLayout = $block->settings['_style']['link_layout'] ?? ''; @endphp
    @if($_lnkLayout === 'plain_text')
        <a href="{{ $s['url'] ?? '#' }}" target="_blank" rel="noopener"
           class="block mb-3 text-center font-semibold underline decoration-1 underline-offset-4 hover:decoration-2 transition"
           style="color: {{ $block->settings['_style']['text_color'] ?? ($s['color'] ?? '#a78bfa') }};
                  font-size: {{ ($s['size'] ?? 'lg') === 'sm' ? '14px' : (($s['size'] ?? 'lg') === 'md' ? '16px' : '18px') }};">
            {{ $s['text'] ?? 'Click Here' }}
        </a>
    @elseif($_lnkLayout === 'image_cover' && !empty($s['thumbnail']))
        <a href="{{ $s['url'] ?? '#' }}" target="_blank" rel="noopener"
           class="block w-full mb-3 rounded-2xl overflow-hidden transition-all duration-300 hover:-translate-y-0.5 hover:shadow-2xl relative"
           style="aspect-ratio: 16/8; background-image: linear-gradient(180deg, rgba(0,0,0,0.15) 0%, rgba(0,0,0,0.7) 100%), url('{{ $s['thumbnail'] }}'); background-size: cover; background-position: center;{{ $btnInline ? ' ' . $btnInline : '' }}">
            <div class="absolute inset-0 flex items-end justify-center p-5">
                <span class="text-white font-bold drop-shadow-lg" style="font-size: {{ ($s['size'] ?? 'lg') === 'sm' ? '14px' : (($s['size'] ?? 'lg') === 'md' ? '16px' : '20px') }};">{{ $s['text'] ?? 'Click Here' }}</span>
            </div>
        </a>
    @else
        <a href="{{ $s['url'] ?? '#' }}" target="_blank" rel="noopener"
           class="block w-full mb-3 text-center font-semibold transition-all duration-300 hover:-translate-y-0.5"
           style="background: {{ $s['color'] ?? ($btnColor ?? '#7c3aed') }}; color: {{ $s['text_color'] ?? ($btnTextColor ?? '#fff') }};
                  padding: {{ ($s['size'] ?? 'lg') === 'sm' ? '10px 20px' : (($s['size'] ?? 'lg') === 'md' ? '14px 24px' : '18px 32px') }};
                  border-radius: {{ $btnRadius ?? '12px' }}; box-shadow: 0 6px 20px {{ $s['color'] ?? ($btnColor ?? '#7c3aed') }}40;
                  font-size: {{ ($s['size'] ?? 'lg') === 'sm' ? '14px' : (($s['size'] ?? 'lg') === 'md' ? '16px' : '18px') }};{{ $btnInline ? ' ' . $btnInline : '' }}">
            {{ $s['text'] ?? 'Click Here' }}
        </a>
    @endif
