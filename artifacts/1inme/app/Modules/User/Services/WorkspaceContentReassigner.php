<?php

namespace App\Modules\User\Services;

use App\Modules\User\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Counts and reassigns content authored by a workspace member.
 *
 * "Authored" means the row was created by that member — the
 * `created_by_user_id` column populated by the workspace migration.
 * The owning `user_id` (= workspace owner) is never changed; only the
 * attribution column moves so removing a teammate doesn't orphan their
 * drafts and posts.
 */
class WorkspaceContentReassigner
{
    /**
     * Tables that carry a `created_by_user_id` and are worth surfacing
     * in the "owned content" warning when removing a seat. Keeping the
     * list short and user-recognisable on purpose — internal join
     * tables aren't useful here.
     */
    public const TRACKED = [
        'links'         => 'links',
        'creator_posts' => 'posts',
        'forms'         => 'forms',
        'projects'      => 'projects',
        'qr_codes'      => 'QR codes',
        'splash_pages'  => 'splash pages',
    ];

    /** Count rows in each tracked table authored by $userId in $workspace. */
    public function countForMember(Workspace $workspace, int $userId): array
    {
        $out = [];
        foreach (self::TRACKED as $table => $label) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'created_by_user_id')) {
                continue;
            }
            $q = DB::table($table)->where('created_by_user_id', $userId);
            if (Schema::hasColumn($table, 'workspace_id')) {
                $q->where('workspace_id', $workspace->id);
            }
            $count = (int) $q->count();
            if ($count > 0) {
                $out[] = ['table' => $table, 'label' => $label, 'count' => $count];
            }
        }
        return $out;
    }

    /** Total count across all tracked tables for $userId in $workspace. */
    public function totalForMember(Workspace $workspace, int $userId): int
    {
        return array_sum(array_column($this->countForMember($workspace, $userId), 'count'));
    }

    /**
     * Reassign every authored row from $fromUserId to $toUserId within
     * $workspace. Returns the number of rows updated.
     */
    public function reassign(Workspace $workspace, int $fromUserId, int $toUserId): int
    {
        if ($fromUserId === $toUserId) return 0;
        $updated = 0;
        DB::transaction(function () use ($workspace, $fromUserId, $toUserId, &$updated) {
            foreach (array_keys(self::TRACKED) as $table) {
                if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'created_by_user_id')) {
                    continue;
                }
                $q = DB::table($table)->where('created_by_user_id', $fromUserId);
                if (Schema::hasColumn($table, 'workspace_id')) {
                    $q->where('workspace_id', $workspace->id);
                }
                $updated += $q->update(['created_by_user_id' => $toUserId]);
            }
        });
        return $updated;
    }
}
