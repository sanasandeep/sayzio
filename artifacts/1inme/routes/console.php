<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Hourly: notify assignees of cards due today / overdue. The command itself
// only fires for workspaces whose **local** time (taken from the workspace
// owner's User.timezone) is currently 8 AM, so a UTC server still delivers
// 8-AM reminders in every workspace's own timezone. Dedup-protected so
// reruns in the same local day are no-ops.
Schedule::command('tasks:send-due-reminders')
    ->hourlyAt(0)
    ->withoutOverlapping()
    ->onOneServer();

// Hourly: end any biolink layout A/B test whose stop condition (sample
// size or end date) has been met. Most experiments end inline as
// visitors trickle in, so this is the safety net for low-traffic pages.
Schedule::command('biolink:promote-experiment-winners')
    ->hourlyAt(7)
    ->withoutOverlapping()
    ->onOneServer();

// Nightly: write yesterday's Performance Coach score + component breakdown
// for every active link. Drives the 30-day sparkline in the coach card.
Schedule::command('coach:snapshot-scores')
    ->dailyAt('01:15')
    ->withoutOverlapping()
    ->onOneServer();

// Every 15 minutes: pull external calendar events for all enabled accounts
// and mirror them as Event Invite (.ics) links.
Schedule::command('calendars:sync')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Every 30 minutes: pull/push Google Contacts for all connected accounts.
Schedule::command('contacts:sync')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Every 5 minutes: deliver due dialer call-back reminders (in-app + push),
// once each. The command stamps callback_notified_at so reruns are idempotent.
Schedule::command('dialer:send-callback-reminders')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Every 4 hours: refresh cached follower counts for every connected social
// account so biolink Follow buttons show fresh numbers without ever blocking
// the public page render (renderer always serves the cached value).
Schedule::command('socials:refresh-follower-counts')
    ->everyFourHours()
    ->withoutOverlapping()
    ->onOneServer();

// Hourly: email each opted-in follower a single digest summarising new
// posts/profile changes/published links from creators they follow. The
// command itself filters by each user's preferred local hour (and
// timezone) so a user who picked 8am gets it at 08:00 local time.
Schedule::command('followers:send-digest')
    ->hourlyAt(0)
    ->withoutOverlapping()
    ->onOneServer();

// Hourly: email each opted-in creator a weekly digest of new backlinks
// the browser-extension radar has found pointing at their short links,
// biolink and custom domains in the last 7 days. The command itself
// filters by each user's preferred local weekday + hour (and timezone)
// so a user who picked Friday 5pm in America/Los_Angeles receives the
// digest at 17:00 LA time, not 09:00 UTC like before. Skips users with
// zero new mentions, honours the per-user `backlink_digest` email
// preference, and a 6-day cooldown stops double-sends if a user
// changes their preferred slot mid-week.
Schedule::command('backlinks:send-weekly-digest')
    ->hourlyAt(0)
    ->withoutOverlapping()
    ->onOneServer();

// Hourly: probe verified user-owned custom domains for DNS drift and run
// the takeover-protection state machine. Healthy → drifting transitions
// alert the creator (in-app + email) with the exact CNAME records to fix;
// after the configured grace window (default 7 days) without resolution
// the domain is auto-unverified with a final warning email so the creator
// can recover it later. The domain row is preserved either way so the
// unique-domain claim lock stops anyone else from grabbing the host
// while the original creator sorts DNS out.
Schedule::command('domains:check-health')
    ->hourlyAt(20)
    ->withoutOverlapping()
    ->onOneServer();

// Every minute: drain queued background jobs (currently used by the contact
// importer for files over a few hundred rows). --stop-when-empty makes the
// command short-lived so the scheduler can keep using --withoutOverlapping
// without leaving a worker pinned forever.
Schedule::command('queue:work --stop-when-empty --tries=1 --queue=default')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

// Hourly: notify the assignee (or workspace owner) about Inbox 2.0 threads
// whose SLA has elapsed without a reply. Idempotent — each thread is only
// notified once via the `sla_overdue_notified` flag.
Schedule::command('inbox:check-sla')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

// Every 5 minutes: retry inbox-forward deliveries (email/webhook) that
// failed transiently. Each delivery has its own exponential backoff window;
// permanently failing deliveries get parked as 'dead' after MAX_ATTEMPTS.
Schedule::command('inbox:retry-forwards')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Daily: delete contact-import preview stash files (storage/app/imports/*/*.json)
// that are older than 24h. These are written when a user uploads a CSV and
// shown for confirm/cancel; if the user closes the tab they would otherwise
// linger forever and slowly fill the disk.
Schedule::command('imports:prune-abandoned')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->onOneServer();

// Hourly: pre-emptively swap each connection's near-expiry access_token for a
// fresh one using its stored refresh_token. Keeps long-lived OAuth links alive
// without the user ever pasting a token, and flips truly broken connections
// to status=error so the UI can surface a "Reconnect" button.
Schedule::command('socials:refresh-oauth-tokens')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

// Hourly: charge gateways for subscriptions whose renewal is within 24h,
// and expire any subscription whose grace window has elapsed.
Schedule::command('subscriptions:renew-due')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

// Hourly: email confirmed RSVP guests N hours before each event
// occurrence. The window is configured per event in
// `link.settings.rsvp_settings.reminder_hours_before` (default 24h, set
// to 0 to disable). Idempotent — each (rsvp_id, occurrence) pair is
// recorded in the cache to suppress duplicate sends.
Schedule::command('events:send-rsvp-reminders')
    ->hourlyAt(15)
    ->withoutOverlapping()
    ->onOneServer();

// Every 5 minutes: probe Link Insurance-enabled destinations and run
// failover/restore. The command itself filters by each link's chosen
// cadence (5/15/30/60/240 min) so a 30-min cadence link only actually
// gets probed every sixth scheduler tick.
Schedule::command('links:check-health')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Daily (off-peak): re-run the idempotent click-source backfill so any
// link_clicks rows written without a `source` (seeders, imports, future
// third-party integrations) get tagged mobile_app/web instead of falling
// through to "Unknown" on the Traffic Source card. The one-shot in #381
// cleaned up history; this keeps stragglers from sneaking back in. Chunked
// + capped so a runaway batch can't swamp the DB at night.
Schedule::command('clicks:backfill-source --chunk=1000 --limit=50000')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->onOneServer();

// Daily: refresh near-expiry tokens AND ping every cloud_connection so
// connections whose OAuth was revoked / expired past refresh get flagged
// (last_error populated, sidebar banner appears, owner emailed once).
Schedule::command('cloud-connections:check')
    ->dailyAt('04:15')
    ->withoutOverlapping()
    ->onOneServer();

// Every 15 minutes: re-crawl AI Mind link sources whose configured
// refresh window has elapsed. The command itself caps the per-tick
// fan-out via the admin-set "max_link_refreshes_per_day" knob.
Schedule::command('minds:refresh-links')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Hourly: evaluate the last 24h of Site Assistant cut-offs and notify
// admins (in-app + email) when the abandon rate exceeds the configured
// threshold. The command itself is a no-op when alerts are disabled,
// the sample size is below the configured floor, or the cooldown window
// hasn't elapsed since the last alert — so a per-hour cadence is safe.
Schedule::command('site-assistant:check-cutoffs')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

// Daily (off-peak): trim old site_assistant_cutoff_alerts rows beyond
// the retention window. The analytics page only shows the most recent
// handful of alerts, so anything older is dead weight; without this
// sweep the table would grow forever as alerts keep firing.
Schedule::command('site-assistant:prune-cutoff-alerts --days=90')
    ->dailyAt('03:45')
    ->withoutOverlapping()
    ->onOneServer();

// Every minute: flip blog posts whose scheduled_at has elapsed from
// status=scheduled to status=published. Cheap query (indexed status +
// scheduled_at) so a per-minute cadence keeps the publish window tight.
Schedule::command('blogs:publish-scheduled')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

// Every minute: activate due biolink theme schedules and revert ones
// whose window has ended. Indexed by status+starts_at/ends_at so the
// per-minute query stays cheap. The renderer also applies the active
// theme in-memory as a safety net during the up-to-1-minute gap.
Schedule::command('biolinks:apply-scheduled-themes')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

// Daily (off-peak): re-run the idempotent legacy image shrink so any
// oversized raster files that slipped past the upload-time compress_image
// pipeline (older API clients, future categories newly opted in) get
// downscaled in place. Re-runs are a no-op once the vault is clean. The
// --limit cap stops a single nightly run from monopolising CPU on a big
// vault; stragglers just get picked up the following night.
Schedule::command('images:backfill-reoptimize --chunk=200 --limit=500')
    ->dailyAt('04:45')
    ->withoutOverlapping()
    ->onOneServer();

// First of each month: estimate the prior month's per-biolink CO2 from
// PageSession traffic and (for opted-in links) auto-purchase verified
// carbon offsets via the workspace's connected provider. The command
// itself is idempotent on (link, period_start) so re-running mid-month
// is safe.
Schedule::command('carbon:snapshot-monthly')
    ->monthlyOn(1, '02:30')
    ->withoutOverlapping()
    ->onOneServer();

// Daily (off-peak): delete orphaned conversational-flow visitor uploads
// (storage/app/public/cv_uploads/) older than 7 days that aren't
// referenced by any completed session. Visitors who upload then drop
// off — or re-upload before completing — would otherwise leave files
// on disk forever. Re-uploads on the same step are also cleaned up at
// write time inside ConversationPublicController::captureFile().
Schedule::command('cv-uploads:prune-abandoned --days=7')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->onOneServer();

// Every 30 minutes: pull 3rd-party reviews (Google, Trustpilot, …) into
// external_reviews for all connected providers. Providers without API
// credentials sync a clearly-labelled preview sample so the connect flow
// is demonstrable; creators can also trigger a per-connection manual refresh.
Schedule::command('reviews:sync')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Hourly: detect an out-of-date DB schema (pending migrations) and alert
// admins (in-app + email) before users hit 500s. The production deploy keeps
// serving even when `migrate --force` fails, so this is the automated safety
// net that turns "incomplete schema" into a proactive alert instead of a
// user-facing error. No-op when the schema is in sync; cooldown-guarded so a
// per-hour cadence won't spam admins.
Schedule::command('db:check-pending-migrations')
    ->hourlyAt(10)
    ->withoutOverlapping()
    ->onOneServer();

// Hourly: probe the live DB for workspace-scoping columns (workspace_id /
// created_by_user_id) that are MISSING despite their migration being recorded
// as ran — the half-applied/interrupted-migration failure class that
// db:check-pending-migrations is blind to (it only diffs migration files vs the
// migrations table). When any column is missing, alerts ops admins (in-app +
// email) before users hit SQLSTATE[42703] 500s. Detect-only by default;
// `--repair` adds + backfills in place. No-op when every column is present;
// cooldown-guarded (with a newly-missing-set bypass) so it won't spam admins.
Schedule::command('db:check-workspace-columns')
    ->hourlyAt(25)
    ->withoutOverlapping()
    ->onOneServer();

// Daily (off-peak): re-validate every active page & card template snapshot
// with TemplateSnapshotValidator and alert ops admins (in-app + email) when a
// saved template develops design issues — e.g. a later code change removes a
// block type or retires a design-variant key, silently degrading a snapshot
// that was valid when it was saved. No-op when everything validates;
// cooldown-guarded (with a newly-broken-set bypass) so it won't spam admins,
// and sends an all-clear once every template is valid again.
Schedule::command('templates:check-design-health')
    ->dailyAt('05:10')
    ->withoutOverlapping()
    ->onOneServer();

// Task #1211 — weekly creator digest. Mondays 08:00 UTC. The service
// itself dedupes by users.creator_digest_last_sent_at so reruns inside
// the same week are no-ops, and only emails creators with at least one
// new follower / reaction / comment in the past 7 days.
Schedule::call(fn () => app(\App\Modules\User\Services\CreatorDigestService::class)->sendDueDigests())
    ->weeklyOn(1, '08:00')
    ->name('creator-digest:weekly')
    ->withoutOverlapping()
    ->onOneServer();
