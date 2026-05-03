<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InboxMessage extends Model
{
    protected $table = 'inbox_messages';

    protected $fillable = [
        'thread_id', 'direction', 'sender_name', 'sender_handle',
        'sender_user_id', 'body', 'sent_at', 'external_id', 'meta',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'meta'    => 'array',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(InboxThread::class, 'thread_id');
    }
}
