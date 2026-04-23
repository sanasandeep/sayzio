<?php

namespace App\Modules\Common\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlogComment extends Model
{
    protected $fillable = [
        'post_id', 'parent_id',
        'author_type', 'author_id', 'author_name', 'author_email', 'author_avatar',
        'body', 'status',
        'ip_address', 'user_agent',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'post_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function isStaffReply(): bool
    {
        return $this->author_type === 'admin';
    }

    public function authorInitial(): string
    {
        return strtoupper(substr((string) ($this->author_name ?: '?'), 0, 1));
    }
}
