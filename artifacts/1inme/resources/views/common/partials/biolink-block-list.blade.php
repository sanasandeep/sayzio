{{--
    Every block on a link, rendered.

    Sana, 2026-09-23, on a restaurant menu: "default blocks are shown but
    in live no blocks.. even i tried to add but still no blocks".

    He was right, and the reason was simple: this loop lived inline in
    common/biolink.blade.php and nowhere else. A menu, a store, a booking
    page and a review wall are all in Link::BIOLINK_FAMILY, so they share
    the block EDITOR -- you can add a block to a menu, it saves, it shows
    in the editor's preview -- and then their own public templates never
    looked at biolink_blocks at all. The blocks were there the whole time
    with nothing rendering them.

    So the loop is a partial, and it is the only copy. That also answers
    his earlier menu note ("need blocks of link in bio also here as it
    shows like advertizements"): the fix and the feature are one change.

    Parameters
      $link            (Link)    whose blocks to draw
      $blkFontColor    (string)  page ink, for blocks that inherit it
      $blkGlobalTheme  (array)   settings.biolink.block_theme
      $blkBtnInline    (string)  inline button CSS the page computed
      $blkSlot         (?string) 'above' | 'below' to draw only the blocks
                                 assigned to that side of a menu; null for
                                 every block, which is what a Link in Bio
                                 passes since it has no menu to sit around
      $blkEmpty        (bool)    draw the "being set up" placeholder when
                                 there is nothing. A menu has its own
                                 content, so it passes false -- an empty
                                 block list there is normal, not an empty
                                 page.

    The starter-page gate lives here rather than in each caller: a page
    whose five example blocks have never been edited must not show them to
    a visitor, and that has to hold wherever the blocks are drawn.
--}}
@php
    $blocks = ($link->_abVariantBlocks instanceof \Illuminate\Support\Collection)
        ? $link->_abVariantBlocks->filter(fn ($b) => $b->isVisible())
        : $link->activeBiolinkBlocks()->get()->filter(fn ($b) => $b->isVisible());

    if ($blocks->isNotEmpty() && $link->isUntouchedStarterPage()) {
        $blocks = collect();
    }

    // Which side of the menu a block sits on. Only menus pass a slot;
    // everything else draws the lot. A block that has never been given a
    // side counts as "below", because the menu is what the page is for
    // and blocks around it are extras.
    if (isset($blkSlot) && $blkSlot) {
        $blocks = $blocks->filter(
            fn ($b) => (($b->settings['_style']['_menu_slot'] ?? 'below') === $blkSlot)
        );
    }

    $fontColor   = $blkFontColor   ?? '#ffffff';
    $globalTheme = $blkGlobalTheme ?? [];
    $btnInline   = $blkBtnInline   ?? '';
    $pageTitle       = $pageTitle       ?? ($link->title ?: 'Link in Bio');
    $pageDescription = $pageDescription ?? '';
    $blkEmpty = $blkEmpty ?? true;
@endphp

@forelse($blocks as $block)
    @php
        $s = $block->settings ?? [];
        $blockStyle = \App\Modules\User\Models\BiolinkBlock::getBlockStyle($s, $globalTheme);
        // Task #6114: horizontal margins render on the wrap (see
        // $wrapExtraStyle below), so skip them here to avoid
        // double-applying.
        $blockInline = \App\Modules\User\Models\BiolinkBlock::buildInlineStyle($blockStyle, true);
        $hasCustomStyle = !empty($s['_style']) || (!empty($globalTheme) && ($globalTheme['apply_to_all'] ?? false));
        // Button-like blocks must apply the preset directly to the
        // <a> element (the actual visible button), NOT to a wrapper
        // div around it — otherwise "Neon Glow" haloes the empty
        // padding around the button instead of the button itself.
        $btnLikeBlocks = ['link', 'link_big', 'cta_button', 'button'];
        $isBtnLike = in_array($block->type, $btnLikeBlocks);
        // Profile cards (Task #1740) own their full card surface — the
        // identity-design renderer applies $blockInline itself and
        // needs overflow-hidden to clip cover images — so the generic
        // .block-styled wrapper must not double-wrap them.
        // Card containers (Task #6173) apply their unified `_style`
        // directly on their own .card-container-render div inside
        // the render partial — wrapping them here would double-apply
        // the background/border/shadow chrome.
        // Browser-window chrome (Task #6568): the render partial
        // draws the full window frame (bg, border, hard shadow)
        // itself, so the generic styled wrapper must not
        // double-apply the block's card chrome around it.
        $hasWindowChrome = !empty($s['_style']['_window_chrome']);
        $skipWrap = in_array($block->type, ['avatar', 'divider', 'spacer', 'social_icons', 'card'])
            || str_starts_with($block->type, 'profile_card')
            || $isBtnLike
            || $hasWindowChrome;
        $btnInline = ($isBtnLike && $hasCustomStyle) ? $blockInline : '';
        // Catalog preset background layer (Task #5970): painted on an
        // absolutely-positioned layer behind the block content at the
        // chosen transparency. Card containers draw their own layer
        // inside the container branch of the render partial.
        $presetLayer = \App\Modules\User\Models\BiolinkBlock::isContainerType($block->type)
            ? null
            : \App\Modules\User\Models\BiolinkBlock::presetLayer($blockStyle);
    @endphp

    @php
        $gridSpan = intval($blockStyle['grid_span'] ?? 12) ?: 12;
        // Desktop overrides — sanitizer bounds these to 1..12 / 1..6.
        $mdSpan = intval($blockStyle['grid_span_md'] ?? 0);
        $rowSpan = intval($blockStyle['grid_row_span'] ?? 0);
        $mdRowSpan = intval($blockStyle['grid_row_span_md'] ?? 0);
        $wrapExtraClass = ($mdSpan ? ' md-span' : '') . ($rowSpan ? ' row-span' : '') . ($mdRowSpan ? ' md-row-span' : '');
        $wrapExtraStyle = ($mdSpan ? ";--md-span:{$mdSpan}" : '') . ($rowSpan ? ";--row-span:{$rowSpan}" : '') . ($mdRowSpan ? ";--md-row-span:{$mdRowSpan}" : '');
        // Task #6114: side spacing lives on the wrap. An explicit
        // _style margin_left/right — including 0 for a full-width
        // block — overrides the container's default child margin.
        $mxL = $blockStyle['margin_left'] ?? '';
        $mxR = $blockStyle['margin_right'] ?? '';
        $wrapExtraStyle .= ($mxL !== '' && $mxL !== null ? ';margin-left:' . (0 + $mxL) . 'px' : '')
            . ($mxR !== '' && $mxR !== null ? ';margin-right:' . (0 + $mxR) . 'px' : '');
        // Task #1041: forward variant metadata hooks as data-attrs
        // so CSS in <style> can drive heading animations, gallery
        // layouts, and social icon style sets without per-block
        // PHP branching. Sanitizer guarantees these are slug-safe.
        $_animAttr = $s['_style']['_animation'] ?? '';
        $_galAttr = $s['_style']['_gallery_layout'] ?? '';
        $_socAttr = $s['_style']['_social_set'] ?? '';
    @endphp
    @php
        // Task #1094 — surface enough metadata on the wrap that the
        // public-page JS can render countdown / remaining-count
        // badges and react to expiry without a per-render server
        // call. We only emit attrs when the block actually has
        // something to display; for "naked" blocks the JS is a no-op.
        $_lim = $block->hasLimits() ? $block->limitsState() : null;
        $_limCfg = is_array($s['_limits'] ?? null) ? $s['_limits'] : [];
    @endphp
    <div data-block-id="{{ $block->id }}" data-block-type="{{ $block->type }}" data-tab="{{ $s['_tab_id'] ?? '' }}"
         @if($_animAttr) data-anim="{{ $_animAttr }}" @endif
         @if($_galAttr) data-gallery-layout="{{ $_galAttr }}" @endif
         @if($_socAttr) data-social-set="{{ $_socAttr }}" @endif
         @if(!empty($blockStyle['stack_mobile'])) data-stack-mobile="1" @endif
         @if($_lim)
             data-limits="1"
             data-limit-state="{{ $_lim['state'] }}"
             @if(!is_null($_lim['expires_at'])) data-expires-at="{{ $_lim['expires_at'] }}" @endif
             @if(!is_null($_lim['max_clicks'])) data-max-clicks="{{ $_lim['max_clicks'] }}" @endif
             @if(!is_null($_lim['remaining'])) data-remaining="{{ $_lim['remaining'] }}" @endif
             data-near-percent="{{ (int) ($_limCfg['near_threshold_percent'] ?? 20) }}"
             data-show-countdown="{{ !empty($_limCfg['show_countdown']) ? '1' : '0' }}"
             data-show-remaining="{{ !empty($_limCfg['show_remaining']) ? '1' : '0' }}"
             data-expired-action="{{ ($_limCfg['expired_action'] ?? 'hide') === 'show' ? 'show' : 'hide' }}"
             data-expired-label="{{ $_limCfg['expired_label'] ?? 'Sold out' }}"
             data-expired-emoji="{{ $_limCfg['expired_emoji'] ?? '' }}"
         @endif
         class="biolink-block-wrap{{ $wrapExtraClass }}" style="grid-column: span {{ $gridSpan }}{{ $wrapExtraStyle }}">
    @if($_lim && (!empty($_limCfg['show_countdown']) || !empty($_limCfg['show_remaining'])))
        {{-- Badge container — populated/updated by the limits ticker
             in JS below. Rendered server-side as well so the first
             paint is correct without a JS round-trip. --}}
        <div class="biolink-limit-badge mb-2 inline-flex items-center gap-1.5 text-[11px] font-semibold px-2 py-1 rounded-md"
             style="background: rgba(244,63,94,0.14); color: rgba(254,205,211,0.95); border: 1px solid rgba(244,63,94,0.25);"
             data-badge-for="{{ $block->id }}"></div>
    @endif
    @if($hasCustomStyle && !$skipWrap)
        <div class="mb-3 block-styled" style="{{ $blockInline }}{{ $presetLayer ? ';position:relative;isolation:isolate;overflow:hidden;' : '' }}">
        @if($presetLayer)
            {{-- Preset CSS resolves server-side from the catalog (never
                 client input); rtrimmed + re-terminated so a missing
                 trailing semicolon can't glue onto the next declaration. --}}
            <div class="block-bg-preset" aria-hidden="true" style="position:absolute;inset:0;z-index:-1;pointer-events:none;{!! $presetLayer['css'] !!};background-attachment:scroll !important;opacity:{{ $presetLayer['opacity'] / 100 }};"></div>
        @endif
    @elseif($presetLayer)
        {{-- skipWrap/button-like blocks with a preset still need a
             positioning context for the layer; blockInline stays on
             the inner element (btnInline) to avoid double-applying. --}}
        <div class="mb-3 block-preset-wrap" style="position:relative;isolation:isolate;overflow:hidden;border-radius:{{ ($blockStyle['border_radius'] ?? '') !== '' ? intval($blockStyle['border_radius']) : 14 }}px;">
            <div class="block-bg-preset" aria-hidden="true" style="position:absolute;inset:0;z-index:-1;pointer-events:none;{!! $presetLayer['css'] !!};background-attachment:scroll !important;opacity:{{ $presetLayer['opacity'] / 100 }};"></div>
    @endif

        {{-- Task #2042 — single source of truth: every top-level block
             renders through the unified dispatch partial, exactly like
             card/grid children do. No inline @if/@elseif chain here. --}}
        @include('common.partials.biolink-block-render', ['link' => $link, 'block' => $block, 's' => $s, 'fontColor' => $fontColor ?? '#ffffff', 'btnInline' => $btnInline])

    @if(($hasCustomStyle && !$skipWrap) || $presetLayer)</div>@endif
    </div>
@empty
@if($blkEmpty)
    <div class="text-center py-12" style="grid-column: 1 / -1;">
        <div class="w-20 h-20 rounded-full bg-white/10 backdrop-blur flex items-center justify-center mx-auto mb-4 border border-white/10">
            <span class="text-3xl font-bold">{{ strtoupper(substr($pageTitle, 0, 1)) }}</span>
        </div>
        <h1 class="text-2xl font-bold mb-2">{{ $pageTitle }}</h1>
        @if($pageDescription)
            <p class="text-sm mt-2" style="color: {{ $fontColor }}aa">{{ $pageDescription }}</p>
        @endif
        <div class="glass-block rounded-xl p-6 text-sm mt-6" style="color: {{ $fontColor }}88">
            This Link in Bio page is being set up. Check back soon!
        </div>
    </div>
@endif
@endforelse
