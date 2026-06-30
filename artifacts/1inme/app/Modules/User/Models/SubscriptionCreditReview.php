<?php

namespace App\Modules\User\Models;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Plan;
use Illuminate\Database\Eloquent\Model;

/**
 * A pending/admin-actioned record of the leftover value a user forfeited
 * when they upgraded mid-cycle at full price (no proration). Leftover
 * value is NOT auto-credited — an admin reviews each row and may grant
 * credit by extending the new plan's expiry (and add-on duration, which
 * shares the subscription period) by a chosen number of days.
 */
class SubscriptionCreditReview extends Model
{
    protected $fillable = [
        'user_id', 'subscription_id', 'old_subscription_id',
        'old_plan_id', 'new_plan_id',
        'leftover_days', 'leftover_addon_days', 'addons_snapshot',
        'currency', 'status', 'granted_days',
        'actioned_by', 'actioned_at', 'note',
    ];

    protected function casts(): array
    {
        return [
            'addons_snapshot' => 'array',
            'actioned_at'     => 'datetime',
        ];
    }

    public function user()         { return $this->belongsTo(User::class); }
    public function subscription() { return $this->belongsTo(Subscription::class); }
    public function oldPlan()      { return $this->belongsTo(Plan::class, 'old_plan_id'); }
    public function newPlan()      { return $this->belongsTo(Plan::class, 'new_plan_id'); }
    public function actionedBy()   { return $this->belongsTo(Admin::class, 'actioned_by'); }

    public function scopePending($q) { return $q->where('status', 'pending'); }
}
