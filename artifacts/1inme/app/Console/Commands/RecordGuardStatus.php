<?php

namespace App\Console\Commands;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Support\VersionRegistry;
use Illuminate\Console\Command;

/**
 * Record the pass/fail outcome of a parity guard run into app_settings so the
 * admin "Versions & Releases" hub can show a Sync Status panel without ever
 * executing guards on demand. Invoked from scripts/post-merge.sh (and any CI
 * job that wants to report) after each guard run.
 *
 * Usage: php artisan guards:record dialer_sync pass
 *        php artisan guards:record docs_parity fail --note="3 endpoints undocumented"
 */
class RecordGuardStatus extends Command
{
    protected $signature = 'guards:record {guard : Guard key (e.g. dialer_sync, docs_parity, doc_constants, api_server_paths)} {status : pass or fail} {--note= : Optional short detail}';

    protected $description = 'Record a parity-guard run result for the admin Versions & Releases sync-status panel';

    public function handle(): int
    {
        $guard  = (string) $this->argument('guard');
        $status = strtolower((string) $this->argument('status'));

        if (!in_array($status, ['pass', 'fail'], true)) {
            $this->error("Status must be 'pass' or 'fail', got '{$status}'.");

            return self::FAILURE;
        }

        $state = AppSetting::get(VersionRegistry::GUARD_STATUS_KEY, []);
        $state = is_array($state) ? $state : [];

        $state[$guard] = [
            'status' => $status,
            'ran_at' => now()->toIso8601String(),
            'note'   => (string) ($this->option('note') ?? '') ?: null,
        ];

        AppSetting::put(VersionRegistry::GUARD_STATUS_KEY, $state);
        $this->info("Recorded {$guard} = {$status}.");

        return self::SUCCESS;
    }
}
