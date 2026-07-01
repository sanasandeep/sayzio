<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Task #3281 — a point-in-time scorecard snapshot for a Marketing
 * Strategy. Persisted each time the strategy is scored (on generation and
 * on manual re-score) so the trend of Reach / Engagement / Conversion /
 * Consistency is visible over time.
 */
class MarketingStrategyScore extends Model
{
    protected $table = 'marketing_strategy_scores';

    public const UPDATED_AT = null;

    protected $fillable = [
        'strategy_id', 'reach', 'engagement', 'conversion', 'consistency', 'overall', 'reasons', 'created_at',
    ];

    protected $casts = [
        'reach'       => 'integer',
        'engagement'  => 'integer',
        'conversion'  => 'integer',
        'consistency' => 'integer',
        'overall'     => 'integer',
        'reasons'     => 'array',
        'created_at'  => 'datetime',
    ];

    public function strategy()
    {
        return $this->belongsTo(MarketingStrategy::class, 'strategy_id');
    }
}
