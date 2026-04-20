<?php

namespace App\Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;

class TaxJurisdiction extends Model
{
    protected $fillable = [
        'country', 'region', 'kind', 'label', 'rate_percent',
        'b2b_reverse_charge', 'effective_from', 'effective_to', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate_percent'        => 'float',
            'b2b_reverse_charge'  => 'boolean',
            'is_active'           => 'boolean',
            'effective_from'      => 'date',
            'effective_to'        => 'date',
        ];
    }
}
