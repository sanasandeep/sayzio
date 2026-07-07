<?php

namespace App\Console\Commands;

use App\Modules\Common\Support\HomePageCache;
use Illuminate\Console\Command;

/**
 * Proactively rebuild every home-page cache so no anonymous visitor ever
 * pays the full rebuild over the cross-region RDS (~4s+ in production).
 *
 * Scheduled every four minutes (routes/schedules/publishing-automation.php)
 * — inside the request path's 5-minute TTL, so the caches are always warm.
 * The warmer writes with a longer TTL (HomePageCache::WARM_TTL) so a single
 * missed run can't open an expiry gap, while content freshness comes from
 * each run overwriting the keys with data rebuilt from the DB — admin edits
 * therefore land within one warm cadence even without an explicit flush.
 */
class WarmHomePageCaches extends Command
{
    protected $signature = 'home:warm-caches';

    protected $description = 'Rebuild the home-page caches (anonymous payload per currency, featured blog posts, AI-hero demo aliases, domain branding) so no visitor hits a cold render.';

    public function handle(): int
    {
        $started = microtime(true);

        $summary = HomePageCache::warm();

        $this->info(sprintf(
            'Warmed home caches in %.2fs — payloads: [%s], featured posts: %d, AI-hero aliases: %d, branding hosts: [%s].',
            microtime(true) - $started,
            implode(', ', $summary['payload_currencies']),
            $summary['featured_posts'],
            $summary['ai_hero_aliases'],
            implode(', ', $summary['branding_hosts']),
        ));

        $errors = $summary['errors'] ?? [];
        if ($errors !== []) {
            // Sections are fault-isolated inside warm(); surface partial
            // failures via a non-zero exit so the scheduler panel records
            // the run as failed (and its failing-streak alerting kicks in).
            $this->error('Failed sections: ' . implode(', ', $errors) . ' (details in the log).');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
