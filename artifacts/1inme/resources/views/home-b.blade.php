<!DOCTYPE html>
<html lang="en" class="{{ (($_COOKIE['1inme_theme'] ?? null) === 'light') ? 'light-mode' : '' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Safari uses theme-color to tint the browser toolbar. Always dark so the
         tab bar matches the brand regardless of the site's light/dark mode. --}}
    <meta name="theme-color" content="#0a0a14">
    @php
        $__seo = \App\Modules\Common\Support\MarketingSeo::resolveForView(['seoKey' => 'home']);
    @endphp
    <title>{{ $__seo['title'] }} — {{ config('app.name', 'Sayzio') }}</title>
    <meta name="description" content="{{ $__seo['description'] }}">
    @if(($__seo['keywords'] ?? '') !== '')
        <meta name="keywords" content="{{ $__seo['keywords'] }}">
    @endif
    @php
        $__schema = \App\Modules\Common\Support\MarketingSchema::forView([
            'seoKey' => 'home',
            'title'  => $__seo['title'] ?? null,
            'url'    => \App\Modules\Common\Support\PlatformHosts::canonicalUrl(),
        ]);
    @endphp
    <script type="application/ld+json">{!! json_encode($__schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @include('public.partials.marketing-share-meta')
    @include('public.partials.marketing-tracking')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('common.partials.default-icons')
    @include('common.partials.fontawesome')
    <script defer src="{{ asset('js/vendor/alpine-collapse.min.js') }}"></script>
    <script defer src="{{ asset('js/vendor/alpine.min.js') }}"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        // Fire-and-forget marketing-CTA tracking shared by every home-page
        // "Sign up free" button so we can see which placement converts.
        window.trackMarketingEvent = function (source, target) {
            try {
                var url = '{{ route('marketing-events.track') }}';
                var data = new FormData();
                data.append('source', source);
                data.append('target', target);
                if (navigator.sendBeacon) {
                    navigator.sendBeacon(url, data);
                } else {
                    fetch(url, { method: 'POST', body: data, keepalive: true, credentials: 'same-origin' });
                }
            } catch (e) { /* fire-and-forget */ }
        };
    </script>
    <script>
        // Sync with site-wide theme preference (also toggled via Cmd/Ctrl+I).
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
            window.dispatchEvent(new CustomEvent('inme-theme-changed', { detail: { light: light } }));
            return light;
        };
        function homeThemeToggle(){
            return {
                light: document.documentElement.classList.contains('light-mode'),
                init(){ window.addEventListener('inme-theme-changed', e => this.light = e.detail.light); },
                toggle(){ this.light = window.inmeToggleTheme(); }
            }
        }
    </script>
    <style>
        /* ==================================================================
           Homepage Variant B — "Signal" (compact editorial take).
           Same brand system as the classic page (blue accent, dark-first,
           light-mode paired rules) but a tighter, calmer layout: narrower
           measure, denser sections, minimal motion. Shares the deferred
           below-the-fold loader pattern with the classic page.
           ================================================================== */
        :root {
            --c1: #1bd4d9;
            --c2: #3d6bff;
            --c3: #e94e8c;
            --c4: #ff8a3c;
            --c5: #ffc845;
            --ink:  #14081f;
            --bg:   #0a0a14;
            --bg-2: #10101d;
            --bg-3: #171728;
        }
        html, body { background: var(--bg); }
        html { overflow-x: clip; }
        body { font-family: 'Space Grotesk', sans-serif; color: #fff; overflow-x: clip; }
        [x-cloak] { display: none !important; }

        html.light-mode {
            --bg:   #f8fafc;
            --bg-2: #ffffff;
            --bg-3: #f3f4f6;
            --ink:  #111827;
        }
        html.light-mode body { background: #f8fafc; color: #111827; }

        /* Shared tokens the reused partials expect (defined inline on the
           classic page too — the deferred fragments assume they exist). */
        .grad-text { color: #90acff; }
        html.light-mode .grad-text { color: #2342c7; }
        .grad-bar { background: linear-gradient(95deg, #3d6bff, #1bd4d9, #22d3ee); }
        .btn-bounce { transition: transform .22s cubic-bezier(.34,1.56,.64,1), box-shadow .25s, filter .25s; }
        .btn-bounce:hover { transform: translateY(-2px); filter: brightness(1.05); }
        .btn-bounce:active { transform: translateY(0) scale(.98); }
        .btn-glow { position: relative; }

        /* Reveal: Variant B keeps motion minimal — content always paints,
           the JS observer only adds a gentle fade-up when it runs. */
        .reveal { opacity: 1; transform: none; transition: opacity .5s ease, transform .5s ease; }
        .js .reveal:not(.visible) { opacity: 0; transform: translateY(14px); }
        @media (prefers-reduced-motion: reduce) {
            .reveal, .js .reveal:not(.visible) { opacity: 1 !important; transform: none !important; transition: none !important; }
        }

        /* Legacy animation hooks some shared partials carry — inert here so
           Variant B stays calm (no floats/marquees/sparkles). */
        .float-a, .float-b, .float-c, .wiggle, .spin-slow, .marquee, .marquee-rev,
        .pulse-dot, .drift-a, .drift-b, .pop-in { animation: none !important; }
        @keyframes spinSlow { to { transform: rotate(360deg); } }

        /* ============ Variant-B layout primitives (hb- prefix) ============ */
        .hb-shell { max-width: 72rem; margin: 0 auto; padding: 0 1rem; }
        @media (min-width: 640px) { .hb-shell { padding: 0 1.5rem; } }

        .hb-eyebrow { font-size: 11px; font-weight: 700; letter-spacing: .2em; text-transform: uppercase; color: #7ea0ff; }
        html.light-mode .hb-eyebrow { color: #2342c7; }

        .hb-hero-grid { display: grid; gap: 2.5rem; align-items: center; }
        @media (min-width: 1024px) { .hb-hero-grid { grid-template-columns: 7fr 5fr; } }

        .hb-panel {
            background: rgba(255,255,255,.05);
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 1.25rem;
        }
        html.light-mode .hb-panel { background: #ffffff; border-color: rgba(17,24,39,.1); box-shadow: 0 10px 30px -18px rgba(17,24,39,.18); }

        .hb-stat b { font-size: 1.05rem; font-weight: 700; color: #fff; }
        .hb-stat span { font-size: .72rem; color: rgba(255,255,255,.55); }
        html.light-mode .hb-stat b { color: #111827; }
        html.light-mode .hb-stat span { color: rgba(17,24,39,.55); }

        /* Compact preview card rows (hero right column). */
        .hb-row { display: flex; align-items: center; gap: .7rem; padding: .6rem .75rem; border-radius: .9rem;
                  background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.09); }
        html.light-mode .hb-row { background: #f3f6ff; border-color: rgba(35,66,199,.12); }
        .hb-row i.hb-ic { width: 30px; height: 30px; border-radius: .6rem; display: inline-flex; align-items: center; justify-content: center;
                          font-size: 12px; color: #fff; background: #3d6bff; flex-shrink: 0; }
        .hb-row .t { font-size: 12.5px; font-weight: 700; line-height: 1.2; color: #fff; }
        .hb-row .s { font-size: 10.5px; color: rgba(255,255,255,.55); }
        html.light-mode .hb-row .t { color: #111827; }
        html.light-mode .hb-row .s { color: rgba(17,24,39,.55); }

        /* Light-mode pairing for the shared grey text utilities used below. */
        html.light-mode .hb-body .text-gray-400 { color: #4b5563; }
        html.light-mode .hb-body .text-white { color: #111827; }
        html.light-mode .hb-body .border-white\/10 { border-color: rgba(17,24,39,.1); }

        a:focus-visible, button:focus-visible { outline: 2px solid var(--c2); outline-offset: 3px; border-radius: 8px; }
    </style>
</head>
<body class="overflow-x-hidden hb-body">

@include('common.partials.announcement-banner', ['surface' => 'site', 'fixed' => true])

{{-- ============================ NAV ============================ --}}
@include('public.partials.header', ['useModal' => true, 'fixed' => true])

{{-- Relationship band — mirrors the classic page (non-primary global domains). --}}
@php $__rel = \App\Modules\Common\Support\DomainBranding::relationship(); @endphp
@if($__rel)
<div class="relative z-30 pt-16 border-b border-white/10 bg-white/[0.04] backdrop-blur-xl">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex flex-col sm:flex-row items-center justify-center gap-x-3 gap-y-1 text-center">
        <span class="text-sm text-gray-200">{{ $__rel['blurb'] }}</span>
        <a href="{{ $__rel['primary_url'] }}"
           class="inline-flex items-center gap-1 text-sm font-semibold text-blue-300 hover:text-blue-200 whitespace-nowrap">
            Visit {{ $__rel['primary_domain'] }} <i class="fas fa-arrow-right text-[10px]"></i>
        </a>
    </div>
</div>
@endif

{{-- ============================ COMPACT HERO ============================ --}}
<section class="relative pt-32 pb-14 lg:pt-40 lg:pb-20" aria-labelledby="hero-h">
    <div class="hb-shell">
        <div class="hb-hero-grid">
            <div>
                <div class="reveal inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-semibold hb-panel mb-5">
                    <i class="fas fa-wand-magic-sparkles text-[11px]" style="color:var(--c2)"></i>
                    <span class="grad-text">One platform. Endless conversations.</span>
                </div>
                <h1 id="hero-h" class="reveal text-4xl sm:text-5xl font-bold leading-[1.1] tracking-tight mb-5">
                    Your link, now it <span class="grad-text">talks back</span>.
                </h1>
                <p class="reveal text-base sm:text-lg text-gray-400 max-w-xl mb-8 leading-relaxed">
                    Meet <strong class="text-white">Zio</strong>, the AI behind Sayzio. It builds your Link in Bio pages,
                    short links and QR codes, answers your visitors and picks up your calls —
                    <strong class="text-white">24/7, free forever</strong>, no card required.
                </p>
                <div class="reveal flex flex-wrap items-center gap-3 mb-8">
                    <button type="button"
                            onclick="window.trackMarketingEvent && window.trackMarketingEvent('landing_home_cta','hero_b'); window.dispatchEvent(new CustomEvent('open-auth',{detail:{tab:'register'}}))"
                            class="btn-bounce inline-flex items-center gap-2 px-7 py-3.5 grad-bar text-white rounded-full text-sm font-bold">
                        Sign up free <i class="fas fa-arrow-right text-xs"></i>
                    </button>
                    <a href="{{ route('site.how-it-works') }}"
                       class="btn-bounce inline-flex items-center gap-2 px-6 py-3.5 hb-panel text-white rounded-full text-sm font-semibold">
                        How it works
                    </a>
                </div>
                <div class="reveal flex flex-wrap gap-x-7 gap-y-3">
                    <div class="hb-stat flex flex-col"><b>2 min</b><span>Idea → live page</span></div>
                    <div class="hb-stat flex flex-col"><b>Free forever</b><span>No card required</span></div>
                    <div class="hb-stat flex flex-col"><b>24/7</b><span>Zio answers for you</span></div>
                </div>
            </div>

            {{-- Compact product preview: a dense "page ledger" instead of the
                 classic 3D phone stack. --}}
            <div class="reveal hidden sm:block" aria-hidden="true">
                <div class="hb-panel p-5 max-w-sm mx-auto lg:ml-auto">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-full grad-bar flex items-center justify-center text-white text-sm font-bold">JD</div>
                        <div>
                            <div class="text-sm font-bold text-white">@jane</div>
                            <div class="text-[11px] text-gray-400">Creator · Berlin</div>
                        </div>
                        <span class="ml-auto inline-flex items-center gap-1.5 text-[10px] font-bold text-emerald-400"><span class="w-1.5 h-1.5 rounded-full bg-emerald-400 inline-block"></span>Live</span>
                    </div>
                    <div class="space-y-2">
                        <div class="hb-row"><i class="hb-ic fas fa-store"></i><div class="flex-1 min-w-0"><div class="t">Shop merch</div><div class="s">22 products · from $19</div></div></div>
                        <div class="hb-row"><i class="hb-ic fas fa-qrcode" style="background:#1bd4d9"></i><div class="flex-1 min-w-0"><div class="t">Dynamic QR</div><div class="s">1,204 scans this week</div></div></div>
                        <div class="hb-row"><i class="hb-ic fas fa-comment-dots" style="background:#2342c7"></i><div class="flex-1 min-w-0"><div class="t">Zio replied to 14 visitors</div><div class="s">while you were offline</div></div></div>
                        <div class="hb-row"><i class="hb-ic fas fa-chart-line" style="background:#22d3ee;color:#0a0a14"></i><div class="flex-1 min-w-0"><div class="t">Live visitor map</div><div class="s">38 visitors right now</div></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ==================== DEFERRED BELOW-THE-FOLD SECTIONS ====================
     Same pattern as the classic page: everything after the hero is rendered
     by the /home/sections fragment (which serves the Variant-B fragment while
     this design is active) and injected right after first paint. --}}
<div id="home-deferred" data-src="{{ route('home.sections') }}" aria-busy="true" style="min-height:60vh">
    <div id="home-deferred-loading" style="display:flex;align-items:center;justify-content:center;padding:6rem 1rem;">
        <span style="width:28px;height:28px;border-radius:50%;border:3px solid rgba(120,140,255,.25);border-top-color:#3d6bff;animation:spinSlow 0.8s linear infinite;display:inline-block" aria-hidden="true"></span>
        <span class="sr-only">Loading more…</span>
    </div>
    <noscript>
        <div style="max-width:42rem;margin:0 auto;padding:3rem 1.5rem;text-align:center">
            <p style="margin-bottom:1rem">Explore everything Sayzio offers:</p>
            <p><a href="{{ route('site.features') }}" style="text-decoration:underline">All features</a> ·
               <a href="{{ route('site.pricing') }}" style="text-decoration:underline">Pricing</a> ·
               <a href="{{ route('site.how-it-works') }}" style="text-decoration:underline">How it works</a></p>
        </div>
    </noscript>
</div>
<script>
    document.documentElement.classList.add('js');
    // Minimal enhancement pass: fade-in reveals (idempotent, with the usual
    // unconditional backstop so nothing can stick at opacity 0).
    window.homeEnhance = function (root) {
        root = root || document;
        var reveals = Array.prototype.filter.call(root.querySelectorAll('.reveal'), function (el) {
            return !el.dataset.hbRevealed;
        });
        reveals.forEach(function (el) { el.dataset.hbRevealed = '1'; });
        if ('IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.05, rootMargin: '0px 0px -10px 0px' });
            reveals.forEach(function (el) { observer.observe(el); });
        }
        // Backstop: everything visible shortly after, observer or not.
        setTimeout(function () { reveals.forEach(function (el) { el.classList.add('visible'); }); }, 800);
    };
    if (document.readyState !== 'loading') window.homeEnhance();
    else document.addEventListener('DOMContentLoaded', function () { window.homeEnhance(); });
</script>
<script>
    (function () {
        var box = document.getElementById('home-deferred');
        if (!box) return;
        var started = false;
        function execScripts(root) {
            var scripts = root.querySelectorAll('script');
            Array.prototype.forEach.call(scripts, function (old) {
                var s = document.createElement('script');
                Array.prototype.forEach.call(old.attributes, function (a) { s.setAttribute(a.name, a.value); });
                s.textContent = old.textContent;
                old.parentNode.replaceChild(s, old);
            });
        }
        function load() {
            if (started) return;
            started = true;
            fetch(box.getAttribute('data-src'), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            }).then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.text();
            }).then(function (html) {
                box.innerHTML = html;
                box.style.minHeight = '';
                box.removeAttribute('aria-busy');
                execScripts(box);
                if (window.homeEnhance) window.homeEnhance(box);
                if (window.marketingAnimScan) window.marketingAnimScan(box);
                window.dispatchEvent(new CustomEvent('home:sections-loaded'));
                if (location.hash.length > 1) {
                    var t = null;
                    try { t = document.querySelector(location.hash); } catch (e) {}
                    if (t) t.scrollIntoView({ block: 'start' });
                }
            }).catch(function () {
                started = false;
            });
        }
        ['scroll', 'pointerdown', 'keydown', 'touchstart'].forEach(function (ev) {
            window.addEventListener(ev, load, { once: true, passive: true });
        });
        if (document.readyState === 'complete') setTimeout(load, 50);
        else window.addEventListener('load', function () { setTimeout(load, 50); });
        setTimeout(load, 3000);
    })();
</script>

{{-- ============================ FOOTER ============================ --}}
@include('public.partials.footer')

@include('common.partials.global-shortcuts')
@include('common.partials.cookie-consent', ['surface' => 'site'])
@include('common.partials.site-assistant', ['surface' => 'marketing'])
</body>
</html>
