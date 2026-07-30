---
name: Infinite animations break Playwright actionability
description: Pages with perpetually-animating wrappers (shimmer/reveal) make click/fill/press time out on the "stable" check; submit forms via evaluate/requestSubmit.
---

Some 1inme user pages (e.g. Buzz create/edit) have perpetually-animating ancestors (`.shimmer`, reveal wrappers), so Playwright's actionability "element is stable" check never passes — `click()`, `fill()`, and `press()` all time out even though the element is visible and enabled.

**How to apply:** for form flows on such pages, set values and submit programmatically:
`locator.evaluate((form, v) => { input.value = v; })` + `form.requestSubmit()` (requestSubmit still fires Alpine `@submit` handlers). Pair with `waitForResponse` on the POST (cold-RDS writes >10s) and a generous `waitForURL(..., waitUntil: 'domcontentloaded')` for the redirect — post-save reloads of heavy editor pages can exceed 15s.

Also: `CheckPlanLimit` middleware now honors `user.plan_limits.bypass` (early return), matching the documented `User::getPlanFeature` contract — e2e specs can unlock plan-gated surfaces by granting the demo user the `user-admin` role.
