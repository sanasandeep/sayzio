---
name: Alpine x-show strips inline display
description: Why an x-show element must declare display:flex/grid in CSS, not inline, or its layout collapses to block when shown.
---

# Alpine `x-show` clobbers an element's inline `display`

Alpine `x-show` toggles visibility by writing `style.display = 'none'` to hide and
**removing the inline `display` property** (`removeProperty('display')` / `display:''`)
to show. Removing it reverts `display` to whatever a **stylesheet rule** says — NOT to
any `display` value you wrote in the same inline `style="..."` attribute.

**Consequence:** if an `x-show` element relies on inline `display:flex` (or `grid`),
the very first show strips that inline value and the element falls back to its default
(`block` for a div). Its flex/grid layout silently dies — flex children stop being
bounded, `flex:1` scroll bodies grow to full content, etc.

**Rule:** for any element controlled by `x-show` (or `x-transition`), declare its
`display: flex/grid` (and direction) in a **CSS rule / class**, never inline. Keep
non-display inline styles (position, inset, background) inline if you like — only
`display` is the trap.

**Why:** observed in the 1INME biolink editor — `.special-panel` (the Templates
overlay, `position:absolute; inset:0; x-show`) had `display:flex` inline; once shown,
computed display was `block`, so the `flex:1; overflow-y:auto` body grew to ~4783px
and the template tabs (Cards/Forms/Buzz/AI) wouldn't scroll. Moving
`.special-panel { display:flex; flex-direction:column; }` into the `<style>` block
fixed it. A panel needs a definite height too so the absolute child + its flex scroll
body can bound, but the display strip was the primary bug.

**How to apply:** grepping for `x-show` near an inline `display:flex/grid` is a smell.
Move display to CSS.
