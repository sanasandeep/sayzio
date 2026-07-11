{{--
    Font Awesome loader (shared by every public-facing page).

    History: public pages previously used the "loadCSS" media=print swap to
    load Font Awesome without blocking render, plus an inline safety net,
    timed retries, and a re-insert recovery — because Safari repeatedly
    failed to activate print-swapped stylesheets (cached print links never
    fire onload, and Safari has even been seen ignoring the media flip on an
    already-parsed link). Despite all of those layers, real-world Safari
    still rendered blank icons.

    Current approach: a plain, ordinary blocking <link rel="stylesheet">.
    Zero cleverness — identical, dependable behavior in every browser. The
    stylesheet is ~100KB from our own origin and cached after first visit,
    so the render-blocking cost is small and only paid on a cold cache.

    Font preloads: the FA css declares font-display:block, so every glyph is
    an invisible box until its woff2 arrives, and browsers only discover the
    font URLs after the CSS is parsed. Preloading the two fonts actually used
    on public pages (solid + brands) starts those downloads immediately and
    removes the blank-glyph window in all browsers. Font preloads require
    crossorigin even for same-origin fonts.

    Guarded by scripts/src/check-fontawesome-loader.ts: the blocking link +
    font preloads must stay here, and no public view may roll its own FA
    <link> (especially not a print-swap — that reintroduces the Safari bug).
--}}
@php $__faHref = asset('css/vendor/fontawesome-free-6.5.1/css/all.min.css'); @endphp
@php $__faFonts = asset('css/vendor/fontawesome-free-6.5.1/webfonts'); @endphp
<link rel="preload" href="{{ $__faFonts }}/fa-solid-900.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="{{ $__faFonts }}/fa-brands-400.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="{{ $__faHref }}" data-fa-stylesheet>


