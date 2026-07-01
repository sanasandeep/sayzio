<?php

namespace App\Console\Commands;

use App\Modules\User\Models\ConnectedApp;
use App\Modules\User\Services\ConnectedApps\CrmSyncService;
use Illuminate\Console\Command;

/**
 * Scheduled inbound pull for every active, pull-enabled CRM connection.
 * Runs every 30 minutes (see routes/console.php), mirroring contacts:sync.
 */
class PullConnectedApps extends Command
{
    protected $signature = 'connected-apps:pull {--user= : Only pull connections for this user id}';

    protected $description = 'Pull inbound contacts from connected CRMs into Sayzio contacts';

    public function handle(CrmSyncService $sync): int
    {
        $query = ConnectedApp::query()
            ->crm()
            ->where('pull_enabled', true)
            ->where('status', ConnectedApp::STATUS_CONNECTED);

        if ($userId = $this->option('user')) {
            $query->where('user_id', (int) $userId);
        }

        $total = 0;
        $query->chunkById(50, function ($conns) use ($sync, &$total) {
            foreach ($conns as $conn) {
                try {
                    $count = $sync->pull($conn);
                    $total += $count;
                    $this->line("Pulled {$count} from {$conn->provider} (user {$conn->user_id}).");
                } catch (\Throwable $e) {
                    $this->error("Pull failed for connection {$conn->id}: {$e->getMessage()}");
                }
            }
        });

        $this->info("connected-apps:pull complete — {$total} contact(s) imported.");
        return self::SUCCESS;
    }
}
