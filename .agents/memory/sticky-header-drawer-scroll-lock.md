---
name: Sticky header vs drawer scroll lock
description: body{overflow:hidden} breaks position:sticky — a scrolled sticky marketing header (and its open mobile drawer) snaps off-viewport; pin to fixed while the drawer is open. Plus the scroll-landing layout-shift race in autohide e2e tests.
---

# Sticky header + drawer scroll lock

**Rule:** any UI that sets `body{overflow:hidden}` (mobile drawer, modal) while a `position:sticky` header is on a scrolled page breaks stickiness — the bar (and anything nested in it, like the drawer + its close button) snaps back to the top of the DOCUMENT, off-viewport. The Alpine `:class` state stays correct; only geometry reveals it.

**Why:** with body overflow hidden the scroll container changes, so the sticky element repositions relative to the document. Bug reproduced pre-ship on the marketing header: scroll down, scroll up (header shows), tap hamburger → header+drawer teleport off-screen.

**How to apply:** the marketing header binds `mkt-nav-drawer-open` (Alpine `:class` object syntax) alongside `mkt-nav-hidden`; `.mkt-nav-autohide.mkt-nav-drawer-open` in `marketing-anim.css` pins `position:fixed; top:var(--inme-anno-h,0px)` while the drawer is open. Harmless on the `$fixed` home variant. Assert geometry (`getBoundingClientRect`) in tests, not just class state.

# Autohide e2e scroll-landing race

Instant `window.scrollTo` on a page with late layout shifts (lazy media, reduced-motion reveal differences) fires the down event, then content above the landing point shrinks a few px → browser adjusts scrollY → a tiny *upward* scroll event fires, which the widget correctly treats as "show" and cancels the pending hide. Deterministic under `reducedMotion: reduce` (single instant scroll event), latent flake with smooth scrolling.

Test fix pattern (in `header-autohide.spec.ts`): `scrollTo` waits for a NEAR-target (±48px) STABLE landing (consecutive equal polls), never exact-match; `scrollDownAndWaitHidden` then does one guaranteed 64px down-nudge (> the 24px accumulator) before waiting for the hidden class.
