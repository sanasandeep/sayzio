<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One entry (announcement / changelog item) on a Creator Updates page.
 *
 * Entries are always scoped to a single Updates-type link (link_id) and to
 * the creating user (user_id). The public page shows published entries
 * newest-first by published_date; draft entries are owner-only.
 */
class UpdateEntry extends Model
{
    protected $fillable = [
        'link_id',
        'user_id',
        'title',
        'body',
        'image',
        'tag',
        'published_date',
        'status',
        'notified_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'published_date' => 'date',
            'notified_at'    => 'datetime',
            'sort_order'     => 'integer',
        ];
    }

    public function link()
    {
        return $this->belongsTo(Link::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Scope: only published entries (public-page safe). */
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    /** Scope: entries that have never been notified (need fan-out). */
    public function scopeUnnotified($query)
    {
        return $query->whereNull('notified_at');
    }

    /** True when this entry should trigger a follower notification. */
    public function needsFollowerNotification(): bool
    {
        return $this->status === 'published' && $this->notified_at === null;
    }

    /**
     * Stable anchor ID for this entry on the public page. Used by both the
     * public renderer and notification deep-links so they agree on the URL.
     */
    public function anchorId(): string
    {
        return 'entry-' . $this->id;
    }

    /**
     * Allowed tag values for the picker. Shown in the editor and used for
     * the badge colour map on the public page.
     *
     * @return string[]
     */
    public static function allowedTags(): array
    {
        return ['New', 'Improvement', 'Fix', 'Announcement', 'Breaking', 'Deprecation', 'Security'];
    }

    /**
     * Tailwind colour classes for each tag on the public page.
     *
     * @return array<string, string>
     */
    public static function tagClasses(): array
    {
        return [
            'New'          => 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
            'Improvement'  => 'bg-blue-500/20 text-blue-300 border-blue-500/30',
            'Fix'          => 'bg-amber-500/20 text-amber-300 border-amber-500/30',
            'Announcement' => 'bg-violet-500/20 text-violet-300 border-violet-500/30',
            'Breaking'     => 'bg-red-500/20 text-red-300 border-red-500/30',
            'Deprecation'  => 'bg-rose-500/20 text-rose-300 border-rose-500/30',
            'Security'     => 'bg-orange-500/20 text-orange-300 border-orange-500/30',
        ];
    }
}
