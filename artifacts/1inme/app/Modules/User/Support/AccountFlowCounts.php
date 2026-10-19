<?php

namespace App\Modules\User\Support;

use App\Services\Planner\PlannerResources;
use Illuminate\Support\Facades\Cache;

/**
 * How much of each resource kind this workspace actually holds.
 *
 * The folder Flow tab answers "what is in THIS folder". The dashboard asks the
 * wider question -- what does this account use at all, and what has it never
 * switched on -- so the counts are workspace-wide.
 *
 * Nineteen COUNT(*) round-trips is not something to do on the way to a first
 * paint. DashboardController's own comment puts a round-trip to a distant DB
 * at "hundreds of ms", so the whole sweep is seconds. Two things keep that off
 * the critical path: the panel is fetched after the dashboard has rendered
 * (see user.dashboard.flow), and the result is cached per workspace.
 *
 * TTL is deliberately short. This panel is an orientation aid, not a ledger --
 * a count that is a few minutes stale costs nothing, whereas a count that is
 * WRONG (invented, or a placeholder) would make the whole panel untrustworthy.
 * Everything shown here is a real COUNT of real rows.
 */
class AccountFlowCounts
{
    public const TTL_SECONDS = 300;

    /**
     * @return array<string, array{count: int, locked: bool}>
     */
    public static function for($user): array
    {
        $workspace = app()->bound('current_workspace') ? app('current_workspace') : null;
        $key = 'dash-flow-counts:v1:'.($workspace->id ?? 'none').':'.$user->id;

        return Cache::remember($key, self::TTL_SECONDS, fn () => self::compute($user, $workspace));
    }

    /**
     * Counted fresh, no cache. Used by the tests, and by anything that needs
     * to be sure it is looking at the database rather than at Redis.
     *
     * @return array<string, array{count: int, locked: bool}>
     */
    public static function compute($user, $workspace = null): array
    {
        $resources = app(PlannerResources::class);
        $out = [];

        foreach (array_keys(PlannerResources::TYPES) as $kind) {
            // A kind the viewer cannot see is NOT the same as a kind they have
            // none of. PlannerResources::query() returns an impossible query
            // for both, so asking the permission directly is the only way to
            // tell them apart -- and telling a team member to "set up" a thing
            // their role forbids is worse than not showing it at all.
            $locked = $workspace !== null
                && ! $user->canInWorkspace($workspace, PlannerResources::permissionFor($kind));

            $out[$kind] = [
                'count'  => $locked ? 0 : (int) $resources->query($kind, $user)->count(),
                'locked' => $locked,
            ];
        }

        return $out;
    }

    public static function forget($user, $workspace = null): void
    {
        $workspace = $workspace ?: (app()->bound('current_workspace') ? app('current_workspace') : null);
        Cache::forget('dash-flow-counts:v1:'.($workspace->id ?? 'none').':'.$user->id);
    }
}
