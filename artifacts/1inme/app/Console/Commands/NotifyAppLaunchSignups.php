<?php

namespace App\Console\Commands;

use App\Modules\Common\Support\AppLaunchNotifier;
use Illuminate\Console\Command;

/**
 * Safety sweep for the mobile-app launch mailing list.
 *
 * MarketingSettingsController::update() already fires the launch emails
 * synchronously the moment an admin sets a store URL (empty → live). This
 * scheduled command re-runs the same idempotent notifier so any signup left
 * unstamped by a transient SMTP blip during that inline send is retried until
 * it is confirmed delivered — a launch email is never permanently dropped.
 *
 * Idempotent: a no-op while no store URL is configured, and only signups with a
 * null `notified_at` are ever considered, so a per-hour cadence never
 * double-sends.
 */
class NotifyAppLaunchSignups extends Command
{
    protected $signature = 'app-launch:notify-signups';

    protected $description = 'Email mobile-app launch-list signups once the app is live on a store (retries any left unsent).';

    public function handle(): int
    {
        $count = AppLaunchNotifier::notifyIfLaunched();

        $this->info("App-launch notify sweep complete. Signups emailed: {$count}.");

        return self::SUCCESS;
    }
}
