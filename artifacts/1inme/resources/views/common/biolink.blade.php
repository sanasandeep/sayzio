@php
    $metaSettings = $link->settings['biolink']['meta'] ?? [];
    $ogSettings = $link->settings['biolink']['og'] ?? [];
    $twSettings = $link->settings['biolink']['twitter'] ?? [];
    $manifestSettings = $link->settings['biolink']['manifest'] ?? [];
    $faviconSettings = $link->settings['biolink']['favicons'] ?? [];
    $shareBtnSettings = $link->settings['biolink']['share_button'] ?? [];
    $menuBarSettings = $link->settings['biolink']['menu_bar'] ?? [];
    $autoTranslateSettings = $link->settings['biolink']['auto_translate'] ?? [];
    $pageTitle = $metaSettings['seo_title'] ?? $link->seo_title ?? $link->title ?? '1INME Link in Bio';
    $pageDesc = $metaSettings['seo_description'] ?? $link->seo_description ?? '';
    $pageImage = $ogSettings['image_url'] ?? $link->seo_image ?? '';
    $ogTitle = $ogSettings['title'] ?? $pageTitle;
    $ogDesc = $ogSettings['description'] ?? $pageDesc;
    $ogType = $ogSettings['type'] ?? 'website';
    $ogSiteName = $ogSettings['site_name'] ?? '1INME';
    $twCard = $twSettings['card'] ?? 'summary_large_image';
    $twSite = $twSettings['site'] ?? '';
    $twTitle = $twSettings['title'] ?? $ogTitle;
    $twDesc = $twSettings['description'] ?? $ogDesc;
    $metaLang = $metaSettings['language'] ?? 'en';
    $__ccViewerId = \App\Modules\Common\Services\ViewerSession::id() ?: optional(request()->user())->id;
    $__ccIsOwner  = $__ccViewerId && (int) $__ccViewerId === (int) ($link->user_id ?? 0);
@endphp
<!DOCTYPE html>
<html lang="{{ $metaLang }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle }}</title>
    @if($pageDesc)
        <meta name="description" content="{{ $pageDesc }}">
    @endif
    @if(!empty($metaSettings['keywords']))
        <meta name="keywords" content="{{ $metaSettings['keywords'] }}">
    @endif
    @if(!empty($metaSettings['author']))
        <meta name="author" content="{{ $metaSettings['author'] }}">
    @endif
    <meta name="robots" content="{{ $metaSettings['robots'] ?? 'index,follow' }}">
    @if(!empty($metaSettings['rating']))
        <meta name="rating" content="{{ $metaSettings['rating'] }}">
    @endif
    @if(!empty($metaSettings['canonical_url']))
        <link rel="canonical" href="{{ $metaSettings['canonical_url'] }}">
    @endif

    <meta property="og:title" content="{{ $ogTitle }}">
    @if($ogDesc)
        <meta property="og:description" content="{{ $ogDesc }}">
    @endif
    <meta property="og:type" content="{{ $ogType }}">
    <meta property="og:site_name" content="{{ $ogSiteName }}">
    @if($pageImage)
        <meta property="og:image" content="{{ $pageImage }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
    @endif
    <meta property="og:url" content="{{ request()->url() }}">

    <meta name="twitter:card" content="{{ $twCard }}">
    @if($twSite)
        <meta name="twitter:site" content="{{ $twSite }}">
    @endif
    <meta name="twitter:title" content="{{ $twTitle }}">
    @if($twDesc)
        <meta name="twitter:description" content="{{ $twDesc }}">
    @endif
    @if($pageImage)
        <meta name="twitter:image" content="{{ $pageImage }}">
    @endif

    @include('common.partials.default-icons')
    @if($link->favicon)
        <link rel="icon" type="image/png" href="{{ $link->favicon }}">
    @endif
    @if(!empty($faviconSettings['apple_touch_icon']))
        <link rel="apple-touch-icon" sizes="180x180" href="{{ $faviconSettings['apple_touch_icon'] }}">
    @endif
    @if(!empty($faviconSettings['icon_512']))
        <link rel="icon" type="image/png" sizes="512x512" href="{{ $faviconSettings['icon_512'] }}">
    @endif

    @if(!empty($manifestSettings['enabled']))
        <link rel="manifest" href="{{ url('/' . $link->alias . '/manifest.json') }}">
        <meta name="theme-color" content="{{ $manifestSettings['theme_color'] ?? '#7c3aed' }}">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        @if(!empty($manifestSettings['short_name']))
            <meta name="apple-mobile-web-app-title" content="{{ $manifestSettings['short_name'] }}">
        @endif
    @endif
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @php
        $bs = $link->settings['biolink'] ?? [];
        $fontFamily = $bs['font_family'] ?? 'Space Grotesk';
        $fontColor = $bs['font_color'] ?? '#ffffff';
        $bgType = $bs['background_type'] ?? 'gradient';
        $bgColor = $bs['background_color'] ?? '#0a0612';
        $bgGradient = $bs['background_gradient'] ?? 'linear-gradient(135deg, #0a0612 0%, #1a0533 50%, #0a0612 100%)';
        $bgImage = $bs['background_image'] ?? '';
        $bgAttachment = $bs['bg_attachment'] ?? 'fixed';
        $bgFallbackColor = $bs['bg_fallback_color'] ?? '#0a0612';
        $bgFallbackImage = $bs['bg_fallback_image'] ?? '';
        $bgBlur = (int)($bs['bg_blur'] ?? 0);
        $bgOverlayColor = $bs['bg_overlay_color'] ?? '#000000';
        $bgOverlayOpacity = (int)($bs['bg_overlay_opacity'] ?? 0);
        $slideshowImages = $bs['slideshow_images'] ?? [];
        $slideshowInterval = (int)($bs['slideshow_interval'] ?? 5);
        $videoUrl = $bs['video_url'] ?? '';
        $videoFileSrc = $bs['video_file'] ?? '';
        $bgTemplateId = $bs['bg_template_id'] ?? null;
        $bgTemplate = $bgTemplateId ? \App\Modules\Admin\Models\BgTemplate::find($bgTemplateId) : null;
        $btnColor = $bs['button_color'] ?? '#7c3aed';
        $btnTextColor = $bs['button_text_color'] ?? '#ffffff';
        $btnStyle = $bs['button_style'] ?? 'rounded';
        $btnRadius = match($btnStyle) {
            'pill' => '9999px',
            'square' => '4px',
            'rounded', 'outline', 'shadow' => '12px',
            default => '12px',
        };
        $socialIcons = [
            'instagram' => ['fab fa-instagram', '#E4405F'],
            'twitter' => ['fab fa-x-twitter', '#ffffff'],
            'facebook' => ['fab fa-facebook-f', '#1877F2'],
            'tiktok' => ['fab fa-tiktok', '#ffffff'],
            'youtube' => ['fab fa-youtube', '#FF0000'],
            'linkedin' => ['fab fa-linkedin-in', '#0A66C2'],
            'github' => ['fab fa-github', '#ffffff'],
            'discord' => ['fab fa-discord', '#5865F2'],
            'telegram' => ['fab fa-telegram', '#26A5E4'],
            'whatsapp' => ['fab fa-whatsapp', '#25D366'],
            'snapchat' => ['fab fa-snapchat', '#FFFC00'],
            'pinterest' => ['fab fa-pinterest', '#BD081C'],
            'twitch' => ['fab fa-twitch', '#9146FF'],
            'dribbble' => ['fab fa-dribbble', '#EA4C89'],
            'website' => ['fas fa-globe', '#7c3aed'],
            'email' => ['fas fa-envelope', '#7c3aed'],
            'spotify' => ['fab fa-spotify', '#1DB954'],
            'soundcloud' => ['fab fa-soundcloud', '#FF5500'],
            'apple' => ['fab fa-apple', '#ffffff'],
            'reddit' => ['fab fa-reddit', '#FF4500'],
            'medium' => ['fab fa-medium', '#ffffff'],
            'behance' => ['fab fa-behance', '#1769FF'],
        ];
    @endphp
    @php
        // Collect every font referenced by this biolink: page font, block-
        // theme font, and any per-block font_family overrides. Then load
        // each one exactly once. Custom fonts ("custom:Family") get a
        // server-rendered @font-face below pointing at the user's upload;
        // every other family is looked up in FontCatalog so we only request
        // weights the family actually ships.
        $allFonts = [(string) $fontFamily];
        $allFonts[] = (string) ($bs['block_theme']['font_family'] ?? '');
        foreach (($link->biolinkBlocks ?? collect()) as $bb) {
            $st = $bb->settings['style'] ?? [];
            if (!empty($st['font_family'])) $allFonts[] = (string) $st['font_family'];
            foreach (($bb->children ?? []) as $cc) {
                $cs = $cc->settings['style'] ?? [];
                if (!empty($cs['font_family'])) $allFonts[] = (string) $cs['font_family'];
            }
        }
        $allFonts = array_values(array_unique(array_filter($allFonts)));
        // Split into Google vs custom. Custom tokens are "custom:<family>".
        $googleFonts = [];
        $customFamilies = [];
        foreach ($allFonts as $f) {
            if (str_starts_with($f, 'custom:')) {
                $customFamilies[] = substr($f, 7);
            } else {
                $googleFonts[] = $f;
            }
        }
        // Resolve user-uploaded font records for the custom families on this
        // page. We look them up via the link owner since custom fonts are
        // user-scoped, not link-scoped.
        $customFontRecords = collect();
        if (!empty($customFamilies) && $link->user) {
            $customFontRecords = $link->user->customFonts()
                ->whereIn('family', $customFamilies)->get();
        }
    @endphp
    @foreach($googleFonts as $gf)
        @php $href = \App\Modules\User\Support\FontCatalog::googleHref($gf); @endphp
        @if($href)
            <link href="{{ $href }}" rel="stylesheet">
        @else
            {{-- Unknown family (legacy data) — fall back to a broad weight
                 request so older saved values still render. --}}
            <link href="https://fonts.googleapis.com/css2?family={{ str_replace('%20', '+', rawurlencode($gf)) }}:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        @endif
    @endforeach
    @if($customFontRecords->isNotEmpty())
    <style>
        @foreach($customFontRecords as $cf)
        @font-face {
            font-family: '{{ addslashes($cf->family) }}';
            src: url('{{ $cf->url }}') format('{{ $cf->format }}');
            font-display: swap;
        }
        @endforeach
    </style>
    @endif
    @if(!empty($bs['custom_js_head']))
    <script>{!! $bs['custom_js_head'] !!}</script>
    @endif
    <style>
        html {
            scroll-behavior: smooth;
        }
        * {
            scrollbar-width: thin;
            scrollbar-color: {{ $fontColor }}18 transparent;
        }
        ::-webkit-scrollbar { width: 4px; height: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: {{ $fontColor }}18; border-radius: 100px; }
        ::-webkit-scrollbar-thumb:hover { background: {{ $fontColor }}30; }
        ::-webkit-scrollbar-corner { background: transparent; }

        body {
            {{-- Custom-uploaded fonts come through as "custom:Family" tokens; strip the prefix before emitting. --}}
            font-family: '{{ str_starts_with((string) $fontFamily, 'custom:') ? substr($fontFamily, 7) : $fontFamily }}', sans-serif;
            color: {{ $fontColor }};
            background-color: {{ $bgFallbackColor }};
            @if($bgType === 'color')
                background-color: {{ $bgColor }};
            @elseif($bgType === 'gradient')
                background: {{ $bgGradient }};
            @elseif($bgType === 'image' && $bgImage)
                background: {{ $bgFallbackColor }} url('{{ $bgImage }}') center/cover no-repeat {{ $bgAttachment }};
            @elseif($bgType === 'slideshow' || $bgType === 'video' || $bgType === 'template')
                background-color: {{ $bgFallbackColor }};
                @if($bgFallbackImage)
                    background-image: url('{{ $bgFallbackImage }}');
                    background-size: cover;
                    background-position: center;
                @endif
            @endif
            min-height: 100vh;
            position: relative;
        }
        @if($bgBlur > 0 || $bgOverlayOpacity > 0)
        body::after {
            content: '';
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            @if($bgBlur > 0)
                backdrop-filter: blur({{ $bgBlur }}px);
                -webkit-backdrop-filter: blur({{ $bgBlur }}px);
            @endif
            @if($bgOverlayOpacity > 0)
                @php
                    $r = hexdec(substr($bgOverlayColor, 1, 2));
                    $g = hexdec(substr($bgOverlayColor, 3, 2));
                    $b = hexdec(substr($bgOverlayColor, 5, 2));
                @endphp
                background: rgba({{ $r }},{{ $g }},{{ $b }},{{ $bgOverlayOpacity / 100 }});
            @endif
        }
        body > *:not(.bg-layer):not(script):not(style) {
            position: relative;
            z-index: 1;
        }
        @endif
        @if($bgType === 'slideshow' && count($slideshowImages) > 0)
        .bg-slideshow { position:fixed; inset:0; z-index:0; }
        .bg-slideshow img {
            position:absolute; inset:0; width:100%; height:100%; object-fit:cover;
            opacity:0; transition:opacity 1.5s ease-in-out;
            @if($bgAttachment === 'fixed') position:fixed; @endif
        }
        .bg-slideshow img.active { opacity:1; }
        @endif
        @if($bgType === 'video')
        .bg-video-wrap { position:fixed; inset:0; z-index:0; overflow:hidden; }
        .bg-video-wrap video {
            min-width:100%; min-height:100%; width:auto; height:auto;
            position:absolute; top:50%; left:50%;
            transform:translate(-50%,-50%);
            object-fit:cover;
        }
        @endif
        @if($bgTemplate)
        {!! $bgTemplate->css !!}
        @endif
        .bio-btn {
            background: {{ $btnColor }};
            color: {{ $btnTextColor }};
            border-radius: {{ $btnRadius }};
            @if($btnStyle === 'outline')
                background: transparent;
                border: 2px solid {{ $btnColor }};
                color: {{ $btnColor }};
            @elseif($btnStyle === 'shadow')
                box-shadow: 0 8px 25px {{ $btnColor }}40;
            @endif
        }
        .bio-btn:hover {
            transform: translateY(-2px);
            @if($btnStyle === 'outline')
                background: {{ $btnColor }};
                color: {{ $btnTextColor }};
            @else
                filter: brightness(1.1);
                box-shadow: 0 8px 30px {{ $btnColor }}50;
            @endif
        }
        .glass-block {
            background: rgba(255,255,255,0.04);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.08);
        }
        @php
            $mbEnabled = !empty($menuBarSettings['enabled']);
            $mbPos = $menuBarSettings['position'] ?? 'top';
            $mbIsFloating = str_starts_with($mbPos, 'floating');
            $mbBg = $menuBarSettings['bg_color'] ?? '#0a0612';
            $mbText = $menuBarSettings['text_color'] ?? '#ffffff';
            $mbActive = $menuBarSettings['active_color'] ?? '#7c3aed';
            $mbStyle = $menuBarSettings['style'] ?? 'pills';
            $mbIconColor = $menuBarSettings['icon_color'] ?? '#ffffff';
            $mbOverlayBg = $menuBarSettings['overlay_bg'] ?? '#0a0612';
            $sbEnabled = !empty($shareBtnSettings['enabled']);
            $sbColor = $shareBtnSettings['color'] ?? '#7c3aed';
            $sbTextColor = $shareBtnSettings['text_color'] ?? '#ffffff';
            $sbPos = $shareBtnSettings['position'] ?? 'bottom-right';
            $sbSize = $shareBtnSettings['size'] ?? 'md';
            $sbBtnDim = match($sbSize) { 'sm' => '40px', 'lg' => '60px', default => '50px' };
            $sbIconSize = match($sbSize) { 'sm' => '14px', 'lg' => '22px', default => '18px' };
            $atEnabled = !empty($autoTranslateSettings['enabled']);
            $atPos = $autoTranslateSettings['position'] ?? 'top-right';
            $atBg = $autoTranslateSettings['bg_color'] ?? '#1a1a2e';
            $atText = $autoTranslateSettings['text_color'] ?? '#ffffff';
        @endphp
        @if($mbEnabled && !$mbIsFloating)
        .biolink-menu-bar {
            position: sticky;
            {{ $mbPos === 'bottom' ? 'bottom: 0' : 'top: 0' }};
            z-index: 50;
            background: {{ $mbBg }}ee;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-{{ $mbPos === 'bottom' ? 'top' : 'bottom' }}: 1px solid rgba(255,255,255,0.06);
            padding: 8px 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            flex-wrap: wrap;
            width: 100%;
        }
        .biolink-menu-bar a {
            color: {{ $mbText }};
            font-size: 12px;
            font-weight: 500;
            padding: 6px 14px;
            border-radius: {{ $mbStyle === 'pills' ? '9999px' : '6px' }};
            text-decoration: none;
            transition: all 0.2s;
            white-space: nowrap;
            @if($mbStyle === 'underline')
                border-radius: 0;
                border-bottom: 2px solid transparent;
                padding: 6px 10px;
            @endif
        }
        .biolink-menu-bar a:hover, .biolink-menu-bar a.active {
            @if($mbStyle === 'pills')
                background: {{ $mbActive }};
                color: #fff;
            @elseif($mbStyle === 'underline')
                border-bottom-color: {{ $mbActive }};
                color: {{ $mbActive }};
            @else
                color: {{ $mbActive }};
            @endif
        }
        @endif
        @if($mbEnabled && $mbIsFloating)
        .menu-fab {
            position: fixed;
            z-index: 100;
            @if(str_contains($mbPos, 'bottom')) bottom: 24px; @else top: 24px; @endif
            @if(str_contains($mbPos, 'right')) right: 24px; @else left: 24px; @endif
        }
        .menu-fab-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: {{ $mbBg }};
            color: {{ $mbIconColor }};
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            box-shadow: 0 8px 30px {{ $mbBg }}40;
            transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
            position: relative;
            z-index: 101;
        }
        .menu-fab-btn:hover {
            transform: scale(1.08);
            box-shadow: 0 12px 40px {{ $mbBg }}60;
        }
        .menu-fab-btn .bar1, .menu-fab-btn .bar2, .menu-fab-btn .bar3 {
            display: block;
            width: 18px;
            height: 2px;
            background: {{ $mbIconColor }};
            border-radius: 2px;
            transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
            position: absolute;
        }
        .menu-fab-btn .bar1 { top: calc(50% - 6px); }
        .menu-fab-btn .bar2 { top: calc(50% - 1px); }
        .menu-fab-btn .bar3 { top: calc(50% + 4px); }
        .menu-fab-btn.open .bar1 { transform: rotate(45deg); top: calc(50% - 1px); }
        .menu-fab-btn.open .bar2 { opacity: 0; transform: scaleX(0); }
        .menu-fab-btn.open .bar3 { transform: rotate(-45deg); top: calc(50% - 1px); }
        .menu-overlay-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 99;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }
        .menu-overlay-backdrop.open {
            opacity: 1;
            pointer-events: auto;
        }
        .menu-overlay-panel {
            position: fixed;
            z-index: 100;
            @if(str_contains($mbPos, 'bottom')) bottom: 86px; @else top: 86px; @endif
            @if(str_contains($mbPos, 'right')) right: 24px; @else left: 24px; @endif
            background: {{ $mbOverlayBg }}f2;
            backdrop-filter: blur(24px) saturate(1.4);
            -webkit-backdrop-filter: blur(24px) saturate(1.4);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 8px;
            min-width: 200px;
            max-width: 280px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5), 0 0 40px {{ $mbActive }}15;
            opacity: 0;
            transform: scale(0.9) translateY({{ str_contains($mbPos, 'bottom') ? '10px' : '-10px' }});
            pointer-events: none;
            transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
        }
        .menu-overlay-panel.open {
            opacity: 1;
            transform: scale(1) translateY(0);
            pointer-events: auto;
        }
        .menu-overlay-panel a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            color: {{ $mbText }};
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.2s;
        }
        .menu-overlay-panel a:hover {
            background: {{ $mbActive }}18;
            color: {{ $mbActive }};
        }
        .menu-overlay-panel a.active {
            background: {{ $mbActive }}20;
            color: {{ $mbActive }};
        }
        .menu-overlay-panel a .menu-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: {{ $mbActive }}40;
            flex-shrink: 0;
        }
        .menu-overlay-panel a.active .menu-dot,
        .menu-overlay-panel a:hover .menu-dot {
            background: {{ $mbActive }};
            box-shadow: 0 0 8px {{ $mbActive }}60;
        }
        @endif
        @if($sbEnabled)
        .share-fab {
            position: fixed;
            z-index: 100;
            @if(str_contains($sbPos, 'bottom')) bottom: 24px; @else top: 24px; @endif
            @if(str_contains($sbPos, 'right')) right: 24px; @elseif(str_contains($sbPos, 'left')) left: 24px; @else left: 50%; transform: translateX(-50%); @endif
        }
        .share-fab-btn {
            width: {{ $sbBtnDim }};
            height: {{ $sbBtnDim }};
            border-radius: 50%;
            background: {{ $sbColor }};
            color: {{ $sbTextColor }};
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: {{ $sbIconSize }};
            box-shadow: 0 8px 30px {{ $sbColor }}40;
            transition: all 0.3s;
        }
        .share-fab-btn:hover {
            transform: scale(1.08);
            box-shadow: 0 12px 40px {{ $sbColor }}60;
        }
        .share-popup {
            position: absolute;
            @if(str_contains($sbPos, 'bottom')) bottom: calc({{ $sbBtnDim }} + 12px); @else top: calc({{ $sbBtnDim }} + 12px); @endif
            @if(str_contains($sbPos, 'right')) right: 0; @elseif(str_contains($sbPos, 'left')) left: 0; @else left: 50%; transform: translateX(-50%); @endif
            background: {{ $sbColor }}15;
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 20px;
            min-width: 240px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
            display: none;
        }
        .share-popup.open { display: block; animation: fadeUp 0.25s ease; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
        .share-popup-actions { display: flex; gap: 10px; justify-content: center; margin-top: 14px; }
        .share-popup-actions a {
            width: 40px; height: 40px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            background: rgba(255,255,255,0.08); color: #fff;
            transition: all 0.2s; font-size: 16px; text-decoration: none;
        }
        .share-popup-actions a:hover { background: {{ $sbColor }}; transform: translateY(-2px); }
        @endif
        @if($atEnabled)
        .translate-widget {
            position: fixed;
            z-index: 90;
            @if(str_contains($atPos, 'top')) top: {{ $mbEnabled && !$mbIsFloating && $mbPos === 'top' ? '56px' : '16px' }}; @else bottom: {{ $mbEnabled && !$mbIsFloating && $mbPos === 'bottom' ? '56px' : '16px' }}; @endif
            @if(str_contains($atPos, 'right')) right: 16px; @else left: 16px; @endif
        }
        .translate-toggle {
            background: {{ $atBg }}cc;
            backdrop-filter: blur(20px) saturate(1.3);
            -webkit-backdrop-filter: blur(20px) saturate(1.3);
            color: {{ $atText }};
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 8px 14px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            letter-spacing: 0.01em;
        }
        .translate-toggle:hover {
            border-color: rgba(255,255,255,0.2);
            transform: translateY(-1px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.3);
        }
        .translate-toggle .globe-icon {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            flex-shrink: 0;
        }
        .translate-toggle .chevron {
            font-size: 8px;
            opacity: 0.5;
            transition: transform 0.3s;
        }
        .translate-widget.open .translate-toggle .chevron {
            transform: rotate(180deg);
        }
        .translate-dropdown {
            position: absolute;
            @if(str_contains($atPos, 'top')) top: 100%; margin-top: 8px; @else bottom: 100%; margin-bottom: 8px; @endif
            @if(str_contains($atPos, 'right')) right: 0; @else left: 0; @endif
            background: {{ $atBg }}f5;
            backdrop-filter: blur(24px) saturate(1.4);
            -webkit-backdrop-filter: blur(24px) saturate(1.4);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 14px;
            padding: 6px;
            min-width: 180px;
            max-height: 320px;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5), 0 0 0 1px rgba(255,255,255,0.03);
            opacity: 0;
            transform: scale(0.95) translateY({{ str_contains($atPos, 'top') ? '-4px' : '4px' }});
            pointer-events: none;
            transition: all 0.25s cubic-bezier(0.4,0,0.2,1);
        }
        .translate-dropdown.open {
            opacity: 1;
            transform: scale(1) translateY(0);
            pointer-events: auto;
        }
        .translate-dropdown a {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            color: {{ $atText }};
            font-size: 12px;
            font-weight: 400;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.15s;
        }
        .translate-dropdown a:hover {
            background: rgba(255,255,255,0.08);
        }
        .translate-dropdown a.active {
            background: rgba(255,255,255,0.1);
            font-weight: 600;
        }
        .translate-dropdown a .lang-flag {
            font-size: 16px;
            line-height: 1;
        }
        .translate-dropdown::-webkit-scrollbar { width: 3px; }
        .translate-dropdown::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
        .translate-dropdown::-webkit-scrollbar-track { background: transparent; }
        .translate-dropdown a.active { background: rgba(124,58,237,0.2); font-weight: 600; }
        @endif
        .ticker-scroll { animation: ticker 20s linear infinite; }
        @keyframes ticker { 0% { transform: translateX(100%); } 100% { transform: translateX(-100%); } }
        @keyframes morphText { 0%,100% { filter: blur(0px); } 50% { filter: blur(3px); } }
        .morph-text { animation: morphText 4s ease-in-out infinite; }
        @php
            $layout = $bs['layout'] ?? [];
            $maxPhone = $layout['max_width_phone'] ?? 448;
            $maxTablet = $layout['max_width_tablet'] ?? 540;
            $maxDesktop = $layout['max_width_desktop'] ?? 680;
            $pagePadTop = $layout['page_padding_top'] ?? 32;
            $pagePadBottom = $layout['page_padding_bottom'] ?? 64;
            $pagePadX = $layout['page_padding_x'] ?? 16;
            $blockGap = $layout['block_gap'] ?? 12;
            $defaultBlockPadding = $layout['block_padding'] ?? '';
        @endphp
        .biolink-container {
            width: 100%;
            max-width: {{ $maxPhone }}px;
            margin: 0 auto;
            padding: {{ $pagePadTop }}px {{ $pagePadX }}px {{ $pagePadBottom }}px;
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: {{ $blockGap }}px;
            align-items: start;
        }
        @media (min-width: 768px) {
            .biolink-container { max-width: {{ $maxTablet }}px; }
        }
        @media (min-width: 1024px) {
            .biolink-container { max-width: {{ $maxDesktop }}px; }
        }
        .biolink-block-wrap {
            grid-column: span 12;
            min-width: 0;
        }
        @if($defaultBlockPadding)
        .biolink-block-wrap > :first-child {
            padding: {{ $defaultBlockPadding }}px;
        }
        @endif
    </style>
    @if(!empty($bs['custom_css']))
    <style>{!! $bs['custom_css'] !!}</style>
    @endif
</head>
<body class="flex flex-col items-center {{ $mbEnabled && $mbPos === 'bottom' ? 'min-h-screen' : '' }}">
    @if($mbEnabled && !$mbIsFloating && $mbPos === 'top')
    @php $mbItems = $menuBarSettings['items'] ?? []; @endphp
    <nav class="biolink-menu-bar" style="align-self: stretch;">
        @foreach($mbItems as $mi)
            @if(!empty($mi['label']) && ($mi['is_active'] ?? true))
                @if(($mi['target'] ?? '_self') === 'tab' && !empty($mi['id']))
                    <a href="#{{ $mi['id'] }}" data-biolink-tab="{{ $mi['id'] }}" onclick="return biolinkSwitchTab(event, '{{ $mi['id'] }}')">{{ $mi['label'] }}</a>
                @elseif(!empty($mi['url']))
                    <a href="{{ $mi['url'] }}" target="{{ $mi['target'] ?? '_self' }}" @if(($mi['target'] ?? '_self') === '_blank') rel="noopener" @endif
                       class="{{ request()->url() === url($mi['url']) ? 'active' : '' }}">{{ $mi['label'] }}</a>
                @endif
            @endif
        @endforeach
    </nav>
    @endif

    @if($bgType === 'slideshow' && count($slideshowImages) > 0)
    <div class="bg-slideshow bg-layer">
        @foreach($slideshowImages as $si => $sImg)
        <img src="{{ $sImg }}" alt="" loading="eager" class="{{ $si === 0 ? 'active' : '' }}">
        @endforeach
    </div>
    @endif

    @if($bgType === 'video')
    <div class="bg-video-wrap bg-layer">
        <video autoplay muted loop playsinline @if($bgFallbackImage) poster="{{ $bgFallbackImage }}" @endif>
            @if($videoFileSrc)
            <source src="{{ $videoFileSrc }}" type="{{ str_ends_with(strtolower($videoFileSrc), '.webm') ? 'video/webm' : 'video/mp4' }}">
            @endif
            @if($videoUrl)
            <source src="{{ $videoUrl }}" type="{{ str_ends_with(strtolower($videoUrl), '.webm') ? 'video/webm' : 'video/mp4' }}">
            @endif
        </video>
    </div>
    @endif

    @if($bgType === 'template' && $bgTemplate)
    <div class="bg-template bg-layer bg-template-{{ $bgTemplate->slug }}" style="position:fixed;inset:0;z-index:0;overflow:hidden;"></div>
    @endif

    <div class="biolink-container">
        @php
            $blocks = $link->activeBiolinkBlocks()->get()->filter(fn($b) => $b->isVisible());
            $pageTitle = $bs['biolink_title'] ?? $link->title ?: 'Link in Bio';
            $pageDescription = $bs['biolink_description'] ?? $link->seo_description ?? '';
            $globalTheme = $bs['block_theme'] ?? [];

            // Lazily publish any due scheduled posts for this creator before
            // we surface their pinned post on the biolink.
            $creatorOwner = $link->user ?? null;
            if ($creatorOwner) {
                \App\Modules\User\Models\CreatorPost::publishDuePosts($creatorOwner->id);
            }
            $pinnedPost = $creatorOwner
                ? \App\Modules\User\Models\CreatorPost::where('user_id', $creatorOwner->id)
                    ->whereNotNull('pinned_at')
                    ->whereNotNull('published_at')
                    ->orderByDesc('pinned_at')
                    ->first()
                : null;
        @endphp

        @if($pinnedPost)
            <div class="biolink-block-wrap" style="grid-column: span 12">
                <div class="mb-3 rounded-2xl p-4" style="background: rgba(255,255,255,0.08); border:1px solid rgba(255,191,80,0.5); color:{{ $fontColor }};">
                    <div class="flex items-center gap-2 mb-2 text-[11px] font-bold uppercase tracking-wider" style="color: rgba(255,191,80,0.95);">
                        <i class="fas fa-thumbtack"></i> Pinned post
                    </div>
                    @if($pinnedPost->title)
                        <h3 class="font-bold text-base mb-1" style="color:{{ $fontColor }};">{{ $pinnedPost->title }}</h3>
                    @endif
                    <p class="text-sm whitespace-pre-line" style="color:{{ $fontColor }}cc;">{{ \Illuminate\Support\Str::limit($pinnedPost->body, 320) }}</p>
                    @if($pinnedPost->image)
                        <img src="{{ $pinnedPost->image }}" class="mt-3 rounded-lg max-h-72 w-full object-cover" alt=""/>
                    @endif
                    <p class="text-[11px] mt-2" style="color:{{ $fontColor }}77;">{{ $pinnedPost->published_at?->diffForHumans() }}</p>
                </div>
            </div>
        @endif

        @php
            // Bio-link header CTA: surface a small "View resume" chip when
            // the page owner has published their /{handle}/resume page.
            // Lives just above the block stream so it sits in the
            // page-header area without competing with custom blocks.
            $__resumeOwner = $link->user ?? null;
            $__resumePublished = $__resumeOwner
                ? optional($__resumeOwner->resume)->is_public
                : false;
        @endphp
        @if ($__resumePublished)
            <div class="biolink-block-wrap" style="grid-column: span 12">
                <div class="mb-3 text-center">
                    <a href="{{ url('/' . $__resumeOwner->publicHandle() . '/resume') }}"
                       class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-xs font-semibold transition-all hover:scale-105"
                       style="background: {{ $fontColor }}12; color: {{ $fontColor }}; border: 1px solid {{ $fontColor }}30;">
                        <i class="fas fa-file-lines"></i>
                        <span>View résumé</span>
                    </a>
                </div>
            </div>
        @endif

        @forelse($blocks as $block)
            @php
                $s = $block->settings ?? [];
                $blockStyle = \App\Modules\User\Models\BiolinkBlock::getBlockStyle($s, $globalTheme);
                $blockInline = \App\Modules\User\Models\BiolinkBlock::buildInlineStyle($blockStyle);
                $hasCustomStyle = !empty($s['_style']) || (!empty($globalTheme) && ($globalTheme['apply_to_all'] ?? false));
                $skipWrap = in_array($block->type, ['avatar', 'divider', 'spacer', 'social_icons']);
            @endphp

            @php $gridSpan = intval($blockStyle['grid_span'] ?? 12) ?: 12; @endphp
            <div data-block-id="{{ $block->id }}" data-block-type="{{ $block->type }}" data-tab="{{ $s['_tab_id'] ?? '' }}" class="biolink-block-wrap" style="grid-column: span {{ $gridSpan }}">
            @if($hasCustomStyle && !$skipWrap)<div class="mb-3 block-styled" style="{{ $blockInline }}">@endif

            {{-- BASIC CONTENT --}}
            @if($block->type === 'avatar')
                <div class="flex justify-center mb-4">
                    @if(!empty($s['url']))
                        <img src="{{ $s['url'] }}" alt="Avatar"
                             class="{{ ($s['rounded'] ?? true) ? 'rounded-full' : 'rounded-2xl' }} object-cover border-2 border-white/10"
                             style="width: {{ $s['size'] ?? 96 }}px; height: {{ $s['size'] ?? 96 }}px;">
                    @else
                        <div class="rounded-full bg-white/10 backdrop-blur flex items-center justify-center border-2 border-white/10"
                             style="width: {{ $s['size'] ?? 96 }}px; height: {{ $s['size'] ?? 96 }}px;">
                            <span class="text-3xl font-bold">{{ strtoupper(substr($link->title ?: 'B', 0, 1)) }}</span>
                        </div>
                    @endif
                </div>

            @elseif($block->type === 'resume')
                @php
                    $__rOwner = $link->user ?? null;
                    $__rResume = $__rOwner ? $__rOwner->resume : null;
                    $__rUrl = $__rOwner ? url('/' . $__rOwner->publicHandle() . '/resume') : null;
                    $__rDisplay = $s['display'] ?? 'card'; // card | inline
                    $__rTitle = $s['title'] ?? 'My résumé';
                    $__rCta   = $s['cta_label'] ?? 'View full résumé';
                    $__rDesc  = $s['description'] ?? null;
                    if ($__rResume && empty($__rDesc)) {
                        $__rh = $__rResume->getMergedSections()['header'] ?? [];
                        $__rDesc = trim($__rh['headline'] ?? '');
                    }
                @endphp
                @if (!$__rResume || !$__rResume->is_public || !$__rUrl)
                    {{-- Owner hasn't published yet — render nothing on the public page. --}}
                @elseif ($__rDisplay === 'inline')
                    <div class="rounded-2xl overflow-hidden mb-3" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08);">
                        <div class="px-3 py-2 flex items-center justify-between text-xs" style="color: {{ $fontColor }}cc;">
                            <span class="inline-flex items-center gap-2 font-semibold"><i class="fas fa-file-lines"></i> {{ $__rTitle }}</span>
                            <a href="{{ $__rUrl }}" class="font-bold underline-offset-2 hover:underline" style="color: {{ $fontColor }};">Open <i class="fas fa-external-link-alt text-[10px]"></i></a>
                        </div>
                        <div style="background:#fff; color:#111;">
                            @include('common.partials.resume-render', ['resume' => $__rResume, 'compact' => true])
                        </div>
                    </div>
                @else
                    <a href="{{ $__rUrl }}" class="block mb-3 rounded-2xl p-4 transition-all hover:scale-[1.01]"
                       style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12); color: {{ $fontColor }};">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                                 style="background: rgba(124,58,237,0.18); color:#c4b5fd;">
                                <i class="fas fa-file-lines"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-bold truncate">{{ $__rTitle }}</div>
                                @if (!empty($__rDesc))
                                    <div class="text-xs opacity-75 truncate">{{ $__rDesc }}</div>
                                @endif
                            </div>
                            <span class="text-xs font-semibold inline-flex items-center gap-1 shrink-0">
                                {{ $__rCta }} <i class="fas fa-arrow-right text-[10px]"></i>
                            </span>
                        </div>
                    </a>
                @endif

            @elseif($block->type === 'verified_heading')
                @php $vhSize = ($s['font_size'] ?? '24') . 'px'; @endphp
                <div class="mb-3 text-{{ $s['alignment'] ?? 'center' }}">
                    <h2 class="font-bold inline-flex items-center gap-2" style="font-size: {{ $vhSize }};">
                        {{ $s['text'] ?? '' }}
                        <svg class="inline-block shrink-0" width="22" height="22" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="12" fill="#1d9bf0"/><path d="M9.5 12.5l2 2 4-4" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </h2>
                </div>

            @elseif($block->type === 'verified_avatar')
                @php $vaSize = ($s['size'] ?? '100') . 'px'; $vaShape = ($s['shape'] ?? 'circle') === 'circle' ? '50%' : '12px'; @endphp
                <div class="mb-4 flex justify-center">
                    <div class="relative inline-block">
                        @if(!empty($s['image_url']))
                        <img src="{{ $s['image_url'] }}" alt="" class="object-cover" style="width: {{ $vaSize }}; height: {{ $vaSize }}; border-radius: {{ $vaShape }}; border: 3px solid rgba(255,255,255,0.2);">
                        @else
                        <div class="flex items-center justify-center" style="width: {{ $vaSize }}; height: {{ $vaSize }}; border-radius: {{ $vaShape }}; background: rgba(124,58,237,0.2); border: 3px solid rgba(255,255,255,0.2);"><i class="fas fa-user text-2xl" style="color: rgba(255,255,255,0.5);"></i></div>
                        @endif
                        <div class="absolute -bottom-1 -right-1 w-7 h-7 rounded-full flex items-center justify-center" style="background: #1d9bf0; border: 2px solid var(--bg-color, #0a0612);">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M9 12.5l2.5 2.5 5-5" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                    </div>
                </div>

            @elseif($block->type === 'heading')
                @php
                    $headingStyle = $s['style'] ?? 'plain';
                    $hs = match($s['size'] ?? 'h2') { 'h1' => 'text-2xl md:text-3xl', 'h2' => 'text-xl md:text-2xl', 'h3' => 'text-lg md:text-xl', default => 'text-xl md:text-2xl' };
                @endphp
                <div class="mb-3 text-{{ $s['align'] ?? 'center' }}">
                    @if($headingStyle === 'gradient')
                        <h2 class="{{ $hs }} font-bold bg-clip-text text-transparent" style="background-image: linear-gradient(to right, {{ $s['from_color'] ?? '#7c3aed' }}, {{ $s['to_color'] ?? '#ec4899' }});">{{ $s['text'] ?? '' }}</h2>
                    @elseif($headingStyle === 'animated')
                        <h2 class="{{ $hs }} font-bold morph-text">{{ $s['text'] ?? '' }}</h2>
                    @else
                        <h2 class="{{ $hs }} font-bold">{{ $s['text'] ?? '' }}</h2>
                    @endif
                </div>

            @elseif($block->type === 'heading_logo')
                <div class="mb-3 text-{{ $s['align'] ?? 'center' }} flex items-center justify-{{ $s['align'] ?? 'center' }} gap-3">
                    @if(!empty($s['logo_url']))<img src="{{ $s['logo_url'] }}" alt="" class="h-8 w-8 object-contain">@endif
                    @php $hs = match($s['size'] ?? 'h2') { 'h1' => 'text-2xl md:text-3xl', 'h2' => 'text-xl md:text-2xl', 'h3' => 'text-lg md:text-xl', default => 'text-xl md:text-2xl' }; @endphp
                    <h2 class="{{ $hs }} font-bold">{{ $s['text'] ?? '' }}</h2>
                </div>

            @elseif($block->type === 'paragraph')
                <div class="mb-4 text-{{ $s['align'] ?? 'center' }}"><p class="text-sm leading-relaxed" style="color: {{ $fontColor }}cc">{{ $s['text'] ?? '' }}</p></div>

            @elseif($block->type === 'paragraph_rich')
                <div class="mb-4 prose prose-invert prose-sm max-w-none">{!! strip_tags($s['html'] ?? '', '<p><br><a><strong><em><u><ul><ol><li><h1><h2><h3><h4><h5><h6><span><div><img><blockquote><hr>') !!}</div>

            @elseif($block->type === 'link')
                <a href="{{ $s['url'] ?? '#' }}" target="_blank" rel="noopener"
                   class="bio-btn block w-full px-6 py-3.5 mb-3 text-center font-medium transition-all duration-300 flex items-center justify-center gap-3">
                    @if(!empty($s['thumbnail']))<img src="{{ $s['thumbnail'] }}" class="w-6 h-6 rounded object-cover" alt="">
                    @elseif(!empty($s['icon']))@php $_lnkIcon = $s['icon']; if(!preg_match('/^fa[sbrl] /', $_lnkIcon)) $_lnkIcon = 'fas ' . $_lnkIcon; @endphp<i class="{{ $_lnkIcon }}"></i>@endif
                    <span>{{ $s['text'] ?? 'Link' }}</span>
                </a>

            @elseif($block->type === 'link_big')
                <a href="{{ $s['url'] ?? '#' }}" target="_blank" rel="noopener"
                   class="block w-full mb-3 rounded-2xl overflow-hidden transition-all duration-300 hover:-translate-y-1 hover:shadow-lg"
                   style="background: {{ $s['bg_color'] ?? $btnColor }};">
                    <div class="px-6 py-5 flex items-center gap-4">
                        @if(!empty($s['thumbnail']))<img src="{{ $s['thumbnail'] }}" class="w-12 h-12 rounded-xl object-cover" alt="">
                        @elseif(!empty($s['icon']))@php $_lnkBigIcon = $s['icon']; if(!preg_match('/^fa[sbrl] /', $_lnkBigIcon)) $_lnkBigIcon = 'fas ' . $_lnkBigIcon; @endphp<div class="w-12 h-12 rounded-xl bg-white/10 flex items-center justify-center"><i class="{{ $_lnkBigIcon }} text-xl"></i></div>@endif
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-white truncate">{{ $s['text'] ?? 'Link' }}</p>
                            @if(!empty($s['description']))<p class="text-xs text-white/60 mt-0.5 truncate">{{ $s['description'] }}</p>@endif
                        </div>
                        <i class="fas fa-arrow-right text-white/40"></i>
                    </div>
                </a>

            @elseif($block->type === 'divider')
                <div class="my-4 px-4"><hr style="border-style: {{ $s['style'] ?? 'solid' }}; border-color: {{ $s['color'] ?? 'rgba(255,255,255,0.1)' }}; border-width: 1px 0 0 0;"></div>

            @elseif($block->type === 'spacer')
                <div style="height: {{ $s['height'] ?? 20 }}px"></div>

            @elseif(in_array($block->type, ['list', 'list_numbered']))
                <div class="mb-4 glass-block rounded-xl p-4">
                    @if($block->type === 'list')
                        @php $_listIcon = $s['icon'] ?? 'fa-check'; if(!preg_match('/^fa[sbrl] /', $_listIcon)) $_listIcon = 'fas ' . $_listIcon; @endphp
                        <ul class="space-y-2">@foreach(($s['items'] ?? []) as $item)<li class="flex items-start gap-2 text-sm"><i class="{{ $_listIcon }} text-purple-400 mt-0.5 text-xs"></i><span style="color:{{ $fontColor }}cc">{{ $item }}</span></li>@endforeach</ul>
                    @else
                        <ol class="space-y-2 list-decimal list-inside">@foreach(($s['items'] ?? []) as $item)<li class="text-sm" style="color:{{ $fontColor }}cc">{{ $item }}</li>@endforeach</ol>
                    @endif
                </div>

            @elseif($block->type === 'list_pricing')
                <div class="mb-4 glass-block rounded-xl p-4 space-y-2">
                    @foreach(($s['items'] ?? []) as $item)
                        <div class="flex items-center justify-between text-sm py-1">
                            <span class="flex items-center gap-2"><i class="fas {{ ($item['included'] ?? false) ? 'fa-check text-green-400' : 'fa-times text-red-400' }} text-xs"></i>{{ $item['name'] ?? '' }}</span>
                            <span class="font-medium">{{ $item['price'] ?? '' }}</span>
                        </div>
                    @endforeach
                </div>

            @elseif($block->type === 'alert')
                @php $alertColors = ['info' => 'border-violet-400/30 bg-violet-500/10', 'success' => 'border-green-400/30 bg-green-500/10', 'warning' => 'border-yellow-400/30 bg-yellow-500/10', 'error' => 'border-red-400/30 bg-red-500/10']; @endphp
                <div class="mb-4 rounded-xl p-4 border {{ $alertColors[$s['type'] ?? 'info'] ?? $alertColors['info'] }}">
                    @php $_alertIcon = $s['icon'] ?? 'fa-info-circle'; if(!preg_match('/^fa[sbrl] /', $_alertIcon)) $_alertIcon = 'fas ' . $_alertIcon; @endphp
                    <p class="text-sm flex items-center gap-2"><i class="{{ $_alertIcon }}"></i>{{ $s['text'] ?? '' }}</p>
                </div>

            @elseif($block->type === 'badge')
                <div class="mb-3 flex justify-center">
                    <span class="inline-block px-4 py-1.5 rounded-full text-xs font-semibold" style="background:{{ $s['color'] ?? '#7c3aed' }}; color:{{ $s['text_color'] ?? '#fff' }}">{{ $s['text'] ?? '' }}</span>
                </div>

            {{-- MEDIA --}}
            @elseif($block->type === 'image')
                @php
                    $imgSt = $s['_image_style'] ?? [];
                    $imgInline = \App\Modules\User\Models\BiolinkBlock::buildImageInlineStyle($imgSt);
                    $imgLk = $s['_link'] ?? [];
                    $imgLinkUrl = $imgLk['url'] ?? $s['link'] ?? '';
                    $imgTrackUrl = $imgLinkUrl ? route('redirect.block', ['alias' => $link->alias, 'blockId' => $block->id]) : '';
                    $imgTarget = $imgLk['target'] ?? '_blank';
                    $imgRel = $imgLk['rel'] ?? 'noopener';
                    $imgTitle = $imgLk['title'] ?? '';
                @endphp
                <div class="mb-4 overflow-hidden{{ empty($imgSt['mask_shape']) || ($imgSt['mask_shape'] ?? 'none') === 'none' ? ' rounded-xl' : '' }}">
                    @if($imgTrackUrl)<a href="{{ $imgTrackUrl }}" target="{{ $imgTarget }}" rel="{{ $imgRel }}"{{ $imgTitle ? ' title="'.e($imgTitle).'"' : '' }}>@endif
                    <img src="{{ $s['url'] ?? '' }}" alt="{{ $s['alt'] ?? '' }}" class="w-full{{ empty($imgInline) ? ' rounded-xl' : '' }}" style="{{ $imgInline }}">
                    @if($imgTrackUrl)</a>@endif
                </div>

            @elseif($block->type === 'image_grid')
                @php
                    $imgSt = $s['_image_style'] ?? [];
                    $imgInline = \App\Modules\User\Models\BiolinkBlock::buildImageInlineStyle($imgSt);
                    $imgLk = $s['_link'] ?? [];
                    $gridLinkUrl = $imgLk['url'] ?? '';
                    $gridTrackUrl = $gridLinkUrl ? route('redirect.block', ['alias' => $link->alias, 'blockId' => $block->id]) : '';
                    $gridTarget = $imgLk['target'] ?? '_blank';
                    $gridRel = $imgLk['rel'] ?? 'noopener';
                @endphp
                <div class="mb-4 grid grid-cols-{{ $s['columns'] ?? 3 }} gap-{{ $s['gap'] ?? 2 }}">
                    @if($gridTrackUrl)<a href="{{ $gridTrackUrl }}" target="{{ $gridTarget }}" rel="{{ $gridRel }}" class="contents">@endif
                    @foreach(($s['images'] ?? []) as $img)
                        <img src="{{ is_array($img) ? ($img['url'] ?? '') : $img }}" alt="" class="w-full aspect-square object-cover{{ empty($imgInline) ? ' rounded-lg' : '' }}" style="{{ $imgInline }}">
                    @endforeach
                    @if($gridTrackUrl)</a>@endif
                </div>

            @elseif(in_array($block->type, ['image_slider', 'image_slider_v2']))
                @php
                    $imgSt = $s['_image_style'] ?? [];
                    $imgInline = \App\Modules\User\Models\BiolinkBlock::buildImageInlineStyle($imgSt);
                    $sliderLk = $s['_link'] ?? [];
                    $sliderLinkUrl = $sliderLk['url'] ?? '';
                    $sliderTrackUrl = $sliderLinkUrl ? route('redirect.block', ['alias' => $link->alias, 'blockId' => $block->id]) : '';
                    $sliderTarget = $sliderLk['target'] ?? '_blank';
                    $sliderRel = $sliderLk['rel'] ?? 'noopener';
                @endphp
                <div class="mb-4 rounded-xl overflow-hidden relative" x-data="{ current: 0, images: {{ json_encode($s['images'] ?? []) }} }" x-init="setInterval(() => { if(images.length > 1) current = (current + 1) % images.length }, {{ $s['interval'] ?? 3000 }})">
                    @if($sliderTrackUrl)<a href="{{ $sliderTrackUrl }}" target="{{ $sliderTarget }}" rel="{{ $sliderRel }}">@endif
                    <template x-for="(img, i) in images" :key="i">
                        <img :src="typeof img === 'string' ? img : img.url" x-show="current === i" class="w-full{{ empty($imgInline) ? ' rounded-xl' : '' }} {{ ($s['effect'] ?? '') === 'fade' ? 'transition-opacity duration-500' : '' }}" style="{{ $imgInline }}" alt="">
                    </template>
                    @if($sliderTrackUrl)</a>@endif
                    <div class="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-1.5">
                        <template x-for="(_, i) in images" :key="'d'+i">
                            <button @click="current = i" class="w-2 h-2 rounded-full transition-all" :class="current === i ? 'bg-white w-4' : 'bg-white/40'"></button>
                        </template>
                    </div>
                </div>

            @elseif($block->type === 'header_video')
                <div class="mb-4 rounded-xl overflow-hidden">
                    <video class="w-full rounded-xl" {{ ($s['autoplay'] ?? true) ? 'autoplay' : '' }} {{ ($s['muted'] ?? true) ? 'muted' : '' }} {{ ($s['loop'] ?? true) ? 'loop' : '' }} playsinline>
                        <source src="{{ $s['url'] ?? '' }}" type="video/mp4">
                    </video>
                </div>

            @elseif($block->type === 'video')
                <div class="mb-4 rounded-xl overflow-hidden glass-block">
                    <video class="w-full rounded-xl" controls {{ ($s['autoplay'] ?? false) ? 'autoplay muted' : '' }}>
                        <source src="{{ $s['url'] ?? '' }}" type="video/mp4">
                    </video>
                </div>

            @elseif($block->type === 'audio')
                <div class="mb-3 glass-block rounded-xl p-4">
                    @if(!empty($s['title']))<p class="text-sm font-medium mb-2">{{ $s['title'] }}</p>@endif
                    <audio controls class="w-full" style="filter: invert(1) hue-rotate(180deg);"><source src="{{ $s['url'] ?? '' }}"></audio>
                </div>

            @elseif($block->type === 'pdf_document')
                <div class="mb-4 glass-block rounded-xl p-4">
                    <div class="flex items-center gap-3 mb-3"><i class="fas fa-file-pdf text-red-400 text-xl"></i><span class="font-medium text-sm">{{ $s['title'] ?? 'PDF Document' }}</span></div>
                    @if(!empty($s['url']))<iframe src="{{ $s['url'] }}" class="w-full h-64 rounded-lg border border-white/10"></iframe>@endif
                </div>

            @elseif(in_array($block->type, ['powerpoint', 'excel']))
                <div class="mb-4 glass-block rounded-xl p-4">
                    <div class="flex items-center gap-3"><i class="fas {{ $block->type === 'powerpoint' ? 'fa-file-powerpoint text-orange-400' : 'fa-file-excel text-green-400' }} text-xl"></i>
                        <div class="flex-1"><span class="font-medium text-sm">{{ $s['title'] ?? ($block->type === 'powerpoint' ? 'Presentation' : 'Spreadsheet') }}</span></div>
                        @if(!empty($s['url']))<a href="{{ $s['url'] }}" target="_blank" class="bio-btn px-4 py-2 text-xs font-medium">View</a>@endif
                    </div>
                </div>

            {{-- SOCIAL — Follow buttons (Task #48) --}}
            @elseif(in_array($block->type, ['socials', 'socials_multi', 'socials_custom']))
                @php
                    $sz = $s['size'] ?? 'md';
                    $szClass = match($sz) { 'sm' => 'w-9 h-9', 'lg' => 'w-14 h-14', default => 'w-11 h-11' };
                    $allPlatforms = $s['platforms'] ?? [];
                    if ($block->type === 'socials_multi' && isset($s['groups'])) {
                        $allPlatforms = [];
                        foreach ($s['groups'] as $group) {
                            $allPlatforms = array_merge($allPlatforms, $group['platforms'] ?? []);
                        }
                    }

                    $connIds = collect($allPlatforms)->pluck('connection_id')->filter()->unique()->values()->all();
                    $connMap = [];
                    if (! empty($connIds)) {
                        // Scope to the biolink owner so a crafted block can never
                        // surface another user's connection data.
                        $connMap = \App\Modules\User\Models\SocialAccountConnection::whereIn('id', $connIds)
                            ->where('user_id', $link->user_id)
                            ->get()->keyBy('id');
                        // Hand off to RedirectController for non-blocking refresh after response.
                        $existingRefs = app()->bound('biolink.referenced_social_connections')
                            ? (array) app('biolink.referenced_social_connections')
                            : [];
                        app()->instance('biolink.referenced_social_connections',
                            array_values(array_unique(array_merge($existingRefs, $connIds)))
                        );
                    }
                @endphp
                <div class="flex justify-center gap-2 mb-4 flex-wrap">
                    @foreach($allPlatforms as $platform)
                        @php
                            $name    = $platform['name'] ?? '';
                            $display = $platform['display'] ?? 'icon';
                            $iconDef = $socialIcons[$name] ?? ['fas fa-link', '#7c3aed'];

                            $conn = ! empty($platform['connection_id']) ? ($connMap[$platform['connection_id']] ?? null) : null;
                            $brandIcon  = $conn ? $conn->brandIcon()  : $iconDef[0];
                            $brandColor = $conn ? $conn->brandColor() : $iconDef[1];
                            $rawUrl     = $platform['url'] ?? '';
                            if ($conn && empty($rawUrl)) $rawUrl = $conn->resolvedProfileUrl();
                            if (! $rawUrl) $rawUrl = '#';

                            $href = $rawUrl !== '#'
                                ? route('redirect.block', ['alias' => $link->alias, 'blockId' => $block->id]) . '?to=' . urlencode($rawUrl)
                                : '#';

                            $count      = $conn ? $conn->follower_count : null;
                            $countLabel = \App\Modules\User\Models\SocialAccountConnection::formatCount($count);
                            $showCount  = $display === 'follow_count' && $countLabel !== null;

                            $platformLabel = $conn
                                ? \App\Modules\User\Models\SocialAccountConnection::platformLabel($conn->platform)
                                : ucfirst($name ?: 'Link');
                            $isYt = $name === 'youtube' || ($conn && $conn->platform === 'youtube');
                            $btnLabel = $isYt ? 'Subscribe' : 'Follow';
                        @endphp

                        @if($display === 'icon' || $display === '')
                            <a href="{{ $href }}" target="_blank" rel="noopener"
                               class="{{ $szClass }} {{ ($s['style'] ?? '') === 'square' ? 'rounded-lg' : 'rounded-full' }} glass-block flex items-center justify-center transition-all hover:scale-110 hover:-translate-y-1"
                               style="color: {{ $brandColor }}"
                               aria-label="{{ $platformLabel }}">
                                <i class="{{ $brandIcon }} {{ $sz === 'lg' ? 'text-xl' : 'text-lg' }}"></i>
                            </a>
                        @else
                            <a href="{{ $href }}" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-2 px-3 py-2 rounded-full text-xs font-semibold transition-all hover:-translate-y-0.5"
                               style="background: {{ $brandColor }}; color: #fff; box-shadow: 0 6px 18px {{ $brandColor }}55;"
                               aria-label="{{ $btnLabel }} on {{ $platformLabel }}">
                                <i class="{{ $brandIcon }}"></i>
                                <span>{{ $btnLabel }}</span>
                                @if($showCount)
                                    <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold"
                                          style="background: rgba(255,255,255,0.18); color: #fff;">{{ $countLabel }}</span>
                                @endif
                            </a>
                        @endif
                    @endforeach
                </div>

            @elseif($block->type === 'instagram_media')
                <div class="mb-4 rounded-xl overflow-hidden glass-block p-1">
                    <iframe src="{{ str_replace('/p/', '/p/', $s['url'] ?? '') }}embed" class="w-full" style="min-height:400px;border:none;" loading="lazy"></iframe>
                </div>

            @elseif($block->type === 'tiktok_video')
                <div class="mb-4 glass-block rounded-xl p-4 text-center">
                    <i class="fab fa-tiktok text-2xl mb-2"></i>
                    <a href="{{ $s['url'] ?? '#' }}" target="_blank" class="bio-btn inline-block px-5 py-2 text-sm font-medium mt-2">Watch on TikTok</a>
                </div>

            @elseif($block->type === 'tiktok_profile')
                <div class="mb-4 glass-block rounded-xl p-4 flex items-center gap-3">
                    <div class="w-12 h-12 rounded-full bg-white/10 flex items-center justify-center"><i class="fab fa-tiktok text-xl"></i></div>
                    <div class="flex-1"><p class="font-medium text-sm">{{ '@' . ($s['username'] ?? '') }}</p><p class="text-xs text-white/40">TikTok</p></div>
                    <a href="https://tiktok.com/@{{ $s['username'] ?? '' }}" target="_blank" class="bio-btn px-4 py-2 text-xs font-medium">Follow</a>
                </div>

            @elseif($block->type === 'twitter_profile')
                <div class="mb-4 glass-block rounded-xl p-4 flex items-center gap-3">
                    <div class="w-12 h-12 rounded-full bg-white/10 flex items-center justify-center"><i class="fab fa-x-twitter text-xl"></i></div>
                    <div class="flex-1"><p class="font-medium text-sm">{{ '@' . ($s['username'] ?? '') }}</p><p class="text-xs text-white/40">X (Twitter)</p></div>
                    <a href="https://x.com/{{ $s['username'] ?? '' }}" target="_blank" class="bio-btn px-4 py-2 text-xs font-medium">Follow</a>
                </div>

            @elseif(in_array($block->type, ['twitter_tweet', 'twitter_video']))
                <div class="mb-4 glass-block rounded-xl p-4 text-center">
                    <i class="fab fa-x-twitter text-2xl mb-2"></i>
                    <a href="{{ $s['url'] ?? '#' }}" target="_blank" class="bio-btn inline-block px-5 py-2 text-sm font-medium mt-2">View on X</a>
                </div>

            @elseif($block->type === 'pinterest_profile')
                <div class="mb-4 glass-block rounded-xl p-4 flex items-center gap-3">
                    <div class="w-12 h-12 rounded-full bg-white/10 flex items-center justify-center"><i class="fab fa-pinterest text-xl" style="color:#BD081C"></i></div>
                    <div class="flex-1"><p class="font-medium text-sm">{{ $s['username'] ?? '' }}</p><p class="text-xs text-white/40">Pinterest</p></div>
                    <a href="https://pinterest.com/{{ $s['username'] ?? '' }}" target="_blank" class="bio-btn px-4 py-2 text-xs font-medium">Follow</a>
                </div>

            @elseif($block->type === 'snapchat')
                <div class="mb-4 glass-block rounded-xl p-4 flex items-center gap-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background:#FFFC00"><i class="fab fa-snapchat text-xl text-black"></i></div>
                    <div class="flex-1"><p class="font-medium text-sm">{{ $s['username'] ?? '' }}</p><p class="text-xs text-white/40">Snapchat</p></div>
                    <a href="https://snapchat.com/add/{{ $s['username'] ?? '' }}" target="_blank" class="bio-btn px-4 py-2 text-xs font-medium">Add</a>
                </div>

            @elseif($block->type === 'rss_feed')
                <div class="mb-4 glass-block rounded-xl p-4">
                    <div class="flex items-center gap-2 mb-3"><i class="fas fa-rss text-orange-400"></i><span class="text-sm font-medium">RSS Feed</span></div>
                    <p class="text-xs text-white/40">Feed: {{ $s['url'] ?? '' }}</p>
                </div>

            {{-- MUSIC --}}
            @elseif($block->type === 'spotify')
                @php $spotifyEmbed = str_replace('open.spotify.com', 'open.spotify.com/embed', $s['url'] ?? ''); @endphp
                <div class="mb-4 rounded-xl overflow-hidden">
                    <iframe src="{{ $spotifyEmbed }}" class="w-full rounded-xl" height="{{ ($s['type'] ?? 'track') === 'track' ? '152' : '352' }}"
                            frameborder="0" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" loading="lazy"></iframe>
                </div>

            @elseif($block->type === 'apple_music')
                @php $amEmbed = str_replace('music.apple.com', 'embed.music.apple.com', $s['url'] ?? ''); @endphp
                <div class="mb-4 rounded-xl overflow-hidden">
                    <iframe src="{{ $amEmbed }}" class="w-full rounded-xl" height="{{ ($s['type'] ?? 'album') === 'song' ? '175' : '450' }}" frameborder="0" allow="autoplay; encrypted-media" loading="lazy"></iframe>
                </div>

            @elseif($block->type === 'soundcloud')
                <div class="mb-4 rounded-xl overflow-hidden">
                    <iframe width="100%" height="166" scrolling="no" frameborder="no" src="https://w.soundcloud.com/player/?url={{ urlencode($s['url'] ?? '') }}&color=%237c3aed&auto_play=false&hide_related=true&show_comments=false&show_user=true&show_reposts=false&show_teaser=false" class="rounded-xl" loading="lazy"></iframe>
                </div>

            @elseif(in_array($block->type, ['tidal', 'mixcloud', 'anchor_fm']))
                <div class="mb-4 glass-block rounded-xl p-4 text-center">
                    <i class="fas {{ $block->type === 'tidal' ? 'fa-water' : ($block->type === 'mixcloud' ? 'fa-headphones' : 'fa-podcast') }} text-2xl mb-2"></i>
                    <p class="text-sm font-medium mb-2">{{ \App\Modules\User\Models\BiolinkBlock::TYPES[$block->type]['label'] }}</p>
                    @if(!empty($s['url']))<a href="{{ $s['url'] }}" target="_blank" class="bio-btn inline-block px-5 py-2 text-sm font-medium">Listen</a>@endif
                </div>

            {{-- VIDEO PLATFORMS --}}
            @elseif($block->type === 'youtube')
                @php
                    $videoId = $s['video_id'] ?? '';
                    if (str_contains($videoId, 'youtube.com') || str_contains($videoId, 'youtu.be')) {
                        preg_match('/(?:v=|\/)([\w-]{11})/', $videoId, $m);
                        $videoId = $m[1] ?? $videoId;
                    }
                @endphp
                <div class="mb-4 rounded-xl overflow-hidden aspect-video">
                    <iframe src="https://www.youtube.com/embed/{{ $videoId }}" class="w-full h-full rounded-xl"
                            frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe>
                </div>

            @elseif($block->type === 'youtube_feed')
                <div class="mb-4 glass-block rounded-xl p-4">
                    <div class="flex items-center gap-2 mb-3"><i class="fab fa-youtube text-red-500 text-lg"></i><span class="text-sm font-medium">YouTube Channel</span></div>
                    <p class="text-xs text-white/40">Channel: {{ $s['channel_id'] ?? '' }}</p>
                </div>

            @elseif($block->type === 'vimeo')
                <div class="mb-4 rounded-xl overflow-hidden aspect-video">
                    <iframe src="https://player.vimeo.com/video/{{ $s['video_id'] ?? '' }}" class="w-full h-full rounded-xl" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen loading="lazy"></iframe>
                </div>

            @elseif($block->type === 'twitch')
                <div class="mb-4 rounded-xl overflow-hidden aspect-video">
                    <iframe src="https://player.twitch.tv/?channel={{ $s['channel'] ?? '' }}&parent={{ request()->getHost() }}" class="w-full h-full rounded-xl" frameborder="0" allowfullscreen loading="lazy"></iframe>
                </div>

            @elseif(in_array($block->type, ['kick', 'rumble_video', 'vk_video']))
                <div class="mb-4 glass-block rounded-xl p-4 text-center">
                    <i class="fas {{ $block->type === 'kick' ? 'fa-bolt' : 'fa-play-circle' }} text-2xl mb-2"></i>
                    <p class="text-sm font-medium mb-2">{{ \App\Modules\User\Models\BiolinkBlock::TYPES[$block->type]['label'] }}</p>
                    @php $watchUrl = $block->type === 'kick' ? 'https://kick.com/' . ($s['channel'] ?? '') : ($s['url'] ?? '#'); @endphp
                    <a href="{{ $watchUrl }}" target="_blank" class="bio-btn inline-block px-5 py-2 text-sm font-medium">Watch</a>
                </div>

            {{-- CONTACT --}}
            @elseif($block->type === 'email_collector')
                <div class="mb-4 glass-block rounded-xl p-5 text-center">
                    <p class="text-sm font-semibold mb-3">{{ $s['title'] ?? 'Subscribe' }}</p>
                    <form class="flex gap-2" onsubmit="event.preventDefault(); this.querySelector('button').textContent='Done!'; this.querySelector('button').disabled=true;">
                        <input type="email" required placeholder="{{ $s['placeholder'] ?? 'Your email' }}" class="flex-1 bg-white/5 border border-white/10 rounded-xl px-3 py-2.5 text-sm outline-none focus:border-white/20" style="color:{{ $fontColor }}">
                        <button type="submit" class="bio-btn px-5 py-2.5 text-sm font-medium whitespace-nowrap">{{ $s['button_text'] ?? 'Subscribe' }}</button>
                    </form>
                </div>

            @elseif($block->type === 'phone_collector')
                <div class="mb-4 glass-block rounded-xl p-5 text-center">
                    <p class="text-sm font-semibold mb-3">{{ $s['title'] ?? 'Call Us' }}</p>
                    <form class="flex gap-2" onsubmit="event.preventDefault(); this.querySelector('button').textContent='Done!'; this.querySelector('button').disabled=true;">
                        <input type="tel" required placeholder="{{ $s['placeholder'] ?? 'Your phone' }}" class="flex-1 bg-white/5 border border-white/10 rounded-xl px-3 py-2.5 text-sm outline-none focus:border-white/20" style="color:{{ $fontColor }}">
                        <button type="submit" class="bio-btn px-5 py-2.5 text-sm font-medium whitespace-nowrap">{{ $s['button_text'] ?? 'Submit' }}</button>
                    </form>
                </div>

            @elseif($block->type === 'contact_form')
                <div class="mb-4 glass-block rounded-xl p-5" x-data="{ submitted: false, loading: false, error: '' }">
                    <p class="text-sm font-semibold mb-3 text-center">{{ $s['title'] ?? 'Contact Us' }}</p>
                    <template x-if="!submitted">
                        <form @submit.prevent="
                            loading = true; error = '';
                            fetch('/{{ $link->alias }}/subscribe', {
                                method: 'POST',
                                headers: {'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
                                body: JSON.stringify({
                                    block_id: {{ $block->id }},
                                    type: 'contact_form',
                                    name: $refs.cfName{{ $block->id }}.value,
                                    email: $refs.cfEmail{{ $block->id }}.value,
                                    message: $refs.cfMessage{{ $block->id }}.value,
                                    _hp: $refs.cfHp{{ $block->id }}.value
                                })
                            }).then(r => r.json()).then(d => {
                                loading = false;
                                if(d.success) submitted = true;
                                else error = d.message || 'Something went wrong';
                            }).catch(() => { loading = false; error = 'Network error'; })
                        " class="space-y-3">
                            <input x-ref="cfHp{{ $block->id }}" type="text" name="_hp" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;opacity:0;overflow:hidden;pointer-events:none;">
                            <input x-ref="cfName{{ $block->id }}" type="text" placeholder="Name" required class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2.5 text-sm outline-none" style="color:{{ $fontColor }}">
                            <input x-ref="cfEmail{{ $block->id }}" type="email" placeholder="Email" required class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2.5 text-sm outline-none" style="color:{{ $fontColor }}">
                            <textarea x-ref="cfMessage{{ $block->id }}" placeholder="Message" rows="3" required maxlength="5000" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2.5 text-sm outline-none" style="color:{{ $fontColor }}"></textarea>
                            <button type="submit" :disabled="loading" class="bio-btn w-full py-2.5 text-sm font-medium flex items-center justify-center gap-2">
                                <template x-if="loading"><svg class="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg></template>
                                <span x-text="loading ? 'Sending...' : '{{ $s['button_text'] ?? 'Send' }}'"></span>
                            </button>
                            <p x-show="error" x-text="error" class="text-xs text-red-400 text-center" x-cloak></p>
                        </form>
                    </template>
                    <template x-if="submitted">
                        <div class="text-center py-3">
                            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full mb-3" style="background: rgba(34,197,94,0.15);">
                                <i class="fas fa-check text-green-400 text-xl"></i>
                            </div>
                            <p class="text-sm font-semibold text-green-400">{{ $s['success_message'] ?? 'Message sent — thanks!' }}</p>
                        </div>
                    </template>
                </div>

            @elseif($block->type === 'direct_message')
                @php
                    $dmTitle  = $s['title']  ?? 'Send a direct message';
                    $dmDesc   = $s['description'] ?? 'Reach out — replies arrive in your inbox.';
                    $dmPh     = $s['placeholder'] ?? 'Write your message…';
                    $dmBtn    = $s['button_text'] ?? 'Send message';
                    $dmLimit  = (int) (\App\Modules\Common\Models\ViewerDmConversation::VIEWER_INITIAL_LIMIT);
                    $dmLinkId = (int) ($link->id ?? 0);
                    $loggedIn = \App\Modules\Common\Services\ViewerSession::check();
                @endphp
                @include('common.partials.dm-chat-widget', [
                    'dmTitle'   => $dmTitle,
                    'dmDesc'    => $dmDesc,
                    'dmPh'      => $dmPh,
                    'dmBtn'     => $dmBtn,
                    'dmLimit'   => $dmLimit,
                    'dmLinkId'  => $dmLinkId,
                    'loggedIn'  => $loggedIn,
                    'fontColor' => $fontColor,
                    'variant'   => 'block',
                ])

            @elseif($block->type === 'whatsapp_widget')
                <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $s['phone'] ?? '') }}?text={{ urlencode($s['message'] ?? '') }}" target="_blank" rel="noopener"
                   class="block w-full mb-3 rounded-2xl py-4 text-center font-semibold transition-all duration-300 hover:-translate-y-1 flex items-center justify-center gap-3"
                   style="background: #25D366; color: #fff; border-radius: {{ $btnRadius }};">
                    <i class="fab fa-whatsapp text-xl"></i><span>{{ $s['button_text'] ?? 'Chat on WhatsApp' }}</span>
                </a>

            @elseif($block->type === 'whatsapp_item')
                <div class="mb-4 glass-block rounded-xl p-4 flex items-center gap-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background:#25D366"><i class="fab fa-whatsapp text-xl text-white"></i></div>
                    <div class="flex-1"><p class="font-medium text-sm">{{ $s['name'] ?? 'WhatsApp' }}</p><p class="text-xs text-white/40">{{ $s['phone'] ?? '' }}</p></div>
                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $s['phone'] ?? '') }}?text={{ urlencode($s['message'] ?? '') }}" target="_blank" class="bio-btn px-4 py-2 text-xs font-medium">Chat</a>
                </div>

            {{-- INTERACTIVE --}}
            @elseif(in_array($block->type, ['faq', 'faq_v2']))
                <div class="mb-4 space-y-2" x-data="{ open: null }">
                    @foreach(($s['items'] ?? []) as $i => $item)
                    <div class="glass-block rounded-xl overflow-hidden {{ $block->type === 'faq_v2' ? 'border border-white/10' : '' }}">
                        <button @click="open = open === {{ $i }} ? null : {{ $i }}" class="w-full px-4 py-3 flex items-center justify-between text-left">
                            <span class="text-sm font-medium flex items-center gap-2">@if(!empty($item['icon']))<i class="{{ $item['icon'] }}"></i>@endif{{ $item['question'] ?? '' }}</span>
                            <i class="fas fa-chevron-down text-xs transition-transform" :class="open === {{ $i }} ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === {{ $i }}" x-cloak class="px-4 pb-3"><p class="text-sm" style="color:{{ $fontColor }}99">{{ $item['answer'] ?? '' }}</p></div>
                    </div>
                    @endforeach
                </div>

            @elseif($block->type === 'poll')
                {{-- Live poll: persists each vote to /api/v1/biolinks/{alias}/blocks/{id}/poll-vote
                     and then swaps the button list out for tally bars fetched from the matching
                     /poll-results endpoint, so viewers can immediately see how their pick compares.
                     If the results fetch fails, the option list stays visible with the picked
                     option highlighted (and a small "Thanks for voting" hint) instead of an error wall. --}}
                @php
                    $pollRevealAt = null;
                    $pollRevealLabel = null;
                    if (!empty($s['reveal_results_at'])) {
                        try {
                            $pollRevealAt = \Carbon\Carbon::parse($s['reveal_results_at']);
                            $pollRevealLabel = $pollRevealAt->toIso8601String();
                        } catch (\Throwable $e) {
                            $pollRevealAt = null;
                        }
                    }
                @endphp
                <div class="mb-4 glass-block rounded-xl p-5"
                     x-data="biolinkPoll({
                        alias: @js($link->alias),
                        blockId: {{ (int) $block->id }},
                        options: @js(array_values((array) ($s['options'] ?? []))),
                        revealAt: @js($pollRevealLabel),
                     })"
                     x-init="init()">
                    <p class="text-sm font-semibold mb-3">{{ $s['question'] ?? '' }}</p>
                    <template x-if="resultsLocked">
                        <p class="text-xs mb-2" style="color:{{ $fontColor }}99">
                            <i class="fas fa-lock mr-1"></i>Results visible after <span x-text="revealAtDisplay"></span>
                        </p>
                    </template>
                    <template x-if="!results">
                        <div class="space-y-2">
                            @foreach(($s['options'] ?? []) as $i => $opt)
                            <button type="button"
                                    @click="vote({{ $i }}, @js($opt))"
                                    :disabled="submitting !== null"
                                    class="w-full text-left px-4 py-2.5 rounded-xl text-sm transition-all disabled:opacity-60"
                                    :class="voted === {{ $i }} ? 'bg-purple-500/30 border border-purple-400/40' : 'bg-white/5 border border-white/10 hover:bg-white/10'">
                                <span class="flex items-center gap-2">
                                    <span class="w-4 h-4 rounded-full border-2 flex items-center justify-center"
                                          :class="voted === {{ $i }} ? 'border-purple-400' : 'border-white/30'">
                                        <span x-show="voted === {{ $i }}" class="w-2 h-2 rounded-full bg-purple-400"></span>
                                    </span>
                                    <span class="flex-1">{{ $opt }}</span>
                                    <template x-if="submitting === {{ $i }}">
                                        <i class="fas fa-spinner fa-spin text-xs text-white/60"></i>
                                    </template>
                                </span>
                            </button>
                            @endforeach
                        </div>
                    </template>
                    <template x-if="results">
                        <div>
                            <p class="text-xs mb-2" style="color:{{ $fontColor }}88">
                                <span x-text="results.total_votes"></span>
                                <span x-text="results.total_votes === 1 ? 'vote' : 'votes'"></span>
                            </p>
                            <div class="space-y-2">
                                <template x-for="opt in results.options" :key="opt.index">
                                    <div class="relative w-full px-4 py-2.5 rounded-xl text-sm overflow-hidden border"
                                         :class="opt.index === voted ? 'border-purple-400/50' : 'border-white/10'">
                                        <div class="absolute inset-y-0 left-0 transition-all"
                                             :style="`width:${Math.max(0, Math.min(100, opt.percent))}%; background-color:${opt.index === voted ? 'rgba(124,58,237,0.35)' : 'rgba(124,58,237,0.15)'}`"></div>
                                        <div class="relative flex items-center gap-2">
                                            <span class="flex-1 truncate" x-text="opt.label"></span>
                                            <template x-if="opt.index === voted">
                                                <i class="fas fa-check text-xs text-purple-300"></i>
                                            </template>
                                            <span class="text-xs font-semibold tabular-nums" x-text="opt.percent + '%'"></span>
                                            <span class="text-[10px] tabular-nums" style="color:{{ $fontColor }}66" x-text="opt.count"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                    <template x-if="!results && voted !== null && !error">
                        <p class="text-xs mt-2 text-green-300">Thanks for voting!</p>
                    </template>
                    <template x-if="error">
                        <p class="text-xs mt-2 text-red-300" x-text="error"></p>
                    </template>
                </div>

            @elseif($block->type === 'testimonials')
                <div class="mb-4 space-y-3">
                    @foreach(($s['items'] ?? []) as $item)
                    <div class="glass-block rounded-xl p-4">
                        <div class="flex items-center gap-3 mb-2">
                            @if(!empty($item['avatar']))<img src="{{ $item['avatar'] }}" class="w-10 h-10 rounded-full object-cover" alt="">
                            @else<div class="w-10 h-10 rounded-full bg-purple-500/20 flex items-center justify-center"><span class="text-sm font-bold">{{ strtoupper(substr($item['name'] ?? 'A', 0, 1)) }}</span></div>@endif
                            <div><p class="text-sm font-medium">{{ $item['name'] ?? '' }}</p>
                            <div class="flex gap-0.5">@for($star = 1; $star <= 5; $star++)<i class="fas fa-star text-xs {{ $star <= ($item['rating'] ?? 5) ? 'text-yellow-400' : 'text-white/20' }}"></i>@endfor</div></div>
                        </div>
                        <p class="text-sm" style="color:{{ $fontColor }}cc">{{ $item['text'] ?? '' }}</p>
                    </div>
                    @endforeach
                </div>

            @elseif($block->type === 'review')
                <div class="mb-4 glass-block rounded-xl p-4">
                    <div class="flex items-center gap-3 mb-2">
                        @if(!empty($s['avatar']))<img src="{{ $s['avatar'] }}" class="w-10 h-10 rounded-full object-cover" alt="">@endif
                        <div><p class="text-sm font-medium">{{ $s['name'] ?? '' }}</p>
                        <div class="flex gap-0.5">@for($star = 1; $star <= 5; $star++)<i class="fas fa-star text-xs {{ $star <= ($s['rating'] ?? 5) ? 'text-yellow-400' : 'text-white/20' }}"></i>@endfor</div></div>
                    </div>
                    <p class="text-sm" style="color:{{ $fontColor }}cc">{{ $s['text'] ?? '' }}</p>
                </div>

            @elseif(in_array($block->type, ['timeline', 'timeline_staged']))
                <div class="mb-4 glass-block rounded-xl p-5">
                    <div class="relative pl-6 border-l-2 border-purple-500/30 space-y-4">
                        @foreach(($s['items'] ?? []) as $item)
                        @php $dotColor = ($block->type === 'timeline_staged') ? match($item['status'] ?? 'upcoming') { 'completed' => 'bg-green-400', 'active' => 'bg-purple-400 animate-pulse', default => 'bg-white/30' } : 'bg-purple-400'; @endphp
                        <div class="relative">
                            <div class="absolute -left-[25px] w-3 h-3 rounded-full {{ $dotColor }}"></div>
                            <p class="text-sm font-medium">{{ $item['title'] ?? '' }}</p>
                            @if(!empty($item['description']))<p class="text-xs mt-0.5" style="color:{{ $fontColor }}88">{{ $item['description'] }}</p>@endif
                            @if(!empty($item['date']))<p class="text-xs mt-0.5 text-purple-400/60">{{ $item['date'] }}</p>@endif
                        </div>
                        @endforeach
                    </div>
                </div>

            @elseif($block->type === 'quiz')
                <div class="mb-4 glass-block rounded-xl p-5 text-center">
                    <i class="fas fa-brain text-2xl mb-2 text-purple-400"></i>
                    <p class="text-sm font-semibold">{{ $s['title'] ?? 'Quiz' }}</p>
                    <p class="text-xs text-white/40 mt-1">Interactive quiz</p>
                </div>

            {{-- BUSINESS --}}
            @elseif($block->type === 'product')
                <div class="mb-4 glass-block rounded-xl overflow-hidden">
                    @if(!empty($s['image']))<img src="{{ $s['image'] }}" alt="{{ $s['name'] ?? '' }}" class="w-full h-48 object-cover">@endif
                    <div class="p-4">
                        <div class="flex items-start justify-between">
                            <div><p class="font-semibold text-sm">{{ $s['name'] ?? '' }}</p>@if(!empty($s['badge']))<span class="inline-block mt-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-purple-500/20 text-purple-300">{{ $s['badge'] }}</span>@endif</div>
                            @if(!empty($s['price']))<span class="font-bold text-lg">{{ $s['price'] }}</span>@endif
                        </div>
                        @if(!empty($s['description']))<p class="text-xs mt-2" style="color:{{ $fontColor }}88">{{ $s['description'] }}</p>@endif
                        @if(!empty($s['url']))<a href="{{ $s['url'] }}" target="_blank" class="bio-btn block w-full text-center mt-3 py-2.5 text-sm font-medium">Buy Now</a>@endif
                    </div>
                </div>

            @elseif($block->type === 'service')
                <div class="mb-4 glass-block rounded-xl p-4">
                    <div class="flex items-start gap-3">
                        <div class="w-12 h-12 rounded-xl bg-purple-500/20 flex items-center justify-center flex-shrink-0"><i class="{{ $s['icon'] ?? 'fas fa-star' }} text-purple-400"></i></div>
                        <div class="flex-1">
                            <p class="font-semibold text-sm">{{ $s['name'] ?? '' }}</p>
                            @if(!empty($s['price']))<p class="text-xs text-purple-400 mt-0.5">{{ $s['price'] }}</p>@endif
                            @if(!empty($s['description']))<p class="text-xs mt-1" style="color:{{ $fontColor }}88">{{ $s['description'] }}</p>@endif
                        </div>
                    </div>
                    @if(!empty($s['url']))<a href="{{ $s['url'] }}" target="_blank" class="bio-btn block w-full text-center mt-3 py-2 text-sm font-medium">Learn More</a>@endif
                </div>

            @elseif(in_array($block->type, ['catalog', 'market']))
                <div class="mb-4 space-y-2">
                    @foreach(($s['items'] ?? []) as $item)
                    <div class="glass-block rounded-xl p-3 flex items-center gap-3">
                        @if(!empty($item['image']))<img src="{{ $item['image'] }}" class="w-14 h-14 rounded-lg object-cover" alt="">@endif
                        <div class="flex-1 min-w-0"><p class="font-medium text-sm truncate">{{ $item['name'] ?? '' }}</p>@if(!empty($item['price']))<p class="text-xs text-purple-400">{{ $item['price'] }}</p>@endif</div>
                        @if(!empty($item['url']))<a href="{{ $item['url'] }}" target="_blank" class="bio-btn px-3 py-1.5 text-xs font-medium">View</a>@endif
                    </div>
                    @endforeach
                </div>

            @elseif($block->type === 'price')
                <div class="mb-4 glass-block rounded-xl p-5 text-center">
                    <p class="text-sm font-medium mb-1">{{ $s['title'] ?? '' }}</p>
                    <div class="flex items-baseline justify-center gap-1"><span class="text-3xl font-bold">{{ $s['amount'] ?? '' }}</span><span class="text-sm text-white/40">{{ $s['period'] ?? '' }}</span></div>
                    @if(!empty($s['features']))<ul class="mt-3 space-y-1.5 text-sm text-left">@foreach(($s['features'] ?? []) as $f)<li class="flex items-center gap-2"><i class="fas fa-check text-green-400 text-xs"></i><span style="color:{{ $fontColor }}cc">{{ $f }}</span></li>@endforeach</ul>@endif
                    @if(!empty($s['url']))<a href="{{ $s['url'] }}" target="_blank" class="bio-btn block w-full mt-4 py-2.5 text-sm font-medium">Get Started</a>@endif
                </div>

            @elseif($block->type === 'donation')
                <div class="mb-4 glass-block rounded-xl p-5 text-center">
                    <i class="fas fa-hand-holding-heart text-2xl mb-2 text-pink-400"></i>
                    <p class="font-semibold text-sm">{{ $s['title'] ?? 'Support Us' }}</p>
                    @if(!empty($s['description']))<p class="text-xs mt-1" style="color:{{ $fontColor }}88">{{ $s['description'] }}</p>@endif
                    <div class="flex justify-center gap-2 mt-3 flex-wrap">
                        @foreach(($s['amounts'] ?? [5,10,25]) as $amt)
                        <a href="{{ ($s['url'] ?? '#') }}" target="_blank" class="bio-btn px-4 py-2 text-sm font-medium">${{ $amt }}</a>
                        @endforeach
                    </div>
                </div>

            @elseif($block->type === 'coupon')
                <div class="mb-4 glass-block rounded-xl p-5 text-center" x-data="{ copied: false }">
                    <i class="fas fa-ticket-alt text-2xl mb-2 text-yellow-400"></i>
                    <p class="text-xs mb-2" style="color:{{ $fontColor }}88">{{ $s['description'] ?? '' }}</p>
                    <div class="flex items-center justify-center gap-2">
                        <code class="px-4 py-2 rounded-lg bg-white/10 border border-dashed border-white/20 font-mono text-lg font-bold tracking-wider">{{ $s['code'] ?? '' }}</code>
                        <button @click="navigator.clipboard.writeText('{{ $s['code'] ?? '' }}'); copied = true; setTimeout(() => copied = false, 2000)" class="bio-btn px-3 py-2 text-sm"><i class="fas" :class="copied ? 'fa-check' : 'fa-copy'"></i></button>
                    </div>
                    @if(!empty($s['expires']))<p class="text-xs text-white/30 mt-2">Expires: {{ $s['expires'] }}</p>@endif
                </div>

            @elseif($block->type === 'one_time_offer')
                <div class="mb-4 glass-block rounded-xl p-5 text-center border border-yellow-500/20">
                    <p class="text-xs font-bold uppercase tracking-wider text-yellow-400 mb-1">Limited Offer</p>
                    <p class="font-semibold">{{ $s['title'] ?? '' }}</p>
                    @if(!empty($s['description']))<p class="text-xs mt-1" style="color:{{ $fontColor }}88">{{ $s['description'] }}</p>@endif
                    <div class="flex items-baseline justify-center gap-2 mt-2">
                        @if(!empty($s['original_price']))<span class="text-sm line-through text-white/30">{{ $s['original_price'] }}</span>@endif
                        <span class="text-2xl font-bold text-yellow-400">{{ $s['price'] ?? '' }}</span>
                    </div>
                    @if(!empty($s['url']))<a href="{{ $s['url'] }}" target="_blank" class="bio-btn block w-full mt-3 py-2.5 text-sm font-medium" style="background: linear-gradient(135deg, #f59e0b, #ef4444);">Grab Now</a>@endif
                </div>

            @elseif($block->type === 'paypal')
                <div class="mb-4">
                    <form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_blank">
                        <input type="hidden" name="cmd" value="_xclick"><input type="hidden" name="business" value="{{ $s['email'] ?? '' }}">
                        <input type="hidden" name="amount" value="{{ $s['amount'] ?? '' }}"><input type="hidden" name="currency_code" value="{{ $s['currency'] ?? 'USD' }}">
                        <button type="submit" class="bio-btn w-full py-3.5 text-sm font-semibold flex items-center justify-center gap-2">
                            <i class="fab fa-paypal"></i>{{ $s['button_text'] ?? 'Pay with PayPal' }}
                        </button>
                    </form>
                </div>

            {{-- UTILITY --}}
            @elseif($block->type === 'countdown')
                <div class="mb-4 glass-block rounded-xl p-5 text-center" x-data="countdown('{{ $s['target_date'] ?? '' }}')" x-init="start()">
                    <p class="text-sm font-semibold mb-3">{{ $s['title'] ?? 'Coming Soon' }}</p>
                    <div class="flex justify-center gap-4">
                        <div><span class="text-2xl font-bold" x-text="days">0</span><p class="text-[10px] uppercase tracking-wider mt-1" style="color:{{ $fontColor }}66">Days</p></div>
                        <div><span class="text-2xl font-bold" x-text="hours">0</span><p class="text-[10px] uppercase tracking-wider mt-1" style="color:{{ $fontColor }}66">Hours</p></div>
                        <div><span class="text-2xl font-bold" x-text="minutes">0</span><p class="text-[10px] uppercase tracking-wider mt-1" style="color:{{ $fontColor }}66">Min</p></div>
                        <div><span class="text-2xl font-bold" x-text="seconds">0</span><p class="text-[10px] uppercase tracking-wider mt-1" style="color:{{ $fontColor }}66">Sec</p></div>
                    </div>
                </div>

            @elseif($block->type === 'progress')
                <div class="mb-4 glass-block rounded-xl p-4 space-y-3">
                    @foreach(($s['items'] ?? []) as $item)
                    <div>
                        <div class="flex justify-between text-xs mb-1"><span>{{ $item['label'] ?? '' }}</span><span>{{ $item['value'] ?? 0 }}%</span></div>
                        <div class="w-full h-2 rounded-full bg-white/10"><div class="h-full rounded-full transition-all" style="width: {{ $item['value'] ?? 0 }}%; background: {{ $item['color'] ?? '#7c3aed' }};"></div></div>
                    </div>
                    @endforeach
                </div>

            @elseif($block->type === 'chart_pie')
                @php $total = array_sum(array_column($s['items'] ?? [], 'value')); $offset = 0; @endphp
                <div class="mb-4 glass-block rounded-xl p-5 flex items-center gap-4">
                    <svg viewBox="0 0 36 36" class="w-24 h-24 flex-shrink-0">
                        @foreach(($s['items'] ?? []) as $item)
                        @php $pct = $total > 0 ? ($item['value'] / $total * 100) : 0; @endphp
                        <circle cx="18" cy="18" r="15.9" fill="none" stroke="{{ $item['color'] ?? '#7c3aed' }}" stroke-width="3.8" stroke-dasharray="{{ $pct }} {{ 100 - $pct }}" stroke-dashoffset="-{{ $offset }}" transform="rotate(-90 18 18)"></circle>
                        @php $offset += $pct; @endphp
                        @endforeach
                    </svg>
                    <div class="space-y-1 text-xs">@foreach(($s['items'] ?? []) as $item)<div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full" style="background:{{ $item['color'] ?? '#7c3aed' }}"></span>{{ $item['label'] ?? '' }}</div>@endforeach</div>
                </div>

            @elseif($block->type === 'qr_code')
                <div class="mb-4 glass-block rounded-xl p-5 flex justify-center">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size={{ $s['size'] ?? 200 }}x{{ $s['size'] ?? 200 }}&data={{ urlencode($s['url'] ?? request()->url()) }}&bgcolor=0f0a1a&color=ffffff" alt="QR Code" class="rounded-lg">
                </div>

            @elseif($block->type === 'share')
                @php $shareUrl = urlencode(request()->url()); $shareText = urlencode($s['text'] ?? ''); @endphp
                <div class="mb-4 glass-block rounded-xl p-4">
                    <p class="text-sm font-medium text-center mb-3">{{ $s['text'] ?? 'Share this page' }}</p>
                    <div class="flex justify-center gap-3">
                        <a href="https://twitter.com/intent/tweet?url={{ $shareUrl }}&text={{ $shareText }}" target="_blank" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center hover:bg-white/20 transition"><i class="fab fa-x-twitter"></i></a>
                        <a href="https://www.facebook.com/sharer/sharer.php?u={{ $shareUrl }}" target="_blank" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center hover:bg-white/20 transition"><i class="fab fa-facebook-f" style="color:#1877F2"></i></a>
                        <a href="https://www.linkedin.com/shareArticle?url={{ $shareUrl }}" target="_blank" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center hover:bg-white/20 transition"><i class="fab fa-linkedin-in" style="color:#0A66C2"></i></a>
                        <a href="https://wa.me/?text={{ $shareText }}%20{{ $shareUrl }}" target="_blank" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center hover:bg-white/20 transition"><i class="fab fa-whatsapp" style="color:#25D366"></i></a>
                    </div>
                </div>

            @elseif($block->type === 'cta_button')
                <a href="{{ $s['url'] ?? '#' }}" target="_blank" rel="noopener"
                   class="block w-full mb-3 text-center font-semibold transition-all duration-300 hover:-translate-y-0.5"
                   style="background: {{ $s['color'] ?? $btnColor }}; color: {{ $s['text_color'] ?? $btnTextColor }};
                          padding: {{ ($s['size'] ?? 'lg') === 'sm' ? '10px 20px' : (($s['size'] ?? 'lg') === 'md' ? '14px 24px' : '18px 32px') }};
                          border-radius: {{ $btnRadius }}; box-shadow: 0 6px 20px {{ $s['color'] ?? $btnColor }}40;
                          font-size: {{ ($s['size'] ?? 'lg') === 'sm' ? '14px' : (($s['size'] ?? 'lg') === 'md' ? '16px' : '18px') }};">
                    {{ $s['text'] ?? 'Click Here' }}
                </a>

            @elseif($block->type === 'notification')
                @php $nColors = ['info' => 'bg-violet-500/20 border-violet-400/30', 'success' => 'bg-green-500/20 border-green-400/30', 'warning' => 'bg-yellow-500/20 border-yellow-400/30']; @endphp
                <div class="mb-4 rounded-xl p-3 border {{ $nColors[$s['type'] ?? 'info'] ?? $nColors['info'] }} flex items-center gap-3" x-data="{ show: true }" x-show="show">
                    <i class="fas fa-bell text-sm"></i><p class="text-sm flex-1">{{ $s['text'] ?? '' }}</p>
                    @if($s['dismissible'] ?? true)<button @click="show = false" class="text-white/40 hover:text-white"><i class="fas fa-times text-xs"></i></button>@endif
                </div>

            @elseif($block->type === 'nav_menu')
                <div class="mb-4 flex flex-wrap justify-center gap-2">
                    @foreach(($s['items'] ?? []) as $item)
                    <a href="{{ $item['url'] ?? '#' }}" class="px-4 py-2 text-xs font-medium glass-block rounded-full hover:bg-white/10 transition">{{ $item['text'] ?? '' }}</a>
                    @endforeach
                </div>

            @elseif($block->type === 'ticker')
                <div class="mb-4 glass-block rounded-xl overflow-hidden py-2">
                    <div class="ticker-scroll whitespace-nowrap text-sm">
                        @foreach(($s['items'] ?? []) as $item)<span class="mx-6">{{ $item }}</span>@endforeach
                        @foreach(($s['items'] ?? []) as $item)<span class="mx-6">{{ $item }}</span>@endforeach
                    </div>
                </div>

            {{-- LAYOUT --}}
            @elseif(in_array($block->type, ['card_slider', 'scroll_cards']))
                <div class="mb-4 flex gap-3 overflow-x-auto pb-2 snap-x snap-mandatory" style="scrollbar-width: thin;">
                    @foreach(($s['cards'] ?? $s['items'] ?? []) as $card)
                    <div class="glass-block rounded-xl flex-shrink-0 w-64 snap-center overflow-hidden">
                        @if(!empty($card['image']))<img src="{{ $card['image'] }}" class="w-full h-32 object-cover" alt="">@endif
                        <div class="p-3"><p class="font-medium text-sm">{{ $card['title'] ?? $card['name'] ?? '' }}</p>@if(!empty($card['description']))<p class="text-xs mt-1" style="color:{{ $fontColor }}88">{{ $card['description'] }}</p>@endif
                        @if(!empty($card['url']))<a href="{{ $card['url'] }}" target="_blank" class="text-xs text-purple-400 mt-2 inline-block">View &rarr;</a>@endif</div>
                    </div>
                    @endforeach
                </div>

            @elseif(str_starts_with($block->type, 'profile_card'))
                <div class="mb-4 glass-block rounded-2xl overflow-hidden">
                    @if($block->type === 'profile_card_v2' && !empty($s['cover']))
                        <div class="h-24 bg-cover bg-center" style="background-image: url('{{ $s['cover'] }}')"></div>
                        <div class="-mt-10 px-4 pb-4">
                    @else
                        <div class="p-5">
                    @endif
                        <div class="flex {{ in_array($block->type, ['profile_card_v3', 'profile_card_v4']) ? 'flex-col items-center text-center' : 'items-center gap-4' }}">
                            @if(!empty($s['avatar']))<img src="{{ $s['avatar'] }}" class="w-16 h-16 rounded-full object-cover border-2 border-white/10" alt="">
                            @else<div class="w-16 h-16 rounded-full bg-purple-500/20 flex items-center justify-center border-2 border-white/10"><span class="text-xl font-bold">{{ strtoupper(substr($s['name'] ?? 'U', 0, 1)) }}</span></div>@endif
                            <div class="{{ in_array($block->type, ['profile_card_v3', 'profile_card_v4']) ? 'mt-3' : '' }}">
                                <p class="font-semibold">{{ $s['name'] ?? '' }}</p>
                                @if(!empty($s['title']))<p class="text-xs text-purple-400">{{ $s['title'] }}</p>@endif
                            </div>
                        </div>
                        @if(!empty($s['bio']))<p class="text-sm mt-3" style="color:{{ $fontColor }}88">{{ $s['bio'] }}</p>@endif
                        @if($block->type === 'profile_card_v3' && !empty($s['stats']))
                        <div class="flex justify-center gap-6 mt-3">@foreach(($s['stats'] ?? []) as $stat)<div class="text-center"><p class="font-bold">{{ $stat['value'] ?? '0' }}</p><p class="text-[10px] text-white/40">{{ $stat['label'] ?? '' }}</p></div>@endforeach</div>
                        @endif
                    </div>
                </div>

            {{-- INTEGRATIONS --}}
            @elseif($block->type === 'custom_html')
                <div class="mb-4">{!! strip_tags($s['html'] ?? '', '<p><br><a><strong><em><u><ul><ol><li><h1><h2><h3><h4><h5><h6><span><div><img><iframe><table><tr><td><th><thead><tbody><hr><blockquote><pre><code><style>') !!}</div>

            @elseif($block->type === 'iframe_embed')
                <div class="mb-4 rounded-xl overflow-hidden"><iframe src="{{ $s['url'] ?? '' }}" class="w-full rounded-xl" style="height:{{ $s['height'] ?? 400 }}px;" frameborder="0" loading="lazy"></iframe></div>

            @elseif($block->type === 'social_proof')
                @php
                    $sp = !empty($s['social_proof_id'])
                        ? \App\Modules\User\Models\SocialProof::where('id', $s['social_proof_id'])
                            ->where('user_id', $link->user_id)
                            ->where('is_active', true)
                            ->first()
                        : null;
                @endphp
                @if($sp)
                    <script src="{{ url('/sp/' . $sp->uuid . '.js') }}" async></script>
                @else
                    <div class="mb-4 glass-block rounded-xl p-4 text-center text-xs" style="color:{{ $fontColor }}66;">
                        <i class="fas fa-bell mr-1"></i> Buzz campaign not selected
                    </div>
                @endif

            @elseif($block->type === 'form')
                @php
                    $formId = $s['form_id'] ?? null;
                    $formModel = $formId ? \App\Modules\User\Models\Form::find($formId) : null;
                @endphp
                @if($formModel && $formModel->is_active && (int)$formModel->user_id === (int)$link->user_id)
                    <div class="mb-4 rounded-xl overflow-hidden glass-block">
                        <iframe src="{{ $formModel->getPublicUrl() }}/iframe"
                                class="w-full block"
                                style="height: {{ $s['height'] ?? 600 }}px; border: 0; background: transparent;"
                                loading="lazy"
                                data-form-frame="{{ $formModel->id }}"
                                title="{{ $formModel->title }}"></iframe>
                    </div>
                    <script>
                        (function () {
                            if (window.__1inmeFormResizeBound) return;
                            window.__1inmeFormResizeBound = true;
                            window.addEventListener('message', function (e) {
                                if (!e.data || e.data.type !== '1inme-form-resize') return;
                                document.querySelectorAll('iframe[data-form-frame]').forEach(function (f) {
                                    if (f.contentWindow === e.source) f.style.height = (e.data.height + 4) + 'px';
                                });
                            });
                        })();
                    </script>
                @else
                    <div class="mb-4 glass-block rounded-xl p-4 text-center text-xs" style="color:{{ $fontColor }}66;">
                        <i class="fas fa-wpforms mr-1"></i> Form not selected
                    </div>
                @endif

            @elseif($block->type === 'typeform')
                <div class="mb-4 rounded-xl overflow-hidden"><iframe src="{{ $s['url'] ?? '' }}" class="w-full rounded-xl" style="height:500px;" frameborder="0" loading="lazy"></iframe></div>

            @elseif($block->type === 'calendly')
                <div class="mb-4 rounded-xl overflow-hidden"><iframe src="{{ $s['url'] ?? '' }}" class="w-full rounded-xl" style="height:630px;" frameborder="0" loading="lazy"></iframe></div>

            @elseif($block->type === 'discord_server')
                <div class="mb-4 rounded-xl overflow-hidden"><iframe src="https://discord.com/widget?id={{ $s['server_id'] ?? '' }}&theme=dark" class="w-full rounded-xl" height="350" frameborder="0" loading="lazy"></iframe></div>

            @elseif(in_array($block->type, ['facebook_post', 'reddit_post', 'telegram_post']))
                <div class="mb-4 glass-block rounded-xl p-4 text-center">
                    @php $platform = match($block->type) { 'facebook_post' => ['fab fa-facebook', 'Facebook', '#1877F2'], 'reddit_post' => ['fab fa-reddit', 'Reddit', '#FF4500'], 'telegram_post' => ['fab fa-telegram', 'Telegram', '#26A5E4'] }; @endphp
                    <i class="{{ $platform[0] }} text-2xl mb-2" style="color:{{ $platform[2] }}"></i>
                    <a href="{{ $s['url'] ?? '#' }}" target="_blank" class="bio-btn inline-block px-5 py-2 text-sm font-medium mt-2">View on {{ $platform[1] }}</a>
                </div>

            {{-- FILES --}}
            @elseif($block->type === 'file')
                <a href="{{ $s['url'] ?? '#' }}" target="_blank" class="mb-3 glass-block rounded-xl p-4 flex items-center gap-3 block hover:bg-white/[0.06] transition">
                    <div class="w-11 h-11 rounded-xl bg-purple-500/20 flex items-center justify-center"><i class="{{ $s['icon'] ?? 'fas fa-file-download' }} text-purple-400"></i></div>
                    <div class="flex-1 min-w-0"><p class="font-medium text-sm truncate">{{ $s['name'] ?? 'Download File' }}</p>@if(!empty($s['size']))<p class="text-xs text-white/40">{{ $s['size'] }}</p>@endif</div>
                    <i class="fas fa-download text-white/30"></i>
                </a>

            @elseif($block->type === 'external_item')
                <a href="{{ $s['url'] ?? '#' }}" target="_blank" class="mb-3 glass-block rounded-xl overflow-hidden block hover:bg-white/[0.06] transition">
                    @if(!empty($s['image']))<img src="{{ $s['image'] }}" class="w-full h-40 object-cover" alt="">@endif
                    <div class="p-4"><p class="font-medium text-sm">{{ $s['title'] ?? '' }}</p>@if(!empty($s['description']))<p class="text-xs mt-1" style="color:{{ $fontColor }}88">{{ $s['description'] }}</p>@endif</div>
                </a>

            @elseif($block->type === 'markdown')
                <div class="mb-4 glass-block rounded-xl p-4 prose prose-invert prose-sm max-w-none">{!! nl2br(e($s['content'] ?? '')) !!}</div>

            {{-- MAPS --}}
            @elseif($block->type === 'map')
                @php $mapQ = urlencode($s['address'] ?? ''); @endphp
                <div class="mb-4 rounded-xl overflow-hidden aspect-video glass-block">
                    <iframe src="https://maps.google.com/maps?q={{ $mapQ }}&z={{ $s['zoom'] ?? 14 }}&output=embed" class="w-full h-full rounded-xl" frameborder="0" style="border:0;filter:invert(90%) hue-rotate(180deg);" loading="lazy"></iframe>
                </div>

            @elseif($block->type === 'yandex_maps')
                @php $yQ = urlencode($s['address'] ?? ''); @endphp
                <div class="mb-4 rounded-xl overflow-hidden aspect-video glass-block">
                    <iframe src="https://yandex.com/map-widget/v1/?text={{ $yQ }}&z={{ $s['zoom'] ?? 14 }}" class="w-full h-full rounded-xl" frameborder="0" loading="lazy"></iframe>
                </div>

            {{-- CARD CONTAINER --}}
            @elseif($block->type === 'card')
                @php
                    $cardChildren = $block->activeChildren()->get()->filter(fn($b) => $b->isVisible());
                    $cols = intval($s['columns'] ?? 2) ?: 2;
                    $gap = intval($s['gap'] ?? 12);
                    $pad = intval($s['padding'] ?? 16);
                    $br = intval($s['border_radius'] ?? 16);
                    $bgType = $s['bg_type'] ?? 'glass';
                    $bw = intval($s['border_width'] ?? 1);
                    $bc = $s['border_color'] ?? 'rgba(255,255,255,0.08)';
                    $shadow = match($s['shadow'] ?? 'none') {
                        'sm' => '0 1px 3px ' . ($s['shadow_color'] ?? '#00000040'),
                        'md' => '0 4px 12px ' . ($s['shadow_color'] ?? '#00000040'),
                        'lg' => '0 10px 30px ' . ($s['shadow_color'] ?? '#00000040'),
                        'xl' => '0 20px 50px ' . ($s['shadow_color'] ?? '#00000040'),
                        default => 'none',
                    };
                    $bgStyle = match($bgType) {
                        'glass' => 'background:rgba(255,255,255,' . (intval($s['glass_opacity'] ?? 6) / 100) . ');backdrop-filter:blur(' . intval($s['glass_blur'] ?? 12) . 'px);-webkit-backdrop-filter:blur(' . intval($s['glass_blur'] ?? 12) . 'px);',
                        'color' => 'background:' . ($s['bg_color'] ?? 'rgba(255,255,255,0.06)') . ';',
                        'gradient' => 'background:' . ($s['bg_gradient'] ?? 'linear-gradient(135deg,#7c3aed,#ec4899)') . ';',
                        'image' => 'background:url(' . ($s['bg_image'] ?? '') . ') center/cover no-repeat;',
                        'transparent' => 'background:transparent;',
                        default => 'background:rgba(255,255,255,0.06);',
                    };
                @endphp
                <div class="mb-4 card-container-render" style="{{ $bgStyle }} padding:{{ $pad }}px; border-radius:{{ $br }}px; border:{{ $bw }}px solid {{ $bc }}; box-shadow:{{ $shadow }};">
                    @if(!empty($s['title']))
                    <div class="mb-3 text-sm font-semibold" style="color: {{ $fontColor ?? '#fff' }}cc;">{{ $s['title'] }}</div>
                    @endif
                    <div style="display:grid; grid-template-columns:repeat({{ $cols }}, 1fr); gap:{{ $gap }}px;">
                        @foreach($cardChildren as $childBlock)
                            @php
                                $cs = $childBlock->settings ?? [];
                                $childStyle = \App\Modules\User\Models\BiolinkBlock::getBlockStyle($cs, $globalTheme);
                                $childInline = \App\Modules\User\Models\BiolinkBlock::buildInlineStyle($childStyle);
                                $childHasStyle = !empty($cs['_style']) || (!empty($globalTheme) && ($globalTheme['apply_to_all'] ?? false));
                                $childSkipWrap = in_array($childBlock->type, ['avatar', 'divider', 'spacer', 'social_icons']);
                                $childSpanRaw = intval($childStyle['grid_span'] ?? 12) ?: 12;
                                $childSpan = max(1, (int)round($childSpanRaw / 12 * $cols));
                            @endphp
                            <div style="grid-column: span {{ min($childSpan, $cols) }};">
                            @if($childHasStyle && !$childSkipWrap)<div class="block-styled" style="{{ $childInline }}">@endif
                                @include('common.partials.biolink-block-render', ['block' => $childBlock, 's' => $cs, 'fontColor' => $fontColor ?? '#fff'])
                            @if($childHasStyle && !$childSkipWrap)</div>@endif
                            </div>
                        @endforeach
                    </div>
                </div>

            {{-- IDENTITY --}}
            @elseif($block->type === 'vcard')
                <div class="mb-4 glass-block rounded-xl p-5 text-center">
                    <div class="w-16 h-16 rounded-full bg-purple-500/20 flex items-center justify-center mx-auto mb-3"><i class="fas fa-address-book text-2xl text-purple-400"></i></div>
                    <p class="font-semibold">{{ $s['name'] ?? '' }}</p>
                    @if(!empty($s['title']))<p class="text-xs text-purple-400">{{ $s['title'] }}</p>@endif
                    @if(!empty($s['company']))<p class="text-xs text-white/40">{{ $s['company'] }}</p>@endif
                    <div class="flex justify-center gap-4 mt-3 text-sm">
                        @if(!empty($s['phone']))<a href="tel:{{ $s['phone'] }}" class="text-purple-400"><i class="fas fa-phone"></i></a>@endif
                        @if(!empty($s['email']))<a href="mailto:{{ $s['email'] }}" class="text-purple-400"><i class="fas fa-envelope"></i></a>@endif
                        @if(!empty($s['website']))<a href="{{ $s['website'] }}" target="_blank" class="text-purple-400"><i class="fas fa-globe"></i></a>@endif
                    </div>
                    <button onclick="downloadVCard()" class="bio-btn mt-3 px-5 py-2 text-sm font-medium">Save Contact</button>
                    <script>
                    function downloadVCard(){
                        var vcard = "BEGIN:VCARD\nVERSION:3.0\nN:{{ $s['name'] ?? '' }}\nFN:{{ $s['name'] ?? '' }}\nORG:{{ $s['company'] ?? '' }}\nTITLE:{{ $s['title'] ?? '' }}\nTEL:{{ $s['phone'] ?? '' }}\nEMAIL:{{ $s['email'] ?? '' }}\nURL:{{ $s['website'] ?? '' }}\nEND:VCARD";
                        var blob = new Blob([vcard], {type:'text/vcard'});
                        var a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = '{{ $s['name'] ?? 'contact' }}.vcf'; a.click();
                    }
                    </script>
                </div>

            {{-- SUBSCRIPTION BLOCKS --}}
            @elseif($block->type === 'email_subscribe')
                <div class="mb-4 glass-block rounded-2xl p-6" x-data="{ submitted: false, loading: false, error: '' }">
                    <div class="text-center mb-4">
                        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full mb-3" style="background: linear-gradient(135deg, rgba(124,58,237,0.3), rgba(168,85,247,0.2));">
                            <i class="fas fa-envelope text-purple-400 text-lg"></i>
                        </div>
                        <p class="text-base font-semibold mb-1">{{ $s['title'] ?? 'Subscribe' }}</p>
                        @if(!empty($s['description']))<p class="text-xs opacity-50 leading-relaxed">{{ $s['description'] }}</p>@endif
                    </div>
                    <template x-if="!submitted">
                        <form @submit.prevent="
                            loading = true; error = '';
                            fetch('/{{ $link->alias }}/subscribe', {
                                method: 'POST',
                                headers: {'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
                                body: JSON.stringify({
                                    block_id: {{ $block->id }},
                                    type: 'email',
                                    email: $refs.emailInput{{ $block->id }}.value,
                                    name: $refs.nameInput{{ $block->id }} ? $refs.nameInput{{ $block->id }}.value : '',
                                    _hp: $refs.hpInput{{ $block->id }} ? $refs.hpInput{{ $block->id }}.value : ''
                                })
                            }).then(r => r.json()).then(d => {
                                loading = false;
                                if(d.success) submitted = true;
                                else error = d.message || 'Something went wrong';
                            }).catch(() => { loading = false; error = 'Network error'; })
                        " class="space-y-3">
                            <input x-ref="hpInput{{ $block->id }}" type="text" name="_hp" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;opacity:0;overflow:hidden;pointer-events:none;">
                            @if($s['name_field'] ?? false)
                            <input x-ref="nameInput{{ $block->id }}" type="text" placeholder="Your name" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm outline-none focus:border-purple-500/40 transition" style="color:{{ $fontColor }}">
                            @endif
                            <input x-ref="emailInput{{ $block->id }}" type="email" required placeholder="{{ $s['placeholder'] ?? 'Enter your email' }}" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm outline-none focus:border-purple-500/40 transition" style="color:{{ $fontColor }}">
                            <button type="submit" :disabled="loading" class="bio-btn w-full px-5 py-3 text-sm font-semibold rounded-xl flex items-center justify-center gap-2 transition-all">
                                <template x-if="loading"><svg class="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg></template>
                                <span x-text="loading ? 'Subscribing...' : '{{ $s['button_text'] ?? 'Subscribe' }}'"></span>
                            </button>
                            <p x-show="error" x-text="error" class="text-xs text-red-400 text-center" x-cloak></p>
                        </form>
                    </template>
                    <template x-if="submitted">
                        <div class="text-center py-3">
                            <div class="inline-flex items-center justify-center w-14 h-14 rounded-full mb-3" style="background: rgba(34,197,94,0.15);">
                                <i class="fas fa-check text-green-400 text-xl"></i>
                            </div>
                            <p class="text-sm font-semibold text-green-400">{{ $s['success_message'] ?? 'Thanks for subscribing!' }}</p>
                        </div>
                    </template>
                </div>

            @elseif($block->type === 'whatsapp_channel_subscribe')
                <div class="mb-4 glass-block rounded-2xl p-5">
                    <div class="text-center mb-4">
                        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full mb-3" style="background: rgba(37,211,102,0.15);">
                            <i class="fab fa-whatsapp text-xl" style="color: #25D366;"></i>
                        </div>
                        <p class="text-base font-semibold mb-1">{{ $s['title'] ?? 'Follow our Channel' }}</p>
                        @if(!empty($s['description']))<p class="text-xs opacity-50 leading-relaxed">{{ $s['description'] }}</p>@endif
                    </div>
                    <input id="hp_{{ $block->id }}" type="text" name="_hp" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;opacity:0;overflow:hidden;pointer-events:none;">
                    <a href="{{ $s['channel_url'] ?? '#' }}" target="_blank" rel="noopener"
                       class="block w-full py-3.5 text-center font-semibold rounded-xl text-sm flex items-center justify-center gap-3 transition-all hover:-translate-y-0.5 hover:shadow-lg"
                       style="background: #25D366; color: #fff;"
                       onclick="fetch('/{{ $link->alias }}/subscribe', {method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},body:JSON.stringify({block_id:{{ $block->id }},type:'whatsapp_channel',channel_url:'{{ $s['channel_url'] ?? '' }}',_hp:(document.getElementById('hp_{{ $block->id }}')||{}).value||''})})">
                        <i class="fab fa-whatsapp text-lg"></i>
                        <span>{{ $s['button_text'] ?? 'Follow Channel' }}</span>
                    </a>
                </div>

            @elseif($block->type === 'whatsapp_number_subscribe')
                <div class="mb-4 glass-block rounded-2xl p-5" x-data="{ submitted: false, loading: false, error: '', phone: '' }">
                    <div class="text-center mb-4">
                        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full mb-3" style="background: rgba(37,211,102,0.15);">
                            <i class="fab fa-whatsapp text-xl" style="color: #25D366;"></i>
                        </div>
                        <p class="text-base font-semibold mb-1">{{ $s['title'] ?? 'Subscribe via WhatsApp' }}</p>
                        @if(!empty($s['description']))<p class="text-xs opacity-50 leading-relaxed">{{ $s['description'] }}</p>@endif
                    </div>
                    <template x-if="!submitted">
                        <div class="space-y-3">
                            <input x-ref="hpInput{{ $block->id }}" type="text" name="_hp" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;opacity:0;overflow:hidden;pointer-events:none;">
                            @if($s['collect_phone'] ?? true)
                            <input x-model="phone" type="tel" placeholder="Your WhatsApp number" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm outline-none focus:border-green-500/40 transition" style="color:{{ $fontColor }}">
                            @endif
                            <button @click="
                                loading = true; error = '';
                                fetch('/{{ $link->alias }}/subscribe', {
                                    method: 'POST',
                                    headers: {'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
                                    body: JSON.stringify({
                                        block_id: {{ $block->id }},
                                        type: 'whatsapp_number',
                                        phone: phone,
                                        _hp: $refs.hpInput{{ $block->id }} ? $refs.hpInput{{ $block->id }}.value : ''
                                    })
                                }).then(r => r.json()).then(d => {
                                    loading = false;
                                    if(d.success) {
                                        submitted = true;
                                        window.open('https://wa.me/{{ preg_replace('/[^0-9]/', '', $s['phone'] ?? '') }}?text={{ urlencode($s['default_message'] ?? 'Hi! I want to subscribe.') }}', '_blank');
                                    } else error = d.message || 'Something went wrong';
                                }).catch(() => { loading = false; error = 'Network error'; })
                            " :disabled="loading"
                               class="block w-full py-3.5 text-center font-semibold rounded-xl text-sm flex items-center justify-center gap-3 transition-all hover:-translate-y-0.5 cursor-pointer"
                               style="background: #25D366; color: #fff;">
                                <template x-if="loading"><svg class="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg></template>
                                <i class="fab fa-whatsapp text-lg" x-show="!loading"></i>
                                <span x-text="loading ? 'Subscribing...' : '{{ $s['button_text'] ?? 'Subscribe on WhatsApp' }}'"></span>
                            </button>
                            <p x-show="error" x-text="error" class="text-xs text-red-400 text-center" x-cloak></p>
                        </div>
                    </template>
                    <template x-if="submitted">
                        <div class="text-center py-3">
                            <div class="inline-flex items-center justify-center w-14 h-14 rounded-full mb-3" style="background: rgba(34,197,94,0.15);">
                                <i class="fas fa-check text-green-400 text-xl"></i>
                            </div>
                            <p class="text-sm font-semibold text-green-400">Subscribed! Check WhatsApp.</p>
                        </div>
                    </template>
                </div>

            @elseif($block->type === 'roadmap')
                @include('common.blocks.roadmap', ['link' => $link, 'block' => $block, 's' => $s, 'fontColor' => $fontColor ?? '#ffffff'])

            @endif

            @if($hasCustomStyle && !$skipWrap)</div>@endif
            </div>
        @empty
            <div class="text-center py-12">
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
        @endforelse

        @php
            $__creator = $link->user ?? null;
            $__viewer  = \App\Modules\Common\Services\ViewerSession::user();
            $__isSelf  = ($__viewer && $__creator && (int)$__viewer->id === (int)$__creator->id);
            $__isFollowing = ($__viewer && $__creator && !$__isSelf)
                ? \App\Modules\User\Models\Follow::where('follower_id',$__viewer->id)->where('creator_id',$__creator->id)->exists()
                : false;
            $__brandingHidden = (bool)($bs['branding_hidden'] ?? false);
            $__allowFollowers = $__creator ? (bool)($__creator->allow_followers ?? true) : false;
        @endphp

        @if(!$__brandingHidden)
            {{-- Subtle viewer sign-in / follow entry in the branding strip. --}}
            @if($__creator && !$__isSelf && $__allowFollowers)
            <div class="text-center mt-6 mb-0" style="grid-column: 1 / -1;">
                @if(!$__viewer)
                    <button type="button"
                            @click="$dispatch('open-viewer-login', {creatorId: {{ (int)$__creator->id }} })"
                            class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-xs font-semibold transition-all hover:scale-105"
                            style="background: {{ $fontColor }}15; color: {{ $fontColor }}cc; border: 1px solid {{ $fontColor }}25;">
                        <i class="fas fa-user-plus text-[10px]"></i>
                        Sign in to follow {{ $__creator->name }}
                    </button>
                @else
                    <div class="inline-flex items-center gap-2 px-2 py-1 rounded-full text-xs"
                         style="background: {{ $fontColor }}10; color: {{ $fontColor }}cc; border: 1px solid {{ $fontColor }}20;"
                         x-data="{busy:false, menu:false, following: {{ $__isFollowing ? 'true' : 'false' }} }">
                        <button type="button" :disabled="busy"
                                @click="busy=true; fetch('/viewer/follow/{{ (int)$__creator->id }}',{method:'POST', headers:{'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept':'application/json'}}).then(r=>r.json()).then(d=>{following=!!d.following; busy=false;}).catch(()=>busy=false)"
                                class="font-bold underline-offset-2 hover:underline px-2"
                                style="color: {{ $fontColor }};"
                                x-text="following ? '✓ Following' : '+ Follow'"></button>
                        <span style="color: {{ $fontColor }}33;">|</span>
                        <div class="relative" @click.away="menu=false">
                            <button type="button" @click="menu=!menu" class="flex items-center gap-1.5 pr-1 hover:opacity-90">
                                @if($__viewer->avatar)
                                    <img src="{{ $__viewer->avatar }}" class="w-5 h-5 rounded-full object-cover" alt=""/>
                                @else
                                    <span class="w-5 h-5 rounded-full inline-flex items-center justify-center text-[10px] font-bold"
                                          style="background: {{ $fontColor }}30; color: {{ $fontColor }};">
                                        {{ strtoupper(substr($__viewer->name, 0, 1)) }}
                                    </span>
                                @endif
                                <span style="color: {{ $fontColor }}cc;">{{ $__viewer->name }}</span>
                                <i class="fas fa-chevron-down text-[8px] opacity-60"></i>
                            </button>
                            <div x-show="menu" x-cloak x-transition
                                 class="absolute right-0 mt-2 w-44 rounded-xl shadow-2xl text-left overflow-hidden z-[1000]"
                                 style="background: #0f172a; color: #fff; border: 1px solid rgba(255,255,255,0.1);">
                                <a href="{{ url('/feed') }}" class="block px-3 py-2 text-xs hover:bg-white/10"><i class="fas fa-stream w-4 opacity-70"></i> My feed</a>
                                <a href="{{ url('/creators') }}" class="block px-3 py-2 text-xs hover:bg-white/10"><i class="fas fa-compass w-4 opacity-70"></i> Discover creators</a>
                                <a href="{{ route('user.profile.edit') }}" class="block px-3 py-2 text-xs hover:bg-white/10"><i class="fas fa-user w-4 opacity-70"></i> My profile</a>
                                <button type="button"
                                        @click="fetch('{{ route('viewer.logout') }}',{method:'POST',headers:{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content}}).then(()=>location.reload())"
                                        class="block w-full text-left px-3 py-2 text-xs hover:bg-white/10 border-t border-white/10">
                                    <i class="fas fa-sign-out-alt w-4 opacity-70"></i> Sign out
                                </button>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
            @endif

            @if(!empty($bs['custom_branding_text']))
            <div class="text-center mt-4" style="grid-column: 1 / -1;">
                @if(!empty($bs['custom_branding_url']))
                <a href="{{ $bs['custom_branding_url'] }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 hover:opacity-80 transition-opacity" style="color: {{ $fontColor }}55; text-decoration: none;">
                @else
                <span class="inline-flex items-center gap-2" style="color: {{ $fontColor }}55;">
                @endif
                    @if(!empty($bs['custom_branding_logo']))
                    <img src="{{ $bs['custom_branding_logo'] }}" class="w-4 h-4 rounded object-contain" alt="">
                    @endif
                    <span class="text-xs">{{ $bs['custom_branding_text'] }}</span>
                @if(!empty($bs['custom_branding_url']))
                </a>
                @else
                </span>
                @endif
            </div>
            @else
            <p class="text-center text-xs mt-3" style="color: {{ $fontColor }}33; grid-column: 1 / -1;">Powered by 1INME</p>
            @endif

            @if(!$__ccIsOwner && \App\Modules\Common\Support\CookieConsentConfig::shouldRender('biolink'))
                @php
                    $__ccCfgBio = \App\Modules\Common\Support\CookieConsentConfig::get();
                    $__ccCopyBio = \App\Modules\Common\Support\CookieConsentConfig::copyFor($__ccCfgBio);
                    $__ccPolicyBio = $__ccCopyBio['policy_link_url'] ?? '/cookies';
                    $__ccReopenBio = $__ccCopyBio['reopen_link_label'] ?? 'Cookie preferences';
                @endphp
                <p class="text-center text-xs mt-3" style="grid-column: 1 / -1;">
                    <a href="{{ $__ccPolicyBio }}"
                       class="cc-footer-link"
                       style="color: {{ $fontColor }}66;"
                       aria-label="{{ $__ccReopenBio }}"
                       onclick="if(window.openCookiePreferences){return window.openCookiePreferences(event);}">
                        {{ $__ccReopenBio }}
                    </a>
                </p>
            @endif
        @endif
    </div>

    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script>
    // Live poll component: posts the picked option to the JSON poll-vote
    // endpoint and then fetches the aggregated tallies so viewers see
    // counts/bars instead of a generic "Thanks!" message.
    function biolinkPoll(opts) {
        return {
            alias: opts.alias,
            blockId: opts.blockId,
            options: Array.isArray(opts.options) ? opts.options : [],
            voted: null,
            submitting: null,
            results: null,
            error: '',
            // Reveal-at deadline: when set + still in the future, the
            // /poll-results endpoint refuses tallies (even for voters),
            // so we surface a "Results visible after <date>" line.
            revealAt: opts.revealAt || null,
            resultsLocked: false,
            revealAtDisplay: '',
            init() {
                if (this.revealAt) {
                    const d = new Date(this.revealAt);
                    if (!Number.isNaN(d.getTime())) {
                        this.revealAtDisplay = d.toLocaleString();
                        if (d.getTime() > Date.now()) this.resultsLocked = true;
                    }
                }
            },
            async vote(i, label) {
                if (this.submitting !== null) return;
                this.submitting = i;
                this.error = '';
                try {
                    const r = await fetch(`/api/v1/biolinks/${encodeURIComponent(this.alias)}/blocks/${this.blockId}/poll-vote`, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({ option_index: i, option_label: typeof label === 'string' ? label : null }),
                    });
                    if (!r.ok) throw new Error('vote failed');
                    this.voted = i;
                    await this.loadResults();
                } catch (e) {
                    this.error = 'Could not save your vote. Please try again.';
                } finally {
                    this.submitting = null;
                }
            },
            async loadResults() {
                try {
                    const r = await fetch(`/api/v1/biolinks/${encodeURIComponent(this.alias)}/blocks/${this.blockId}/poll-results`, {
                        method: 'GET',
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json' },
                    });
                    if (r.status === 403) {
                        // Either the creator has hidden tallies until the
                        // viewer votes, or the reveal-at deadline hasn't
                        // passed yet. Inspect the envelope to tell them apart.
                        let body = null;
                        try { body = await r.json(); } catch (_) { /* noop */ }
                        const code = body && body.error && body.error.code;
                        const details = (body && body.error && body.error.details) || {};
                        if (code === 'results_locked' && details.reveal_at) {
                            this.revealAt = details.reveal_at;
                            const d = new Date(details.reveal_at);
                            if (!Number.isNaN(d.getTime())) this.revealAtDisplay = d.toLocaleString();
                            this.resultsLocked = true;
                            this.error = '';
                            return;
                        }
                        this.error = 'Vote to see results';
                        return;
                    }
                    if (!r.ok) return;
                    const json = await r.json();
                    if (json && json.data && Array.isArray(json.data.options)) {
                        this.results = json.data;
                    }
                } catch (_) {
                    /* swallow — vote is already recorded */
                }
            },
        };
    }

    function countdown(target) {
        return {
            days: 0, hours: 0, minutes: 0, seconds: 0, interval: null,
            start() {
                if (!target) return;
                const end = new Date(target).getTime();
                this.interval = setInterval(() => {
                    const now = Date.now();
                    const diff = Math.max(0, end - now);
                    this.days = Math.floor(diff / 86400000);
                    this.hours = Math.floor((diff % 86400000) / 3600000);
                    this.minutes = Math.floor((diff % 3600000) / 60000);
                    this.seconds = Math.floor((diff % 60000) / 1000);
                    if (diff <= 0) clearInterval(this.interval);
                }, 1000);
            }
        }
    }
    </script>

    @if($mbEnabled && !$mbIsFloating && $mbPos === 'bottom')
    @php $mbItems = $menuBarSettings['items'] ?? []; @endphp
    <nav class="biolink-menu-bar" style="margin-top: auto; align-self: stretch;">
        @foreach($mbItems as $mi)
            @if(!empty($mi['label']) && ($mi['is_active'] ?? true))
                @if(($mi['target'] ?? '_self') === 'tab' && !empty($mi['id']))
                    <a href="#{{ $mi['id'] }}" data-biolink-tab="{{ $mi['id'] }}" onclick="return biolinkSwitchTab(event, '{{ $mi['id'] }}')">{{ $mi['label'] }}</a>
                @elseif(!empty($mi['url']))
                    <a href="{{ $mi['url'] }}" target="{{ $mi['target'] ?? '_self' }}" @if(($mi['target'] ?? '_self') === '_blank') rel="noopener" @endif
                       class="{{ request()->url() === url($mi['url']) ? 'active' : '' }}">{{ $mi['label'] }}</a>
                @endif
            @endif
        @endforeach
    </nav>
    @endif

    @if($mbEnabled && $mbIsFloating)
    @php $mbItems = $menuBarSettings['items'] ?? []; @endphp
    <div class="menu-fab" id="menuFab">
        <button type="button" class="menu-fab-btn" id="menuFabBtn" onclick="toggleMenuOverlay()">
            <span class="bar1"></span>
            <span class="bar2"></span>
            <span class="bar3"></span>
        </button>
    </div>
    <div class="menu-overlay-backdrop" id="menuBackdrop" onclick="toggleMenuOverlay()"></div>
    <div class="menu-overlay-panel" id="menuPanel">
        @foreach($mbItems as $mi)
            @if(!empty($mi['label']) && ($mi['is_active'] ?? true))
                @if(($mi['target'] ?? '_self') === 'tab' && !empty($mi['id']))
                    <a href="#{{ $mi['id'] }}" data-biolink-tab="{{ $mi['id'] }}" onclick="toggleMenuOverlay(); return biolinkSwitchTab(event, '{{ $mi['id'] }}')">
                        <span class="menu-dot"></span>
                        {{ $mi['label'] }}
                    </a>
                @elseif(!empty($mi['url']))
                    <a href="{{ e($mi['url']) }}" target="{{ $mi['target'] ?? '_self' }}" @if(($mi['target'] ?? '_self') === '_blank') rel="noopener" @endif
                       class="{{ request()->url() === url($mi['url']) ? 'active' : '' }}">
                        <span class="menu-dot"></span>
                        {{ $mi['label'] }}
                    </a>
                @endif
            @endif
        @endforeach
    </div>
    <script>
    function toggleMenuOverlay() {
        var btn = document.getElementById('menuFabBtn');
        var panel = document.getElementById('menuPanel');
        var backdrop = document.getElementById('menuBackdrop');
        var isOpen = panel.classList.contains('open');
        btn.classList.toggle('open', !isOpen);
        panel.classList.toggle('open', !isOpen);
        backdrop.classList.toggle('open', !isOpen);
    }
    </script>
    @endif

    @if($sbEnabled)
    @php
        $showQr = $shareBtnSettings['show_qr'] ?? true;
        $sbLabel = $shareBtnSettings['label'] ?? 'Share';
        $sbStyleType = $shareBtnSettings['style'] ?? 'fab';
        $qrSize = $shareBtnSettings['qr_size'] ?? 200;
        $qrFg = urlencode($shareBtnSettings['qr_fg_color'] ?? '#000000');
        $qrBg = urlencode($shareBtnSettings['qr_bg_color'] ?? '#ffffff');
        $shareUrl = request()->url();
        $qrApiUrl = "https://api.qrserver.com/v1/create-qr-code/?size={$qrSize}x{$qrSize}&data=" . urlencode($shareUrl) . "&color=" . ltrim($qrFg, '%23') . "&bgcolor=" . ltrim($qrBg, '%23');
    @endphp
    <div class="share-fab" id="shareFab">
        @if($sbStyleType === 'bar')
        <button type="button" onclick="document.getElementById('sharePopup').classList.toggle('open')" title="{{ $sbLabel }}"
                style="display:flex;align-items:center;gap:8px;padding:10px 20px;border-radius:12px;background:{{ $sbColor }};color:{{ $sbTextColor }};border:none;cursor:pointer;font-size:13px;font-weight:600;box-shadow:0 8px 30px {{ $sbColor }}40;transition:all 0.3s;">
            <i class="fas fa-share-alt"></i>
            <span>{{ $sbLabel }}</span>
        </button>
        @elseif($sbStyleType === 'icon')
        <button type="button" onclick="document.getElementById('sharePopup').classList.toggle('open')" title="{{ $sbLabel }}"
                style="background:transparent;border:none;cursor:pointer;color:{{ $sbColor }};font-size:{{ $sbIconSize }};padding:8px;transition:all 0.3s;opacity:0.7;"
                onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.7'">
            <i class="fas fa-share-alt"></i>
        </button>
        @else
        <button type="button" class="share-fab-btn" onclick="document.getElementById('sharePopup').classList.toggle('open')" title="{{ $sbLabel }}">
            <i class="fas fa-share-alt"></i>
        </button>
        @endif
        <div class="share-popup" id="sharePopup">
            @if($showQr)
            <div style="text-align:center; margin-bottom: 12px;">
                <img src="{{ $qrApiUrl }}" alt="QR Code" style="width: {{ min($qrSize, 200) }}px; height: {{ min($qrSize, 200) }}px; border-radius: 8px; margin: 0 auto;">
                <p style="font-size: 10px; margin-top: 6px; opacity: 0.5;">Scan to visit</p>
            </div>
            @endif
            <div style="margin-bottom: 10px;">
                <div style="display: flex; gap: 6px; align-items: center;">
                    <input type="text" value="{{ $shareUrl }}" readonly id="shareUrlInput" style="flex: 1; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 7px 10px; font-size: 11px; color: #fff; outline: none; min-width: 0;">
                    <button type="button" onclick="navigator.clipboard.writeText('{{ $shareUrl }}'); this.innerHTML='<i class=\'fas fa-check\'></i>'; setTimeout(() => this.innerHTML='<i class=\'fas fa-copy\'></i>', 1500);"
                            style="background: rgba(255,255,255,0.08); border: none; border-radius: 8px; padding: 7px 10px; color: #fff; cursor: pointer; font-size: 13px;">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
            </div>
            <div class="share-popup-actions">
                <a href="https://twitter.com/intent/tweet?url={{ urlencode($shareUrl) }}" target="_blank" rel="noopener" title="Twitter"><i class="fab fa-x-twitter"></i></a>
                <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($shareUrl) }}" target="_blank" rel="noopener" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                <a href="https://wa.me/?text={{ urlencode($shareUrl) }}" target="_blank" rel="noopener" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                <a href="https://t.me/share/url?url={{ urlencode($shareUrl) }}" target="_blank" rel="noopener" title="Telegram"><i class="fab fa-telegram"></i></a>
                <a href="mailto:?body={{ urlencode($shareUrl) }}" title="Email"><i class="fas fa-envelope"></i></a>
            </div>
        </div>
    </div>
    <script>
    document.addEventListener('click', function(e) {
        var fab = document.getElementById('shareFab');
        if (fab && !fab.contains(e.target)) {
            document.getElementById('sharePopup').classList.remove('open');
        }
    });
    </script>
    @endif

    @if($atEnabled)
    @php
        $atDefaultLang = $autoTranslateSettings['default_lang'] ?? 'en';
        $atLangs = $autoTranslateSettings['languages'] ?? 'en,es,fr,de,pt,ja,ko,zh-CN,ar,hi,tr,ru';
        $atLangList = array_filter(array_map('trim', explode(',', $atLangs)));
        $langNames = [
            'en' => 'English', 'es' => 'Spanish', 'fr' => 'French', 'de' => 'German',
            'pt' => 'Portuguese', 'it' => 'Italian', 'nl' => 'Dutch', 'ru' => 'Russian',
            'ja' => 'Japanese', 'ko' => 'Korean', 'zh-CN' => 'Chinese (Simplified)',
            'zh-TW' => 'Chinese (Traditional)', 'ar' => 'Arabic', 'hi' => 'Hindi',
            'tr' => 'Turkish', 'th' => 'Thai', 'vi' => 'Vietnamese', 'id' => 'Indonesian',
            'ms' => 'Malay', 'pl' => 'Polish', 'sv' => 'Swedish', 'da' => 'Danish',
            'no' => 'Norwegian', 'fi' => 'Finnish', 'el' => 'Greek', 'cs' => 'Czech',
            'ro' => 'Romanian', 'hu' => 'Hungarian', 'uk' => 'Ukrainian', 'he' => 'Hebrew',
            'bn' => 'Bengali',
        ];
        $langFlags = [
            'en' => '🇬🇧', 'es' => '🇪🇸', 'fr' => '🇫🇷', 'de' => '🇩🇪', 'pt' => '🇧🇷',
            'it' => '🇮🇹', 'nl' => '🇳🇱', 'ru' => '🇷🇺', 'ja' => '🇯🇵', 'ko' => '🇰🇷',
            'zh-CN' => '🇨🇳', 'zh-TW' => '🇹🇼', 'ar' => '🇸🇦', 'hi' => '🇮🇳',
            'tr' => '🇹🇷', 'th' => '🇹🇭', 'vi' => '🇻🇳', 'id' => '🇮🇩',
            'ms' => '🇲🇾', 'pl' => '🇵🇱', 'sv' => '🇸🇪', 'da' => '🇩🇰',
            'no' => '🇳🇴', 'fi' => '🇫🇮', 'el' => '🇬🇷', 'cs' => '🇨🇿',
            'ro' => '🇷🇴', 'hu' => '🇭🇺', 'uk' => '🇺🇦', 'he' => '🇮🇱', 'bn' => '🇧🇩',
        ];
        $atWidgetStyle = $autoTranslateSettings['style'] ?? 'dropdown';
    @endphp
    <div class="translate-widget" id="translateWidget">
        <button type="button" class="translate-toggle" onclick="var w=document.getElementById('translateWidget');w.classList.toggle('open');document.getElementById('translateDropdown').classList.toggle('open')">
            <span class="globe-icon"><i class="fas fa-globe"></i></span>
            @if($atWidgetStyle !== 'minimal')
            <span id="currentLangLabel">{{ $langNames[$atDefaultLang] ?? 'English' }}</span>
            @endif
            <i class="fas fa-chevron-down chevron"></i>
        </button>
        <div class="translate-dropdown" id="translateDropdown">
            @foreach($atLangList as $lc)
                @if(preg_match('/^[a-z]{2}(-[A-Z]{2,3})?$/', $lc))
                <a href="#" class="translate-lang-link {{ $lc === $atDefaultLang ? 'active' : '' }}"
                   data-lang="{{ e($lc) }}">
                    @if(isset($langFlags[$lc]))
                        <span class="lang-flag">{{ $langFlags[$lc] }}</span>
                    @endif
                    {{ $langNames[$lc] ?? $lc }}
                </a>
                @endif
            @endforeach
        </div>
    </div>
    <script>
    (function() {
        var defaultLang = '{{ e($atDefaultLang) }}';
        var langNames = @json($langNames);
        var validPattern = /^[a-z]{2}(-[A-Z]{2,3})?$/;

        document.addEventListener('click', function(e) {
            var tw = document.getElementById('translateWidget');
            if (tw && !tw.contains(e.target)) {
                tw.classList.remove('open');
                document.getElementById('translateDropdown').classList.remove('open');
            }

            var langLink = e.target.closest('.translate-lang-link');
            if (langLink) {
                e.preventDefault();
                var lang = langLink.getAttribute('data-lang');
                if (!lang || !validPattern.test(lang)) return;

                document.getElementById('translateWidget').classList.remove('open');
                document.getElementById('translateDropdown').classList.remove('open');
                var label = document.getElementById('currentLangLabel');

                if (lang === defaultLang) {
                    var iframe = document.querySelector('.goog-te-banner-frame');
                    if (iframe) iframe.remove();
                    document.cookie = 'googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
                    document.cookie = 'googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; domain=.' + location.hostname;
                    location.reload();
                    return;
                }

                document.cookie = 'googtrans=/' + defaultLang + '/' + lang + '; path=/;';
                document.cookie = 'googtrans=/' + defaultLang + '/' + lang + '; path=/; domain=.' + location.hostname;

                if (!document.getElementById('google_translate_element')) {
                    var div = document.createElement('div');
                    div.id = 'google_translate_element';
                    div.style.display = 'none';
                    document.body.appendChild(div);
                    var script = document.createElement('script');
                    script.src = 'https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit';
                    document.body.appendChild(script);
                    window.googleTranslateElementInit = function() {
                        new google.translate.TranslateElement({
                            pageLanguage: defaultLang,
                            autoDisplay: false,
                            layout: google.translate.TranslateElement.InlineLayout.SIMPLE
                        }, 'google_translate_element');
                    };
                } else {
                    location.reload();
                }

                document.querySelectorAll('.translate-lang-link').forEach(function(a) { a.classList.remove('active'); });
                langLink.classList.add('active');
                if (label && langNames[lang]) label.textContent = langNames[lang];
            }
        });
    })();
    </script>
    <style>
    .goog-te-banner-frame, .skiptranslate { display: none !important; }
    body { top: 0 !important; }
    .goog-te-gadget { font-size: 0 !important; }
    </style>
    @endif

    @include('common.partials.pixel-scripts', ['link' => $link])

    @include('common.partials.cookie-consent', ['surface' => 'biolink', 'isOwner' => $__ccIsOwner])

    <script>
    (function() {
        var params = new URLSearchParams(window.location.search);
        var editBlockId = params.get('_editBlock');
        if (editBlockId) {
            setTimeout(function() {
                var el = document.querySelector('[data-block-id="' + editBlockId + '"]');
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    el.style.transition = 'outline 0.3s ease, outline-offset 0.3s ease';
                    el.style.outline = '2px solid rgba(139,92,246,0.6)';
                    el.style.outlineOffset = '4px';
                    el.style.borderRadius = '12px';
                    setTimeout(function() {
                        el.style.outline = '2px solid rgba(139,92,246,0.25)';
                    }, 1500);
                }
            }, 500);
        }
    })();
    </script>
    @if($bgType === 'slideshow' && count($slideshowImages) > 1)
    <script>
    (function(){
        var imgs = document.querySelectorAll('.bg-slideshow img');
        if(imgs.length < 2) return;
        var idx = 0;
        setInterval(function(){
            imgs[idx].classList.remove('active');
            idx = (idx + 1) % imgs.length;
            imgs[idx].classList.add('active');
        }, {{ $slideshowInterval * 1000 }});
    })();
    </script>
    @endif

    @if($bgType === 'template' && $bgTemplate && $bgTemplate->js)
    <script>
    (function(){
        var container = document.querySelector('.bg-template-{{ $bgTemplate->slug }}');
        if(!container) return;
        {!! $bgTemplate->js !!}
    })();
    </script>
    @endif

    @if(!empty($bs['custom_js_body']))
    <script>{!! $bs['custom_js_body'] !!}</script>
    @endif

    {{-- Engagement tracking: page session + per-block dwell time --}}
    <script>
    (function(){
        var ALIAS = @json($link->alias);
        var startUrl = '/' + ALIAS + '/track/session';
        var hbUrl    = '/' + ALIAS + '/track/heartbeat';
        var sessionId = null;
        var sessionStart = Date.now();
        var lastActive = Date.now();
        var totalActiveMs = 0;
        var hidden = false;
        var blockState = {}; // id -> {type, totalMs, impressions, visibleSince}
        var pendingFlush = {}; // id -> {addedMs, addedImpr, type}

        function now(){ return Date.now(); }
        function activeSeconds(){ return Math.floor(totalActiveMs / 1000); }

        function tickActive(){
            if(!hidden){
                var n = now();
                totalActiveMs += (n - lastActive);
                lastActive = n;
            }
        }

        function snapshotPending(final){
            tickActive();
            // close any currently visible blocks for snapshot
            Object.keys(blockState).forEach(function(id){
                var b = blockState[id];
                if(b.visibleSince && !hidden){
                    var dur = now() - b.visibleSince;
                    b.totalMs += dur;
                    if(!pendingFlush[id]) pendingFlush[id] = {addedMs:0, addedImpr:0, type:b.type};
                    pendingFlush[id].addedMs += dur;
                    b.visibleSince = now();
                }
            });
            var blockViews = Object.keys(pendingFlush).filter(function(id){
                var p = pendingFlush[id];
                return (p.addedMs > 0) || (p.addedImpr > 0);
            }).map(function(id){
                var p = pendingFlush[id];
                return {block_id: parseInt(id,10), block_type: p.type, view_duration_ms: p.addedMs|0, impression_count: p.addedImpr|0};
            });
            pendingFlush = {};
            return blockViews;
        }

        function sendHeartbeat(final){
            if(!sessionId) return;
            var blockViews = snapshotPending(final);
            var payload = JSON.stringify({
                session_id: sessionId,
                duration_seconds: activeSeconds(),
                ended: !!final,
                block_views: blockViews
            });
            try {
                if(final && navigator.sendBeacon){
                    var blob = new Blob([payload], {type: 'application/json'});
                    navigator.sendBeacon(hbUrl, blob);
                } else {
                    fetch(hbUrl, {method:'POST', headers:{'Content-Type':'application/json'}, body: payload, keepalive: true});
                }
            } catch(e){}
        }

        function startSession(){
            fetch(startUrl, {method:'POST', headers:{'Content-Type':'application/json'}, body: '{}'})
                .then(function(r){ return r.json(); })
                .then(function(d){ if(d && d.session_id){ sessionId = d.session_id; setupObserver(); } })
                .catch(function(){});
        }

        function setupObserver(){
            if(!('IntersectionObserver' in window)) return;
            var io = new IntersectionObserver(function(entries){
                entries.forEach(function(en){
                    var el = en.target;
                    var id = el.getAttribute('data-block-id');
                    var type = el.getAttribute('data-block-type') || '';
                    if(!id) return;
                    if(!blockState[id]) blockState[id] = {type:type, totalMs:0, impressions:0, visibleSince:null};
                    var b = blockState[id];
                    if(en.isIntersecting && en.intersectionRatio >= 0.5){
                        if(!b.visibleSince){
                            b.visibleSince = now();
                            b.impressions += 1;
                            if(!pendingFlush[id]) pendingFlush[id] = {addedMs:0, addedImpr:0, type:type};
                            pendingFlush[id].addedImpr += 1;
                        }
                    } else {
                        if(b.visibleSince){
                            var dur = now() - b.visibleSince;
                            b.totalMs += dur;
                            if(!pendingFlush[id]) pendingFlush[id] = {addedMs:0, addedImpr:0, type:type};
                            pendingFlush[id].addedMs += dur;
                            b.visibleSince = null;
                        }
                    }
                });
            }, {threshold: [0, 0.5, 1]});
            document.querySelectorAll('[data-block-id]').forEach(function(el){ io.observe(el); });
        }

        document.addEventListener('visibilitychange', function(){
            tickActive();
            hidden = document.hidden;
            if(hidden){
                // pause all visible blocks
                Object.keys(blockState).forEach(function(id){
                    var b = blockState[id];
                    if(b.visibleSince){
                        var dur = now() - b.visibleSince;
                        b.totalMs += dur;
                        if(!pendingFlush[id]) pendingFlush[id] = {addedMs:0, addedImpr:0, type:b.type};
                        pendingFlush[id].addedMs += dur;
                        b.visibleSince = null;
                    }
                });
                sendHeartbeat(false);
            } else {
                lastActive = now();
            }
        });

        ['mousemove','keydown','scroll','touchstart','click'].forEach(function(ev){
            window.addEventListener(ev, function(){ tickActive(); lastActive = now(); }, {passive:true});
        });

        window.addEventListener('pagehide', function(){ sendHeartbeat(true); });
        window.addEventListener('beforeunload', function(){ sendHeartbeat(true); });

        setInterval(function(){ sendHeartbeat(false); }, 15000);

        if(document.readyState === 'complete' || document.readyState === 'interactive') startSession();
        else document.addEventListener('DOMContentLoaded', startSession);
    })();
    </script>

    <script>
    (function(){
        function applyTab(tabId) {
            tabId = tabId || '';
            document.body.setAttribute('data-active-tab', tabId);
            document.querySelectorAll('.biolink-block-wrap').forEach(function(el){
                var bt = el.getAttribute('data-tab') || '';
                el.style.display = (bt === tabId) ? '' : 'none';
            });
            document.querySelectorAll('[data-biolink-tab]').forEach(function(btn){
                btn.classList.toggle('active', btn.getAttribute('data-biolink-tab') === tabId);
            });
        }
        window.biolinkSwitchTab = function(ev, tabId) {
            if (ev) { ev.preventDefault(); ev.stopPropagation(); }
            var current = document.body.getAttribute('data-active-tab') || '';
            applyTab(current === tabId ? '' : tabId);
            try { history.replaceState(null, '', tabId ? ('#' + tabId) : window.location.pathname + window.location.search); } catch(e){}
            return false;
        };
        function init() {
            if (!document.querySelector('[data-biolink-tab]')) return;
            var initial = '';
            var hash = (window.location.hash || '').replace(/^#/, '');
            if (hash && document.querySelector('[data-biolink-tab="' + CSS.escape(hash) + '"]')) {
                initial = hash;
            }
            applyTab(initial);
        }
        if (document.readyState === 'complete' || document.readyState === 'interactive') init();
        else document.addEventListener('DOMContentLoaded', init);
    })();
    </script>

    @php
        $modalCreatorId = $__creator?->id;
        $modalAccent    = $fontColor;
        $modalBgPanel   = $bs['background_color'] ?? '#0f172a';
        $viewerInitial  = $__viewer ? ['id'=>$__viewer->id,'name'=>$__viewer->name,'email'=>$__viewer->email,'avatar'=>$__viewer->avatar] : null;
    @endphp
    @include('common.partials.viewer-login-modal', compact('modalCreatorId','modalAccent','modalBgPanel','viewerInitial'))

    {{-- Opt-in "Open in app" button. We deliberately do NOT auto-redirect:
         on Android the Custom Tabs handoff causes a visible flicker, and on
         iOS an unprompted scheme attempt looks like a malware prompt. The
         button is only useful for users who already have the app installed
         and somehow ended up on the web page; everyone else just ignores it. --}}
    <button type="button" id="oneinmeOpenInAppBtn"
            style="position:fixed;left:50%;bottom:18px;transform:translateX(-50%);z-index:120;
                   display:none;background:rgba(15,15,25,0.85);color:#fff;border:1px solid rgba(255,255,255,0.18);
                   backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);
                   padding:9px 16px;border-radius:9999px;font:500 13px/1 'Space Grotesk',sans-serif;
                   cursor:pointer;box-shadow:0 8px 24px rgba(0,0,0,0.4);"
            onclick="(function(){
                var alias = @json($link->alias);
                var fallback = window.location.href;
                var t = Date.now();
                window.location.href = '1inme://biolink/' + encodeURIComponent(alias);
                setTimeout(function(){
                  // If we're still here after 1.5s the scheme didn't resolve;
                  // hide the button so we don't pester the user further.
                  if (Date.now() - t < 2500 && !document.hidden) {
                    var b = document.getElementById('oneinmeOpenInAppBtn');
                    if (b) b.style.display = 'none';
                    try { localStorage.setItem('1inme.no_app', '1'); } catch(_){}
                  }
                }, 1500);
            })()">
        Open in 1INME app
    </button>
    <script>
        (function(){
            // Mobile-only and only when the user hasn't previously dismissed.
            var ua = navigator.userAgent || '';
            var isMobile = /Android|iPhone|iPad|iPod/i.test(ua);
            var dismissed = false;
            try { dismissed = localStorage.getItem('1inme.no_app') === '1'; } catch(_){}
            if (isMobile && !dismissed) {
                var b = document.getElementById('oneinmeOpenInAppBtn');
                if (b) b.style.display = 'inline-block';
            }
        })();
    </script>
    {{-- AR Business Card fallback notice. When /ar/{alias} can't activate AR
         on the visitor's device it redirects here with ?ar=unsupported so the
         scan still lands somewhere useful; we surface a brief toast so the
         visitor understands why they didn't see the AR card. --}}
    <script>
        (function () {
            try {
                var qs = new URLSearchParams(location.search);
                if (qs.get('ar') !== 'unsupported') return;
                var msg = "AR isn't supported on this device or browser — here's the standard biolink instead.";
                var t = document.createElement('div');
                t.setAttribute('role', 'status');
                t.style.cssText = 'position:fixed;left:50%;bottom:22px;transform:translateX(-50%);'
                    + 'background:rgba(15,23,42,.94);color:#f8fafc;padding:11px 16px;'
                    + 'border-radius:12px;font-size:12.5px;line-height:1.45;'
                    + 'border:1px solid rgba(255,255,255,.12);box-shadow:0 8px 24px rgba(0,0,0,.35);'
                    + 'max-width:88vw;text-align:center;z-index:99999;font-family:inherit;';
                t.textContent = msg;
                document.body.appendChild(t);
                setTimeout(function () {
                    t.style.transition = 'opacity .4s ease';
                    t.style.opacity = '0';
                    setTimeout(function () { t.remove(); }, 450);
                }, 6000);
                // Strip the hint params so a refresh doesn't re-show the toast.
                if (history && history.replaceState) {
                    qs.delete('ar'); qs.delete('reason');
                    var clean = location.pathname + (qs.toString() ? ('?' + qs.toString()) : '') + location.hash;
                    history.replaceState(null, '', clean);
                }
            } catch (e) {}
        })();
    </script>
    @include('common.blocks._carbon_badge', ['link' => $link])
</body>
</html>
