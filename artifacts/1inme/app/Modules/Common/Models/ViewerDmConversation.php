<?php

namespace App\Modules\Common\Models;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ViewerDmConversation extends Model
{
    protected $table = 'viewer_dm_conversations';

    protected $fillable = [
        'link_id', 'source', 'owner_user_id', 'creator_user_id', 'viewer_user_id',
        'viewer_msg_count', 'owner_msg_count', 'owner_replied',
        'owner_unread_count', 'viewer_unread_count',
        'viewer_last_read_at', 'owner_last_read_at',
        'paid_to_message', 'paid_amount_cents', 'paid_currency', 'paid_at',
        'status', 'blocked_at', 'last_message_at',
        'last_message_preview', 'last_sender',
        'auto_reply_companion_id',
    ];

    protected $casts = [
        'owner_replied'           => 'bool',
        'paid_to_message'         => 'bool',
        'paid_amount_cents'       => 'int',
        'paid_at'                 => 'datetime',
        'blocked_at'              => 'datetime',
        'last_message_at'         => 'datetime',
        'viewer_last_read_at'     => 'datetime',
        'owner_last_read_at'      => 'datetime',
        'auto_reply_companion_id' => 'int',
    ];

    public const SOURCE_BIOLINK = 'biolink';
    public const SOURCE_PROFILE = 'profile';

    /** Anti-spam: the very first burst from a brand-new viewer is capped. */
    public const VIEWER_INITIAL_LIMIT = 2;

    public function link(): BelongsTo
    {
        return $this->belongsTo(Link::class, 'link_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_user_id');
    }

    public function viewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'viewer_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ViewerDmMessage::class, 'conversation_id')
                    ->orderBy('created_at');
    }

    public function isBlocked(): bool
    {
        return $this->status === 'blocked';
    }

    public function isProfileScoped(): bool
    {
        return $this->source === self::SOURCE_PROFILE || $this->link_id === null;
    }

    /** True while the viewer is still in the capped "initial outreach" phase.
     *  Pay-to-message threads bypass the cap once payment is recorded — the
     *  fan paid for the right to keep messaging until the creator replies. */
    public function viewerIsThrottled(): bool
    {
        if ($this->paid_to_message) return false;

        return ! $this->owner_replied
            && $this->viewer_msg_count >= self::VIEWER_INITIAL_LIMIT;
    }
}
