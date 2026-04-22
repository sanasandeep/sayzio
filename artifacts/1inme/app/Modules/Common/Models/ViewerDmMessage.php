<?php

namespace App\Modules\Common\Models;

use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ViewerDmMessage extends Model
{
    protected $table = 'viewer_dm_messages';

    protected $fillable = [
        'conversation_id', 'sender_type', 'sender_user_id', 'body', 'read_at', 'is_ai',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'is_ai'   => 'bool',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ViewerDmConversation::class, 'conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }
}
