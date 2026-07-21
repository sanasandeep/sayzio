---
name: Same-URL redirect makes waitForURL a no-op
description: Playwright gotcha when a form POST redirects back to the page's own URL (PRG on the same route).
---

When a non-AJAX form POST redirects back to the SAME URL the page is already on (e.g. review page → confirm → back to the show page), `page.waitForURL(pattern)` resolves instantly against the *current* page, so assertions then poll the stale pre-submit DOM and time out even though the server round-trip succeeded.

**Why:** waitForURL only checks the URL, not that a navigation happened. Combined with a slow post-write render (distant RDS), the fresh page arrives after the default 10s expect budget.

**How to apply:** for same-URL PRG flows: click with `{ noWaitAfter: true }`, `waitForResponse` on the POST (long timeout for cold first writes), then let the FIRST post-redirect assertion carry a generous `toBeVisible({ timeout: 90_000 })` — expect auto-retries across the navigation. Example: brand-studio-flow.spec.ts.
