<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One bandit arm within one visitor segment of an adaptive biolink
 * experiment. `featured_block_id` null = the baseline arm (creator's
 * manual block order, nothing featured); otherwise it's the id of the
 * block promoted to the top of the page for this arm.
 */
class BiolinkAdaptiveArm extends Model
{
    protected $fillable = [
        'biolink_experiment_id',
        'segment',
        'featured_block_id',
        'impressions',
        'clicks',
        'conversions',
    ];

    protected $casts = [
        'featured_block_id' => 'integer',
        'impressions'       => 'int',
        'clicks'            => 'int',
        'conversions'       => 'int',
    ];

    public function experiment(): BelongsTo
    {
        return $this->belongsTo(BiolinkExperiment::class, 'biolink_experiment_id');
    }

    /**
     * Conversion rate (conversions / impressions). 0.0 when the arm
     * hasn't been shown yet so callers never divide by zero.
     */
    public function conversionRate(): float
    {
        if ($this->impressions <= 0) return 0.0;
        return round($this->conversions / $this->impressions, 4);
    }
}
