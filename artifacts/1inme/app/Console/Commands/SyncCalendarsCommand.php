<?php

namespace App\Console\Commands;

use App\Modules\User\Models\CalendarAccount;
use App\Modules\User\Services\Calendar\CalendarSyncService;
use Illuminate\Console\Command;

class SyncCalendarsCommand extends Command
{
    protected $signature   = 'calendars:sync {--account= : Sync only this calendar account id}';
    protected $description = 'Pull events from connected external calendars and mirror them as Event Invite links.';

    public function handle(CalendarSyncService $sync): int
    {
        $q = CalendarAccount::where('mirror_enabled', true);
        if ($id = $this->option('account')) {
            $q->where('id', $id);
        }
        $accounts = $q->get();

        $this->info("Syncing {$accounts->count()} account(s)...");
        foreach ($accounts as $account) {
            $stats = $sync->syncAccount($account);
            $this->line("  #{$account->id} {$account->provider} {$account->display_name}: " .
                "+{$stats['created']} ~{$stats['updated']} -{$stats['deleted']} (errors {$stats['errors']})");
        }
        return self::SUCCESS;
    }
}
