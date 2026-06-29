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
