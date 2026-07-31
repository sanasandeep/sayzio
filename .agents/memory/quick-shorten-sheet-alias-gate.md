---
name: Quick-shorten sheet alias gate
description: Mobile quick-shorten sheet blocks submit on negative alias verdict; two e2e harnesses assert opposite-era behaviors and the ✓/✕ prefix is client-rendered.
---

The mobile Links-tab quick-shorten sheet disables the Shorten button (plus a submit-handler guard with an inline message) while the live alias check reports taken/invalid/banned/too-short. Blank alias (auto-generate) never blocks.

**Why:** prevents a wasted round-trip over the slow cross-region API; server 422 remains the backstop.

**How to apply:**
- Two e2e harnesses cover this sheet: `test-quick-shorten-sheet-e2e.mjs` (happy path) and `test-quick-shorten-backhalf-real-api-e2e.mjs` (negative path). The negative harness now asserts NO POST fires on a taken alias — reverting to "submit anyway → 422 inline" breaks it.
- The ✓/✕ verdict prefix is rendered CLIENT-side (`✓ ${message}` in links.tsx); server AliasAvailability messages carry no prefix. The sheet harness waits for `/^✓ /`, so dropping the prefix makes it time out at the verdict step (looks like slow-RDS flakiness but reproduces every run).
- RN-web Pressable disabled state is asserted via aria-disabled — keep `accessibilityState={{disabled}}` in lockstep with `disabled`.
