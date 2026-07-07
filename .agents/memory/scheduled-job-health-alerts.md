---
name: Scheduled-job health alerts
description: Episode-based admin alerting for failed scheduled jobs and a stale scheduler heartbeat; why the watchdog runs on web boots.
---

# Scheduled-job health alerts

`ScheduledJobHealthAlerts` (Admin\Support) alerts ops admins (permission `user.ops_alerts.receive`) via in-app + `system.health_alert` Emailer + InternalAlertDispatcher when a scheduled job run fails or the scheduler heartbeat goes stale, deep-linking to `/admin/cron-jobs` (mobile maps it to `/admin/scheduled-jobs` in `nativeRouteFor`).

**Key design points (durable):**
- **One alert per failure streak**, not per failed run: per-job "episode" state in AppSetting `scheduled_job_health` (`jobs.{key}` / `scheduler`), first success closes with an all-clear — same pattern as SchemaHealth / StorageHealth.
- **A dead scheduler cannot report on itself** — the stale-heartbeat check MUST run from web request boots (`checkFromBoot()` in AppServiceProvider::boot, console-guarded + `Cache::add` throttle), not from a scheduled job. Stale threshold is deliberately much more conservative (15 min) than the panel's ~3 min "stale" badge so brief delays never page.
- **Laravel's ScheduledTaskFinished / ScheduledTaskFailed are mutually exclusive** (ScheduleRunCommand dispatches Finished in try, Failed in catch) — so hooking the recorder's single `finished()` path never double-counts a run.
- Manual "Run now" executions (`scheduled-jobs:run`) feed the SAME streak (source `manual`): a failing job re-run by hand doesn't re-alert, and a successful manual re-run sends the all-clear immediately.
- Any successful `schedule`-source run also closes an open scheduler-stale episode (proof of life), cheaper than waiting for the boot check.

**Admin tuning (mute + stale threshold):** settings live in a SEPARATE AppSetting `scheduled_job_health_settings` (`muted_jobs[]`, `scheduler_stale_after_seconds` clamped 300–86400) — never mixed into the `scheduled_job_health` episode-state key. Muting suppresses opening episodes AND closes an open episode silently on next success (no recovery noise); unmute re-arms fresh streaks. Web control rides the existing failure-alert-settings form (`stale_after_minutes` is now a required field — old tests posting only threshold/cooldown start failing validation); API parity via `/api/v1/admin/scheduled-jobs` alert-settings + `{key}/mute-alerts|unmute-alerts` (mute routes must precede the pause routes in routes/api.php).

**Why:** operators only saw failures if they happened to open the panel; queue-job failures already alerted (`alertOnFailedBackgroundJobs` → webhook only) but scheduled runs were silent — the two systems are separate, don't conflate them.
