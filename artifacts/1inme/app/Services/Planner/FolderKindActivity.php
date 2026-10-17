<?php

namespace App\Services\Planner;

use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Observers\PlannerResourceActivity;
use Illuminate\Support\Facades\DB;

/**
 * Who has been working in each kind of thing this folder holds.
 *
 * Sana, 2026-10-17: "staffs basically make changes does actions in
 * corresponding modules... only those changes should noted apart from main
 * owner of workspace who is always."
 *
 * FolderPeople already answers "who worked in this folder", but it reads
 * TaskActivity and TaskTimeEntry, so it only ever sees task cards. For the
 * other eighteen kinds it has nothing to read. This reads the workspace
 * activity ledger instead, which since PlannerResourceActivity covers every
 * kind, and reports it per kind so the map can show it on each station.
 *
 * SCOPED TO THIS FOLDER, NOT THE WORKSPACE. The ledger has no folder column:
 * a row names a kind and an id. So the folder's own resource ids are the
 * filter, taken from the same $resources the map is drawn from. A member who
 * edits a form in another folder does not appear on this folder's Forms
 * station, which is the only reading of "works in this module" that is true.
 *
 * THE OWNER IS EXCLUDED, BY NAME AND ON PURPOSE. Sana's words: the owner is
 * always there, so counting them tells you nothing about who else is. Their
 * rows are counted into owner_changes rather than dropped, so the figures
 * still add up and the view can say what it left out.
 *
 * WHAT THIS CANNOT SAY. AI staff. An ai_staff row has a user_id, a name and a
 * domain -- no folder, no resource, nothing tying it to a station -- and
 * actor_user_id points at users, so an AI staff member cannot appear in the
 * ledger at all. Assigning AI staff to modules is a product change, not a
 * rendering one, and until it exists the map says nothing about it rather
 * than guessing from the domain field.
 */
class FolderKindActivity
{
    /** Matches FolderPeople's window, so the two panels agree. */
    public const WINDOW_DAYS = 30;

    /** How many faces a station shows before it collapses into "+n". */
    public const FACES = 3;

    /**
     * Kinds whose coverage began with the observer. `link` is left out: it has
     * been recorded by LinkController since long before, so including it would
     * date the ledger years earlier than it really covers the other eighteen.
     *
     * @return list<string>
     */
    public static function newlyCoveredKinds(): array
    {
        return array_values(array_diff(
            array_keys(PlannerResources::TYPES),
            PlannerResourceActivity::ALREADY_RECORDED,
        ));
    }

    public function data($project, array $resources): array
    {
        $workspace = Workspace::find($project->workspace_id);
        $ownerId = (int) ($workspace->owner_user_id ?? 0);

        $blank = [
            'byKind' => [],
            'people' => [],
            'counted' => 0,
            'owner_changes' => 0,
            'unattributed' => 0,
            'total' => 0,
            'window_days' => self::WINDOW_DAYS,
            'first_recorded_at' => $this->firstRecordedAt($project),
            'owner_name' => $workspace?->owner?->name,
        ];

        // The folder's own ids, per kind. Attribution covers every kind,
        // `link` included -- the exclusion in the observer is only about not
        // writing link rows twice, not about hiding who worked on links.
        $ids = [];
        foreach ($resources as $item) {
            $kind = $item['kind'] ?? null;
            $id = $item['row']->id ?? null;
            if ($kind !== null && $id !== null) {
                $ids[$kind][] = (int) $id;
            }
        }

        if ($ids === []) {
            return $blank;
        }

        $rows = DB::table('workspace_activity_events')
            ->where('workspace_id', $project->workspace_id)
            ->where('created_at', '>=', now()->subDays(self::WINDOW_DAYS))
            ->where(function ($query) use ($ids) {
                foreach ($ids as $kind => $list) {
                    $query->orWhere(fn ($w) => $w->where('object_type', $kind)->whereIn('object_id', $list));
                }
            })
            ->get(['object_type', 'object_id', 'actor_user_id', 'created_at']);

        $byKind = [];
        $ownerChanges = 0;
        $unattributed = 0;
        $counted = 0;

        foreach ($rows as $row) {
            $actor = $row->actor_user_id === null ? null : (int) $row->actor_user_id;

            // Every row lands in exactly one of the three, so the three add
            // back up to the total and no change is silently dropped.
            if ($actor === null) {
                $unattributed++;
                continue;
            }
            if ($actor === $ownerId) {
                $ownerChanges++;
                continue;
            }

            $counted++;
            $kind = (string) $row->object_type;
            $byKind[$kind][$actor]['changes'] = ($byKind[$kind][$actor]['changes'] ?? 0) + 1;
            $last = $byKind[$kind][$actor]['last_at'] ?? null;
            if ($last === null || $row->created_at > $last) {
                $byKind[$kind][$actor]['last_at'] = $row->created_at;
            }
        }

        $actorIds = [];
        foreach ($byKind as $actors) {
            foreach (array_keys($actors) as $actorId) {
                $actorIds[$actorId] = true;
            }
        }

        $users = $actorIds
            ? User::whereIn('id', array_keys($actorIds))->get()->keyBy('id')
            : collect();

        $stations = [];
        $folderTotals = [];

        foreach ($byKind as $kind => $actors) {
            $people = [];
            foreach ($actors as $actorId => $tally) {
                $user = $users->get($actorId);
                if (! $user) {
                    // The member was deleted since. Their changes stay in the
                    // counted total; there is just no face to put on them.
                    continue;
                }
                $people[] = [
                    'id' => (int) $actorId,
                    'name' => $user->name,
                    'avatar_url' => $user->resolveAvatarUrl(),
                    'changes' => $tally['changes'],
                    'last_at' => $tally['last_at'],
                ];
                $folderTotals[$actorId]['name'] = $user->name;
                $folderTotals[$actorId]['avatar_url'] = $user->resolveAvatarUrl();
                $folderTotals[$actorId]['changes'] = ($folderTotals[$actorId]['changes'] ?? 0) + $tally['changes'];
            }

            usort($people, fn ($a, $b) => $b['changes'] <=> $a['changes'] ?: strcmp($a['name'], $b['name']));

            $stations[$kind] = [
                'people' => $people,
                'changes' => array_sum(array_column($people, 'changes')),
            ];
        }

        $folderPeople = [];
        foreach ($folderTotals as $actorId => $tally) {
            $folderPeople[] = ['id' => (int) $actorId] + $tally;
        }
        usort($folderPeople, fn ($a, $b) => $b['changes'] <=> $a['changes'] ?: strcmp($a['name'], $b['name']));

        return [
            'byKind' => $stations,
            'people' => $folderPeople,
            'counted' => $counted,
            'owner_changes' => $ownerChanges,
            'unattributed' => $unattributed,
            'total' => $rows->count(),
            'window_days' => self::WINDOW_DAYS,
            'first_recorded_at' => $blank['first_recorded_at'],
            'owner_name' => $blank['owner_name'],
        ];
    }

    /**
     * When the ledger started covering the kinds the observer records, for
     * this workspace. A station with nobody on it means "nobody since then",
     * not "nobody ever", and the view has to be able to say which.
     */
    private function firstRecordedAt($project): ?string
    {
        $first = DB::table('workspace_activity_events')
            ->where('workspace_id', $project->workspace_id)
            ->whereIn('object_type', self::newlyCoveredKinds())
            ->min('created_at');

        return $first === null ? null : (string) $first;
    }
}
