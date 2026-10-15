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
        // Short keyword-focused home designs carry their own SEO cluster
        // (title/description/keywords) so picking a design also retargets
        // search. Classic returns null and keeps the generic home SEO.
        if ($__designSeo = \App\Modules\Common\Controllers\HomeController::activeDesignSeo()) {
            $__seo = array_merge($__seo, $__designSeo);
        }
    @endphp
    <title>{{ \App\Modules\Common\Support\MarketingSeo::documentTitle($__seo['title'], ', ') }}</title>
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
    <link rel="stylesheet" href="{{ asset('css/marketing-anim.css') }}?v=18">
    @include('public.partials.surfaces')
    @include('public.partials.section-surfaces')
    @include('public.partials.rails')
    @include('home.partials.expandable-cards')
    @vite(['resources/js/marketing-anim.js'])
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
    <link rel="stylesheet" href="{{ asset('css/marketing/home-shell-d1cf380cecbd.css') }}">
</head>
<body class="overflow-x-hidden">

@include('common.partials.announcement-banner', ['surface' => 'site', 'fixed' => true])

{{-- ============ Aurora background ============ --}}
<div class="aurora" aria-hidden="true"><b></b><b></b><b></b><b></b></div>

{{-- ============================ NAV ============================ --}}
@include('public.partials.header', ['useModal' => true, 'fixed' => true])

{{-- Relationship band — only on a non-primary global domain. Sits directly
     below the (fixed) menu, explaining how this sub-brand domain relates to
     the primary domain. Hidden entirely on the primary domain. --}}
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

{{-- Scroll-spy strip removed: it duplicated the Product menu in the header.
     The home page now matches all other marketing pages, which use a sticky
     sidebar TOC (features, faqs, policy) instead of a horizontal sub-nav. --}}

{{-- ==================== ZONE · HOOK & CREDIBILITY ==================== --}}
@include('home.partials.hero')

{{-- ==================== SERVER-RENDERED SECTIONS ====================
     The first sections below the hero, in the initial HTML rather than in
     the fetch below, so a crawler's first pass finds the page's headings,
     its body copy and its internal links instead of a spinner. Which view
     (if any) is the active design's business: see the 'above' key in
     HomeController::DESIGNS, and the reasoning in the view itself.

     index() always passes $aboveFoldView, as a view name or as null. empty()
     rather than isset() because it has to answer BOTH of those and the
     undefined case — a design that defers everything passes null, and any
     other renderer of this view (a test rendering it directly) passes
     nothing at all. empty() is the one check that is safe on all three. --}}
@if(!empty($aboveFoldView))
    @include($aboveFoldView)
@endif

{{-- ==================== DEFERRED BELOW-THE-FOLD SECTIONS ====================
     Everything after the sections above (AI zone, pricing, FAQ, final CTA…)
     is server-rendered by the /home/sections fragment and injected here
     right after first paint. This keeps the initial response small — no
     plan or blog queries — while the rest of the brand story still
     arrives moments later — triggered by
     window load, first interaction, or a short failsafe timer, whichever
     comes first. Injected <script> tags are re-created so they execute;
     Alpine's mutation observer initializes injected x-data trees, and
     window.homeEnhance() + marketingAnimScan() re-run the reveal/anim
     observers over the new content (with the usual visibility backstops). --}}
<div id="home-deferred" data-src="{{ route('home.sections') }}" aria-busy="true" style="min-height:70vh">
    <div id="home-deferred-loading" style="display:flex;align-items:center;justify-content:center;padding:6rem 1rem;">
        <span style="width:28px;height:28px;border-radius:50%;border:3px solid rgba(120,140,255,.25);border-top-color:#3d6bff;animation:spinSlow 0.8s linear infinite;display:inline-block" aria-hidden="true"></span>
        <span class="sr-only">Loading more…</span>
    </div>
    <noscript>
        {{-- No JS: the fragment can't be fetched. Keep the page useful with
             direct links to the same content on always-full pages. --}}
        <div style="max-width:42rem;margin:0 auto;padding:3rem 1.5rem;text-align:center">
            <p style="margin-bottom:1rem">Explore everything Sayzio offers:</p>
            <p><a href="{{ route('site.features') }}" style="text-decoration:underline">All features</a> ·
               <a href="{{ route('site.pricing') }}" style="text-decoration:underline">Pricing</a> ·
               <a href="{{ route('site.how-it-works') }}" style="text-decoration:underline">How it works</a></p>
        </div>
    </noscript>
</div>
<script>
    (function () {
        var box = document.getElementById('home-deferred');
        if (!box) return;
        var started = false;
        function execScripts(root) {
            // Scripts inserted via innerHTML never execute, recreate each
            // one in place so section runtimes (demos, Alpine helpers) run.
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
                // If the visitor arrived with an in-page anchor (e.g. /#features),
                // honor it now that the target exists.
                if (location.hash.length > 1) {
                    var t = null;
                    try { t = document.querySelector(location.hash); } catch (e) {}
                    if (t) t.scrollIntoView({ block: 'start' });
                }
            }).catch(function () {
                // Fetch failed, allow a retry on the next trigger.
                started = false;
            });
        }
        // Earliest of: window load (+tiny delay so it never competes with
        // above-the-fold work), first interaction, or a failsafe timer.
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

<script>
    document.documentElement.classList.add('js');
    // Idempotent enhancement pass over `root` (defaults to the whole
    // document). Runs once at DOMContentLoaded for the initial header/hero
    // markup and AGAIN over the injected deferred sections (see the
    // #home-deferred loader). Every element is stamped via dataset flags so
    // re-running never double-observes or double-binds.
    // Belt and braces for the reveal observer. IntersectionObserver exists in
    // every browser we support, but it has been known to go quiet inside
    // some in-app webviews and after a bfcache restore, and a reveal that
    // never fires is invisible content, not just a missing animation. This
    // sweeps anything still hidden but inside the viewport, on scroll and
    // resize, throttled to one frame. It installs once and stops listening
    // as soon as no hidden reveals are left.
    window.homeRevealFallback = (function () {
        var installed = false, queued = false;
        function sweep() {
            queued = false;
            var pending = document.querySelectorAll('.reveal.reveal-ready:not(.visible)');
            for (var i = 0; i < pending.length; i++) {
                var top = pending[i].getBoundingClientRect().top;
                if (top < window.innerHeight * 0.95) { pending[i].classList.add('visible'); }
            }
            if (!document.querySelector('.reveal.reveal-ready:not(.visible)')) {
                window.removeEventListener('scroll', request);
                window.removeEventListener('resize', request);
                installed = false;
            }
        }
        function request() { if (!queued) { queued = true; requestAnimationFrame(sweep); } }
        return function () {
            if (!installed) {
                installed = true;
                window.addEventListener('scroll', request, { passive: true });
                window.addEventListener('resize', request);
            }
            setTimeout(request, 1200);
        };
    })();

    window.homeEnhance = function (root) {
        root = root || document;
        const pick = (sel) => Array.prototype.filter.call(
            root.querySelectorAll(sel), el => !el.dataset.homeEnhanced || !el.dataset.homeEnhanced.includes(sel)
        );
        const stamp = (el, sel) => { el.dataset.homeEnhanced = (el.dataset.homeEnhanced || '') + '|' + sel; };

        const reveals = pick('.reveal');
        reveals.forEach(el => stamp(el, '.reveal'));
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.05, rootMargin: '0px 0px -8% 0px' });
            // `reveal-ready` is what actually hides the element (see the CSS).
            // It goes on immediately before observe() so an element is never
            // hidden without something watching for its turn to appear.
            //
            // Anything already on screen when this runs is NEVER hidden. The
            // markup paints before this script does, so hiding the hero here
            // and waiting for the observer's first callback to bring it back
            // blanks the top of the page for about a second on every load, // an entrance nobody sees, in exchange for a blank first
            // impression. Those elements are simply marked done.
            const vh = window.innerHeight || 800;
            reveals.forEach(el => {
                if (el.getBoundingClientRect().top < vh) { el.classList.add('visible'); return; }
                el.classList.add('reveal-ready');
                observer.observe(el);
            });
            window.homeRevealFallback();
        } else {
            // No observer: never hide anything.
            reveals.forEach(el => el.classList.add('visible'));
        }

        // Toggle sc-alive on showcase cards so their continuous glow/blob
        // animations only run while the card is on screen and pause once it
        // scrolls out of view (saves GPU/battery on low-power devices). The
        // one-time entrance reveal stays gated on .visible above and is never
        // removed, so scrolling back in never re-triggers the entrance keyframe.
        // Skipped under prefers-reduced-motion (those animations are already
        // killed in CSS), keeping the page fully static.
        const showcaseCards = pick('.showcase-card');
        showcaseCards.forEach(el => stamp(el, '.showcase-card'));
        const scReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (showcaseCards.length && !scReducedMotion && 'IntersectionObserver' in window) {
            const scObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    entry.target.classList.toggle('sc-alive', entry.isIntersecting);
                });
            }, { threshold: 0.1 });
            showcaseCards.forEach(el => scObserver.observe(el));
        }

        // Toggle pp-in-view on pillar preview blocks so their subtle animations
        // only run while the card is on screen (and pause when scrolled away).
        const pillarPreviews = pick('.pillar-preview');
        pillarPreviews.forEach(el => stamp(el, '.pillar-preview'));
        if (pillarPreviews.length && 'IntersectionObserver' in window) {
            const ppObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    entry.target.classList.toggle('pp-in-view', entry.isIntersecting);
                });
            }, { threshold: 0.15 });
            pillarPreviews.forEach(el => ppObserver.observe(el));
        } else {
            pillarPreviews.forEach(el => el.classList.add('pp-in-view'));
        }

        // Toggle aud-in-view on audience cards so their subtle animations
        // only run while the card is on screen.
        const audCards = pick('.audience-card');
        audCards.forEach(el => stamp(el, '.audience-card'));
        if (audCards.length && 'IntersectionObserver' in window) {
            const audObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    entry.target.classList.toggle('aud-in-view', entry.isIntersecting);
                });
            }, { threshold: 0.2 });
            audCards.forEach(el => audObserver.observe(el));
        } else {
            audCards.forEach(el => el.classList.add('aud-in-view'));
        }

        pick('a[href^="#"]').forEach(anchor => {
            stamp(anchor, 'a[href^="#"]');
            anchor.addEventListener('click', function (e) {
                const href = this.getAttribute('href');
                if (href === '#' || href.length < 2) return;
                const target = document.querySelector(href);
                if (target) { e.preventDefault(); target.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
            });
        });

        // (Hero phone parallax is gated by IntersectionObserver in
        // resources/views/home/partials/hero.blade.php so it only runs while
        // the hero is on screen.)

        // Showcase grid ("what you can create"): cursor-following spotlight
        // per card, isolated to the hovered card. Skipped entirely under
        // prefers-reduced-motion (no cursor-driven motion) and on touch/coarse
        // pointer devices (no hover, so no listeners doing useless work).
        const showcaseFinePointer = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
        if (!scReducedMotion && showcaseFinePointer) {
            showcaseCards.forEach(card => {
                card.addEventListener('pointermove', (e) => {
                    const rect = card.getBoundingClientRect();
                    card.style.setProperty('--sc-x', ((e.clientX - rect.left) / rect.width * 100) + '%');
                    card.style.setProperty('--sc-y', ((e.clientY - rect.top) / rect.height * 100) + '%');
                });
            });
        }
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => window.homeEnhance());
    } else {
        window.homeEnhance();
    }
</script>
@include('common.partials.cookie-consent', ['surface' => 'site'])
@include('common.partials.site-assistant', ['surface' => 'marketing'])
</body>
</html>
