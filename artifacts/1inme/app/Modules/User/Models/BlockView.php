<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class BlockView extends Model
{
    protected $fillable = [
        'link_id', 'block_id', 'block_type', 'session_id',
        'view_duration_ms', 'impression_count',
        'first_viewed_at', 'last_viewed_at',
    ];

    protected $casts = [
        'first_viewed_at' => 'datetime',
        'last_viewed_at' => 'datetime',
        'view_duration_ms' => 'integer',
        'impression_count' => 'integer',
    ];
}
