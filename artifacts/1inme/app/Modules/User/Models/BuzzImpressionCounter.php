<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-creator monthly Buzz (social-proof) impression meter row.
 *
 * See {@see \App\Services\BuzzImpressionMeter} for how this is incremented
 * (on each public impression event) and how the per-plan
 * `max_buzz_impressions` allowance is enforced. Mirrors ApiUsageCounter.
 */
class BuzzImpressionCounter extends Model
{
    protected $fillable = [
        'user_id',
        'period',
        'impressions_used',
    ];

    protected function casts(): array
    {
        return [
            'impressions_used' => 'integer',
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
