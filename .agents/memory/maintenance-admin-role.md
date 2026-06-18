---
name: Maintenance admin-role concept
description: What "admin" means across guards in 1inme, and the gotcha that global middleware runs before route auth.
---
1inme has two parallel admin identities, both backed by the shared `roles` table (it has a `guard` column):
- **Admin guard** — the back-office `Admin` model (session).
- **Web-guard platform admin** — a regular `User` who has a role attached whose `roles.guard === 'web'` (managed under `/user/access`). These grant `user.*` platform permissions; a normal end-user has zero web roles.

**Rule:** to gate something to "any admin", check ALL THREE actor paths: admin guard, session web-guard user, AND token-authenticated API caller. Skipping the third silently blocks/leaks for mobile/JSON clients.

**Why this bites:** the maintenance middleware is registered GLOBALLY, so it runs BEFORE route-level `auth:sanctum`. At that point a bearer-token API request has NOT populated the session web guard — you must resolve the token user yourself (the `sanctum` guard resolves as a `RequestGuard` on demand, even though `config('auth.guards')` has no `sanctum` entry; sanctum's config `guard` is `['web']`). A first attempt that only checked admin+web guards passed web tests but blocked admin API callers.

**How to apply:** any web-role lookup must be defensively try/catch-wrapped — isolated/un-migrated task-env DBs often lack `user_roles` and would otherwise 500. Runtime-testing the web/token admin bypass locally fails for the same missing-table reason; trust CI / production (and the RefreshDatabase feature test) where the table exists.
