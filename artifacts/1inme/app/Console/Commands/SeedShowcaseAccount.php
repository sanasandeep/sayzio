<?php

namespace App\Console\Commands;

use Database\Seeders\ShowcaseAccountSeeder;
use Illuminate\Console\Command;

/**
 * Provision or refresh the sana@sayzio.app showcase account: a fresh main
 * link-in-bio page, 2 realistic demo links + 1 usage-explainer page for
 * every supported link type, every other feature surface populated, and
 * 90 days of backdated analytics (link_clicks / page_sessions / block_views)
 * with the counter/rollup commands re-run afterwards.
 *
 * Strictly scoped to that one account: the underlying
 * {@see ShowcaseAccountSeeder} wipes and rebuilds only rows belonging to
 * sana@sayzio.app and never touches any other account. Safe to re-run —
 * every run converges to the same end state.
 *
 * Owner instructions (production):
 *   1. Make sure plans are seeded (the "unlimited" plan must exist).
 *   2. Run: php artisan showcase:seed
 *      (you will be asked to confirm in production; add --force to skip).
 *   3. To refresh only the 90-day backdated analytics window later,
 *      without rebuilding the content: php artisan showcase:seed --analytics-only
 */
class SeedShowcaseAccount extends Command
{
    protected $signature = 'showcase:seed
        {--analytics-only : Only regenerate the 90-day backdated analytics (keeps all links/content as-is)}
        {--force : Skip the confirmation prompt in production}';

    protected $description = 'Provision/refresh the sana@sayzio.app showcase account (demo links, explainer pages, backdated analytics). Scoped to that account only.';

    public function handle(): int
    {
        $analyticsOnly = (bool) $this->option('analytics-only');

        // Safety latch: sana@sayzio.app is a REAL user account that doubles as
        // the showcase target, and a full (non --analytics-only) reseed WIPES
        // and rebuilds all of her links/content. An accidental mass workflow
        // restart once re-ran the prod reseed and destroyed live edits, so a
        // destructive run now requires the explicit SHOWCASE_SEED_CONFIRM=yes
        // environment variable in addition to --force. Interactive runs still
        // get the confirmation prompt instead; --analytics-only is additive
        // and stays unrestricted.
        if (! $analyticsOnly
            && $this->option('force')
            && getenv('SHOWCASE_SEED_CONFIRM') !== 'yes') {
            $this->error(
                'Refusing to wipe & rebuild the ' . ShowcaseAccountSeeder::EMAIL . ' showcase account. '
                . 'This is a real user account; a forced reseed destroys her live edits. '
                . 'To run intentionally: SHOWCASE_SEED_CONFIRM=yes php artisan showcase:seed --force'
            );

            return self::FAILURE;
        }

        if (app()->environment('production') && ! $this->option('force')) {
            $what = $analyticsOnly
                ? 'regenerate the backdated analytics for the ' . ShowcaseAccountSeeder::EMAIL . ' showcase account'
                : 'WIPE and rebuild ALL content on the ' . ShowcaseAccountSeeder::EMAIL . ' showcase account (no other account is touched)';
            if (! $this->confirm("This will {$what}. Continue?")) {
                $this->warn('Aborted.');
                return self::FAILURE;
            }
        }

        /** @var ShowcaseAccountSeeder $seeder */
        $seeder = app(ShowcaseAccountSeeder::class);
        $seeder->setContainer(app())->setCommand($this);

        if ($analyticsOnly) {
            $seeder->seedAnalyticsForShowcaseUser();
        } else {
            $seeder->__invoke();
        }

        return self::SUCCESS;
    }
}
