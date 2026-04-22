<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class AiCreditTransaction extends Model
{
    public $timestamps = false;

    protected $table = 'ai_credit_transactions';

    protected $fillable = [
        'balance_id', 'user_id', 'type', 'delta_credits', 'balance_after',
        'idempotency_key', 'feature', 'related_id', 'model',
        'tokens_in', 'tokens_out', 'wallet_transaction_id',
        'admin_id', 'reason', 'meta', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'delta_credits' => 'integer',
            'balance_after' => 'integer',
            'tokens_in'     => 'integer',
            'tokens_out'    => 'integer',
            'meta'          => 'array',
            'created_at'    => 'datetime',
        ];
    }

    public const TYPES = ['purchase', 'spend', 'refund', 'grant', 'admin_adjustment'];

    /** Known AI features for filtering / reporting. */
    public const FEATURES = ['mind', 'persona', 'companion', 'coach', 'ask_coach'];

    public function balance() { return $this->belongsTo(AiCreditBalance::class, 'balance_id'); }
    public function user()    { return $this->belongsTo(User::class); }
    public function walletTransaction()
    {
        return $this->belongsTo(WalletTransaction::class, 'wallet_transaction_id');
    }
}
