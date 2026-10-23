{{--
    Standalone preview document for one block TYPE, shown in the editor
    palette flyout before a creator commits to adding it.

    Deliberately a sibling of admin/block-defaults/preview-frame rather
    than a copy with different colours: that one answers "what do my
    admin overrides do", on a neutral dark stage. This one answers "what
    will this look like on MY page", so it paints the creator's own
    background, font colour and button colours.

    It is a flat approximation of the page, not a clone of it: the
    fallback background colour and image, without the slideshow, torn and
    tiled layers the real page can carry. A 220px-wide flyout is not the
    place to reproduce a parallax backdrop, and the block is the subject.
--}}
@php
    $s = $block->settings ?? [];
    $blockStyle = \App\Modules\User\Models\BiolinkBlock::getBlockStyle($s, []);
    $blockInline = \App\Modules\User\Models\BiolinkBlock::buildInlineStyle($blockStyle);
    $hasCustomStyle = !empty($s['_style']);
    // Same wrapper rules as the public page: button-like blocks apply the
    // style to the <a> itself; self-styling blocks skip the wrapper div.
    $btnLikeBlocks = ['link', 'link_big', 'cta_button', 'button'];
    $isBtnLike = in_array($block->type, $btnLikeBlocks);
    $skipWrap = in_array($block->type, ['avatar', 'divider', 'spacer', 'social_icons'])
        || str_starts_with($block->type, 'profile_card')
        || $isBtnLike;
    $btnInline = ($isBtnLike && $hasCustomStyle) ? $blockInline : '';
    $gridSpan = intval($blockStyle['grid_span'] ?? 12) ?: 12;

    $bs = $link->settings['biolink'] ?? [];
    // previewGround() exists for exactly this: it resolves colour AND
    // gradient grounds (resolve()'s fallbackColor flattens a gradient to
    // nothing), and its `ink` is the colour a block's text actually
    // inherits on the page rather than a hardcoded white -- which is what
    // keeps a light-themed page from previewing white on white.
    $ground = \App\Modules\User\Support\PageBackground::previewGround($bs);
    $bgPaint = $ground['bg'];
    // Already contrast-checked against the ground it is going to be painted
    // on -- previewGround does it for every page now, not only for pages
    // that were never themed. The gate used to live here and let a themed
    // light page keep its stale white ink.
    $fontColor = $ground['ink'];
    $fontFamily = $bs['font_family'] ?? 'Space Grotesk';
    $previewFontHref = \App\Modules\User\Support\FontCatalog::googleHref((string) $fontFamily);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>Preview: {{ $block->type }}</title>
    @vite(['resources/css/app.css'])
    @include('common.partials.fontawesome')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    @if($previewFontHref)
        <link href="{{ $previewFontHref }}" rel="stylesheet">
    @endif
    <style>
        html { scroll-behavior: auto; background: {{ $bgPaint }}; }
        body {
            margin: 0;
            font-family: '{{ $fontFamily }}', 'Space Grotesk', sans-serif;
            color: {{ $fontColor }};
            {{-- One `background` shorthand, because the ground may be a
                 gradient as readily as a flat colour. --}}
            background: {{ $bgPaint }};
            background-size: cover;
            background-position: center;
            {{-- Deliberately no min-height. The flyout sizes this frame
                 from the document's own height, and a body that always
                 fills its frame can never report anything smaller than
                 the frame it is already in. html carries the same ground
                 so nothing shows through underneath. --}}
            min-height: 0;
        }
        {{-- 12-column stage matching the public biolink grid so a block
             whose default width is less than full still reads correctly. --}}
        .preview-stage {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 8px;
            max-width: 460px;
            margin: 0 auto;
            padding: 14px 12px 18px;
            align-items: start;
        }
        .biolink-block-wrap { min-width: 0; }
        a { color: inherit; }
        img, iframe, video, audio, embed, object { max-width: 100%; }
        iframe, video { width: 100%; }
        {{-- The flyout is a still life. Nothing in it should animate,
             autoplay or demand attention while the creator is reading. --}}
        *, *::before, *::after {
            animation-duration: 0s !important;
            animation-delay: 0s !important;
            transition-duration: 0s !important;
        }
    </style>
</head>
<body>
    <div class="preview-stage">
        <div data-block-id="0" data-block-type="{{ $block->type }}"
             class="biolink-block-wrap" style="grid-column: span {{ $gridSpan }}">
            @if($hasCustomStyle && !$skipWrap)<div class="block-styled" style="{{ $blockInline }}">@endif

                @include('common.partials.biolink-block-render', ['link' => $link, 'block' => $block, 's' => $s, 'fontColor' => $fontColor, 'btnInline' => $btnInline])

            @if($hasCustomStyle && !$skipWrap)</div>@endif
        </div>
    </div>
</body>
</html>
