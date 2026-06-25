<?php

namespace App\Console\Commands;

use App\Modules\User\Models\ReviewProvider;
use App\Services\ReviewProviders\ReviewProviderRegistry;
use Illuminate\Console\Command;

/**
 * Pull 3rd-party reviews (Google, Trustpilot, …) into external_reviews for
 * every connected provider. Runs on a schedule and is also invoked for a
 * single connection on a manual "Refresh now" from the moderation UI.
 *
 * Providers without configured API credentials sync a clearly-labelled
 * preview sample instead of failing, so the connect flow is demonstrable
 * end-to-end without secrets.
 */
class SyncReviewProviders extends Command
{
    protected $signature = 'reviews:sync {--provider= : Sync only this review_providers.id}';

    protected $description = 'Sync 3rd-party reviews from connected providers into Sayzio.';

    public function handle(): int
    {
        $query = ReviewProvider::query()
            ->whereIn('status', [
                ReviewProvider::STATUS_CONNECTED,
                ReviewProvider::STATUS_PREVIEW,
                ReviewProvider::STATUS_ERROR,
            ]);

        if ($id = $this->option('provider')) {
            $query->where('id', $id);
        }

        $total = 0;
        $count = 0;
        foreach ($query->get() as $connection) {
            if (!ReviewProviderRegistry::exists($connection->provider)) {
                continue;
            }
            $adapter = ReviewProviderRegistry::adapter($connection->provider);
            $result = $adapter->sync($connection);
            $total += $result['imported'];
            $count++;
            $this->info(sprintf(
                '[%s] user=%d imported=%d%s',
                $connection->provider,
                $connection->user_id,
                $result['imported'],
                $result['preview'] ? ' (preview)' : ''
            ));
        }

        $this->info("Synced {$count} connection(s), imported {$total} new review(s).");
        return self::SUCCESS;
    }
}
