<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    protected $fillable = ['user_id', 'balance', 'low_balance_threshold', 'low_balance_notified_at'];

    protected function casts(): array
    {
        return [
            'balance' => 'integer',
            'low_balance_threshold' => 'integer',
            'low_balance_notified_at' => 'datetime',
        ];
    }

    public function user() { return $this->belongsTo(User::class); }
    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class)->orderByDesc('id');
    }
}
