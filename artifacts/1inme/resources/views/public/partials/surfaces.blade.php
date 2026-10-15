{{--
    Surfaces.

    One place that decides what a surface looks like across EVERY marketing
    page -- the home page and the ~26 pages on public/layouts/site.blade.php
    (features, pricing, how-it-works, about, the AI product pages, the
    compare set, the policy pages) -- so the answer does not have to be
    repeated in eleven partials and four thousand lines of section CSS.

    It used to load on the home page alone, which is how the home page ended
    up looking like a different product from /features.

    The rules here are deliberately last in the cascade and use !important:
    they exist to override component CSS that was written before there was a
    system, not to be the first word on anything. When a component is
    rewritten to take these tokens directly, its line here can go.

    What it decides:

      1. The ground. One flat colour behind the whole page — white in light
         mode, near-black in dark — with the decorative washes and tinted
         section bands turned off, so no section reads as a different shade
         of page.

      2. The surface. A card is transparent with a hairline border and no
         shadow: an outlined box on the ground rather than a raised pane.
         Small furniture that has to float over content (the navbar, the
         orbit's icon buttons) keeps a fill, because an outline alone would
         let the page show straight through it.

      3. The corners. Cards 12px, buttons 10px — squarer than the old 20px
         cards and fully round pills, without going sharp.

      4. No liquid glass and few gradients. Every backdrop blur is off; the
         drifting auras and blurred blobs behind cards are gone.

    Brand marks, product mockups and the primary call-to-action buttons keep
    their gradients on purpose: those read as the product's identity rather
    than as decoration.
--}}
<link rel="stylesheet" href="{{ asset('css/marketing/marketing-surfaces-f4060dfa866c.css') }}">
