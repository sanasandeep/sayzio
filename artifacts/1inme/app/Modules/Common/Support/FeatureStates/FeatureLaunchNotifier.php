<?php

namespace App\Modules\Common\Support\FeatureStates;

use App\Modules\Common\Services\Emailer;
use App\Modules\User\Models\FeatureNotifyInterest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route as RouteFacade;

/**
 * Closes the loop on the "Notify me" signups collected by the app-wide
 * "Coming soon" system (FeatureAvailability / FeatureCatalog).
 *
 * When a feature transitions from coming_soon → ready — either because an
 * admin cleared its forced override, or because the underlying
 * integration/config finally connected (auto-detected) — every user who had
 * a stored notify interest for that feature is emailed exactly once and their
 * interest is stamped `notified_at` so they are never emailed again.
 *
 * Two entry points feed this:
 *   - the admin override screen calls notifyLaunched() the moment an admin
 *     clears an override (immediate payoff), and
 *   - a scheduled command calls notifyAllLaunched() so the integration/config
 *     path — which has no synchronous code hook — is still caught.
 *
 * Both are idempotent: only interests with a null `notified_at` are ever
 * considered, and an interest is stamped ONLY after its email is confirmed
 * delivered. A transient transport failure leaves it unstamped so the next
 * sweep retries it (never a silent drop); a user with no email on file is
 * skipped and left unstamped (nothing to deliver).
 */
final class FeatureLaunchNotifier
{
    /**
     * Email everyone waiting on a single feature, if (and only if) that
     * feature currently resolves to "ready". Returns the number of interests
     * processed (emailed or skipped-but-stamped).
     */
    public static function notifyLaunched(string $key): int
    {
        $def = FeatureAvailability::definition($key);
        if (!$def) {
            return 0;
        }

        // Still coming soon (forced or not-yet-configured) → nothing to do.
        if (FeatureAvailability::isComingSoon($key)) {
            return 0;
        }

        $processed = 0;

        FeatureNotifyInterest::query()
            ->where('feature_key', $key)
            ->whereNull('notified_at')
            ->with('user')
            ->chunkById(200, function ($interests) use ($def, $key, &$processed) {
                foreach ($interests as $interest) {
                    if (self::deliver($interest, $def, $key)) {
                        $processed++;
                    }
                }
            });

        return $processed;
    }

    /**
     * Scan every feature that still has pending notify interests and email the
     * waiters for any that are now ready. Returns the total processed.
     */
    public static function notifyAllLaunched(): int
    {
        $keys = FeatureNotifyInterest::query()
            ->whereNull('notified_at')
            ->distinct()
            ->pluck('feature_key')
            ->all();

        $total = 0;
        foreach ($keys as $key) {
            $total += self::notifyLaunched((string) $key);
        }

        return $total;
    }

    /**
     * Send one launch email and — ONLY on confirmed delivery — stamp the
     * interest so it is never re-sent.
     *
     * Emailer::send swallows transport errors by default, so we opt into
     * `throw_on_failure`: a transient failure (SMTP outage, etc.) throws AFTER
     * the failed email_logs row is written, and we leave `notified_at` null so
     * the next sweep retries — a launch email is never silently dropped.
     *
     * A user with no email address on file is skipped and left unstamped (there
     * is nothing to deliver); re-scanning them is a cheap no-op that
     * self-resolves if they later gain an address.
     *
     * @param  array<string,mixed>  $def
     * @return bool  true only when the interest was emailed and stamped
     */
    private static function deliver(FeatureNotifyInterest $interest, array $def, string $key): bool
    {
        $user = $interest->user;
        $email = $user?->email;

        if (!$user || !is_string($email) || $email === '') {
            return false;
        }

        $featureUrl = self::landingUrl($def);

        try {
            Emailer::send('feature.launched', $email, [
                'name'        => $user->name ?: 'there',
                'feature'     => $def['label'] ?? $key,
                'blurb'       => $def['blurb'] ?? '',
                'feature_url' => $featureUrl,
            ], [
                'user'             => $user->id,
                'related'          => $user,
                'throw_on_failure' => true,
                'view_data' => [
                    'subject'      => (($def['label'] ?? $key) . ' is now available on Sayzio'),
                    'userName'     => $user->name ?: 'there',
                    'featureLabel' => $def['label'] ?? $key,
                    'blurb'        => $def['blurb'] ?? '',
                    'capabilities' => $def['capabilities'] ?? [],
                    'featureUrl'   => $featureUrl,
                ],
            ]);
        } catch (\Throwable $e) {
            // Transport failure — leave unstamped so the next sweep retries.
            Log::warning("FeatureLaunchNotifier delivery failed for interest {$interest->id} [{$key}]: " . $e->getMessage());
            return false;
        }

        // Delivery confirmed — stamp so this interest is never emailed twice.
        try {
            $interest->forceFill(['notified_at' => now()])->save();
        } catch (\Throwable $e) {
            Log::warning("FeatureLaunchNotifier could not stamp interest {$interest->id}: " . $e->getMessage());
        }

        return true;
    }

    /**
     * Resolve an absolute URL to the feature's landing route, falling back to
     * the user dashboard if the route can't be built.
     *
     * @param  array<string,mixed>  $def
     */
    private static function landingUrl(array $def): string
    {
        $landing = $def['landing'] ?? null;
        try {
            if (is_string($landing) && $landing !== '' && RouteFacade::has($landing)) {
                return route($landing);
            }
            if (RouteFacade::has('user.dashboard')) {
                return route('user.dashboard');
            }
        } catch (\Throwable $e) {
            // fall through to config url
        }

        return rtrim((string) config('app.url'), '/');
    }
}
