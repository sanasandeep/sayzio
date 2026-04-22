<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-user default Mind selection for an AI feature (e.g. "persona",
 * "coach"). Pre-fills the Mind picker on the form so users don't have
 * to re-select every time.
 */
class AiMindDefault extends Model
{
    protected $table = 'ai_mind_defaults';

    protected $fillable = [
        'user_id', 'feature', 'mind_ids', 'include_platform',
    ];

    protected $casts = [
        'mind_ids'         => 'array',
        'include_platform' => 'boolean',
    ];

    public function user() { return $this->belongsTo(User::class); }

    /**
     * Look up the default Mind selection for a user/feature pair.
     */
    public static function forUserFeature(int $userId, string $feature): ?self
    {
        return static::where('user_id', $userId)
            ->where('feature', $feature)
            ->first();
    }
}
