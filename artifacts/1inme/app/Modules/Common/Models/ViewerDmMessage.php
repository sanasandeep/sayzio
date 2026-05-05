<?php

namespace App\Modules\Common\Models;

use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ViewerDmMessage extends Model
{
    protected $table = 'viewer_dm_messages';

    protected $fillable = [
        'conversation_id', 'sender_type', 'sender_user_id', 'body',
        'kind', 'tip_id', 'has_attachments',
        'read_at', 'is_ai',
    ];

    protected $casts = [
        'read_at'         => 'datetime',
        'is_ai'           => 'bool',
        'has_attachments' => 'bool',
        'tip_id'          => 'int',
    ];

    public const KIND_TEXT       = 'text';
    public const KIND_ATTACHMENT = 'attachment';
    public const KIND_TIP        = 'tip';
    public const KIND_SYSTEM     = 'system';

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ViewerDmConversation::class, 'conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function attachments()
    {
        return $this->hasMany(ViewerDmAttachment::class, 'message_id');
    }

    public function tip(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\User\Models\CreatorTip::class, 'tip_id');
    }
}
