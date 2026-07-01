---
name: Cookie-consent scroll lock scope
description: which cookie-consent layouts may lock page scroll, and how it's enforced in the widget.
---

In `common/partials/cookie-consent.blade.php` the consent widget decides scroll
locking in JS via `wantsScrollLock()` (keyed on the *live* layout =
`state.layoutOverride || cfg.layout`):

- **Only** `modal` and `takeover` (the full-screen blocker layouts) may lock page
  scroll. `lockScroll()` sets `overflow:hidden` on both `<html>` and `<body>` and
  stashes prior inline overflow; `hide()` calls `unlockScroll()` to restore.
- **Bar layouts** — `banner` (default), `inline`, `corner`, `pill` — must NEVER
  lock scroll. Visitors have to be able to keep reading marketing pages
  (Pricing/Features) before deciding on cookies.

**Why:** a bottom-bar prompt that blocks scrolling traps the visitor; the product
direction is a non-blocking bar (default was moved modal→banner for the same
reason). The modal/takeover lock is the deliberate "hold them on the prompt"
behavior, matching the code's "deliberately full-screen blockers" comment.

**How to apply:** the one-shot `inline/pill → modal` "Customize" upgrade
re-enters `show()`, so keeping `wantsScrollLock()` on the live layout means it
correctly locks while the customize sheet is open and unlocks on `hide()`. Don't
add a blanket body-lock at show time — gate it on the live layout. Regression:
`tests/Browser/cookie-consent-scroll.spec.ts` (loads /pricing, asserts a bar
layout scrolls without dismissing consent). Note it is a standalone spec, not
wired into the `.replit` e2e gate.
