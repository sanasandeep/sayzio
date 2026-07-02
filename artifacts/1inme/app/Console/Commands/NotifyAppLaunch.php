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
 *
 * Guard: because each signup is emailed exactly once (stamped `notified_at` and
 * never re-sent), sending before a store URL is set would burn the whole list
 * on an email with no download buttons. This command therefore REFUSES to run
 * — sending and stamping nothing — while both `marketing_play_store_url` and
 * `marketing_app_store_url` are empty. Pass `--force` to override intentionally.
 */
class NotifyAppLaunch extends Command
{
    protected $signature = 'app-launch:notify {--force : Send even when no store URL is configured (the email will have no download buttons)}';

    protected $description = 'Email the mobile-app launch mailing list that the app is now live (idempotent — never double-sends).';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        if (! $force && ! AppLaunchNotifier::isLaunched()) {
            $this->error(
                'Refusing to send: no store URL is configured '
                . '(marketing_play_store_url and marketing_app_store_url are both empty). '
                . 'The launch email would have no download buttons, and each signup can '
                . 'only ever be emailed once — sending now would burn the whole list. '
                . 'Set a store URL first, or re-run with --force to send anyway.'
            );

            return self::FAILURE;
        }

        $count = AppLaunchNotifier::notifyIfLaunched($force);

        $this->info("App-launch notify complete. Signups emailed: {$count}.");

        return self::SUCCESS;
    }
}
