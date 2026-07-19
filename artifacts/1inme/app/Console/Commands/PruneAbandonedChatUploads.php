<?php

namespace App\Console\Commands;

use App\Modules\User\Models\ConversationSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Delete orphaned conversational-flow file uploads.
 *
 * Visitor uploads land on the `public` disk under `cv_uploads/YYYY/MM/`
 * via {@see \App\Modules\Common\Controllers\ConversationPublicController::captureFile()}.
 * If a visitor uploads then drops off (never finishes the flow), the file
 * sits there forever — and re-uploads on the same step also used to leave
 * the previous file behind (now cleaned up at write time, but historical
 * orphans still need sweeping).
 *
 * This command builds a set of basenames referenced by **completed**
 * sessions' answers and deletes any `cv_uploads/...` file older than the
 * retention window that isn't in that set.
 */
class PruneAbandonedChatUploads extends Command
{
    protected $signature = 'cv-uploads:prune-abandoned
        {--days=7 : Delete uploads older than this many days that no completed session references}';

    protected $description = 'Delete orphaned conversational-flow visitor uploads (cv_uploads/) older than the retention window.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days)->getTimestamp();
        $disk = Storage::disk('public');

        if (!$disk->exists('cv_uploads')) {
            $this->info('No cv_uploads directory — nothing to prune.');
            return self::SUCCESS;
        }

        try {
            $referenced = $this->collectReferencedBasenames();
        } catch (\Throwable $e) {
            $this->error('Failed to collect referenced basenames: ' . $e->getMessage());
            return self::FAILURE;
        }

        try {
            $files = $disk->allFiles('cv_uploads');
        } catch (\Throwable $e) {
            $this->error('Failed to list cv_uploads files: ' . $e->getMessage());
            return self::FAILURE;
        }

        $deleted = 0;
        $kept = 0;
        $skippedRecent = 0;
        foreach ($files as $file) {
            try {
                if ($disk->lastModified($file) >= $cutoff) {
                    $skippedRecent++;
                    continue;
                }
                if (isset($referenced[$file])) {
                    $kept++;
                    continue;
                }
                $disk->delete($file);
                $deleted++;
            } catch (\Throwable $e) {
                logger()->warning('Failed pruning cv_upload ' . $file . ': ' . $e->getMessage());
            }
        }

        $this->info("Pruned {$deleted} orphaned upload(s); kept {$kept} referenced; {$skippedRecent} still within the {$days}d window.");
        return self::SUCCESS;
    }

    /**
     * Pull `cv_uploads/...` relative paths out of every completed session's
     * answers JSON. We key on the full relative path (e.g.
     * `cv_uploads/2026/05/abc.bin`) rather than just the basename so two
     * files with the same filename in different month folders can never
     * mask each other.
     *
     * @return array<string,true>
     */
    protected function collectReferencedBasenames(): array
    {
        $set = [];
        ConversationSession::query()
            ->where('completed', true)
            ->select(['id', 'answers'])
            ->chunkById(500, function ($rows) use (&$set) {
                foreach ($rows as $row) {
                    $answers = $row->answers;
                    if (!is_array($answers)) continue;
                    foreach ($answers as $val) {
                        if (!is_string($val) || !str_contains($val, 'cv_uploads/')) continue;
                        // Works for both public-disk URLs (/storage/cv_uploads/..)
                        // and raw relative paths (cv_uploads/..). We grab the
                        // full cv_uploads/... suffix so the keep-set matches
                        // Storage::disk('public')->allFiles('cv_uploads') exactly.
                        $candidate = parse_url($val, PHP_URL_PATH) ?: $val;
                        if (preg_match('#(cv_uploads/[A-Za-z0-9_/\-.]+)#', $candidate, $m)) {
                            $set[$m[1]] = true;
                        }
                    }
                }
            });
        return $set;
    }
}
