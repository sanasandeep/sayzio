<?php

namespace App\Modules\Common\Models;

use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per (attachment, fan) successful purchase. Refunds set
 * refunded_at; the access predicate ignores refunded rows.
 */
class ViewerDmAttachmentUnlock extends Model
{
    protected $table = 'viewer_dm_attachment_unlocks';

    protected $fillable = [
        'attachment_id', 'fan_user_id', 'creator_user_id',
        'price_cents', 'currency',
        'gateway', 'gateway_charge_id',
        'unlocked_at', 'refunded_at',
    ];

    protected $casts = [
        'price_cents' => 'int',
        'unlocked_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(ViewerDmAttachment::class, 'attachment_id');
    }

    public function fan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fan_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_user_id');
    }
}
