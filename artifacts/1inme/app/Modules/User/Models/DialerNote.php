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
    ];

    protected $casts = [
        'remind_at' => 'datetime',
        'done' => 'boolean',
        'checklist' => 'array',
        'reminder_sent_at' => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }

    public function shares() { return $this->hasMany(DialerNoteShare::class); }

    /** True when this row was auto-created from platform activity. */
    public function isAutoTask(): bool
    {
        return $this->source_type !== null;
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
