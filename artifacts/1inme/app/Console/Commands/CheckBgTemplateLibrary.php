<?php

namespace App\Console\Commands;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\BgTemplate;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Proactively detect a bare (or dangerously thin) biolink background template
 * library and alert admins before users quietly hit an empty picker.
 *
 * Context: the biolink editor's Appearance → Page background → Template picker
 * gracefully degrades to a "No templates available yet" empty state when the
 * `bg_templates` table has zero active rows. That's the right safety net for
 * the end user but fails silently for the operator — the library once sat
 * empty unnoticed. This scheduled check is the automated safety net: when the
 * active count drops to zero (or below a sane floor), it fans the warning out
 * to admins (in-app + email) and sends an all-clear on recovery.
 *
 * Mirrors {@see CheckTemplateGallery}. Dedup / cooldown state lives in
 * `app_settings` (so it survives deploys and multiple schedulers) under the
 * `bg_template_health.*` keys:
 *   - bg_template_health.alerting     — true while a shortage episode is open
 *   - bg_template_health.last_sent_at — ISO-8601 of the last alert (cooldown)
 *   - bg_template_health.signature    — severity of the last alert ("empty"
 *                                        or "low"), so a worsening episode
 *                                        (low → empty) re-alerts immediately
 *                                        instead of waiting out the cooldown.
 *
 * The cooldown stops a per-hour cadence from spamming admins; --force bypasses
 * it for manual runs.
 */
class CheckBgTemplateLibrary extends Command
{
    protected $signature = 'bg-templates:check-library
                            {--force : Bypass the cooldown window and re-send even if recently alerted}';

    protected $description = 'Detect an empty or dangerously thin biolink background template library and alert admins (in-app + email).';

    /** Don't re-alert for the same open episode more often than this. */
    private const COOLDOWN_HOURS = 6;

    /**
     * Below this many active templates the library is considered dangerously
     * thin (the seeded catalog ships well above this).
     */
    public const MIN_ACTIVE = 50;

    public function handle(): int
    {
        try {
            $active = BgTemplate::query()->where('is_active', true)->count();
        } catch (\Throwable $e) {
            // DB unreachable or table missing — don't alert on a transient
            // error, just note it.
            $this->warn('Could not determine background template library status: ' . $e->getMessage());
            return self::SUCCESS;
        }

        if ($active >= self::MIN_ACTIVE) {
            $this->info("Background template library has {$active} active template(s) (floor " . self::MIN_ACTIVE . ') — nothing to alert.');
            // Recovery: if we previously alerted, send an all-clear and close
            // the episode.
            if ($this->state('alerting', false)) {
                $this->dispatchRecovery($active);
            }
            return self::SUCCESS;
        }

        $isEmpty   = $active === 0;
        $signature = $isEmpty ? 'empty' : 'low';

        // Loud marker so log-based alerting catches it regardless of cooldown.
        if ($isEmpty) {
            Log::error(
                '::1inme:: BIOLINK BACKGROUND TEMPLATE LIBRARY EMPTY — zero active bg_templates; '
                . 'the Appearance → Page background → Template picker is showing its "No templates available yet" '
                . 'empty state to every user until a template is added or re-activated.'
            );
            $this->error('Background template library is empty — zero active templates.');
        } else {
            Log::error(
                "::1inme:: BIOLINK BACKGROUND TEMPLATE LIBRARY LOW — only {$active} active bg_templates "
                . '(floor ' . self::MIN_ACTIVE . '); the Appearance → Page background → Template picker is running '
                . 'dangerously thin, likely from a bulk wipe or deactivation.'
            );
            $this->error("Background template library is low — only {$active} active template(s) (floor " . self::MIN_ACTIVE . ').');
        }

        // Cooldown — skip the fan-out if we alerted recently for the SAME open
        // episode (unless --force). A worsening episode (low → empty) bypasses
        // the cooldown so a full wipe is reported promptly.
        $lastSent      = $this->state('last_sent_at');
        $lastSignature = $this->state('signature');
        $sameEpisode   = $this->state('alerting', false) && $lastSignature === $signature;
        if (! $this->option('force') && $sameEpisode && $lastSent) {
            try {
                $lastSentAt = Carbon::parse($lastSent);
                if ($lastSentAt->greaterThan(now()->subHours(self::COOLDOWN_HOURS))) {
                    $this->info("Within cooldown window (last alert {$lastSentAt->diffForHumans()}, same severity) — not re-sending.");
                    return self::SUCCESS;
                }
            } catch (\Throwable $e) {
                // Malformed timestamp — fall through and re-alert; the write
                // below heals the value.
            }
        }

        $this->dispatchAlert($isEmpty, $active, $signature);
        return self::SUCCESS;
    }

    private function dispatchAlert(bool $isEmpty, int $active, string $signature): void
    {
        $admins = $this->admins();
        $url    = $this->bgTemplatesUrl();

        if ($isEmpty) {
            $type    = 'bg_template_library_empty';
            $subject = 'Background template library is empty';
            $body    = "Sayzio has no active biolink background templates, so the Appearance → Page background → "
                     . "Template picker is silently showing \"No templates available yet\" to every user. Add or "
                     . "re-activate templates (or re-run the background template seeder) so the picker offers "
                     . "choices again.";
        } else {
            $type    = 'bg_template_library_low';
            $subject = 'Background template library is running low';
            $body    = "Only {$active} biolink background template(s) are active — below the expected floor of "
                     . self::MIN_ACTIVE . ". This usually means a bulk wipe or deactivation. The Appearance → "
                     . "Page background → Template picker still works but offers users far fewer choices than "
                     . "intended; add or re-activate templates to restore the library.";
        }

        $inApp  = $this->fanOutInApp($admins, $type, $subject, $body, $url, ['active_count' => $active]);
        $emails = $this->fanOutEmail($admins, $subject, $body, $url);

        $this->putState([
            'alerting'     => true,
            'last_sent_at' => now()->toIso8601String(),
            'signature'    => $signature,
        ]);

        $this->info("Alert dispatched — in-app: {$inApp}, email: {$emails}.");
    }

    private function dispatchRecovery(int $activeCount): void
    {
        $admins  = $this->admins();
        $url     = $this->bgTemplatesUrl();
        $subject = 'Background template library restored';
        $body    = "Good news — the biolink background template library is healthy again with {$activeCount} "
                 . "active template(s) (floor " . self::MIN_ACTIVE . "). The Appearance → Page background → "
                 . "Template picker is offering a full set of choices. No further action needed.";

        $inApp  = $this->fanOutInApp($admins, 'bg_template_library_ok', $subject, $body, $url, ['active_count' => $activeCount]);
        $emails = $this->fanOutEmail($admins, $subject, $body, $url);

        $this->putState([
            'alerting'     => false,
            'signature'    => null,
            'recovered_at' => now()->toIso8601String(),
        ]);

        $this->info("Recovery dispatched — in-app: {$inApp}, email: {$emails}.");
    }

    /**
     * Operators who opted in to operational alerts. Mirrors the audience used
     * by the other ops commands (schema health, template gallery health).
     */
    private function admins()
    {
        return User::query()->withPermission('user.ops_alerts.receive')->get();
    }

    private function bgTemplatesUrl(): string
    {
        foreach (['admin.bg-templates.index', 'admin.dashboard'] as $name) {
            try {
                return \App\Modules\Common\Support\PlatformHosts::outboundUrl(route($name));
            } catch (\Throwable $e) {
                // try the next candidate
            }
        }
        return \App\Modules\Common\Support\PlatformHosts::outboundUrl(url('/admin'));
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
                Log::warning("bg-template-library in-app alert failed for user {$u->id}: " . $e->getMessage());
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
                Log::warning("bg-template-library alert email to {$email} failed: " . $e->getMessage());
            }
        }
        return $sent;
    }

    private function state(string $key, $default = null)
    {
        $all = AppSetting::get('bg_template_health', []);
        return is_array($all) ? ($all[$key] ?? $default) : $default;
    }

    /**
     * @param array<string,mixed> $patch
     */
    private function putState(array $patch): void
    {
        try {
            $all = AppSetting::get('bg_template_health', []);
            $all = is_array($all) ? $all : [];
            AppSetting::put('bg_template_health', array_merge($all, $patch));
        } catch (\Throwable $e) {
            Log::warning('bg-template-library state write failed: ' . $e->getMessage());
        }
    }
}
