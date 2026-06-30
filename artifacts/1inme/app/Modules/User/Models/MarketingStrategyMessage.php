<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Task #3060 — append-only chat-refinement turn for a Marketing Strategy.
 * Assistant rows carry metered spend + model in `meta`.
 */
class MarketingStrategyMessage extends Model
{
    protected $table = 'marketing_strategy_messages';

    public $timestamps = false;

    protected $fillable = ['strategy_id', 'role', 'content', 'meta', 'created_at'];

    protected $casts = [
        'meta'       => 'array',
        'created_at' => 'datetime',
    ];

    public function strategy()
    {
        return $this->belongsTo(MarketingStrategy::class, 'strategy_id');
    }
}
