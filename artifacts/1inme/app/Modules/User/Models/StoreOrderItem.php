<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class StoreOrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'name', 'unit_price', 'quantity', 'line_total', 'note',
        'options', 'options_total',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'quantity'   => 'integer',
            // The choices as they were AT THE TIME, not a reference to
            // rows the owner will go on editing.
            'options'       => 'array',
            'options_total' => 'decimal:2',
        ];
    }

    public function order()
    {
        return $this->belongsTo(StoreOrder::class, 'order_id');
    }

    public function product()
    {
        return $this->belongsTo(StoreProduct::class, 'product_id');
    }
}
