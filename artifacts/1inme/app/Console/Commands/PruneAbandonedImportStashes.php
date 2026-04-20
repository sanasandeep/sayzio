<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Delete abandoned contact-import preview stash files (storage/app/imports/{userId}/{token}.json)
 * that are older than the retention window. Users sometimes upload a CSV, see
 * the preview, then close the tab without confirming or cancelling — without
 * this sweep those temp files would accumulate forever.
 */
class PruneAbandonedImportStashes extends Command
{
    protected $signature = 'imports:prune-abandoned
        {--hours=24 : Delete stash files older than this many hours}';

    protected $description = 'Delete abandoned contact-import preview stash files older than the retention window.';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $cutoff = now()->subHours($hours)->getTimestamp();
        $disk = Storage::disk('local');

        if (!$disk->exists('imports')) {
            $this->info('No imports directory — nothing to prune.');
            return self::SUCCESS;
        }

        $deleted = 0;
        $kept = 0;
        foreach ($disk->files('imports', true) as $file) {
            if (!str_ends_with($file, '.json')) continue;
            try {
                if ($disk->lastModified($file) < $cutoff) {
                    $disk->delete($file);
                    $deleted++;
                } else {
                    $kept++;
                }
            } catch (\Throwable $e) {
                logger()->warning('Failed pruning import stash ' . $file . ': ' . $e->getMessage());
            }
        }

        $this->info("Pruned {$deleted} abandoned import stash file(s); {$kept} still within the {$hours}h window.");
        return self::SUCCESS;
    }
}
