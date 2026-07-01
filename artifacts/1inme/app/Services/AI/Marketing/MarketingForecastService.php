<?php

namespace App\Services\AI\Marketing;

/**
 * Task #3281 — three-scenario forecast.
 *
 * Projects a goal metric forward over a horizon into pessimistic /
 * realistic / optimistic bands. Rule-based and deterministic: the band
 * multipliers are anchored on the creator's recent trend and the current
 * scorecard (a healthier funnel earns a higher realistic uplift), then the
 * AI layer explains the assumptions — it never sets the numbers.
 */
class MarketingForecastService
{
    /**
     * @param  int    $baseline    Current value of the goal metric over the analysis window.
     * @param  string $goalMetric  Normalised metric key (clicks|reach|engagement|sessions).
     * @param  int    $horizonDays Forecast horizon in days.
     * @param  array  $diagnosis   Diagnosis payload (for trend).
     * @param  array  $scorecard   Scorecard payload (for funnel health).
     * @return array{
     *   goal_metric:string, baseline_value:int, horizon_days:int,
     *   bands:array<string,array{value:int, delta_pct:int, label:string}>
     * }
     */
    public function forecast(int $baseline, string $goalMetric, int $horizonDays, array $diagnosis, array $scorecard): array
    {
        $baseline    = max(0, $baseline);
        $horizonDays = max(7, min(365, $horizonDays));

        // Horizon scaling: a 60-day horizon has more room to move than 14.
        $horizonFactor = $horizonDays / 30.0;

        // Trend momentum from the diagnosis (clamped to a sane band).
        $delta = $diagnosis['trend']['clicks_delta_pct'] ?? 0;
        $delta = is_numeric($delta) ? max(-50, min(80, (int) $delta)) : 0;
        $momentum = $delta / 100.0; // -0.5 .. 0.8

        // Funnel health lifts the realistic case: a strong overall score
        // means the plan's actions are more likely to compound.
        $overall = (int) ($scorecard['overall'] ?? 0);
        $healthUplift = ($overall / 100.0) * 0.35; // up to +35%

        // Realistic growth = momentum carried forward + plan uplift,
        // scaled by horizon. Cold-start accounts (no baseline) get an
        // absolute floor so the forecast is still motivating.
        $realGrowth = ($momentum * 0.5 + 0.12 + $healthUplift) * $horizonFactor;
        $realGrowth = max(0.03, min(3.0, $realGrowth));

        $realistic   = (int) round($baseline * (1 + $realGrowth));
        $pessimistic = (int) round($baseline * (1 + max(-0.15, $realGrowth * 0.35)));
        $optimistic  = (int) round($baseline * (1 + min(4.0, $realGrowth * 1.9)));

        // Cold-start floors so an empty account still sees a target.
        if ($baseline < 5) {
            $floor = (int) max(5, round(10 * $horizonFactor));
            $pessimistic = max($pessimistic, (int) round($floor * 0.4));
            $realistic   = max($realistic, $floor);
            $optimistic  = max($optimistic, (int) round($floor * 2.4));
        }

        // Keep the ordering strictly pessimistic ≤ realistic ≤ optimistic.
        $realistic   = max($realistic, $pessimistic);
        $optimistic  = max($optimistic, $realistic + 1);

        return [
            'goal_metric'    => $goalMetric,
            'baseline_value' => $baseline,
            'horizon_days'   => $horizonDays,
            'bands'          => [
                'pessimistic' => $this->band('Pessimistic', $pessimistic, $baseline),
                'realistic'   => $this->band('Realistic', $realistic, $baseline),
                'optimistic'  => $this->band('Optimistic', $optimistic, $baseline),
            ],
        ];
    }

    protected function band(string $label, int $value, int $baseline): array
    {
        $delta = $baseline > 0
            ? (int) round((($value - $baseline) / max(1, $baseline)) * 100)
            : ($value > 0 ? 100 : 0);
        return ['value' => $value, 'delta_pct' => $delta, 'label' => $label];
    }
}
