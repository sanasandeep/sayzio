<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user-saved kit combination for AI Brand Studio (Task #5577): a named,
 * reusable composition (kinds/counts/purposes) shown alongside the built-in
 * presets in the kit composer. Owned by `user_id` (no workspace scope), like
 * BrandStudioKit, so web and Sanctum API resolve rows identically.
 */
class BrandStudioPreset extends Model
{
    /** Hard cap on saved combos per user. */
    public const MAX_PER_USER = 20;

    protected $fillable = ['user_id', 'name', 'composition'];

    protected $casts = [
        'composition' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
