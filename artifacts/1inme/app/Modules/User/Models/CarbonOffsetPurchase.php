<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

/**
 * Receipt-style record of one offset transaction (real or sandbox).
 * Carries enough info for the dashboard certificate list and for
 * webhook reconciliation without a second round-trip to the provider.
 */
class CarbonOffsetPurchase extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id', 'link_id', 'provider', 'provider_ref',
        'grams_offset', 'currency', 'cost_minor', 'status',
        'certificate_url', 'project_name', 'raw',
        'invoice_id', 'purchased_at',
    ];

    protected function casts(): array
    {
        return [
            'grams_offset' => 'float',
            'cost_minor'   => 'integer',
            'raw'          => 'array',
            'purchased_at' => 'datetime',
        ];
    }

    public function link()    { return $this->belongsTo(Link::class); }
    public function invoice() { return $this->belongsTo(Invoice::class); }
}
