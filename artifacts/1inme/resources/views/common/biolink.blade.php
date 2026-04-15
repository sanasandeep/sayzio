<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @if($link->seo_title)
        <title>{{ $link->seo_title }}</title>
        <meta property="og:title" content="{{ $link->seo_title }}">
    @else
        <title>{{ $link->title ?: '1INME Bio Link' }}</title>
    @endif
    @if($link->seo_description)
        <meta name="description" content="{{ $link->seo_description }}">
        <meta property="og:description" content="{{ $link->seo_description }}">
    @endif
    @if($link->seo_image)
        <meta property="og:image" content="{{ $link->seo_image }}">
    @endif
    @if($link->favicon)
        <link rel="icon" type="image/png" href="{{ $link->favicon }}">
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
    <link href="https://fonts.googleapis.com/css2?family={{ urlencode($fontFamily) }}:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @php
        $themeFont = $bs['block_theme']['font_family'] ?? '';
        $extraFonts = [];
        if ($themeFont && $themeFont !== $fontFamily) $extraFonts[] = $themeFont;
    @endphp
    @foreach($extraFonts as $ef)
    <link href="https://fonts.googleapis.com/css2?family={{ urlencode($ef) }}:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @endforeach
    <style>
        body {
            font-family: '{{ $fontFamily }}', sans-serif;
            color: {{ $fontColor }};
            @if($bgType === 'image' && $bgImage)
                background: url('{{ $bgImage }}') center/cover no-repeat fixed;
            @elseif($bgType === 'gradient')
                background: {{ $bgGradient }};
            @else
                background-color: {{ $bgColor }};
            @endif
            min-height: 100vh;
        }
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
        .ticker-scroll { animation: ticker 20s linear infinite; }
        @keyframes ticker { 0% { transform: translateX(100%); } 100% { transform: translateX(-100%); } }
        @keyframes morphText { 0%,100% { filter: blur(0px); } 50% { filter: blur(3px); } }
        .morph-text { animation: morphText 4s ease-in-out infinite; }
    </style>
</head>
<body class="flex justify-center p-4 pt-8 pb-16">
    <div class="w-full max-w-md">
        @php
            $blocks = $link->activeBiolinkBlocks()->get()->filter(fn($b) => $b->isVisible());
            $pageTitle = $bs['biolink_title'] ?? $link->title ?: 'Bio Link';
            $pageDescription = $bs['biolink_description'] ?? $link->seo_description ?? '';
            $globalTheme = $bs['block_theme'] ?? [];
        @endphp

        @forelse($blocks as $block)
            @php
                $s = $block->settings ?? [];
                $blockStyle = \App\Modules\User\Models\BiolinkBlock::getBlockStyle($s, $globalTheme);
                $blockInline = \App\Modules\User\Models\BiolinkBlock::buildInlineStyle($blockStyle);
                $hasCustomStyle = !empty($s['_style']) || (!empty($globalTheme) && ($globalTheme['apply_to_all'] ?? false));
                $skipWrap = in_array($block->type, ['avatar', 'divider', 'spacer', 'social_icons']);
            @endphp

            <div data-block-id="{{ $block->id }}" class="biolink-block-wrap">
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

            @elseif($block->type === 'heading')
                @php $hs = match($s['size'] ?? 'h2') { 'h1' => 'text-2xl md:text-3xl', 'h2' => 'text-xl md:text-2xl', 'h3' => 'text-lg md:text-xl', default => 'text-xl md:text-2xl' }; @endphp
                <div class="mb-3 text-{{ $s['align'] ?? 'center' }}"><h2 class="{{ $hs }} font-bold">{{ $s['text'] ?? '' }}</h2></div>

            @elseif($block->type === 'heading_gradient')
                @php $hs = match($s['size'] ?? 'h2') { 'h1' => 'text-2xl md:text-3xl', 'h2' => 'text-xl md:text-2xl', 'h3' => 'text-lg md:text-xl', default => 'text-xl md:text-2xl' }; @endphp
                <div class="mb-3 text-{{ $s['align'] ?? 'center' }}">
                    <h2 class="{{ $hs }} font-bold bg-clip-text text-transparent" style="background-image: linear-gradient(to right, {{ $s['from_color'] ?? '#7c3aed' }}, {{ $s['to_color'] ?? '#ec4899' }});">{{ $s['text'] ?? '' }}</h2>
                </div>

            @elseif($block->type === 'heading_logo')
                <div class="mb-3 text-{{ $s['align'] ?? 'center' }} flex items-center justify-{{ $s['align'] ?? 'center' }} gap-3">
                    @if(!empty($s['logo_url']))<img src="{{ $s['logo_url'] }}" alt="" class="h-8 w-8 object-contain">@endif
                    @php $hs = match($s['size'] ?? 'h2') { 'h1' => 'text-2xl md:text-3xl', 'h2' => 'text-xl md:text-2xl', 'h3' => 'text-lg md:text-xl', default => 'text-xl md:text-2xl' }; @endphp
                    <h2 class="{{ $hs }} font-bold">{{ $s['text'] ?? '' }}</h2>
                </div>

            @elseif($block->type === 'heading_morph')
                @php $hs = match($s['size'] ?? 'h1') { 'h1' => 'text-3xl md:text-4xl', 'h2' => 'text-2xl md:text-3xl', default => 'text-3xl md:text-4xl' }; @endphp
                <div class="mb-3 text-{{ $s['align'] ?? 'center' }}"><h2 class="{{ $hs }} font-bold morph-text">{{ $s['text'] ?? '' }}</h2></div>

            @elseif($block->type === 'paragraph')
                <div class="mb-4 text-{{ $s['align'] ?? 'center' }}"><p class="text-sm leading-relaxed" style="color: {{ $fontColor }}cc">{{ $s['text'] ?? '' }}</p></div>

            @elseif($block->type === 'paragraph_rich')
                <div class="mb-4 prose prose-invert prose-sm max-w-none">{!! strip_tags($s['html'] ?? '', '<p><br><a><strong><em><u><ul><ol><li><h1><h2><h3><h4><h5><h6><span><div><img><blockquote><hr>') !!}</div>

            @elseif($block->type === 'link')
                <a href="{{ $s['url'] ?? '#' }}" target="_blank" rel="noopener"
                   class="bio-btn block w-full px-6 py-3.5 mb-3 text-center font-medium transition-all duration-300 flex items-center justify-center gap-3">
                    @if(!empty($s['thumbnail']))<img src="{{ $s['thumbnail'] }}" class="w-6 h-6 rounded object-cover" alt="">
                    @elseif(!empty($s['icon']))<i class="{{ $s['icon'] }}"></i>@endif
                    <span>{{ $s['text'] ?? 'Link' }}</span>
                </a>

            @elseif($block->type === 'link_big')
                <a href="{{ $s['url'] ?? '#' }}" target="_blank" rel="noopener"
                   class="block w-full mb-3 rounded-2xl overflow-hidden transition-all duration-300 hover:-translate-y-1 hover:shadow-lg"
                   style="background: {{ $s['bg_color'] ?? $btnColor }};">
                    <div class="px-6 py-5 flex items-center gap-4">
                        @if(!empty($s['thumbnail']))<img src="{{ $s['thumbnail'] }}" class="w-12 h-12 rounded-xl object-cover" alt="">
                        @elseif(!empty($s['icon']))<div class="w-12 h-12 rounded-xl bg-white/10 flex items-center justify-center"><i class="{{ $s['icon'] }} text-xl"></i></div>@endif
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
                        <ul class="space-y-2">@foreach(($s['items'] ?? []) as $item)<li class="flex items-start gap-2 text-sm"><i class="fas {{ $s['icon'] ?? 'fa-check' }} text-purple-400 mt-0.5 text-xs"></i><span style="color:{{ $fontColor }}cc">{{ $item }}</span></li>@endforeach</ul>
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
                @php $alertColors = ['info' => 'border-blue-400/30 bg-blue-500/10', 'success' => 'border-green-400/30 bg-green-500/10', 'warning' => 'border-yellow-400/30 bg-yellow-500/10', 'error' => 'border-red-400/30 bg-red-500/10']; @endphp
                <div class="mb-4 rounded-xl p-4 border {{ $alertColors[$s['type'] ?? 'info'] ?? $alertColors['info'] }}">
                    <p class="text-sm flex items-center gap-2"><i class="fas {{ $s['icon'] ?? 'fa-info-circle' }}"></i>{{ $s['text'] ?? '' }}</p>
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

            {{-- SOCIAL --}}
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
                @endphp
                <div class="flex justify-center gap-3 mb-4 flex-wrap">
                    @foreach($allPlatforms as $platform)
                        @php $icon = $socialIcons[$platform['name'] ?? ''] ?? ['fas fa-link', '#7c3aed']; @endphp
                        <a href="{{ $platform['url'] ?? '#' }}" target="_blank" rel="noopener"
                           class="{{ $szClass }} {{ ($s['style'] ?? '') === 'square' ? 'rounded-lg' : 'rounded-full' }} glass-block flex items-center justify-center transition-all hover:scale-110 hover:-translate-y-1"
                           style="color: {{ $icon[1] }}"><i class="{{ $icon[0] }} {{ $sz === 'lg' ? 'text-xl' : 'text-lg' }}"></i></a>
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
                <div class="mb-4 glass-block rounded-xl p-5">
                    <p class="text-sm font-semibold mb-3 text-center">{{ $s['title'] ?? 'Contact Us' }}</p>
                    <form class="space-y-3" onsubmit="event.preventDefault(); this.querySelector('button').textContent='Sent!'; this.querySelector('button').disabled=true;">
                        <input type="text" placeholder="Name" required class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2.5 text-sm outline-none" style="color:{{ $fontColor }}">
                        <input type="email" placeholder="Email" required class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2.5 text-sm outline-none" style="color:{{ $fontColor }}">
                        <textarea placeholder="Message" rows="3" required class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2.5 text-sm outline-none" style="color:{{ $fontColor }}"></textarea>
                        <button type="submit" class="bio-btn w-full py-2.5 text-sm font-medium">{{ $s['button_text'] ?? 'Send' }}</button>
                    </form>
                </div>

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
                <div class="mb-4 glass-block rounded-xl p-5" x-data="{ voted: null }">
                    <p class="text-sm font-semibold mb-3">{{ $s['question'] ?? '' }}</p>
                    <div class="space-y-2">
                        @foreach(($s['options'] ?? []) as $i => $opt)
                        <button @click="voted = {{ $i }}" class="w-full text-left px-4 py-2.5 rounded-xl text-sm transition-all"
                                :class="voted === {{ $i }} ? 'bg-purple-500/30 border border-purple-400/40' : 'bg-white/5 border border-white/10 hover:bg-white/10'">
                            <span class="flex items-center gap-2"><span class="w-4 h-4 rounded-full border-2 flex items-center justify-center" :class="voted === {{ $i }} ? 'border-purple-400' : 'border-white/30'"><span x-show="voted === {{ $i }}" class="w-2 h-2 rounded-full bg-purple-400"></span></span>{{ $opt }}</span>
                        </button>
                        @endforeach
                    </div>
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
                @php $nColors = ['info' => 'bg-blue-500/20 border-blue-400/30', 'success' => 'bg-green-500/20 border-green-400/30', 'warning' => 'bg-yellow-500/20 border-yellow-400/30']; @endphp
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
                    This bio link page is being set up. Check back soon!
                </div>
            </div>
        @endforelse

        <p class="text-center text-xs mt-10" style="color: {{ $fontColor }}33">Powered by 1INME</p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script>
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

    @include('common.partials.pixel-scripts', ['link' => $link])

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
</body>
</html>
