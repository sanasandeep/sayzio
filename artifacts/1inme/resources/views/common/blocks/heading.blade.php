    @php
        $headingStyle = $s['style'] ?? 'plain';
        $hs = match($s['size'] ?? 'h2') { 'h1' => 'text-2xl md:text-3xl', 'h2' => 'text-xl md:text-2xl', 'h3' => 'text-lg md:text-xl', default => 'text-xl md:text-2xl' };

        // ── Decorative shape accents (Task #5938) ───────────────────────
        // Keys live in _style (same pattern as the image block's
        // _photo_* decorations) so curated variants can carry them.
        $haSt = is_array($s['_style'] ?? null) ? $s['_style'] : [];
        $haAccents = \App\Modules\User\Support\AccentShapeCatalog::parseTokens((string) ($haSt['_heading_accents'] ?? ''));
        if (!empty($haAccents)) {
            $haColor = (string) ($haSt['_heading_accent_color'] ?? '') ?: '#ec4899';
            $haPlacement = (string) ($haSt['_heading_accent_placement'] ?? '');
            if (!in_array($haPlacement, \App\Modules\User\Support\AccentShapeCatalog::HEADING_PLACEMENTS, true)) {
                $haPlacement = 'behind_left';
            }
            $haSize = (string) ($haSt['_heading_accent_size'] ?? '');
            $haScale = \App\Modules\User\Support\AccentShapeCatalog::HEADING_SIZE_SCALES[$haSize] ?? 1.0;
            // Up to three positioning slots per placement: the first accent
            // sits at the chosen anchor, extras fan out to complementary
            // spots so stacking several shapes still reads well. All slots
            // hug the heading box (absolute, no layout flow impact).
            $haSlots = match ($haPlacement) {
                'behind_right' => [
                    'right:2%;top:50%;transform:translate(30%,-50%)',
                    'left:2%;top:50%;transform:translate(-30%,-50%)',
                    'left:50%;top:0;transform:translate(-50%,-55%)',
                ],
                'top_left' => [
                    'left:0;top:0;transform:translate(-35%,-55%)',
                    'right:0;bottom:0;transform:translate(35%,45%)',
                    'right:0;top:0;transform:translate(35%,-55%)',
                ],
                'top_right' => [
                    'right:0;top:0;transform:translate(35%,-55%)',
                    'left:0;bottom:0;transform:translate(-35%,45%)',
                    'left:0;top:0;transform:translate(-35%,-55%)',
                ],
                default => [ // behind_left
                    'left:2%;top:50%;transform:translate(-30%,-50%)',
                    'right:2%;top:50%;transform:translate(30%,-50%)',
                    'left:50%;top:0;transform:translate(-50%,-55%)',
                ],
            };
        }
    @endphp
    @php
        // Tilt/rotation (Task #5954) — sanitizer clamps to ±30°; re-clamp
        // at render time so a hand-edited value can never rotate wildly.
        $hTilt = max(-30, min(30, (float) ($haSt['_tilt'] ?? 0)));
    @endphp
    <div class="mb-3 text-{{ $s['align'] ?? 'center' }} relative" data-tilt-wrap
         @if($hTilt != 0.0) style="transform:rotate({{ $hTilt }}deg)" @endif
         @if(!empty($haAccents)) data-heading-accents="{{ implode(',', $haAccents) }}" @endif>
        @if(!empty($haAccents))
            @foreach($haAccents as $haIdx => $haShape)
                @php
                    $haDef = \App\Modules\User\Support\AccentShapeCatalog::SHAPES[$haShape];
                    // Later slots shrink slightly so the primary shape leads.
                    $haSlotScale = $haScale * ($haIdx === 0 ? 1.0 : 0.72);
                @endphp
                @include('common.partials.accent-shape', [
                    'shape'    => $haShape,
                    'color'    => $haColor,
                    'w'        => (int) round($haDef['w'] * $haSlotScale),
                    'h'        => (int) round($haDef['h'] * $haSlotScale),
                    'posStyle' => $haSlots[$haIdx % count($haSlots)],
                    'accClass' => 'z-0',
                ])
            @endforeach
        @endif
        @if($headingStyle === 'gradient')
            <h2 class="{{ $hs }} font-bold bg-clip-text text-transparent relative z-[1]" style="background-image: linear-gradient(to right, {{ $s['from_color'] ?? '#3d6bff' }}, {{ $s['to_color'] ?? '#ec4899' }});">{{ $s['text'] ?? '' }}</h2>
        @elseif($headingStyle === 'animated')
            <h2 class="{{ $hs }} font-bold morph-text relative z-[1]">{{ $s['text'] ?? '' }}</h2>
        @else
            <h2 class="{{ $hs }} font-bold relative z-[1]">{{ $s['text'] ?? '' }}</h2>
        @endif
    </div>
