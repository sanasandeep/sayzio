<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentAttempt extends Model
{
    protected $fillable = [
        'invoice_id', 'gateway', 'gateway_ref', 'status',
        'raw_response', 'signature_verified_at',
    ];

    protected function casts(): array
    {
        return [
            'raw_response'          => 'array',
            'signature_verified_at' => 'datetime',
        ];
    }

    public function invoice() { return $this->belongsTo(Invoice::class); }
}
