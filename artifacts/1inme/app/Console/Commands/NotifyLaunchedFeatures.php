<?php

namespace App\Console\Commands;

use App\Modules\Common\Support\FeatureStates\FeatureLaunchNotifier;
use Illuminate\Console\Command;

/**
 * Closes the loop on "Notify me" signups from the app-wide "Coming soon"
 * system. When a feature transitions coming_soon → ready, everyone who asked
 * to be notified is emailed once.
 *
 * The admin override screen already fires this synchronously the moment an
 * admin clears an override. This scheduled sweep catches the OTHER transition
 * path — where the underlying integration/config finally connects and the
 * feature auto-detects as ready — which has no synchronous code hook.
 *
 * Idempotent: only interests with a null `notified_at` are considered, and
 * each is stamped as soon as it is processed, so re-runs never double-send.
 */
class NotifyLaunchedFeatures extends Command
{
    protected $signature = 'features:notify-launched';

    protected $description = 'Email users who asked to be notified about a "coming soon" feature that is now available.';

    public function handle(): int
    {
        $count = FeatureLaunchNotifier::notifyAllLaunched();

        $this->info("Feature-launch notify sweep complete. Interests processed: {$count}.");

        return self::SUCCESS;
    }
}
