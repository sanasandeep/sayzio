<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Task #3060 — a saved AI Marketing Strategist plan.
 *
 * Owned directly by a user (the workspace owner when run inside a team
 * workspace). `strategy` holds the parsed structured plan; `sources`,
 * `parameters` and `context_snapshot` keep the inputs so the plan can be
 * re-opened and chat-refined. Child messages + suggestions are purged on
 * delete via the `deleting` hook (no DB FKs on the shared RDS).
 */
class MarketingStrategy extends Model
{
    protected $table = 'marketing_strategies';

    protected $fillable = [
        'user_id', 'workspace_id', 'profile_id', 'title', 'goal', 'status',
        'sources', 'source_items', 'parameters', 'profile_snapshot', 'context_snapshot', 'strategy',
        'diagnosis', 'scorecard', 'forecast', 'competitor_analysis', 'baseline', 'outcome',
        'goal_metric', 'share_token', 'model', 'credits_spent',
    ];

    protected $casts = [
        'sources'             => 'array',
        'source_items'        => 'array',
        'parameters'          => 'array',
        'profile_snapshot'    => 'array',
        'context_snapshot'    => 'array',
        'strategy'            => 'array',
        'diagnosis'           => 'array',
        'scorecard'           => 'array',
        'forecast'            => 'array',
        'competitor_analysis' => 'array',
        'baseline'            => 'array',
        'outcome'             => 'array',
        'credits_spent'       => 'integer',
    ];

    protected static function booted(): void
    {
        static::deleting(function (MarketingStrategy $strategy): void {
            $strategy->messages()->delete();
            $strategy->suggestions()->delete();
            $strategy->scores()->delete();
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Task #3302 — the named project profile this plan was generated for. */
    public function profile()
    {
        return $this->belongsTo(MarketingProfile::class, 'profile_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MarketingStrategyMessage::class, 'strategy_id')->orderBy('id');
    }

    public function suggestions(): HasMany
    {
        return $this->hasMany(MarketingStrategySuggestion::class, 'strategy_id')->orderBy('id');
    }

    /** Task #3281 — scorecard history (most recent last). */
    public function scores(): HasMany
    {
        return $this->hasMany(MarketingStrategyScore::class, 'strategy_id')->orderBy('id');
    }

    /** Short human label for the plan's headline goal. */
    public function goalSummary(int $len = 120): string
    {
        $goal = trim((string) $this->goal);
        return $goal === '' ? '—' : \Illuminate\Support\Str::limit($goal, $len);
    }

    /** The analysis depth (1-5) this plan was generated at. */
    public function depth(): int
    {
        $d = (int) (($this->parameters['depth'] ?? 3));
        return max(1, min(5, $d));
    }

    /** True once a public share link has been minted. */
    public function isShared(): bool
    {
        return trim((string) $this->share_token) !== '';
    }
}
