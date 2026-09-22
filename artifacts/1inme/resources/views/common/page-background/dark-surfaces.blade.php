{{--
    The dark-mode surface rules for a page that has COMMITTED to dark.

    These pages normally switch their cards, sheets and borders on
    `prefers-color-scheme`. Once a creator chooses a background, the
    visitor's OS must stop deciding -- otherwise a dark background lands
    light-mode cards on a light-OS visitor, and the picker looks broken to
    half the audience and fine to the other half.

    Emitted AFTER the media queries it overrides, with the same specificity,
    so it wins by order. Restating them is the point: the alternative is
    rewriting twenty-five existing rules to carry a guard, which is a much
    larger edit to pages this change is otherwise not touching.
--}}
.item    { border-color:rgba(255,255,255,.08); }
.qbtn    { border-color:rgba(255,255,255,.2); }
.cartbar { background:#15151c; border-color:rgba(255,255,255,.1); }
.sheet   { background:#15151c; color:#f5f5f7; }
.field   { border-color:rgba(255,255,255,.2); }
