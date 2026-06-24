<?php

namespace App\Console\Commands;

use App\Jobs\ProcessPrivacyDeletionJob;
use App\Modules\Common\Models\PrivacyRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Housekeeping for GDPR/CCPA privacy data requests. Runs hourly and is
 * fully idempotent:
 *
 *  1. Expire unverified requests whose email link has lapsed.
 *  2. Prune export archives whose secure download window has closed.
 *  3. Dispatch any approved deletion whose cooling-off window has elapsed
 *     — a safety net in case the delayed queue job was lost.
 */
class PrivacyMaintenanceCommand extends Command
{
    protected $signature = 'privacy:maintenance';
    protected $description = 'Expire stale privacy requests, prune old exports, and dispatch due deletions.';

    public function handle(): int
    {
        $expired = $this->expireUnverified();
        $pruned  = $this->pruneExpiredExports();
        $due     = $this->dispatchDueDeletions();

        $this->info("Privacy maintenance: expired {$expired}, pruned {$pruned} archives, dispatched {$due} deletions.");

        return self::SUCCESS;
    }

    private function expireUnverified(): int
    {
        $count = 0;
        PrivacyRequest::query()
            ->where('status', PrivacyRequest::STATUS_PENDING_VERIFICATION)
            ->whereNotNull('token_expires_at')
            ->where('token_expires_at', '<', now())
            ->each(function (PrivacyRequest $pr) use (&$count) {
                $pr->forceFill([
                    'status'             => PrivacyRequest::STATUS_REJECTED,
                    'rejection_reason'   => 'Verification link expired before the request was confirmed.',
                    'rejected_at'        => now(),
                    'verification_token' => null,
                ])->save();
                $pr->recordAudit('expired', 'system', 'Verification window lapsed.');
                $count++;
            });

        return $count;
    }

    private function pruneExpiredExports(): int
    {
        $count = 0;
        $disk = Storage::disk('local');

        PrivacyRequest::query()
            ->where('type', PrivacyRequest::TYPE_EXPORT)
            ->whereNotNull('archive_path')
            ->whereNotNull('download_expires_at')
            ->where('download_expires_at', '<', now())
            ->each(function (PrivacyRequest $pr) use (&$count, $disk) {
                try {
                    if ($pr->archive_path && $disk->exists($pr->archive_path)) {
                        $disk->delete($pr->archive_path);
                    }
                } catch (\Throwable $e) {
                    // Best-effort: a missing file shouldn't block clearing the row.
                }
                $pr->forceFill([
                    'archive_path'   => null,
                    'download_token' => null,
                ])->save();
                $pr->recordAudit('export_pruned', 'system', 'Download window closed; archive removed.');
                $count++;
            });

        return $count;
    }

    private function dispatchDueDeletions(): int
    {
        $count = 0;
        PrivacyRequest::query()
            ->where('type', PrivacyRequest::TYPE_DELETION)
            ->where('status', PrivacyRequest::STATUS_APPROVED)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->each(function (PrivacyRequest $pr) use (&$count) {
                ProcessPrivacyDeletionJob::dispatch($pr->id);
                $count++;
            });

        return $count;
    }
}
