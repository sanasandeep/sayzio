---
name: Session driver file-vs-database split
description: Dev/e2e pin SESSION_DRIVER=file; Devices & sessions revoke flows only work on the database driver — how to e2e-test them.
---

Dev `.env` pins `SESSION_DRIVER=file` (a DB session write per request is too
slow over the distant RDS), but the Devices & sessions page
(SessionManagerController) only LISTS and DELETES web sessions when
`config('session.driver') === 'database'`. Under the file driver the page shows
only Sanctum tokens and web-session revocation is a silent no-op.

**Why:** dev perf vs the security feature needing DB-backed sessions.

**How to apply:**
- To e2e-test session revocation, boot the e2e server with
  `SESSION_DRIVER=database bash tests/Browser/run-validation.sh
  sessions-revoke-logout.spec.ts` — `SESSION_DRIVER` is in the
  ServeCommand passthrough allowlist (AppServiceProvider) so the child
  `php -S` sees it. The spec auto-SKIPs (via the missing "This device" row)
  when the driver isn't database, so the plain full-suite gate stays green.
- Revocation semantics are correct: deleting the sessions row means the other
  browser's cookie maps to a fresh EMPTY session on its next request → auth
  middleware bounces to /user/login. Proven end-to-end by
  sessions-revoke-logout.spec.ts (two contexts).
- The shared demo user accumulates stale session rows across parallel envs
  (incl. run-validation.sh's warm-up login); the spec's tinker seed prunes
  `sessions` for the demo user first or the revoke loop blows the time budget.
- Form-submit revokes need `click({ noWaitAfter: true })` + sibling
  waitForNavigation (slow authenticated re-render).
- PRODUCTION FIX (July 28 2026): the deployment run prelude in
  `.replit-artifact/artifact.toml` now exports `SESSION_DRIVER=database`
  before boot. Root cause found the hard way: Autoscale serves from multiple
  machines, file sessions are per-machine local disk, so editor AJAX saves
  landing on the "other" machine failed CSRF/auth silently — "none of the
  edit functionality works" on prod while page loads look fine. Symptom
  fingerprint: blocks' `updated_at` frozen at creation despite active editing.
  Note: first deploy after the switch logs everyone out (file sessions are
  not migrated). Dev `.env` still pins `file` for perf.
