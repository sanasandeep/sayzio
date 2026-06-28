<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/** A business expense recorded against a billing company. */
class Expense extends Model
{
    protected $fillable = [
        'user_id', 'workspace_id', 'billing_company_id', 'category_id',
        'vendor', 'description', 'spent_at', 'amount_minor', 'tax_minor',
        'currency', 'attachment_path', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'spent_at'     => 'date',
            'amount_minor' => 'integer',
            'tax_minor'    => 'integer',
        ];
    }

    public function user()           { return $this->belongsTo(User::class); }
    public function billingCompany() { return $this->belongsTo(BillingCompany::class); }
    public function category()       { return $this->belongsTo(CatalogCategory::class, 'category_id'); }

    public function totalMinor(): int
    {
        return (int) $this->amount_minor + (int) $this->tax_minor;
    }
}
