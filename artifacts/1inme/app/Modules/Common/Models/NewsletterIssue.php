<?php

namespace App\Modules\Common\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterIssue extends Model
{
    protected $fillable = [
        'subject',
        'body_html',
        'status',
        'recipients_count',
        'sent_count',
        'failed_count',
        'unsubscribed_count',
        'sender_id',
        'sender_email',
        'sent_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at'     => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
