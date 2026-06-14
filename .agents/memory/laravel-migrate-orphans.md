---
name: Laravel migrate orphans on the distant RDS
description: Why killed `php artisan migrate` runs leave applied-but-unrecorded migrations, and how to finish a large pending batch.
---

Running a large `php artisan migrate --force` batch against the far ap-south-2 RDS
takes many minutes (per-query latency is high). The shell tool kills the process at
its timeout, and the process does NOT survive nohup/setsid/disown in this env.

**The orphan trap:** Laravel commits a migration's `up()` in one transaction, then
logs it to the `migrations` table in a *separate* statement. If the run is SIGKILLed
between COMMIT and the log INSERT, the schema change persists but the migration is
never recorded. The next run retries it and fails with an idempotency error
(`42P07 already exists`, `42P01 / 42704 does not exist`).

**How to finish the batch:** loop `migrate --force`; on a failure whose error is an
idempotency type (already exists / does not exist / duplicate / undefined
object|table), the effects are already present — reconcile by inserting the row into
`migrations` (`batch = max(batch)`) and continue. Abort on any *other* error. Repeat
until `migrate:status | grep -c Pending` is 0. Each pass advances; a ~226-migration
backlog took several bounded passes.

**Why:** avoids re-running half-applied migrations (which would error) while still
completing the sync without code changes.

**Faster than the per-`migrate` loop — a single-boot resumable reconciler:** the
`migrate --force` loop reboots the framework every pass (~10-15s) and only advances
one orphan at a time. Instead, write a temp PHP script that boots the kernel once,
gets the sorted pending files, and for each does `$migration = require $file;` then
`$migration->up()` — record on success (`batch = max+1`), record as orphan on an
idempotency error, abort on anything else. Use `require` (NOT `Migrator::resolvePath`,
which is `protected`); 1inme migrations are anonymous-class files so `require` returns
the object. Give it an internal time budget (~70s) under the shell tool timeout. It is
idempotent/resumable because each row is inserted right after its `up()` commits, so
even a SIGKILL mid-pass keeps all progress — just re-run. A ~220-migration backlog
cleared in ~8 passes this way. **Delete the temp script when done.**

**Two pitfalls of the single-boot approach:**
- *Cached plan must not change result type* (`SQLSTATE[0A000]`): running 100+ DDL ops
  in one PHP process/connection stales Postgres's prepared-statement cache, so a later
  seeder-style migration that SELECTs errors. It is NOT a real failure — a fresh
  process resets the plan cache, so just re-run (the migration is first-pending in the
  new pass and succeeds).
- *Lost stdout on SIGKILL*: piped PHP stdout is buffered, so a pass killed at the shell
  timeout prints nothing even though rows were committed. Don't trust empty output —
  check progress with `DB::table('migrations')->count()` between passes.

**Path-coverage gotcha:** a hand-rolled reconciler that enumerates only
`$migrator->paths()` + `database_path('migrations')` can miss migrations that
`php artisan migrate` finds via module-registered paths. After the reconciler reports
0 pending, always confirm with the real `php artisan migrate` (→ "Nothing to migrate")
and `migrate:status`; mop up any stragglers it surfaces.

**The RDS ledger is SHARED and volatile across concurrent task-agent envs.** All the
parallel isolated envs point at the same distant RDS `Database=postgres`, so the
`migrations` table is shared state. A concurrent task running `migrate:fresh` or a
ledger "dedup" can wipe the ledger down to a handful of rows mid-session — and a
concurrent `migrate:fresh` can drop+recreate tables, so a fresh reconcile then does
real (slow) DDL for the dropped ones, not just orphan-records. Expect to have to
re-reconcile from scratch even right after you finished; it is not your prior work
regressing, it is another agent churning the shared DB. Just re-run the reconciler to
convergence (it handles run-vs-orphan idempotently regardless of the mixed state).

**Note:** transient `42P01 does not exist` / `42P07 already exists` lines in
`storage/logs/laravel.log` during the migration window are expected (a page hit a
table before its migration ran, or a reconcile pass hit an orphan). Only errors
*after* the batch reaches 0 pending indicate a real problem.
