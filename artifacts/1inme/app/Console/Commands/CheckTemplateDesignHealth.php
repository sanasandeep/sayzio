<?php

namespace App\Console\Commands;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Support\TemplateDesignHealth;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Proactively detect saved page/card templates whose stored snapshots have
 * developed design issues and alert admins (in-app + email) before visitors
 * see a degraded public page.
 *
 * Context: template snapshots are validated by {@see \App\Modules\User\Support\TemplateSnapshotValidator}
 * at *save* time, but a later code change (a removed block type, a retired
 * design-variant key) can silently invalidate a snapshot that was valid when
 * it was saved. A broken snapshot doesn't throw — it just renders with
 * stripped styling or a blank/unknown block — so it's only visible if an admin
 * happens to open the templates index. This scheduled check is the automated
 * safety net: it re-runs the validator across every active template, and when
 * any template starts failing it fans the warning out to admins and sends an
 * all-clear once everything is valid again.
 *
 * Mirrors {@see CheckPendingMigrations}. Dedup / cooldown state lives in
 * `app_settings` (so it survives deploys and multiple schedulers) under the
 * `template_design_health.*` keys:
 *   - template_design_health.alerting     — true while a broken episode is open
 *   - template_design_health.last_sent_at — ISO-8601 of the last alert (cooldown)
 *   - template_design_health.last_count   — broken count at the last alert
 *   - template_design_health.last_refs    — sorted "kind:id" refs at last alert
 *
 * The cooldown stops a frequent cadence from spamming admins, but a *change*
 * in the set of broken templates (a newly-broken one appearing) bypasses the
 * cooldown so newly-degraded templates are surfaced promptly. --force bypasses
 * the cooldown entirely for manual runs.
 */
class CheckTemplateDesignHealth extends Command
{
    protected $signature = 'templates:check-design-health
                            {--force : Bypass the cooldown window and re-send even if recently alerted}';

    protected $description = 'Detect saved templates that have developed design issues and alert admins (in-app + email).';

    /** Don't re-alert for the same unchanged broken set more often than this. */
    private const COOLDOWN_HOURS = 6;

    public function handle(): int
    {
        $report = TemplateDesignHealth::compute();

        // Refresh the cached report so any consumer reflects reality immediately.
        TemplateDesignHealth::flush();

        if (! ($report['available'] ?? false)) {
            // DB unreachable or probe failed — don't alert on a transient error.
            $this->warn('Could not scan templates: ' . ($report['error'] ?? 'unknown'));
            return self::SUCCESS;
        }

        $broken = $report['broken'];
        $count  = count($broken);

        if ($count === 0) {
            $this->info("All {$report['scanned']} active template(s) are valid — no design issues.");
            // Recovery: if we previously alerted, send an all-clear and close
            // the episode.
            if ($this->state('alerting', false)) {
                $this->dispatchRecovery((int) $this->state('last_count', 0));
            }
            return self::SUCCESS;
        }

        // Loud marker so log-based alerting catches it regardless of cooldown.
        Log::error(
            "::1inme:: TEMPLATE DESIGN ISSUES — {$count} active template(s) have snapshots that "
            . 'no longer validate and would silently degrade on the public page. Broken: '
            . implode(', ', array_map([TemplateDesignHealth::class, 'ref'], $broken))
        );

        $this->error("Broken templates — {$count}: " . implode(', ', array_map(
            fn ($r) => $r['name'] . ' (' . TemplateDesignHealth::ref($r) . ')',
            $broken
        )));

        // Cooldown — skip the fan-out if we alerted recently AND the broken set
        // hasn't changed since (unless --force). A newly-broken template
        // bypasses the cooldown so it isn't hidden for up to COOLDOWN_HOURS.
        $refs     = $this->refsOf($broken);
        $lastRefs = (array) $this->state('last_refs', []);
        $setChanged = $refs !== $lastRefs;
        $lastSent = $this->state('last_sent_at');

        if (! $this->option('force') && ! $setChanged && $lastSent) {
            try {
                $lastSentAt = Carbon::parse($lastSent);
                if ($lastSentAt->greaterThan(now()->subHours(self::COOLDOWN_HOURS))) {
                    $this->info("Within cooldown window (last alert {$lastSentAt->diffForHumans()}), broken set unchanged — not re-sending.");
                    return self::SUCCESS;
                }
            } catch (\Throwable $e) {
                // Malformed timestamp — fall through and re-alert; the write
                // below heals the value.
            }
        }

        $this->dispatchAlert($broken, $refs);
        return self::SUCCESS;
    }

    /**
     * @param array<int,array{kind:string,id:int,name:string,slug:string,issues:array<int,string>}> $broken
     * @param array<int,string> $refs
     */
    private function dispatchAlert(array $broken, array $refs): void
    {
        $count   = count($broken);
        $admins  = $this->admins();
        $url     = $this->templatesUrl();
        $subject = "Template design issues: {$count} saved template(s) degraded";

        $lines = [];
        foreach (array_slice($broken, 0, 10) as $r) {
            $label = ucfirst($r['kind']) . ' template "' . $r['name'] . '"';
            $lines[] = $label . ': ' . implode(' ', $r['issues']);
        }
        if ($count > 10) {
            $lines[] = '… and ' . ($count - 10) . ' more.';
        }

        $body = "{$count} active Sayzio template(s) have saved snapshots that no longer validate. "
              . "A later code change (a removed block type or a retired design-variant key) has invalidated them, "
              . "so they would render with stripped styling or a blank/unknown block on the public page. "
              . "Review and re-save them from the Templates admin.\n\n"
              . implode("\n", $lines);

        $inApp = $this->fanOutInApp($admins, 'template_design_issues', $subject, $body, $url, [
            'broken_count' => $count,
            'broken'       => array_slice($broken, 0, 20),
        ]);
        $emails = $this->fanOutEmail($admins, $subject, $body, $url);

        $this->putState([
            'alerting'     => true,
            'last_sent_at' => now()->toIso8601String(),
            'last_count'   => $count,
            'last_refs'    => $refs,
        ]);

        $this->info("Alert dispatched — in-app: {$inApp}, email: {$emails}.");
    }

    private function dispatchRecovery(int $previousCount): void
    {
        $admins  = $this->admins();
        $url     = $this->templatesUrl();
        $subject = 'Template design issues resolved';
        $body    = "Good news — all active Sayzio templates validate again"
                 . ($previousCount > 0 ? " (was {$previousCount} broken)." : '.')
                 . ' No further action needed.';

        $inApp  = $this->fanOutInApp($admins, 'template_design_ok', $subject, $body, $url, []);
        $emails = $this->fanOutEmail($admins, $subject, $body, $url);

        $this->putState([
            'alerting'     => false,
            'recovered_at' => now()->toIso8601String(),
            'last_count'   => 0,
            'last_refs'    => [],
        ]);

        $this->info("Recovery dispatched — in-app: {$inApp}, email: {$emails}.");
    }

    /**
     * Sorted "kind:id" refs for the current broken set, used to detect when
     * the set changes between runs.
     *
     * @param array<int,array<string,mixed>> $broken
     * @return array<int,string>
     */
    private function refsOf(array $broken): array
    {
        $refs = array_map([TemplateDesignHealth::class, 'ref'], $broken);
        sort($refs);
        return array_values($refs);
    }

    /**
     * Operators who opted in to operational alerts. Mirrors the audience used
     * by the other ops commands (schema health, site-assistant cut-offs).
     */
    private function admins()
    {
        return User::query()->withPermission('user.ops_alerts.receive')->get();
    }

    private function templatesUrl(): string
    {
        foreach (['admin.templates.index', 'admin.templates', 'admin.dashboard'] as $name) {
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
                Log::warning("template-design-health in-app alert failed for user {$u->id}: " . $e->getMessage());
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
                Log::warning("template-design-health alert email to {$email} failed: " . $e->getMessage());
            }
        }
        return $sent;
    }

    private function state(string $key, $default = null)
    {
        $all = AppSetting::get('template_design_health', []);
        return is_array($all) ? ($all[$key] ?? $default) : $default;
    }

    /**
     * @param array<string,mixed> $patch
     */
    private function putState(array $patch): void
    {
        try {
            $all = AppSetting::get('template_design_health', []);
            $all = is_array($all) ? $all : [];
            AppSetting::put('template_design_health', array_merge($all, $patch));
        } catch (\Throwable $e) {
            Log::warning('template-design-health state write failed: ' . $e->getMessage());
        }
    }
}
