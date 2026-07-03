---
name: Showcase seeder privilege inheritance trap
description: Subclassing ShowcaseAccountSeeder for a new demo account silently inherits the base account's privileged web role unless explicitly gated.
---

`ShowcaseAccountSeeder::ensureUser()` unconditionally grants the `user-admin` web role (a highly privileged role — role/plan management, platform admin permissions) to whatever account it provisions. This is separate from `ensureAdminBridge()` (the back-office `Admin`/super-admin bridge).

When creating a variant showcase/demo account by subclassing `ShowcaseAccountSeeder` and overriding `ensureAdminBridge()` to a no-op, that alone is **not enough** to make the account a plain user — the inherited `ensureUser()` still attaches `user-admin`. Must also gate the role grant itself (e.g. a `shouldAssignUserAdminRole()` hook, overridden false in the subclass, with an idempotent `detach()` cleanup path for accounts seeded before the guard existed).

**Why:** caught by code review after a first pass only stripped the `Admin`/super-admin bridge but missed the separate user-side privileged role grant living in the shared base seeder — a subtle privilege leak in any account meant to be a plain/no-privilege user.

**How to apply:** whenever subclassing/parameterizing a seeder that provisions accounts with special roles, audit every role/permission grant path in the base class individually — don't assume "no admin bridge" implies "no elevated web-guard role" too.
