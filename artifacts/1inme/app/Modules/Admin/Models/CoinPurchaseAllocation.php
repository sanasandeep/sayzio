<?php

namespace App\Modules\Admin\Models;

use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin-only snapshot of the internal revenue split for one completed coin
 * purchase: how much of the collected amount was budgeted for API costs vs
 * platform margin, at the package's allocation percentages at the moment the
 * purchase completed. One row per invoice (unique invoice_id makes writes
 * idempotent against webhook re-delivery).
 *
 * This data must NEVER surface on any user-facing page or API response.
 */
class CoinPurchaseAllocation extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'invoice_id', 'user_id', 'coin_package_id', 'coins', 'currency',
        'amount_minor', 'api_budget_pct', 'margin_pct',
        'api_budget_minor', 'margin_minor',
    ];

    protected function casts(): array
    {
        return [
            'coins'            => 'integer',
            'amount_minor'     => 'integer',
            'api_budget_pct'   => 'float',
            'margin_pct'       => 'float',
            'api_budget_minor' => 'integer',
            'margin_minor'     => 'integer',
            'created_at'       => 'datetime',
        ];
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function package()
    {
        return $this->belongsTo(CoinPackage::class, 'coin_package_id');
    }
}
