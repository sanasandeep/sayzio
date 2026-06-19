<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per email-verification reminder actually sent
 * (`users:send-email-verification-reminders`). The users table only keeps
 * each user's most-recent reminder timestamp, so this log is what makes the
 * admin weekly trend an exact per-send count instead of a proxy.
 */
class EmailVerificationReminderSend extends Model
{
    protected $fillable = [
        'user_id',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
