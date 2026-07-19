<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One-off tip from a fan to a creator. Optionally attached to a post
 * so the post-card can render "{name} tipped" footers later.
 */
class CreatorTip extends Model
{
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_REFUNDED  = 'refunded';
    public const STATUS_FAILED    = 'failed';

    protected $fillable = [
        'creator_user_id', 'fan_user_id', 'post_id',
        'amount_cents', 'currency', 'note', 'anonymous',
        'status', 'gateway', 'gateway_charge_id', 'refunded_at',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'anonymous'    => 'boolean',
            'refunded_at'  => 'datetime',
        ];
    }

    public function creator() { return $this->belongsTo(User::class, 'creator_user_id'); }
    public function fan()     { return $this->belongsTo(User::class, 'fan_user_id'); }
    public function post()    { return $this->belongsTo(CreatorPost::class, 'post_id'); }
}
