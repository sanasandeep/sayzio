    @php
        // Richer divider (Task #6581). Every value is re-clamped here so a
        // legacy/tampered payload can never emit broken markup; legacy blocks
        // (style solid/dashed/dotted, 1px hairline, full width) render
        // exactly as before because every new key defaults to the old look.
        $dvStyle = $s['style'] ?? 'solid';
        if (!in_array($dvStyle, ['solid', 'dashed', 'dotted', 'double', 'gradient', 'dots', 'zigzag', 'wave'], true)) $dvStyle = 'solid';
        $dvThick = max(1, min(12, (int) ($s['thickness'] ?? 1)));
        $dvWidth = max(10, min(100, (int) ($s['width'] ?? 100)));
        $dvAlign = $s['align'] ?? 'center';
        if (!in_array($dvAlign, ['left', 'center', 'right'], true)) $dvAlign = 'center';
        // Legacy color values were free-text; Blade escaping keeps them
        // attribute-safe, so we only fall back when the value is blank.
        $dvColor = trim((string) ($s['color'] ?? '')) !== '' ? (string) $s['color'] : 'rgba(255,255,255,0.1)';
        $dvMargin = ['left' => '0 auto 0 0', 'center' => '0 auto', 'right' => '0 0 0 auto'][$dvAlign];

        $dvIcon = trim((string) ($s['ornament_icon'] ?? ''));
        $dvText = trim((string) ($s['ornament_text'] ?? ''));
        $dvHasOrn = $dvIcon !== '' || $dvText !== '';
        $dvOrnColor = trim((string) ($s['ornament_color'] ?? '')) !== '' ? (string) $s['ornament_color'] : $dvColor;
        $dvOrnSize = max(10, min(40, (int) ($s['ornament_size'] ?? 16)));

        // One line segment. $flex=true when it sits beside an ornament.
        $dvSeg = function (bool $flex) use ($dvStyle, $dvThick, $dvColor) {
            $c = e($dvColor);
            $base = $flex ? 'flex:1 1 0%;min-width:0;' : 'width:100%;';
            switch ($dvStyle) {
                case 'gradient':
                    return '<div style="' . $base . 'height:' . $dvThick . 'px;background:linear-gradient(90deg,transparent,' . $c . ',transparent);"></div>';
                case 'dots':
                    $d = max(4, $dvThick * 3);
                    $r = (int) ceil($d / 2) - 1;
                    return '<div style="' . $base . 'height:' . $d . 'px;background-image:radial-gradient(circle,' . $c . ' ' . $r . 'px,transparent ' . ($r + 1) . 'px);background-size:' . ($d * 3) . 'px ' . $d . 'px;background-position:center;background-repeat:repeat-x;"></div>';
                case 'zigzag':
                    $h = max(6, $dvThick * 3);
                    return '<div style="' . $base . 'height:' . $h . 'px;background:'
                        . 'linear-gradient(135deg,' . $c . ' 25%,transparent 25%) 0 0/' . $h . 'px ' . $h . 'px repeat-x,'
                        . 'linear-gradient(225deg,' . $c . ' 25%,transparent 25%) 0 0/' . $h . 'px ' . $h . 'px repeat-x;"></div>';
                case 'wave':
                    $h = $dvThick + 8;
                    $mid = $h / 2;
                    $id = 'dvw' . substr(md5(uniqid('', true)), 0, 8);
                    return '<svg style="' . $base . 'display:block;" height="' . $h . '" width="100%" xmlns="http://www.w3.org/2000/svg">'
                        . '<defs><pattern id="' . $id . '" width="24" height="' . $h . '" patternUnits="userSpaceOnUse">'
                        . '<path d="M0 ' . $mid . ' Q6 0 12 ' . $mid . ' T24 ' . $mid . '" fill="none" stroke="' . $c . '" stroke-width="' . $dvThick . '"/>'
                        . '</pattern></defs><rect width="100%" height="' . $h . '" fill="url(#' . $id . ')"/></svg>';
                case 'double':
                    return '<div style="' . $base . 'height:0;border-top:' . max(3, $dvThick) . 'px double ' . $c . ';"></div>';
                default: // solid / dashed / dotted — the legacy hairline family.
                    return '<div style="' . $base . 'height:0;border-top:' . $dvThick . 'px ' . e($dvStyle) . ' ' . $c . ';"></div>';
            }
        };
    @endphp
    <div class="my-4 px-4">
        <div style="width: {{ $dvWidth }}%; margin: {{ $dvMargin }};">
            @if($dvHasOrn)
                <div style="display:flex;align-items:center;gap:10px;">
                    {!! $dvSeg(true) !!}
                    <span style="flex:0 0 auto;color:{{ $dvOrnColor }};font-size:{{ $dvOrnSize }}px;line-height:1;white-space:nowrap;">
                        @if($dvIcon !== '')
                            <i class="{{ fa_icon_class($dvIcon, 'fas fa-star') }}"></i>
                        @else
                            {{ \Illuminate\Support\Str::limit($dvText, 30, '') }}
                        @endif
                    </span>
                    {!! $dvSeg(true) !!}
                </div>
            @else
                {!! $dvSeg(false) !!}
            @endif
        </div>
    </div>
