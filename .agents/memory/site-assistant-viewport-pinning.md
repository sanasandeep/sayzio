---
name: Ask Zio panel visualViewport pinning (mobile)
description: How saSyncPanelViewport keeps the chat panel usable when Safari bars / the software keyboard shrink & offset the visual viewport, and how the e2e shim must model it.
---

# Ask Zio panel — visualViewport pinning & keyboard anchoring

`saSyncPanelViewport` in `resources/views/common/partials/site-assistant.blade.php`
keeps the open Ask Zio chat panel usable on small screens as the *visible*
viewport changes mid-session. Two distinct forces:

1. **Height** — Safari iOS animates address/tab bars, shrinking `vv.height`. The
   panel height is pinned to `vv.height - 100` (SA_VV_RESERVE, in lockstep with
   the mobile `100dvh - 100px` CSS), floored at 240.
2. **Position (keyboard)** — the software keyboard both shrinks `vv.height` AND
   shifts `vv.offsetTop`/`pageTop` (Safari scrolls the focused composer into
   view). The panel is `position:fixed` near the *layout* viewport bottom, which
   the keyboard does NOT change, so its bottom edge (the composer) ends up
   occluded behind the keyboard.

**Rule:** height pinning alone is NOT enough for the keyboard case. The visible
region in layout coords is `[offsetTop, offsetTop + vv.height]`; the wrap must be
`translateY`'d up so the composer's rect bottom stays ≤ visible bottom, clamped
so the panel top never rises above the visible top. Transform is cleared on
desktop / when closed / when there's no `window.visualViewport`.

**Why:** without the lift the composer is unreachable when the keyboard is up —
the exact thing a user needs to type. `getBoundingClientRect()` (and CSS fixed
positioning) is relative to the layout viewport, so it does not "see" the
keyboard on its own.

**How to apply / test:** the Playwright shim
(`tests/Browser/site-assistant-visualviewport.spec.ts`) must model BOTH a
settable `height` and a settable `offsetTop`/`pageTop` (getters over a shared
override) — Playwright can't emulate real visualViewport changes. Drive the
keyboard case via `window.__setVisualViewport(height, offsetTop)` and assert the
`.sa-input-row` rect stays inside `[offsetTop, offsetTop+height]`, not merely
`toBeVisible()` (which ignores viewport occlusion). Keep SA_VV_RESERVE in the
spec in lockstep with the blade constant.
