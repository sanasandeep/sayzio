<?php

namespace App\Jobs;

use App\Modules\Admin\Models\Admin;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Defers the admin `last_login_at` write off the request path.
 *
 * Admin logins do not use LoginAlertService, so this is a lightweight job
 * whose sole purpose is to persist the timestamp without blocking the
 * response. The timestamp is captured at dispatch time so it reflects when
 * the admin actually signed in.
 */
class RecordAdminLastLoginJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        public readonly int $adminId,
        public readonly ?Carbon $loggedInAt = null,
    ) {}

    public function handle(): void
    {
        $admin = Admin::find($this->adminId);
        if (!$admin) {
            return;
        }

        try {
            $admin->forceFill(['last_login_at' => $this->loggedInAt ?? now()])->save();
        } catch (\Throwable $e) {
            Log::warning('RecordAdminLastLoginJob: update failed', [
                'admin_id' => $this->adminId,
                'error'    => $e->getMessage(),
            ]);
        }
    }
}
