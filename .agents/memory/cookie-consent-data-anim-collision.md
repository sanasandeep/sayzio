---
name: Cookie consent [data-anim] reveal collision
description: Why a phantom empty band can appear below the footer on 1inme marketing pages
---

# Cookie consent vs marketing reveal: `data-anim` collision

The 1inme marketing reveal system (`public/css/marketing-anim.css` + `public/js/marketing-anim.js`)
styles EVERY `[data-anim]` element with `opacity:0` and only reveals it (opacity:1) when its
IntersectionObserver adds `.in-view`.

**Pitfall:** any dynamically-injected element that uses a bare `data-anim` attribute for its own
purpose but is never observed by that observer will be stuck invisible (opacity:0) while still
occupying layout. The cookie-consent host did exactly this — invisible banner + leftover
`updateFooterReserve()` body padding = a visible empty dark/light band below the footer with no
banner in it.

**Rule:** the consent host's animation attribute is namespaced `data-cc-anim` (not `data-anim`).
Never reintroduce a bare `data-anim` on dynamically-created nodes outside the reveal pipeline.

**Also:** the footer reserve must only exist for a prompt the user can actually SEE. `updateFooterReserve`/`reserveVisible`
treats detached / not-bottom-pinned / display:none / visibility:hidden / opacity 0 / zero-height as
"not shown" and clears the reserve (capped at 50% viewport). A reserve without a visible covering
prompt is always a bug.

**How to apply:** if a phantom band returns below the footer, first check the consent host's computed
`opacity` (not just `display`) — a parent opacity:0 hides the whole banner while keeping its height.
