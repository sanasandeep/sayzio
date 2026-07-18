<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Task #5008 — per-(user, event-link) opt-in for "People at this event".
 * A row means the user is currently discoverable. expires_at is stamped
 * with the event end_at so the opt-in auto-expires when the event ends.
 */
class EventDiscoverability extends Model
{
    protected $table = 'event_discoverability';

    protected $fillable = [
        'user_id', 'link_id', 'expires_at', 'lat', 'lng',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'lat'        => 'float',
            'lng'        => 'float',
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

    /** True when the opt-in is still within the event window. */
    public function isActive(): bool
    {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    /**
     * Scope to currently active (not yet expired) discoverability rows.
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }
}
