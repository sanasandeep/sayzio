---
name: e2e demo user bypasses banned names
description: Why 1inme browser e2e specs can't assert the "banned alias" availability state via demo-login.
---

The shared `demo@1inme.com` account that every 1inme browser e2e spec logs in
as (via the rate-limited demo-login route) is provisioned with the `user-admin`
role, which holds `user.banned_names.bypass`.

Consequence: the live alias-availability checker (`AliasAvailability::check`,
backing `user.links.check-alias`) runs `NotBannedName` with `allowBypass=true`,
so for this privileged user a banned name resolves to **available**, not
**banned**. Seeding a `BannedName` row + flushing `BannedNameChecker` cache does
NOT make the checker report "reserved" for the demo user.

**How to apply:** in a browser e2e spec, do not assert the banned/"reserved"
availability state — it is genuinely not shown to the demo account. Cover the
"blocks Continue" guard with a *taken* alias (seed a link owning it) instead,
and use an invalid-format value (e.g. `"bad alias!"`) for a third, account-
independent state — the alpha_dash format check runs before the bypass branch.

Also: the demo user's plan alias min-length is <=2 here, so `"ab"` reads as
available — don't rely on "too short" either; use the illegal-character path.

So the banned-block must be verified with an HTTP **feature test** (not the
browser gate): act as a freshly-created plain User — no roles ⇒ owner of its
own workspace ⇒ passes `workspace.can:links.create` with no bypass — seed a
`BannedName` + flush its checker cache, then assert the rejection on both the
live check and the submit. Add a bypass contrast by syncing the seeded
`user-admin` web role. Roles/permissions ARE seeded in the RefreshDatabase
test DB, so the role-sync trick works there.

## Demo-login account vs spec seed email mismatch (July 2026)

`demoLogin` (web `/user/demo-login` + API OTP demo path) now signs in
**sazioapp@gmail.com**, while most browser e2e specs still seed fixtures under
**demo@1inme.com**. Specs that only need "any logged-in user" (voice panel,
nav/menus) still pass; any spec that opens a *seeded* resource as its owner
(biolink block editor etc.) hits the controller's
`$link->user_id !== workspace_owner_id()` guard and 403s deterministically
(generic "No access" page, `.block-card` never renders).

**How to apply:** when a browser e2e spec 403s on an owner-scoped page right
after demo-login, first diff the email in the spec's tinker seed against the
one in `AuthController::demoLogin` — keep them identical (best: read the login
email from a single shared constant/helper rather than hardcoding it per spec).
The generic "No access" (Error 403) page renders instead of the seeded surface,
so ANY `.section-card`/`.block-card` the spec asserts is simply absent — the
failure looks like "the feature stopped rendering" but is really ownership.
Confirmed fix for the two Audience-Insights specs (audience-prompt-flow,
audience-estimate-stale): swap the seed email to sazioapp@gmail.com. NOTE the
still-hardcoded `demo@1inme.com` seeds remain in other specs (biolink editor
family, links-stats, dashboards, dialer, events, header, onboarding, voice) —
same latent 403 for any that open a seeded owner-scoped resource.
