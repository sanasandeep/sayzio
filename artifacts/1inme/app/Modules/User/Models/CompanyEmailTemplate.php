<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A creator's per-billing-company override for one client-facing accounting
 * email (e.g. the invoice or receipt sent to their clients). Layered on top of
 * the admin/global override ({@see \App\Services\Integrations\EmailTemplateSettings})
 * and the registry default — when no row exists the central pipeline falls back
 * to those, so this only ever customises a single company's own client emails.
 */
class CompanyEmailTemplate extends Model
{
    protected $fillable = [
        'billing_company_id', 'template_key', 'subject', 'body', 'format', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'billing_company_id' => 'integer',
            'updated_by'         => 'integer',
        ];
    }

    public function company()
    {
        return $this->belongsTo(BillingCompany::class, 'billing_company_id');
    }
}
