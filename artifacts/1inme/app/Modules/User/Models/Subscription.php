<?php

namespace App\Modules\User\Models;

use App\Modules\Admin\Models\Plan;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'user_id', 'plan_id', 'status', 'billing_cycle',
        'current_period_start', 'current_period_end', 'cancel_at',
        'cancel_at_period_end', 'replaced_by_id', 'grace_until',
        'grace_ending_notified_at', 'renewal_retry_at', 'scheduled_downgrade_plan_id',
        'gateway', 'gateway_subscription_id', 'currency',
    ];

    protected function casts(): array
    {
        return [
            'current_period_start'     => 'datetime',
            'current_period_end'       => 'datetime',
            'cancel_at'                => 'datetime',
            'cancel_at_period_end'     => 'boolean',
            'grace_until'              => 'datetime',
            'grace_ending_notified_at' => 'datetime',
            'renewal_retry_at'         => 'datetime',
        ];
    }

    public function user()   { return $this->belongsTo(User::class); }
    public function plan()   { return $this->belongsTo(Plan::class); }
    public function addons() { return $this->hasMany(SubscriptionAddon::class); }
    public function invoices() { return $this->hasMany(Invoice::class); }
    public function scheduledDowngradePlan() { return $this->belongsTo(Plan::class, 'scheduled_downgrade_plan_id'); }
}
