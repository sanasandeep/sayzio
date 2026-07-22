<?php

/*
|--------------------------------------------------------------------------
| Scheduled jobs — Reminders & Digests
|--------------------------------------------------------------------------
| User-facing reminder and digest fan-out. Every job here is idempotent /
| dedup-protected, and the timezone-aware ones fire hourly but only act for
| recipients whose LOCAL time matches their preferred hour.
|
| Loaded by App\Modules\Admin\Support\ScheduledJobRegistry; registered as a
| live schedule by routes/console.php. Cadences are defined here ONCE — see
| the registry docblock for the definition shape.
*/

return [
    [
        'key'         => 'tasks:send-due-reminders',
        'description' => 'Email task assignees about cards due today or overdue (delivered at 8 AM in each workspace\'s own timezone).',
        'cadence'     => ['hourlyAt', 0],
    ],
    [
        'key'         => 'delivery-projects:warranty-reminders',
        'description' => 'Notify Delivery Project creators before/at warranty expiry (8 AM in each workspace\'s own timezone; deduped via persisted stamps).',
        'cadence'     => ['hourlyAt', 0],
    ],
    [
        'key'         => 'calendars:send-today-reminders',
        'description' => 'Notify calendar owners and followers about events happening today (8 AM in each recipient\'s own timezone).',
        'cadence'     => ['hourlyAt', 0],
    ],
    [
        'key'         => 'events:send-new-alerts',
        'description' => 'Alert opted-in users about new public events created near their saved location (instant, or batched at 9 AM local for daily-digest users).',
        'cadence'     => ['hourly'],
    ],
    [
        'key'         => 'dialer:send-callback-reminders',
        'description' => 'Deliver due dialer call-back reminders (in-app + push), once each.',
        'cadence'     => ['everyFiveMinutes'],
    ],
    [
        'key'         => 'dialer:send-note-reminders',
        'description' => 'Deliver due dialer note/to-do reminders (in-app + push + email), once each.',
        'cadence'     => ['everyFiveMinutes'],
    ],
    [
        'key'         => 'dialer:sync-auto-tasks',
        'description' => 'Create/refresh auto tasks in dialer notes from upcoming RSVP\'d/ticketed events and scheduled call-backs.',
        'cadence'     => ['hourlyAt', 10],
    ],
    [
        'key'         => 'contacts:send-follow-up-reminders',
        'description' => 'Deliver due contact/lead follow-up reminders (in-app + email + push, honoring per-channel prefs), once each.',
        'cadence'     => ['everyFiveMinutes'],
    ],
    [
        'key'         => 'followers:send-digest',
        'description' => 'Email opted-in followers a digest of new posts and links from creators they follow, at their chosen local hour.',
        'cadence'     => ['hourlyAt', 0],
    ],
    [
        'key'         => 'backlinks:send-weekly-digest',
        'description' => 'Email opted-in creators a weekly digest of newly found backlinks pointing at their links and domains, at their chosen local weekday + hour.',
        'cadence'     => ['hourlyAt', 0],
    ],
    [
        'key'         => 'users:send-email-verification-reminders',
        'description' => 'Gently remind users who still haven\'t verified their email (self rate-limited).',
        'cadence'     => ['dailyAt', '10:00'],
    ],
    [
        'key'         => 'whatsapp:send-connect-reminders',
        'description' => 'Nudge users who still haven\'t shared a verified WhatsApp number to add one and follow the channel (in-app only, self rate-limited).',
        'cadence'     => ['weeklyOn', 2, '10:45'],
    ],
    [
        'key'         => 'inbox:check-sla',
        'description' => 'Notify assignees about Inbox 2.0 threads whose SLA elapsed without a reply.',
        'cadence'     => ['hourly'],
    ],
    [
        'key'         => 'bookings:send-reminders',
        'description' => 'Email confirmed service-booking visitors a configurable lead-time reminder before their appointment.',
        'cadence'     => ['hourly'],
    ],
    [
        'key'         => 'events:send-rsvp-reminders',
        'description' => 'Email confirmed RSVP guests the configured number of hours before each event occurrence.',
        'cadence'     => ['hourlyAt', 15],
    ],
    [
        // Closure job — runs CreatorDigestService directly; the service dedupes
        // by users.creator_digest_last_sent_at so reruns in-week are no-ops.
        'key'         => 'creator-digest:weekly',
        'callback'    => \App\Modules\User\Services\CreatorDigestService::class . '@sendDueDigests',
        'description' => 'Email creators a weekly digest of new followers, reactions and comments from the past 7 days.',
        'cadence'     => ['weeklyOn', 1, '08:00'],
    ],
];
