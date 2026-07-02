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

// Hourly: notify calendar owners and followers about events happening today.
// Recipient-tz aware (User.timezone) — the command only fires for a recipient
// whose local time is 8 AM, so a UTC server delivers 8-AM reminders in every
// recipient's own timezone. Dedup-protected per recipient/event/local-day.
Schedule::command('calendars:send-today-reminders')
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

// Every 15 minutes: inbound half of two-way sync — pull owner edits/deletes from
// connected Google calendars back into published (followable) calendars so
// followers see updates without re-subscribing.
Schedule::command('calendars:pull-published')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Every 2 minutes: near-real-time backstop that pulls/pushes Google Contacts
// for all connected accounts. On-demand triggers + sync-on-open handle the
// interactive path; this catches changes for users who aren't actively in the
// app. withoutOverlapping(10) guards against a slow run overlapping the next
// tick (the lock auto-expires after 10 min); the per-account cooldown inside
// syncNow means this cheaply skips accounts that just synced.
Schedule::command('contacts:sync')
    ->everyTwoMinutes()
    ->withoutOverlapping(10)
    ->onOneServer();

// Hourly: clear caller-ID biolink attachments whose creator is no longer
// reachable to the contact owner (suspended/deactivated/deleted, or has since
// blocked the owner). The read-time gate already hides these, so this is
// low-urgency cleanup that stops a stale attachment silently reappearing if the
// creator becomes reachable again; a block additionally records a detach so it
// can't re-attach after an unblock. Detach memory is always respected.
Schedule::command('contacts:reconcile-attachments')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

// Every 30 minutes: pull inbound CRM contacts (Salesforce/HubSpot/Zoho) into
// Sayzio contacts for every active, pull-enabled Connected Apps connection.
Schedule::command('connected-apps:pull')
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

// Daily: gently nudge users who still haven't verified their email. The
// in-app banner only reaches users while signed in, so this catches the
// ones who skipped verification and rarely log back in. The command is
// self-rate-limited (grace period after sign-up, at most one reminder per
// week, capped at a few total), stops once verified, honours the per-user
// `email_verification_reminder` email preference, and is a no-op under a
// login policy where email verification isn't meaningful. 10:00 UTC keeps
// it clear of the midnight digest fan-out.
Schedule::command('users:send-email-verification-reminders')
    ->dailyAt('10:00')
    ->withoutOverlapping()
    ->onOneServer();

// Daily: nudge free Starter-plan users to re-confirm their plan once a year,
// near the end of their rolling 1-year free window. Reminder only — lapsing
// never locks the account or downgrades anything; a one-click link renews the
// free window for another year. Self-rate-limited (once per window, only
// within the lead time of lapsing), honours the `starter.free_window_renewal`
// preference. 10:30 UTC keeps it clear of the digest fan-out.
Schedule::command('starter:send-free-window-reminders')
    ->dailyAt('10:30')
    ->withoutOverlapping()
    ->onOneServer();

// Daily: remind fans a few days before a creator subscription they pay for
// auto-renews (email + in-app), naming the creator, amount, billing cycle,
// exact renewal date and a manage/cancel link. Heads-up only — it does not
// change how/when renewals are actually charged. Self-rate-limited (once per
// billing period via renewal_reminder_sent_at, only within the lead window),
// skips canceled / cancel-at-period-end / non-active / free subs, and honours
// the `billing.creator_sub_renewal_reminder` preference. 10:45 UTC keeps it
// clear of the digest fan-out.
Schedule::command('creator-subscriptions:send-renewal-reminders')
    ->dailyAt('10:45')
    ->withoutOverlapping()
    ->onOneServer();

// Weekly (Tue 10:45 UTC): nudge users who still haven't shared a verified
// WhatsApp number to add one and follow our channel (in-app only). Self
// rate-limited — stops once a number exists, honours the dashboard nudge's
// one-week snooze, respects a per-user cooldown, and skips brand-new accounts
// for a short grace window so it never overlaps the post-registration step.
Schedule::command('whatsapp:send-connect-reminders')
    ->weeklyOn(2, '10:45')
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

// Hourly: probe the live DB for tables/columns the code depends on that are
// MISSING despite their migration being recorded as ran — the expected schema is
// now derived automatically by replaying the migration files (SchemaManifest),
// so any drifted column is caught, not just a hand-curated few — the edited-after-applied
// drift class that db:check-pending-migrations is blind to (a recorded migration
// later changed to add columns is never re-run, yet `migrate:status` shows 0
// pending). This took the public /creators page down via the 18+ columns on
// `users`. When any column is missing, alerts ops admins (in-app + email) before
// users hit SQLSTATE[42703] 500s. Detect-only; the fix is `migrate --force`
// (guarded additive migrations). No-op when every column is present;
// cooldown-guarded (with a newly-missing-set bypass) so it won't spam admins.
Schedule::command('db:check-expected-columns')
    ->hourlyAt(40)
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

// Hourly: detect an empty onboarding template gallery (zero active page
// templates). The onboarding wizard gracefully degrades to a "No templates
// available yet" escape when a persona/plan has nothing to offer — the right
// safety net for the end user, but it fails silently for the operator, so new
// users can quietly land on a bare setup screen for days. This is the automated
// safety net: it alerts ops admins (in-app + email) when the gallery goes empty
// and sends an all-clear once a template is added/re-activated. No-op when at
// least one active template exists; cooldown-guarded so it won't spam admins.
Schedule::command('templates:check-gallery')
    ->hourlyAt(55)
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

// Task #2106 — hourly: revert accounts whose admin-granted comp /
// time-limited plan window has elapsed back to the default plan. Idempotent
// (only touches comp_plan_expires_at <= now and clears the markers).
Schedule::command('plans:revert-expired-comps')
    ->hourlyAt(30)
    ->withoutOverlapping()
    ->onOneServer();

// Task #2106 — hourly: auto-lift admin temporary holds whose scheduled
// reactivation date has arrived. The login path also clears elapsed holds,
// so this catches accounts whose owner never signs in during the window.
Schedule::command('users:reactivate-due')
    ->hourlyAt(35)
    ->withoutOverlapping()
    ->onOneServer();

// Hourly: housekeep GDPR/CCPA privacy data requests — expire unverified
// requests whose email link has lapsed, prune stale export archives once
// their download window closes, and dispatch any approved deletion whose
// cooling-off window has elapsed (safety net for the delayed queue job).
// Fully idempotent so a per-hour cadence is safe.
Schedule::command('privacy:maintenance')
    ->hourlyAt(45)
    ->withoutOverlapping()
    ->onOneServer();

// Every minute: fold append-only click counter_deltas into the links /
// biolink_blocks running totals. A single scheduled worker owns the fold, so
// high-frequency clicks never contend on hot counter rows (the redirect path
// only appends a delta). RecountLinkStats remains the periodic backstop.
Schedule::command('analytics:flush-counters')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

// Daily: finalize the previous day(s) into the link_click_daily rollup tables
// and re-roll a short trailing lookback for late-arriving clicks. Dashboard
// reads serve finalized days from the rollup and only the current day from raw,
// keeping analytics fast as link_clicks grows into the 100M-row range.
Schedule::command('analytics:rollup-daily')
    ->dailyAt('03:45')
    ->withoutOverlapping()
    ->onOneServer();

// Monthly: provision future month partitions for the tracking tables so insert
// targets always exist ahead of time. No-op until the tables are converted to
// partitioned (see tracking:setup-partitions), so it is safe to always schedule.
Schedule::command('tracking:maintain-partitions')
    ->monthlyOn(1, '02:30')
    ->withoutOverlapping()
    ->onOneServer();

// Daily: prune raw click & visitor-session history older than the largest
// stats-retention window across all active plans, so the analytics tables
// don't grow without bound. No-op while any active plan keeps history forever
// (retention = -1) UNLESS a hard physical cap (stats.hard_max_days) is set;
// either way it surfaces table growth and alerts admins on unbounded growth.
// Drops whole expired partitions when the tables are partitioned. Runs off-peak
// and is chunked to bound DB load.
Schedule::command('stats:prune-history')
    ->dailyAt('04:05')
    ->withoutOverlapping()
    ->onOneServer();

// Daily (off-peak): prune the email_logs activity table so it can't grow
// forever. Every outbound email writes a row (subject + full rendered body),
// so across ~70 email types this table bloats the shared RDS and slows the
// admin Email Log screen. Two tiers (windows configurable via AppSetting):
// null the heavy stored body of rows older than `email_logs.body_retention_days`
// (keeps the audit row), then delete whole rows older than
// `email_logs.retention_days`. A -1 window disables that tier (keep forever);
// chunked + capped so a single run can never mass-mutate the table.
Schedule::command('email-logs:prune-history')
    ->dailyAt('04:25')
    ->withoutOverlapping()
    ->onOneServer();

// Daily: generate concrete client invoices from active recurring-invoice
// templates whose next_run_date is due, then advance each template's
// schedule. Idempotent per template per due date (next_run_date is advanced
// after each run), so a missed day self-heals on the next run.
Schedule::command('invoices:run-recurring')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->onOneServer();

// Hourly: close the loop on "Notify me" signups from the app-wide "Coming
// soon" system. When a feature transitions coming_soon → ready because its
// underlying integration/config finally connected (the path with no
// synchronous code hook — the admin override screen already fires this
// inline when an admin clears an override), everyone who asked to be notified
// is emailed once. Idempotent: only un-notified interests are considered and
// each is stamped as it's processed, so a per-hour cadence never double-sends.
Schedule::command('features:notify-launched')
    ->hourlyAt(50)
    ->withoutOverlapping()
    ->onOneServer();

// Hourly: safety sweep for the mobile-app launch mailing list. The marketing
// settings save already emails the "coming soon" signups the moment an admin
// sets a store URL (empty → live). This retries any signup left unstamped by a
// transient SMTP blip during that inline send, so a launch email is never
// permanently dropped. No-op while no store URL is configured; only un-notified
// signups are considered, so a per-hour cadence never double-sends.
Schedule::command('app-launch:notify-signups')
    ->hourlyAt(52)
    ->withoutOverlapping()
    ->onOneServer();
