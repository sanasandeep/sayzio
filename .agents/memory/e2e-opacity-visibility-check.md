---
name: e2e opacity visibility check (Playwright)
description: Why Playwright isVisible() misses opacity:0, and the effective-opacity ancestor-walk pattern for asserting scroll-reveal / animated content actually renders
---

Playwright's `locator.isVisible()` / `waitFor({state:"visible"})` report an
element as VISIBLE even at `opacity: 0` (it only checks bounding box + `display`
+ `visibility`, not opacity). So an entrance-animation regression that leaves a
section stuck at opacity 0 — e.g. react-native-reanimated no longer applying the
animated opacity on web, or a `<ScrollReveal>` whose measurement never fires —
sails past a plain visibility assertion.

**The check:** in `page.evaluate`, find the deepest element whose trimmed
`textContent` EXACTLY equals the target copy (document order is pre-order, so the
last match while iterating `querySelectorAll("*")` is the leaf), then walk up the
ancestor chain to the root multiplying `getComputedStyle(el).opacity`. Assert the
product > 0. This catches a stuck wrapper even when the text node's own opacity
is 1 — because reanimated paints the reveal by animating the WRAPPER's opacity,
not the leaf.

**Why the walk (not just the leaf):** the animated opacity lives on the
ScrollReveal `Animated.View` ancestor, several levels above the `<Text>`. Reading
only the leaf's computed opacity always returns 1 and proves nothing.

**How to apply:** poll effective opacity (don't read once) with a generous
deadline (≥8s) to absorb the reveal animation (~480ms) AND, worst case, the
2200ms failsafe timer that force-reveals when measurement never fires. First
`waitFor({state:"attached"})` the copy to prove the screen mounted (routing
regression), THEN measure opacity (animation regression) — the two failure modes
have different messages. See `artifacts/1inme-mobile/scripts/test-info-pages-e2e.mjs`
for the /info/* implementation; it's the integration counterpart to the
source-driven `test-info-scroll-reveal.mjs` unit guard, which never boots the app.

Deep-linking a public expo-router screen: navigate straight to
`${appUrl}info/help` etc. (the /info/* screens are reachable pre-auth from the
login footer). Seeding a signed-in session in `addInitScript` is simply the most
permissive way to guarantee no splash/onboarding/login gate intercepts a direct
deep link.
