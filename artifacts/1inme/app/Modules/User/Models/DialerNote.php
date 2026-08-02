<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class DialerNote extends Model
{
    /** Auto-task source types (Task #5508). */
    public const SOURCE_EVENT = 'event';
    public const SOURCE_CALLBACK = 'callback';

    protected $fillable = [
        'user_id', 'title', 'body', 'number_e164', 'remind_at', 'done', 'color',
        'kind', 'checklist', 'source_type', 'source_id', 'reminder_sent_at',
        'attached_url', 'attached_title', 'attached_host',
        'calendar_event_id',
    ];

    protected $casts = [
        'remind_at' => 'datetime',
        'done' => 'boolean',
        'checklist' => 'array',
        'reminder_sent_at' => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }

    public function shares() { return $this->hasMany(DialerNoteShare::class); }

    public function calendarEvent() { return $this->belongsTo(CalendarEvent::class, 'calendar_event_id'); }

    /**
     * Task #6477 — mirror the note's reminder time onto the owner's personal
     * "Tasks & Reminders" calendar (and drop the event on delete).
     */
    protected static function booted(): void
    {
        static::saved(function (self $note) {
            \App\Modules\User\Support\PersonalCalendarSync::syncDialerNote($note);
        });

        static::deleting(function (self $note) {
            \App\Modules\User\Support\PersonalCalendarSync::deleteDialerNoteEvent($note);
        });
    }

    /** True when this row was auto-created from platform activity. */
    public function isAutoTask(): bool
    {
        return $this->source_type !== null;
    }

    /**
     * Extract the lower-cased host from an attached URL (null when absent
     * or unparsable). Strips a leading "www." so domain filters match both
     * forms.
     */
    public static function hostFromUrl(?string $url): ?string
    {
        if ($url === null || trim($url) === '') return null;
        $host = parse_url(trim($url), PHP_URL_HOST);
        if (!is_string($host) || $host === '') return null;
        $host = mb_strtolower($host);
        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    /**
     * Normalize an incoming checklist payload into a stable array of
     * {text, done} items, dropping empties and hard-capping length.
     *
     * @return array<int, array{text: string, done: bool}>
     */
    public static function normalizeChecklist($raw): array
    {
        if (!is_array($raw)) return [];
        $out = [];
        foreach ($raw as $item) {
            if (!is_array($item)) continue;
            $text = trim((string) ($item['text'] ?? ''));
            if ($text === '') continue;
            $out[] = [
                'text' => mb_substr($text, 0, 500),
                'done' => (bool) ($item['done'] ?? false),
            ];
            if (count($out) >= 100) break;
        }
        return $out;
    }
}
