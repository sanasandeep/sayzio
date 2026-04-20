<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InboxForwardDelivery extends Model
{
    protected $table = 'inbox_forward_deliveries';

    protected $fillable = [
        'destination_id', 'user_id', 'source_type', 'source_id',
        'status', 'attempts', 'last_error', 'last_response_code',
        'last_attempt_at', 'next_retry_at', 'delivered_at', 'payload_snapshot',
    ];

    protected $casts = [
        'last_attempt_at'    => 'datetime',
        'next_retry_at'      => 'datetime',
        'delivered_at'       => 'datetime',
        'payload_snapshot'   => 'array',
    ];

    public function destination(): BelongsTo
    {
        return $this->belongsTo(InboxForwardDestination::class, 'destination_id');
    }
}
