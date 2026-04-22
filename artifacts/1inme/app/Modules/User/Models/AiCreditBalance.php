<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class AiCreditBalance extends Model
{
    protected $table = 'ai_credit_balances';

    protected $fillable = ['user_id', 'balance', 'lifetime_purchased', 'lifetime_spent'];

    protected function casts(): array
    {
        return [
            'balance'            => 'integer',
            'lifetime_purchased' => 'integer',
            'lifetime_spent'     => 'integer',
        ];
    }

    public function user() { return $this->belongsTo(User::class); }

    public function transactions()
    {
        return $this->hasMany(AiCreditTransaction::class, 'balance_id')->orderByDesc('id');
    }
}
