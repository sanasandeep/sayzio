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
        $bgColor = $bs['background_color'] ?? '#0f0a1a';
        $bgGradient = $bs['background_gradient'] ?? 'linear-gradient(135deg, #0f0a1a 0%, #1a0533 50%, #0f0a1a 100%)';
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
    @endphp
    <link href="https://fonts.googleapis.com/css2?family={{ urlencode($fontFamily) }}:wght@400;500;600;700&display=swap" rel="stylesheet">
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
    </style>
</head>
<body class="flex justify-center p-4 pt-8 pb-16">
    <div class="w-full max-w-md">
        @php
            $blocks = $link->activeBiolinkBlocks()->get()->filter(fn($b) => $b->isVisible());
            $pageTitle = $bs['biolink_title'] ?? $link->title ?: 'Bio Link';
            $pageDescription = $bs['biolink_description'] ?? $link->seo_description ?? '';
        @endphp

        @forelse($blocks as $block)
            @php $s = $block->settings ?? []; @endphp

            @if($block->type === 'avatar')
                <div class="flex justify-center mb-4">
                    @if(!empty($s['url']))
                        <img src="{{ $s['url'] }}" alt="Avatar"
                             class="{{ ($s['rounded'] ?? true) ? 'rounded-full' : 'rounded-2xl' }} object-cover border-2 border-white/10"
                             style="width: {{ $s['size'] ?? 96 }}px; height: {{ $s['size'] ?? 96 }}px;">
                    @else
                        <div class="rounded-full bg-white/10 backdrop-blur flex items-center justify-center border-2 border-white/10"
                             style="width: {{ $s['size'] ?? 96 }}px; height: {{ $s['size'] ?? 96 }}px;">
                            <span class="text-3xl font-bold" style="color: {{ $fontColor }}">{{ strtoupper(substr($link->title ?: 'B', 0, 1)) }}</span>
                        </div>
                    @endif
                </div>

            @elseif($block->type === 'heading')
                @php
                    $headingSize = match($s['size'] ?? 'h2') {
                        'h1' => 'text-2xl md:text-3xl',
                        'h2' => 'text-xl md:text-2xl',
                        'h3' => 'text-lg md:text-xl',
                        default => 'text-xl md:text-2xl',
                    };
                    $align = $s['align'] ?? 'center';
                @endphp
                <div class="mb-3 text-{{ $align }}">
                    <h2 class="{{ $headingSize }} font-bold">{{ $s['text'] ?? '' }}</h2>
                </div>

            @elseif($block->type === 'paragraph')
                <div class="mb-4 text-{{ $s['align'] ?? 'center' }}">
                    <p class="text-sm leading-relaxed" style="color: {{ $fontColor }}cc">{{ $s['text'] ?? '' }}</p>
                </div>

            @elseif($block->type === 'link')
                <a href="{{ $s['url'] ?? '#' }}" target="_blank" rel="noopener"
                   class="bio-btn block w-full px-6 py-3.5 mb-3 text-center font-medium transition-all duration-300 flex items-center justify-center gap-3">
                    @if(!empty($s['thumbnail']))
                        <img src="{{ $s['thumbnail'] }}" class="w-6 h-6 rounded object-cover" alt="">
                    @elseif(!empty($s['icon']))
                        <i class="{{ $s['icon'] }}"></i>
                    @endif
                    <span>{{ $s['text'] ?? 'Link' }}</span>
                </a>

            @elseif($block->type === 'cta_button')
                <a href="{{ $s['url'] ?? '#' }}" target="_blank" rel="noopener"
                   class="block w-full mb-3 text-center font-semibold transition-all duration-300 hover:-translate-y-0.5"
                   style="background: {{ $s['color'] ?? $btnColor }}; color: {{ $s['text_color'] ?? $btnTextColor }};
                          padding: {{ ($s['size'] ?? 'lg') === 'sm' ? '10px 20px' : (($s['size'] ?? 'lg') === 'md' ? '14px 24px' : '18px 32px') }};
                          border-radius: {{ $btnRadius }}; box-shadow: 0 6px 20px {{ $s['color'] ?? $btnColor }}40;
                          font-size: {{ ($s['size'] ?? 'lg') === 'sm' ? '14px' : (($s['size'] ?? 'lg') === 'md' ? '16px' : '18px') }};">
                    {{ $s['text'] ?? 'Click Here' }}
                </a>

            @elseif($block->type === 'image')
                <div class="mb-4 rounded-xl overflow-hidden">
                    @if(!empty($s['link']))
                        <a href="{{ $s['link'] }}" target="_blank" rel="noopener">
                    @endif
                    <img src="{{ $s['url'] ?? '' }}" alt="{{ $s['alt'] ?? '' }}" class="w-full rounded-xl">
                    @if(!empty($s['link']))
                        </a>
                    @endif
                </div>

            @elseif($block->type === 'video')
                <div class="mb-4 rounded-xl overflow-hidden glass-block">
                    <video class="w-full rounded-xl" controls {{ ($s['autoplay'] ?? false) ? 'autoplay muted' : '' }}>
                        <source src="{{ $s['url'] ?? '' }}" type="video/mp4">
                    </video>
                </div>

            @elseif($block->type === 'audio')
                <div class="mb-3 glass-block rounded-xl p-4">
                    @if(!empty($s['title']))
                        <p class="text-sm font-medium mb-2">{{ $s['title'] }}</p>
                    @endif
                    <audio controls class="w-full" style="filter: invert(1) hue-rotate(180deg);">
                        <source src="{{ $s['url'] ?? '' }}">
                    </audio>
                </div>

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
                            frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                </div>

            @elseif($block->type === 'spotify')
                @php
                    $spotifyUrl = $s['url'] ?? '';
                    $spotifyEmbed = str_replace('open.spotify.com', 'open.spotify.com/embed', $spotifyUrl);
                @endphp
                <div class="mb-4 rounded-xl overflow-hidden">
                    <iframe src="{{ $spotifyEmbed }}" class="w-full rounded-xl" height="{{ ($s['type'] ?? 'track') === 'track' ? '152' : '352' }}"
                            frameborder="0" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"></iframe>
                </div>

            @elseif($block->type === 'socials')
                @php
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
                    ];
                @endphp
                <div class="flex justify-center gap-3 mb-4 flex-wrap">
                    @foreach(($s['platforms'] ?? []) as $platform)
                        @php $icon = $socialIcons[$platform['name'] ?? ''] ?? ['fas fa-link', '#7c3aed']; @endphp
                        <a href="{{ $platform['url'] ?? '#' }}" target="_blank" rel="noopener"
                           class="w-11 h-11 rounded-full glass-block flex items-center justify-center transition-all hover:scale-110 hover:-translate-y-1"
                           style="color: {{ $icon[1] }}">
                            <i class="{{ $icon[0] }} text-lg"></i>
                        </a>
                    @endforeach
                </div>

            @elseif($block->type === 'divider')
                <div class="my-4 px-4">
                    <hr style="border-style: {{ $s['style'] ?? 'solid' }}; border-color: {{ $s['color'] ?? 'rgba(255,255,255,0.1)' }}; border-width: 1px 0 0 0;">
                </div>

            @elseif($block->type === 'spacer')
                <div style="height: {{ $s['height'] ?? 20 }}px"></div>

            @elseif($block->type === 'faq')
                <div class="mb-4 space-y-2" x-data="{ open: null }">
                    @foreach(($s['items'] ?? []) as $i => $item)
                    <div class="glass-block rounded-xl overflow-hidden">
                        <button @click="open = open === {{ $i }} ? null : {{ $i }}" class="w-full px-4 py-3 flex items-center justify-between text-left">
                            <span class="text-sm font-medium">{{ $item['question'] ?? '' }}</span>
                            <i class="fas fa-chevron-down text-xs transition-transform" :class="open === {{ $i }} ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === {{ $i }}" x-cloak class="px-4 pb-3">
                            <p class="text-sm" style="color: {{ $fontColor }}99">{{ $item['answer'] ?? '' }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>

            @elseif($block->type === 'email_collector')
                <div class="mb-4 glass-block rounded-xl p-5 text-center">
                    <p class="text-sm font-semibold mb-3">{{ $s['title'] ?? 'Subscribe' }}</p>
                    <form class="flex gap-2" onsubmit="event.preventDefault(); this.querySelector('button').textContent='Subscribed!'; this.querySelector('button').disabled=true;">
                        <input type="email" required placeholder="{{ $s['placeholder'] ?? 'Your email' }}"
                               class="flex-1 bg-white/5 border border-white/10 rounded-xl px-3 py-2.5 text-sm outline-none focus:border-white/20"
                               style="color: {{ $fontColor }}">
                        <button type="submit" class="bio-btn px-5 py-2.5 text-sm font-medium whitespace-nowrap">{{ $s['button_text'] ?? 'Subscribe' }}</button>
                    </form>
                </div>

            @elseif($block->type === 'map')
                @php $mapQ = urlencode($s['address'] ?? ''); @endphp
                <div class="mb-4 rounded-xl overflow-hidden aspect-video glass-block">
                    <iframe src="https://maps.google.com/maps?q={{ $mapQ }}&z={{ $s['zoom'] ?? 14 }}&output=embed"
                            class="w-full h-full rounded-xl" frameborder="0" style="border:0;filter:invert(90%) hue-rotate(180deg);"></iframe>
                </div>

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

            @elseif($block->type === 'custom_html')
                <div class="mb-4">{!! strip_tags($s['html'] ?? '', '<p><br><a><strong><em><u><ul><ol><li><h1><h2><h3><h4><h5><h6><span><div><img><iframe><table><tr><td><th><thead><tbody><hr><blockquote><pre><code>') !!}</div>
            @endif
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
</body>
</html>
