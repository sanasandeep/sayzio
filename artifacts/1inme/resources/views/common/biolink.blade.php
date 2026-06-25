@php
    $metaSettings = $link->settings['biolink']['meta'] ?? [];
    $ogSettings = $link->settings['biolink']['og'] ?? [];
    $twSettings = $link->settings['biolink']['twitter'] ?? [];
    $manifestSettings = $link->settings['biolink']['manifest'] ?? [];
    $faviconSettings = $link->settings['biolink']['favicons'] ?? [];
    $shareBtnSettings = $link->settings['biolink']['share_button'] ?? [];
    $menuBarSettings = $link->settings['biolink']['menu_bar'] ?? [];
    $autoTranslateSettings = $link->settings['biolink']['auto_translate'] ?? [];
    $pageTitle = $link->seo_title ?? $metaSettings['seo_title'] ?? $link->title ?? 'Sayzio Link in Bio';
    $pageDesc = $link->seo_description ?? $metaSettings['seo_description'] ?? '';
    $pageImage = $link->seo_image ?? $ogSettings['image_url'] ?? '';
    $ogTitle = $ogSettings['title'] ?? $pageTitle;
    $ogDesc = $ogSettings['description'] ?? $pageDesc;
    $ogType = $ogSettings['type'] ?? 'website';
    $ogSiteName = $ogSettings['site_name'] ?? 'Sayzio';
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
        <meta name="theme-color" content="{{ $manifestSettings['theme_color'] ?? '#3d6bff' }}">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        @if(!empty($manifestSettings['short_name']))
            <meta name="apple-mobile-web-app-title" content="{{ $manifestSettings['short_name'] }}">
        @endif
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
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
        $btnColor = $bs['button_color'] ?? '#3d6bff';
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
            'website' => ['fas fa-globe', '#3d6bff'],
            'email' => ['fas fa-envelope', '#3d6bff'],
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
    {{-- JS challenge cookie used by VisitorRateLimiter to give visitors
         that actually executed JS a much higher rate-limit budget than
         scripts that ignore JS. Set as early as possible so even a fast
         second-hit reload sees it. --}}
    <script>(function(){try{if(!document.cookie.match(/(?:^|; )1inme_human=1/)){document.cookie='1inme_human=1; path=/; max-age=2592000; SameSite=Lax';}}catch(e){}})();</script>
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
            $mbActive = $menuBarSettings['active_color'] ?? '#3d6bff';
            $mbStyle = $menuBarSettings['style'] ?? 'pills';
            $mbIconColor = $menuBarSettings['icon_color'] ?? '#ffffff';
            $mbOverlayBg = $menuBarSettings['overlay_bg'] ?? '#0a0612';
            $sbEnabled = !empty($shareBtnSettings['enabled']);
            $sbColor = $shareBtnSettings['color'] ?? '#3d6bff';
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
        .translate-dropdown a.active { background: rgba(61,107,255,0.2); font-weight: 600; }
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
            position: relative;
            z-index: 1;
        }
        /* Keep the fixed bg-template layer behind everything else. */
        .bg-template.bg-layer { z-index: 0 !important; }
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
        /* Task #1041: heading animation hooks driven by data-anim. Each
           variant in BlockVariantCatalog::heading_styles emits one of
           these slugs; renderers stay generic. Reduced-motion users opt
           out of the moving variants and just get the static look. */
        [data-anim] h2, [data-anim] h1, [data-anim] h3 { display: inline-block; }
        [data-anim="gradient_swipe"] h1, [data-anim="gradient_swipe"] h2, [data-anim="gradient_swipe"] h3 {
            background: linear-gradient(110deg, #5c83ff, #ec4899, #f59e0b, #5c83ff);
            background-size: 300% 100%; -webkit-background-clip: text; background-clip: text; color: transparent;
            animation: anim-gradient-swipe 6s linear infinite;
        }
        @keyframes anim-gradient-swipe { 0% { background-position: 0% 50%; } 100% { background-position: 300% 50%; } }
        [data-anim="neon_glitch"] h1, [data-anim="neon_glitch"] h2, [data-anim="neon_glitch"] h3 {
            color: #fff; text-shadow: 0 0 6px #6e61ff, 0 0 14px #6e61ff, 0 0 28px #ec4899;
            animation: anim-neon-glitch 2.4s steps(20, end) infinite;
        }
        @keyframes anim-neon-glitch { 0%,90%,100% { transform: translateX(0); } 92% { transform: translateX(-2px) skewX(-3deg); } 94% { transform: translateX(2px) skewX(2deg); } 96% { transform: translateX(-1px); } }
        [data-anim="typewriter"] h1, [data-anim="typewriter"] h2, [data-anim="typewriter"] h3 {
            font-family: ui-monospace, "SF Mono", Menlo, monospace; overflow: hidden; white-space: nowrap;
            border-right: 2px solid currentColor; animation: anim-typewriter 3.6s steps(40, end) 1 both, anim-blink 0.9s step-end infinite;
        }
        @keyframes anim-typewriter { from { max-width: 0; } to { max-width: 100%; } }
        @keyframes anim-blink { 50% { border-color: transparent; } }
        [data-anim="wave_letters"] h1, [data-anim="wave_letters"] h2, [data-anim="wave_letters"] h3 {
            animation: anim-wave 2.4s ease-in-out infinite;
        }
        @keyframes anim-wave { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }
        [data-anim="extrude_3d"] h1, [data-anim="extrude_3d"] h2, [data-anim="extrude_3d"] h3 {
            color: #fff; text-shadow: 1px 1px 0 #6366f1, 2px 2px 0 #6366f1, 3px 3px 0 #4338ca, 4px 4px 0 #4338ca, 5px 5px 8px rgba(0,0,0,0.35);
        }
        [data-anim="ticker_marquee"] { overflow: hidden; }
        [data-anim="ticker_marquee"] h1, [data-anim="ticker_marquee"] h2, [data-anim="ticker_marquee"] h3 {
            white-space: nowrap; animation: anim-marquee 14s linear infinite;
        }
        @keyframes anim-marquee { from { transform: translateX(100%); } to { transform: translateX(-100%); } }
        [data-anim="fade_in"] h1, [data-anim="fade_in"] h2, [data-anim="fade_in"] h3 { animation: anim-fadein 1.2s ease-out 1 both; }
        @keyframes anim-fadein { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
        @media (prefers-reduced-motion: reduce) {
            [data-anim="gradient_swipe"] h1, [data-anim="gradient_swipe"] h2, [data-anim="gradient_swipe"] h3,
            [data-anim="neon_glitch"] h1, [data-anim="neon_glitch"] h2, [data-anim="neon_glitch"] h3,
            [data-anim="wave_letters"] h1, [data-anim="wave_letters"] h2, [data-anim="wave_letters"] h3,
            [data-anim="ticker_marquee"] h1, [data-anim="ticker_marquee"] h2, [data-anim="ticker_marquee"] h3,
            [data-anim="typewriter"] h1, [data-anim="typewriter"] h2, [data-anim="typewriter"] h3,
            [data-anim="fade_in"] h1, [data-anim="fade_in"] h2, [data-anim="fade_in"] h3 {
                animation: none !important; max-width: none !important; border-right: 0 !important; white-space: normal !important;
            }
        }
        /* Gallery layout hooks for image_grid. Selectors use descendant
           combinators so an optional anchor wrapper (`<a class="contents">`
           when the gallery has a click-through) doesn't break the rules. */
        [data-gallery-layout="grid_2"] .grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        [data-gallery-layout="grid_3"] .grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        [data-gallery-layout="grid_4"] .grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        [data-gallery-layout="masonry"] .grid {
            display: block; column-count: 2; column-gap: 0.5rem;
        }
        [data-gallery-layout="masonry"] .grid img { width: 100%; height: auto !important; aspect-ratio: auto !important; margin-bottom: 0.5rem; display: block; break-inside: avoid; }
        [data-gallery-layout="carousel_peek"] .grid {
            display: flex; gap: 0.5rem; overflow-x: auto; scroll-snap-type: x mandatory; padding-bottom: 4px;
        }
        [data-gallery-layout="carousel_peek"] .grid img { flex: 0 0 70%; scroll-snap-align: center; }
        [data-gallery-layout="stacked_polaroids"] .grid {
            display: flex; justify-content: center; gap: 0; padding: 1rem 0;
        }
        [data-gallery-layout="stacked_polaroids"] .grid img {
            background: #fff; padding: 8px 8px 28px; box-shadow: 0 8px 24px rgba(0,0,0,0.3);
            width: 55%; aspect-ratio: 1 / 1;
        }
        [data-gallery-layout="stacked_polaroids"] .grid img:nth-child(odd) { transform: rotate(-4deg) translateX(8%); }
        [data-gallery-layout="stacked_polaroids"] .grid img:nth-child(even) { transform: rotate(5deg) translateX(-8%); }
        [data-gallery-layout="marquee_strip"] { overflow: hidden; }
        [data-gallery-layout="marquee_strip"] .grid {
            display: flex; gap: 0.5rem; width: max-content; animation: anim-marquee-strip 18s linear infinite;
        }
        [data-gallery-layout="marquee_strip"] .grid img { width: 140px; height: 140px; flex: 0 0 auto; }
        @keyframes anim-marquee-strip { from { transform: translateX(0); } to { transform: translateX(-50%); } }
        [data-gallery-layout="lightbox_grid"] .grid img { cursor: zoom-in; transition: transform 0.2s ease; }
        [data-gallery-layout="lightbox_grid"] .grid img:hover { transform: scale(1.04); z-index: 1; position: relative; }
        @media (prefers-reduced-motion: reduce) {
            [data-gallery-layout="marquee_strip"] .grid { animation: none !important; }
        }
        /* Social icon style-set hooks. The renderer emits the brand color
           via inline style; these selectors override or augment it. */
        [data-social-set="mono_line"] a > i { color: currentColor !important; opacity: 0.85; font-weight: 100; }
        [data-social-set="mono_solid"] a { background: rgba(255,255,255,0.06); }
        [data-social-set="mono_solid"] a > i { color: #fff !important; }
        [data-social-set="sketch"] a {
            border: 1.5px dashed rgba(255,255,255,0.6); background: transparent !important; box-shadow: 2px 2px 0 rgba(255,255,255,0.25) !important;
        }
        [data-social-set="brand_color"] a > i { filter: drop-shadow(0 0 6px currentColor); }
        [data-social-set="brand_tiles"] a {
            border-radius: 0.5rem !important; background: currentColor; min-width: 2.75rem;
        }
        [data-social-set="brand_tiles"] a > i { color: #fff !important; mix-blend-mode: normal; }
        [data-social-set="wordmark"] a::after {
            content: attr(aria-label); font-size: 10px; font-weight: 600; margin-left: 6px; text-transform: uppercase; letter-spacing: 0.05em;
        }
        [data-social-set="wordmark"] a { padding: 0 0.6rem; width: auto !important; border-radius: 999px !important; }
        [data-social-set="glassy"] a {
            background: rgba(255,255,255,0.10) !important; backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.18);
        }
        [data-social-set="neon_pop"] a {
            box-shadow: 0 0 0 1px currentColor, 0 0 12px currentColor, 0 0 28px currentColor !important;
        }
        [data-social-set="animated_pulse"] a { animation: anim-social-pulse 2.4s ease-in-out infinite; }
        @keyframes anim-social-pulse { 0%,100% { transform: scale(1); } 50% { transform: scale(1.08); } }
        @media (prefers-reduced-motion: reduce) {
            [data-social-set="animated_pulse"] a { animation: none !important; }
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
            // When an A/B test is running, the renderer reads the assigned
            // variant snapshot (set on the link by RedirectController) instead
            // of the live biolink_blocks rows so each visitor sees a stable
            // layout. We still run the same isVisible() filter so per-block
            // scheduling and geo-targeting work identically across variants.
            $blocks = ($link->_abVariantBlocks instanceof \Illuminate\Support\Collection)
                ? $link->_abVariantBlocks->filter(fn($b) => $b->isVisible())
                : $link->activeBiolinkBlocks()->get()->filter(fn($b) => $b->isVisible());
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

            // Storefront (Task #1761): count native-checkout products so each
            // product renders "Buy Now" (single) vs "Add to Cart" (multiple),
            // and a cart drawer only mounts when at least one exists. Must be
            // computed BEFORE the blocks loop below.
            $__storeProducts = $blocks->filter(fn($b) =>
                $b->type === 'product'
                && !empty(($b->settings['native_checkout'] ?? false))
                && (int)($b->settings['price_cents'] ?? 0) > 0
            );
            $__storeCount    = $__storeProducts->count();
            $__storeMultiple = $__storeCount > 1;
            $__storeAlias    = $link->alias;
            $__storeCreatorId = $creatorOwner?->id;
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
                $skipWrap = in_array($block->type, ['avatar', 'divider', 'spacer', 'social_icons'])
                    || str_starts_with($block->type, 'profile_card')
                    || $isBtnLike;
                $btnInline = ($isBtnLike && $hasCustomStyle) ? $blockInline : '';
            @endphp

            @php
                $gridSpan = intval($blockStyle['grid_span'] ?? 12) ?: 12;
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
                 class="biolink-block-wrap" style="grid-column: span {{ $gridSpan }}">
            @if($_lim && (!empty($_limCfg['show_countdown']) || !empty($_limCfg['show_remaining'])))
                {{-- Badge container — populated/updated by the limits ticker
                     in JS below. Rendered server-side as well so the first
                     paint is correct without a JS round-trip. --}}
                <div class="biolink-limit-badge mb-2 inline-flex items-center gap-1.5 text-[11px] font-semibold px-2 py-1 rounded-md"
                     style="background: rgba(244,63,94,0.14); color: rgba(254,205,211,0.95); border: 1px solid rgba(244,63,94,0.25);"
                     data-badge-for="{{ $block->id }}"></div>
            @endif
            @if($hasCustomStyle && !$skipWrap)<div class="mb-3 block-styled" style="{{ $blockInline }}">@endif

                {{-- Task #2042 — single source of truth: every top-level block
                     renders through the unified dispatch partial, exactly like
                     card/grid children do. No inline @if/@elseif chain here. --}}
                @include('common.partials.biolink-block-render', ['link' => $link, 'block' => $block, 's' => $s, 'fontColor' => $fontColor ?? '#ffffff', 'btnInline' => $btnInline])

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

        @php
            $__combinedFooter = $__creator && !$__isSelf && $__allowFollowers && !$__viewer && empty($bs['custom_branding_text']);
        @endphp
        @if(!$__brandingHidden)
            {{-- Subtle viewer sign-in / follow entry in the branding strip. --}}
            @if($__creator && !$__isSelf && $__allowFollowers && !$__combinedFooter)
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
            @elseif($__combinedFooter)
            <div class="mt-6 flex flex-wrap items-center justify-center gap-x-3 gap-y-2 text-xs" style="grid-column: 1 / -1;">
                <button type="button"
                        @click="$dispatch('open-viewer-login', {creatorId: {{ (int)$__creator->id }} })"
                        class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full font-semibold transition-all hover:scale-105"
                        style="background: {{ $fontColor }}15; color: {{ $fontColor }}cc; border: 1px solid {{ $fontColor }}25;">
                    <i class="fas fa-user-plus text-[10px]"></i>
                    Sign in to follow {{ $__creator->name }}
                </button>
                <span style="color: {{ $fontColor }}33;">|</span>
                <span style="color: {{ $fontColor }}55;">Powered by Sayzio</span>
            </div>
            @else
            <p class="text-center text-xs mt-3" style="color: {{ $fontColor }}33; grid-column: 1 / -1;">Powered by Sayzio</p>
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

    <script defer src="{{ asset('js/vendor/alpine-collapse.min.js') }}"></script>
    <script src="{{ asset('js/vendor/alpine.min.js') }}" defer></script>
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

    @php
        // Per-biolink consent banner (task #1114). We only render this
        // mini-banner when the page owner has opted in AND the visitor
        // isn't the owner themselves. The workspace-wide cookie-consent
        // banner takes priority when it's already going to render for
        // this surface — they share the same script-gating contract
        // (script[type="text/plain"][data-consent-category]) so we don't
        // need to also show our own.
        $__linkPrivacy = $link->settings['biolink']['privacy'] ?? [];
        $__renderLinkConsent = !$__ccIsOwner
            && !empty($__linkPrivacy['consent_banner_enabled'])
            && !\App\Modules\Common\Support\CookieConsentConfig::shouldRender('biolink');
        $__linkConsentText    = $__linkPrivacy['consent_banner_text']   ?? 'This page uses essential cookies to work. With your consent we also load analytics and marketing pixels.';
        $__linkConsentAccept  = $__linkPrivacy['consent_accept_label']  ?? 'Accept';
        $__linkConsentDecline = $__linkPrivacy['consent_decline_label'] ?? 'Decline';
        $__linkConsentCookie  = '1inme_link_consent_' . (int) $link->id;
    @endphp
    @if($__renderLinkConsent)
    <style>
        .link-consent-host {
            position: fixed; left: 12px; right: 12px; bottom: 12px;
            z-index: 2147483600;
            display: none;
            background: rgba(17,24,39,0.96);
            color: #f9fafb;
            border-radius: 14px;
            padding: 14px 16px;
            box-shadow: 0 18px 50px rgba(0,0,0,0.45);
            font-family: 'Space Grotesk', system-ui, sans-serif;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }
        .link-consent-host.is-open { display: flex; }
        .link-consent-host { gap: 14px; align-items: center; flex-wrap: wrap; }
        .link-consent-text { flex: 1; min-width: 220px; font-size: 13px; line-height: 1.5; }
        .link-consent-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .link-consent-btn {
            border: 0; cursor: pointer; font-family: inherit;
            font-weight: 600; font-size: 13px; padding: 8px 14px;
            border-radius: 9999px;
        }
        .link-consent-accept { background: #3d6bff; color: #fff; }
        .link-consent-decline { background: rgba(255,255,255,0.12); color: #f9fafb; }
        @media (min-width: 720px) {
            .link-consent-host { left: auto; right: 18px; bottom: 18px; max-width: 520px; }
        }
    </style>
    <div id="link-consent" class="link-consent-host" role="dialog" aria-live="polite" aria-label="Privacy consent">
        <div class="link-consent-text">{{ $__linkConsentText }}</div>
        <div class="link-consent-actions">
            <button type="button" class="link-consent-btn link-consent-decline" data-consent="reject">{{ $__linkConsentDecline }}</button>
            <button type="button" class="link-consent-btn link-consent-accept" data-consent="accept">{{ $__linkConsentAccept }}</button>
        </div>
    </div>
    <script>
    (function(){
        var COOKIE = @json($__linkConsentCookie);
        var host = document.getElementById('link-consent');
        if (!host) return;

        function readCookie(name) {
            var m = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[-.]/g,'\\$&') + '=([^;]*)'));
            return m ? decodeURIComponent(m[1]) : null;
        }
        function writeCookie(name, value, days) {
            var exp = new Date(Date.now() + days*86400000).toUTCString();
            document.cookie = name + '=' + encodeURIComponent(value) + '; expires=' + exp + '; path=/; SameSite=Lax';
        }
        function upgrade() {
            // Promote any pixel scripts the page rendered as text/plain to
            // text/javascript so they actually execute. Mirrors the
            // contract used by the workspace-wide cookie-consent script.
            document.querySelectorAll('script[type="text/plain"][data-consent-category]').forEach(function(s){
                var fresh = document.createElement('script');
                for (var i = 0; i < s.attributes.length; i++) {
                    var a = s.attributes[i];
                    if (a.name === 'type') continue;
                    fresh.setAttribute(a.name, a.value);
                }
                if (s.src) fresh.src = s.src;
                else fresh.text = s.textContent || '';
                s.parentNode.replaceChild(fresh, s);
            });
        }
        var existing = readCookie(COOKIE);
        if (existing === 'accept') { upgrade(); return; }
        if (existing === 'reject') { return; }
        // No decision yet — show the banner.
        host.classList.add('is-open');
        host.querySelector('[data-consent="accept"]').addEventListener('click', function(){
            writeCookie(COOKIE, 'accept', 180);
            host.classList.remove('is-open');
            upgrade();
        });
        host.querySelector('[data-consent="reject"]').addEventListener('click', function(){
            writeCookie(COOKIE, 'reject', 180);
            host.classList.remove('is-open');
        });
    })();
    </script>
    @endif

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
                    el.style.outline = '2px solid rgba(92,131,255,0.6)';
                    el.style.outlineOffset = '4px';
                    el.style.borderRadius = '12px';
                    setTimeout(function() {
                        el.style.outline = '2px solid rgba(92,131,255,0.25)';
                    }, 1500);
                }
            }, 500);

            // Live preview for profile-card stats/badges: the editor posts the
            // current repeater state as the owner types/reorders, and we rebuild
            // the matching card section in place (no save/reload round-trip).
            window.addEventListener('message', function (e) {
                if (e.origin !== window.location.origin) return;
                var d = e.data;
                if (!d || d.type !== '1inme-pc-live') return;
                var block = document.querySelector('[data-block-id="' + d.blockId + '"]');
                if (!block) return;

                if (Array.isArray(d.stats)) {
                    var sWrap = block.querySelector('[data-pc-stats]');
                    if (sWrap) {
                        var accent = sWrap.getAttribute('data-pc-accent') || '';
                        var rows = d.stats.slice(0, 6).filter(function (st) {
                            return (st && ((st.value || '') !== '' || (st.label || '') !== ''));
                        });
                        if (rows.length === 0) {
                            sWrap.style.display = 'none';
                            sWrap.innerHTML = '';
                        } else {
                            sWrap.style.display = '';
                            sWrap.innerHTML = rows.map(function (st) {
                                var v = esc(st.value != null && st.value !== '' ? st.value : '0');
                                var l = esc(st.label || '');
                                return '<div class="text-center"><p class="font-bold">' + v +
                                    '</p><p class="text-[10px]" style="opacity:.45">' + l + '</p></div>';
                            }).join('');
                        }
                    }
                }

                if (Array.isArray(d.badges)) {
                    var bWrap = block.querySelector('[data-pc-badges]');
                    if (bWrap) {
                        var bAccent = bWrap.getAttribute('data-pc-accent') || '';
                        var labels = d.badges.slice(0, 12).map(function (bd) {
                            return bd && bd.label != null ? String(bd.label) : '';
                        }).filter(function (lbl) { return lbl !== ''; });
                        if (labels.length === 0) {
                            bWrap.style.display = 'none';
                            bWrap.innerHTML = '';
                        } else {
                            bWrap.style.display = '';
                            bWrap.innerHTML = labels.map(function (lbl) {
                                return '<span class="px-3 py-1 rounded-full text-xs" style="background:rgba(61,107,255,0.18);color:' +
                                    bAccent + '">' + esc(lbl) + '</span>';
                            }).join('');
                        }
                    }
                }

                function esc(v) {
                    return String(v).replace(/[&<>"']/g, function (c) {
                        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
                    });
                }
            });
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

    {{-- Engagement tracking: page session + per-block dwell time.
         Per-biolink privacy (task #1114): when the page owner enabled the
         consent banner, treat session + heartbeat + dwell tracking as
         "non-essential analytics" and only run after the visitor has
         accepted. The banner script writes the cookie checked here. --}}
    <script>
    (function(){
        var ALIAS = @json($link->alias);
        var startUrl = '/' + ALIAS + '/track/session';
        var hbUrl    = '/' + ALIAS + '/track/heartbeat';
        var CONSENT_REQUIRED = {!! !empty(($link->settings['biolink']['privacy']['consent_banner_enabled'] ?? false)) ? 'true' : 'false' !!};
        var CONSENT_COOKIE   = @json('1inme_link_consent_' . (int) $link->id);
        function readCookie(name){
            var m = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[-.]/g,'\\$&') + '=([^;]*)'));
            return m ? decodeURIComponent(m[1]) : null;
        }
        function consentGranted(){
            if (!CONSENT_REQUIRED) return true;
            var perLink = readCookie(CONSENT_COOKIE);
            if (perLink === 'accept') return true;
            if (perLink === 'reject') return false;
            // Fall back to workspace consent: when admin enabled the
            // workspace banner, our per-link mini-banner is suppressed
            // and the workspace cookie (1inme_cookie_consent, JSON
            // {v,t,c:{cat:bool}}) governs. Accept covers the analytics
            // category.
            var ws = readCookie('1inme_cookie_consent');
            if (ws) {
                try {
                    var parsed = JSON.parse(ws);
                    if (parsed && parsed.c && parsed.c.analytics) return true;
                } catch (e) {}
            }
            return false;
        }
        if (!consentGranted()) {
            // Re-check on the consent banner's accept click — its handler
            // sets the cookie before re-running pixel upgrades, so we can
            // observe the change with a short MutationObserver-free poll.
            var attempts = 0;
            var poll = setInterval(function(){
                attempts += 1;
                if (consentGranted()) {
                    clearInterval(poll);
                    bootstrap();
                } else if (attempts > 600) { // ~5 minutes, then give up
                    clearInterval(poll);
                }
            }, 500);
            return;
        }
        bootstrap();
        function bootstrap(){
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
        } // end bootstrap()
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

    @if(($__storeCount ?? 0) > 0)
    {{-- In-page storefront: floating cart button + drawer (Task #1761).
         Only mounts when the page has native-checkout products. --}}
    <div x-data x-cloak>
        @if($__storeMultiple)
        <button type="button" @click="$store.bioStore.open = true"
                x-show="$store.bioStore.count > 0"
                class="fixed bottom-5 right-5 z-[50] flex items-center gap-2 px-4 py-3 rounded-full shadow-2xl text-sm font-semibold"
                style="background:{{ $fontColor }}; color:{{ $bs['background_color'] ?? '#0f172a' }};">
            <i class="fas fa-shopping-bag"></i>
            <span x-text="$store.bioStore.count"></span>
        </button>
        @endif

        <div x-show="$store.bioStore.open" class="fixed inset-0 z-[60]" style="display:none;">
            <div class="absolute inset-0 bg-black/60" @click="$store.bioStore.open = false"></div>
            <div class="absolute right-0 top-0 h-full w-full max-w-sm p-5 overflow-y-auto shadow-2xl"
                 style="background:{{ $bs['background_color'] ?? '#0f172a' }}; color:{{ $fontColor }};"
                 @click.stop>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-lg">Your Cart</h3>
                    <button type="button" @click="$store.bioStore.open = false" class="opacity-70 hover:opacity-100"><i class="fas fa-times text-xl"></i></button>
                </div>

                <template x-if="$store.bioStore.items.length === 0">
                    <p class="text-sm opacity-60 py-8 text-center">Your cart is empty.</p>
                </template>

                <div class="space-y-3">
                    <template x-for="it in $store.bioStore.items" :key="it.block_id">
                        <div class="flex items-center gap-3 rounded-xl p-2" style="background:{{ $fontColor }}10;">
                            <template x-if="it.image_url">
                                <img :src="it.image_url" class="w-12 h-12 rounded-lg object-cover" alt="">
                            </template>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium truncate" x-text="it.name"></p>
                                <p class="text-xs opacity-60" x-text="$store.bioStore.money(it.price_cents)"></p>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <button type="button" @click="$store.bioStore.setQty(it, it.quantity - 1)" class="w-6 h-6 rounded-full" style="background:{{ $fontColor }}20;">−</button>
                                <span class="text-sm w-5 text-center" x-text="it.quantity"></span>
                                <button type="button" @click="$store.bioStore.setQty(it, it.quantity + 1)" class="w-6 h-6 rounded-full" style="background:{{ $fontColor }}20;">+</button>
                            </div>
                        </div>
                    </template>
                </div>

                <template x-if="$store.bioStore.items.length > 0">
                    <div class="mt-5 pt-4 border-t" style="border-color:{{ $fontColor }}20;">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-sm opacity-70">Total</span>
                            <span class="font-bold text-lg" x-text="$store.bioStore.money($store.bioStore.total)"></span>
                        </div>
                        <button type="button" @click="$store.bioStore.checkout()" :disabled="$store.bioStore.busy"
                                class="bio-btn block w-full text-center py-3 text-sm font-semibold disabled:opacity-50">
                            <span x-show="!$store.bioStore.busy">Checkout</span>
                            <span x-show="$store.bioStore.busy">Processing…</span>
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('alpine:init', () => {
        Alpine.store('bioStore', {
            open: false,
            busy: false,
            count: 0,
            total: 0,
            currency: 'USD',
            items: [],
            alias: @json($__storeAlias),
            creatorId: {{ (int) ($__storeCreatorId ?? 0) }},
            _csrf() { return document.querySelector('meta[name=csrf-token]')?.content || ''; },
            money(cents) {
                return this.currency + ' ' + (Math.round(cents) / 100).toFixed(2);
            },
            _apply(d) {
                if (!d) return;
                this.count = d.count || 0;
                this.total = d.total || 0;
                this.currency = d.currency || 'USD';
                this.items = d.items || [];
            },
            async _post(url, body) {
                const r = await fetch(url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this._csrf(), 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify(body || {}),
                });
                let data = null;
                try { data = await r.json(); } catch (e) {}
                if (r.status === 401 && data && data.login_required) {
                    window.dispatchEvent(new CustomEvent('open-viewer-login', { detail: { creatorId: data.creator_id || this.creatorId } }));
                    return { _login: true };
                }
                if (!r.ok) {
                    alert((data && data.error) || 'Something went wrong. Please try again.');
                    return { _error: true };
                }
                return data;
            },
            async add(blockId) {
                const d = await this._post('/store/' + encodeURIComponent(this.alias) + '/cart/add', { block_id: blockId });
                if (d && d.ok) { this._apply(d); this.open = true; }
            },
            async setQty(it, qty) {
                const d = await this._post('/store/' + encodeURIComponent(this.alias) + '/cart/update', { block_id: it.block_id, quantity: qty });
                if (d && d.ok) this._apply(d);
            },
            async buy(blockId) {
                if (this.busy) return;
                this.busy = true;
                const d = await this._post('/store/' + encodeURIComponent(this.alias) + '/buy', { block_id: blockId });
                this.busy = false;
                if (d && d.url) window.location = d.url;
            },
            async checkout() {
                if (this.busy) return;
                this.busy = true;
                const d = await this._post('/store/' + encodeURIComponent(this.alias) + '/checkout', {});
                this.busy = false;
                if (d && d.url) window.location = d.url;
            },
        });
    });
    </script>
    @endif

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
        Open in Sayzio app
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
                var msg = "AR isn't supported on this device or browser — here's the standard Link in Bio instead.";
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
    {{-- Task #1094 — limits ticker. Renders countdowns + remaining-count
         badges for every block with a cap or expiry, and reacts to expiry
         either by hiding the wrap or by replacing the primary CTA with a
         "sold out" label. Polls the public limits endpoint every 15s so a
         viewer parked on the page sees the count tick down as others click.
    --}}
    <script>
    (function () {
        var ALIAS = @json($link->alias);
        // Endpoint is rate-limited at 120/min per IP; with our 15s poll
        // interval we use ~4 requests/min, well under the cap even if
        // multiple tabs are open.
        var LIMITS_URL = '/api/v1/biolinks/' + encodeURIComponent(ALIAS) + '/blocks/limits';

        function fmtDuration(secs) {
            secs = Math.max(0, Math.floor(secs));
            var d = Math.floor(secs / 86400); secs -= d * 86400;
            var h = Math.floor(secs / 3600);  secs -= h * 3600;
            var m = Math.floor(secs / 60);    secs -= m * 60;
            var pad = function (n) { return n < 10 ? '0' + n : '' + n; };
            if (d > 0) return d + 'd ' + pad(h) + ':' + pad(m) + ':' + pad(secs);
            return pad(h) + ':' + pad(m) + ':' + pad(secs);
        }

        function applyExpired(wrap) {
            if (wrap.getAttribute('data-expired-applied') === '1') return;
            wrap.setAttribute('data-expired-applied', '1');
            var action = wrap.getAttribute('data-expired-action') || 'hide';
            if (action === 'hide') {
                wrap.style.display = 'none';
                return;
            }
            // "Show" mode — keep the block visible but neutralise CTAs and
            // overlay an unmistakable label so visitors don't try to click.
            var label = wrap.getAttribute('data-expired-label') || 'Sold out';
            var emoji = wrap.getAttribute('data-expired-emoji') || '';
            wrap.style.opacity = '0.55';
            wrap.style.pointerEvents = 'none';
            wrap.style.filter = 'grayscale(0.6)';
            var stamp = document.createElement('div');
            stamp.className = 'biolink-limit-expired-stamp';
            stamp.style.cssText = 'margin-top:6px;display:inline-flex;align-items:center;gap:6px;'
                + 'padding:4px 10px;border-radius:8px;font-size:11px;font-weight:700;'
                + 'background:rgba(120,113,108,0.28);color:#f5f5f4;'
                + 'border:1px solid rgba(255,255,255,0.18);';
            stamp.textContent = (emoji ? emoji + ' ' : '') + label;
            wrap.appendChild(stamp);
        }

        function renderBadge(wrap, opts) {
            var badge = wrap.querySelector('.biolink-limit-badge[data-badge-for]');
            if (!badge) return;
            var showCountdown = wrap.getAttribute('data-show-countdown') === '1';
            var showRemaining = wrap.getAttribute('data-show-remaining') === '1';
            var pieces = [];
            if (showCountdown && opts.expiresAt) {
                var secs = Math.floor((opts.expiresAt.getTime() - Date.now()) / 1000);
                if (secs > 0) pieces.push('⏳ ' + fmtDuration(secs));
            }
            if (showRemaining && opts.maxClicks > 0 && opts.remaining !== null) {
                var nearPct = parseInt(wrap.getAttribute('data-near-percent') || '20', 10);
                var pct = (opts.remaining / opts.maxClicks) * 100;
                var hot = pct <= nearPct;
                pieces.push((hot ? '🔥 Only ' : '') + opts.remaining + ' left');
                if (hot) {
                    badge.style.background = 'rgba(245,158,11,0.22)';
                    badge.style.color = 'rgba(254,243,199,0.98)';
                    badge.style.borderColor = 'rgba(245,158,11,0.35)';
                }
            }
            if (pieces.length === 0) {
                badge.style.display = 'none';
            } else {
                badge.style.display = '';
                badge.textContent = pieces.join(' · ');
            }
        }

        function readWrapState(wrap) {
            var expRaw = wrap.getAttribute('data-expires-at');
            var maxRaw = wrap.getAttribute('data-max-clicks');
            var remRaw = wrap.getAttribute('data-remaining');
            return {
                expiresAt: expRaw ? new Date(expRaw) : null,
                maxClicks: maxRaw ? parseInt(maxRaw, 10) : 0,
                remaining: remRaw === null || remRaw === '' ? null : parseInt(remRaw, 10),
                state: wrap.getAttribute('data-limit-state') || 'active',
            };
        }

        function tick() {
            document.querySelectorAll('[data-limits="1"]').forEach(function (wrap) {
                var st = readWrapState(wrap);
                // Expiry by time wins immediately on the client even before
                // the next poll lands — this avoids a "1 second after
                // midnight" flicker where the badge claims time remains.
                if (st.expiresAt && st.expiresAt.getTime() <= Date.now()) {
                    wrap.setAttribute('data-limit-state', 'expired');
                    applyExpired(wrap);
                    return;
                }
                if (st.maxClicks > 0 && st.remaining !== null && st.remaining <= 0) {
                    wrap.setAttribute('data-limit-state', 'expired');
                    applyExpired(wrap);
                    return;
                }
                renderBadge(wrap, st);
            });
        }

        function refresh() {
            // Skip polling when there's nothing on the page that needs it,
            // and skip while the tab is hidden — the next visibilitychange
            // handler does the catch-up fetch instead.
            if (document.hidden) return;
            if (!document.querySelector('[data-limits="1"]')) return;
            fetch(LIMITS_URL, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (j) {
                    // ApiResponses::ok wraps payloads as { data: {...} } —
                    // fall back to the bare object too, in case middleware
                    // ever decides to short-circuit the wrapper.
                    var payload = (j && j.data) ? j.data : j;
                    if (!payload || !Array.isArray(payload.items)) return;
                    payload.items.forEach(function (item) {
                        var wrap = document.querySelector('[data-block-id="' + item.id + '"][data-limits="1"]');
                        if (!wrap) return;
                        if (item.expires_at) wrap.setAttribute('data-expires-at', item.expires_at);
                        if (item.max_clicks !== null && item.max_clicks !== undefined) wrap.setAttribute('data-max-clicks', String(item.max_clicks));
                        if (item.remaining !== null && item.remaining !== undefined) wrap.setAttribute('data-remaining', String(item.remaining));
                        if (item.state) wrap.setAttribute('data-limit-state', item.state);
                    });
                    tick();
                })
                .catch(function () { /* network blip — next interval will retry */ });
        }

        function start() {
            tick();
            setInterval(tick, 1000);
            setInterval(refresh, 15000);
            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) refresh();
            });
        }
        if (document.readyState === 'complete' || document.readyState === 'interactive') start();
        else document.addEventListener('DOMContentLoaded', start);
    })();
    </script>
    @include('common.blocks._carbon_badge', ['link' => $link])
    @include('common.partials.biolink-report')
    {{-- reviews_wall: interactive star picker inside the "Write a review" modal. --}}
    <script>
    (function () {
        function wire(box) {
            var stars = box.querySelectorAll('button[data-star]');
            var input = box.querySelector('input[name="rating"]');
            function paint(v) {
                stars.forEach(function (s) {
                    var on = parseInt(s.getAttribute('data-star'), 10) <= v;
                    s.classList.toggle('text-yellow-400', on);
                    s.classList.toggle('text-white/20', !on);
                });
            }
            stars.forEach(function (s) {
                s.addEventListener('click', function () {
                    var v = parseInt(s.getAttribute('data-star'), 10);
                    if (input) input.value = v;
                    paint(v);
                });
            });
        }
        function start() { document.querySelectorAll('[data-rw-stars]').forEach(wire); }
        if (document.readyState === 'complete' || document.readyState === 'interactive') start();
        else document.addEventListener('DOMContentLoaded', start);
    })();
    </script>
</body>
</html>
