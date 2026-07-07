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
--}}
@php $__faHref = asset('css/vendor/fontawesome-free-6.5.1/css/all.min.css'); @endphp
<link rel="preload" href="{{ $__faHref }}" as="style">
<link rel="stylesheet" href="{{ $__faHref }}" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="{{ $__faHref }}"></noscript>
