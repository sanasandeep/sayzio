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
        'link_id', 'owner_user_id', 'viewer_user_id',
        'viewer_msg_count', 'owner_msg_count', 'owner_replied',
        'owner_unread_count', 'viewer_unread_count',
        'status', 'blocked_at', 'last_message_at',
        'last_message_preview', 'last_sender',
    ];

    protected $casts = [
        'owner_replied'    => 'bool',
        'blocked_at'       => 'datetime',
        'last_message_at'  => 'datetime',
    ];

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

    /** True while the viewer is still in the capped "initial outreach" phase. */
    public function viewerIsThrottled(): bool
    {
        return ! $this->owner_replied
            && $this->viewer_msg_count >= self::VIEWER_INITIAL_LIMIT;
    }
}
