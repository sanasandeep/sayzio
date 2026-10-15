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

<link rel="stylesheet" href="{{ asset('css/marketing/marketing-rails-5da954b54e20.css') }}">
