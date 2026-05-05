<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Auto-DM rule: when a fan triggers $trigger for $user, fire $body.
 *
 * trigger:
 *   - new_follower     — fan starts following the creator
 *   - new_subscriber   — fan activates a paid subscription. If tier_id
 *                        is set the rule only fires for that tier;
 *                        otherwise it fires for any tier.
 */
class DmWelcomeRule extends Model
{
    public const TRIGGER_FOLLOWER   = 'new_follower';
    public const TRIGGER_SUBSCRIBER = 'new_subscriber';

    public const TRIGGERS = [
        self::TRIGGER_FOLLOWER   => 'New follower',
        self::TRIGGER_SUBSCRIBER => 'New paid subscriber',
    ];

    protected $fillable = [
        'user_id', 'trigger', 'tier_id', 'body',
        'attachment_url', 'attachment_thumb_url', 'attachment_kind',
        'attachment_lock_price_cents', 'attachment_lock_currency',
        'is_active', 'sent_count',
    ];

    protected $casts = [
        'tier_id'                     => 'int',
        'attachment_lock_price_cents' => 'int',
        'is_active'                   => 'bool',
        'sent_count'                  => 'int',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(SubscriptionTier::class, 'tier_id');
    }
}
