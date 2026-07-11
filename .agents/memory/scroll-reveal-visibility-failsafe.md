---
name: ScrollReveal visibility failsafe
description: Why entrance-animation reveals need an unconditional backstop and how the source-driven test guards it
---

# ScrollReveal must never leave a section stuck at opacity 0

`components/ScrollReveal.tsx` (1inme-mobile) starts sections at opacity 0 and
reveals them only when a scroll-driven `measure()` says they're near/in the
viewport. That gamble can silently fail (measure callback never fires, ref is
immeasurable/null, content is off-screen and never scrolled to) — leaving
InfoPage-backed screens (help/terms/privacy/nfc) and AboutPage permanently
blank. Typechecking can't catch this.

**Rule:** any measure-gated entrance reveal must have an unconditional backstop.
Implementation splits the reveal into an idempotent `applyReveal()` (guarded by
`triggered.current`), `reveal()` falls back to `applyReveal()` when the ref
can't be measured, and the reveal effect schedules a `setTimeout(applyReveal,
2200)` failsafe (cleared on cleanup). Reduce Motion still reveals instantly at
first paint (opacity initialises to 1).

**Why:** entrance animations that hide-then-reveal are a content-loss risk, not
just a polish detail; the "reveal on scroll" path is not guaranteed to run.

**Test:** `scripts/test-info-scroll-reveal.mjs` (in `test:unit` + strict) is
source-driven — it lifts the real applyReveal/reveal/effect bodies via
`scripts/lib/extract.mjs`, so its regex anchors depend on the exact useCallback
dep arrays and effect deps in ScrollReveal; keep them in lockstep when editing.
It asserts a failsafe timer is scheduled — verified to FAIL if the timer is
removed. Shared-value initialisers lifted from `useSharedValue(...)` can capture
a trailing comma (multi-line ternary), strip with `.replace(/,$/, "")`.
