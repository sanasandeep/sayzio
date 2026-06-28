---
name: Post-merge migrate serialization (advisory lock)
description: Why post-merge migrate must run under a cross-process Postgres advisory lock, fail-closed.
---

# Post-merge migrate serialization

Post-merge runs `php artisan migrate:guarded`, NOT a bare `migrate || reconcile`
chain. `migrate:guarded` (a custom command) holds a session-scoped Postgres
**advisory lock** for the whole run, then does `migrate --force` with a
`db:reconcile-migrations --force` fallback inside the same lock.

**Why:** every isolated env + every merge's post-merge + the deployed app share
one distant RDS `postgres` DB. When two merges land close together their migrate
runs execute concurrently against that one DB, which produced intermittent
post-merge failures:
- one run ALTERs a table (e.g. `site_pages`) while another holds a cached
  `select *` plan → `SQLSTATE[0A000] cached plan must not change result type`;
- a backlog drain that stops partway, leaving the schema half-applied (and the
  far-future migration the homepage needs, e.g. `plans.is_internal`, unapplied).
Per-migration guards (hasTable etc.) can't fix this — it's a concurrency
problem, so the runs must be serialized.

**How to apply / invariants to preserve:**
- **Fail CLOSED.** If the lock can't be acquired within the timeout (another run
  is migrating) or the lock probe errors, `migrate:guarded` SKIPS migrating and
  exits 0 — it must NEVER migrate unlocked, or the race returns. Leftovers are
  caught by the holder, the next merge's locked run, or the hourly
  `db:check-pending-migrations` alert. (An earlier fail-OPEN version was rejected
  in review for exactly this reason.)
- Lock auto-releases on connection close, so a dying run can't deadlock others;
  explicit `pg_advisory_unlock` runs in `finally` as well.
- Non-pgsql connections skip the lock and run directly (no shared-RDS race).
- This only changes WHEN migrate runs, never WHAT it does — it stays additive.
- Verify a behavior change with the fail-closed smoke test: hold
  `pg_advisory_lock(<key>)` in a background php process, then run
  `migrate:guarded --timeout=3` and confirm it prints the skip message + exits 0.
