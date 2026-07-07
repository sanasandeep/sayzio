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
    DOM is ready, and again on window load as a belt-and-braces fallback.
--}}
@php $__faHref = asset('css/vendor/fontawesome-free-6.5.1/css/all.min.css'); @endphp
<link rel="preload" href="{{ $__faHref }}" as="style">
<link rel="stylesheet" href="{{ $__faHref }}" media="print" onload="this.media='all'" data-fa-async>
<noscript><link rel="stylesheet" href="{{ $__faHref }}"></noscript>
<script>
(function () {
    function activateFa() {
        var links = document.querySelectorAll('link[data-fa-async][media="print"]');
        for (var i = 0; i < links.length; i++) links[i].media = 'all';
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', activateFa);
    } else {
        activateFa();
    }
    window.addEventListener('load', activateFa);
})();
</script>
