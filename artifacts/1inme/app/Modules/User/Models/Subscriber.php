<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class Subscriber extends Model
{
    protected $fillable = [
        'user_id', 'link_id', 'block_id', 'type', 'email', 'phone',
        'name', 'channel_url', 'status', 'source', 'metadata',
        'subscribed_at', 'unsubscribed_at',
        'is_read', 'is_starred', 'is_spam', 'spam_reason', 'read_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'subscribed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
            'is_read' => 'boolean',
            'is_starred' => 'boolean',
            'is_spam' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(\App\Modules\User\Models\User::class);
    }

    public function link()
    {
        return $this->belongsTo(Link::class);
    }

    public function block()
    {
        return $this->belongsTo(BiolinkBlock::class, 'block_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
