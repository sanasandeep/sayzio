---
name: Playwright reduced-motion emulation gotchas (1inme browser gate)
description: Why reducedMotion:"reduce" doesn't force a demo's JS branch, and how to assert on multi-scene animated partials.
---

In the 1inme Playwright browser gate (`artifacts/1inme/tests/Browser/`, run via
`run-validation.sh` on a standalone server), two non-obvious traps when testing
reduced-motion behaviour of animated Blade partials:

1. **`test.use({ reducedMotion: "reduce" })` / `emulateMedia` does NOT reliably
   reach the page's own `window.matchMedia('(prefers-reduced-motion: reduce)')
   .matches` read.** A partial that branches on that JS read at init will run its
   MOTION path even though the CSS `@media (prefers-reduced-motion: reduce)` block
   may be emulated. To force the JS reduced-motion branch deterministically,
   install an `addInitScript` that overrides `window.matchMedia` for just the
   reduce query (return `{matches:true,...}`) and delegates all other queries to
   the real impl. Install it BEFORE the demo script runs.
   **Why:** the Chromium emulation surfaced via Playwright in this env flips the
   CSS media but not the scripting read, so a JS-gated fallback never engages.

2. **Animated demos often pre-render ALL scenes into the DOM** (e.g. the AI
   dashboard demo renders 5 preset `[data-aidd-scene]`, only scene 0 is
   `is-active`/`is-playing`; the rest are `display:none` at base `opacity:0`).
   A bare `[data-aidd-tile]` selector then reads opacity 0 from hidden-scene
   tiles and a "fully visible" assertion fails. **Scope tile/visibility
   assertions to the active scene** (`[data-aidd-scene].is-active [data-aidd-tile]`).
   `getComputedStyle().opacity` still returns the base 0 for `display:none`
   elements, so this is invisible until you read it.

**How to apply:** any new reduced-motion or "static fallback renders" browser
spec in this gate should (a) force the JS branch via the matchMedia init-script
override, and (b) assert only on the visible/active portion of a partial that
pre-renders multiple states.
