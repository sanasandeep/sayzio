<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per (post, fan) once a fan unlocks a pay-per-view post.
 * A non-null refunded_at revokes access without losing history.
 */
class PostUnlock extends Model
{
    protected $fillable = [
        'post_id', 'fan_user_id', 'price_cents', 'currency',
        'gateway', 'gateway_charge_id', 'unlocked_at', 'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'unlocked_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function post() { return $this->belongsTo(CreatorPost::class, 'post_id'); }
    public function fan()  { return $this->belongsTo(User::class, 'fan_user_id'); }

    public function isActive(): bool { return empty($this->refunded_at); }
}
