<?php

/*
|--------------------------------------------------------------------------
| Scheduled jobs — Syncing & Integrations
|--------------------------------------------------------------------------
| Third-party pull/push backstops: calendars, Google Contacts, CRMs, social
| accounts, cloud connections, AI Mind sources and external reviews.
|
| Loaded by App\Modules\Admin\Support\ScheduledJobRegistry; registered as a
| live schedule by routes/console.php.
*/

return [
    [
        'key'         => 'calendars:sync',
        'description' => 'Pull external calendar events for connected accounts and mirror them as Event Invite (.ics) links.',
        'cadence'     => ['everyFifteenMinutes'],
    ],
    [
        'key'         => 'calendars:pull-published',
        'description' => 'Pull owner edits/deletes from connected Google calendars back into published (followable) calendars so followers see updates.',
        'cadence'     => ['everyFifteenMinutes'],
    ],
    [
        // Near-real-time backstop; the per-account cooldown inside syncNow
        // cheaply skips accounts that just synced. The 10-minute lock expiry
        // guards against a slow run overlapping the next 2-minute tick.
        'key'                 => 'contacts:sync',
        'description'         => 'Two-way sync of Google Contacts for every connected account.',
        'cadence'             => ['everyTwoMinutes'],
        'without_overlapping' => 10,
    ],
    [
        'key'         => 'contacts:reconcile-attachments',
        'description' => 'Clear caller-ID biolink attachments whose creator is no longer reachable to the contact owner (blocked/suspended/deleted).',
        'cadence'     => ['hourly'],
    ],
    [
        'key'         => 'connected-apps:pull',
        'description' => 'Pull inbound CRM contacts (Salesforce/HubSpot/Zoho) into Sayzio contacts for every active, pull-enabled Connected Apps connection.',
        'cadence'     => ['everyThirtyMinutes'],
    ],
    [
        'key'         => 'socials:refresh-follower-counts',
        'description' => 'Refresh cached social follower counts so Link in Bio Follow buttons show fresh numbers.',
        'cadence'     => ['everyFourHours'],
    ],
    [
        'key'         => 'socials:refresh-oauth-tokens',
        'description' => 'Refresh near-expiry social OAuth access tokens and flag broken connections for reconnect.',
        'cadence'     => ['hourly'],
    ],
    [
        'key'         => 'cloud-connections:check',
        'description' => 'Refresh near-expiry tokens and flag cloud connections whose OAuth was revoked or expired.',
        'cadence'     => ['dailyAt', '04:15'],
    ],
    [
        'key'         => 'minds:refresh-links',
        'description' => 'Re-crawl AI Mind link sources whose refresh window has elapsed (capped per day).',
        'cadence'     => ['everyFifteenMinutes'],
    ],
    [
        'key'         => 'reviews:sync',
        'description' => 'Pull third-party reviews (Google, Trustpilot, …) into external_reviews for connected providers.',
        'cadence'     => ['everyThirtyMinutes'],
    ],
];
