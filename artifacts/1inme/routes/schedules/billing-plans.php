<?php

/*
|--------------------------------------------------------------------------
| Scheduled jobs — Billing & Plans
|--------------------------------------------------------------------------
| Subscription renewals, plan-window reminders, comp reversions, account
| reactivation and recurring client invoicing. subscriptions:renew-due is
| protected — pausing it silently stops all renewal charging.
|
| Loaded by App\Modules\Admin\Support\ScheduledJobRegistry; registered as a
| live schedule by routes/console.php.
*/

return [
    [
        'key'         => 'ai:reconcile-abandoned-builds',
        'description' => 'Refund AI page builds that were charged but never delivered, and remove the empty links they left behind. Covers the one failure the builder cannot: a process killed before its own catch block could run.',
        'cadence'     => ['hourly'],
    ],
    [
        'key'         => 'starter:send-free-window-reminders',
        'description' => 'Remind free Starter-plan users to re-confirm their plan near the end of their 1-year free window.',
        'cadence'     => ['dailyAt', '10:30'],
    ],
    [
        'key'         => 'creator-subscriptions:send-renewal-reminders',
        'description' => 'Remind fans a few days before a creator subscription they pay for auto-renews (email + in-app; heads-up only).',
        'cadence'     => ['dailyAt', '10:45'],
    ],
    [
        'key'         => 'subscriptions:renew-due',
        'description' => 'Charge gateways for subscriptions renewing within 24h and expire any past their grace window.',
        'cadence'     => ['hourly'],
        'protected'   => true,
    ],
    [
        'key'         => 'plans:revert-expired-comps',
        'description' => 'Revert accounts whose admin-granted complimentary / time-limited plan window has elapsed.',
        'cadence'     => ['hourlyAt', 30],
    ],
    [
        'key'         => 'users:reactivate-due',
        'description' => 'Auto-lift admin temporary account holds whose scheduled reactivation date has arrived.',
        'cadence'     => ['hourlyAt', 35],
    ],
    [
        'key'         => 'invoices:run-recurring',
        'description' => 'Generate client invoices from active recurring-invoice templates whose next run date is due, then advance each schedule.',
        'cadence'     => ['dailyAt', '06:00'],
    ],
];
