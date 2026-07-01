---
name: Forcing 1inme user-layout theme in Playwright
description: How to reliably capture dark vs light screenshots of authenticated 1inme user pages.
---

The 1inme user layout picks its theme purely from a server-rendered `light-mode`
html class (added when the `1inme_theme` cookie != 'dark'); there is NO client
boot script that re-derives theme from system preference. The `themeToggle()`
Alpine component only reads/writes that class + cookie on user click.

**Rule:** to force dark/light for a screenshot, toggle the exact `light-mode`
class the CSS + Chart.js theming read, AFTER navigation:
`page.evaluate(m => document.documentElement.classList[m==='dark'?'remove':'add']('light-mode'))`.

**Why:** setting the `1inme_theme` cookie via `context.addCookies` did NOT flip
the rendered theme (both dark+light shots came out light) — likely cookie
plumbing / encryption interaction. Toggling the class is bulletproof because it
is the single thing all theme-dependent CSS and the chart MutationObserver key
off.

**How to apply:** after toggling, wait ~300ms+ for Chart.js's observer to
re-theme. Note bento reveal + count-up animations need longer to settle — a
mid-flight capture looks hazy with blank KPI numbers (not a bug); the
reduced-motion / later-captured light shot shows the true rendered state.
