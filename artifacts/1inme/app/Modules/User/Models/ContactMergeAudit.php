<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per merged-away contact. Carries everything needed to undo the
 * merge: a snapshot of the deleted source contact (attributes + phones +
 * emails) and the ids of every row repointed to / created on the primary.
 */
class ContactMergeAudit extends Model
{
    /** Days during which a merge can still be undone. */
    public const UNDO_WINDOW_DAYS = 30;

    protected $fillable = [
        'user_id', 'primary_contact_id', 'source_contact_id',
        'source_snapshot', 'moved', 'restored_contact_id', 'undone_at',
    ];

    protected function casts(): array
    {
        return [
            'source_snapshot' => 'array',
            'moved'           => 'array',
            'undone_at'       => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function primaryContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'primary_contact_id');
    }

    /** Merges still eligible for undo (not undone, inside the window). */
    public function scopeUndoable(Builder $q): Builder
    {
        return $q->whereNull('undone_at')
                 ->where('created_at', '>=', now()->subDays(self::UNDO_WINDOW_DAYS));
    }

    public function isUndoable(): bool
    {
        return $this->undone_at === null
            && $this->created_at !== null
            && $this->created_at->gte(now()->subDays(self::UNDO_WINDOW_DAYS));
    }

    /** Display name of the merged-away contact, from the snapshot. */
    public function sourceName(): string
    {
        $s = (array) ($this->source_snapshot ?? []);
        $name = $s['display_name']
            ?? trim(($s['given_name'] ?? '') . ' ' . ($s['family_name'] ?? ''));
        if (is_string($name) && trim($name) !== '') {
            return trim($name);
        }
        $phone = $s['phones'][0]['value'] ?? null;
        $email = $s['emails'][0]['value'] ?? null;
        return $phone ?: ($email ?: '(no name)');
    }
}
