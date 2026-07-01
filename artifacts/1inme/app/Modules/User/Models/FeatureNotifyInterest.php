<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's request to be notified when a "coming soon" feature goes live.
 * Deduped per (user_id, feature_key) via a unique index. Once the feature
 * becomes available and the launch email has been sent, `notified_at` is
 * stamped so the same interest is never emailed twice.
 */
class FeatureNotifyInterest extends Model
{
    protected $table = 'feature_notify_interests';

    protected $fillable = [
        'user_id',
        'feature_key',
        'notified_at',
    ];

    protected $casts = [
        'notified_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
