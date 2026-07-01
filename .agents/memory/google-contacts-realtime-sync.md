---
name: Google Contacts near-real-time sync
description: How Sayzio keeps Google Contacts in near-real-time — single throttled sync funnel, immediate push on CRUD, tombstone retry safety.
---

# Google Contacts near-real-time sync

Sayzio (`artifacts/1inme`) reflects Google Contacts changes within seconds, not up to 30 min. Four paths converge on ONE throttled funnel so no single account can hammer the Google People API.

## The single funnel
- `GoogleContactsSyncService::syncNow($account, $force=false, $cooldown=15)` is the ONE entry every trigger uses. It wraps `syncAccount()` with a per-account cache cooldown stamp (`google-contacts:sync-at:{id}`) + an atomic `Cache::lock` (`google-contacts:sync-lock:{id}`). Returns `['status'=>'ok'|'throttled'|'in_progress', 'retry_after'?, 'stats'?]`.
- **Never call `syncAccount()` directly from a new trigger** — always go through `syncNow()` or you bypass the throttle/lock.
- `SYNC_COOLDOWN_SECONDS = 15`. Cache store is FileStore (supports atomic locks) — see distant-db-dev-perf.md.

## Triggers (all → syncNow)
1. On-demand: web `GoogleContactsAccountController::syncNow` + API `ContactController::googleSync` (returns status synced/throttled/in_progress). API route also has `throttle:12,1` on top.
2. On web app open: `SyncGoogleContactsOnOpen` middleware (alias `contacts.sync-on-open`, on `dashboard` + `contacts.index` routes). Session-throttled ~60s, dispatches `SyncGoogleContactsJob->afterResponse()`.
3. Scheduled backstop: `contacts:sync` every 2 min, `withoutOverlapping(10)->onOneServer()`; `SyncContactsCommand` calls `syncNow` (skips just-synced accounts). Explicit `--account` run passes `$force=true`.

**afterResponse() runs the job in-process after the HTTP response** — no queue worker needed even with the database queue driver. This is why on-open sync is non-blocking without infra.

## Immediate push on local create/edit/delete
- Both web AND API `ContactController` store/update/merge set `locally_modified_at=now()` and call `pushToGoogleSafely()` (best-effort; only when a `push_enabled` account exists; never fails the request).
- Delete: destroy creates a `ContactDeletionTombstone` FIRST (source of truth, retried by the scheduled drain), then best-effort immediate `deleteFromGoogleSafely()`.
- `attemptTombstoneDelete($account, $tombstone):bool` is the SINGLE place the tombstone retry/backoff lives (success→delete tombstone; fail→increment attempts + last_error). Reused by `syncAccount`'s drain loop and both destroy paths.

**Why:** the tombstone is the durable retry unit; immediate attempts are pure latency optimization. If you add another delete path, park a tombstone + call the shared helper — don't re-implement the delete.

## Untouched invariants
`syncAccount()` still owns incremental `syncToken` + HTTP 410 → full-pull fallback, and the pull-side conflict guard skips rows with a newer `locally_modified_at` than `last_synced_at`. `pushContact` bumps `last_synced_at` past `locally_modified_at` so a just-pushed row isn't re-pushed.
