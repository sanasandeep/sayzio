<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InboxThreadConversion extends Model
{
    protected $table = 'inbox_thread_conversions';

    protected $fillable = [
        'thread_id', 'kind', 'target_id', 'created_by_user_id', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(InboxThread::class, 'thread_id');
    }
}
