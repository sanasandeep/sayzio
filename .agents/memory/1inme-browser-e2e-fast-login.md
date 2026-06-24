---
name: 1inme browser e2e fast login over distant RDS
description: How to log in (and pick a landing) in 1inme Playwright specs without eating the heavy post-login dashboard/onboarding cold renders.
---

# Fast demo-login for 1inme browser specs

In 1inme Playwright Browser specs, do NOT wait for the post-login page to render.
Two heavy cold Blade renders sit on the default login path over the distant RDS:
the **dashboard** (~20–30s cold) and, if the user isn't onboarded, the
**onboarding wizard** redirect (~18–26s). Waiting for either (e.g.
`Promise.all([waitForURL(/dashboard/), click()])`, the older sibling-spec pattern)
can blow the per-test budget on its own.

**Pattern that avoids both:**
- Submit the demo-login form via `page.evaluate(form.submit())` (not `.click()`),
  so you don't inherit the click's navigation auto-wait (`actionTimeout`, 30s).
- Wait only for the demo-login **POST response**
  (`page.waitForResponse(r => r.url().endsWith('/user/demo-login') && method POST)`).
  Laravel sets the authenticated session cookie on that 302, so the context is
  logged in immediately — no need to follow the redirect render.
- Pre-seed the demo user with `onboarded_at = now()` (idempotent) **before** the
  beforeAll login, or the `RedirectToOnboarding` soft gate
  (`app/Modules/User/Middleware/RedirectToOnboarding.php`, fires on null
  `onboarded_at` for any GET) bounces every login through the slow wizard.

**Why:** the agent bash tool caps at 120s; ephemeral `artisan serve` + cold
renders otherwise can't complete a full editor spec in one call. Background
servers get reaped between tool calls, so the only reliable run unit is a single
bash call that boots the server and runs Playwright together — trimming login
render time is what makes the spec fit.

**How to apply:** use this login helper for any new 1inme Browser spec that then
navigates straight to a deep page (e.g. the block editor at
`/user/links/{id}/blocks`); the dashboard never needs to render.
