---
name: Scheduled jobs registry & admin panel
description: How the registry-driven scheduler, pause/resume, run-now and run history fit together; lockstep surfaces when adding a scheduled job.
---

# Scheduled jobs registry & admin panel

The 1inme schedule is registry-driven: `ScheduledJobRegistry` loads `routes/schedules/<group>.php` files (each returns an array of job defs: key, description, group, cadence `[method, ...args]`, command XOR callback, optional `protected`, `without_overlapping` minutes), and `routes/console.php` is a thin foreach that registers every def with `->name($key)` (callbacks) / `->description(...)`, `->withoutOverlapping()->onOneServer()->skip(isPaused)`.

**Adding a scheduled job = add a def to the right `routes/schedules/*.php` file only.** Never schedule directly in console.php — the lockstep guard test (`ScheduledJobsPanelTest::test_every_registry_job_materialises_as_exactly_one_schedule_event`) fails if schedule event count ≠ registry count.

**Why:** the admin "Scheduled Jobs" panel (web `/admin/cron-jobs`, API `/api/v1/admin/scheduled-jobs`) derives everything (grouping, purpose, protected/paused badges, pause/resume, run-now) from the registry; an unregistered event lands in "Other" with no key and no actions.

Key mechanics:
- Pause state is an AppSetting (`scheduled_jobs.paused`, via put() so cache invalidates); `pausedKeys()` fails open (empty) on DB errors so schedule registration never breaks. `pause()` throws on protected jobs — controllers also guard for friendly messages.
- Run history rows in `scheduled_job_runs` (model timestamps=false); written by ScheduledTaskStarting/Finished/Failed listeners (source=`schedule`) and by `scheduled-jobs:run {key}` (source=`manual`). Recorder maps events→keys via `Event::normalizeCommand` + registry commandKeyMap; callback events matched by name==key.
- Run-now spawns `nohup php artisan scheduled-jobs:run <key> &` (Process::fromShellCommandline); in tests it runs synchronously in-process (`app()->runningUnitTests()`), so assertions can read the DB row immediately.
- CronJobsInspector merges cache-based CronRunLog with the latest finished DB row (newer wins) and degrades gracefully pre-migration (try/catch around the DB query).
- Testing pause: `event->filtersPass(app())` flips false when paused — no need to actually run the scheduler.
