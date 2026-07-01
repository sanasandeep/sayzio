---
name: Home e2e — anchor handler + reduced-motion emulation
description: Two gotchas when writing 1inme home-page browser specs — in-page anchor clicks don't set the URL hash, and reducedMotion emulation is unreliable in the headless render.
---

# Home-page browser-spec gotchas

Both bit me while adding the AI-zone browser spec; both cost an extra run to discover.

## In-page anchor clicks never update the URL hash
`home.blade.php` has a global handler on `a[href^="#"]` that calls
`e.preventDefault()` + `target.scrollIntoView({behavior:'smooth', block:'start'})`.
So jump-link / chip clicks do NOT change `location.hash`.

**How to apply:** assert the target section's scroll position (poll
`getBoundingClientRect().top` into a small band near 0), never
`new URL(page.url()).hash`. A hash assertion is guaranteed to fail.

## `reducedMotion:"reduce"` emulation is flaky in this headless render
`test.use({reducedMotion:"reduce"})` / `page.emulateMedia` does not reliably flip
`@media (prefers-reduced-motion: reduce/no-preference)` in the CI Chromium here
(already documented in `home-hero-orbit-popover.spec.ts` ~L80). Consequence: CSS
gated behind `no-preference` (e.g. the AI-suite `.aisx-row` reveal that starts at
`opacity:0` and fills `forwards`) can still run, so an instantaneous opacity read
catches a mid-animation `0`.

**Why:** the emulation is the only lever the blade's reduced-motion fallback
depends on, and it's unreliable here — so don't assert on the *instantaneous*
reduced-motion frame.

**How to apply:** assert the *settled end-state* with `expect.poll` (rows
converge to opacity 1 either way — instantly under a real match, or after the
`forwards` fill). To make animated elements clickable/stable, inject a freeze via
`page.addStyleTag({content:"... {animation:none!important}"})` like the hero spec,
rather than trusting the emulation.
