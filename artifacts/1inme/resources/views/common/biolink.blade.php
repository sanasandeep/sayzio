@php
    $metaSettings = $link->settings['biolink']['meta'] ?? [];
    $ogSettings = $link->settings['biolink']['og'] ?? [];
    $twSettings = $link->settings['biolink']['twitter'] ?? [];
    $manifestSettings = $link->settings['biolink']['manifest'] ?? [];
    $faviconSettings = $link->settings['biolink']['favicons'] ?? [];
    $shareBtnSettings = $link->settings['biolink']['share_button'] ?? [];
    $menuBarSettings = $link->settings['biolink']['menu_bar'] ?? [];
    $autoTranslateSettings = $link->settings['biolink']['auto_translate'] ?? [];
    $pageTitle = $link->seo_title ?? $metaSettings['seo_title'] ?? $link->title ?? '1INME Link in Bio';
    $pageDesc = $link->seo_description ?? $metaSettings['seo_description'] ?? '';
    $pageImage = $link->seo_image ?? $ogSettings['image_url'] ?? '';
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
            background: linear-gradient(110deg, #8b5cf6, #ec4899, #f59e0b, #8b5cf6);
            background-size: 300% 100%; -webkit-background-clip: text; background-clip: text; color: transparent;
            animation: anim-gradient-swipe 6s linear infinite;
        }
        @keyframes anim-gradient-swipe { 0% { background-position: 0% 50%; } 100% { background-position: 300% 50%; } }
        [data-anim="neon_glitch"] h1, [data-anim="neon_glitch"] h2, [data-anim="neon_glitch"] h3 {
            color: #fff; text-shadow: 0 0 6px #a855f7, 0 0 14px #a855f7, 0 0 28px #ec4899;
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

