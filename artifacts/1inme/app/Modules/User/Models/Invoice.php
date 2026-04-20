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
        'subscription_id', 'gateway', 'status', 'paid_at',
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
            'paid_at'                  => 'datetime',
        ];
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function paymentAttempts()
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class);
    }

    public function creditNotes()
    {
        return $this->hasMany(CreditNote::class);
    }
}
