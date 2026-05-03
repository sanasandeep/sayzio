<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        $title = $splash['title'] ?? ($link->title ?: '1INME');
        $description = $splash['description'] ?? null;
        $ogImage = $splash['og_image'] ?? ($splash['logo'] ?? null);
        $favicon = $splash['favicon'] ?? null;
        $countdown = (int) ($splash['countdown'] ?? 5);
        $autoRedirect = !empty($splash['auto_redirect']);
        $ctaLabel = ($splash['cta_label'] ?? '') ?: 'Continue';
        $ctaUrl = ($splash['cta_url'] ?? '') ?: $continueUrl;
        $continueAfterDelayUrl = $autoRedirect ? $destinationUrl : null;
        $hexRe = '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/';
        $ctaBg   = (isset($splash['cta_bg_color'])   && preg_match($hexRe, (string) $splash['cta_bg_color']))   ? $splash['cta_bg_color']   : null;
        $ctaText = (isset($splash['cta_text_color']) && preg_match($hexRe, (string) $splash['cta_text_color'])) ? $splash['cta_text_color'] : null;
        $ctaStyle = '';
        if ($ctaBg)   { $ctaStyle .= "background: {$ctaBg}; box-shadow: 0 10px 30px -8px rgba(0,0,0,0.35), inset 0 1px 0 rgba(255,255,255,0.18);"; }
        if ($ctaText) { $ctaStyle .= "color: {$ctaText};"; }
        $extraButtons = [];
        foreach ((array) ($splash['extra_buttons'] ?? []) as $b) {
            if (!is_array($b)) continue;
            $label = trim((string) ($b['label'] ?? ''));
            $url   = trim((string) ($b['url']   ?? ''));
            if ($label === '' || $url === '') continue;
            if (!preg_match('/^https?:\/\//i', $url)) continue;
            $bBg   = (isset($b['bg_color'])   && preg_match($hexRe, (string) $b['bg_color']))   ? $b['bg_color']   : null;
            $bText = (isset($b['text_color']) && preg_match($hexRe, (string) $b['text_color'])) ? $b['text_color'] : null;
            $style = '';
            if ($bBg)   { $style .= "background: {$bBg}; box-shadow: 0 10px 30px -8px rgba(0,0,0,0.35), inset 0 1px 0 rgba(255,255,255,0.18);"; }
            if ($bText) { $style .= "color: {$bText};"; }
            $extraButtons[] = ['label' => $label, 'url' => $url, 'style' => $style];
        }
    @endphp
    <title>{{ $title }}</title>
    @if($description)<meta name="description" content="{{ $description }}">@endif
    @include('common.partials.default-icons')
    @if($favicon)<link rel="icon" href="{{ $favicon }}">@endif
    <meta property="og:title" content="{{ $title }}">
    @if($description)<meta property="og:description" content="{{ $description }}">@endif
    @if($ogImage)<meta property="og:image" content="{{ $ogImage }}">@endif
    <meta name="twitter:card" content="{{ $ogImage ? 'summary_large_image' : 'summary' }}">
    <meta name="robots" content="noindex,nofollow">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-1: #0a0b10;
            --bg-2: #14111f;
            --bg-3: #1a1230;
            --accent: #8b5cf6;
            --accent-glow: rgba(139,92,246,0.45);
            --text: #f5f6fa;
            --text-muted: #9aa0b3;
        }
        * { box-sizing: border-box; }
        html, body {
            margin: 0; padding: 0; min-height: 100vh;
            font-family: 'Plus Jakarta Sans', 'Inter', system-ui, sans-serif;
            color: var(--text);
            background: var(--bg-1);
            -webkit-font-smoothing: antialiased;
        }
        .splash-bg {
            position: fixed; inset: 0; z-index: 0; overflow: hidden;
            background:
                radial-gradient(ellipse at 20% 0%, rgba(139,92,246,0.18), transparent 55%),
                radial-gradient(ellipse at 80% 100%, rgba(236,72,153,0.16), transparent 55%),
                radial-gradient(ellipse at 50% 50%, rgba(99,102,241,0.10), transparent 70%),
                linear-gradient(180deg, var(--bg-1), var(--bg-2) 60%, var(--bg-3));
        }
        .splash-bg::before, .splash-bg::after {
            content: ''; position: absolute; border-radius: 50%; filter: blur(80px); opacity: 0.55;
        }
        .splash-bg::before {
            width: 480px; height: 480px; top: -120px; left: -120px;
            background: radial-gradient(circle, rgba(139,92,246,0.45), transparent 70%);
            animation: drift1 18s ease-in-out infinite;
        }
        .splash-bg::after {
            width: 520px; height: 520px; bottom: -160px; right: -140px;
            background: radial-gradient(circle, rgba(236,72,153,0.35), transparent 70%);
            animation: drift2 22s ease-in-out infinite;
        }
        @keyframes drift1 { 0%,100% { transform: translate(0,0) scale(1);} 50% { transform: translate(60px,40px) scale(1.1);} }
        @keyframes drift2 { 0%,100% { transform: translate(0,0) scale(1);} 50% { transform: translate(-50px,-30px) scale(0.95);} }
        .glass-card {
            position: relative;
            background: linear-gradient(160deg, rgba(255,255,255,0.04), rgba(255,255,255,0.015));
            border: 1px solid rgba(255,255,255,0.10);
            border-radius: 24px;
            box-shadow: 0 30px 80px -20px rgba(0,0,0,0.6), inset 0 1px 0 rgba(255,255,255,0.06);
            backdrop-filter: blur(18px) saturate(1.1);
            -webkit-backdrop-filter: blur(18px) saturate(1.1);
        }
        .cta {
            display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
            padding: 0.95rem 2rem; border-radius: 14px;
            background: linear-gradient(135deg, #8b5cf6, #6d28d9);
            color: white; font-weight: 700; font-size: 0.95rem; letter-spacing: -0.01em;
            text-decoration: none;
            box-shadow: 0 10px 30px -8px var(--accent-glow), inset 0 1px 0 rgba(255,255,255,0.18);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .cta:hover { transform: translateY(-2px); box-shadow: 0 14px 40px -8px var(--accent-glow); }
        .cta:active { transform: translateY(0); }
        .skip-link {
            font-size: 0.78rem; color: var(--text-muted); text-decoration: none; opacity: 0.7;
        }
        .skip-link:hover { opacity: 1; color: var(--text); }
        .countdown-ring {
            transform: rotate(-90deg);
            transition: stroke-dashoffset 1s linear;
        }
        .pulse-orb {
            animation: pulse 2.4s ease-in-out infinite;
        }
        @keyframes pulse {
            0%,100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(139,92,246,0.5); }
            50%     { transform: scale(1.04); box-shadow: 0 0 0 24px rgba(139,92,246,0); }
        }
        .fade-up { animation: fadeUp 0.7s ease-out both; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }
        .fade-up-1 { animation-delay: 0.05s; }
        .fade-up-2 { animation-delay: 0.18s; }
        .fade-up-3 { animation-delay: 0.32s; }
        .fade-up-4 { animation-delay: 0.46s; }
        .branding {
            font-size: 0.7rem; color: var(--text-muted); letter-spacing: 0.06em;
            text-transform: uppercase; opacity: 0.55;
        }
        @media (prefers-reduced-motion: reduce) {
            .splash-bg::before, .splash-bg::after, .pulse-orb, .fade-up { animation: none !important; }
            .countdown-ring { transition: none !important; }
        }
        {!! $splash['custom_css'] ?? '' !!}
    </style>
</head>
<body>
    <div class="splash-bg"></div>

    <div class="relative z-10 min-h-screen flex flex-col items-center justify-center px-6 py-12">
        <div class="glass-card w-full max-w-xl mx-auto p-8 sm:p-12 text-center">
            @if(!empty($splash['logo']))
                <div class="fade-up fade-up-1 mb-6 flex items-center justify-center">
                    <div class="w-20 h-20 rounded-2xl pulse-orb flex items-center justify-center" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); padding: 12px;">
                        <img src="{{ $splash['logo'] }}" alt="{{ $title }}" class="max-w-full max-h-full object-contain">
                    </div>
                </div>
            @else
                <div class="fade-up fade-up-1 mb-6 flex items-center justify-center">
                    <div class="w-16 h-16 rounded-2xl pulse-orb flex items-center justify-center" style="background: linear-gradient(135deg, #8b5cf6, #6d28d9);">
                        <i class="fas fa-bolt text-white text-2xl"></i>
                    </div>
                </div>
            @endif

            <h1 class="fade-up fade-up-2 text-3xl sm:text-4xl font-extrabold tracking-tight mb-3" style="letter-spacing: -0.02em;">
                {{ $title }}
            </h1>

            @if($description)
                <p class="fade-up fade-up-3 text-base leading-relaxed mb-8" style="color: var(--text-muted); max-width: 28rem; margin-left: auto; margin-right: auto;">
                    {{ $description }}
                </p>
            @endif

            <div class="fade-up fade-up-4 flex flex-col items-center gap-4">
                <a id="splash-cta" href="{{ $ctaUrl }}" class="cta" @if($ctaStyle) style="{{ $ctaStyle }}" @endif>
                    <span>{{ $ctaLabel }}</span>
                    <i class="fas fa-arrow-right text-xs"></i>
                </a>

                @foreach($extraButtons as $b)
                    <a href="{{ $b['url'] }}" class="cta" @if($b['style']) style="{{ $b['style'] }}" @endif rel="noopener">
                        <span>{{ $b['label'] }}</span>
                    </a>
                @endforeach

                @if($autoRedirect && $countdown > 0)
                    <div class="flex items-center gap-3 mt-2">
                        <div class="relative" style="width: 38px; height: 38px;">
                            <svg width="38" height="38" viewBox="0 0 38 38">
                                <circle cx="19" cy="19" r="16" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="3"/>
                                <circle id="countdown-ring" class="countdown-ring" cx="19" cy="19" r="16" fill="none"
                                        stroke="url(#splash-grad)" stroke-width="3" stroke-linecap="round"
                                        stroke-dasharray="100.5" stroke-dashoffset="0" pathLength="100"/>
                                <defs>
                                    <linearGradient id="splash-grad" x1="0" y1="0" x2="1" y2="1">
                                        <stop offset="0%" stop-color="#a78bfa"/>
                                        <stop offset="100%" stop-color="#ec4899"/>
                                    </linearGradient>
                                </defs>
                            </svg>
                            <span id="countdown-num" class="absolute inset-0 flex items-center justify-center text-xs font-bold">{{ $countdown }}</span>
                        </div>
                        <span class="text-sm" style="color: var(--text-muted);">
                            Redirecting in <span id="countdown-text">{{ $countdown }}</span>s
                        </span>
                    </div>
                @endif
            </div>
        </div>

        <a href="{{ url('/') }}" class="branding mt-6 hover:opacity-100" style="text-decoration: none;">
            Powered by 1INME
        </a>
    </div>

    @if($autoRedirect && $countdown > 0)
    <script>
        (function(){
            var total = {{ $countdown }};
            var remaining = total;
            var ring = document.getElementById('countdown-ring');
            var num = document.getElementById('countdown-num');
            var txt = document.getElementById('countdown-text');
            var dest = @json($continueAfterDelayUrl);
            function tick(){
                remaining--;
                if (num) num.textContent = Math.max(0, remaining);
                if (txt) txt.textContent = Math.max(0, remaining);
                if (ring) ring.setAttribute('stroke-dashoffset', String(((total - remaining) / total) * 100));
                if (remaining <= 0) {
                    window.location.href = dest;
                    return;
                }
                setTimeout(tick, 1000);
            }
            setTimeout(tick, 1000);
        })();
    </script>
    @endif

    @if(!empty($splash['custom_js']))
    <script>
        try {
            {!! $splash['custom_js'] !!}
        } catch (e) { console.error('Splash custom JS error:', e); }
    </script>
    @endif
</body>
</html>
