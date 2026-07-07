{{--
    Non-blocking Font Awesome loader.

    Public pages previously loaded the full Font Awesome stylesheet with a
    plain blocking <link rel="stylesheet">, forcing the browser to fetch and
    parse ~100KB of CSS (plus large woff2 subsets) before it could finish
    rendering. That directly hurts First Paint / LCP on indexable pages.

    This uses the standard "loadCSS" media=print swap trick: the browser
    fetches the stylesheet at low priority without blocking render, then the
    onload handler flips its media to "all" once it's available. The
    <noscript> fallback keeps icons working with JS disabled.

    Safari gotcha: Safari does not reliably fire the link onload handler for
    media=print stylesheets (notably when they're served from cache), which
    left the stylesheet print-only forever and rendered every fa-* glyph
    blank. The inline script below is the standard loadCSS safety net: it
    force-flips any still-pending media=print FA link to media="all" once the
    DOM is ready, and again on window load as a belt-and-braces fallback —
    plus timed retries, because on long pages DOMContentLoaded can be many
    seconds away and Safari has additionally been seen IGNORING the media
    flip on an already-parsed print link. If the stylesheet still hasn't
    applied shortly after flipping (detected via document.fonts), the link is
    re-inserted fresh with media="all", which forces Safari to (re)apply it.

    Font preloads: the FA css declares font-display:block, so every glyph is
    an invisible box until the woff2 arrives. Browsers only discover the font
    URLs after the CSS applies (post-flip), which is late; preloading the two
    fonts actually used on public pages (solid + brands) removes that blank
    window in all browsers. Font preloads require crossorigin even for
    same-origin fonts.
--}}
@php $__faHref = asset('css/vendor/fontawesome-free-6.5.1/css/all.min.css'); @endphp
@php $__faFonts = asset('css/vendor/fontawesome-free-6.5.1/webfonts'); @endphp
<link rel="preload" href="{{ $__faHref }}" as="style">
<link rel="preload" href="{{ $__faFonts }}/fa-solid-900.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="{{ $__faFonts }}/fa-brands-400.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="{{ $__faHref }}" media="print" onload="this.media='all'" data-fa-async>
<noscript><link rel="stylesheet" href="{{ $__faHref }}"></noscript>
<script>
(function () {
    function activateFa() {
        var links = document.querySelectorAll('link[data-fa-async][media="print"]');
        for (var i = 0; i < links.length; i++) links[i].media = 'all';
    }
    // Safari recovery: if the FA font still isn't available a while after the
    // flip, assume the media change was ignored (a known Safari failure mode
    // for cached print stylesheets) and re-insert the link fresh with
    // media="all", which forces a clean (cache-served) apply.
    function reinsertIfDead() {
        try {
            if (document.fonts &&
                (document.fonts.check('900 16px "Font Awesome 6 Free"') ||
                 document.fonts.check('400 16px "Font Awesome 6 Brands"'))) return;
        } catch (e) { return; }
        var links = document.querySelectorAll('link[data-fa-async]');
        for (var i = 0; i < links.length; i++) {
            var old = links[i];
            if (old.getAttribute('data-fa-reinserted')) continue;
            var fresh = document.createElement('link');
            fresh.rel = 'stylesheet';
            fresh.href = old.href;
            fresh.media = 'all';
            fresh.setAttribute('data-fa-async', '');
            fresh.setAttribute('data-fa-reinserted', '1');
            old.parentNode.insertBefore(fresh, old.nextSibling);
            old.parentNode.removeChild(old);
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', activateFa);
    } else {
        activateFa();
    }
    window.addEventListener('load', activateFa);
    // Timed fallbacks: don't wait for DOMContentLoaded on long pages, and
    // recover if Safari ignored the media flip entirely.
    setTimeout(activateFa, 400);
    setTimeout(activateFa, 1500);
    setTimeout(reinsertIfDead, 3000);
})();
</script>
