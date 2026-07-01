<?php

namespace App\Jobs;

use App\Modules\User\Models\GoogleContactsAccount;
use App\Modules\User\Services\Contacts\GoogleContactsSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Run a throttle-respecting Google Contacts sync for one user, off the
 * request hot path. Fired non-blocking on web app open (see
 * {@see \App\Modules\User\Middleware\SyncGoogleContactsOnOpen}) so a
 * creator's page reflects contact edits made elsewhere within seconds
 * instead of waiting for the scheduled backstop.
 *
 * The per-account cooldown lives in {@see GoogleContactsSyncService::syncNow()},
 * so dispatching this on every app open is cheap — a hot account simply
 * short-circuits as "throttled".
 */
class SyncGoogleContactsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;

    public function __construct(private int $userId)
    {
    }

    public function handle(GoogleContactsSyncService $sync): void
    {
        $account = GoogleContactsAccount::where('user_id', $this->userId)->first();
        if (!$account) {
            return;
        }
        $sync->syncNow($account);
    }

    /** True when the user has a connected Google Contacts account. */
    public static function shouldQueue(int $userId): bool
    {
        return GoogleContactsAccount::where('user_id', $userId)->exists();
    }
}
