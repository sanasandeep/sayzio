<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A scheduled application window for a {@see BiolinkTheme}. The
 * resolver / cron flips this through `pending → active → completed`
 * (or `cancelled` if the creator aborts before the window ends).
 *
 * `prev_settings` stores the link's biolink settings as captured the
 * moment we activated this schedule, so we can revert cleanly when
 * the window ends (even if the creator edited the page mid-window).
 */
class BiolinkThemeSchedule extends Model
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'biolink_theme_schedules';

    protected $fillable = [
        'link_id', 'theme_id', 'prev_settings',
        'starts_at', 'ends_at', 'timezone', 'status',
        'applied_at', 'reverted_at',
    ];

    protected function casts(): array
    {
        return [
            'prev_settings' => 'array',
            'starts_at'     => 'datetime',
            'ends_at'       => 'datetime',
            'applied_at'    => 'datetime',
            'reverted_at'   => 'datetime',
        ];
    }

    public function link(): BelongsTo
    {
        return $this->belongsTo(Link::class, 'link_id');
    }

    public function theme(): BelongsTo
    {
        return $this->belongsTo(BiolinkTheme::class, 'theme_id');
    }

    public function isLive(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            || ($this->status === self::STATUS_PENDING
                && $this->starts_at && $this->starts_at->isPast()
                && $this->ends_at && $this->ends_at->isFuture());
    }
}
