<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class CreditNote extends Model
{
    protected $fillable = [
        'number', 'financial_year', 'seq', 'refund_id', 'invoice_id',
        'user_id', 'currency', 'amount_minor', 'snapshot', 'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'snapshot'     => 'array',
            'amount_minor' => 'integer',
            'seq'          => 'integer',
            'issued_at'    => 'datetime',
        ];
    }

    public function refund()  { return $this->belongsTo(Refund::class); }
    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function user()    { return $this->belongsTo(User::class); }
}
