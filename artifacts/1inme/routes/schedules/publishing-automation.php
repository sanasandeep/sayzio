<?php

/*
|--------------------------------------------------------------------------
| Scheduled jobs — Publishing & Automation
|--------------------------------------------------------------------------
| Timed content publishing, A/B test promotion, queue draining, retry
| sweeps and certificate issuance. The queue drain is protected — pausing
| it silently stalls every queued background job.
|
| Loaded by App\Modules\Admin\Support\ScheduledJobRegistry; registered as a
| live schedule by routes/console.php.
*/

return [
    [
        'key'         => 'biolink:promote-experiment-winners',
        'description' => 'End any Link in Bio layout A/B test whose sample-size or end-date stop condition has been met.',
        'cadence'     => ['hourlyAt', 7],
    ],
    [
        'key'         => 'queue:work',
        'command'     => 'queue:work --stop-when-empty --tries=1 --queue=default',
        'description' => 'Drain queued background jobs (e.g. large contact imports), then exit so the next tick is clean.',
        'cadence'     => ['everyMinute'],
        'protected'   => true,
    ],
    [
        'key'         => 'inbox:retry-forwards',
        'description' => 'Retry transiently failed inbox-forward deliveries (email/webhook) with exponential backoff.',
        'cadence'     => ['everyFiveMinutes'],
    ],
    [
        'key'         => 'blogs:publish-scheduled',
        'description' => 'Flip blog posts whose scheduled time has passed from scheduled to published.',
        'cadence'     => ['everyMinute'],
    ],
    [
        'key'         => 'biolinks:apply-scheduled-themes',
        'description' => 'Activate due Link in Bio theme schedules and revert ones whose window has ended.',
        'cadence'     => ['everyMinute'],
    ],
    [
        'key'         => 'features:notify-launched',
        'description' => 'Email "Notify me" signups once a coming-soon feature transitions to ready (idempotent per interest).',
        'cadence'     => ['hourlyAt', 50],
    ],
    [
        'key'         => 'app-launch:notify-signups',
        'description' => 'Safety sweep for the mobile-app launch mailing list — retries launch emails left unstamped by a transient SMTP blip.',
        'cadence'     => ['hourlyAt', 52],
    ],
    [
        'key'         => 'home:warm-caches',
        'description' => 'Proactively rebuild the home-page and marketing-page caches (home payloads, pricing catalog, site pages, creators directory, demos, blogs index) so no visitor ever hits a cold render over the distant DB.',
        'cadence'     => ['everyFourMinutes'],
        'without_overlapping' => 5,
    ],
    [
        'key'         => 'domains:issue-certificates',
        'description' => 'Issue Let\'s Encrypt certificates for verified custom domains without one (EC2 kit only; no-op unless SSL_AUTO_ISSUE=true).',
        'cadence'     => ['everyTenMinutes'],
    ],
];
