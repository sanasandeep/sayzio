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
        if ($id = $this->option('account')) {
            $q->where('id', $id);
        } else {
            // Skip revoked/expired connections on the unattended backstop —
            // they fail every time until the user reconnects.
            $q->whereNull('needs_reauth_at');
        }
        $accounts = $q->get();

        // An explicit --account run is an operator forcing a sync, so bypass
        // the cooldown; the unattended backstop routes through syncNow so it
        // cheaply skips accounts an on-demand trigger or sync-on-open just
        // handled (avoids double-hitting the Google People API).
        $force = (bool) $this->option('account');

        $this->info("Syncing contacts for {$accounts->count()} account(s)…");
        foreach ($accounts as $account) {
            $result = $sync->syncNow($account, $force);
            if ($result['status'] !== 'ok') {
                $this->line("  #{$account->id} {$account->account_email}: skipped ({$result['status']})");
                continue;
            }
            $stats = $result['stats'];
            $this->line("  #{$account->id} {$account->account_email}: +{$stats['created']} ~{$stats['updated']} -{$stats['deleted']} pushed {$stats['pushed']} (errors {$stats['errors']})");
        }
        return self::SUCCESS;
    }
}
