{{--
    Standalone preview document for the admin Block Defaults editor.

    Rendered by BlockDefaultsController::preview() with a transient block
    built from the current form state, and injected into a sandboxed
    <iframe> on the edit screen. Replicates the exact per-block wrapper
    semantics of common/biolink.blade.php (getBlockStyle → buildInlineStyle,
    btn-like blocks, $skipWrap, grid_span) and dispatches through the same
    shared public renderer so the preview is the real thing, not a mock.
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
    $fontColor = '#ffffff';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>Preview — {{ $type }}</title>
    @vite(['resources/css/app.css'])
    @include('common.partials.fontawesome')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            font-family: 'Space Grotesk', sans-serif;
            color: {{ $fontColor }};
            background: linear-gradient(135deg, #0b0f1a 0%, #101830 50%, #0b0f1a 100%);
            min-height: 100vh;
        }
        {{-- 12-column stage matching the public biolink grid so grid_span
             overrides visibly change the block's width. --}}
        .preview-stage {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 8px;
            max-width: 460px;
            margin: 0 auto;
            padding: 28px 16px 40px;
            align-items: start;
        }
        .biolink-block-wrap { min-width: 0; }
        a { color: inherit; }
        img { max-width: 100%; }
    </style>
</head>
<body>
    <div class="preview-stage">
        <div data-block-id="0" data-block-type="{{ $block->type }}"
             class="biolink-block-wrap" style="grid-column: span {{ $gridSpan }}">
            @if($hasCustomStyle && !$skipWrap)<div class="mb-3 block-styled" style="{{ $blockInline }}">@endif

                @include('common.partials.biolink-block-render', ['link' => $link, 'block' => $block, 's' => $s, 'fontColor' => $fontColor, 'btnInline' => $btnInline])

            @if($hasCustomStyle && !$skipWrap)</div>@endif
        </div>
    </div>
    <script defer src="{{ asset('js/vendor/alpine.min.js') }}"></script>
</body>
</html>
