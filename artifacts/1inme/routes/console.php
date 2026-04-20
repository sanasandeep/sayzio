<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

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

// Hourly: pre-emptively swap each connection's near-expiry access_token for a
// fresh one using its stored refresh_token. Keeps long-lived OAuth links alive
// without the user ever pasting a token, and flips truly broken connections
// to status=error so the UI can surface a "Reconnect" button.
Schedule::command('socials:refresh-oauth-tokens')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();
