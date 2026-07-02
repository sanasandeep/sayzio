<?php

namespace App\Console\Commands;

use App\Modules\Common\Support\AppLaunchNotifier;
use Illuminate\Console\Command;

/**
 * Emails the mobile-app launch mailing list — every visitor who left their
 * address in the "coming soon" modal — the moment the app ships.
 *
 * A manual trigger for the same idempotent notifier that
 * MarketingSettingsController fires inline on launch and the scheduled
 * app-launch:notify-signups sweep re-runs. A no-op until a store URL is
 * configured; only signups with a null `notified_at` are considered, and each
 * is stamped as soon as its email is confirmed delivered, so re-runs never
 * double-send. Run this to send/retry on demand — later signups collected after
 * the first run are picked up too.
 */
class NotifyAppLaunch extends Command
{
    protected $signature = 'app-launch:notify';

    protected $description = 'Email the mobile-app launch mailing list that the app is now live (idempotent — never double-sends).';

    public function handle(): int
    {
        $count = AppLaunchNotifier::notifyIfLaunched();

        $this->info("App-launch notify complete. Signups emailed: {$count}.");

        return self::SUCCESS;
    }
}
