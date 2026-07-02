<?php

namespace App\Modules\Common\Support;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Models\AppLaunchSignup;
use App\Modules\Common\Services\Emailer;
use Illuminate\Support\Facades\Log;

/**
 * Closes the loop on the mobile-app launch mailing list collected by the
 * public "coming soon" modal (AppLaunchSignup / AppLaunchNotifyController).
 *
 * When an admin finally sets a store URL (marketing settings
 * `marketing_play_store_url` / `marketing_app_store_url`), the app has shipped —
 * so everyone who left their email to be notified is emailed exactly once, with
 * the live store link(s), and their row is stamped `notified_at` so they are
 * never emailed again.
 *
 * Three entry points feed this (mirrors FeatureLaunchNotifier):
 *   - MarketingSettingsController::update() calls notifyIfLaunched() the moment
 *     the launch state transitions empty → live (immediate payoff),
 *   - a scheduled command (app-launch:notify-signups) calls the same method so
 *     a transient SMTP blip that left rows unstamped is always retried, and
 *   - a manual command (app-launch:notify) lets an operator send/retry on demand.
 *
 * All are idempotent and safe to re-run: only rows with a null `notified_at`
 * are ever considered, and a row is stamped ONLY after its email is confirmed
 * delivered. `Emailer::send` swallows transport errors by default, so we opt
 * into `throw_on_failure`: a transient failure throws AFTER the failed
 * email_logs row is written and we leave `notified_at` null so the next sweep
 * retries — a launch email is never silently dropped.
 */
final class AppLaunchNotifier
{
    /**
     * Email every pending signup, if (and only if) the app is now live on at
     * least one store. A no-op while both store URLs are still empty, so it is
     * safe to call unconditionally (e.g. from the marketing-settings save on
     * every field change, or from a manual/scheduled command). Returns the
     * number of signups emailed + stamped.
     *
     * $force overrides the "must be launched" guard so an operator can send a
     * store-less launch email intentionally (the email then has no download
     * buttons). This is deliberately opt-in — the manual `app-launch:notify
     * --force` command is the only caller that passes it — because each signup
     * is a one-shot send: once stamped it is never emailed again, so an
     * unintentional store-less blast permanently burns the list.
     */
    public static function notifyIfLaunched(bool $force = false): int
    {
        $stores = self::liveStores();
        if ($stores === [] && ! $force) {
            // Not launched yet — nothing to announce.
            return 0;
        }

        $processed = 0;

        AppLaunchSignup::query()
            ->whereNull('notified_at')
            ->orderBy('id')
            ->chunkById(200, function ($signups) use ($stores, &$processed) {
                foreach ($signups as $signup) {
                    if (self::deliver($signup, $stores)) {
                        $processed++;
                    }
                }
            });

        return $processed;
    }

    /**
     * Whether the app is currently live on at least one store — i.e. any store
     * URL is configured. This is the "launched" signal all entry points share.
     */
    public static function isLaunched(): bool
    {
        return self::liveStores() !== [];
    }

    /**
     * The currently-configured store links, keyed by store. Empty URLs are
     * dropped, so an empty array means "not launched anywhere yet".
     *
     * @return array<string,string>  e.g. ['play' => 'https://…', 'app' => 'https://…']
     */
    public static function liveStores(): array
    {
        $out = [];

        $play = trim((string) AppSetting::get('marketing_play_store_url', ''));
        if ($play !== '') {
            $out['play'] = $play;
        }

        $app = trim((string) AppSetting::get('marketing_app_store_url', ''));
        if ($app !== '') {
            $out['app'] = $app;
        }

        return $out;
    }

    /**
     * Send one launch email and — ONLY on confirmed delivery — stamp the signup
     * so it is never re-sent.
     *
     * A transient transport failure throws (throw_on_failure) after the failed
     * email_logs row is written; we swallow it here and leave `notified_at`
     * null so the next scheduled sweep retries. A signup with no email on file
     * is skipped and left unstamped (nothing to deliver).
     *
     * @param  array<string,string>  $stores  live store links keyed by store
     * @return bool  true only when the signup was emailed and stamped
     */
    private static function deliver(AppLaunchSignup $signup, array $stores): bool
    {
        $email = trim((string) $signup->email);
        if ($email === '') {
            return false;
        }

        $appName = (string) config('app.name', 'Sayzio');

        $playUrl = $stores['play'] ?? '';
        $appUrl  = $stores['app'] ?? '';
        // Prefer the store the visitor originally clicked; otherwise the first
        // one that is live. Used as the single primary CTA link.
        $primaryUrl = ($signup->store === 'app' ? $appUrl : ($signup->store === 'play' ? $playUrl : ''))
            ?: ($playUrl ?: $appUrl);

        try {
            Emailer::send('app.launched', $email, [
                'app_name'  => $appName,
                'play_url'  => $playUrl,
                'app_url'   => $appUrl,
                'store_url' => $primaryUrl,
            ], [
                'related'          => $signup,
                'throw_on_failure' => true,
                'view_data'        => [
                    'subject'  => "The {$appName} app is here — download it now",
                    'appName'  => $appName,
                    'playUrl'  => $playUrl,
                    'appUrl'   => $appUrl,
                    'storeUrl' => $primaryUrl,
                ],
            ]);
        } catch (\Throwable $e) {
            // Transport failure — leave unstamped so the next sweep retries.
            Log::warning("AppLaunchNotifier delivery failed for signup {$signup->id}: " . $e->getMessage());
            return false;
        }

        // Delivery confirmed — stamp so this signup is never emailed twice.
        try {
            $signup->forceFill(['notified_at' => now()])->save();
        } catch (\Throwable $e) {
            Log::warning("AppLaunchNotifier could not stamp signup {$signup->id}: " . $e->getMessage());
        }

        return true;
    }
}
