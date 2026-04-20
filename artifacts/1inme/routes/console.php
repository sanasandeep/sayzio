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

// Every 4 hours: refresh cached follower counts for every connected social
// account so biolink Follow buttons show fresh numbers without ever blocking
// the public page render (renderer always serves the cached value).
Schedule::command('socials:refresh-follower-counts')
    ->everyFourHours()
    ->withoutOverlapping()
    ->onOneServer();

// Daily at 09:00: email each opted-in follower a single digest summarising
// new posts/profile changes/published links from creators they follow,
// instead of one email per creator update.
Schedule::command('followers:send-digest')
    ->dailyAt('09:00')
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
