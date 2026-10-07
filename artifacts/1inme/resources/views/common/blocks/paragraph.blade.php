    @php
        // Tilt/rotation (Task #5954) — sanitizer clamps to ±30°; re-clamp
        // at render time so a hand-edited value can never rotate wildly.
        $pTiltSt = is_array($s['_style'] ?? null) ? $s['_style'] : [];
        $pTilt = max(-30, min(30, (float) ($pTiltSt['_tilt'] ?? 0)));
    @endphp
    <div class="mb-4 text-{{ $s['align'] ?? 'center' }}" data-tilt-wrap data-text-design="{{ $s['_style']['_text_design'] ?? '' }}"
         @if($pTilt != 0.0) style="transform:rotate({{ $pTilt }}deg)" @endif><p class="text-sm leading-relaxed" style="color: {{ $fontColor }}">{{ $s['text'] ?? '' }}</p></div>
