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
        if ($_accent === '') $_accent = '#3d6bff';
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
           style="color: {{ $block->settings['_style']['text_color'] ?? '#90acff' }};">
            @if(!empty($s['icon']))<i class="{{ fa_icon_class($s['icon']) }} mr-1.5"></i>@endif{{ $s['text'] ?? 'Link' }}
        </a>
    @elseif($_lnkLayout === 'text_divider')
        {{-- Minimal text list row: left-aligned plain text with a thin
             hairline divider below it (classic "text list" link-in-bio
             look). Every row carries its own bottom hairline so
             consecutive rows stack into a clean list with no doubled
             lines; the divider derives from the row's own text color
             (via currentColor) so it stays legible on both dark and
             light page themes. No bio-btn chrome. --}}
        @php $_tdColor = ($_st['text_color'] ?? '') !== '' ? $_st['text_color'] : ($fontColor ?? '#ffffff'); @endphp
        <a href="{{ $_url }}" target="_blank" rel="noopener"
           class="block w-full py-3.5 text-left transition-opacity duration-200 hover:opacity-70"
           style="color: {{ $_tdColor }}; border-bottom: 1px solid color-mix(in srgb, currentColor 25%, transparent); font-weight: {{ $_st['font_weight'] ?? '500' }}; font-size: {{ intval($_st['font_size'] ?? 0) ?: 15 }}px;@if(!empty($_st['font_family'])) font-family: '{{ str_replace("'", '', str_starts_with($_st['font_family'], 'custom:') ? substr($_st['font_family'], 7) : $_st['font_family']) }}', sans-serif;@endif">
            @if($_icon)<i class="{{ $_icon }} mr-2 text-[0.85em] opacity-80"></i>@endif{{ $_txt }}
        </a>
    @elseif($_lnkLayout === 'action_row')
        {{-- Bold action-word row: big uppercase accent word on the left,
             smaller uppercase description beside it (Lillian-Pratt style).
             Transparent — no bio-btn chrome. Accent = text_color. --}}
        <a href="{{ $_url }}" target="_blank" rel="noopener"
           class="block w-full mb-4 transition-opacity duration-200 hover:opacity-75 flex items-baseline gap-x-4 gap-y-1 flex-wrap text-left">
            <span class="uppercase leading-none tracking-wide min-w-[6.5rem]"
                  style="color: {{ ($_st['text_color'] ?? '') !== '' ? $_st['text_color'] : '#e3f77e' }}; font-weight: {{ $_st['font_weight'] ?? '800' }}; font-size: {{ intval($_st['font_size'] ?? 0) ?: 22 }}px;@if(!empty($_st['font_family'])) font-family: '{{ str_replace("'", '', str_starts_with($_st['font_family'], 'custom:') ? substr($_st['font_family'], 7) : $_st['font_family']) }}', sans-serif;@endif">
                @if($_icon)<i class="{{ $_icon }} mr-1.5 text-[0.8em]"></i>@endif{{ $_txt }}
            </span>
            @if(!empty($s['description']))
                <span class="text-[11px] uppercase tracking-[0.14em] opacity-90" style="color: {{ $fontColor ?? '#ffffff' }};">{{ $s['description'] }}</span>
            @endif
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
    @elseif($_lnkLayout === 'image_cover_square')
        {{-- Square (1:1) image tile with the title centered over a subtle
             dark overlay. Without a thumbnail it falls back to a flat
             accent-colored square tile so the layout never breaks. --}}
        <a href="{{ $_url }}" target="_blank" rel="noopener"
           class="block w-full mb-3 rounded-2xl overflow-hidden transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl relative"
           style="aspect-ratio: 1/1; @if($_thumb)background-image: linear-gradient(rgba(0,0,0,0.32), rgba(0,0,0,0.32)), url('{{ $_thumb }}'); background-size: cover; background-position: center;@else background: linear-gradient(135deg, {{ $_accent }} 0%, {{ $_accent }}cc 100%);@endif{{ $btnInline ? ' ' . $btnInline : '' }}">
            <div class="absolute inset-0 flex items-center justify-center p-4 text-center">
                <div class="text-white font-bold drop-shadow-lg leading-snug">
                    @if($_icon)<i class="{{ $_icon }} mr-2"></i>@endif{{ $_txt }}
                </div>
            </div>
        </a>
    @elseif($_lnkLayout === 'title_desc_row')
        {{-- Two-column text row inside normal button chrome: bold title on
             the left, lighter description on the right. Wraps to stacked
             lines on narrow screens (flex-wrap). --}}
        <a href="{{ $_url }}" target="_blank" rel="noopener"
           class="bio-btn block w-full px-5 py-3.5 mb-3 transition-all duration-300 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-0.5 text-left"
           @if($btnInline) style="{{ $btnInline }}" @endif>
            <span class="font-bold">@if($_icon)<i class="{{ $_icon }} mr-1.5"></i>@endif{{ $_txt }}</span>
            @if(!empty($s['description']))
                <span class="text-sm font-normal opacity-75 min-w-0">{{ $s['description'] }}</span>
            @endif
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
    @elseif($_lnkLayout === 'image_overhang_top')
        {{-- Sticker-style card: the photo deliberately overhangs the top edge
             of the colored panel; big bold uppercase title centered below.
             The wrapper reserves headroom with padding-top so the protruding
             image stays inside the block's own box (no ancestor clipping).
             $btnInline goes FIRST so the layout-critical padding wins. --}}
        <a href="{{ $_url }}" target="_blank" rel="noopener"
           class="block w-full mb-3 relative transition-all duration-300 hover:-translate-y-1"
           @if($_thumb) style="padding-top: 44px;" @endif>
            <div class="bio-btn w-full text-center uppercase tracking-wide"
                 style="{{ $btnInline ? rtrim($btnInline, '; ') . '; ' : '' }}padding: {{ $_thumb ? '132px 20px 30px' : '30px 20px' }}; font-weight: {{ $_st['font_weight'] ?? '800' }}; font-size: {{ intval($_st['font_size'] ?? 0) ?: 24 }}px; line-height: 1.15;">
                @if($_icon)<i class="{{ $_icon }} mr-2"></i>@endif{{ $_txt }}
            </div>
            @if($_thumb)
                <img src="{{ $_thumb }}" class="absolute top-0 left-1/2 -translate-x-1/2 w-4/5 h-40 object-cover rounded-xl shadow-lg" alt="">
            @endif
        </a>
    @elseif($_lnkLayout === 'image_overhang_left')
        {{-- Sticker-style banner: square photo thumbnail sticks out past the
             left edge (and above/below) of the colored bar; big bold label.
             Wrapper padding reserves the overhang room inside the block box. --}}
        <a href="{{ $_url }}" target="_blank" rel="noopener"
           class="block w-full mb-3 relative transition-all duration-300 hover:-translate-y-0.5"
           @if($_thumb) style="padding: 10px 0 10px 22px;" @endif>
            <div class="bio-btn w-full flex items-center uppercase tracking-wide"
                 style="{{ $btnInline ? rtrim($btnInline, '; ') . '; ' : '' }}padding: 26px 22px 26px {{ $_thumb ? '100px' : '22px' }}; font-weight: {{ $_st['font_weight'] ?? '800' }}; font-size: {{ intval($_st['font_size'] ?? 0) ?: 20 }}px; line-height: 1.15;">
                @if($_icon)<i class="{{ $_icon }} mr-2"></i>@endif<span class="flex-1 text-left">{{ $_txt }}</span>
            </div>
            @if($_thumb)
                <img src="{{ $_thumb }}" class="absolute left-0 top-1/2 -translate-y-1/2 w-24 h-24 object-cover rounded-lg shadow-lg" alt="">
            @endif
        </a>
    @elseif($_lnkLayout === 'taped_note')
        {{-- Taped Notes: muted pastel paper card with a washi-tape strip
             overhanging the top edge and a centered serif label (reference:
             Bestsellers / Collections grid). Per-card paper tint rotates
             through a pastel palette by sort order unless the block carries
             its own bg_color/text_color override, so a set of cards gets
             the subtle tint variation of the reference with zero config.
             Designed for the 2-column grid (grid_span 6). Paper + ink
             colors are explicit so the card reads the same on dark and
             light page themes; the tape strip is translucent cream that
             stays subtle over any paper tint. --}}
        @php
            $_tnPalette = [
                ['#f7e9ed', '#6d4c3d'], // blush pink / warm brown ink
                ['#a98a7d', '#f9f2ec'], // warm clay / cream ink
                ['#bdb3aa', '#4a3d31'], // stone grey / dark brown ink
                ['#f2e3e6', '#6d4c3d'], // pale rose / warm brown ink
                ['#8d7466', '#f6ede5'], // deep mauve / cream ink
                ['#cfc3b8', '#4a3d31'], // taupe / dark brown ink
            ];
            $_tnIdx = abs((int)($block->sort_order ?? $block->id ?? 0)) % count($_tnPalette);
            [$_tnBgDefault, $_tnInkDefault] = $_tnPalette[$_tnIdx];
            $_tnBgPick = $_st['bg_color'] ?? '';
            $_tnBg  = ($_tnBgPick !== '' && $_tnBgPick !== 'transparent') ? $_tnBgPick : $_tnBgDefault;
            $_tnInk = ($_st['text_color'] ?? '') !== '' ? $_st['text_color'] : $_tnInkDefault;
            $_tnShadowMap = [
                'none'   => 'none',
                'soft'   => '0 8px 18px rgba(76,60,50,0.16), 0 2px 5px rgba(76,60,50,0.10)',
                'medium' => '0 12px 26px rgba(76,60,50,0.24), 0 3px 8px rgba(76,60,50,0.14)',
                'strong' => '0 18px 38px rgba(76,60,50,0.32), 0 5px 12px rgba(76,60,50,0.18)',
            ];
            $_tnShadow = $_tnShadowMap[$_st['shadow_preset'] ?? 'soft'] ?? $_tnShadowMap['soft'];
            $_tnRadius = intval($_st['border_radius'] ?? 0) ?: 3;
            $_tnBorder = (($_st['border_style'] ?? 'none') !== 'none' && intval($_st['border_width'] ?? 0) > 0)
                ? (intval($_st['border_width']) . 'px ' . $_st['border_style'] . ' ' . (($_st['border_color'] ?? '') !== '' ? $_st['border_color'] : 'rgba(0,0,0,0.15)'))
                : 'none';
            $_tnFont = !empty($_st['font_family'])
                ? "'" . str_replace("'", '', str_starts_with($_st['font_family'], 'custom:') ? substr($_st['font_family'], 7) : $_st['font_family']) . "', Georgia, serif"
                : "'Playfair Display', Georgia, 'Times New Roman', serif";
            $_tnTilt = $_tnIdx % 2 === 0 ? '-2.5deg' : '2deg';
        @endphp
        <a href="{{ $_url }}" target="_blank" rel="noopener"
           class="block w-full mb-3 relative transition-transform duration-300 hover:-translate-y-1"
           style="padding-top: 12px;">
            <div class="w-full text-center"
                 style="background: {{ $_tnBg }}; color: {{ $_tnInk }}; border-radius: {{ $_tnRadius }}px; border: {{ $_tnBorder }}; box-shadow: {{ $_tnShadow }}; padding: {{ intval($_st['padding'] ?? 0) ?: 34 }}px 16px; font-family: {{ $_tnFont }}; font-weight: {{ $_st['font_weight'] ?? '500' }}; font-size: {{ intval($_st['font_size'] ?? 0) ?: 16 }}px; letter-spacing: 0.02em; line-height: 1.3;">
                @if($_icon)<i class="{{ $_icon }} mr-1.5 text-[0.85em] opacity-80"></i>@endif{{ $_txt }}
            </div>
            <span aria-hidden="true" class="absolute left-1/2 pointer-events-none"
                  style="top: 0; width: 86px; height: 24px; transform: translateX(-50%) rotate({{ $_tnTilt }}); background: rgba(235,227,208,0.82); border-left: 1px dashed rgba(120,105,85,0.28); border-right: 1px dashed rgba(120,105,85,0.28); box-shadow: 0 1px 3px rgba(76,60,50,0.18);"></span>
        </a>
    @elseif($_lnkLayout === 'arrow_chip_left')
        {{-- Arrow chip: a detached white/outlined rounded chip holding the
             block icon (right-arrow fallback) overlaps the left end of a
             colored pill that carries the label (yellow "Website"
             reference). The pill is the bio-btn so per-block
             colors/gradients/borders apply normally; the chip derives its
             outline + icon color from border_color → text_color with a
             dark-ink fallback so it stays legible on light pills. --}}
        @php
            $_acInk = ($_st['border_color'] ?? '') !== '' && ($_st['border_color'] ?? '') !== 'transparent'
                ? $_st['border_color']
                : (($_st['text_color'] ?? '') !== '' ? $_st['text_color'] : '#1f2937');
        @endphp
        <a href="{{ $_url }}" target="_blank" rel="noopener"
           class="relative block w-full mb-3 transition-all duration-300 hover:-translate-y-0.5"
           style="padding-left: 26px;">
            <div class="bio-btn w-full py-3.5 pr-6 font-medium flex items-center justify-center"
                 style="{{ $btnInline ? rtrim($btnInline, '; ') . '; ' : '' }}padding-left: 64px; border-radius: {{ intval($_st['border_radius'] ?? 0) ?: 999 }}px;">
                <span>{{ $_txt }}</span>
            </div>
            <span class="absolute left-0 top-1/2 -translate-y-1/2 flex items-center justify-center"
                  style="width: 68px; height: calc(100% + 8px); background: #ffffff; border: 1.5px solid {{ $_acInk }}; border-radius: {{ intval($_st['border_radius'] ?? 0) ?: 999 }}px; box-shadow: 0 1px 4px rgba(0,0,0,0.10);">
                <i class="{{ $_icon ?: 'fas fa-arrow-right' }} text-lg" style="color: {{ $_acInk }};"></i>
            </span>
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
    @elseif($_lnkLayout === 'arrow_hex')
        {{-- Arrow banner: hexagonal button with pointed left and right ends
             (navy/blue reference). The clip-path lives on the inner bio-btn
             panel so per-block colors/gradients apply normally; box shadows
             are intentionally clipped away by the shape. --}}
        <a href="{{ $_url }}" target="_blank" rel="noopener"
           class="block w-full mb-3 transition-all duration-300 hover:-translate-y-0.5">
            <div class="bio-btn w-full px-10 py-3.5 text-center font-bold uppercase tracking-wide flex items-center justify-center gap-2"
                 style="{{ $btnInline ? rtrim($btnInline, '; ') . '; ' : '' }}clip-path: polygon(26px 0%, calc(100% - 26px) 0%, 100% 50%, calc(100% - 26px) 100%, 26px 100%, 0% 50%); border-radius: 0;">
                @if($_icon)<i class="{{ $_icon }} text-[0.85em]"></i>@endif<span>{{ $_txt }}</span>
            </div>
        </a>
    @elseif($_lnkLayout === 'arrow_hex_round')
        {{-- Rounded arrow banner (Task #6580): same hexagonal banner as
             `arrow_hex` but with softly rounded points and corners (yellow
             "PORTFOLIO" reference). Sharp polygon clip-paths can't round
             corners, so we clip with CSS `shape()` (smooth quadratic curves
             at every vertex) and fall back to the sharp polygon on browsers
             without shape() support. The clip lives on the inner bio-btn
             panel so per-block colors/gradients apply normally; box shadows
             are intentionally clipped away by the shape. --}}
        @once('bio-arrow-hex-round-style')
        <style>
            .bio-arrow-hex-round {
                clip-path: polygon(26px 0%, calc(100% - 26px) 0%, 100% 50%, calc(100% - 26px) 100%, 26px 100%, 0% 50%);
                border-radius: 0;
            }
            @supports (clip-path: shape(from 0 0, line to 100% 0, line to 100% 100%, close)) {
                .bio-arrow-hex-round {
                    clip-path: shape(
                        from 38px 0,
                        line to calc(100% - 38px) 0,
                        curve to calc(100% - 19px) 14% with calc(100% - 26px) 0,
                        line to calc(100% - 6px) 39%,
                        curve to calc(100% - 6px) 61% with 100% 50%,
                        line to calc(100% - 19px) 86%,
                        curve to calc(100% - 38px) 100% with calc(100% - 26px) 100%,
                        line to 38px 100%,
                        curve to 19px 86% with 26px 100%,
                        line to 6px 61%,
                        curve to 6px 39% with 0 50%,
                        line to 19px 14%,
                        curve to 38px 0 with 26px 0,
                        close
                    );
                }
            }
        </style>
        @endonce
        <a href="{{ $_url }}" target="_blank" rel="noopener"
           class="block w-full mb-3 transition-all duration-300 hover:-translate-y-0.5">
            <div class="bio-btn bio-arrow-hex-round w-full px-10 py-3.5 text-center font-bold uppercase tracking-wide flex items-center justify-center gap-2"
                 @if($btnInline) style="{{ $btnInline }}" @endif>
                @if($_icon)<i class="{{ $_icon }} text-[0.85em]"></i>@endif<span>{{ $_txt }}</span>
            </div>
        </a>
    @elseif($_lnkLayout === 'numbered_list')
        {{-- Numbered editorial list: big left-aligned text link with a small
             right-aligned index (01, 02, …). The index auto-increments per
             numbered link block on the page: we count numbered siblings in
             the page's $blocks collection up to this block. Falls back to
             the block's position-independent count of 1 when rendered
             standalone (editor single-block previews). --}}
        @php
            $_nlIdx = 1;
            if (isset($blocks) && $blocks instanceof \Illuminate\Support\Collection) {
                $_nlPos = 0;
                foreach ($blocks as $_nlB) {
                    if (($_nlB->settings['_style']['link_layout'] ?? '') === 'numbered_list') {
                        $_nlPos++;
                        if ($_nlB->id === $block->id) { $_nlIdx = $_nlPos; break; }
                    }
                }
            }
            $_nlColor = ($_st['text_color'] ?? '') !== '' ? $_st['text_color'] : ($fontColor ?? '#ffffff');
        @endphp
        <a href="{{ $_url }}" target="_blank" rel="noopener"
           class="w-full mb-2 py-1.5 flex items-center justify-between gap-6 text-left transition-opacity duration-200 hover:opacity-70"
           style="color: {{ $_nlColor }};">
            <span class="leading-tight" style="font-weight: {{ $_st['font_weight'] ?? '600' }}; font-size: {{ intval($_st['font_size'] ?? 0) ?: 28 }}px;@if(!empty($_st['font_family'])) font-family: '{{ str_replace("'", '', str_starts_with($_st['font_family'], 'custom:') ? substr($_st['font_family'], 7) : $_st['font_family']) }}', sans-serif;@endif">
                @if($_icon)<i class="{{ $_icon }} mr-2 text-[0.7em] opacity-80"></i>@endif{{ $_txt }}
            </span>
            <span class="text-[11px] font-medium tracking-[0.18em] opacity-75 shrink-0">{{ str_pad((string) $_nlIdx, 2, '0', STR_PAD_LEFT) }}</span>
        </a>
    @elseif($_lnkLayout === 'side_accent_tab')
        {{-- Side accent tab: full-width bar with a right-aligned label and a
             small contrasting tab hugging the outer right edge (teal + tan
             reference). The bar uses bg/text colors; the tab uses the
             block's border_color as its accent. --}}
        @php
            $_satBg = (($_st['bg_color'] ?? '') !== '' && ($_st['bg_color'] ?? '') !== 'transparent') ? $_st['bg_color'] : '#35595a';
            $_satInk = ($_st['text_color'] ?? '') !== '' ? $_st['text_color'] : '#fdf6ec';
            $_satAccent = ($_st['border_color'] ?? '') !== '' && ($_st['border_color'] ?? '') !== 'transparent' ? $_st['border_color'] : '#ddb387';
            $_satRadius = intval($_st['border_radius'] ?? 0);
        @endphp
        <a href="{{ $_url }}" target="_blank" rel="noopener"
           class="w-full mb-3 flex items-stretch gap-2.5 transition-all duration-300 hover:-translate-y-0.5">
            <span class="flex-1 flex items-center justify-end px-5 py-4 min-w-0"
                  style="background: {{ $_satBg }}; color: {{ $_satInk }}; border-radius: {{ $_satRadius }}px; font-weight: {{ $_st['font_weight'] ?? '600' }}; font-size: {{ intval($_st['font_size'] ?? 0) ?: 16 }}px;@if(!empty($_st['font_family'])) font-family: '{{ str_replace("'", '', str_starts_with($_st['font_family'], 'custom:') ? substr($_st['font_family'], 7) : $_st['font_family']) }}', sans-serif;@endif">
                @if($_icon)<i class="{{ $_icon }} mr-2 text-[0.85em] opacity-80"></i>@endif<span class="truncate">{{ $_txt }}</span>
            </span>
            <span aria-hidden="true" class="shrink-0" style="width: 26px; background: {{ $_satAccent }}; border-radius: {{ $_satRadius }}px;"></span>
        </a>
    @elseif($_lnkLayout === 'edge_bleed_bar')
        {{-- Edge-bleed bar: a full-width bar that bleeds to the page edge
             (the curated variant sets margin_left/right to 0 on the wrap),
             label right-aligned, with a small contrasting accent strip
             hugging the opposite page edge (teal + tan reference). The
             outer corners stay square so the bleed reads as intentional;
             border_radius (if any) only softens the inner-facing corners.
             Accent = border_color. --}}
        @php
            $_ebBg = (($_st['bg_color'] ?? '') !== '' && ($_st['bg_color'] ?? '') !== 'transparent') ? $_st['bg_color'] : '#3c5f5c';
            $_ebInk = ($_st['text_color'] ?? '') !== '' ? $_st['text_color'] : '#f7efe2';
            $_ebAccent = ($_st['border_color'] ?? '') !== '' && ($_st['border_color'] ?? '') !== 'transparent' ? $_st['border_color'] : '#dcb489';
            $_ebRadius = intval($_st['border_radius'] ?? 0);
        @endphp
        <a href="{{ $_url }}" target="_blank" rel="noopener"
           class="w-full mb-3 flex items-stretch gap-3 transition-opacity duration-200 hover:opacity-85">
            <span class="flex-1 flex items-center justify-end pr-6 pl-5 py-4 min-w-0"
                  style="background: {{ $_ebBg }}; color: {{ $_ebInk }}; border-radius: 0 {{ $_ebRadius }}px {{ $_ebRadius }}px 0; font-weight: {{ $_st['font_weight'] ?? '600' }}; font-size: {{ intval($_st['font_size'] ?? 0) ?: 16 }}px;@if(!empty($_st['font_family'])) font-family: '{{ str_replace("'", '', str_starts_with($_st['font_family'], 'custom:') ? substr($_st['font_family'], 7) : $_st['font_family']) }}', sans-serif;@endif">
                @if($_icon)<i class="{{ $_icon }} mr-2 text-[0.85em] opacity-80"></i>@endif<span class="truncate">{{ $_txt }}</span>
            </span>
            <span aria-hidden="true" class="shrink-0" style="width: 20px; background: {{ $_ebAccent }}; border-radius: {{ $_ebRadius }}px 0 0 {{ $_ebRadius }}px;"></span>
        </a>
    @elseif($_lnkLayout === 'double_border')
        {{-- Double-border button: an inner ring inset inside the outer
             border for the framed "menu card" look (cream WEBSITE
             reference). Outer border derives from border_color/width;
             the inner ring is a thinner line 4px inside, drawn on an
             absolutely-positioned overlay so bg/gradients apply normally. --}}
        @php
            $_dbBg = (($_st['bg_color'] ?? '') !== '' && ($_st['bg_color'] ?? '') !== 'transparent') ? $_st['bg_color'] : '#f6efe3';
            $_dbInk = ($_st['text_color'] ?? '') !== '' ? $_st['text_color'] : '#42351f';
            $_dbLine = ($_st['border_color'] ?? '') !== '' && ($_st['border_color'] ?? '') !== 'transparent' ? $_st['border_color'] : $_dbInk;
            $_dbW = intval($_st['border_width'] ?? 0) ?: 2;
            $_dbRadius = intval($_st['border_radius'] ?? 0) ?: 12;
        @endphp
        <a href="{{ $_url }}" target="_blank" rel="noopener"
           class="relative block w-full mb-3 text-center transition-all duration-300 hover:-translate-y-0.5"
           style="background: {{ $_dbBg }}; color: {{ $_dbInk }}; border: {{ $_dbW }}px solid {{ $_dbLine }}; border-radius: {{ $_dbRadius }}px; padding: {{ intval($_st['padding'] ?? 0) ?: 16 }}px 20px; font-weight: {{ $_st['font_weight'] ?? '600' }}; font-size: {{ intval($_st['font_size'] ?? 0) ?: 16 }}px; letter-spacing: 0.06em;@if(!empty($_st['font_family'])) font-family: '{{ str_replace("'", '', str_starts_with($_st['font_family'], 'custom:') ? substr($_st['font_family'], 7) : $_st['font_family']) }}', sans-serif;@endif">
            <span aria-hidden="true" class="absolute pointer-events-none"
                  style="inset: 4px; border: 1px solid {{ $_dbLine }}; border-radius: {{ max($_dbRadius - 4, 0) }}px;"></span>
            <span class="relative uppercase">@if($_icon)<i class="{{ $_icon }} mr-2 text-[0.85em] opacity-80"></i>@endif{{ $_txt }}</span>
        </a>
    @elseif($_lnkLayout === 'riveted_plaque')
        {{-- Riveted plaque (Task #6602): the double-border framed look
             dressed as a dark plaque — metallic outer + inner frame and
             four small rivet/stud dots in the corners between the two
             frames ("About Us" gold-on-black reference). Frame color =
             border_color; rivets pick up the same metal tone with a
             radial highlight so they read as raised studs. Pure CSS —
             no external assets. --}}
        @php
            $_rpBg = (($_st['bg_color'] ?? '') !== '' && ($_st['bg_color'] ?? '') !== 'transparent') ? $_st['bg_color'] : '#17161a';
            $_rpInk = ($_st['text_color'] ?? '') !== '' ? $_st['text_color'] : '#f3ede0';
            $_rpMetal = ($_st['border_color'] ?? '') !== '' && ($_st['border_color'] ?? '') !== 'transparent' ? $_st['border_color'] : '#c9a35c';
            $_rpW = intval($_st['border_width'] ?? 0) ?: 2;
            $_rpRadius = intval($_st['border_radius'] ?? 0) ?: 10;
            $_rpFont = !empty($_st['font_family'])
                ? "'" . str_replace("'", '', str_starts_with($_st['font_family'], 'custom:') ? substr($_st['font_family'], 7) : $_st['font_family']) . "', Georgia, serif"
                : "'Playfair Display', Georgia, serif";
        @endphp
        <a href="{{ $_url }}" target="_blank" rel="noopener"
           class="relative block w-full mb-3 text-center transition-all duration-300 hover:-translate-y-0.5"
           style="background: {{ $_rpBg }}; color: {{ $_rpInk }}; border: {{ $_rpW }}px solid {{ $_rpMetal }}; border-radius: {{ $_rpRadius }}px; padding: {{ intval($_st['padding'] ?? 0) ?: 18 }}px 24px; font-family: {{ $_rpFont }}; font-weight: {{ $_st['font_weight'] ?? '500' }}; font-size: {{ intval($_st['font_size'] ?? 0) ?: 17 }}px; letter-spacing: 0.05em; box-shadow: inset 0 0 0 1px color-mix(in srgb, {{ $_rpMetal }} 35%, transparent), 0 4px 14px rgba(0,0,0,0.35);">
            <span aria-hidden="true" class="absolute pointer-events-none"
                  style="inset: 9px; border: 1px solid {{ $_rpMetal }}; border-radius: {{ max($_rpRadius - 6, 2) }}px;"></span>
            @foreach ([['top:4px;left:4px;'], ['top:4px;right:4px;'], ['bottom:4px;left:4px;'], ['bottom:4px;right:4px;']] as $_rpPos)
                <span aria-hidden="true" class="absolute pointer-events-none rounded-full"
                      style="{{ $_rpPos[0] }} width: 5px; height: 5px; background: radial-gradient(circle at 32% 30%, #ffffffcc, {{ $_rpMetal }} 55%, color-mix(in srgb, {{ $_rpMetal }} 55%, #000) 100%);"></span>
            @endforeach
            <span class="relative">@if($_icon)<i class="{{ $_icon }} mr-2 text-[0.85em] opacity-80"></i>@endif{{ $_txt }}</span>
        </a>
    @elseif($_lnkLayout === 'sparkle_pill')
        {{-- Sparkle pill (Task #6602): thin-outline pill with small
             decorative four-point sparkle glyphs anchored just past the
             top-right and bottom-left of the pill; centered serif label
             ("WEBSITE" cream reference). Sparkles are inline SVG in
             currentColor — no external assets. Outline/sparkle color =
             border_color → text_color. --}}
        @php
            $_spInk = ($_st['text_color'] ?? '') !== '' ? $_st['text_color'] : '#2c2a26';
            $_spLine = ($_st['border_color'] ?? '') !== '' && ($_st['border_color'] ?? '') !== 'transparent' ? $_st['border_color'] : $_spInk;
            $_spBg = (($_st['bg_color'] ?? '') !== '' ) ? $_st['bg_color'] : 'transparent';
            $_spW = intval($_st['border_width'] ?? 0) ?: 1;
            $_spFont = !empty($_st['font_family'])
                ? "'" . str_replace("'", '', str_starts_with($_st['font_family'], 'custom:') ? substr($_st['font_family'], 7) : $_st['font_family']) . "', Georgia, serif"
                : "'Playfair Display', Georgia, 'Times New Roman', serif";
            $_spSparkle = 'M12 0 C13.2 7.4 16.6 10.8 24 12 C16.6 13.2 13.2 16.6 12 24 C10.8 16.6 7.4 13.2 0 12 C7.4 10.8 10.8 7.4 12 0 Z';
        @endphp
        <a href="{{ $_url }}" target="_blank" rel="noopener"
           class="relative block w-full mb-3 transition-all duration-300 hover:-translate-y-0.5"
           style="padding: 9px 12px;">
            <span class="block w-full text-center"
                  style="background: {{ $_spBg }}; color: {{ $_spInk }}; border: {{ $_spW }}px solid {{ $_spLine }}; border-radius: 999px; padding: {{ intval($_st['padding'] ?? 0) ?: 14 }}px 24px; font-family: {{ $_spFont }}; font-weight: {{ $_st['font_weight'] ?? '500' }}; font-size: {{ intval($_st['font_size'] ?? 0) ?: 18 }}px; letter-spacing: 0.06em;">
                @if($_icon)<i class="{{ $_icon }} mr-1.5 text-[0.85em] opacity-80"></i>@endif{{ $_txt }}
            </span>
            <svg aria-hidden="true" viewBox="0 0 24 24" class="absolute pointer-events-none" style="top: 0; right: 6%; width: 19px; height: 19px; color: {{ $_spLine }};" fill="currentColor"><path d="{{ $_spSparkle }}"/></svg>
            <svg aria-hidden="true" viewBox="0 0 24 24" class="absolute pointer-events-none" style="bottom: 0; left: 8%; width: 15px; height: 15px; color: {{ $_spLine }};" fill="currentColor"><path d="{{ $_spSparkle }}"/></svg>
        </a>
    @elseif($_lnkLayout === 'notched_bar')
        {{-- Notched bar (Task #6602): solid full-width bar with clipped
             45° corners on all four corners (elongated-octagon "OUR
             MENU" black reference), bold uppercase centered label.
             Sharp polygon fallback everywhere; browsers with CSS
             shape() get gently rounded notch vertices (same pattern as
             arrow_hex_round). Clip lives on the inner bio-btn panel so
             per-block colors/gradients apply normally; shadows are
             intentionally clipped away. --}}
        @once('bio-notched-bar-style')
        <style>
            .bio-notched-bar {
                clip-path: polygon(16px 0%, calc(100% - 16px) 0%, 100% 34%, 100% 66%, calc(100% - 16px) 100%, 16px 100%, 0% 66%, 0% 34%);
                border-radius: 0;
            }
            @supports (clip-path: shape(from 0 0, line to 100% 0, line to 100% 100%, close)) {
                .bio-notched-bar {
                    clip-path: shape(
                        from 20px 0,
                        line to calc(100% - 20px) 0,
                        curve to calc(100% - 13px) 8% with calc(100% - 16px) 0,
                        line to calc(100% - 2px) 28%,
                        curve to 100% 38% with 100% 32%,
                        line to 100% 62%,
                        curve to calc(100% - 2px) 72% with 100% 68%,
                        line to calc(100% - 13px) 92%,
                        curve to calc(100% - 20px) 100% with calc(100% - 16px) 100%,
                        line to 20px 100%,
                        curve to 13px 92% with 16px 100%,
                        line to 2px 72%,
                        curve to 0 62% with 0 68%,
                        line to 0 38%,
                        curve to 2px 28% with 0 32%,
                        line to 13px 8%,
                        curve to 20px 0 with 16px 0,
                        close
                    );
                }
            }
        </style>
        @endonce
        <a href="{{ $_url }}" target="_blank" rel="noopener"
           class="block w-full mb-3 transition-all duration-300 hover:-translate-y-0.5">
            <div class="bio-btn bio-notched-bar w-full px-8 py-4 text-center font-bold uppercase tracking-[0.08em] flex items-center justify-center gap-2"
                 @if($btnInline) style="{{ $btnInline }}" @endif>
                @if($_icon)<i class="{{ $_icon }} text-[0.85em]"></i>@endif<span>{{ $_txt }}</span>
            </div>
        </a>
    @elseif($_lnkLayout === 'speech_bubble')
        {{-- Speech bubble (Task #6602): chunky rounded-rectangle bubble
             with a small tail poking out of the bottom-right corner and
             a left-aligned bold rounded label ("MY WORK" brown/mustard
             reference). The tail is a CSS-clipped span sharing the
             bubble's background (so gradients work too) — no external
             assets. Wrapper padding reserves the tail room inside the
             block box. --}}
        @php
            $_sbBg = (($_st['bg_color'] ?? '') !== '' && ($_st['bg_color'] ?? '') !== 'transparent') ? $_st['bg_color'] : '#6b4a2f';
            $_sbInk = ($_st['text_color'] ?? '') !== '' ? $_st['text_color'] : '#f7ead3';
            $_sbRadius = intval($_st['border_radius'] ?? 0) ?: 26;
            $_sbBorder = (($_st['border_style'] ?? 'none') !== 'none' && intval($_st['border_width'] ?? 0) > 0)
                ? (intval($_st['border_width']) . 'px ' . $_st['border_style'] . ' ' . (($_st['border_color'] ?? '') !== '' ? $_st['border_color'] : $_sbInk))
                : 'none';
            $_sbFont = !empty($_st['font_family'])
                ? "'" . str_replace("'", '', str_starts_with($_st['font_family'], 'custom:') ? substr($_st['font_family'], 7) : $_st['font_family']) . "', sans-serif"
                : "'Baloo 2', 'Nunito', sans-serif";
        @endphp
        <a href="{{ $_url }}" target="_blank" rel="noopener"
           class="relative block w-full mb-3 transition-all duration-300 hover:-translate-y-0.5"
           style="padding-bottom: 12px;">
            <span class="block w-full text-left uppercase"
                  style="background: {{ $_sbBg }}; color: {{ $_sbInk }}; border: {{ $_sbBorder }}; border-radius: {{ $_sbRadius }}px; padding: {{ intval($_st['padding'] ?? 0) ?: 22 }}px 28px; font-family: {{ $_sbFont }}; font-weight: {{ $_st['font_weight'] ?? '800' }}; font-size: {{ intval($_st['font_size'] ?? 0) ?: 19 }}px; letter-spacing: 0.04em;">
                @if($_icon)<i class="{{ $_icon }} mr-2 text-[0.9em]"></i>@endif{{ $_txt }}
            </span>
            <span aria-hidden="true" class="absolute pointer-events-none"
                  style="bottom: 0; right: 22px; width: 26px; height: 16px; background: {{ $_sbBg }}; clip-path: polygon(0 0, 100% 0, 100% 100%, 55% 30%);"></span>
        </a>
    @elseif($_lnkLayout === 'icon_top')
        {{-- Icon above label: chromeless stacked icon + small label, built
             for grid-span multi-column use (green Printers/Monitors
             reference). Uses bio-btn chrome so an optional bg/border still
             applies; the seeded style keeps it transparent by default. --}}
        <a href="{{ $_url }}" target="_blank" rel="noopener"
           class="bio-btn block w-full h-full px-3 py-5 mb-3 text-center transition-all duration-300 hover:opacity-80"
           @if($btnInline) style="{{ $btnInline }}" @endif>
            @if($_thumb)
                <img src="{{ $_thumb }}" class="w-12 h-12 mx-auto mb-2.5 object-cover rounded-lg" alt="">
            @else
                <i class="{{ $_icon ?: 'fas fa-link' }} block mx-auto mb-2.5 text-3xl"></i>
            @endif
            <span class="block text-sm leading-snug">{{ $_txt }}</span>
        </a>
    @elseif($_lnkLayout === 'offset_frame')
        {{-- Offset frame: solid bar with a thin outline frame offset toward
             the bottom-right (clay/cream reference). Frame color = the
             block's border_color, falling back to the bar color. --}}
        @php
            $_ofBg = (($_st['bg_color'] ?? '') !== '' && ($_st['bg_color'] ?? '') !== 'transparent') ? $_st['bg_color'] : '#a98a7d';
            $_ofInk = ($_st['text_color'] ?? '') !== '' ? $_st['text_color'] : '#f9f2ec';
            $_ofFrame = ($_st['border_color'] ?? '') !== '' && ($_st['border_color'] ?? '') !== 'transparent' ? $_st['border_color'] : $_ofBg;
            $_ofRadius = intval($_st['border_radius'] ?? 0);
            $_ofFont = !empty($_st['font_family'])
                ? "'" . str_replace("'", '', str_starts_with($_st['font_family'], 'custom:') ? substr($_st['font_family'], 7) : $_st['font_family']) . "', Georgia, serif"
                : "'Playfair Display', Georgia, serif";
        @endphp
        <a href="{{ $_url }}" target="_blank" rel="noopener"
           class="block w-full mb-3 relative transition-all duration-300 hover:-translate-y-0.5"
           style="padding: 0 10px 10px 0;">
            <span aria-hidden="true" class="absolute pointer-events-none"
                  style="top: 10px; left: 10px; right: 0; bottom: 0; border: 1px solid {{ $_ofFrame }}; border-radius: {{ $_ofRadius }}px;"></span>
            <span class="relative block w-full text-center"
                  style="background: {{ $_ofBg }}; color: {{ $_ofInk }}; border-radius: {{ $_ofRadius }}px; padding: {{ intval($_st['padding'] ?? 0) ?: 18 }}px 16px; font-family: {{ $_ofFont }}; font-weight: {{ $_st['font_weight'] ?? '500' }}; font-size: {{ intval($_st['font_size'] ?? 0) ?: 18 }}px; letter-spacing: 0.03em;">
                @if($_icon)<i class="{{ $_icon }} mr-1.5 text-[0.85em] opacity-80"></i>@endif{{ $_txt }}
            </span>
        </a>
    @elseif($_lnkLayout === 'torn_tape')
        {{-- Torn tape: washi-tape strip with jagged torn left and right
             edges (brown "About me" reference). The zigzag is a fixed
             clip-path polygon; colors come from the block style. --}}
        @php
            $_ttBg = (($_st['bg_color'] ?? '') !== '' && ($_st['bg_color'] ?? '') !== 'transparent') ? $_st['bg_color'] : '#a17c5b';
            $_ttInk = ($_st['text_color'] ?? '') !== '' ? $_st['text_color'] : '#fdf8f2';
            $_ttFont = !empty($_st['font_family'])
                ? "'" . str_replace("'", '', str_starts_with($_st['font_family'], 'custom:') ? substr($_st['font_family'], 7) : $_st['font_family']) . "', Georgia, serif"
                : "'Lora', Georgia, serif";
        @endphp
        <a href="{{ $_url }}" target="_blank" rel="noopener"
           class="block w-full mb-3 transition-all duration-300 hover:-translate-y-0.5">
            <span class="block w-full text-center"
                  style="background: {{ $_ttBg }}; color: {{ $_ttInk }}; padding: {{ intval($_st['padding'] ?? 0) ?: 20 }}px 28px; font-family: {{ $_ttFont }}; font-weight: {{ $_st['font_weight'] ?? '400' }}; font-size: {{ intval($_st['font_size'] ?? 0) ?: 20 }}px; letter-spacing: 0.02em; clip-path: polygon(1.2% 0%, 98.6% 0%, 100% 9%, 98.9% 18%, 99.8% 30%, 98.7% 42%, 100% 55%, 99% 66%, 99.9% 78%, 98.8% 90%, 99.6% 100%, 1.5% 100%, 0.2% 91%, 1.4% 80%, 0.4% 68%, 1.3% 56%, 0.3% 45%, 1.5% 33%, 0.5% 22%, 1.6% 10%);">
                @if($_icon)<i class="{{ $_icon }} mr-1.5 text-[0.85em] opacity-80"></i>@endif{{ $_txt }}
            </span>
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
