{{--
    The light-mode surface rules for a page that has COMMITTED to light.

    The mirror of dark-surfaces: a creator who picks a pale background and
    dark ink must not get dark cards because the VISITOR's OS is in dark
    mode. Values copied from each page's own default (non-media-query)
    rules, so "committed to light" renders exactly as an unedited page
    does for a light-OS visitor.
--}}
.item    { border-color:rgba(0,0,0,.08); }
.qbtn    { border-color:rgba(0,0,0,.2); }
.cartbar { background:#fff; border-color:rgba(0,0,0,.1); }
.sheet   { background:#fff; color:#111; }
.field   { border-color:rgba(0,0,0,.2); }
