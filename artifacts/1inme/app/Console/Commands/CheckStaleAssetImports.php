<?php

namespace App\Console\Commands;

use App\Modules\Admin\Models\AdminAssetImport;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use App\Services\Integrations\InternalAlertDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Sweep for Asset Vault zip imports whose worker died mid-run.
 *
 * ProcessAdminAssetZipImportJob streams progress back onto the import row
 * (updated_at moves on every progress save). If the queue worker crashes or
 * the container is recycled mid-import, the row is left stuck in an active
 * status (pending/downloading/processing) forever and the vault UI keeps
 * showing a phantom "import in progress".
 *
 * This scheduled sweep auto-fails any active import whose row has not been
 * touched for longer than the stale window, marking it failed with a
 * "worker lost" error so the admin can simply re-run the import.
 *
 * When one or more imports are auto-failed, a one-time ops alert fans out to
 * admins (in-app + email via the centralized Emailer, plus a best-effort
 * Slack/Discord ping) naming each import's source and the reason — mirroring
 * {@see CheckQueueBacklog}. Because a row is only ever transitioned
 * active → failed once, the same already-failed row can never re-alert.
 *
 * The stale window is overridable via `app_settings` under
 * {@see SETTINGS_KEY} (key: stale_minutes); the class constant is only the
 * default. The default comfortably exceeds the job's 1h timeout plus the
 * 30-minute download timeout, so a slow-but-alive import is never killed.
 */
class CheckStaleAssetImports extends Command
{
    protected $signature = 'assets:check-stale-imports';

    protected $description = 'Auto-fail Asset Vault zip imports whose worker died mid-run (row untouched past the stale window) and alert ops admins once per failed import.';

    /** Default: an active import counts as worker-lost after this many minutes without a row update. */
    public const STALE_MINUTES = 120;

    /** Sane bounds for the overridable stale window. */
    public const MIN_STALE_MINUTES = 30;
    public const MAX_STALE_MINUTES = 1440;

    /** AppSetting key holding optional overrides (stale_minutes). */
    public const SETTINGS_KEY = 'asset_import_stale_alerts';

    /** Error text stamped onto auto-failed rows (also shown in /admin/assets). */
    public const WORKER_LOST_ERROR = 'Import worker lost — the background job stopped reporting progress and was auto-failed. Re-run the import.';

    public static function staleMinutes(): int
    {
        try {
            $all   = AppSetting::get(self::SETTINGS_KEY, []);
            $value = is_array($all) ? ($all['stale_minutes'] ?? null) : null;
        } catch (\Throwable $e) {
            $value = null;
        }

        if (! is_numeric($value)) {
            return self::STALE_MINUTES;
        }

        return max(self::MIN_STALE_MINUTES, min(self::MAX_STALE_MINUTES, (int) $value));
    }

    public function handle(): int
    {
        $staleMinutes = self::staleMinutes();
        $cutoff       = now()->subMinutes($staleMinutes);

        $stale = AdminAssetImport::query()
            ->whereIn('status', ['pending', 'downloading', 'processing'])
            ->where('updated_at', '<=', $cutoff)
            ->orderBy('id')
            ->get();

        if ($stale->isEmpty()) {
            $this->info('No stale Asset Vault imports — all active imports are progressing.');

            return self::SUCCESS;
        }

        $failed = [];
        foreach ($stale as $import) {
            try {
                $import->forceFill([
                    'status'       => 'failed',
                    'error'        => self::WORKER_LOST_ERROR,
                    'completed_at' => now(),
                ])->save();
                $failed[] = $import;
            } catch (\Throwable $e) {
                Log::warning("stale-asset-import auto-fail for import {$import->id} failed: " . $e->getMessage());
            }
        }

        if ($failed === []) {
            $this->error('Found stale imports but could not mark any of them failed — see the log.');

            return self::FAILURE;
        }

        $count = count($failed);
        Log::error(
            "::1inme:: ASSET IMPORT WORKER LOST — {$count} zip import(s) stuck with no progress for {$staleMinutes}+ minute(s); "
            . 'auto-failed. The import(s) must be re-run from /admin/assets.'
        );

        $this->dispatchAlert($failed, $staleMinutes);
        $this->error("Auto-failed {$count} worker-lost zip import(s).");

        return self::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────────

    /**
     * @param array<int, AdminAssetImport> $failed
     */
    private function dispatchAlert(array $failed, int $staleMinutes): void
    {
        $count   = count($failed);
        $admins  = $this->admins();
        $url     = $this->panelUrl();
        $subject = $count === 1
            ? 'Asset Vault zip import failed — worker lost'
            : "{$count} Asset Vault zip imports failed — worker lost";

        $lines = [];
        foreach ($failed as $import) {
            $lines[] = '  - ' . $this->describeSource($import)
                . ' (started ' . ($import->started_at?->toDayDateTimeString() ?? 'never')
                . ", last progress {$import->processed_entries}/{$import->total_entries} entries)";
        }

        $body = ($count === 1
                ? 'An Asset Vault zip import stopped reporting progress'
                : "{$count} Asset Vault zip imports stopped reporting progress")
              . " for more than {$staleMinutes} minute(s) — the background worker was likely lost "
              . "(worker crash or container recycle) — and " . ($count === 1 ? 'it has' : 'they have')
              . " been auto-failed:\n\n"
              . implode("\n", $lines) . "\n\n"
              . 'Reason: ' . self::WORKER_LOST_ERROR . "\n\n"
              . 'Nothing was lost besides the in-flight run — re-imports are idempotent, so simply re-run '
              . 'the import from the Asset Vault. If this keeps happening, check the queue worker health.';

        $inApp  = $this->fanOutInApp($admins, $subject, $body, $url, [
            'count'         => $count,
            'stale_minutes' => $staleMinutes,
            'import_ids'    => array_map(fn ($i) => $i->id, $failed),
        ]);
        $emails = $this->fanOutEmail($admins, $subject, $body, $url);

        try {
            InternalAlertDispatcher::send(
                $subject,
                "Auto-failed {$count} worker-lost Asset Vault zip import(s) (no progress for {$staleMinutes}+ minutes). "
                . 'They must be re-run from the Asset Vault.',
                'error',
                ['Imports' => (string) $count, 'Stale window' => "{$staleMinutes}m"]
            );
        } catch (\Throwable $e) {
            Log::warning('stale-asset-import webhook alert failed: ' . $e->getMessage());
        }

        $this->info("Alert dispatched — in-app: {$inApp}, email: {$emails}.");
    }

    /** Human-readable "what was being imported" for the alert body. */
    private function describeSource(AdminAssetImport $import): string
    {
        $source = trim((string) $import->source);
        $label  = $source !== '' ? Str::limit($source, 160) : 'unknown source';

        return ($import->source_type === 'url' ? 'Remote archive ' : 'Uploaded archive ') . '"' . $label . '"';
    }

    /**
     * Operators who opted in to operational alerts — same audience as the
     * other ops health commands (queue backlog, schema health, storage).
     */
    private function admins()
    {
        return User::query()->withPermission('user.ops_alerts.receive')->get();
    }

    private function panelUrl(): string
    {
        try {
            return \App\Modules\Common\Support\PlatformHosts::outboundUrl(route('admin.assets.index'));
        } catch (\Throwable $e) {
            return \App\Modules\Common\Support\PlatformHosts::outboundUrl(url('/admin/assets'));
        }
    }

    /**
     * @param iterable $admins
     * @param array<string,mixed> $extra
     */
    private function fanOutInApp($admins, string $subject, string $body, string $url, array $extra): int
    {
        $delivered = 0;
        foreach ($admins as $u) {
            try {
                UserNotification::create([
                    'user_id' => $u->id,
                    'type'    => 'asset_import_worker_lost',
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
                Log::warning("stale-asset-import in-app alert failed for user {$u->id}: " . $e->getMessage());
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
                Log::warning("stale-asset-import alert email to {$email} failed: " . $e->getMessage());
            }
        }

        return $sent;
    }
}
