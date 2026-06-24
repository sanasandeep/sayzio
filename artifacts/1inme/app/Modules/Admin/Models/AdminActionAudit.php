<?php

namespace App\Modules\Admin\Models;

use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per back-office user-management action (plan assign, coin
 * grant/deduct, account create, suspend/reactivate). Append-only:
 * rows are created by {@see \App\Modules\Admin\Services\AdminActionLogger}
 * and never updated. `created_at` is DB-defaulted (useCurrent) and the
 * table has no `updated_at`, so timestamps are disabled.
 */
class AdminActionAudit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'admin_id', 'admin_name', 'admin_email',
        'target_user_id', 'target_name', 'target_email',
        'action', 'details', 'ip', 'created_at',
    ];

    protected $casts = [
        'details'    => 'array',
        'created_at' => 'datetime',
    ];

    /** Operator who performed the action (admin guard). */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    /** Affected user account. */
    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /** Human label for the action verb, used in the viewer. */
    public function actionLabel(): string
    {
        return match ($this->action) {
            'plan.assigned'        => 'Plan assigned',
            'coins.granted'        => 'Coins granted',
            'coins.deducted'       => 'Coins deducted',
            'account.created'      => 'Account created',
            'account.suspended'    => 'Account suspended',
            'account.reactivated'  => 'Account reactivated',
            default                => ucfirst(str_replace(['.', '_'], ' ', $this->action)),
        };
    }
}
