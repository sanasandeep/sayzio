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

**Note:** transient `42P01 does not exist` / `42P07 already exists` lines in
`storage/logs/laravel.log` during the migration window are expected (a page hit a
table before its migration ran, or a reconcile pass hit an orphan). Only errors
*after* the batch reaches 0 pending indicate a real problem.
