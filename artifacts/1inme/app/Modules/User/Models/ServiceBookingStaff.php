<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A staff / team member on a Service Booking page (Task #6325).
 *
 * Staff can be limited to a subset of services (empty pivot = performs all),
 * carry their own weekly hours (rows in service_booking_availability_rules
 * with staff_id set — falling back to the page-level schedule when they have
 * none), their own blocked dates, and an optional linked Google Calendar
 * account whose busy blocks remove slots for that member.
 */
class ServiceBookingStaff extends Model
{
    protected $table = 'service_booking_staff';

    protected $fillable = [
        'service_booking_id', 'name', 'title', 'bio', 'email', 'photo_url',
        'calendar_account_id', 'is_active', 'sort_order', 'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
            'settings'   => 'array',
        ];
    }

    public function serviceBooking()
    {
        return $this->belongsTo(ServiceBooking::class);
    }

    public function services()
    {
        return $this->belongsToMany(
            ServiceBookingService::class,
            'service_booking_staff_service',
            'staff_id',
            'service_id',
        )->withTimestamps();
    }

    public function availabilityRules()
    {
        return $this->hasMany(ServiceBookingAvailabilityRule::class, 'staff_id');
    }

    public function blockedDates()
    {
        return $this->hasMany(ServiceBookingBlockedDate::class, 'staff_id');
    }

    public function calendarAccount()
    {
        return $this->belongsTo(CalendarAccount::class, 'calendar_account_id');
    }

    /**
     * True when this member can perform every service in the given id list.
     * An empty pivot means the member performs ALL services.
     */
    public function canPerformAll(array $serviceIds): bool
    {
        $assigned = $this->relationLoaded('services')
            ? $this->services->pluck('id')->map(fn ($i) => (int) $i)->all()
            : $this->services()->pluck('service_booking_services.id')->map(fn ($i) => (int) $i)->all();

        if (empty($assigned)) {
            return true;
        }

        foreach ($serviceIds as $id) {
            if (!in_array((int) $id, $assigned, true)) {
                return false;
            }
        }

        return true;
    }
}
