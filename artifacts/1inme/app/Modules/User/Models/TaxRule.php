<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A reusable tax rule (rate in basis points; 2000 = 20%). Attached to a
 * billing company and/or catalog item; consumed by InvoiceCalculator.
 */
class TaxRule extends Model
{
    protected $fillable = [
        'user_id', 'billing_company_id', 'name', 'rate_bps',
        'inclusive', 'is_compound', 'is_default', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate_bps'    => 'integer',
            'inclusive'   => 'boolean',
            'is_compound' => 'boolean',
            'is_default'  => 'boolean',
            'is_active'   => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function billingCompany()
    {
        return $this->belongsTo(BillingCompany::class);
    }

    public function ratePercent(): float
    {
        return round($this->rate_bps / 100, 2);
    }
}
