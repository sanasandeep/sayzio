<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only Ask-Coach turn. Assistant rows carry tool snapshots,
 * insight cards, deep-link actions and (later) thumbs up/down.
 */
class AskCoachMessage extends Model
{
    protected $table = 'ask_coach_messages';

    public $timestamps = false;

    protected $fillable = ['thread_id', 'role', 'content', 'meta', 'feedback', 'feedback_note', 'created_at'];

    protected function casts(): array
    {
        return [
            'meta'       => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function thread()
    {
        return $this->belongsTo(AskCoachThread::class, 'thread_id');
    }
}
