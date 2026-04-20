<?php

namespace App\Console\Commands;

use App\Modules\User\Models\InboxForwardDelivery;
use App\Modules\User\Services\InboxForwarder;
use Illuminate\Console\Command;

/**
 * Retry inbox-forward deliveries whose backoff window has elapsed.
 * Runs every few minutes so transient webhook/email failures heal on their own
 * while permanently broken destinations get parked as 'dead' after MAX_ATTEMPTS.
 */
class RetryInboxForwards extends Command
{
    protected $signature = 'inbox:retry-forwards
        {--limit=200 : Maximum number of deliveries to attempt this run}';

    protected $description = 'Retry pending/failed inbox forwarding deliveries.';

    public function handle(InboxForwarder $forwarder): int
    {
        $limit = (int) $this->option('limit');

        $rows = InboxForwardDelivery::where('status', 'failed')
            ->where('next_retry_at', '<=', now())
            ->orderBy('next_retry_at')
            ->limit($limit)
            ->get();

        $ok = 0; $fail = 0;
        foreach ($rows as $row) {
            try {
                $forwarder->deliver($row);
                $row->refresh();
                if ($row->status === 'success') $ok++; else $fail++;
            } catch (\Throwable $e) {
                $fail++;
                logger()->warning('Inbox forward retry error: ' . $e->getMessage());
            }
        }

        $this->info("Retried {$rows->count()} deliveries — {$ok} ok / {$fail} still failing.");
        return self::SUCCESS;
    }
}
