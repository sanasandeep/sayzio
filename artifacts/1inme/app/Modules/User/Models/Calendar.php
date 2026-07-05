<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A followable Calendar — a user-owned, publishable collection of
 * {@see CalendarEvent}s bridged 1:1 to a `calendar` {@see Link}.
 *
 * Distinct from the single-invite `ics` link type and from the external
 * `CalendarAccount` Google-sync infrastructure: this is its own surface that
 * other users follow, with a "My Calendar" aggregation across followed +
 * owned calendars. Page-level visibility gating still flows through
 * links.visibility; `is_public` only governs discovery / followability.
 */
class Calendar extends Model
{
    protected $fillable = [
        'link_id', 'user_id', 'title', 'slug', 'description',
        'timezone', 'accent_color', 'is_public', 'followers_count', 'settings',
        'workspace_id', 'delivery_project_id', 'privacy',
    ];

    protected $attributes = [
        'accent_color' => '#3d6bff',
        'timezone'     => \App\Support\PlatformTimezone::DEFAULT,
    ];

    protected function casts(): array
    {
        return [
            'is_public'       => 'boolean',
            'followers_count' => 'integer',
            'settings'        => 'array',
        ];
    }

    public function link()
    {
        return $this->belongsTo(Link::class);
    }

    /** Task #3584 — the Delivery Project this calendar belongs to, if any. */
    public function deliveryProject()
    {
        return $this->belongsTo(DeliveryProject::class, 'delivery_project_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function events()
    {
        return $this->hasMany(CalendarEvent::class)
            ->orderBy('start_at')
            ->orderBy('id');
    }

    public function follows()
    {
        return $this->hasMany(CalendarFollow::class);
    }

    /** Upcoming events (start in the future), soonest first. */
    public function upcomingEvents()
    {
        return $this->events()->where('start_at', '>=', now());
    }

    /** True when $user follows this calendar. */
    public function isFollowedBy(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $this->follows()->where('follower_id', $user->id)->exists();
    }

    /** Effective timezone, always a valid string for date math. */
    public function effectiveTimezone(): string
    {
        return \App\Support\PlatformTimezone::resolve($this->timezone);
    }
}
