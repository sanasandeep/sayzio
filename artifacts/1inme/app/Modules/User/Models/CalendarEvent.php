<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single event inside a {@see Calendar}. Times are stored in UTC; the
 * `timezone` column records the wall-clock zone the owner authored the event
 * in so the public page, ICS export and reminders render correctly. `hashtags`
 * are lower-cased strings without the leading '#'; `params` is a free-form
 * extra key/value bag (e.g. price, capacity).
 */
class CalendarEvent extends Model
{
    protected $fillable = [
        'calendar_id', 'user_id', 'title', 'description',
        'start_at', 'end_at', 'timezone', 'all_day',
        'location', 'lat', 'lng', 'hashtags', 'payment_url', 'params',
    ];

    protected $attributes = [
        'timezone' => 'UTC',
        'all_day'  => false,
    ];

    protected function casts(): array
    {
        return [
            'start_at'  => 'datetime',
            'end_at'    => 'datetime',
            'all_day'   => 'boolean',
            'lat'       => 'float',
            'lng'       => 'float',
            'hashtags'  => 'array',
            'params'    => 'array',
        ];
    }

    public function calendar()
    {
        return $this->belongsTo(Calendar::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Effective timezone, always a valid string for date math. */
    public function effectiveTimezone(): string
    {
        $tz = trim((string) $this->timezone);

        return $tz !== '' ? $tz : 'UTC';
    }

    /** Normalize a free-text hashtag list into clean, deduped slugs. */
    public static function normalizeHashtags($raw): array
    {
        if (is_string($raw)) {
            $raw = preg_split('/[\s,]+/', $raw) ?: [];
        }
        if (!is_array($raw)) {
            return [];
        }

        $tags = [];
        foreach ($raw as $tag) {
            $tag = ltrim(trim((string) $tag), '#');
            $tag = mb_strtolower($tag);
            $tag = preg_replace('/[^\p{L}\p{N}_-]+/u', '', $tag);
            if ($tag !== '' && !in_array($tag, $tags, true)) {
                $tags[] = $tag;
            }
        }

        return array_slice($tags, 0, 30);
    }
}
