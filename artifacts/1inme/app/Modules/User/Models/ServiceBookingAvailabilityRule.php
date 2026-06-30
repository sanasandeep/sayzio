<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One weekly opening-hours window for a Service Booking page. Multiple rows per
 * day are allowed (e.g. a morning and an afternoon window around a lunch break).
 * `day_of_week` is 0=Sunday … 6=Saturday to match Carbon::dayOfWeek.
 */
class ServiceBookingAvailabilityRule extends Model
{
    protected $fillable = [
        'service_booking_id', 'day_of_week', 'start_time', 'end_time', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_active'   => 'boolean',
        ];
    }

    public function serviceBooking()
    {
        return $this->belongsTo(ServiceBooking::class);
    }
}
