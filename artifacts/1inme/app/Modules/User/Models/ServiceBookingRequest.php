<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A visitor's booking request against a Service Booking page (Task #3085).
 * Mirrors RestaurantOrder: a tokenized, no-payment record the owner advances
 * through a status workflow. The slot [slot_start, slot_end) is held by any
 * non-cancelled / non-declined request so visitors only ever see genuinely
 * free times.
 */
class ServiceBookingRequest extends Model
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_DECLINED  = 'declined';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
        self::STATUS_DECLINED,
    ];

    /** Statuses still needing the owner's attention. */
    public const OPEN_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
    ];

    /**
     * Statuses that HOLD a slot (so it disappears from public availability and
     * blocks double-booking). Everything except cancelled / declined.
     */
    public const BLOCKING_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_COMPLETED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_PENDING   => 'Pending',
        self::STATUS_CONFIRMED => 'Confirmed',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_CANCELLED => 'Cancelled',
        self::STATUS_DECLINED  => 'Declined',
    ];

    /**
     * Allowed owner transitions. A pending request can be confirmed, declined
     * or cancelled; a confirmed one can be completed or cancelled; terminal
     * states don't move. Enforced server-side so API clients can't jump state.
     */
    public const STATUS_TRANSITIONS = [
        self::STATUS_PENDING   => [self::STATUS_CONFIRMED, self::STATUS_DECLINED, self::STATUS_CANCELLED],
        self::STATUS_CONFIRMED => [self::STATUS_COMPLETED, self::STATUS_CANCELLED],
        self::STATUS_COMPLETED => [],
        self::STATUS_CANCELLED => [],
        self::STATUS_DECLINED  => [],
    ];

    public function canTransitionTo(string $next): bool
    {
        if ($next === $this->status) {
            return true;
        }

        return in_array($next, self::STATUS_TRANSITIONS[$this->status] ?? [], true);
    }

    protected $fillable = [
        'service_booking_id', 'link_id', 'public_token', 'status',
        'customer_name', 'customer_email', 'customer_phone', 'customer_note',
        'slot_start', 'slot_end', 'duration_minutes',
        'subtotal', 'tax_rate', 'tax_inclusive', 'tax_amount', 'total',
        'currency', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'slot_start'       => 'datetime',
            'slot_end'         => 'datetime',
            'duration_minutes' => 'integer',
            'subtotal'         => 'decimal:2',
            'tax_rate'         => 'decimal:3',
            'tax_inclusive'    => 'boolean',
            'tax_amount'       => 'decimal:2',
            'total'            => 'decimal:2',
            'meta'             => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ServiceBookingRequest $request) {
            if (empty($request->public_token)) {
                $request->public_token = (string) Str::uuid();
            }
        });
    }

    public function serviceBooking()
    {
        return $this->belongsTo(ServiceBooking::class);
    }

    public function link()
    {
        return $this->belongsTo(Link::class);
    }

    public function items()
    {
        return $this->hasMany(ServiceBookingRequestItem::class, 'request_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }
}
