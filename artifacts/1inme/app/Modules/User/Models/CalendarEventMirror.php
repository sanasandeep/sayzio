<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class CalendarEventMirror extends Model
{
    protected $table = 'calendar_event_mirror';

    protected $fillable = [
        'calendar_account_id', 'link_id', 'calendar_event_id', 'external_calendar_id',
        'external_event_id', 'etag', 'ical_uid', 'source',
        'detached', 'external_updated_at', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'detached'           => 'boolean',
            'external_updated_at' => 'datetime',
            'last_seen_at'       => 'datetime',
        ];
    }

    public function account()       { return $this->belongsTo(CalendarAccount::class, 'calendar_account_id'); }
    public function link()          { return $this->belongsTo(Link::class); }
    public function calendarEvent() { return $this->belongsTo(CalendarEvent::class, 'calendar_event_id'); }
}
