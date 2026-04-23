<?php

namespace App\Console\Commands;

use App\Modules\Common\Models\SiteAssistantMessage;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use App\Services\AI\SiteAssistantSettings;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sweeps the last 24h of site-assistant traffic and alerts admins when
 * the cut-off abandon rate (visitors who saw a partial/failed assistant
 * stream and never clicked Retry) crosses the configured threshold.
 *
 * Hooked into the scheduler so admins get notified as soon as an
 * upstream regression starts shedding answers — without having to load
 * the analytics dashboard themselves. Mirrors the same SQL the dashboard
 * tile uses so what admins read in the email matches the page exactly.
 *
 * Guarded by:
 *   - cutoff_alert_enabled        — admin opt-in (off by default)
 *   - cutoff_alert_min_sample     — avoid noisy alerts on tiny windows
 *   - cutoff_alert_cooldown_hours — don't re-alert until this elapses
 *
 * Notification fan-out:
 *   - Every user with the `settings.manage` platform permission gets an
 *     in-app notification of type `site_assistant_cutoff_alert`.
 *   - Email goes to the explicit `cutoff_alert_emails` list when set,
 *     otherwise to the same admin set's verified email addresses.
 */
class CheckSiteAssistantCutoffs extends Command
{
    protected $signature   = 'site-assistant:check-cutoffs
                              {--force : Bypass the cooldown window and re-send even if recently alerted}';

    protected $description = 'Alert admins when the Site Assistant cut-off abandon rate exceeds the configured threshold.';

    public function handle(): int
    {
        $cfg = SiteAssistantSettings::get();
        if (! ($cfg['cutoff_alert_enabled'] ?? false)) {
            $this->info('Cut-off alerts disabled — nothing to do.');
            return self::SUCCESS;
        }

        $threshold     = max(1, min(100, (int) ($cfg['cutoff_alert_abandon_threshold'] ?? 60)));
        $minSample     = max(1, (int) ($cfg['cutoff_alert_min_sample'] ?? 20));
        $cooldownHours = max(1, (int) ($cfg['cutoff_alert_cooldown_hours'] ?? 6));

        $since = now()->subDay();

        // Mirror SiteAssistantController::analytics() so the alert maths
        // match what an admin would see on the dashboard for the same
        // window.
        $base = SiteAssistantMessage::query()
            ->where('role', 'assistant')
            ->where('created_at', '>=', $since)
            ->whereRaw("meta->>'status' IN ('partial','failed')");

        $total = (int) (clone $base)->count();
        if ($total < $minSample) {
            $this->info("Sample size {$total} below minimum {$minSample} — skipping.");
            return self::SUCCESS;
        }

        $retried = 0;
        $retriedIds = SiteAssistantMessage::query()
            ->where('role', 'user')
            ->where('created_at', '>=', $since)
            ->whereRaw("meta->>'retry_of' ~ '^[0-9]+$'")
            ->selectRaw("DISTINCT (meta->>'retry_of')::bigint AS rid")
            ->pluck('rid')
            ->filter()
            ->all();
        if (! empty($retriedIds)) {
            $retried = (int) (clone $base)->whereIn('id', $retriedIds)->count();
        }

        $abandonRate = (int) round((($total - $retried) / $total) * 100);
        $this->info("Last 24h: {$total} cut-offs, {$retried} retried — abandon rate {$abandonRate}% (threshold {$threshold}%).");

        if ($abandonRate < $threshold) {
            $this->info('Below threshold — no alert dispatched.');
            return self::SUCCESS;
        }

        // Cooldown — store the last alert as ISO-8601 in the settings
        // store so it survives across schedulers / deploys without
        // needing a dedicated cache key.
        $lastSent = $cfg['cutoff_alert_last_sent_at'] ?? null;
        if (! $this->option('force') && $lastSent) {
            try {
                $lastSentAt = Carbon::parse($lastSent);
                if ($lastSentAt->greaterThan(now()->subHours($cooldownHours))) {
                    $this->info("Within cooldown window (last sent {$lastSentAt->diffForHumans()}) — skipping.");
                    return self::SUCCESS;
                }
            } catch (\Throwable $e) {
                // Malformed timestamp — fall through and re-alert; the
                // write below will heal the value.
            }
        }

        $admins = User::query()
            ->whereHas('roles.permissions', fn ($q) => $q->where('key', 'settings.manage'))
            ->get();

        $subject = "Site Assistant cut-off alert: {$abandonRate}% abandon rate (last 24h)";
        $body    = "Of {$total} cut-off / failed assistant streams in the last 24h, only {$retried} were retried — that's a {$abandonRate}% abandon rate, exceeding the {$threshold}% threshold. This usually points to an upstream regression. Open the Site Assistant analytics dashboard for context.";
        $url     = route('admin.site-assistant.analytics');

        $inAppDelivered = 0;
        foreach ($admins as $u) {
            try {
                UserNotification::create([
                    'user_id' => $u->id,
                    'type'    => 'site_assistant_cutoff_alert',
                    'data'    => [
                        'subject'      => $subject,
                        'body'         => $body,
                        'message'      => $body, // legacy field rendered by the user_notifications view
                        'url'          => $url,  // canonical key consumed by the in-app notification list
                        'target_url'   => $url,  // legacy alias for older renderers
                        'abandon_rate' => $abandonRate,
                        'threshold'    => $threshold,
                        'total'        => $total,
                        'retried'      => $retried,
                        'window_hours' => 24,
                    ],
                    'created_at' => now(),
                ]);
                $inAppDelivered++;
            } catch (\Throwable $e) {
                Log::warning("site-assistant cut-off alert in-app failed for user {$u->id}: " . $e->getMessage());
            }
        }

        // Email fan-out: explicit list if the admin configured one,
        // otherwise every settings.manage user with a verified email.
        // Defence in depth: the controller already normalizes the
        // stored list, but historical / hand-edited rows may still
        // contain malformed addresses, so re-validate here too.
        $explicit = [];
        foreach (preg_split('/[\s,;]+/', (string) ($cfg['cutoff_alert_emails'] ?? '')) ?: [] as $p) {
            $p = strtolower(trim((string) $p));
            if ($p !== '' && filter_var($p, FILTER_VALIDATE_EMAIL)) {
                $explicit[$p] = true;
            }
        }
        $explicit = array_keys($explicit);
        if (! empty($explicit)) {
            $emails = $explicit;
        } else {
            $emails = $admins
                ->filter(fn ($u) => $u->email && $u->email_verified_at)
                ->pluck('email')
                ->unique()
                ->values()
                ->all();
        }

        $emailsSent = 0;
        foreach ($emails as $email) {
            try {
                Mail::raw($body . "\n\n" . $url, function ($m) use ($email, $subject) {
                    $m->to($email)->subject($subject);
                });
                $emailsSent++;
            } catch (\Throwable $e) {
                Log::warning("site-assistant cut-off alert email to {$email} failed: " . $e->getMessage());
            }
        }

        SiteAssistantSettings::update([
            'cutoff_alert_last_sent_at' => now()->toIso8601String(),
        ]);

        $this->info("Alert dispatched — in-app: {$inAppDelivered}, email: {$emailsSent}.");
        return self::SUCCESS;
    }
}
