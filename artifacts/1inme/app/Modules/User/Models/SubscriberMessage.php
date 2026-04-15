<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriberMessage extends Model
{
    protected $fillable = [
        'user_id', 'channel', 'subject', 'body', 'status',
        'recipients_count', 'sent_count', 'failed_count',
        'filters', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(\App\Modules\User\Models\User::class);
    }
}
