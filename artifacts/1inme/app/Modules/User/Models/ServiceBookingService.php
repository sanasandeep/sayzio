<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single bookable service (name, description, estimated price, duration,
 * optional photo) belonging to a Service Booking page. `is_unavailable`
 * mirrors the restaurant item's "sold out" flag — still shown, but not
 * bookable.
 *
 * Payment modes (added in task #5284):
 *   none    — no upfront payment (default, existing behaviour)
 *   deposit — visitor pays a partial amount before the appointment
 *   full    — visitor pays the full price before the appointment
 *
 * Deposit config:
 *   deposit_type  — 'fixed' (flat currency amount) or 'percent' (% of price)
 *   deposit_value — the flat amount or the percentage value
 */
class ServiceBookingService extends Model
{
    public const PAYMENT_MODE_NONE    = 'none';
    public const PAYMENT_MODE_DEPOSIT = 'deposit';
    public const PAYMENT_MODE_FULL    = 'full';

    public const DEPOSIT_TYPE_FIXED   = 'fixed';
    public const DEPOSIT_TYPE_PERCENT = 'percent';

    protected $fillable = [
        'service_booking_id', 'category_id', 'name', 'description', 'price',
        'currency', 'duration_minutes', 'photo_url', 'sort_order',
        'is_unavailable', 'is_active',
        'payment_mode', 'deposit_type', 'deposit_value',
    ];

    protected function casts(): array
    {
        return [
            'price'            => 'decimal:2',
            'duration_minutes' => 'integer',
            'sort_order'       => 'integer',
            'is_unavailable'   => 'boolean',
            'is_active'        => 'boolean',
            'deposit_value'    => 'decimal:2',
        ];
    }

    public function serviceBooking()
    {
        return $this->belongsTo(ServiceBooking::class);
    }

    public function category()
    {
        return $this->belongsTo(ServiceBookingCategory::class, 'category_id');
    }

    /** True when this service requires payment at the time of booking. */
    public function requiresPayment(): bool
    {
        return in_array($this->payment_mode ?? self::PAYMENT_MODE_NONE, [
            self::PAYMENT_MODE_DEPOSIT,
            self::PAYMENT_MODE_FULL,
        ], true);
    }

    /**
     * Compute the required upfront payment in cents for this service line
     * (unit_price × qty) respecting deposit mode.
     */
    public function requiredPaymentCents(float $lineTotal): int
    {
        $mode = $this->payment_mode ?? self::PAYMENT_MODE_NONE;

        if ($mode === self::PAYMENT_MODE_FULL) {
            return (int) round($lineTotal * 100);
        }

        if ($mode === self::PAYMENT_MODE_DEPOSIT) {
            $type  = $this->deposit_type ?? self::DEPOSIT_TYPE_FIXED;
            $value = (float) ($this->deposit_value ?? 0);
            if ($type === self::DEPOSIT_TYPE_PERCENT) {
                return (int) round($lineTotal * $value / 100 * 100);
            }
            return (int) round($value * 100);
        }

        return 0;
    }
}
