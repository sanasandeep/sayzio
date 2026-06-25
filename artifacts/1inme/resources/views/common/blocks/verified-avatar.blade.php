    @php $vaSize = ($s['size'] ?? '100') . 'px'; $vaShape = ($s['shape'] ?? 'circle') === 'circle' ? '50%' : '12px'; @endphp
    <div class="mb-4 flex justify-center">
        <div class="relative inline-block">
            @if(!empty($s['image_url']))
            <img src="{{ $s['image_url'] }}" alt="" class="object-cover" style="width: {{ $vaSize }}; height: {{ $vaSize }}; border-radius: {{ $vaShape }}; border: 3px solid rgba(255,255,255,0.2);">
            @else
            <div class="flex items-center justify-center" style="width: {{ $vaSize }}; height: {{ $vaSize }}; border-radius: {{ $vaShape }}; background: rgba(61,107,255,0.2); border: 3px solid rgba(255,255,255,0.2);"><i class="fas fa-user text-2xl" style="color: rgba(255,255,255,0.5);"></i></div>
            @endif
            <div class="absolute -bottom-1 -right-1 w-7 h-7 rounded-full flex items-center justify-center" style="background: #1d9bf0; border: 2px solid var(--bg-color, #0a0612);">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M9 12.5l2.5 2.5 5-5" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
        </div>
    </div>
