<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reusable snapshot of a biolink's themable fields (colors, hero,
 * header copy, background) bound to a specific link. Themes can be
 * scheduled via {@see BiolinkThemeSchedule} to apply automatically
 * over a date range and revert when the window ends.
 */
class BiolinkTheme extends Model
{
    protected $table = 'biolink_themes';

    protected $fillable = ['link_id', 'name', 'settings'];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function link(): BelongsTo
    {
        return $this->belongsTo(Link::class, 'link_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(BiolinkThemeSchedule::class, 'theme_id');
    }
}
