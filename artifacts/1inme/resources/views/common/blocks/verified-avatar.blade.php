    @php
        $vaSize = ($s['size'] ?? '100') . 'px';
        $vaShape = ($s['shape'] ?? 'circle') === 'circle' ? '50%' : '12px';
        // Blend the badge ring with the biolink's own background so it doesn't
        // freeze to a dark literal on a light-themed page; derive the avatar
        // rings from the theme text color so they stay visible in both themes.
        $__cBg = $bgColor ?? '#0a0612';
        $__cFg = $fontColor ?? '#ffffff';
        $__cRing = $__cFg . '33';   // ~20% tint of the theme text color
    @endphp
    <div class="mb-4 flex justify-center">
        <div class="relative inline-block">
            @if(!empty($s['image_url']))
            <img src="{{ $s['image_url'] }}" alt="" class="object-cover" style="width: {{ $vaSize }}; height: {{ $vaSize }}; border-radius: {{ $vaShape }}; border: 3px solid {{ $__cRing }};">
            @else
            <div class="flex items-center justify-center" style="width: {{ $vaSize }}; height: {{ $vaSize }}; border-radius: {{ $vaShape }}; background: rgba(61,107,255,0.2); border: 3px solid {{ $__cRing }};"><i class="fas fa-user text-2xl" style="color: {{ $__cFg }}80;"></i></div>
            @endif
            <div class="absolute -bottom-1 -right-1 w-7 h-7 rounded-full flex items-center justify-center" style="background: #1d9bf0; border: 2px solid {{ $__cBg }};">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M9 12.5l2.5 2.5 5-5" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
        </div>
    </div>
