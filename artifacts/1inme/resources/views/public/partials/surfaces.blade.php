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
<style>
:root{
  /* The lattice. One cell size for the whole page, because more than one
     thing lines up to it: the hero's grid draws it, the hero's feature tiles
     sit in its cells, and the floating navbar spans a whole number of them.
     Phase is set by the hero grid at background-position-x:50%, which puts a
     cell CENTRE on the viewport centre — so anything centred and an ODD
     number of cells wide has both its edges on a line. */
  --cell:58px;

  /* The DRAWN square. The lattice is measured in --cell (that is what the
     navbar counts to land its edges on a line), but what the eye reads as
     "the grid" is this module. It was four cells (232px) and is now one and
     a half (87px).

     The size is not a taste call, it falls out of the arithmetic. Seventeen
     feature icons have to sit at cell CENTRES, in a field about 620x700
     with Zio and his bubble taking a 280x530 bite out of the middle, and
     the outermost column still has to clear the left edge at 1200px wide.
     That leaves roughly 400,000/G^2 cells, of which about a third are under
     Zio -- so G much above 96px cannot seat them all with any room left to
     move. Four cells gave nine usable cells; three gave eleven; two gave
     eighteen, which is seventeen icons in eighteen squares, a grid rather
     than a scatter. One and a half gives twenty-eight. Phase is set by the hero grid at background-position-x:50%,
     which puts a cell CENTRE on the viewport centre -- so anything centred
     lands in a square, and anything an ODD number of cells wide lands both
     its edges on a line.

     This is the phone and no-round() value. Wider than that, --grid is
     snapped to the rails just below, which moves it by a few percent. */
  --grid:calc(var(--cell) * 1.5);

  /* The span between the two page rails, and the width the floating navbar
     resolves to. It lives here rather than in rails.blade.php because the
     drawn grid has to agree with it -- see the block below. The plain 92vw
     is the fallback for browsers without round(). */
  --rail-span:92vw;

  /* Ground and surfaces */
  --fs-page:#000000;                    /* the whole page, one flat colour   */
  --fs-card:transparent;                /* a card is an outline, not a pane  */
  --fs-chip:#131320;                    /* furniture that floats over content */
  --fs-panel:#121218;                   /* inside a product mockup            */
  /* A hairline that held its own on #0B0B0F disappears on true black, so the
     rules come up as the ground goes down. */
  --fs-rule:rgba(255,255,255,.14);
  --fs-rule-2:rgba(255,255,255,.28);
  --fs-shadow:none;
  --fs-ink-2:#A9B0D0;
  /* Corners */
  --fs-r-card:12px;
  --fs-r-btn:10px;
  --fs-r-chip:8px;
}


/* Snap the drawn square to the rails.
   ----------------------------------
   These are two different measuring systems, and they disagreed. The rails
   and the navbar count in --cell: 23 cells at a 1512px viewport, so a
   1334px span with its edges at x=89 and x=1423. The drawn grid was a flat
   87px phased to the viewport centre, and 87 does not divide 1334 -- so the
   nearest drawn line to the left rail fell at 103.5, a 14.5px sliver away.
   At the left edge of the page, where the ribbon does not cover it, that
   read as two vertical lines almost on top of each other with an
   off-looking narrow column between them, while every other column was a
   full square wide.

   The fix is to stop drawing a fixed square and start drawing a whole
   number of squares BETWEEN the rails, sized as close to 1.5 cells as that
   allows. The rails then are grid lines rather than near-misses, and every
   column across the page is identical.

   The count must be ODD. The rail span is centred on the viewport, so an
   odd number of squares keeps a square CENTRE on the viewport centre --
   which is the phase `background-position: 50%` draws, and the phase the
   hero's feature tiles and Zio's nodes are positioned against. An even
   count would put a LINE on the centre and shift all of them by half a
   square. round(...) to the nearest odd count is what the *2+1 does.

   In practice this moves the square from a flat 87px to between 79px and
   91px depending on viewport width -- small enough that the field still
   seats its twenty-eight usable cells, exact enough that the gap at both
   rails is 0px instead of 14.5px. */
@media (min-width: 768px){
  @supports (width: round(down, 10px, 3px)){
    :root{
      --rail-span:calc(var(--cell) * (round(down, (92vw / var(--cell) - 1) / 2, 1) * 2 + 1));
      --grid-count:calc(round((var(--rail-span) / (var(--cell) * 1.5) - 1) / 2, 1) * 2 + 1);
      --grid:calc(var(--rail-span) / var(--grid-count));
    }
  }
}

html.light-mode{
  --fs-page:#FFFFFF;
  --fs-card:transparent;
  --fs-chip:#FFFFFF;
  --fs-panel:#F5F6FA;
  --fs-rule:#E6E8F2;
  --fs-rule-2:#C9CEE4;
  --fs-shadow:none;
  --fs-ink-2:#4E5680;
}

/* ---------- 1. one ground ---------- */

/* The selector is this long on purpose: marketing-anim.css paints the body
   from `html.light-mode body` with !important, and among important
   declarations specificity still decides. A bare `body` loses to it. */
html,html body,html.light-mode body{background:var(--fs-page) !important}

/* Section-level washes and tinted bands. Each of these paints a rectangle
   the width of the page in a slightly different shade, which is exactly
   what stops the page reading as one sheet. */
:is(.ai-zone-wash,.aisx-grid-bg,.aud-wash,.lt-wash){background-image:none !important;background-color:transparent !important}
/* The same wash again, drawn as a drifting pseudo-element, once per section
   that copied the pattern: form builder, notifications, dialer, resume. */
:is(.fb-mesh,.nf-mesh,.dc-mesh,.rb-mesh)::before{display:none !important}
/* .mesh-bg is the shared version of the same thing, and the compare teaser
   brings it onto this page. Killed here only — the standalone compare and
   pricing pages don't load this file, so they keep their own look. */
.mesh-bg::before,.mesh-bg::after{display:none !important}
/* The blurred blue-violet blob behind every grid of cards. It was there to
   give translucent glass something to refract; the cards are opaque now, so
   all it does is stain the page. Killed here rather than at each call site
   so the other marketing pages lose it too. */
.glass-ambient-wash::before{display:none !important}
/* Decorative colour blobs parked inside a card, same era, same problem —
   and now they fight the card's own corner bloom. */
:is(.aud-blob,.glass > .absolute.rounded-full[style*="background:var(--c"]){display:none !important}
.-z-10[style*="rgba(61,107,255"]{background:transparent !important}
.glass-footer{background-image:none !important;background-color:var(--fs-page) !important;
  border-top:1px solid var(--fs-rule) !important}

/* ---------- 2. no liquid glass ---------- */
*,*::before,*::after{backdrop-filter:none !important;-webkit-backdrop-filter:none !important}

/* ---------- 3. the surface ---------- */

/* Cards: transparent, one hairline, no lift. */
:is(.glass,.glass-2,.trust-band-card,.buzz-card,.prem-feat,.audience-card,
    .hiw-step,.rb-feat,.dc-feat,.aisx-card,.ai-prompt-card,.bs-banner,
    .cc-card,.geo-ticker,.nt-card,.fm-card){
  background-image:none !important;
  background-color:var(--fs-card) !important;
  border:1px solid var(--fs-rule) !important;
  box-shadow:var(--fs-shadow) !important;
}
:is(.glass,.glass-2,.buzz-card,.audience-card,.rb-feat,.dc-feat,.aisx-card):hover{
  border-color:var(--fs-rule-2) !important}

/* Furniture that floats over the page keeps a body, or the content behind
   it shows through: the navbar bar, the orbit's icon buttons, the cookie
   card. Same hairline, same corners — just not see-through. */
:is(.mkt-navbar-bar,.cc-card){
  background-color:var(--fs-chip) !important;
  border:1px solid var(--fs-rule) !important;
}

/* The navbar sits on the lattice: square corners, and a width of a whole
   number of cells. An ODD number, because the grid's phase puts a cell
   centre on the viewport centre — so a centred odd-width bar lands both its
   edges on a line. round() picks the largest odd count that still fits
   inside 92% of the viewport; the plain 92% above it is what browsers
   without round() keep. */
.mkt-navbar-bar{
  border-radius:0 !important;
  width:92% !important;
  width:calc(var(--cell) * (round(down, (92vw / var(--cell) - 1) / 2, 1) * 2 + 1)) !important;
  max-width:none !important;
  /* No lift. The bar carried a six-layer inset highlight plus a 45px drop
     shadow — the last of the glass look, and what made it read as a card
     hovering above the lattice instead of a row of it. A hairline is all a
     rule on a grid needs. */
  box-shadow:none !important;
  /* At the top of the page the bar is glass: the hero's ribbon and lattice
     run under it, and only the wordmark and links sit on top. It gains a
     body as soon as the page moves, because from then on there is real
     content sliding underneath that the links have to stay readable
     against. `scrolled` is the Alpine flag the header already kept for its
     auto-hide behaviour, exposed here as .is-stuck -- no new listener.

     Transitioning colour rather than opacity keeps the text at full
     strength throughout; fading the whole bar would take the links with
     it. backdrop-filter only ever applies to the already-blurred stuck
     state, so there is nothing to composite while the bar is clear. */
  background-color:transparent !important;
  border-color:transparent !important;
  transition:background-color .28s ease, border-color .28s ease, backdrop-filter .28s ease !important;
}
.mkt-navbar-bar.is-stuck{
  background-color:color-mix(in srgb, var(--fs-chip) 82%, transparent) !important;
  border-color:var(--fs-rule) !important;
  -webkit-backdrop-filter:saturate(150%) blur(14px);
          backdrop-filter:saturate(150%) blur(14px);
}
/* No color-mix, no translucency: the bar takes its solid fill the moment
   it sticks, which is the same thing one step less pretty. */
@supports not (background-color: color-mix(in srgb, red 50%, transparent)){
  .mkt-navbar-bar.is-stuck{ background-color:var(--fs-chip) !important; }
}
@media (prefers-reduced-motion: reduce){
  .mkt-navbar-bar{ transition:none !important; }
}

/* The inner highlight and wash those cards drew on their own pseudo
   elements went with the blur; without it they read as smudges. */
:is(.glass,.glass-2,.trust-band-card,.buzz-card,.prem-feat)::before,
:is(.glass,.glass-2,.trust-band-card,.buzz-card,.prem-feat)::after{
  background-image:none !important;
}

/* Product mock surfaces — a phone screen, a sheet of paper, a map — are
   panels INSIDE a card, so they take a faint fill rather than the ground:
   on a white page a white screen would have no edges at all. */
/* `.bb-screen` is deliberately NOT in this list. It is the one mock here
   showing the user's OWN page rather than a neutral surface, and that page
   carries a background image — stripping it left the builder demo showing a
   blank panel, which is the opposite of what the section is selling. It needs
   no faint fill for its edges either: the image is dark and supplies its own
   contrast against the white card behind it. */
:is(.lt-mock-zone,.rb-paper,.geo-map){
  background-image:none !important;background-color:var(--fs-panel) !important}

/* ---------- 4. corners ---------- */

:is(.glass,.glass-2,.trust-band-card,.buzz-card,.prem-feat,.audience-card,
    .hiw-step,.rb-feat,.dc-feat,.aisx-card,.ai-prompt-card,.bs-banner,
    .cc-card,.nt-card,.fm-card,.hiw-cta-wrap,.fb-card,
    .rounded-3xl,.rounded-2xl){border-radius:var(--fs-r-card) !important}
/* The navbar is NOT in that list. It used to be, and since both rules are
   important at the same specificity the later one won — which is how it kept
   a 12px radius after being told to square off. Its own rule is below. */

/* Buttons. The named CTA classes, plus any link or button that is round
   AND has horizontal padding — which is what separates a pill-shaped
   button from a round icon button (those are sized w-9 h-9 and have no
   px- utility, so they stay round). */
:is(.btn-cta,.btn-bounce,.zio-cta-ghost,.zio-claim,.zio-claim-btn,.ai-zone-chip,
    .ms-btn,.wa-btn,.aih-see-live,.cmp-cta,.store-badge,.cc-btn),
:is(a,button)[class*="px-"].rounded-full{border-radius:var(--fs-r-btn) !important}

:is(.th-pill,.lt-chip-new,.chip,.pill)[class]{border-radius:var(--fs-r-chip) !important}

/* ---------- 5. fewer gradients ---------- */

/* Drifting auras, mascot halo, and the blurred circles behind cards. */
:is(.aurora,.zio-glow,.zio-mascot-halo,.aud-blob,.lt-blob,.ai-zone-aura,.aisx-blob){display:none !important}
.absolute[class*="blur-"]{display:none !important}
.absolute.rounded-full[class*="opacity-"]{display:none !important}

/* The "how it works" rail ran behind the four step cards, which used to be
   opaque. Now that a card is an outline the rail shows straight through all
   four of them as a line across the text. It was decoration either way. */
.hiw-track::before{display:none !important}

/* The feature ticker was a five stop rainbow. */
.grad-bar{background-image:none !important;background-color:#3E3AE0 !important}

/* ---------- 6. light mode contrast ----------
   Not part of the flattening, and not caused by it: several newer
   components hardcode rgba(255,255,255,...) text instead of taking a
   colour token, so in light mode they turn white on white. The link type
   chips lose every label. This is a real bug on the site today, with no
   skin involved; the durable fix is a light-mode pairing inside each
   component, and this holds the line until then. */
html.light-mode :is(.lt-chip,.lt-chip span,.lt-rail span,.lt-pane-desc,.lt-pane-title,
  .lt-pane-badge,.lt-chip-new,.ms-item-text,.dc-dialchan-label,.dc-digit,
  .aisx-card-desc,.th-pill){color:var(--fs-ink-2) !important}
html.light-mode :is(.lt-chip.is-active,.lt-chip[aria-selected="true"],.aisx-card-title){color:#0B1033 !important}
</style>
