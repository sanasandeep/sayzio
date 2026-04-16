<?php

namespace App\Modules\User\Services;

use App\Modules\User\Models\Link;

/**
 * Performance Coach engine.
 *
 * Translates the already-computed analytics context (current period totals,
 * previous-period totals, block stats, engagement, referrers, link config)
 * into a 0-100 health score + a ranked list of actionable insights.
 *
 * PURE TRANSFORMER: this class issues no DB queries of its own. Any block
 * metadata the rules need (active block types/ids) is prefetched once by
 * the caller and passed in via the `blockInventory` key on the context.
 */
class LinkPerformanceCoach
{
    /**
     * User-tunable threshold fields. These are the only keys a per-link
     * override (preset or custom) is allowed to change — everything else
     * (weights, floors, etc.) stays on the defaults below for safety.
     */
    public const TUNABLE_KEYS = [
        'ctr_critical', 'ctr_warning', 'ctr_excellent',
        'bounce_critical', 'bounce_warning', 'bounce_excellent',
        'engagement_low_seconds', 'engagement_excellent_seconds',
        'momentum_drop_critical', 'momentum_drop_warning', 'momentum_win_threshold',
    ];

    /**
     * Ready-made preset bundles creators can pick from. Each preset only
     * overrides the TUNABLE_KEYS — the unchanged defaults in CONFIG still
     * govern weighting and rule gating.
     */
    public const PRESETS = [
        'creator' => [
            'label'       => 'Creator / Link-in-bio',
            'description' => 'Balanced defaults for a link-in-bio page.',
            'values'      => [
                'ctr_critical' => 0.05, 'ctr_warning' => 0.15, 'ctr_excellent' => 0.40,
                'bounce_critical' => 70, 'bounce_warning' => 50, 'bounce_excellent' => 25,
                'engagement_low_seconds' => 10, 'engagement_excellent_seconds' => 45,
                'momentum_drop_critical' => -0.40, 'momentum_drop_warning' => -0.15, 'momentum_win_threshold' => 0.25,
            ],
        ],
        'storefront' => [
            'label'       => 'Storefront',
            'description' => 'Shopping-oriented page — higher CTR expected, traffic swings normal.',
            'values'      => [
                'ctr_critical' => 0.08, 'ctr_warning' => 0.20, 'ctr_excellent' => 0.50,
                'bounce_critical' => 75, 'bounce_warning' => 60, 'bounce_excellent' => 30,
                'engagement_low_seconds' => 15, 'engagement_excellent_seconds' => 60,
                'momentum_drop_critical' => -0.30, 'momentum_drop_warning' => -0.10, 'momentum_win_threshold' => 0.20,
            ],
        ],
        'landing' => [
            'label'       => 'Landing page',
            'description' => 'Conversion-focused page — strict click-through and bounce expectations.',
            'values'      => [
                'ctr_critical' => 0.15, 'ctr_warning' => 0.30, 'ctr_excellent' => 0.60,
                'bounce_critical' => 60, 'bounce_warning' => 40, 'bounce_excellent' => 20,
                'engagement_low_seconds' => 20, 'engagement_excellent_seconds' => 75,
                'momentum_drop_critical' => -0.25, 'momentum_drop_warning' => -0.10, 'momentum_win_threshold' => 0.15,
            ],
        ],
    ];

    /**
     * Single source of truth for tuning thresholds and weights.
     * Kept at the top of the file as required by the task spec.
     */
    public const CONFIG = [
        // Score component weights (must sum to 100).
        'weights' => [
            'ctr'        => 25,   // block-clicks / page-visits
            'bounce'     => 20,   // inverse of bounce rate
            'engagement' => 15,   // avg session duration
            'momentum'   => 20,   // delta vs previous period
            'diversity'  => 10,   // referrer / alias spread
            'activity'   => 10,   // absolute traffic presence
        ],

        // CTR rule thresholds (block_clicks / page_visits, as a fraction).
        'ctr_critical' => 0.05,   // <5% = critical
        'ctr_warning'  => 0.15,   // <15% = warning
        'ctr_excellent'=> 0.40,   // >=40% = green win

        // Bounce rate thresholds (%).
        'bounce_critical' => 70,  // >70% bounce = critical
        'bounce_warning'  => 50,  // >50% bounce = warning
        'bounce_excellent'=> 25,  // <25% bounce = green win

        // Engagement thresholds (avg session duration, seconds).
        'engagement_low_seconds'       => 10,
        'engagement_excellent_seconds' => 45,

        // Dead blocks: active blocks with zero clicks in range.
        'dead_block_min_active_blocks' => 3,  // only flag if link has >=3 active blocks
        'dead_block_min_total_clicks'  => 20, // only flag once link has some traffic

        // Top-heavy distribution: one block captures >= X% of block-clicks.
        'top_heavy_share' => 0.70,

        // Momentum (vs prev period).
        'momentum_drop_critical' => -0.40, // -40% or worse = critical
        'momentum_drop_warning'  => -0.15, // -15% or worse = warning
        'momentum_win_threshold' => 0.25,  // +25% or better = green win

        // Referrer concentration — a single referrer producing >= X% of traffic
        // is risky (algorithm change wipes you out).
        'single_referrer_share'   => 0.80,
        'single_referrer_min_refs'=> 20,

        // Page length / attention mismatch.
        'long_page_block_count'   => 10,  // blocks
        'long_page_avg_seconds'   => 12,  // but avg session < this

        // Minimum traffic for most rules to fire (avoids noisy advice on empty pages).
        'min_traffic_for_rules' => 10,

        // Onboarding threshold — below this we show the empty / setup state.
        'empty_state_threshold' => 1,
    ];

    /**
     * Compute the full Performance Coach payload.
     *
     * @param  array{
     *   link: Link,
     *   totalInRange: int,
     *   uniqueInRange: int,
     *   blockClicksInRange: int,
     *   pageVisitsInRange: int,
     *   totalSessions: int,
     *   avgSessionSeconds: int,
     *   bounceRate: float,
     *   blockStats: \Illuminate\Support\Collection,
     *   topReferrers: \Illuminate\Support\Collection,
     *   totalInRangePrev?: int,
     *   aliasFilter?: ?string,
     *   period?: string,
     * }  $ctx
     */
    public static function build(array $ctx): array
    {
        /** @var Link $link */
        $link  = $ctx['link'];

        // Resolve effective config for this link (preset + overrides) once,
        // then pass it via $ctx so every rule / score helper sees the same
        // thresholds without re-resolving.
        $cfg = self::resolveConfig($link);
        $ctx['_config'] = $cfg;
        $ctx['_preset'] = self::resolvePreset($link);

        $total = (int) ($ctx['totalInRange'] ?? 0);

        // Empty / onboarding state
        if ($total < $cfg['empty_state_threshold']) {
            return self::emptyState($ctx);
        }

        // When alias filter is active, scrub session-derived fields from the
        // rule context so session-dependent rules (bounce, long-page-short-
        // session, low-bounce-win) silently skip — page_sessions can't be
        // scoped to a single alias yet.
        if (!empty($ctx['aliasFilter'])) {
            $ctx['totalSessions']     = 0;
            $ctx['avgSessionSeconds'] = 0;
            $ctx['bounceRate']        = 0.0;
        }

        $deltaPct = self::trendDelta($ctx);
        $score    = self::computeScore($ctx);
        [$grade, $label] = self::gradeAndLabel($score);

        $rules = [
            self::ruleHighBounce($ctx),
            self::ruleLowCtr($ctx),
            self::ruleDeadBlocks($ctx),
            self::ruleTopHeavy($ctx),
            self::ruleTrafficDrop($ctx, $deltaPct),
            self::ruleSingleReferrer($ctx),
            self::ruleMissingSocials($ctx),
            self::ruleLongPageShortSession($ctx),
            self::ruleMissingPixel($ctx),
            self::ruleNoQrScans($ctx),
            self::ruleMomentumWin($ctx, $deltaPct),
            self::ruleHighCtrWin($ctx),
            self::ruleLowBounceWin($ctx),
        ];

        $insights = array_values(array_filter($rules));

        // Rank: severity → priority (both already numeric on the insight).
        usort($insights, function ($a, $b) {
            $sev = self::severityRank($b['severity']) <=> self::severityRank($a['severity']);
            if ($sev !== 0) return $sev;
            return ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0);
        });

        // Cap at 5 visible.
        $insights = array_slice($insights, 0, 5);

        // All-good fallback: if no critical/warning insights fire, ensure at
        // least one positive reinforcement insight is present.
        $hasNegative = false;
        foreach ($insights as $ins) {
            if (in_array($ins['severity'], ['critical', 'warning'], true)) { $hasNegative = true; break; }
        }
        if (!$hasNegative && empty($insights)) {
            $insights[] = self::allGoodInsight();
        }

        return [
            'empty'        => false,
            'score'        => $score,
            'grade'        => $grade,
            'label'        => $label,
            'delta_pct'    => $deltaPct,
            'delta_label'  => self::formatDelta($deltaPct, $ctx['period'] ?? null),
            'alias_filter' => $ctx['aliasFilter'] ?? null,
            'insights'     => $insights,
            'headline'     => !empty($ctx['aliasFilter'])
                ? 'Performance for /' . $ctx['aliasFilter']
                : 'Performance Coach',
        ];
    }

    /* ------------------------------------------------------------------
     | Scoring
     |------------------------------------------------------------------*/

    private static function computeScore(array $ctx): int
    {
        return self::scoreWithComponents($ctx)['score'];
    }

    /**
     * Compute the 0-100 score alongside the normalized (0-1) per-component
     * breakdown used to produce it. Split out from `computeScore` so nightly
     * snapshots can persist both the headline score and its components.
     *
     * @return array{score:int, components:array<string,float>}
     */
    public static function scoreWithComponents(array $ctx): array
    {
        $cfg = $ctx['_config'] ?? self::CONFIG;
        $w   = $cfg['weights'];

        $pageVisits  = max(1, (int) ($ctx['pageVisitsInRange'] ?? 0));
        $blockClicks = (int) ($ctx['blockClicksInRange'] ?? 0);
        $ctr         = min(1.0, $blockClicks / $pageVisits);

        // CTR component: 0% => 0, 40%+ => 1.0 (linear).
        $ctrScore = min(1.0, $ctr / $cfg['ctr_excellent']);

        // Session-derived metrics (bounce + engagement) cannot currently be
        // alias-scoped because `page_sessions` is per-page, not per-alias.
        // When an alias filter is active we drop these components from the
        // score entirely and renormalize the remaining weights — this is
        // more honest than blending alias click data with page-wide sessions.
        $sessionsAvailable = empty($ctx['aliasFilter']);
        $bounceScore = $sessionsAvailable
            ? max(0.0, 1.0 - (((float) ($ctx['bounceRate'] ?? 0)) / 100.0))
            : 0.0;
        $avgSec   = (int) ($ctx['avgSessionSeconds'] ?? 0);
        $engScore = $sessionsAvailable
            ? min(1.0, $avgSec / max(1, $cfg['engagement_excellent_seconds']))
            : 0.0;

        // Momentum: piecewise-linear mapping so the configured drop/win
        // thresholds actually map to 0.0 and 1.0 respectively.
        //   delta <= drop_critical           => 0.0
        //   drop_critical < delta < 0        => 0.0 → 0.5
        //   delta == 0                       => 0.5
        //   0 < delta < momentum_win_thresh  => 0.5 → 1.0
        //   delta >= momentum_win_threshold  => 1.0
        $delta = self::trendDelta($ctx);
        if ($delta === null) {
            $momentumScore = 0.5;
        } elseif ($delta <= $cfg['momentum_drop_critical']) {
            $momentumScore = 0.0;
        } elseif ($delta < 0) {
            $span = abs($cfg['momentum_drop_critical']);
            $momentumScore = 0.5 * (1.0 - (abs($delta) / max(1e-9, $span)));
        } elseif ($delta >= $cfg['momentum_win_threshold']) {
            $momentumScore = 1.0;
        } else {
            $momentumScore = 0.5 + 0.5 * ($delta / max(1e-9, $cfg['momentum_win_threshold']));
        }
        $momentumScore = max(0.0, min(1.0, $momentumScore));

        // Diversity: referrer Herfindahl-ish — more spread = better.
        $diversityScore = self::diversityScore($ctx);

        // Activity: has meaningful traffic vs the "min_traffic_for_rules" floor.
        $total = (int) ($ctx['totalInRange'] ?? 0);
        $activityScore = min(1.0, $total / max(1, $cfg['min_traffic_for_rules'] * 10));

        // When session data is unavailable (alias-filtered), drop the bounce +
        // engagement weights and renormalize so the score still lives on the
        // full 0-100 range instead of being artificially capped.
        $components = [
            ['score' => $ctrScore,       'weight' => $w['ctr']],
            ['score' => $momentumScore,  'weight' => $w['momentum']],
            ['score' => $diversityScore, 'weight' => $w['diversity']],
            ['score' => $activityScore,  'weight' => $w['activity']],
        ];
        if ($sessionsAvailable) {
            $components[] = ['score' => $bounceScore, 'weight' => $w['bounce']];
            $components[] = ['score' => $engScore,    'weight' => $w['engagement']];
        }
        $wSum = 0;
        $raw  = 0.0;
        foreach ($components as $c) {
            $raw  += $c['score'] * $c['weight'];
            $wSum += $c['weight'];
        }
        $breakdown = [
            'ctr'        => round($ctrScore, 4),
            'bounce'     => round($bounceScore, 4),
            'engagement' => round($engScore, 4),
            'momentum'   => round($momentumScore, 4),
            'diversity'  => round($diversityScore, 4),
            'activity'   => round($activityScore, 4),
        ];
        if ($wSum <= 0) {
            return ['score' => 0, 'components' => $breakdown];
        }
        $normalized = ($raw / $wSum) * 100.0;
        $score = (int) max(0, min(100, round($normalized)));
        return ['score' => $score, 'components' => $breakdown];
    }

    private static function diversityScore(array $ctx): float
    {
        $refs = $ctx['topReferrers'] ?? collect();
        $total = 0;
        $counts = [];
        foreach ($refs as $r) {
            $c = (int) ($r->count ?? 0);
            $counts[] = $c;
            $total   += $c;
        }
        if ($total <= 0 || count($counts) === 0) return 0.4; // neutral default
        // Normalized Herfindahl — lower = more diverse.
        $hhi = 0.0;
        foreach ($counts as $c) {
            $share = $c / $total;
            $hhi  += $share * $share;
        }
        // hhi=1 (single source) => 0.0, hhi approaching 0 (many) => 1.0
        return max(0.0, min(1.0, 1.0 - $hhi));
    }

    private static function gradeAndLabel(int $score): array
    {
        // Four-label scheme from spec: Excellent / Healthy / Needs work / Critical.
        return match (true) {
            $score >= 85 => ['A', 'Excellent'],
            $score >= 70 => ['B', 'Healthy'],
            $score >= 50 => ['C', 'Needs work'],
            $score >= 30 => ['D', 'Needs work'],
            default      => ['F', 'Critical'],
        };
    }

    /**
     * Percentage delta in total clicks vs previous period.
     * Returns a float (e.g. 0.14 for +14%), or null when comparison is not
     * meaningful (no prev baseline).
     */
    private static function trendDelta(array $ctx): ?float
    {
        $cur  = (int) ($ctx['totalInRange'] ?? 0);
        $prev = (int) ($ctx['totalInRangePrev'] ?? 0);
        if ($prev <= 0) return $cur > 0 ? 1.0 : null;
        return ($cur - $prev) / $prev;
    }

    private static function formatDelta(?float $delta, ?string $period): string
    {
        $periodLabel = match ($period) {
            'today' => 'vs yesterday',
            '7d'    => 'vs prev 7d',
            '30d'   => 'vs prev 30d',
            '90d'   => 'vs prev 90d',
            'year'  => 'vs prev year',
            default => 'vs previous period',
        };
        if ($delta === null) return 'No prior baseline';
        $pct = round($delta * 100);
        $sign = $pct > 0 ? '+' : '';
        return "{$sign}{$pct}% {$periodLabel}";
    }

    /* ------------------------------------------------------------------
     | Rules
     |------------------------------------------------------------------*/

    private static function ruleHighBounce(array $ctx): ?array
    {
        $cfg = $ctx['_config'] ?? self::CONFIG;
        if ((int) ($ctx['totalSessions'] ?? 0) < 10) return null;
        $rate = (float) ($ctx['bounceRate'] ?? 0);

        if ($rate >= $cfg['bounce_critical']) {
            return [
                'severity' => 'critical', 'priority' => 95,
                'icon' => 'fa-person-running',
                'headline' => "Bounce rate is {$rate}%",
                'reason'   => "Most visitors leave within 5 seconds. Improve the hero — clearer headline, first-screen CTA.",
                'action_label' => 'Edit page',
                'action_url'   => self::editUrl($ctx),
                'threshold'    => self::thresholdPayload('bounce_critical', 'critical', '≥', round($cfg['bounce_critical']) . '%', round($rate) . '%'),
            ];
        }
        if ($rate >= $cfg['bounce_warning']) {
            return [
                'severity' => 'warning', 'priority' => 70,
                'icon' => 'fa-running',
                'headline' => "{$rate}% bounce rate",
                'reason'   => "Over half of visitors leave almost immediately. Tighten the top of the page.",
                'action_label' => 'Edit page',
                'action_url'   => self::editUrl($ctx),
                'threshold'    => self::thresholdPayload('bounce_warning', 'warning', '≥', round($cfg['bounce_warning']) . '%', round($rate) . '%'),
            ];
        }
        return null;
    }

    private static function ruleLowBounceWin(array $ctx): ?array
    {
        $cfg = $ctx['_config'] ?? self::CONFIG;
        if ((int) ($ctx['totalSessions'] ?? 0) < 20) return null;
        $rate = (float) ($ctx['bounceRate'] ?? 0);
        if ($rate <= $cfg['bounce_excellent']) {
            return [
                'severity' => 'win', 'priority' => 20,
                'icon' => 'fa-hand-holding-heart',
                'headline' => "Only {$rate}% bounce",
                'reason'   => "Visitors are sticking around — your page is compelling.",
                'threshold' => self::thresholdPayload('bounce_excellent', 'excellent', '≤', round($cfg['bounce_excellent']) . '%', round($rate) . '%'),
            ];
        }
        return null;
    }

    private static function ruleLowCtr(array $ctx): ?array
    {
        $cfg = $ctx['_config'] ?? self::CONFIG;
        $visits = (int) ($ctx['pageVisitsInRange'] ?? 0);
        $blockClicks = (int) ($ctx['blockClicksInRange'] ?? 0);
        if ($visits < 20) return null;
        $ctr = $blockClicks / max(1, $visits);
        $pct = round($ctr * 100, 1);

        if ($ctr < $cfg['ctr_critical']) {
            return [
                'severity' => 'critical', 'priority' => 90,
                'icon' => 'fa-bullseye',
                'headline' => "Only {$pct}% click-through",
                'reason'   => "Visitors arrive but rarely click a block. Reorder or rewrite your top blocks.",
                'action_label' => 'Reorder blocks',
                'action_url'   => self::editUrl($ctx),
                'threshold'    => self::thresholdPayload('ctr_critical', 'critical', '<', round($cfg['ctr_critical'] * 100) . '%', $pct . '%'),
            ];
        }
        if ($ctr < $cfg['ctr_warning']) {
            return [
                'severity' => 'warning', 'priority' => 65,
                'icon' => 'fa-bullseye',
                'headline' => "{$pct}% click-through",
                'reason'   => "Below healthy click-through. Move your strongest link to the top.",
                'action_label' => 'Edit page',
                'action_url'   => self::editUrl($ctx),
                'threshold'    => self::thresholdPayload('ctr_warning', 'warning', '<', round($cfg['ctr_warning'] * 100) . '%', $pct . '%'),
            ];
        }
        return null;
    }

    private static function ruleHighCtrWin(array $ctx): ?array
    {
        $cfg = $ctx['_config'] ?? self::CONFIG;
        $visits = (int) ($ctx['pageVisitsInRange'] ?? 0);
        $blockClicks = (int) ($ctx['blockClicksInRange'] ?? 0);
        if ($visits < 20) return null;
        $ctr = $blockClicks / max(1, $visits);
        if ($ctr >= $cfg['ctr_excellent']) {
            $pct = round($ctr * 100);
            return [
                'severity' => 'win', 'priority' => 30,
                'icon' => 'fa-bullseye',
                'headline' => "{$pct}% of visitors click through",
                'reason'   => "Great engagement. Whatever you're doing — keep it.",
                'threshold' => self::thresholdPayload('ctr_excellent', 'excellent', '≥', round($cfg['ctr_excellent'] * 100) . '%', $pct . '%'),
            ];
        }
        return null;
    }

    private static function ruleDeadBlocks(array $ctx): ?array
    {
        $cfg = $ctx['_config'] ?? self::CONFIG;
        /** @var Link $link */
        $link = $ctx['link'];
        if ($link->type !== 'biolink') return null;
        if ((int) ($ctx['totalInRange'] ?? 0) < $cfg['dead_block_min_total_clicks']) return null;

        $blockStats = $ctx['blockStats'] ?? collect();
        $clicked = [];
        foreach ($blockStats as $b) {
            $clicked[$b->block_id] = true;
        }

        $inv = $ctx['blockInventory'] ?? [];
        // `clickable` = active top-level blocks whose type is something a visitor
        // can actually click (so we don't false-flag headings/dividers/etc.).
        $clickable = $inv['clickable'] ?? [];
        if (count($clickable) < $cfg['dead_block_min_active_blocks']) return null;

        $dead = array_values(array_filter($clickable, fn ($id) => !isset($clicked[$id])));
        if (count($dead) < 1) return null;
        if (count($dead) < 2 && count($clickable) < 5) return null;

        $n = count($dead);
        return [
            'severity' => 'warning', 'priority' => 75,
            'icon' => 'fa-ghost',
            'headline' => "{$n} block" . ($n === 1 ? ' gets' : 's get') . " zero clicks",
            'reason'   => "Consider removing, rewording, or moving them higher on the page.",
            'action_label' => $n === 1 ? 'Hide this block' : "Hide all {$n}",
            'action_url'   => self::editUrl($ctx),
            'action'       => [
                'type'      => 'deactivate_blocks',
                'block_ids' => array_values(array_map('intval', $dead)),
                'confirm'   => $n === 1
                    ? 'Hide this block from your page?'
                    : "Hide {$n} zero-click blocks from your page?",
            ],
        ];
    }

    private static function ruleTopHeavy(array $ctx): ?array
    {
        $cfg = $ctx['_config'] ?? self::CONFIG;
        $blockStats = $ctx['blockStats'] ?? collect();
        $totalBlockClicks = (int) ($ctx['blockClicksInRange'] ?? 0);
        if ($totalBlockClicks < 30 || $blockStats->count() < 3) return null;

        $top = $blockStats->first();
        if (!$top) return null;
        $share = $top->count / max(1, $totalBlockClicks);
        if ($share < $cfg['top_heavy_share']) return null;

        $pct = round($share * 100);
        $insight = [
            'severity' => 'tip', 'priority' => 50,
            'icon' => 'fa-chart-pie',
            'headline' => "One block captures {$pct}% of clicks",
            'reason'   => "Clicks are concentrated on a single block. Move it to the top so visitors see it first.",
            'action_label' => 'Manage blocks',
            'action_url'   => self::editUrl($ctx),
            'action'       => [
                'type'     => 'promote_block',
                'block_id' => (int) $top->block_id,
                'label'    => 'Pin top performer to the top',
            ],
        ];
        // One-click: promote the top-performing block to position 1 — but only
        // if the top block is a real top-level block on THIS link (child blocks
        // nested inside cards don't have a meaningful "position 1").
        $topId = (int) ($top->block_id ?? 0);
        $topLevelIds = $ctx['blockInventory']['clickable'] ?? [];
        if ($topId > 0 && in_array($topId, $topLevelIds, true)) {
            $insight['action_label'] = 'Move to top';
            $insight['action'] = [
                'type'     => 'promote_block',
                'block_id' => $topId,
                'confirm'  => 'Move this block to the top of your page?',
            ];
        }
        return $insight;
    }

    private static function ruleTrafficDrop(array $ctx, ?float $delta): ?array
    {
        $cfg = $ctx['_config'] ?? self::CONFIG;
        if ($delta === null) return null;
        if ((int) ($ctx['totalInRange'] ?? 0) < $cfg['min_traffic_for_rules']) return null;

        if ($delta <= $cfg['momentum_drop_critical']) {
            $pct = abs(round($delta * 100));
            return [
                'severity' => 'critical', 'priority' => 85,
                'icon' => 'fa-arrow-trend-down',
                'headline' => "Traffic down {$pct}%",
                'reason'   => "Clicks dropped sharply vs the previous period. Check referrers and recent changes.",
                'threshold' => self::thresholdPayload('momentum_drop_critical', 'critical', '≤', round($cfg['momentum_drop_critical'] * 100) . '%', round($delta * 100) . '%'),
            ];
        }
        if ($delta <= $cfg['momentum_drop_warning']) {
            $pct = abs(round($delta * 100));
            return [
                'severity' => 'warning', 'priority' => 60,
                'icon' => 'fa-arrow-trend-down',
                'headline' => "Traffic down {$pct}%",
                'reason'   => "Softer than the previous period. Refresh your share channels.",
                'threshold' => self::thresholdPayload('momentum_drop_warning', 'warning', '≤', round($cfg['momentum_drop_warning'] * 100) . '%', round($delta * 100) . '%'),
            ];
        }
        return null;
    }

    private static function ruleMomentumWin(array $ctx, ?float $delta): ?array
    {
        $cfg = $ctx['_config'] ?? self::CONFIG;
        if ($delta === null) return null;
        if ($delta < $cfg['momentum_win_threshold']) return null;
        if ((int) ($ctx['totalInRange'] ?? 0) < $cfg['min_traffic_for_rules']) return null;
        $pct = round($delta * 100);
        $sign = $pct > 0 ? '+' : '';
        return [
            'severity' => 'win', 'priority' => 40,
            'icon' => 'fa-arrow-trend-up',
            'headline' => "Traffic up {$pct}%",
            'reason'   => "Momentum is on your side. Lean into whatever channel drove the spike.",
            'threshold' => self::thresholdPayload('momentum_win_threshold', 'win', '≥', '+' . round($cfg['momentum_win_threshold'] * 100) . '%', $sign . $pct . '%'),
        ];
    }

    private static function ruleSingleReferrer(array $ctx): ?array
    {
        $cfg = $ctx['_config'] ?? self::CONFIG;
        $refs = $ctx['topReferrers'] ?? collect();
        if ($refs->count() === 0) return null;
        $sum = 0;
        foreach ($refs as $r) { $sum += (int) $r->count; }
        if ($sum < $cfg['single_referrer_min_refs']) return null;
        $top = $refs->first();
        $share = $top->count / max(1, $sum);
        if ($share < $cfg['single_referrer_share']) return null;
        $host = parse_url($top->referrer, PHP_URL_HOST) ?: $top->referrer;
        $pct  = round($share * 100);
        return [
            'severity' => 'tip', 'priority' => 45,
            'icon' => 'fa-link-slash',
            'headline' => "{$pct}% of traffic is from {$host}",
            'reason'   => "Single-channel dependence is risky. Try sharing elsewhere to diversify referrers.",
        ];
    }

    private static function ruleMissingSocials(array $ctx): ?array
    {
        /** @var Link $link */
        $link = $ctx['link'];
        if ($link->type !== 'biolink') return null;

        $inv = $ctx['blockInventory'] ?? [];
        if (!empty($inv['has_socials'])) return null;
        if ((int) ($inv['active_count'] ?? 0) < 2) return null; // not worth nagging an empty page

        // If the user already has a socials block but it's disabled, offer a
        // one-click enable instead of the "add socials" tip.
        $disabledId = $inv['disabled_socials_block_id'] ?? null;
        if ($disabledId) {
            return [
                'severity' => 'tip', 'priority' => 38,
                'icon' => 'fa-share-nodes',
                'headline' => 'Your socials block is hidden',
                'reason'   => 'You have a socials block but it\'s disabled — turn it on so visitors can follow you.',
                'action_label' => 'Enable socials',
                'action_url'   => self::editUrl($ctx),
                'action'       => [
                    'type'     => 'enable_block',
                    'block_id' => (int) $disabledId,
                    'confirm'  => 'Enable your socials block?',
                ],
            ];
        }

        return [
            'severity' => 'tip', 'priority' => 35,
            'icon' => 'fa-share-nodes',
            'headline' => 'No socials block yet',
            'reason'   => 'Adding a socials block helps visitors follow you across platforms.',
            'action_label' => 'Add socials',
            'action_url'   => self::editUrl($ctx),
        ];
    }

    private static function ruleLongPageShortSession(array $ctx): ?array
    {
        $cfg = $ctx['_config'] ?? self::CONFIG;
        /** @var Link $link */
        $link = $ctx['link'];
        if ($link->type !== 'biolink') return null;
        if ((int) ($ctx['totalSessions'] ?? 0) < 20) return null;

        $inv = $ctx['blockInventory'] ?? [];
        $blockCount = (int) ($inv['top_level_active_count'] ?? 0);
        $avgSec = (int) ($ctx['avgSessionSeconds'] ?? 0);
        if ($blockCount < $cfg['long_page_block_count']) return null;
        if ($avgSec >= $cfg['long_page_avg_seconds']) return null;

        return [
            'severity' => 'tip', 'priority' => 40,
            'icon' => 'fa-scroll',
            'headline' => "Long page, short attention",
            'reason'   => "Page has {$blockCount} blocks but avg. session is only {$avgSec}s. Trim low performers.",
            'action_label' => 'Trim blocks',
            'action_url'   => self::editUrl($ctx),
        ];
    }

    private static function ruleMissingPixel(array $ctx): ?array
    {
        /** @var Link $link */
        $link = $ctx['link'];
        if (!$link->relationLoaded('pixels')) return null;
        if ($link->pixels->count() > 0) return null;
        if ((int) ($ctx['totalInRange'] ?? 0) < 30) return null;

        return [
            'severity' => 'tip', 'priority' => 25,
            'icon' => 'fa-bullseye-arrow',
            'headline' => 'No tracking pixel attached',
            'reason'   => 'Attach a Meta, TikTok, or Google pixel to retarget the visitors you already have.',
            'action_label' => 'Add pixel',
            'action_url'   => self::pixelsUrl(),
        ];
    }

    private static function ruleNoQrScans(array $ctx): ?array
    {
        /** @var Link $link */
        $link = $ctx['link'];
        if ($link->type !== 'biolink') return null;
        // Feature gate: only fire if the link has a QR-code block configured.
        $inv = $ctx['blockInventory'] ?? [];
        if (empty($inv['has_qr'])) return null;

        $refs = $ctx['topReferrers'] ?? collect();
        $anyQr = false;
        foreach ($refs as $r) {
            if ($r->referrer && stripos($r->referrer, 'qr') !== false) { $anyQr = true; break; }
        }
        if ($anyQr) return null;
        if ((int) ($ctx['totalInRange'] ?? 0) < 30) return null;

        return [
            'severity' => 'tip', 'priority' => 20,
            'icon' => 'fa-qrcode',
            'headline' => 'No QR-attributed traffic this period',
            'reason'   => 'Your QR block isn\'t pulling its weight — print it on offline collateral like cards, flyers, or signage.',
        ];
    }

    private static function allGoodInsight(): array
    {
        return [
            'severity' => 'win', 'priority' => 10,
            'icon' => 'fa-check-double',
            'headline' => "Everything looks healthy",
            'reason'   => 'No red flags detected. Keep shipping — and keep sharing.',
        ];
    }

    /* ------------------------------------------------------------------
     | Empty state
     |------------------------------------------------------------------*/

    private static function emptyState(array $ctx): array
    {
        /** @var Link $link */
        $link = $ctx['link'];
        $tips = [
            [
                'severity' => 'tip', 'priority' => 90,
                'icon' => 'fa-share-square',
                'headline' => 'Share your link once to seed data',
                'reason'   => 'The coach starts giving recommendations as soon as you have real visitors.',
            ],
            [
                'severity' => 'tip', 'priority' => 80,
                'icon' => 'fa-th-large',
                'headline' => 'Add at least 3 blocks',
                'reason'   => 'A biolink with one block has nothing to compare against. Add your top links.',
                'action_label' => 'Edit page',
                'action_url'   => self::editUrl($ctx),
            ],
            [
                'severity' => 'tip', 'priority' => 70,
                'icon' => 'fa-bullseye-arrow',
                'headline' => 'Attach a tracking pixel',
                'reason'   => 'Retargeting unlocks as soon as you start getting traffic.',
                'action_label' => 'Add pixel',
                'action_url'   => self::pixelsUrl(),
            ],
        ];
        return [
            'empty'        => true,
            'score'        => null,
            'grade'        => null,
            'label'        => 'Awaiting data',
            'delta_pct'    => null,
            'delta_label'  => 'Share your link to start',
            'alias_filter' => $ctx['aliasFilter'] ?? null,
            'insights'     => $tips,
            'headline'     => !empty($ctx['aliasFilter'])
                ? 'Performance for /' . $ctx['aliasFilter']
                : 'Performance Coach',
        ];
    }

    /* ------------------------------------------------------------------
     | Helpers
     |------------------------------------------------------------------*/

    /**
     * Build the threshold metadata attached to an insight so the UI can show
     * creators which rule fired and jump them to the matching tuning field.
     *
     * @param string $key             One of TUNABLE_KEYS.
     * @param string $severityLabel   Human label: 'critical', 'warning', 'excellent', 'win'.
     * @param string $comparator      Symbolic comparator that matches the rule condition ('>', '<', '≥', '≤').
     * @param string $thresholdDisplay Pre-formatted threshold value (e.g. '50%').
     * @param string $actualDisplay    Pre-formatted measured value (e.g. '58%').
     */
    private static function thresholdPayload(string $key, string $severityLabel, string $comparator, string $thresholdDisplay, string $actualDisplay): array
    {
        return [
            'key'             => $key,
            'severity_label'  => $severityLabel,
            'comparator'      => $comparator,
            'threshold_label' => "{$severityLabel} {$comparator} {$thresholdDisplay}",
            'actual_label'    => "your value {$actualDisplay}",
        ];
    }

    private static function severityRank(string $s): int
    {
        return match ($s) {
            'critical' => 4,
            'warning'  => 3,
            'tip'      => 2,
            'win'      => 1,
            default    => 0,
        };
    }

    private static function editUrl(array $ctx): string
    {
        return route('user.links.edit', $ctx['link']);
    }

    /* ------------------------------------------------------------------
     | Per-link tuning (preset / custom overrides)
     |------------------------------------------------------------------*/

    /**
     * Read the raw performance-coach settings stored on the link
     * (settings->performance_coach). Shape: ['preset' => string, 'overrides' => array].
     */
    public static function linkSettings(?Link $link): array
    {
        if (!$link) return [];
        $s = $link->settings ?? [];
        if (!is_array($s)) return [];
        $pc = $s['performance_coach'] ?? [];
        return is_array($pc) ? $pc : [];
    }

    /**
     * Returns true if the link has its own performance-coach settings saved.
     * Used to distinguish "no per-link override → use admin defaults" from
     * "explicit creator preset chosen on this link".
     */
    private static function hasLinkOverride(?Link $link): bool
    {
        $pc = self::linkSettings($link);
        return !empty($pc['preset']);
    }

    /**
     * The preset key currently applied to this link. Falls back to the
     * admin-chosen workspace default ('creator' if none set).
     */
    public static function resolvePreset(?Link $link): string
    {
        if (self::hasLinkOverride($link)) {
            $preset = self::linkSettings($link)['preset'];
        } else {
            $admin = self::adminSettings();
            $preset = $admin['preset'] ?? 'creator';
        }
        $valid = self::validPresetKeys();
        return in_array($preset, $valid, true) ? $preset : 'creator';
    }

    /**
     * The effective config for this link — CONFIG defaults + preset tunables
     * + custom overrides (from the link if set, otherwise admin defaults).
     */
    public static function resolveConfig(?Link $link): array
    {
        $cfg = self::CONFIG;
        $preset = self::resolvePreset($link);

        if ($preset === 'custom') {
            // Custom: start from the creator baseline, then apply whichever
            // set of overrides governs (per-link if set, else admin-wide).
            foreach ((self::PRESETS['creator']['values'] ?? []) as $k => $v) {
                $cfg[$k] = $v;
            }
            if (self::hasLinkOverride($link)) {
                $pc = self::linkSettings($link);
                $overrides = is_array($pc['overrides'] ?? null) ? $pc['overrides'] : [];
            } else {
                $admin = self::adminSettings();
                $overrides = is_array($admin['overrides'] ?? null) ? $admin['overrides'] : [];
            }
            foreach (self::TUNABLE_KEYS as $k) {
                if (array_key_exists($k, $overrides) && is_numeric($overrides[$k])) {
                    $cfg[$k] = self::clampTunable($k, (float) $overrides[$k]);
                }
            }
        } else {
            foreach (self::presetValues($preset) as $k => $v) {
                if (in_array($k, self::TUNABLE_KEYS, true)) {
                    $cfg[$k] = $v;
                }
            }
        }

        return $cfg;
    }

    /* ------------------------------------------------------------------
     | Admin-wide defaults + custom presets
     |------------------------------------------------------------------*/

    /**
     * Read the admin-wide Performance Coach settings blob.
     * Shape: ['preset' => string, 'overrides' => array, 'custom_presets' => array].
     */
    public static function adminSettings(): array
    {
        $raw = \App\Modules\Admin\Models\AppSetting::get('coach_defaults', []);
        return is_array($raw) ? $raw : [];
    }

    /**
     * Admin-published custom presets, each shaped like:
     *   ['key' => 'org_landing', 'label' => 'Our Landing', 'description' => '...',
     *    'values' => [TUNABLE_KEY => number, ...]]
     *
     * @return array<int,array{key:string,label:string,description:string,values:array<string,float>}>
     */
    public static function adminCustomPresets(): array
    {
        $admin = self::adminSettings();
        $out = [];
        foreach (($admin['custom_presets'] ?? []) as $p) {
            if (!is_array($p) || empty($p['key']) || empty($p['label'])) continue;
            $values = [];
            foreach (self::TUNABLE_KEYS as $k) {
                if (isset($p['values'][$k]) && is_numeric($p['values'][$k])) {
                    $values[$k] = self::clampTunable($k, (float) $p['values'][$k]);
                }
            }
            if (count($values) !== count(self::TUNABLE_KEYS)) continue;
            $out[] = [
                'key'         => (string) $p['key'],
                'label'       => (string) $p['label'],
                'description' => (string) ($p['description'] ?? ''),
                'values'      => $values,
            ];
        }
        return $out;
    }

    /**
     * All preset keys a link is allowed to reference: built-ins + admin
     * custom presets + 'custom' (per-link hand-tuned values).
     *
     * @return array<int,string>
     */
    public static function validPresetKeys(): array
    {
        $keys = array_keys(self::PRESETS);
        foreach (self::adminCustomPresets() as $p) {
            $keys[] = $p['key'];
        }
        $keys[] = 'custom';
        return $keys;
    }

    /**
     * Menu of presets available in the per-link picker (built-ins + admin
     * custom presets, same shape as self::PRESETS entries keyed by slug).
     *
     * @return array<string,array{label:string,description:string,values:array<string,float>}>
     */
    public static function availablePresets(): array
    {
        $out = [];
        foreach (self::PRESETS as $k => $meta) {
            $out[$k] = [
                'label'       => $meta['label'],
                'description' => $meta['description'],
                'values'      => $meta['values'],
            ];
        }
        foreach (self::adminCustomPresets() as $p) {
            // Admin-custom keys can collide with built-ins only by mistake;
            // built-ins win so a typo can't silently replace 'creator'.
            if (isset($out[$p['key']])) continue;
            $out[$p['key']] = [
                'label'       => $p['label'],
                'description' => $p['description'] ?: 'Workspace preset',
                'values'      => $p['values'],
            ];
        }
        return $out;
    }

    /**
     * Values array for a given preset key. Unknown keys fall back to the
     * 'creator' preset so scoring never fails on stale / removed presets.
     */
    public static function presetValues(string $key): array
    {
        $all = self::availablePresets();
        return $all[$key]['values'] ?? (self::PRESETS['creator']['values'] ?? []);
    }

    /**
     * Persist admin-wide default preset + (optional) custom overrides +
     * (optional) published custom presets. Returns the canonical blob stored.
     */
    public static function saveAdminSettings(string $preset, array $overrides = [], array $customPresets = []): array
    {
        // Canonicalize custom presets first so a 'preset' pointing to one of
        // them survives validation against the updated preset list.
        $cleanCustoms = [];
        foreach ($customPresets as $p) {
            if (!is_array($p)) continue;
            $key = strtolower(trim((string) ($p['key'] ?? '')));
            $label = trim((string) ($p['label'] ?? ''));
            if ($key === '' || $label === '') continue;
            if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,31}$/', $key)) continue;
            if (isset(self::PRESETS[$key]) || $key === 'custom') continue;

            $values = [];
            foreach (self::TUNABLE_KEYS as $k) {
                $v = $p['values'][$k] ?? null;
                if (!is_numeric($v)) { $values = []; break; }
                $values[$k] = self::clampTunable($k, (float) $v);
            }
            if (count($values) !== count(self::TUNABLE_KEYS)) continue;

            $cleanCustoms[$key] = [
                'key'         => $key,
                'label'       => $label,
                'description' => trim((string) ($p['description'] ?? '')),
                'values'      => $values,
            ];
        }
        $cleanCustoms = array_values($cleanCustoms);

        // Validate preset against built-ins + the just-canonicalized customs.
        $validKeys = array_keys(self::PRESETS);
        foreach ($cleanCustoms as $p) { $validKeys[] = $p['key']; }
        $validKeys[] = 'custom';
        if (!in_array($preset, $validKeys, true)) $preset = 'creator';

        $cleanOverrides = [];
        if ($preset === 'custom') {
            foreach (self::TUNABLE_KEYS as $k) {
                if (array_key_exists($k, $overrides) && is_numeric($overrides[$k])) {
                    $cleanOverrides[$k] = self::clampTunable($k, (float) $overrides[$k]);
                }
            }
        }

        $blob = [
            'preset'         => $preset,
            'overrides'      => $cleanOverrides,
            'custom_presets' => $cleanCustoms,
        ];
        \App\Modules\Admin\Models\AppSetting::put('coach_defaults', $blob);
        return $blob;
    }

    /**
     * Clamp a user-supplied tunable value into a sane range so a typo
     * can't break scoring (e.g. negative CTR, 500% bounce).
     */
    public static function clampTunable(string $key, float $value): float
    {
        return match ($key) {
            'ctr_critical', 'ctr_warning', 'ctr_excellent'
                => max(0.0, min(1.0, $value)),
            'bounce_critical', 'bounce_warning', 'bounce_excellent'
                => max(0.0, min(100.0, $value)),
            'engagement_low_seconds', 'engagement_excellent_seconds'
                => max(1.0, min(600.0, $value)),
            'momentum_drop_critical', 'momentum_drop_warning'
                => max(-1.0, min(0.0, $value)),
            'momentum_win_threshold'
                => max(0.0, min(5.0, $value)),
            default => $value,
        };
    }

    /**
     * Persist preset + optional overrides onto the link's settings JSON.
     * Returns the canonicalized performance-coach block now stored.
     */
    public static function saveLinkSettings(Link $link, string $preset, array $overrides = []): array
    {
        $validPresets = self::validPresetKeys();
        if (!in_array($preset, $validPresets, true)) $preset = 'creator';

        $clean = [];
        if ($preset === 'custom') {
            foreach (self::TUNABLE_KEYS as $k) {
                if (array_key_exists($k, $overrides) && is_numeric($overrides[$k])) {
                    $clean[$k] = self::clampTunable($k, (float) $overrides[$k]);
                }
            }
        }

        $settings = $link->settings ?? [];
        if (!is_array($settings)) $settings = [];
        $settings['performance_coach'] = [
            'preset'    => $preset,
            'overrides' => $clean,
        ];
        $link->settings = $settings;
        $link->save();

        return $settings['performance_coach'];
    }

    private static function pixelsUrl(): string
    {
        return route('user.pixels.index');
    }
}
