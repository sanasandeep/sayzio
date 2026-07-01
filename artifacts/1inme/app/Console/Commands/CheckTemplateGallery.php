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
 * Proactively detect onboarding template-gallery coverage gaps and alert admins
 * before new users quietly land on a bare (or persona-less) setup screen.
 *
 * Context: the onboarding wizard gracefully degrades to an empty-state escape
 * ("No templates available yet" → Continue to dashboard) when there are zero
 * active page templates to offer. It also silently offers no tailored
 * "Recommended for {persona}" row when a specific persona has zero active
 * templates tagging it — the gallery isn't blank (the browse-all list still
 * shows), but that persona's new users get no recommendation. Both are the
 * right safety net for the end user yet fail silently for the operator: nobody
 * is told the catalog (or a persona) has gone bare, so it can persist for days
 * unnoticed. This scheduled check is the automated safety net: it inspects
 * per-persona coverage and, when at least one persona has no recommended
 * template, fans the warning out to admins (in-app + email) and sends an
 * all-clear once every persona is covered again.
 *
 * Mirrors {@see CheckPendingMigrations}. Dedup / cooldown state lives in
 * `app_settings` (so it survives deploys and multiple schedulers) under the
 * `template_gallery_health.*` keys:
 *   - template_gallery_health.alerting     — true while a gap episode is open
 *   - template_gallery_health.last_sent_at — ISO-8601 of the last alert (cooldown)
 *   - template_gallery_health.signature    — sorted uncovered-persona slugs of
 *                                             the last alert, so a worsening gap
 *                                             (new persona goes bare) re-alerts
 *                                             immediately instead of waiting out
 *                                             the cooldown.
 *
 * The cooldown stops a per-hour cadence from spamming admins; --force bypasses
 * it for manual runs.
 */
class CheckTemplateGallery extends Command
{
    protected $signature = 'templates:check-gallery
                            {--force : Bypass the cooldown window and re-send even if recently alerted}';

    protected $description = 'Detect onboarding template-gallery coverage gaps (empty catalog or personas with no recommended templates) and alert admins (in-app + email).';

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

        $uncovered = $report['uncovered'] ?? [];
        $isEmpty   = !empty($report['empty']);

        if (empty($uncovered)) {
            $this->info("Onboarding gallery has {$report['active']} active template(s), every persona covered — nothing to alert.");
            // Recovery: if we previously alerted, send an all-clear and close
            // the episode.
            if ($this->state('alerting', false)) {
                $this->dispatchRecovery((int) $report['active']);
            }
            return self::SUCCESS;
        }

        $slugs      = collect($uncovered)->pluck('slug')->sort()->values()->all();
        $labels     = collect($uncovered)->pluck('label')->all();
        $signature  = implode(',', $slugs);
        $labelList  = $this->humanList($labels);

        // Loud marker so log-based alerting catches it regardless of cooldown.
        if ($isEmpty) {
            Log::error(
                '::1inme:: ONBOARDING TEMPLATE GALLERY EMPTY — zero active page templates; '
                . 'the onboarding wizard is degrading to its empty-state escape and new users are landing '
                . 'on a bare setup screen until a template is added or re-activated.'
            );
            $this->error('Onboarding template gallery is empty — zero active page templates.');
        } else {
            Log::error(
                '::1inme:: ONBOARDING TEMPLATE GALLERY PERSONA GAP — no active recommended templates for: '
                . $labelList . '; new users who pick '
                . (count($labels) === 1 ? 'that persona' : 'those personas')
                . ' get no tailored recommendation in the onboarding wizard until a template is added/tagged.'
            );
            $this->error('Onboarding persona coverage gap — no recommended templates for: ' . $labelList . '.');
        }

        // Cooldown — skip the fan-out if we alerted recently for the SAME open
        // episode (unless --force). A worsening gap (the uncovered set changed)
        // bypasses the cooldown so a newly-bare persona is reported promptly.
        $lastSent      = $this->state('last_sent_at');
        $lastSignature = $this->state('signature');
        $sameEpisode   = $this->state('alerting', false) && $lastSignature === $signature;
        if (! $this->option('force') && $sameEpisode && $lastSent) {
            try {
                $lastSentAt = Carbon::parse($lastSent);
                if ($lastSentAt->greaterThan(now()->subHours(self::COOLDOWN_HOURS))) {
                    $this->info("Within cooldown window (last alert {$lastSentAt->diffForHumans()}, same gap) — not re-sending.");
                    return self::SUCCESS;
                }
            } catch (\Throwable $e) {
                // Malformed timestamp — fall through and re-alert; the write
                // below heals the value.
            }
        }

        $this->dispatchAlert($isEmpty, $labels, $signature);
        return self::SUCCESS;
    }

    /**
     * @param  array<int,string>  $labels  Uncovered persona labels.
     */
    private function dispatchAlert(bool $isEmpty, array $labels, string $signature): void
    {
        $admins  = $this->admins();
        $url     = $this->templatesUrl();

        if ($isEmpty) {
            $type    = 'template_gallery_empty';
            $subject = 'Onboarding template gallery is empty';
            $body    = "Sayzio has no active page templates, so the new-user onboarding wizard is silently "
                     . "degrading to its \"No templates available yet\" escape and new users are landing on a bare "
                     . "setup screen. Add or re-activate at least one template so onboarding can offer a starting "
                     . "point again.";
        } else {
            $list    = $this->humanList($labels);
            $type    = 'template_gallery_persona_gap';
            $subject = 'Onboarding personas have no recommended templates';
            $noun    = count($labels) === 1 ? 'persona has' : 'personas have';
            $them    = count($labels) === 1 ? 'that persona' : 'those personas';
            $body    = "These {$noun} no active recommended page templates: {$list}. New users who pick {$them} "
                     . "in onboarding get no tailored \"Recommended for you\" row — only the generic browse-all list. "
                     . "Add a template (or tag an existing one) for each so onboarding can recommend a starting point.";
        }

        $inApp  = $this->fanOutInApp($admins, $type, $subject, $body, $url, ['personas' => $labels]);
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
        $url     = $this->templatesUrl();
        $subject = 'Onboarding template coverage restored';
        $body    = "Good news — every onboarding persona now has at least one active recommended template "
                 . "({$activeCount} active in total). New users will be offered a tailored starting point in "
                 . "the setup wizard. No further action needed.";

        $inApp  = $this->fanOutInApp($admins, 'template_gallery_ok', $subject, $body, $url, []);
        $emails = $this->fanOutEmail($admins, $subject, $body, $url);

        $this->putState([
            'alerting'     => false,
            'signature'    => null,
            'recovered_at' => now()->toIso8601String(),
        ]);

        $this->info("Recovery dispatched — in-app: {$inApp}, email: {$emails}.");
    }

    /**
     * Join a list of labels into a natural-language phrase:
     * ["A"] => "A", ["A","B"] => "A and B", ["A","B","C"] => "A, B and C".
     *
     * @param  array<int,string>  $items
     */
    private function humanList(array $items): string
    {
        $items = array_values(array_filter($items, fn ($v) => $v !== null && $v !== ''));
        $n = count($items);
        if ($n === 0) {
            return '';
        }
        if ($n === 1) {
            return (string) $items[0];
        }
        $last = array_pop($items);
        return implode(', ', $items) . ' and ' . $last;
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
