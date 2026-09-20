{{--
    AI Chat's own surfaces, committed to the scheme the creator chose.

    The menu pages share five surfaces; this page has a different five --
    header rule, assistant bubble, starter chips, the composer and its
    textarea -- and, unlike them, it switches on BOTH the visitor's OS and
    the companion's own light/dark control. Once a background is chosen,
    neither may decide: the assistant bubble in particular is a filled
    surface, so getting it wrong is not a subtle border, it is a light grey
    block on a dark page.

    Expects $pbInkLight. Emitted last so it wins by order.
--}}
@if($pbInkLight)
.header   { border-color: rgba(255,255,255,.08); }
.msg.a    { background:rgba(255,255,255,.07); }
.chip     { border-color:rgba(255,255,255,.16); }
form      { border-color:rgba(255,255,255,.08); }
textarea  { border-color:rgba(255,255,255,.14); }
@else
.header   { border-color: rgba(0,0,0,.08); }
.msg.a    { background:rgba(0,0,0,.05); }
.chip     { border-color:rgba(0,0,0,.12); }
form      { border-color:rgba(0,0,0,.08); }
textarea  { border-color:rgba(0,0,0,.14); }
@endif
