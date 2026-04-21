<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'wallet_id', 'user_id', 'type', 'delta_coins', 'balance_after',
        'idempotency_key', 'reason', 'invoice_id', 'coin_package_id',
        'addon_id', 'subscription_addon_id', 'admin_id', 'meta', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'delta_coins'   => 'integer',
            'balance_after' => 'integer',
            'meta'          => 'array',
            'created_at'    => 'datetime',
        ];
    }

    public const TYPES = ['purchase', 'spend', 'adjustment', 'refund'];

    public function wallet()  { return $this->belongsTo(Wallet::class); }
    public function user()    { return $this->belongsTo(User::class); }
    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function package() { return $this->belongsTo(\App\Modules\Admin\Models\CoinPackage::class, 'coin_package_id'); }
    public function addon()   { return $this->belongsTo(\App\Modules\Admin\Models\Addon::class, 'addon_id'); }
}
