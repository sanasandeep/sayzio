<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A snapshot of one service included in a booking request — name, unit price,
 * duration and quantity captured at submission time so later edits to the
 * service catalog never rewrite a placed request.
 */
class ServiceBookingRequestItem extends Model
{
    protected $fillable = [
        'request_id', 'service_id', 'name', 'unit_price',
        'duration_minutes', 'quantity', 'line_total',
    ];

    protected function casts(): array
    {
        return [
            'unit_price'       => 'decimal:2',
            'duration_minutes' => 'integer',
            'quantity'         => 'integer',
            'line_total'       => 'decimal:2',
        ];
    }

    public function request()
    {
        return $this->belongsTo(ServiceBookingRequest::class, 'request_id');
    }

    public function service()
    {
        return $this->belongsTo(ServiceBookingService::class, 'service_id');
    }
}
