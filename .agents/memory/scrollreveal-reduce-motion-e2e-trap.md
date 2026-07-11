---
name: ScrollReveal + reduce-motion e2e trap
description: Why forcing prefers-reduced-motion in a web e2e freezes shared-ScrollReveal sections at opacity 0 instead of revealing them.
---

Forcing Reduce Motion (Playwright `reducedMotion:"reduce"` and/or a `matchMedia`
override) to make ScrollReveal sections paint instantly BACKFIRES: it leaves
every below-fold section stuck at opacity 0 forever.

**Why:** the shared `ScrollReveal` seeds its opacity shared value from
`reduceMotion` on the FIRST render. `useReducedMotion()` starts `false` and only
flips `true` asynchronously (after `AccessibilityInfo.isReduceMotionEnabled()`
resolves). By then the shared value is already 0, and ScrollReveal's
reduce-motion effect branch only calls `setRevealed(true)` — it never sets
`opacity.value=1` and never schedules the ~2200ms failsafe. So the node never
animates up. (The hero title still paints because ITS own effect sets
`heroOpacity.value=1` directly on the reduce-motion branch — a per-component
difference, not a general guarantee.)

**How to apply:** for a runtime paint/e2e assertion on any /info screen (About,
Help, Privacy, Terms) or other AboutPage/InfoPage sections, DO NOT emulate
reduce motion. Leave motion at the default and poll the element's effective
opacity (product of ancestor computed opacities) until it crosses 0 — the
measure trigger or the unconditional failsafe animates it to 1 within a few
seconds. Mid-animation values (e.g. 0.19) are already >0 and count as painted.
