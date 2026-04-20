<?php

namespace App\Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Polymorphic price row, attached to a Plan or an Addon.
 * Amounts are stored in MINOR units (cents/paise).
 */
class Price extends Model
{
    protected $fillable = [
        'priceable_type', 'priceable_id',
        'currency', 'billing_cycle',
        'amount_minor_units', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor_units' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function priceable(): MorphTo
    {
        return $this->morphTo();
    }
}
