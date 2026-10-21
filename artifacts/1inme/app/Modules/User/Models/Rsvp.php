<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Rsvp extends Model
{
    protected $table = 'rsvps';

    protected $fillable = [
        'link_id', 'user_id', 'name', 'email', 'phone', 'response', 'plus_ones',
        'message', 'source', 'source_block_id', 'ip_address', 'user_agent',
        'status', 'occurrences', 'answers', 'company', 'role', 'manage_token',
        'waitlist_invited_at',
    ];

    protected function casts(): array
    {
        return [
            'plus_ones'           => 'integer',
            'occurrences'         => 'array',
            'answers'             => 'array',
            'waitlist_invited_at' => 'datetime',
        ];
    }

    public const RESPONSES = ['yes' => 'Going', 'maybe' => 'Maybe', 'no' => 'Not going'];
    public const STATUSES  = ['confirmed' => 'Confirmed', 'waitlist' => 'Waitlist', 'cancelled' => 'Cancelled'];

    protected static function booted(): void
    {
        static::creating(function (self $r) {
            if (empty($r->status)) $r->status = 'confirmed';
            if (empty($r->manage_token)) $r->manage_token = Str::random(40);
        });

        // Unified contact linking (Task #6501): tie the RSVP to the event
        // owner's Contact by guest email/phone, off the hot path.
        static::created(function (self $r): void {
            $ownerId = $r->link_id
                ? Link::withoutGlobalScope('workspace')->whereKey($r->link_id)->value('user_id')
                : null;
            \App\Jobs\LinkCaptureToContactJob::forRecord(
                $r, $ownerId ? (int) $ownerId : null, $r->email, $r->phone, $r->name, 'rsvp'
            );
        });
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function link()
    {
        return $this->belongsTo(Link::class);
    }

    /**
     * The tier-less check-in ticket issued for this RSVP (Task #3606),
     * when the response is confirmed+"yes". See RsvpTicketService::sync().
     */
    public function ticket()
    {
        return $this->hasOne(EventTicket::class, 'rsvp_id');
    }

    public function manageUrl(): string
    {
        return url('/' . $this->link->alias . '/rsvp/manage/' . $this->manage_token);
    }

    /**
     * Total seats this RSVP is consuming when the guest is "going".
     * Includes the guest themselves.
     */
    public function seatsConsumed(): int
    {
        if ($this->response !== 'yes' || $this->status === 'cancelled') return 0;
        return 1 + max(0, (int) $this->plus_ones);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Every RSVP that belongs to this account.
     *
     * Two ways in, because a user_id only exists from the moment that
     * column did. An RSVP made while signed in carries the id outright.
     * An older one, or one made signed-out, is matched on the account's
     * own email -- the same reach EventTicketApiController::myEvents
     * already uses for tickets, so a person sees the same set of events
     * in the app and on the web.
     *
     * The email arm is deliberately grouped. Left ungrouped next to any
     * other condition, an orWhere widens the whole query rather than this
     * one clause, which is how a filter quietly turns into "everyone".
     */
    public function scopeForAccount($query, $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->where('user_id', $user->id);

            if (!empty($user->email)) {
                $q->orWhere('email', $user->email);
            }
        });
    }
}
