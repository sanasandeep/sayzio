<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's request to be notified when a "coming soon" feature goes live.
 * Deduped per (user_id, feature_key) via a unique index.
 */
class FeatureNotifyInterest extends Model
{
    protected $table = 'feature_notify_interests';

    protected $fillable = [
        'user_id',
        'feature_key',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
