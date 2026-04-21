<?php

namespace App\Modules\User\Models;

use App\Modules\Admin\Models\Plan;
use Illuminate\Database\Eloquent\Model;

class RevenueCatGrant extends Model
{
    protected $fillable = [
        'user_id', 'plan_id', 'cycle', 'app_user_id',
        'entitlement', 'product_identifier', 'original_transaction_id',
        'store', 'purchased_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'purchased_at' => 'datetime',
            'expires_at'   => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}
