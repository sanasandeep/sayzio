<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A biolink layout A/B test. The "live" biolink_blocks rows are treated as
 * Variant B for the duration of the experiment — every save in the editor
 * automatically mirrors into `variant_b_snapshot`. Variant A is a frozen
 * snapshot taken when the experiment was started.
 */
class BiolinkExperiment extends Model
{
    protected $fillable = [
        'link_id',
        'mode',
        'variant_a_snapshot',
        'variant_b_snapshot',
        'status',
        'winner',
        'stop_condition',
        'stop_sample_size',
        'stop_end_date',
        'variant_a_visits',
        'variant_a_clicks',
        'variant_a_conversions',
        'variant_b_visits',
        'variant_b_clicks',
        'variant_b_conversions',
        'started_at',
        'stopped_at',
        'promoted_at',
    ];

    protected $casts = [
        'variant_a_snapshot'    => 'array',
        'variant_b_snapshot'    => 'array',
        'stop_end_date'         => 'datetime',
        'started_at'            => 'datetime',
        'stopped_at'            => 'datetime',
        'promoted_at'           => 'datetime',
        'variant_a_visits'      => 'int',
        'variant_a_clicks'      => 'int',
        'variant_a_conversions' => 'int',
        'variant_b_visits'      => 'int',
        'variant_b_clicks'      => 'int',
        'variant_b_conversions' => 'int',
    ];

    public function link(): BelongsTo
    {
        return $this->belongsTo(Link::class);
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    /**
     * 'adaptive' = per-segment multi-armed bandit block ordering
     * (Task #3531). Anything else (including the historical default,
     * empty string, or null on old rows) is the manual two-variant A/B
     * flow this model originally shipped with.
     */
    public function isAdaptive(): bool
    {
        return $this->mode === 'adaptive';
    }

    public function adaptiveArms(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BiolinkAdaptiveArm::class, 'biolink_experiment_id');
    }

    /**
     * Click-through rate (clicks / visits) for the requested variant.
     * Returns 0.0 when the variant has had no exposures yet so the UI
     * can render a stable "0%" instead of NaN.
     */
    public function ctrFor(string $variant): float
    {
        $variant = $variant === 'a' ? 'a' : 'b';
        $visits = (int) $this->{"variant_{$variant}_visits"};
        if ($visits <= 0) return 0.0;
        return round(((int) $this->{"variant_{$variant}_clicks"}) / $visits, 4);
    }

    /**
     * Total exposures across both variants. Used by the sample_size
     * stop condition to decide when to auto-promote.
     */
    public function totalVisits(): int
    {
        return $this->variant_a_visits + $this->variant_b_visits;
    }
}
