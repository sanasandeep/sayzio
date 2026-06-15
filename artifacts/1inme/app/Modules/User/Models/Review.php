<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_HIDDEN   = 'hidden';

    protected $fillable = [
        'user_id', 'link_id', 'author_name', 'author_email', 'author_avatar',
        'rating', 'body', 'status', 'reply', 'replied_at', 'is_pinned',
        'is_spam', 'spam_reason', 'ip_hash', 'fingerprint',
    ];

    protected function casts(): array
    {
        return [
            'rating'     => 'integer',
            'is_pinned'  => 'boolean',
            'is_spam'    => 'boolean',
            'replied_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function link()
    {
        return $this->belongsTo(Link::class);
    }

    public function media()
    {
        return $this->hasMany(ReviewMedia::class)->orderBy('sort_order');
    }

    public function answers()
    {
        return $this->hasMany(ReviewAnswer::class);
    }

    /** Public-visible native reviews (approved, not spam). */
    public function scopePublic($query)
    {
        return $query->where('status', self::STATUS_APPROVED)->where('is_spam', false);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
