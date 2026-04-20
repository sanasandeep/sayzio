<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'number', 'financial_year', 'seq', 'user_id', 'currency',
        'subtotal_minor', 'tax_total_minor', 'grand_total_minor',
        'billing_address_snapshot', 'merchant_snapshot',
        'line_items', 'tax_breakdown', 'reverse_charge_note',
        'place_of_supply', 'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'billing_address_snapshot' => 'array',
            'merchant_snapshot'        => 'array',
            'line_items'               => 'array',
            'tax_breakdown'            => 'array',
            'subtotal_minor'           => 'integer',
            'tax_total_minor'          => 'integer',
            'grand_total_minor'        => 'integer',
            'seq'                      => 'integer',
            'issued_at'                => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
