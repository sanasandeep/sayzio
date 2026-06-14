<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-user monthly API-call meter row (task #1393). See the
 * MeterApiUsage middleware for how this is incremented and how coin
 * overage is charged.
 */
class ApiUsageCounter extends Model
{
    protected $fillable = [
        'user_id',
        'period',
        'calls_used',
        'overage_calls',
        'coins_spent',
        'prepaid_overage_remaining',
    ];

    protected function casts(): array
    {
        return [
            'calls_used'                => 'integer',
            'overage_calls'             => 'integer',
            'coins_spent'               => 'integer',
            'prepaid_overage_remaining' => 'integer',
        ];
    }

    /** Current calendar-month bucket, e.g. "2026-06". */
    public static function currentPeriod(): string
    {
        return now()->format('Y-m');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
