{{--
    Every standalone CSS rule that paints the page background.

    Expects $pb -- \App\Modules\User\Support\PageBackground::resolve().

    ORDER MATTERS in exactly one place, and it is preserved here: the
    template's catalog CSS hardcodes position:fixed / background-attachment,
    so the .bg-template.bg-layer overrides must come AFTER it. Everything
    else uses selectors disjoint from the rest of the page, so this block
    can sit wherever the host page's stylesheet finds it convenient.
--}}
@if($pb['hasLayer'])
{{-- Mobile-Safari-safe "Fixed" background: a fixed-position layer behind the
     content instead of `background-attachment: fixed` on the body. --}}
.bg-page-fixed {
    {{-- Translucent presets on "Scroll" still use this layer (opacity
         can't be applied to the body background itself); absolute
         positioning keeps the layer scrolling with the page. --}}
    position: {{ $pb['fixed'] ? 'fixed' : 'absolute' }};
    inset: 0;
    z-index: 0;
    pointer-events: none;
    @if($pb['presetTranslucent'])
        opacity: {{ $pb['presetOpacity'] / 100 }};
    @endif
    @if($pb['type'] === 'color')
        background-color: {{ $pb['color'] }};
    @elseif($pb['type'] === 'gradient')
        background: {{ $pb['gradient'] }};
    @elseif($pb['type'] === 'preset' && $pb['presetCss'])
        {!! rtrim($pb['presetCss'], "; \t\n\r") !!};
    @elseif($pb['type'] === 'preset')
        background-color: {{ $pb['fallbackColor'] }};
    @elseif(($pb['type'] === 'mesh' || $pb['type'] === 'pattern') && $pb['presetCss'])
        {!! rtrim($pb['presetCss'], "; \t\n\r") !!};
    @elseif($pb['type'] === 'mesh' || $pb['type'] === 'pattern')
        background-color: {{ $pb['fallbackColor'] }};
    @elseif($pb['type'] === 'image' && $pb['image'])
        {{-- See body-declarations: the fit is the creator's, and these are
             longhands so the colour below is not reset by a shorthand. --}}
        background-color: {{ $pb['fallbackColor'] }};
        background-image: url('{{ $pb['image'] }}');
        {!! $pb['fitCss'] !!}
    @elseif($pb['type'] === 'image')
        background-color: {{ $pb['fallbackColor'] }};
    @endif
}
@endif
@if($pb['tilesActive'])
{{-- Tiles background (Task #6204): a full-viewport grid of catalog
     gradient tiles on its own dedicated layer. "Fixed" pins it to
     the viewport (mobile-Safari-safe); "Scroll" absolutely positions
     it over the body. The optional pulse animation only runs when
     the visitor allows motion. --}}
.bg-tiles {
    position: {{ $pb['fixed'] ? 'fixed' : 'absolute' }};
    inset: 0;
    z-index: 0;
    pointer-events: none;
    overflow: hidden;
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    grid-auto-rows: minmax(14vh, 1fr);
    grid-auto-flow: dense;
    gap: 6px;
    padding: 6px;
    background-color: {{ $pb['fallbackColor'] }};
}
.bg-tiles span {
    border-radius: 10px;
    display: block;
}
@if($pb['tilesAnimate'])
@media (prefers-reduced-motion: no-preference) {
    @keyframes bgTilePulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.55; }
    }
    .bg-tiles span {
        animation: bgTilePulse 6s ease-in-out infinite;
    }
}
@endif
@endif
@if($pb['blur'] > 0 || $pb['overlayOpacity'] > 0)
body::after {
    content: '';
    position: fixed;
    inset: 0;
    z-index: 0;
    pointer-events: none;
    @if($pb['blur'] > 0)
        backdrop-filter: blur({{ $pb['blur'] }}px);
        -webkit-backdrop-filter: blur({{ $pb['blur'] }}px);
    @endif
    @if($pb['overlayOpacity'] > 0)
        @php
            $r = hexdec(substr($pb['overlayColor'], 1, 2));
            $g = hexdec(substr($pb['overlayColor'], 3, 2));
            $b = hexdec(substr($pb['overlayColor'], 5, 2));
        @endphp
        background: rgba({{ $r }},{{ $g }},{{ $b }},{{ $pb['overlayOpacity'] / 100 }});
    @endif
}
@endif
@if($pb['blur'] > 0 || $pb['overlayOpacity'] > 0 || $pb['hasLayer'] || $pb['tornActive'] || $pb['tilesActive'])
/* Lift page CONTENT above the background layers.

   A viewport-pinned overlay is NOT content: this rule's `position:
   relative` beats its own `position: fixed` on specificity and drops it
   into the flow, where it scrolls away with the page. That has now
   happened twice -- first to the share button, then to the menu cart's
   floating button and its order panel, which on any menu with a
   background layer turned into a button sitting at the bottom of the
   document and two dialogs stacked in the middle of it.

   So the exclusion is a MARKER rather than a growing list of class names:
   anything body-level that pins itself to the viewport says so by
   carrying `sz-pinned`, and is excluded once, here. `.sz-share` stays
   named for the pages that have not been given the marker yet. */
body > *:not(.bg-layer):not(.sz-pinned):not(.sz-share):not(script):not(style) {
    position: relative;
    z-index: 1;
}
@endif
@if($pb['tornActive'])
{{-- Torn-paper composite: full backdrop layer (photo or preset
     gradient) with a solid paper sheet clipped by a jagged torn
     diagonal on top. "Fixed" pins both layers to the viewport
     (mobile-Safari-safe, no background-attachment); "Scroll" makes
     them absolutely-positioned over the whole (relative) body so
     they move with the content. --}}
.bg-torn-backdrop {
    position: {{ $pb['fixed'] ? 'fixed' : 'absolute' }};
    inset: 0;
    z-index: 0;
    pointer-events: none;
    @if($pb['tornBackdropImage'])
        background: {{ $pb['fallbackColor'] }} url('{{ $pb['tornBackdropImage'] }}') center/cover no-repeat;
    @elseif($pb['tornBackdropCss'])
        {!! rtrim($pb['tornBackdropCss'], "; \t\n\r") !!};
    @else
        background-color: {{ $pb['fallbackColor'] }};
    @endif
}
.bg-torn-paper {
    position: {{ $pb['fixed'] ? 'fixed' : 'absolute' }};
    inset: 0;
    z-index: 0;
    pointer-events: none;
    {{-- drop-shadow on the wrapper follows the clip-path silhouette
         of the inner sheet (box-shadow would hug the clipped box). --}}
    filter: drop-shadow(4px 0 10px rgba(0,0,0,0.28));
}
{{-- Tear variant sheets (Task #6204): each style resolves to one or
     more clipped paper sheets from TornStyleCatalog (legacy pages
     without a torn_style render the classic diagonal default).
     Clip paths and shade factors come only from the catalog. --}}
@foreach($pb['tornSheets'] as $__i => $__sheet)
.bg-torn-paper .torn-sheet-{{ $__i }} {
    position: absolute;
    inset: 0;
    background-color: {{ \App\Modules\User\Support\TornStyleCatalog::shadeHex($pb['tornPaper'], $__sheet['shade']) }};
    clip-path: {{ $__sheet['clip'] }};
    -webkit-clip-path: {{ $__sheet['clip'] }};
}
@endforeach
@endif
@if($pb['type'] === 'slideshow' && count($pb['slideshowImages']) > 0)
{{-- "Fixed": the slideshow layer pins to the viewport. "Scroll": it becomes an
     absolutely-positioned layer covering the whole (relative) body so it moves
     with the content. Both are mobile-Safari-safe (no background-attachment). --}}
.bg-slideshow { position:{{ $pb['fixed'] ? 'fixed' : 'absolute' }}; inset:0; z-index:0; overflow:hidden; }
.bg-slideshow img {
    position:absolute; inset:0; width:100%; height:100%; object-fit:cover;
    opacity:0; transition:opacity 1.5s ease-in-out;
}
.bg-slideshow img.active { opacity:1; }
@endif
@if($pb['type'] === 'video')
.bg-video-wrap { position:fixed; inset:0; z-index:0; overflow:hidden; }
.bg-video-wrap video {
    min-width:100%; min-height:100%; width:auto; height:auto;
    position:absolute; top:50%; left:50%;
    transform:translate(-50%,-50%);
    object-fit:cover;
}
@endif
@if($pb['template'])
{!! $pb['template']->css !!}
@endif
/* Keep the fixed bg-template layer behind everything else. */
.bg-template.bg-layer { z-index: 0 !important; }
@if($pb['template'] && !$pb['fixed'])
/* User chose "Scroll": the template layer covers the whole (relative) body
   and moves with the content instead of pinning to the viewport. Catalog
   CSS hardcodes position:fixed / background-attachment:fixed, so override. */
.bg-template.bg-layer {
    position: absolute !important;
    background-attachment: scroll !important;
}
@endif
