<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalReview extends Model
{
    protected $table = 'external_reviews';

    protected $fillable = [
        'user_id', 'provider_id', 'provider', 'source_id', 'dedup_key',
        'author_name', 'author_avatar', 'rating', 'body', 'source_url',
        'payload', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'rating'      => 'integer',
            'payload'     => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function provider()
    {
        return $this->belongsTo(ReviewProvider::class, 'provider_id');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
