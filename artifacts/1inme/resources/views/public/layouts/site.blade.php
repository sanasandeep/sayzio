<!DOCTYPE html>
<html lang="en" class="{{ (($_COOKIE['1inme_theme'] ?? null) === 'light') ? 'light-mode' : '' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $__seo = \App\Modules\Common\Support\MarketingSeo::resolveForView([
            'seoKey'           => $seoKey ?? null,
            'page'             => $page ?? null,
            'yieldTitle'       => trim($__env->yieldContent('title')) ?: null,
            'shareTitle'       => $shareTitle ?? null,
            'shareDescription' => $shareDescription ?? null,
        ]);
    @endphp
    <title>{{ $__seo['title'] }} — {{ config('app.name', '1INME') }}</title>
    <meta name="description" content="{{ $__seo['description'] }}">
    @if(($__seo['keywords'] ?? '') !== '')
        <meta name="keywords" content="{{ $__seo['keywords'] }}">
    @endif
    @php
        $__schema = \App\Modules\Common\Support\MarketingSchema::forView([
            'seoKey' => $seoKey ?? null,
            'page'   => $page ?? null,
            'title'  => $__seo['title'] ?? null,
            'url'    => request()->url(),
        ]);
    @endphp
    <script type="application/ld+json">{!! json_encode($__schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @include('common.partials.default-icons')
    @include('public.partials.marketing-share-meta')
    @include('public.partials.marketing-tracking')
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="{{ asset('js/vendor/alpine.min.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('css/marketing-anim.css') }}?v=4">
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
        })();
        window.inmeToggleTheme = function(){
            var html = document.documentElement;
            var light = !html.classList.contains('light-mode');
            html.classList.toggle('light-mode', light);
            var val = light ? 'light' : 'dark';
            try { localStorage.setItem('1inme_theme', val); } catch(e) {}
            try { document.cookie = '1inme_theme=' + val + '; path=/; max-age=31536000; SameSite=Lax'; } catch(e) {}
            try { window.dispatchEvent(new CustomEvent('inme-theme-changed', { detail: { light: light } })); } catch(e) {}
            return light;
        };
    </script>
    <script>
        try { tailwind.config = { theme: { extend: { fontFamily: { sans: ['Space Grotesk','sans-serif'] } } } } } catch(e) {}
    </script>
    <style>
        html, body { background:#1e2330; }
        body { color:#fff; font-family:'Space Grotesk', sans-serif; }
        html.light-mode, html.light-mode body { background:#f8fafc; color:#111827; }
        [x-cloak]{display:none!important}
        .prose-light p { margin-bottom:.75rem; line-height:1.65; color:#d1d5db; }
        .prose-light a { color:#a78bfa; text-decoration:underline; }
        .prose-light strong { color:#f5f3ff; font-weight:600; }
        .prose-light em { font-style:italic; }
        .prose-light ul, .prose-light ol { margin: .25rem 0 .9rem 1.4rem; line-height:1.65; color:#d1d5db; }
        .prose-light ul { list-style: disc; }
        .prose-light ol { list-style: decimal; }
        .prose-light li { margin-bottom:.25rem; }
        .prose-light h3 { font-size:1.05rem; font-weight:600; color:#fff; margin:.5rem 0 .35rem; }
        .prose-light h4 { font-size:.95rem; font-weight:600; color:#fff; margin:.5rem 0 .25rem; }
        .prose-light blockquote { border-left:3px solid rgba(167,139,250,.5); padding-left:.75rem; color:#cbd5e1; margin:.5rem 0 .9rem; }
        .prose-light code { background:rgba(255,255,255,.08); padding:.05rem .35rem; border-radius:.25rem; font-size:.9em; }
        .brand-logo { display: none; }
        .brand-logo--dark { display: inline-block; }
        html.light-mode .brand-logo--dark { display: none; }
        html.light-mode .brand-logo--light { display: inline-block; }
    </style>
    @stack('head')
</head>
<body class="min-h-screen flex flex-col">

@include('public.partials.header', ['useModal' => $useModal ?? false])

<main class="flex-1">
    @yield('content')
</main>

@include('public.partials.footer')

@include('common.partials.cookie-consent', ['surface' => 'site'])

@include('common.partials.site-assistant', ['surface' => 'marketing'])

@include('common.partials.global-shortcuts')

<script src="{{ asset('js/marketing-anim.js') }}?v=1" defer></script>

@stack('scripts')
</body>
</html>
