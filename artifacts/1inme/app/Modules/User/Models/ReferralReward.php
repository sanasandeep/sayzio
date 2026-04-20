<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralReward extends Model
{
    protected $fillable = [
        'user_id', 'referral_id', 'type', 'days_granted', 'plan_id_basis', 'granted_at',
    ];

    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function referral()
    {
        return $this->belongsTo(Referral::class);
    }
}
