<?php

namespace App\Console\Commands;

use App\Modules\User\Models\Calendar;
use App\Modules\User\Models\CalendarAccount;
use App\Modules\User\Models\CalendarEvent;
use App\Modules\User\Models\CalendarEventMirror;
use App\Modules\User\Services\Calendar\CalendarSyncService;
use Illuminate\Console\Command;

/**
 * Inbound half of two-way sync for published (followable) calendars: pulls the
 * owner's external (Google) edits/deletes back into their followable calendars
 * so followers always see the latest without re-subscribing.
 *
 * Only touches accounts with push enabled (the ones a followable calendar was
 * pushed to) whose owner still has the `calendar_sync` plan feature, and only
 * the calendars that actually have pushed events. Reconcile-only — it never
 * imports the owner's private Google events as new followable events.
 */
class PullPublishedCalendarsCommand extends Command
{
    protected $signature   = 'calendars:pull-published {--account= : Pull only this calendar account id}';
    protected $description = 'Pull owner edits from connected Google calendars back into published (followable) calendars (two-way sync).';

    public function handle(CalendarSyncService $sync): int
    {
        $q = CalendarAccount::where('provider', 'google')->where('push_enabled', true);
        if ($id = $this->option('account')) {
            $q->where('id', $id);
        }
        $accounts = $q->get();

        $this->info("Pulling published-calendar edits for {$accounts->count()} account(s)...");

        foreach ($accounts as $account) {
            $owner = $account->user;
            if (!$owner || !$owner->getPlanFeature('calendar_sync', false)) {
                continue;
            }

            // Distinct followable calendars this account has pushed events for.
            $pushedEventIds = CalendarEventMirror::where('calendar_account_id', $account->id)
                ->where('source', 'push')
                ->whereNotNull('calendar_event_id')
                ->pluck('calendar_event_id');

            if ($pushedEventIds->isEmpty()) {
                continue;
            }

            $calendarIds = CalendarEvent::whereIn('id', $pushedEventIds)
                ->distinct()
                ->pluck('calendar_id');

            foreach (Calendar::whereIn('id', $calendarIds)->get() as $calendar) {
                $stats = $sync->pullCalendar($account, $calendar);
                $this->line("  #{$account->id} {$account->provider} → calendar #{$calendar->id}: " .
                    "~{$stats['updated']} -{$stats['deleted']} (errors {$stats['errors']})");
            }
        }

        return self::SUCCESS;
    }
}
