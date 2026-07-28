    @php
        // Tilt/rotation (Task #5954) — sanitizer clamps to ±30°; re-clamp
        // at render time so a hand-edited value can never rotate wildly.
        $pTiltSt = is_array($s['_style'] ?? null) ? $s['_style'] : [];
        $pTilt = max(-30, min(30, (float) ($pTiltSt['_tilt'] ?? 0)));
    @endphp
    <div class="mb-4 text-{{ $s['align'] ?? 'center' }}" data-tilt-wrap
         @if($pTilt != 0.0) style="transform:rotate({{ $pTilt }}deg)" @endif><p class="text-sm leading-relaxed" style="color: {{ $fontColor }}cc">{{ $s['text'] ?? '' }}</p></div>
