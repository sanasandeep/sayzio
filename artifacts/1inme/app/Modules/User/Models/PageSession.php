<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class PageSession extends Model
{
    protected $fillable = [
        'link_id', 'session_id', 'ip_address', 'country_code', 'city',
        'browser', 'os', 'device_type', 'referrer', 'language',
        'started_at', 'last_seen_at', 'duration_seconds', 'ended',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'duration_seconds' => 'integer',
        'ended' => 'boolean',
    ];
}
