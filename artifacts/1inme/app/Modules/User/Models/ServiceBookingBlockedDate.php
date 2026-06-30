<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single date on which a Service Booking page accepts no bookings
 * (holidays, time off). Removed from generated availability entirely.
 */
class ServiceBookingBlockedDate extends Model
{
    protected $fillable = [
        'service_booking_id', 'date', 'reason',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function serviceBooking()
    {
        return $this->belongsTo(ServiceBooking::class);
    }
}
