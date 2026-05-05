<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A mass-DM campaign. Audience is resolved at send time so newly
 * acquired followers/subscribers between draft and send are caught.
 *
 * audience_kind:
 *   - followers       — every follower of this creator
 *   - subscribers     — every active paid subscriber
 *   - tier            — subscribers on a specific tier (audience_value = tier id)
 *   - all_dm_threads  — every viewer who has ever DM'd this creator
 */
class DmBroadcast extends Model
{
    public const STATUS_DRAFT   = 'draft';
    public const STATUS_QUEUED  = 'queued';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT    = 'sent';
    public const STATUS_FAILED  = 'failed';

    public const AUDIENCES = [
        'followers'      => 'All followers',
        'subscribers'    => 'All paid subscribers',
        'tier'           => 'Subscribers on a specific tier',
        'all_dm_threads' => 'Anyone who has DM\'d me before',
    ];

    protected $fillable = [
        'user_id', 'audience_kind', 'audience_value', 'body',
        'attachment_url', 'attachment_thumb_url', 'attachment_kind',
        'attachment_lock_price_cents', 'attachment_lock_currency',
        'status', 'recipients_count', 'sent_count', 'failed_count',
        'scheduled_at', 'sent_at', 'error',
    ];

    protected $casts = [
        'attachment_lock_price_cents' => 'int',
        'recipients_count' => 'int',
        'sent_count'       => 'int',
        'failed_count'     => 'int',
        'scheduled_at'     => 'datetime',
        'sent_at'          => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
