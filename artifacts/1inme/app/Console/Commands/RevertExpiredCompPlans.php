<?php

namespace App\Console\Commands;

use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Services\AdminActionLogger;
use App\Modules\Admin\Services\UserAccountNotifier;
use App\Modules\User\Models\User;
use App\Modules\User\Services\ReferralService;
use Illuminate\Console\Command;

/**
 * Reverts accounts whose comp / time-limited plan window has elapsed back
 * to the default (free) plan. The admin "assign plan → free for N days"
 * flow records `comp_plan_expires_at`; this job (scheduled) closes the
 * window. Idempotent: it only touches rows whose comp window is in the
 * past and clears the comp markers afterwards so a re-run is a no-op.
 */
class RevertExpiredCompPlans extends Command
{
    protected $signature = 'plans:revert-expired-comps';

    protected $description = 'Revert expired comp/time-limited plans to the default plan';

    public function handle(
        ReferralService $referrals,
        AdminActionLogger $audit,
        UserAccountNotifier $notifier
    ): int {
        $default = Plan::defaultPlan();
        if (! $default) {
            $this->warn('No default plan resolved; skipping.');
            return self::SUCCESS;
        }

        $reverted = 0;

        User::query()
            ->whereNotNull('comp_plan_expires_at')
            ->where('comp_plan_expires_at', '<=', now())
            ->chunkById(200, function ($users) use ($default, $referrals, $audit, $notifier, &$reverted) {
                foreach ($users as $user) {
                    $previousPlanId = $user->plan_id;

                    $user->forceFill([
                        'plan_id'              => $default->id,
                        'plan_expires_at'      => null,
                        'comp_plan_expires_at' => null,
                        'comp_plan_granted_by' => null,
                    ])->save();

                    if ($default->id != $previousPlanId) {
                        $referrals->handlePlanActivation($user->fresh(), $default);
                    }

                    $audit->log(AdminActionLogger::PLAN_ASSIGNED, $user, [
                        'plan_id'          => $default->id,
                        'plan_name'        => $default->name,
                        'previous_plan_id' => $previousPlanId,
                        'comp_expired'     => true,
                    ], null);

                    $notifier->planAssigned($user->fresh(), $default->name, null);
                    $reverted++;
                }
            });

        $this->info("Reverted {$reverted} expired comp plan(s).");
        return self::SUCCESS;
    }
}
