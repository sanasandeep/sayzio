<?php

namespace App\Modules\Common\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsletterIssueUnsubscribe extends Model
{
    protected $fillable = ['subscriber_id', 'issue_id', 'unsubscribed_at'];

    protected function casts(): array
    {
        return [
            'unsubscribed_at' => 'datetime',
        ];
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(NewsletterSubscriber::class, 'subscriber_id');
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(NewsletterIssue::class, 'issue_id');
    }
}
