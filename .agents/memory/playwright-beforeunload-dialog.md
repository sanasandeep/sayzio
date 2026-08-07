---
name: Playwright vs beforeunload prompts
description: beforeunload dirty-guard dialogs are auto-dismissed by Playwright, silently cancelling navigation in e2e
---

When a page has a `beforeunload` unsaved-changes guard, any e2e navigation away while dirty triggers a browser dialog Playwright auto-DISMISSES — which CANCELS the navigation. Symptoms: `waitForURL` times out even though the click "worked".

**How to apply:** register `page.on('dialog', d => d.accept())` before navigating away from a dirty page — a `page.once(...)` is not enough when an in-app `confirm()` fires first and the beforeunload prompt follows as a second dialog.

Also: an in-app link guard (capture-phase click listener calling `confirm()`) plus `beforeunload` means TWO dialogs per navigation; tests must handle both.
