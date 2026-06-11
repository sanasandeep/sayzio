---
name: Alpine x-transition leave can leave panels stuck open
description: Why dropdown/x-show panels using the x-transition shorthand open but never visually close, and the reliable fix.
---

The `x-transition.opacity.duration.150ms` shorthand on an `x-show` panel (Alpine 3.x) can apply the enter (display:block) correctly but **fail to run the leave cleanup**, so `display` stays `block` even after the bound state goes false. Symptom: a click-toggle dropdown opens but never visually closes (state resets to null, DOM stays visible).

**Why:** the shorthand's leave path depends on transition-end/duration cleanup that doesn't reliably set `display:none` here; the reactive state flips but the DOM effect doesn't hide it.

**How to apply:** for click dropdowns/menus, prefer **enter-only** explicit directives and let leave be instant:
```
x-show="..." x-cloak
x-transition:enter="transition ease-out duration-150"
x-transition:enter-start="opacity-0 -translate-y-1"
x-transition:enter-end="opacity-100 translate-y-0"
```
No leave directives → Alpine hides immediately and reliably. To diagnose, read `getComputedStyle(panel).display` in an **async** `page.evaluate` with a ~300ms wait after the click (Alpine applies `x-show` on a later microtask, so a synchronous read right after `.click()` always shows the stale display value and is misleading).
