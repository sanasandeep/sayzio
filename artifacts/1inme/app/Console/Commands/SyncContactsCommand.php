<?php

namespace App\Console\Commands;

use App\Modules\User\Models\GoogleContactsAccount;
use App\Modules\User\Services\Contacts\GoogleContactsSyncService;
use Illuminate\Console\Command;

class SyncContactsCommand extends Command
{
    protected $signature   = 'contacts:sync {--account= : Sync only this google_contacts_accounts.id}';
    protected $description = 'Pull Google Contacts and push pending local changes for every connected account.';

    public function handle(GoogleContactsSyncService $sync): int
    {
        $q = GoogleContactsAccount::query();
        if ($id = $this->option('account')) $q->where('id', $id);
        $accounts = $q->get();

        $this->info("Syncing contacts for {$accounts->count()} account(s)…");
        foreach ($accounts as $account) {
            $stats = $sync->syncAccount($account);
            $this->line("  #{$account->id} {$account->account_email}: +{$stats['created']} ~{$stats['updated']} -{$stats['deleted']} pushed {$stats['pushed']} (errors {$stats['errors']})");
        }
        return self::SUCCESS;
    }
}
