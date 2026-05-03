<?php

namespace App\Modules\User\Services;

use App\Modules\User\Models\FanLeaderboardSetting;
use App\Modules\User\Models\FanPoint;
use App\Modules\User\Models\Link;
use Illuminate\Support\Facades\DB;

/**
 * Append-only points engine that powers the per-biolink fan leaderboard.
 *
 * award() writes a row to fan_points with the configured point value for an
 * action; topFans() aggregates the ledger to render the public leaderboard.
 */
class FanPointsEngine
{
    public function rulesFor(Link $link): array
    {
        $settings = FanLeaderboardSetting::query()
            ->withoutGlobalScope('workspace')
            ->where('link_id', $link->id)
            ->first();
        return array_merge(
            FanLeaderboardSetting::defaultRules(),
            $settings?->point_rules ?? []
        );
    }

    /**
     * Record an action by a viewer/member that earns points on a biolink.
     * Silent no-op if the leaderboard is disabled or the rule weight is 0.
     */
    public function award(
        Link $link,
        string $action,
        $subject,
        ?int $viewerUserId = null,
        ?string $fingerprint = null,
        ?string $displayName = null,
        array $meta = []
    ): ?FanPoint {
        $settings = FanLeaderboardSetting::query()
            ->withoutGlobalScope('workspace')
            ->where('link_id', $link->id)
            ->first();
        if (!$settings || !$settings->is_enabled) return null;

        $points = (int)($this->rulesFor($link)[$action] ?? 0);
        if ($points === 0) return null;

        if (!$viewerUserId && !$fingerprint) return null;

        return FanPoint::create([
            'user_id'           => $link->user_id,
            'link_id'           => $link->id,
            'workspace_id'      => $link->workspace_id ?? null,
            'viewer_user_id'    => $viewerUserId,
            'voter_fingerprint' => $fingerprint,
            'display_name'      => $displayName,
            'action'            => $action,
            'points'            => $points,
            'subject_id'        => is_object($subject) ? $subject->getKey() : null,
            'subject_type'      => is_object($subject) ? get_class($subject) : null,
            'meta'              => $meta,
        ]);
    }

    /**
     * Aggregate the points ledger for a link into a ranked top-N list.
     * Groups by viewer_user_id when available, otherwise voter_fingerprint.
     */
    public function topFans(Link $link, int $limit = 10): array
    {
        // Group by viewer_user_id when present so a single logged-in fan
        // aggregates across multiple sessions/fingerprints. Anonymous
        // rows fall back to grouping by voter_fingerprint via a synthetic
        // identity expression, so two devices for the same logged-in
        // user collapse into one ranked entry instead of being split.
        $identity = "COALESCE(CAST(viewer_user_id AS CHAR), CONCAT('fp:', voter_fingerprint))";
        $rows = FanPoint::query()
            ->withoutGlobalScope('workspace')
            ->where('link_id', $link->id)
            ->selectRaw("
                {$identity} as identity,
                MAX(viewer_user_id) as viewer_user_id,
                MAX(display_name) as display_name,
                SUM(points) as total
            ")
            ->groupByRaw($identity)
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        // Note: voter_fingerprint is intentionally not returned — it's a
        // session-derived identifier that should never leak to the public.
        $out = [];
        $rank = 0;
        foreach ($rows as $r) {
            $rank++;
            $out[] = [
                'rank'  => $rank,
                'name'  => $r->display_name ?: 'Anonymous fan',
                'total' => (int)$r->total,
            ];
        }
        return $out;
    }
}
