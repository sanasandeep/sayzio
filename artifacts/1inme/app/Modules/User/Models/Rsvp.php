<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Rsvp extends Model
{
    protected $table = 'rsvps';

    protected $fillable = [
        'link_id', 'name', 'email', 'phone', 'response', 'plus_ones',
        'message', 'source', 'source_block_id', 'ip_address', 'user_agent',
        'status', 'occurrences', 'answers', 'company', 'role', 'manage_token',
    ];

    protected function casts(): array
    {
        return [
            'plus_ones'   => 'integer',
            'occurrences' => 'array',
            'answers'     => 'array',
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
}
