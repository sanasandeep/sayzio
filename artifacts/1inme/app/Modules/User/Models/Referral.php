<?php

namespace App\Modules\User\Models;


use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class Referral extends Model
{
    
    use BelongsToWorkspace;
protected $fillable = [
        'referrer_id', 'referred_user_id', 'code_used', 'status',
        'signed_up_at', 'converted_at', 'ip', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'signed_up_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    public function referrer()
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referredUser()
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function rewards()
    {
        return $this->hasMany(ReferralReward::class);
    }
}
