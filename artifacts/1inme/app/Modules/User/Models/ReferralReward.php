<?php

namespace App\Modules\User\Models;


use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class ReferralReward extends Model
{
    
    use BelongsToWorkspace;
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
