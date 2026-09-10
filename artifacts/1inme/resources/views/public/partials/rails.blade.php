{{--
    The page rails.

    Stripe frames its whole homepage in one drawn structure: a hairline under
    the nav, and two vertical rules that run unbroken from that line to the
    bottom of the footer, past every section boundary. It reads as a sheet
    the content is set on rather than a stack of separate bands.

    The hero already draws a lattice, but it stops where the hero stops, so
    the framing died at the fold. These rails carry the same geometry down
    the rest of the page.

    Fixed, not absolute, on purpose. The lines are vertical and never move
    horizontally, so pinning them to the viewport gives an unbroken rule at
    no layout cost -- an absolutely-positioned element would have to span the
    full scroll height of a very long page and be re-rasterised as it moves.

    They land on the SAME columns the navbar's edges do, because the navbar
    is already an odd number of lattice cells wide and centred, which puts
    both its edges on a cell boundary. So the rails are not a new grid; they
    are the one the page already keeps, made visible.
--}}
<div class="mkt-rails" aria-hidden="true">
    <span class="mkt-rail mkt-rail--l"></span>
    <span class="mkt-rail mkt-rail--r"></span>
</div>

<style>
.mkt-rails{
  position:fixed; inset:0;
  pointer-events:none;
  /* Above the page ground, below everything that is read. Sections on this
     page are outlines on one flat ground rather than opaque bands, which is
     what lets a rule behind them still show through. */
  z-index:0;
}
.mkt-rail{
  position:absolute; top:0; bottom:0;
  width:1px;
  background:var(--fs-rule, #E6E8F2);
  /* The rails are structure, not content: present enough to frame the page,
     faint enough that nobody reads them as a divider between two things. */
  opacity:.62;
}
/* Plain 4% is the fallback for browsers without round(); --rail-span is the
   same width the navbar resolves to, so the rails and the bar share an edge
   instead of nearly sharing one.

   --rail-span is defined on :root in flat-surfaces.blade.php, not here.
   It used to be a local on .mkt-rails, which meant the drawn grid had no way
   to see it -- and the grid, sized independently, put its nearest line 14.5px
   off the left rail. Two lines, one narrow column, and every other column a
   full square wide. One definition, read by both, is what keeps them on the
   same geometry. */
.mkt-rail--l{ left:4%; }
.mkt-rail--r{ right:4%; }
@supports (width: round(down, 10px, 3px)){
  .mkt-rail--l{ left:calc(50% - var(--rail-span) / 2); }
  .mkt-rail--r{ left:calc(50% + var(--rail-span) / 2); right:auto; }
}

/* The hairline under the nav band, full bleed. Drawn here rather than as a
   border on the bar itself because the bar is 92vw and this rule runs edge
   to edge, the way Stripe's does.

   It fades in with the bar: at the top of the page the navbar is
   transparent so the ribbon can run under it, and a hard rule across the
   viewport there would cut the hero in half. */
.mkt-nav-rule{
  position:fixed; left:0; right:0;
  top:calc(var(--inme-anno-h, 0px) + 4rem);
  height:1px;
  background:var(--fs-rule, #E6E8F2);
  opacity:0;
  transition:opacity .28s ease;
  pointer-events:none;
  z-index:39;
}
.mkt-nav-rule.is-stuck{ opacity:.62; }
@media (prefers-reduced-motion: reduce){
  .mkt-nav-rule{ transition:none; }
}

/* On a phone the rails would sit inside the text column -- the content runs
   nearly full width there, so a rule at 4% is a line through the copy
   rather than a frame around it. */
@media (max-width: 767px){
  .mkt-rails{ display:none; }
}
</style>
