<?php

/*
|--------------------------------------------------------------------------
| Scheduled jobs — Analytics & Cleanup
|--------------------------------------------------------------------------
| Analytics pipeline maintenance (counter folds, rollups, partitions,
| retention pruning) plus off-peak disk/table housekeeping sweeps.
| analytics:flush-counters and tracking:maintain-partitions are protected —
| pausing them stalls click totals / breaks tracking inserts.
|
| Loaded by App\Modules\Admin\Support\ScheduledJobRegistry; registered as a
| live schedule by routes/console.php.
*/

return [
    [
        'key'         => 'coach:snapshot-scores',
        'description' => 'Record yesterday\'s Performance Coach score for every active link, powering the 30-day trend sparkline.',
        'cadence'     => ['dailyAt', '01:15'],
    ],
    [
        'key'         => 'imports:prune-abandoned',
        'description' => 'Delete contact-import preview stash files older than 24h that were never confirmed.',
        'cadence'     => ['dailyAt', '03:30'],
    ],
    [
        'key'         => 'clicks:backfill-source',
        'command'     => 'clicks:backfill-source --chunk=1000 --limit=50000',
        'description' => 'Re-tag any link_clicks rows missing a traffic source so they don\'t show as "Unknown" (chunked + capped).',
        'cadence'     => ['dailyAt', '02:30'],
    ],
    [
        'key'         => 'site-assistant:prune-cutoff-alerts',
        'command'     => 'site-assistant:prune-cutoff-alerts --days=90',
        'description' => 'Trim old Site Assistant cut-off alert rows beyond the retention window.',
        'cadence'     => ['dailyAt', '03:45'],
    ],
    [
        'key'         => 'images:backfill-reoptimize',
        'command'     => 'images:backfill-reoptimize --chunk=200 --limit=500',
        'description' => 'Downscale oversized images that slipped past the upload-time compression pipeline (capped per night).',
        'cadence'     => ['dailyAt', '04:45'],
    ],
    [
        'key'         => 'cv-uploads:prune-abandoned',
        'command'     => 'cv-uploads:prune-abandoned --days=7',
        'description' => 'Delete orphaned conversational-flow visitor uploads not referenced by any completed session.',
        'cadence'     => ['dailyAt', '03:30'],
    ],
    [
        'key'         => 'privacy:maintenance',
        'description' => 'Housekeep GDPR/CCPA privacy requests — expire lapsed verifications, prune stale export archives, dispatch due deletions.',
        'cadence'     => ['hourlyAt', 45],
    ],
    [
        'key'         => 'analytics:flush-counters',
        'description' => 'Fold append-only click counter deltas into the links / biolink blocks running totals (the click hot path only appends deltas).',
        'cadence'     => ['everyMinute'],
        'protected'   => true,
    ],
    [
        'key'         => 'analytics:rollup-daily',
        'description' => 'Finalize the previous day(s) into the link_click_daily rollup tables and re-roll a short trailing lookback for late clicks.',
        'cadence'     => ['dailyAt', '03:45'],
    ],
    [
        'key'         => 'tracking:maintain-partitions',
        'description' => 'Provision future month partitions for the tracking tables so insert targets always exist ahead of time.',
        'cadence'     => ['monthlyOn', 1, '02:30'],
        'protected'   => true,
    ],
    [
        'key'         => 'stats:prune-history',
        'description' => 'Delete click and visitor-session history older than the largest stats-retention window across all active plans (no-op while any plan keeps history forever).',
        'cadence'     => ['dailyAt', '04:05'],
    ],
    [
        'key'         => 'scheduled-runs:prune',
        'description' => 'Trim scheduled-job run history: keep the last 30 days or the newest 200 runs per job, whichever is larger, so the history drawer stays fast.',
        'cadence'     => ['dailyAt', '04:35'],
    ],
    [
        'key'         => 'events:prune-discoverability',
        'command'     => 'events:prune-discoverability --days=7',
        'description' => 'Delete "People at this event" opt-in rows whose expiry is more than 7 days past, so dead rows (and their location data) don\'t pile up.',
        'cadence'     => ['dailyAt', '04:15'],
    ],
    [
        'key'         => 'events:prune-contact-exchanges',
        'command'     => 'events:prune-contact-exchanges --days=30 --fallback-days=90 --accepted-days=730',
        'description' => 'Delete pending/declined event contact-swap requests for events that ended over 30 days ago (90-day fallback when the event has no end date); accepted exchanges are kept 2 years after acceptance, then pruned (the exchanged contacts already live in each address book).',
        'cadence'     => ['dailyAt', '04:20'],
    ],
    [
        'key'         => 'email-logs:prune-history',
        'description' => 'Trim the email log: null heavy stored bodies past the body-retention window, then delete whole rows past the retention window.',
        'cadence'     => ['dailyAt', '04:25'],
    ],
];
