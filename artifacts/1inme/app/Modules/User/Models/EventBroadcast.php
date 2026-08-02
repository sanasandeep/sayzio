<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single organizer → guest broadcast send, logged so the organizer can
 * see past messages (subject, audience, recipient count, date).
 */
class EventBroadcast extends Model
{
    protected $table = 'event_broadcasts';

    protected $fillable = [
        'link_id', 'user_id', 'audience', 'subject', 'message', 'recipients_count',
    ];

    protected function casts(): array
    {
        return [
            'recipients_count' => 'integer',
        ];
    }

    public const AUDIENCES = [
        'going'          => 'Going',
        'waitlist'       => 'Waitlist',
        'all_rsvps'      => 'All RSVPs',
        'ticket_holders' => 'Ticket holders',
    ];

    public function link()
    {
        return $this->belongsTo(Link::class);
    }
}
