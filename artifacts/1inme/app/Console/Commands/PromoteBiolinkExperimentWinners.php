<?php

namespace App\Console\Commands;

use App\Modules\User\Models\BiolinkExperiment;
use App\Modules\User\Services\BiolinkExperimentService;
use Illuminate\Console\Command;

/**
 * Hourly sweep that ends any biolink A/B test whose stop condition has
 * been met but whose visitors haven't trickled in to trigger the inline
 * `maybeAutoPromote()` check. Most experiments end via the inline
 * check; this is a safety net for end_date tests on quiet pages.
 *
 * Also folds in Task #3531 housekeeping for adaptive experiments: those
 * never "stop" (no winner to promote — they keep learning per segment
 * indefinitely), so instead we prune stale bandit-arm rows that haven't
 * seen traffic in a while.
 */
class PromoteBiolinkExperimentWinners extends Command
{
    protected $signature = 'biolink:promote-experiment-winners';
    protected $description = 'Auto-promote winners for biolink A/B tests whose stop conditions have been met, and prune idle adaptive-optimization arms.';

    public function handle(BiolinkExperimentService $service): int
    {
        $promoted = 0;
        $pruned = 0;
        BiolinkExperiment::where('status', 'running')->chunkById(50, function ($batch) use ($service, &$promoted, &$pruned) {
            foreach ($batch as $exp) {
                if ($exp->isAdaptive()) {
                    $pruned += $service->pruneIdleAdaptiveArms($exp);
                    continue;
                }
                $before = $exp->status;
                $exp = $service->maybeAutoPromote($exp);
                if ($exp->status !== $before) $promoted++;
            }
        });
        $this->info("Promoted {$promoted} experiment(s). Pruned {$pruned} idle adaptive arm(s).");
        return self::SUCCESS;
    }
}
