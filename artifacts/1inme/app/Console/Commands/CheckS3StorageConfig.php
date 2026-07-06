<?php

namespace App\Console\Commands;

use App\Services\Integrations\StorageHealthAlerts;
use Illuminate\Console\Command;

/**
 * Scheduled safety net for a misconfigured S3 user-content storage backend.
 *
 * User content is S3-only (no local-disk fallback), so an incomplete S3
 * config means every file upload fails loudly. The boot path already logs a
 * warning and fires a one-off admin alert on web boots; this hourly command
 * is the automated backstop that (a) keeps re-checking even when no web
 * traffic boots the app, and (b) sends the all-clear once an admin fixes the
 * configuration. All dedup/cooldown state lives in app_settings under
 * `storage_health` (see StorageHealthAlerts), so a per-hour cadence never
 * spams admins.
 */
class CheckS3StorageConfig extends Command
{
    protected $signature = 'storage:check-s3-config
                            {--force : Bypass the cooldown window and re-send even if recently alerted}';

    protected $description = 'Detect a misconfigured S3 user-content storage backend and alert admins (in-app + email).';

    public function handle(): int
    {
        $result = StorageHealthAlerts::check((bool) $this->option('force'));

        if ($result['configured']) {
            $this->info(
                $result['action'] === 'recovery_sent'
                    ? 'S3 storage is configured again — recovery all-clear dispatched to admins.'
                    : 'S3 storage is fully configured — nothing to do.'
            );
            return self::SUCCESS;
        }

        $missing = implode(', ', $result['missing']);
        $this->error("S3 storage is misconfigured — missing: {$missing}. Uploads will fail until this is fixed.");

        $this->info(match ($result['action']) {
            'alert_sent' => 'Admin alert dispatched (in-app + email).',
            'cooldown'   => 'Within cooldown window — not re-sending (use --force to override).',
            default      => 'No alert action taken.',
        });

        return self::SUCCESS;
    }
}
