<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single bookable service (name, description, estimated price, duration,
 * optional photo) belonging to a Service Booking page. `is_unavailable`
 * mirrors the restaurant item's "sold out" flag — still shown, but not
 * bookable.
 */
class ServiceBookingService extends Model
{
    protected $fillable = [
        'service_booking_id', 'category_id', 'name', 'description', 'price',
        'currency', 'duration_minutes', 'photo_url', 'sort_order',
        'is_unavailable', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price'            => 'decimal:2',
            'duration_minutes' => 'integer',
            'sort_order'       => 'integer',
            'is_unavailable'   => 'boolean',
            'is_active'        => 'boolean',
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
}
