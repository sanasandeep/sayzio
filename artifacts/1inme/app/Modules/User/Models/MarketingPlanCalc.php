<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Task #6737 — a saved Marketing Plan Calculator plan.
 *
 * Interactive replacement for the "Sayzio-Powered Digital Marketing Plan"
 * spreadsheet: budget, seasonality, per-channel benchmarks and the ROI /
 * value-of-Sayzio assumptions all live in one JSON `payload`. The browser
 * does every computation live; the server just stores the inputs.
 */
class MarketingPlanCalc extends Model
{
    protected $table = 'marketing_plan_calcs';

    protected $fillable = ['user_id', 'workspace_id', 'name', 'payload'];

    protected $casts = [
        'payload' => 'array',
    ];

    /**
     * Task #6771 — the metric keys a manually logged actuals month can carry
     * (overall monthly totals, not per channel).
     */
    public const ACTUAL_METRICS = ['spend', 'visitors', 'leads', 'customers', 'revenue'];

    /**
     * Task #6771 — how many of the payload's 12 actuals_log months have at
     * least one metric logged. A month with every field blank is "not yet
     * tracked" and doesn't count.
     */
    public static function trackedActualMonths(array $payload): int
    {
        $log = $payload['actuals_log'] ?? null;
        if (!is_array($log)) return 0;

        $n = 0;
        foreach (array_slice(array_values($log), 0, 12) as $month) {
            if (!is_array($month)) continue;
            foreach (self::ACTUAL_METRICS as $k) {
                if (isset($month[$k]) && is_numeric($month[$k])) { $n++; break; }
            }
        }
        return $n;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * All of an owner's saved plans, newest first.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int,self>
     */
    public static function listForOwner(int $userId, ?int $workspaceId = null)
    {
        return static::query()
            ->where('user_id', $userId)
            ->where('workspace_id', $workspaceId)
            ->orderByDesc('id')
            ->get();
    }
}
