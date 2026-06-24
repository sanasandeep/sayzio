<?php

namespace App\Console\Commands;

use App\Modules\Admin\Services\AdminActionLogger;
use App\Modules\Admin\Services\UserAccountNotifier;
use App\Modules\User\Models\User;
use Illuminate\Console\Command;

/**
 * Auto-lifts admin temporary holds whose `reactivate_at` date has
 * arrived. The login flow + suspension middleware also opportunistically
 * clear an elapsed hold, so this scheduled sweep mainly catches accounts
 * whose owner never tries to sign in during the window. Idempotent: it
 * only touches still-suspended rows with a past reactivation date and
 * clears the suspension markers afterwards.
 */
class ReactivateSuspendedUsers extends Command
{
    protected $signature = 'users:reactivate-due';

    protected $description = 'Auto-reactivate suspended accounts whose reactivation date has arrived';

    public function handle(AdminActionLogger $audit, UserAccountNotifier $notifier): int
    {
        $reactivated = 0;

        User::query()
            ->whereNotNull('suspended_at')
            ->whereNotNull('reactivate_at')
            ->where('reactivate_at', '<=', now())
            ->chunkById(200, function ($users) use ($audit, $notifier, &$reactivated) {
                foreach ($users as $user) {
                    $user->forceFill([
                        'suspended_at'      => null,
                        'suspension_reason' => null,
                        'suspended_by'      => null,
                        'reactivate_at'     => null,
                    ])->save();

                    $audit->log(AdminActionLogger::ACCOUNT_REACTIVATED, $user, ['auto' => true], null);
                    $notifier->reactivated($user->fresh());
                    $reactivated++;
                }
            });

        $this->info("Reactivated {$reactivated} account(s).");
        return self::SUCCESS;
    }
}
