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
