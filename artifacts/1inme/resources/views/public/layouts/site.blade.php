<!DOCTYPE html>
<html lang="en" class="{{ (($_COOKIE['1inme_theme'] ?? null) === 'light') ? 'light-mode' : '' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Safari uses theme-color to tint browser chrome and fill the area behind
         rounded page corners. Without it, Safari samples the root background which
         is dark by default, producing dark squares in light mode. We render the
         initial value server-side (matching the html.light-mode class logic on
         line 2) and keep it in sync with the client-side theme toggle via JS. --}}
    <meta id="site-theme-color" name="theme-color" content="{{ (($_COOKIE['1inme_theme'] ?? null) === 'light') ? '#f8fafc' : '#0a0a14' }}">
    @php
        $__seo = \App\Modules\Common\Support\MarketingSeo::resolveForView([
            'seoKey'           => $seoKey ?? null,
            'page'             => $page ?? null,
            'yieldTitle'       => trim($__env->yieldContent('title')) ?: null,
            'shareTitle'       => $shareTitle ?? null,
            'shareDescription' => $shareDescription ?? null,
        ]);
    @endphp
    <title>{{ $__seo['title'] }} — {{ config('app.name', 'Sayzio') }}</title>
    <meta name="description" content="{{ $__seo['description'] }}">
    @if(($__seo['keywords'] ?? '') !== '')
        <meta name="keywords" content="{{ $__seo['keywords'] }}">
    @endif
    @php
        $__schema = \App\Modules\Common\Support\MarketingSchema::forView([
            'seoKey' => $seoKey ?? null,
            'page'   => $page ?? null,
            'title'  => $__seo['title'] ?? null,
            'url'    => \App\Modules\Common\Support\PlatformHosts::canonicalUrl(),
        ]);
    @endphp
    <script type="application/ld+json">{!! json_encode($__schema, JSON_UNESCAPED_UNICODE) !!}</script>
    @include('common.partials.default-icons')
    @include('public.partials.marketing-share-meta')
    @include('public.partials.marketing-tracking')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('common.partials.fontawesome')
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="{{ asset('js/vendor/alpine-collapse.min.js') }}"></script>
    <script defer src="{{ asset('js/vendor/alpine.min.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('css/marketing-anim.css') }}?v=16">
    <script>
        // Theme preference: apply ASAP and expose toggle helper.
        // Kept in its own <script> tag so a Tailwind CDN error can't kill it.
        (function(){
            var pref = null;
            try { pref = localStorage.getItem('1inme_theme'); } catch(e) {}
            if (!pref) {
                var m = document.cookie.match(/(?:^|; )1inme_theme=([^;]+)/);
                if (m) pref = decodeURIComponent(m[1]);
            }
            if (pref === 'light' || pref === 'dark') {
                if (pref === 'light') document.documentElement.classList.add('light-mode');
            } else {
                try {
                    if (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) {
                        document.documentElement.classList.add('light-mode');
                    }
                } catch(e) {}
            }
            // Sync the theme-color meta with whatever class state we resolved
            // (localStorage may differ from the PHP-rendered cookie value).
            try {
                var __tc = document.getElementById('site-theme-color');
                if (__tc) __tc.setAttribute('content', document.documentElement.classList.contains('light-mode') ? '#f8fafc' : '#0a0a14');
            } catch(e) {}
        })();
        window.inmeToggleTheme = function(){
            var html = document.documentElement;
            var light = !html.classList.contains('light-mode');
            html.classList.toggle('light-mode', light);
            var val = light ? 'light' : 'dark';
            try { localStorage.setItem('1inme_theme', val); } catch(e) {}
            try { document.cookie = '1inme_theme=' + val + '; path=/; max-age=31536000; SameSite=Lax'; } catch(e) {}
            // Keep Safari theme-color (tab-bar / rounded-corner chrome) in sync.
            try {
                var __tc = document.getElementById('site-theme-color');
                if (__tc) __tc.setAttribute('content', light ? '#f8fafc' : '#0a0a14');
            } catch(e) {}
            try { window.dispatchEvent(new CustomEvent('inme-theme-changed', { detail: { light: light } })); } catch(e) {}
            return light;
        };
    </script>
    <style>
        /* Match the home page's richer, full-height background so marketing
           pages are visually consistent with it (and with the shared header's
           mega-menu panels, which are designed against this deep base). A flat
           mid-slate body used to clash with the home page, the darker section
           cards (#11101c) and the near-black footer (#08020f), leaving a seam. */
        :root { --bg:#0a0a14; }
        html, body { background: var(--bg); }
        html { overflow-x: clip; }
        body { color:#fff; font-family:'Space Grotesk', sans-serif; }
        html.light-mode { --bg:#f8fafc; }
        html.light-mode, html.light-mode body { background:#f8fafc; color:#111827; }
        html.light-mode .aurora { opacity: 0.18; }
        [x-cloak]{display:none!important}

        /* Aurora background (mirrors the home page) — a fixed, full-viewport
           glow that stays seamless from the header through to the footer as the
           page scrolls. */
        .aurora { position: fixed; inset: -10%; z-index: -1; pointer-events: none; opacity: .6; filter: blur(80px); }
        .aurora b { position: absolute; border-radius: 50%; mix-blend-mode: screen; animation: aurora 22s ease-in-out infinite; }
        .aurora b:nth-child(1) { top:-10%; left:-10%; width:60vw; height:60vw; background:#3d6bff; animation-delay:-2s; }
        .aurora b:nth-child(2) { bottom:-15%; right:-10%; width:55vw; height:55vw; background:#5c83ff; animation-delay:-8s; }
        .aurora b:nth-child(3) { top:30%; left:40%; width:40vw; height:40vw; background:#6e61ff; animation-delay:-14s; }
        .aurora b:nth-child(4) { top:60%; left:5%; width:35vw; height:35vw; background:#2342c7; opacity:.7; animation-delay:-18s; }
        @keyframes aurora {
            0%,100% { transform: translate(0,0) scale(1); }
            33%     { transform: translate(6%,-4%) scale(1.15); }
            66%     { transform: translate(-5%,5%) scale(.95); }
        }
        @media (prefers-reduced-motion: reduce) { .aurora b { animation: none; } }
        .prose-light p { margin-bottom:.75rem; line-height:1.65; color:#d1d5db; }
        .prose-light a { color:#90acff; text-decoration:underline; }
        .prose-light strong { color:#f5f3ff; font-weight:600; }
        .prose-light em { font-style:italic; }
        .prose-light ul, .prose-light ol { margin: .25rem 0 .9rem 1.4rem; line-height:1.65; color:#d1d5db; }
        .prose-light ul { list-style: disc; }
        .prose-light ol { list-style: decimal; }
        .prose-light li { margin-bottom:.25rem; }
        .prose-light h3 { font-size:1.05rem; font-weight:600; color:#fff; margin:.5rem 0 .35rem; }
        .prose-light h4 { font-size:.95rem; font-weight:600; color:#fff; margin:.5rem 0 .25rem; }
        .prose-light blockquote { border-left:3px solid rgba(144,172,255,.5); padding-left:.75rem; color:#cbd5e1; margin:.5rem 0 .9rem; }
        .prose-light code { background:rgba(255,255,255,.08); padding:.05rem .35rem; border-radius:.25rem; font-size:.9em; }
        .brand-logo { display: none; }
        .brand-logo--dark { display: inline-block; }
        html.light-mode .brand-logo--dark { display: none; }
        html.light-mode .brand-logo--light { display: inline-block; }
        /* Force the dark-bg logo variant regardless of page theme — used on
           always-dark surfaces like the auth-hero photo pane where the
           light-mode logo would wash out against the dark image. */
        .force-dark-logo .brand-logo--light { display: none !important; }
        .force-dark-logo .brand-logo--dark  { display: inline-block !important; }
        html.light-mode .force-dark-logo .brand-logo--light { display: none !important; }
        html.light-mode .force-dark-logo .brand-logo--dark  { display: inline-block !important; }
    </style>
    @stack('head')
</head>
<body class="min-h-screen flex flex-col">

{{-- Aurora background (consistent with the home page) --}}
<div class="aurora" aria-hidden="true"><b></b><b></b><b></b><b></b></div>

@include('common.partials.announcement-banner', ['surface' => 'site'])

@include('public.partials.header', ['useModal' => $useModal ?? false])

{{-- Cross-page "Discover Events" promo band — every site-layout page shows
     it except the events directory itself (which already has the full hero
     it's modeled on) and any page that opts out via the
     `suppress_events_hero_band` request attribute (e.g. a single event's own
     page, which has its own hero and would otherwise show two stacked heroes). --}}
@unless(request()->routeIs('events.index') || request()->attributes->get('suppress_events_hero_band'))
    @include('common.partials.events-hero-band')
@endunless

<main class="flex-1 mkt-site-main">
    @yield('content')
</main>

@include('public.partials.footer')

@include('common.partials.cookie-consent', ['surface' => 'site'])

@include('common.partials.site-assistant', ['surface' => 'marketing'])

@include('common.partials.global-shortcuts')

@vite(['resources/js/marketing-anim.js'])

@stack('scripts')
</body>
</html>
