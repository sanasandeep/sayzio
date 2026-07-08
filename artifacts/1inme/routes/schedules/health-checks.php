<?php

/*
|--------------------------------------------------------------------------
| Scheduled jobs — Health Checks & Alerts
|--------------------------------------------------------------------------
| Automated safety nets that probe the platform (DB schema, storage config,
| domains, link destinations, template snapshots) and alert ops admins
| before users hit errors. The db:* schema probes are protected — pausing
| them would blind the platform to half-applied migrations.
|
| Loaded by App\Modules\Admin\Support\ScheduledJobRegistry; registered as a
| live schedule by routes/console.php.
*/

return [
    [
        'key'         => 'parity:check-mobile-docs',
        'description' => 'Recurring mobile/docs parity audit — surfaces web block/link types missing mobile or docs coverage.',
        'cadence'     => ['weekly'],
    ],
    [
        'key'         => 'domains:check-health',
        'description' => 'Probe verified custom domains for DNS drift and run the takeover-protection state machine.',
        'cadence'     => ['hourlyAt', 20],
    ],
    [
        'key'         => 'links:check-health',
        'description' => 'Probe Link Insurance destinations and run failover/restore on each link\'s chosen cadence.',
        'cadence'     => ['everyFiveMinutes'],
    ],
    [
        'key'         => 'site-assistant:check-cutoffs',
        'description' => 'Alert admins when the Site Assistant abandon rate exceeds the configured threshold.',
        'cadence'     => ['hourly'],
    ],
    [
        'key'         => 'db:check-pending-migrations',
        'description' => 'Alert admins when the database schema is out of date (pending migrations) before users hit 500s.',
        'cadence'     => ['hourlyAt', 10],
        'protected'   => true,
    ],
    [
        'key'         => 'storage:check-s3-config',
        'description' => 'Detect a misconfigured S3 user-content storage backend and alert ops admins before uploads pile up failures.',
        'cadence'     => ['hourlyAt', 15],
    ],
    [
        'key'         => 'db:check-workspace-columns',
        'description' => 'Probe the live DB for missing workspace-scoping columns from half-applied migrations and alert admins.',
        'cadence'     => ['hourlyAt', 25],
        'protected'   => true,
    ],
    [
        'key'         => 'db:check-expected-columns',
        'description' => 'Probe the live DB for any code-required columns missing despite their migration being recorded as run.',
        'cadence'     => ['hourlyAt', 40],
        'protected'   => true,
    ],
    [
        'key'         => 'templates:check-design-health',
        'description' => 'Re-validate saved page/card template snapshots and alert admins when one develops design issues.',
        'cadence'     => ['dailyAt', '05:10'],
    ],
    [
        'key'         => 'queue:check-backlog',
        'description' => 'Alert ops admins when the database queue backlog is unhealthy (stale pending jobs — worker likely down, login events/mail undelivered), with an all-clear once it drains.',
        'cadence'     => ['everyTenMinutes'],
        'protected'   => true,
    ],
    [
        'key'         => 'scheduled-jobs:check-failures',
        'description' => 'Watchdog: alert ops admins when a scheduled job racks up 3+ consecutive failed runs, with an all-clear once it succeeds again.',
        'cadence'     => ['hourlyAt', 35],
    ],
    [
        'key'         => 'templates:check-gallery',
        'description' => 'Alert ops admins when the onboarding template gallery goes empty (zero active page templates), and all-clear once restored.',
        'cadence'     => ['hourlyAt', 55],
    ],
    [
        'key'         => 'bg-templates:check-library',
        'description' => 'Alert ops admins when the biolink background template library goes empty or drops below its expected floor, and all-clear once restored.',
        'cadence'     => ['hourlyAt', 50],
    ],
];
