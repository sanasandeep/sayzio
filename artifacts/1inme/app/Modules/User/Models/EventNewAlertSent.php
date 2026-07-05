<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Idempotency guard for location-based "new event near you" alerts
 * (Task #3593). A row means the given user has already been alerted about
 * the given event — permanently, regardless of whether they're on the
 * instant or daily-digest delivery frequency.
 */
class EventNewAlertSent extends Model
{
    protected $table = 'event_new_alerts_sent';

    public $timestamps = false;

    protected $fillable = ['link_id', 'user_id', 'sent_at'];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
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
}
