<?php

namespace App\Services\AI\Marketing;

use App\Modules\User\Models\MarketingStrategy;
use App\Modules\User\Models\MarketingStrategySuggestion;
use App\Modules\User\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Task #3281 — outcome tracking.
 *
 * Re-measures a strategy's goal metric over the same window length that its
 * baseline was captured with and reports the movement: did the plan work?
 * Deterministic — it re-reads the same real tracking data the baseline came
 * from and compares. Also counts how many of the strategy's one-click
 * suggestions were actually applied, so an unmoved metric can be read in
 * the context of whether the plan was executed.
 */
class MarketingOutcomeService
{
    public function __construct(protected MarketingDiagnosisService $diagnosis) {}

    /**
     * @return array{
     *   goal_metric:string, baseline_value:int, current_value:int,
     *   delta_pct:int, verdict:string, window_days:int,
     *   applied_actions:int, total_actions:int, measured_at:string
     * }|null  null when there is no baseline to compare against.
     */
    public function evaluate(MarketingStrategy $strategy, User $user): ?array
    {
        $baseline = is_array($strategy->baseline ?? null) ? $strategy->baseline : [];
        if (empty($baseline) || !isset($baseline['value'])) {
            return null;
        }

        $metric     = $this->diagnosis->normalizeMetric($baseline['metric'] ?? ($strategy->goal_metric ?? 'clicks'));
        $windowDays = (int) ($baseline['window_days'] ?? MarketingDiagnosisService::WINDOW_DAYS);
        $baseValue  = (int) $baseline['value'];

        $current = $this->diagnosis->metricValue($user, $metric, $windowDays);

        $delta = $baseValue > 0
            ? (int) round((($current - $baseValue) / max(1, $baseValue)) * 100)
            : ($current > 0 ? 100 : 0);

        $verdict = 'flat';
        if ($delta >= 10) {
            $verdict = 'improved';
        } elseif ($delta <= -10) {
            $verdict = 'declined';
        }

        [$applied, $total] = $this->appliedActions($strategy);

        return [
            'goal_metric'     => $metric,
            'baseline_value'  => $baseValue,
            'current_value'   => $current,
            'delta_pct'       => $delta,
            'verdict'         => $verdict,
            'window_days'     => $windowDays,
            'applied_actions' => $applied,
            'total_actions'   => $total,
            'measured_at'     => Carbon::now()->toIso8601String(),
        ];
    }

    /** @return array{0:int,1:int} [applied, total] suggestion counts. */
    protected function appliedActions(MarketingStrategy $strategy): array
    {
        try {
            if (!Schema::hasTable('marketing_strategy_suggestions')) {
                return [0, 0];
            }
            $q = MarketingStrategySuggestion::query()->where('strategy_id', $strategy->id);
            $total   = (int) (clone $q)->count();
            $applied = (int) (clone $q)->where('status', 'applied')->count();
            return [$applied, $total];
        } catch (\Throwable $e) {
            return [0, 0];
        }
    }
}
