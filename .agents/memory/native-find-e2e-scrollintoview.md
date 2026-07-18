---
name: Native find-in-page can't be automated; scrollIntoView is the faithful proxy
description: How to e2e-test browser find-in-page (Ctrl/Cmd+F) blank-out/scroll regressions in Playwright when the find bar itself is unreachable.
---

The browser's native find bar (Ctrl/Cmd+F) CANNOT be driven from Playwright:
it lives in the browser's chrome/UI process, not the page renderer. Playwright
key events (and CDP `Input.dispatchKeyEvent`) are delivered to the renderer, so
`Ctrl/Cmd+F` never opens or types into the find bar, and headless Chromium has
no find UI at all. CDP exposes no find-in-page command
(`Page.handleJavaScriptDialog` is for JS alert/confirm/prompt only, unrelated).

**Faithful proxy:** when native find locates a match it reveals it by scrolling
the match's nearest scrollable ancestor(s) into view — the exact routine
`Element.scrollIntoView()` invokes. So drive `match.scrollIntoView({block:'start'})`
on the matched element (block:'start' = worst case, pins the match to the top and
pushes everything above it out).

**Why:** this is stronger than hard-coding `someScroller.scrollTop = …` — it does
NOT presume WHICH element scrolls (the browser resolves that), so it still
reproduces a scroll/blank regression reintroduced through a *different* scroll
container after a refactor.

**How to apply:**
- The match target must be RENDERED/visible text — native find AND `window.find()`
  both skip `display:none`. For a collapsed accordion/nav group, the honest match
  is the still-visible group HEADER, never its hidden children.
- Fire `window.find(term)` too as the literal JS twin, but keep it best-effort /
  un-asserted (engine-dependent).
- Assert no element is painted OUTSIDE its clipping box over a sibling
  (`elementFromPoint` at its center once its rect is past the box edge).
- Guarded example: `artifacts/1inme/tests/Browser/sidebar-findbar.spec.ts`
  (the sidebar `<nav>` = paint boundary + inner `.sidebar-nav-scroll`).
