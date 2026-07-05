<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One-tap Interested / Not-interested signal on an `ics` event — kept
 * deliberately separate from {@see Rsvp} (Task #3593). Signed-in users are
 * keyed by user_id; anonymous visitors are keyed by a client-supplied
 * guest_fingerprint (cookie-backed, never a raw IP/email) so a repeat tap
 * flips the existing row instead of stacking duplicates.
 */
class EventInterest extends Model
{
    protected $table = 'event_interests';

    protected $fillable = [
        'link_id', 'user_id', 'guest_email', 'guest_fingerprint', 'status',
    ];

    public const INTERESTED = 'interested';
    public const NOT_INTERESTED = 'not_interested';

    public function link()
    {
        return $this->belongsTo(Link::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
