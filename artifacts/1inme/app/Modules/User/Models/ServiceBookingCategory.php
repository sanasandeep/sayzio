<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceBookingCategory extends Model
{
    protected $fillable = [
        'service_booking_id', 'name', 'description', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active'  => 'boolean',
        ];
    }

    public function serviceBooking()
    {
        return $this->belongsTo(ServiceBooking::class);
    }

    public function services()
    {
        return $this->hasMany(ServiceBookingService::class, 'category_id')->orderBy('sort_order')->orderBy('id');
    }
}
