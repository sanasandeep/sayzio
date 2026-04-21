<?php

namespace App\Modules\Common\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterSubscriber extends Model
{
    protected $fillable = ['email', 'source', 'ip', 'user_agent', 'unsubscribed_at', 'unsubscribe_source'];

    protected function casts(): array
    {
        return [
            'unsubscribed_at' => 'datetime',
        ];
    }
}
