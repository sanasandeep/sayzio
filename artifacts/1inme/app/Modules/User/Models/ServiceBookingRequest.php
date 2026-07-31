<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A visitor's booking request against a Service Booking page.
 * Mirrors RestaurantOrder: a tokenized record the owner advances through a
 * status workflow.
 *
 * Slot holding:
 *   BLOCKING_STATUSES holds the slot so visitors only ever see free times.
 *   STATUS_AWAITING_PAYMENT is treated as blocking while checkout_expires_at
 *   is in the future (handled by SlotAvailabilityService::busyRanges).
 *
 * Payment state (added in task #5284):
 *   payment_mode    — none | deposit | full  (snapshot from services at booking time)
 *   payment_status  — none | pending | paid | refunded
 *   payment_amount_cents — gross amount charged in cents
 *   payment_currency     — ISO 4217 3-char
 *   payment_gateway      — adapter key (stripe, paypal, …)
 *   payment_charge_id    — gateway charge / transaction ID
 *   checkout_expires_at  — slot hold expiry for AWAITING_PAYMENT bookings
 *
 * Reminder dedup is stored in meta['reminders_sent'] as an array of
 * {lead_minutes: N, sent_at: ISO} objects.
 */
class ServiceBookingRequest extends Model
{
    public const STATUS_AWAITING_PAYMENT = 'awaiting_payment';
    public const STATUS_PENDING          = 'pending';
    public const STATUS_CONFIRMED        = 'confirmed';
    public const STATUS_COMPLETED        = 'completed';
    public const STATUS_CANCELLED        = 'cancelled';
    public const STATUS_DECLINED         = 'declined';

    public const STATUSES = [
        self::STATUS_AWAITING_PAYMENT,
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
     * Statuses that PERMANENTLY hold a slot (excluding awaiting_payment
     * which holds only while checkout_expires_at is in the future —
     * SlotAvailabilityService handles that case directly).
     */
    public const BLOCKING_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_COMPLETED,
    ];

    public const PAYMENT_STATUS_NONE     = 'none';
    public const PAYMENT_STATUS_PENDING  = 'pending';
    public const PAYMENT_STATUS_PAID     = 'paid';
    public const PAYMENT_STATUS_REFUNDED = 'refunded';

    public const STATUS_LABELS = [
        self::STATUS_AWAITING_PAYMENT => 'Awaiting Payment',
        self::STATUS_PENDING          => 'Pending',
        self::STATUS_CONFIRMED        => 'Confirmed',
        self::STATUS_COMPLETED        => 'Completed',
        self::STATUS_CANCELLED        => 'Cancelled',
        self::STATUS_DECLINED         => 'Declined',
    ];

    /**
     * Allowed owner transitions.
     * awaiting_payment → the owner can cancel (payment expired) but the
     * normal forward path is driven by payment confirmation (auto-confirmed).
     */
    public const STATUS_TRANSITIONS = [
        self::STATUS_AWAITING_PAYMENT => [self::STATUS_CANCELLED],
        self::STATUS_PENDING          => [self::STATUS_CONFIRMED, self::STATUS_DECLINED, self::STATUS_CANCELLED],
        self::STATUS_CONFIRMED        => [self::STATUS_COMPLETED, self::STATUS_CANCELLED],
        self::STATUS_COMPLETED        => [],
        self::STATUS_CANCELLED        => [],
        self::STATUS_DECLINED         => [],
    ];

    public function canTransitionTo(string $next): bool
    {
        if ($next === $this->status) {
            return true;
        }

        return in_array($next, self::STATUS_TRANSITIONS[$this->status] ?? [], true);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === self::PAYMENT_STATUS_PAID;
    }

    public function isRefundable(): bool
    {
        return $this->isPaid()
            && $this->payment_amount_cents > 0
            && $this->payment_status !== self::PAYMENT_STATUS_REFUNDED;
    }

    protected $fillable = [
        'service_booking_id', 'link_id', 'staff_id', 'public_token', 'status',
        'buffer_before_minutes', 'buffer_after_minutes',
        'customer_name', 'customer_email', 'customer_phone', 'customer_note',
        'slot_start', 'slot_end', 'duration_minutes',
        'subtotal', 'tax_rate', 'tax_inclusive', 'tax_amount', 'total',
        'currency', 'meta',
        'payment_mode', 'payment_status', 'payment_amount_cents',
        'payment_currency', 'payment_gateway', 'payment_charge_id',
        'checkout_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'slot_start'              => 'datetime',
            'slot_end'                => 'datetime',
            'checkout_expires_at'     => 'datetime',
            'duration_minutes'        => 'integer',
            'subtotal'                => 'decimal:2',
            'tax_rate'                => 'decimal:3',
            'tax_inclusive'           => 'boolean',
            'tax_amount'              => 'decimal:2',
            'total'                   => 'decimal:2',
            'payment_amount_cents'    => 'integer',
            'buffer_before_minutes'   => 'integer',
            'buffer_after_minutes'    => 'integer',
            'meta'                    => 'array',
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

    public function staff()
    {
        return $this->belongsTo(ServiceBookingStaff::class, 'staff_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }

    /**
     * Record that a reminder at a given lead time was sent, stored in
     * meta['reminders_sent'] for idempotent dedup by the reminder command.
     */
    public function markReminderSent(int $leadMinutes): void
    {
        $meta = $this->meta ?? [];
        $sent = $meta['reminders_sent'] ?? [];
        $sent[] = ['lead_minutes' => $leadMinutes, 'sent_at' => now()->toIso8601String()];
        $this->meta = array_merge($meta, ['reminders_sent' => $sent]);
        $this->save();
    }

    /** True when a reminder at this lead time was already dispatched. */
    public function wasReminderSent(int $leadMinutes): bool
    {
        $sent = $this->meta['reminders_sent'] ?? [];
        foreach ($sent as $entry) {
            if ((int) ($entry['lead_minutes'] ?? 0) === $leadMinutes) {
                return true;
            }
        }
        return false;
    }

    /**
     * Record that the assigned staff member's reminder at a given lead time
     * was sent, stored in meta['staff_reminders_sent'] (Task #6338).
     */
    public function markStaffReminderSent(int $leadMinutes): void
    {
        $meta = $this->meta ?? [];
        $sent = $meta['staff_reminders_sent'] ?? [];
        $sent[] = ['lead_minutes' => $leadMinutes, 'sent_at' => now()->toIso8601String()];
        $this->meta = array_merge($meta, ['staff_reminders_sent' => $sent]);
        $this->save();
    }

    /** True when the staff reminder at this lead time was already dispatched. */
    public function wasStaffReminderSent(int $leadMinutes): bool
    {
        $sent = $this->meta['staff_reminders_sent'] ?? [];
        foreach ($sent as $entry) {
            if ((int) ($entry['lead_minutes'] ?? 0) === $leadMinutes) {
                return true;
            }
        }
        return false;
    }
}
