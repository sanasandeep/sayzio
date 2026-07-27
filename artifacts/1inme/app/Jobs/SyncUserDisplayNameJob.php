<?php

namespace App\Jobs;

use App\Modules\User\Models\User;
use App\Modules\User\Services\UserNameSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queued fan-out after a profile rename: rewrites every denormalized copy
 * of the user's display name (comments, community rosters, fan points,
 * subscriber entries, internally-linked contacts) and busts the cached
 * creator surfaces. Kept off the request path so profile save stays fast.
 *
 * Reads the CURRENT users.name at run time, so stacked renames are safe —
 * the last job to run wins with the latest name either way.
 */
class SyncUserDisplayNameJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $userId)
    {
    }

    public function handle(): void
    {
        $user = User::find($this->userId);
        if (!$user) {
            return;
        }
        UserNameSync::applyDenormalized($user);
    }
}
