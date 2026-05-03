<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per (link, calendar month) describing the page-traffic
 * carbon footprint we estimated and how much (if anything) was
 * offset. Inputs to the model are persisted in `model_breakdown`
 * so the methodology popover can render exactly what was used.
 */
class BiolinkCarbonSnapshot extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id', 'link_id', 'period_start', 'period_end',
        'page_views', 'avg_bytes_per_view', 'device_mix', 'country_mix',
        'grid_intensity_g_per_kwh', 'grams_co2', 'grams_offset',
        'offset_status', 'offset_purchase_id', 'model_breakdown',
        'model_version',
    ];

    protected function casts(): array
    {
        return [
            'period_start'             => 'date',
            'period_end'               => 'date',
            'page_views'               => 'integer',
            'avg_bytes_per_view'       => 'integer',
            'device_mix'               => 'array',
            'country_mix'              => 'array',
            'grid_intensity_g_per_kwh' => 'float',
            'grams_co2'                => 'float',
            'grams_offset'             => 'float',
            'model_breakdown'          => 'array',
        ];
    }

    public function link()     { return $this->belongsTo(Link::class); }
    public function purchase() { return $this->belongsTo(CarbonOffsetPurchase::class, 'offset_purchase_id'); }
}
