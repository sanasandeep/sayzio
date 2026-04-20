<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    protected $fillable = [
        'invoice_id', 'user_id', 'amount_minor', 'currency', 'status',
        'gateway', 'gateway_ref', 'reason', 'created_by_admin_id',
        'user_initiated', 'downgrade_on_success', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor'         => 'integer',
            'user_initiated'       => 'boolean',
            'downgrade_on_success' => 'boolean',
            'processed_at'         => 'datetime',
        ];
    }

    public function invoice()    { return $this->belongsTo(Invoice::class); }
    public function user()       { return $this->belongsTo(User::class); }
    public function creditNote() { return $this->hasOne(CreditNote::class); }
}
