<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewProvider extends Model
{
    public const STATUS_CONNECTED    = 'connected';
    public const STATUS_PREVIEW      = 'preview';
    public const STATUS_ERROR        = 'error';
    public const STATUS_DISCONNECTED = 'disconnected';

    protected $fillable = [
        'user_id', 'provider', 'external_ref', 'status',
        'status_reason', 'settings', 'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'settings'       => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function externalReviews()
    {
        return $this->hasMany(ExternalReview::class, 'provider_id');
    }
}
