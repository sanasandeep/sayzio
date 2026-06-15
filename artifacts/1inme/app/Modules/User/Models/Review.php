<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    public const STATUS_PENDING    = 'pending';
    public const STATUS_APPROVED   = 'approved';
    public const STATUS_HIDDEN     = 'hidden';
    // Held until the reviewer confirms via a one-time email link. Never shown
    // publicly (see scopePublic) because it isn't an approved review yet.
    public const STATUS_UNVERIFIED = 'unverified';

    public const METHOD_EMAIL      = 'email';
    public const METHOD_SUBSCRIBER = 'subscriber';
    public const METHOD_CONTACT    = 'contact';

    protected $fillable = [
        'user_id', 'link_id', 'author_name', 'author_email', 'author_avatar',
        'rating', 'body', 'status', 'reply', 'replied_at', 'is_pinned',
        'is_spam', 'spam_reason', 'ip_hash', 'fingerprint',
        'verified_at', 'verification_method', 'verification_token',
    ];

    protected function casts(): array
    {
        return [
            'rating'      => 'integer',
            'is_pinned'   => 'boolean',
            'is_spam'     => 'boolean',
            'replied_at'  => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    /** A review that passed customer verification (email / subscriber / contact). */
    public function isVerified(): bool
    {
        return $this->verified_at !== null;
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
