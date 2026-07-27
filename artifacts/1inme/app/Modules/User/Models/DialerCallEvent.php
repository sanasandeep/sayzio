<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One incoming-call event mirrored from the Zio Dialer phone app so the
 * Zio Browser desktop pane can show "your phone is ringing" (task #5780).
 *
 * Best-effort telemetry, not a call log: rows are retention-capped per
 * user on write and only ever read back by the owner via a short-poll
 * `since` cursor.
 *
 * @property int $id
 * @property int $user_id
 * @property string $status  ringing|answered|ended
 * @property string $number
 * @property string|null $caller_name
 * @property \Illuminate\Support\Carbon $occurred_at
 */
class DialerCallEvent extends Model
{
    protected $table = 'dialer_call_events';

    protected $fillable = [
        'user_id',
        'status',
        'number',
        'caller_name',
        'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
