<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EventTicket extends Model
{
    protected $table = 'event_tickets';

    public const STATUS_VALID      = 'valid';
    public const STATUS_CHECKED_IN = 'checked_in';
    public const STATUS_CANCELLED  = 'cancelled';
    public const STATUS_REFUNDED   = 'refunded';

    protected $fillable = [
        'tier_id', 'rsvp_id', 'link_id', 'buyer_user_id',
        'attendee_name', 'attendee_email', 'attendee_phone',
        'quantity', 'price_cents', 'currency', 'code', 'status',
        'purchase_reference', 'gateway', 'gateway_charge_id',
        'checked_in_at', 'checked_in_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity'      => 'integer',
            'price_cents'   => 'integer',
            'checked_in_at' => 'datetime',
        ];
    }

    /**
     * Unified contact linking (Task #6501): tie the ticket to the event
     * owner's Contact by attendee email/phone, off the hot path.
     */
    protected static function booted(): void
    {
        static::created(function (EventTicket $ticket): void {
            $ownerId = $ticket->link_id
                ? Link::withoutGlobalScope('workspace')->whereKey($ticket->link_id)->value('user_id')
                : null;
            \App\Jobs\LinkCaptureToContactJob::forRecord(
                $ticket,
                $ownerId ? (int) $ownerId : null,
                $ticket->attendee_email,
                $ticket->attendee_phone,
                $ticket->attendee_name,
                'event_ticket'
            );
        });
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function tier()
    {
        return $this->belongsTo(EventTicketTier::class, 'tier_id');
    }

    public function rsvp()
    {
        return $this->belongsTo(Rsvp::class, 'rsvp_id');
    }

    public function link()
    {
        return $this->belongsTo(Link::class);
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }

    public function checkedInBy()
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public static function generateCode(): string
    {
        do {
            $code = 'TKT-' . strtoupper(Str::random(10));
        } while (self::where('code', $code)->exists());
        return $code;
    }

    public function isValid(): bool
    {
        return $this->status === self::STATUS_VALID;
    }

    public function isCheckedIn(): bool
    {
        return $this->status === self::STATUS_CHECKED_IN;
    }
}
