<?php

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\AdminActionAudit;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Single sink for the user-management audit trail. Every admin/staff
 * action in the suite (create account, assign plan, grant/deduct coins,
 * suspend/reactivate) calls one of the helpers here so the "Activity
 * log" viewer has a complete, filterable record of who did what.
 *
 * The operator is resolved from the admin guard and snapshotted by
 * name/email, so the entry stays readable after the operator or target
 * is renamed/removed. Writes are best-effort: an audit failure must
 * never roll back or block the underlying action.
 */
class AdminActionLogger
{
    public const PLAN_ASSIGNED       = 'plan.assigned';
    public const COINS_GRANTED       = 'coins.granted';
    public const COINS_DEDUCTED      = 'coins.deducted';
    public const ACCOUNT_CREATED     = 'account.created';
    public const ACCOUNT_SUSPENDED   = 'account.suspended';
    public const ACCOUNT_REACTIVATED = 'account.reactivated';
    public const PROTECTED_ADDED     = 'protected.added';
    public const PROTECTED_REMOVED   = 'protected.removed';
    public const DELETE_BLOCKED      = 'account.delete_blocked';
    public const SUSPEND_BLOCKED     = 'account.suspend_blocked';
    public const BADGE_ASSIGNED      = 'badge.assigned';
    public const BADGE_REMOVED       = 'badge.removed';
    public const CREDIT_REVIEW_APPROVED  = 'credit_review.approved';
    public const CREDIT_REVIEW_DISMISSED = 'credit_review.dismissed';
    public const USER_PASSWORD_SET       = 'user.password_set';

    /**
     * Record one action.
     *
     * @param  string                 $action  one of the self::* constants
     * @param  User|null              $target  affected user account
     * @param  array<string,mixed>    $details structured change payload
     */
    public function log(string $action, ?User $target = null, array $details = [], ?Admin $operator = null): ?AdminActionAudit
    {
        try {
            $operator ??= Auth::guard('admin')->user();

            return AdminActionAudit::create([
                'admin_id'       => $operator?->id,
                'admin_name'     => $operator?->name,
                'admin_email'    => $operator?->email,
                'target_user_id' => $target?->id,
                'target_name'    => $target?->name,
                'target_email'   => $target?->email,
                'action'         => $action,
                'details'        => $details ?: null,
                'ip'             => $this->ip(),
                'created_at'     => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('AdminActionLogger failed: ' . $e->getMessage(), [
                'action' => $action,
                'target' => $target?->id,
            ]);
            return null;
        }
    }

    protected function ip(): ?string
    {
        try {
            /** @var Request $request */
            $request = app('request');
            return $request?->ip();
        } catch (\Throwable) {
            return null;
        }
    }
}
