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
        'user_id', 'workspace_id', 'title', 'goal', 'status',
        'sources', 'parameters', 'context_snapshot', 'strategy',
        'model', 'credits_spent',
    ];

    protected $casts = [
        'sources'          => 'array',
        'parameters'       => 'array',
        'context_snapshot' => 'array',
        'strategy'         => 'array',
        'credits_spent'    => 'integer',
    ];

    protected static function booted(): void
    {
        static::deleting(function (MarketingStrategy $strategy): void {
            $strategy->messages()->delete();
            $strategy->suggestions()->delete();
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MarketingStrategyMessage::class, 'strategy_id')->orderBy('id');
    }

    public function suggestions(): HasMany
    {
        return $this->hasMany(MarketingStrategySuggestion::class, 'strategy_id')->orderBy('id');
    }

    /** Short human label for the plan's headline goal. */
    public function goalSummary(int $len = 120): string
    {
        $goal = trim((string) $this->goal);
        return $goal === '' ? '—' : \Illuminate\Support\Str::limit($goal, $len);
    }
}
