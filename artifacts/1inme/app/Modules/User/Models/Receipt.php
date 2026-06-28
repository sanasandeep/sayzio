<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/** A receipt generated on any successful payment (online or manual). */
class Receipt extends Model
{
    protected $fillable = [
        'number', 'financial_year', 'seq', 'invoice_id', 'user_id',
        'billing_company_id', 'currency', 'amount_minor', 'method',
        'gateway', 'gateway_ref', 'paid_at', 'snapshot', 'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'seq'          => 'integer',
            'snapshot'     => 'array',
            'paid_at'      => 'datetime',
            'issued_at'    => 'datetime',
        ];
    }

    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function user()    { return $this->belongsTo(User::class); }
}
