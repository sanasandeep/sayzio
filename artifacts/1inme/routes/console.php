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

// Every minute: drain queued background jobs (currently used by the contact
// importer for files over a few hundred rows). --stop-when-empty makes the
// command short-lived so the scheduler can keep using --withoutOverlapping
// without leaving a worker pinned forever.
Schedule::command('queue:work --stop-when-empty --tries=1 --queue=default')
    ->everyMinute()
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
