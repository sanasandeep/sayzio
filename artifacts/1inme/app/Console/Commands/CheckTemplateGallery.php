<?php

namespace App\Console\Commands;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Support\TemplateGalleryHealth;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Proactively detect an empty onboarding template gallery and alert admins
 * before new users quietly land on a bare setup screen.
 *
 * Context: the onboarding wizard gracefully degrades to an empty-state escape
 * ("No templates available yet" → Continue to dashboard) when there are zero
 * active page templates to offer. That is the right safety net for the end user
 * but it fails silently for the operator — nobody is told the catalog has gone
 * empty, so new users could quietly land on a bare setup screen for days
 * without anyone noticing. This scheduled check is the automated safety net: it
 * counts active templates, and when the gallery is empty it fans the warning
 * out to admins (in-app + email) and sends an all-clear once at least one
 * active template exists again.
 *
 * Mirrors {@see CheckPendingMigrations}. Dedup / cooldown state lives in
 * `app_settings` (so it survives deploys and multiple schedulers) under the
 * `template_gallery_health.*` keys:
 *   - template_gallery_health.alerting     — true while an empty episode is open
 *   - template_gallery_health.last_sent_at — ISO-8601 of the last alert (cooldown)
 *
 * The cooldown stops a per-hour cadence from spamming admins; --force bypasses
 * it for manual runs.
 */
class CheckTemplateGallery extends Command
{
    protected $signature = 'templates:check-gallery
                            {--force : Bypass the cooldown window and re-send even if recently alerted}';

    protected $description = 'Detect an empty onboarding template gallery (zero active templates) and alert admins (in-app + email).';

    /** Don't re-alert for the same open episode more often than this. */
    private const COOLDOWN_HOURS = 6;

    public function handle(): int
    {
        $report = TemplateGalleryHealth::compute();

        // Refresh the cached report so the dashboard banner reflects reality
        // immediately after this run.
        TemplateGalleryHealth::flush();

        if (! ($report['available'] ?? false)) {
            // DB unreachable or probe failed — don't alert on a transient
            // error, just note it.
            $this->warn('Could not determine template gallery status: ' . ($report['error'] ?? 'unknown'));
            return self::SUCCESS;
        }

        if (empty($report['empty'])) {
            $this->info("Onboarding gallery has {$report['active']} active template(s) — nothing to alert.");
            // Recovery: if we previously alerted, send an all-clear and close
            // the episode.
            if ($this->state('alerting', false)) {
                $this->dispatchRecovery((int) $report['active']);
            }
            return self::SUCCESS;
        }

        // Loud marker so log-based alerting catches it regardless of cooldown.
        Log::error(
            '::1inme:: ONBOARDING TEMPLATE GALLERY EMPTY — zero active page templates; '
            . 'the onboarding wizard is degrading to its empty-state escape and new users are landing '
            . 'on a bare setup screen until a template is added or re-activated.'
        );

        $this->error('Onboarding template gallery is empty — zero active page templates.');

        // Cooldown — skip the fan-out if we alerted recently for the same open
        // episode (unless --force).
        $lastSent = $this->state('last_sent_at');
        if (! $this->option('force') && $lastSent) {
            try {
                $lastSentAt = Carbon::parse($lastSent);
                if ($lastSentAt->greaterThan(now()->subHours(self::COOLDOWN_HOURS))) {
                    $this->info("Within cooldown window (last alert {$lastSentAt->diffForHumans()}) — not re-sending.");
                    return self::SUCCESS;
                }
            } catch (\Throwable $e) {
                // Malformed timestamp — fall through and re-alert; the write
                // below heals the value.
            }
        }

        $this->dispatchAlert();
        return self::SUCCESS;
    }

    private function dispatchAlert(): void
    {
        $admins  = $this->admins();
        $url     = $this->templatesUrl();
        $subject = 'Onboarding template gallery is empty';
        $body    = "Sayzio has no active page templates, so the new-user onboarding wizard is silently "
                 . "degrading to its \"No templates available yet\" escape and new users are landing on a bare "
                 . "setup screen. Add or re-activate at least one template so onboarding can offer a starting "
                 . "point again.";

        $inApp  = $this->fanOutInApp($admins, 'template_gallery_empty', $subject, $body, $url, []);
        $emails = $this->fanOutEmail($admins, $subject, $body, $url);

        $this->putState([
            'alerting'     => true,
            'last_sent_at' => now()->toIso8601String(),
        ]);

        $this->info("Alert dispatched — in-app: {$inApp}, email: {$emails}.");
    }

    private function dispatchRecovery(int $activeCount): void
    {
        $admins  = $this->admins();
        $url     = $this->templatesUrl();
        $subject = 'Onboarding template gallery restocked';
        $body    = "Good news — the onboarding template gallery has active templates again "
                 . "({$activeCount} active). New users will be offered a starting point in the setup wizard. "
                 . "No further action needed.";

        $inApp  = $this->fanOutInApp($admins, 'template_gallery_ok', $subject, $body, $url, []);
        $emails = $this->fanOutEmail($admins, $subject, $body, $url);

        $this->putState([
            'alerting'     => false,
            'recovered_at' => now()->toIso8601String(),
        ]);

        $this->info("Recovery dispatched — in-app: {$inApp}, email: {$emails}.");
    }

    /**
     * Operators who opted in to operational alerts. Mirrors the audience used
     * by the other ops commands (schema health, template design health).
     */
    private function admins()
    {
        return User::query()->withPermission('user.ops_alerts.receive')->get();
    }

    private function templatesUrl(): string
    {
        foreach (['admin.templates.index', 'admin.templates', 'admin.dashboard'] as $name) {
            try {
                return route($name);
            } catch (\Throwable $e) {
                // try the next candidate
            }
        }
        return url('/admin');
    }

    /**
     * @param  iterable  $admins
     * @param  array<string,mixed>  $extra
     */
    private function fanOutInApp($admins, string $type, string $subject, string $body, string $url, array $extra): int
    {
        $delivered = 0;
        foreach ($admins as $u) {
            try {
                UserNotification::create([
                    'user_id' => $u->id,
                    'type'    => $type,
                    'data'    => array_merge([
                        'subject'    => $subject,
                        'body'       => $body,
                        'message'    => $body, // legacy field rendered by the notifications view
                        'url'        => $url,  // canonical key consumed by the in-app list
                        'target_url' => $url,  // legacy alias for older renderers
                    ], $extra),
                    'created_at' => now(),
                ]);
                $delivered++;
            } catch (\Throwable $e) {
                Log::warning("template-gallery in-app alert failed for user {$u->id}: " . $e->getMessage());
            }
        }
        return $delivered;
    }

    /**
     * @param iterable $admins
     */
    private function fanOutEmail($admins, string $subject, string $body, string $url): int
    {
        $emails = collect($admins)
            ->filter(fn ($u) => $u->email && $u->email_verified_at)
            ->pluck('email')
            ->unique()
            ->values()
            ->all();

        $sent = 0;
        foreach ($emails as $email) {
            try {
                \App\Modules\Common\Services\Emailer::send('system.health_alert', $email, [], [
                    'subject' => $subject,
                    'body'    => $body . "\n\n" . $url,
                    'format'  => 'text',
                ]);
                $sent++;
            } catch (\Throwable $e) {
                Log::warning("template-gallery alert email to {$email} failed: " . $e->getMessage());
            }
        }
        return $sent;
    }

    private function state(string $key, $default = null)
    {
        $all = AppSetting::get('template_gallery_health', []);
        return is_array($all) ? ($all[$key] ?? $default) : $default;
    }

    /**
     * @param array<string,mixed> $patch
     */
    private function putState(array $patch): void
    {
        try {
            $all = AppSetting::get('template_gallery_health', []);
            $all = is_array($all) ? $all : [];
            AppSetting::put('template_gallery_health', array_merge($all, $patch));
        } catch (\Throwable $e) {
            Log::warning('template-gallery state write failed: ' . $e->getMessage());
        }
    }
}
