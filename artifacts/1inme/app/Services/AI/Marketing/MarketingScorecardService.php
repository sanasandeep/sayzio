<?php

namespace App\Services\AI\Marketing;

/**
 * Task #3281 — 0-100 marketing scorecard.
 *
 * Turns a deterministic {@see MarketingDiagnosisService} payload into four
 * 0-100 axes — Reach, Engagement, Conversion, Consistency — plus a
 * weighted overall, with a one-line reason per axis. Pure/side-effect-free
 * so it can be re-run any time the underlying data changes (re-scoring is
 * free — no AI, no coins).
 */
class MarketingScorecardService
{
    /** Overall weighting per axis (must sum to 1.0). */
    protected const WEIGHTS = [
        'reach'       => 0.25,
        'engagement' => 0.30,
        'conversion' => 0.30,
        'consistency' => 0.15,
    ];

    /**
     * @return array{
     *   reach:int, engagement:int, conversion:int, consistency:int, overall:int,
     *   reasons:array<string,string>
     * }
     */
    public function score(array $diagnosis): array
    {
        $m       = $diagnosis['metrics'] ?? [];
        $trend   = $diagnosis['trend'] ?? [];
        $hasData = (bool) ($diagnosis['has_data'] ?? false);

        if (!$hasData) {
            return [
                'reach' => 0, 'engagement' => 0, 'conversion' => 0, 'consistency' => 0, 'overall' => 0,
                'reasons' => [
                    'reach'       => 'No measurable reach yet — start sharing your links.',
                    'engagement' => 'Not enough visits to gauge engagement.',
                    'conversion' => 'No traffic to convert yet.',
                    'consistency' => 'Activity will show here once traffic begins.',
                ],
            ];
        }

        $reach       = $this->scoreReach($m);
        $engagement  = $this->scoreEngagement($m);
        $conversion  = $this->scoreConversion($m, $diagnosis);
        $consistency = $this->scoreConsistency($m, $diagnosis['window_days'] ?? 30, $trend);

        $overall = (int) round(
            $reach * self::WEIGHTS['reach']
            + $engagement * self::WEIGHTS['engagement']
            + $conversion * self::WEIGHTS['conversion']
            + $consistency * self::WEIGHTS['consistency']
        );

        return [
            'reach'       => $reach,
            'engagement' => $engagement,
            'conversion' => $conversion,
            'consistency' => $consistency,
            'overall'     => $this->clamp($overall),
            'reasons'     => [
                'reach'       => $this->reachReason($m, $reach),
                'engagement' => $this->engagementReason($m, $engagement),
                'conversion' => $this->conversionReason($m, $diagnosis, $conversion),
                'consistency' => $this->consistencyReason($m, (int) ($diagnosis['window_days'] ?? 30), $consistency),
            ],
        ];
    }

    protected function scoreReach(array $m): int
    {
        // Log-scaled human clicks (0..70) + geographic breadth (0..30).
        $clicks = max(0, (int) ($m['human_clicks'] ?? $m['clicks'] ?? 0));
        $volume = $clicks > 0 ? min(70, (int) round((log10($clicks + 1) / log10(2001)) * 70)) : 0;
        $geo    = min(30, (int) ($m['countries'] ?? 0) * 3);
        return $this->clamp($volume + $geo);
    }

    protected function scoreEngagement(array $m): int
    {
        // Dwell (0..45) + engaged blocks (0..30) + inverse bounce (0..25).
        $dwell   = min(45, (int) round(((int) ($m['avg_seconds'] ?? 0) / 60) * 45));
        $blocks  = min(30, (int) round(((int) ($m['engaged_blocks'] ?? 0) / 100) * 30));
        $bounce  = (int) ($m['bounce_rate'] ?? 0);
        $inverse = min(25, (int) round(((100 - min(100, $bounce)) / 100) * 25));
        // If we have no sessions at all, engagement is unknown → low but not zero when clicks exist.
        if ((int) ($m['sessions'] ?? 0) === 0) {
            return $this->clamp(min(20, $this->scoreReach($m) > 0 ? 15 : 0));
        }
        return $this->clamp($dwell + $blocks + $inverse);
    }

    protected function scoreConversion(array $m, array $diagnosis): int
    {
        $clicks   = max(0, (int) ($m['clicks'] ?? 0));
        $sessions = max(0, (int) ($m['sessions'] ?? 0));
        if ($clicks === 0) {
            return 0;
        }
        // Click → measured-session ratio is our proxy for a funnel that
        // holds people past the first tap (0..70).
        $ratio = min(1.0, $sessions / max(1, $clicks));
        $base  = (int) round($ratio * 70);

        // Penalise dead + conversion-blind links (they leak the funnel).
        $dead  = count($diagnosis['dead_links'] ?? []);
        $blind = count($diagnosis['conversion_blind'] ?? []);
        $penalty = min(30, ($dead * 3) + ($blind * 5));

        // Reward for engaged dwell (people actually doing something).
        $bonus = min(30, (int) round(((int) ($m['engaged_blocks'] ?? 0) / 100) * 30));

        return $this->clamp($base + $bonus - $penalty);
    }

    protected function scoreConsistency(array $m, int $windowDays, array $trend = []): int
    {
        $active = (int) ($m['active_days'] ?? 0);
        $windowDays = max(1, $windowDays);
        $spread = min(70, (int) round(($active / $windowDays) * 70));

        // Trend stability: growing or flat is good, sharp decline hurts.
        $delta = $trend['clicks_delta_pct'] ?? null;
        $trendScore = 30;
        if (is_numeric($delta)) {
            $delta = (int) $delta;
            if ($delta <= -40) {
                $trendScore = 5;
            } elseif ($delta < 0) {
                $trendScore = 18;
            } elseif ($delta >= 15) {
                $trendScore = 30;
            } else {
                $trendScore = 25;
            }
        }
        return $this->clamp($spread + $trendScore);
    }

    protected function reachReason(array $m, int $score): string
    {
        $clicks = (int) ($m['human_clicks'] ?? $m['clicks'] ?? 0);
        $geo    = (int) ($m['countries'] ?? 0);
        if ($score >= 70) {
            return "Strong reach: {$clicks} human clicks across {$geo} countries.";
        }
        if ($score >= 40) {
            return "Building reach: {$clicks} clicks from {$geo} countries — more sharing will lift this.";
        }
        return "Low reach so far ({$clicks} clicks) — distribution is the first lever to pull.";
    }

    protected function engagementReason(array $m, int $score): string
    {
        if ((int) ($m['sessions'] ?? 0) === 0) {
            return 'Engagement is not measurable yet — no page sessions recorded.';
        }
        $s = (int) ($m['avg_seconds'] ?? 0);
        $b = (int) ($m['bounce_rate'] ?? 0);
        if ($score >= 70) {
            return "High engagement: {$s}s average dwell, {$b}% bounce.";
        }
        if ($score >= 40) {
            return "Moderate engagement: {$s}s dwell, {$b}% bounce — tighten the hero to improve.";
        }
        return "Weak engagement: {$s}s dwell with {$b}% bounce — visitors leave fast.";
    }

    protected function conversionReason(array $m, array $diagnosis, int $score): string
    {
        $dead  = count($diagnosis['dead_links'] ?? []);
        $blind = count($diagnosis['conversion_blind'] ?? []);
        if ($score >= 70) {
            return 'Healthy funnel: clicks turn into real sessions and actions.';
        }
        if ($score >= 40) {
            $bits = [];
            if ($dead) { $bits[] = "{$dead} dead link(s)"; }
            if ($blind) { $bits[] = "{$blind} click-but-no-dwell link(s)"; }
            $tail = $bits ? ' Fix ' . implode(' and ', $bits) . '.' : '';
            return 'Funnel is leaking some traffic.' . $tail;
        }
        return 'Conversion is the weak point — clicks are not translating into engaged sessions.';
    }

    protected function consistencyReason(array $m, int $windowDays, int $score): string
    {
        $active = (int) ($m['active_days'] ?? 0);
        if ($score >= 70) {
            return "Very consistent: traffic on {$active} of the last {$windowDays} days.";
        }
        if ($score >= 40) {
            return "Somewhat consistent: active {$active}/{$windowDays} days — a regular cadence will help.";
        }
        return "Sporadic activity ({$active}/{$windowDays} days) — a steady posting rhythm is your biggest unlock.";
    }

    protected function clamp(int $n): int
    {
        return max(0, min(100, $n));
    }
}
