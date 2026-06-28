<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/** A reusable product/service the user can drop onto invoices. */
class CatalogItem extends Model
{
    protected $fillable = [
        'user_id', 'billing_company_id', 'category_id', 'name', 'description',
        'unit_price_minor', 'currency', 'tax_rule_id', 'sku', 'unit_label', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'unit_price_minor' => 'integer',
            'tax_rule_id'      => 'integer',
            'is_active'        => 'boolean',
        ];
    }

    public function user()     { return $this->belongsTo(User::class); }
    public function category() { return $this->belongsTo(CatalogCategory::class, 'category_id'); }
    public function taxRule()  { return $this->belongsTo(TaxRule::class, 'tax_rule_id'); }
}
