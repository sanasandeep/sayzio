@php
    $items   = is_array($s['items'] ?? null) ? $s['items'] : [];
    $style   = is_array($s['_style'] ?? null) ? $s['_style'] : [];
    // Layout can come from a curated variant (stamped into the opaque
    // `_style._ltg_layout` hook) or from the block's own content setting.
    $layout  = $style['_ltg_layout'] ?? ($s['layout'] ?? ($s['_registry']['layout'] ?? 'list'));
    if (!in_array($layout, ['list', 'grid', 'text_divider', 'outline_pills', 'washi_tape', 'tile_grid_alt'], true)) $layout = 'list';
    $align   = $style['_ltg_align'] ?? ($s['align'] ?? 'left');
    if (!in_array($align, ['left', 'center', 'right'], true)) $align = 'left';
    $title   = trim($s['title'] ?? '');
    $accent  = $s['accent_color'] ?? '#3d6bff';

    // Per-item click tracking (Task #6576): route every http(s) item URL
    // through the shared block-redirect pipeline so taps are recorded in
    // analytics exactly like single link blocks. Non-web schemes (mailto:,
    // tel:) stay raw — the redirect endpoint only forwards http/https.
    $alias = $alias ?? ($link->primary_alias ?? $link->alias ?? null);
    $ltgHref = function (array $it) use ($alias, $block) {
        $raw = trim((string) ($it['url'] ?? ''));
        if ($raw === '' ) return '#';
        if (!$alias || !preg_match('/^https?:\/\//i', $raw)) return $raw;
        $q = '?to=' . urlencode($raw);
        if (!empty($it['id'])) $q .= '&item=' . urlencode((string) $it['id']);
        return route('redirect.block', ['alias' => $alias, 'blockId' => $block->id]) . $q;
    };
    $alignClass = ['left' => 'text-left', 'center' => 'text-center', 'right' => 'text-right'][$align];
@endphp

<div class="mb-4">
    @if($title !== '')
        <p class="text-sm font-semibold mb-2 px-1 {{ $layout === 'text_divider' ? $alignClass : '' }}" style="color: {{ $fontColor }};">{{ $title }}</p>
    @endif

    @if($layout === 'grid')
        <div class="grid grid-cols-2 gap-2">
            @foreach($items as $it)
                <a href="{{ $ltgHref($it) }}" target="_blank" rel="noopener"
                   class="rounded-xl px-3 py-3 text-center text-sm font-medium transition border hover:-translate-y-0.5"
                   style="background: {{ $fontColor }}08; border-color: {{ $fontColor }}1a; color: {{ $fontColor }};">
                    @if(!empty($it['icon']))<i class="{{ fa_icon_class($it['icon']) }} mr-1.5" style="color: {{ $accent }};"></i>@endif
                    <span class="truncate">{{ $it['text'] ?? 'Link' }}</span>
                </a>
            @endforeach
        </div>
    @elseif($layout === 'text_divider')
        {{-- Minimal "text + hairline divider" list (Task #6576): plain text
             labels, no button chrome, each row carries its own bottom
             hairline derived from currentColor so it stays legible on any
             page theme (same treatment as the single link block's
             text_divider layout). --}}
        <div class="w-full" data-ltg-layout="text_divider">
            @foreach($items as $it)
                <a href="{{ $ltgHref($it) }}" target="_blank" rel="noopener"
                   class="block w-full py-3.5 {{ $alignClass }} transition-opacity duration-200 hover:opacity-70"
                   style="color: {{ $fontColor }}; border-bottom: 1px solid color-mix(in srgb, currentColor 25%, transparent); font-weight: 500; font-size: 15px;">
                    @if(!empty($it['icon']))<i class="{{ fa_icon_class($it['icon']) }} mr-2 text-[0.85em] opacity-80"></i>@endif{{ $it['text'] ?? 'Link' }}
                </a>
            @endforeach
        </div>
    @elseif($layout === 'outline_pills')
        {{-- Outlined pill stack (Task #6589): stacked full-width pills with a
             thin light outline and a translucent fill — reads on any theme
             because both derive from the effective text color. Explicit
             text_color from the variant/style wins over the page font. --}}
        @php $_opInk = ($style['text_color'] ?? '') !== '' ? $style['text_color'] : $fontColor; @endphp
        <div class="space-y-3" data-ltg-layout="outline_pills">
            @foreach($items as $it)
                <a href="{{ $ltgHref($it) }}" target="_blank" rel="noopener"
                   class="block w-full rounded-full text-center transition duration-200 hover:-translate-y-0.5"
                   style="color: {{ $_opInk }}; border: 1px solid color-mix(in srgb, {{ $_opInk }} 45%, transparent); background: color-mix(in srgb, {{ $_opInk }} 7%, transparent); padding: 15px 20px; font-weight: 500; font-size: 15px; letter-spacing: 0.01em;">
                    @if(!empty($it['icon']))<i class="{{ fa_icon_class($it['icon']) }} mr-2 text-[0.9em] opacity-80"></i>@endif{{ $it['text'] ?? 'Link' }}
                </a>
            @endforeach
        </div>
    @elseif($layout === 'washi_tape')
        {{-- Washi-tape strips (Task #6589): each link looks like a torn strip
             of tape — irregular clipped edges, slight per-item rotation and
             horizontal offset, bold uppercase label. Reuses the taped_note /
             torn_tape visual technique (rotating pastel paper palette with
             explicit ink colors so strips read the same on dark and light
             themes). bg_color/text_color from the variant override the whole
             palette with a single tone. --}}
        @php
            $_wtPalette = [
                ['#f7e9ed', '#6d4c3d'], // blush pink / warm brown ink
                ['#a98a7d', '#f9f2ec'], // warm clay / cream ink
                ['#cfc3b8', '#4a3d31'], // taupe / dark brown ink
                ['#f2e3e6', '#6d4c3d'], // pale rose / warm brown ink
                ['#8d7466', '#f6ede5'], // deep mauve / cream ink
                ['#bdb3aa', '#4a3d31'], // stone grey / dark brown ink
            ];
            $_wtBgPick  = ($style['bg_color'] ?? '') !== '' && ($style['bg_color'] ?? '') !== 'transparent' ? $style['bg_color'] : null;
            $_wtInkPick = ($style['text_color'] ?? '') !== '' ? $style['text_color'] : null;
            // Torn-edge strips: slightly different clip polygons per slot so
            // strips don't look stamped from the same die.
            $_wtClips = [
                'polygon(1.2% 8%, 3% 0%, 97.5% 3%, 99.4% 14%, 98.6% 88%, 96.5% 100%, 2.4% 97%, 0.6% 84%)',
                'polygon(0.8% 12%, 2.6% 2%, 98% 0%, 99.2% 10%, 99% 90%, 97% 98%, 1.8% 100%, 0.4% 88%)',
                'polygon(1.6% 4%, 4% 0%, 96.8% 2%, 99.6% 18%, 98.2% 92%, 95.8% 100%, 1.4% 96%, 0.8% 80%)',
            ];
            $_wtTilts   = ['-1.6deg', '1.2deg', '-0.8deg', '1.8deg'];
            $_wtOffsets = ['-6px', '5px', '-3px', '7px'];
        @endphp
        <div class="space-y-3 py-1" data-ltg-layout="washi_tape">
            @foreach($items as $_wi => $it)
                @php [$_wtBgD, $_wtInkD] = $_wtPalette[$_wi % count($_wtPalette)]; @endphp
                <a href="{{ $ltgHref($it) }}" target="_blank" rel="noopener"
                   class="block w-full text-center uppercase transition-transform duration-300 hover:-translate-y-1 hover:rotate-0"
                   style="background: {{ $_wtBgPick ?? $_wtBgD }}; color: {{ $_wtInkPick ?? $_wtInkD }}; clip-path: {{ $_wtClips[$_wi % count($_wtClips)] }}; transform: rotate({{ $_wtTilts[$_wi % count($_wtTilts)] }}) translateX({{ $_wtOffsets[$_wi % count($_wtOffsets)] }}); padding: 18px 24px; font-weight: 800; font-size: 15px; letter-spacing: 0.14em; box-shadow: 0 6px 16px rgba(60,45,35,0.18);">
                    @if(!empty($it['icon']))<i class="{{ fa_icon_class($it['icon']) }} mr-2 text-[0.9em] opacity-80"></i>@endif{{ $it['text'] ?? 'Link' }}
                </a>
            @endforeach
        </div>
    @elseif($layout === 'tile_grid_alt')
        {{-- Alternating tile grid (Task #6589): two-column edge-to-edge tiles
             (gap-0, shared hairline borders) with checkerboard-alternating
             background tones and large serif labels. Tone pair defaults are
             cream/sand; bg_color and text_color from the variant retint the
             base tone (the alternate tone is mixed from it) so color
             variations only need two style keys. --}}
        @php
            $_tgBase = ($style['bg_color'] ?? '') !== '' && ($style['bg_color'] ?? '') !== 'transparent' ? $style['bg_color'] : '#f4efe6';
            $_tgInk  = ($style['text_color'] ?? '') !== '' ? $style['text_color'] : '#2c2820';
            $_tgAlt  = 'color-mix(in srgb, ' . $_tgBase . ' 82%, ' . $_tgInk . ')';
            $_tgLine = 'color-mix(in srgb, ' . $_tgInk . ' 22%, transparent)';
        @endphp
        <div class="grid grid-cols-2 gap-0 overflow-hidden" data-ltg-layout="tile_grid_alt" style="border: 1px solid {{ $_tgLine }};">
            @foreach($items as $_ti => $it)
                @php $_tgTone = ((int) floor($_ti / 2) + $_ti % 2) % 2 === 0 ? $_tgBase : $_tgAlt; @endphp
                <a href="{{ $ltgHref($it) }}" target="_blank" rel="noopener"
                   class="flex items-center justify-center text-center transition-colors duration-200 hover:opacity-80"
                   style="background: {{ $_tgTone }}; color: {{ $_tgInk }}; border: 0.5px solid {{ $_tgLine }}; min-height: 96px; padding: 22px 14px; font-family: 'Playfair Display', Georgia, 'Times New Roman', serif; font-weight: 600; font-size: 19px; line-height: 1.25;">
                    <span>@if(!empty($it['icon']))<i class="{{ fa_icon_class($it['icon']) }} mr-2 text-[0.8em] opacity-70"></i>@endif{{ $it['text'] ?? 'Link' }}</span>
                </a>
            @endforeach
        </div>
    @else
        <div class="space-y-2">
            @foreach($items as $it)
                <a href="{{ $ltgHref($it) }}" target="_blank" rel="noopener"
                   class="block rounded-xl px-4 py-3 transition border hover:-translate-y-0.5"
                   style="background: {{ $fontColor }}08; border-color: {{ $fontColor }}1a; color: {{ $fontColor }};">
                    <div class="flex items-center gap-3">
                        @if(!empty($it['icon']))<i class="{{ fa_icon_class($it['icon']) }} w-5 text-center" style="color: {{ $accent }};"></i>@endif
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium truncate">{{ $it['text'] ?? 'Link' }}</div>
                            @if(!empty($it['description']))<div class="text-xs opacity-60 truncate">{{ $it['description'] }}</div>@endif
                        </div>
                        <i class="fas fa-arrow-right text-xs opacity-40"></i>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
